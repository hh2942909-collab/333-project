<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$weekId = $_GET['week_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;


// ===================== HELPERS =====================

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)));
}


// ===================== WEEKS =====================

function getAllWeeks($db) {

    $sql = "SELECT id, title, start_date, description, links, created_at FROM weeks";

    $search = $_GET['search'] ?? null;

    if ($search) {
        $sql .= " WHERE title LIKE :s OR description LIKE :s";
    }

    $sort = $_GET['sort'] ?? 'start_date';
    $order = strtolower($_GET['order'] ?? 'asc');

    $allowedSort = ['title', 'start_date'];
    $allowedOrder = ['asc', 'desc'];

    if (!in_array($sort, $allowedSort)) $sort = 'start_date';
    if (!in_array($order, $allowedOrder)) $order = 'asc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    if ($search) {
        $stmt->bindValue(':s', "%$search%");
    }

    $stmt->execute();
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$w) {
        $w['links'] = json_decode($w['links'], true) ?? [];
    }

    sendResponse(["success" => true, "data" => $weeks]);
}

function getWeekById($db, $id) {

    if (!$id || !is_numeric($id)) {
        sendResponse(["success" => false, "message" => "Invalid ID"], 400);
    }

    $stmt = $db->prepare("SELECT * FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$week) {
        sendResponse(["success" => false, "message" => "Not found"], 404);
    }

    $week['links'] = json_decode($week['links'], true) ?? [];

    sendResponse(["success" => true, "data" => $week]);
}

function createWeek($db, $data) {

    $title = sanitize($data['title'] ?? '');
    $start = sanitize($data['start_date'] ?? '');
    $desc = sanitize($data['description'] ?? '');
    $links = $data['links'] ?? [];

    if (!$title || !$start) {
        sendResponse(["success" => false, "message" => "Missing fields"], 400);
    }

    if (!validateDate($start)) {
        sendResponse(["success" => false, "message" => "Invalid date"], 400);
    }

    $links = json_encode(is_array($links) ? $links : []);

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links)
                          VALUES (?, ?, ?, ?)");

    if ($stmt->execute([$title, $start, $desc, $links])) {
        sendResponse([
            "success" => true,
            "message" => "Week created",
            "id" => $db->lastInsertId()
        ], 201);
    }

    sendResponse(["success" => false, "message" => "Insert failed"], 500);
}

function updateWeek($db, $data) {

    $id = $data['id'] ?? null;

    if (!$id) {
        sendResponse(["success" => false, "message" => "ID required"], 400);
    }

    $fields = [];
    $values = [];

    if (!empty($data['title'])) {
        $fields[] = "title=?";
        $values[] = sanitize($data['title']);
    }

    if (!empty($data['start_date'])) {
        if (!validateDate($data['start_date'])) {
            sendResponse(["success" => false, "message" => "Invalid date"], 400);
        }
        $fields[] = "start_date=?";
        $values[] = $data['start_date'];
    }

    if (isset($data['description'])) {
        $fields[] = "description=?";
        $values[] = sanitize($data['description']);
    }

    if (isset($data['links'])) {
        $fields[] = "links=?";
        $values[] = json_encode($data['links']);
    }

    if (empty($fields)) {
        sendResponse(["success" => false, "message" => "Nothing to update"], 400);
    }

    $values[] = $id;

    $sql = "UPDATE weeks SET " . implode(",", $fields) . " WHERE id=?";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($values)) {
        sendResponse(["success" => true, "message" => "Updated"]);
    }

    sendResponse(["success" => false, "message" => "Update failed"], 500);
}

function deleteWeek($db, $id) {

    if (!$id || !is_numeric($id)) {
        sendResponse(["success" => false, "message" => "Invalid ID"], 400);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id=?");

    if ($stmt->execute([$id])) {
        sendResponse(["success" => true, "message" => "Deleted"]);
    }

    sendResponse(["success" => false, "message" => "Delete failed"], 500);
}


// ===================== COMMENTS =====================

function getCommentsByWeek($db, $weekId) {

    if (!$weekId) {
        sendResponse(["success" => false, "message" => "Week ID required"], 400);
    }

    $stmt = $db->prepare("SELECT * FROM comments_week WHERE week_id=? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);

    sendResponse([
        "success" => true,
        "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function createComment($db, $data) {

    $week_id = $data['week_id'] ?? null;
    $author = sanitize($data['author'] ?? '');
    $text = sanitize($data['text'] ?? '');

    if (!$week_id || !$author || !$text) {
        sendResponse(["success" => false, "message" => "Missing fields"], 400);
    }

    $check = $db->prepare("SELECT id FROM weeks WHERE id=?");
    $check->execute([$week_id]);

    if (!$check->fetch()) {
        sendResponse(["success" => false, "message" => "Week not found"], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text)
                          VALUES (?, ?, ?)");

    if ($stmt->execute([$week_id, $author, $text])) {
        sendResponse([
            "success" => true,
            "message" => "Comment added",
            "id" => $db->lastInsertId()
        ], 201);
    }

    sendResponse(["success" => false, "message" => "Failed"], 500);
}

function deleteComment($db, $commentId) {

    if (!$commentId) {
        sendResponse(["success" => false, "message" => "Invalid ID"], 400);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id=?");

    if ($stmt->execute([$commentId])) {
        sendResponse(["success" => true, "message" => "Deleted"]);
    }

    sendResponse(["success" => false, "message" => "Failed"], 500);
}


// ===================== ROUTER =====================

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);

        } elseif ($id) {
            getWeekById($db, $id);

        } else {
            getAllWeeks($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }

    } elseif ($method === 'PUT') {
        updateWeek($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }

    } else {
        sendResponse(["success" => false, "message" => "Method not allowed"], 405);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(["success" => false, "message" => "Server error"], 500);
}