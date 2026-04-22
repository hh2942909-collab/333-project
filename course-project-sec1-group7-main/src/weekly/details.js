let currentWeekId = null;
let currentComments = [];

// Elements
const weekTitle = document.getElementById('week-title');
const weekStartDate = document.getElementById('week-start-date');
const weekDescription = document.getElementById('week-description');
const weekLinksList = document.getElementById('week-links-list');
const commentList = document.getElementById('comment-list');
const commentForm = document.getElementById('comment-form');
const newCommentInput = document.getElementById('new-comment');


// ===================== GET ID FROM URL =====================
function getWeekIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}


// ===================== RENDER WEEK DETAILS =====================
function renderWeekDetails(week) {

    weekTitle.textContent = week.title;
    weekStartDate.textContent = "Starts on: " + week.start_date;
    weekDescription.textContent = week.description;

    weekLinksList.innerHTML = "";

    week.links.forEach(url => {
        const li = document.createElement('li');
        const a = document.createElement('a');

        a.href = url;
        a.textContent = url;
        a.target = "_blank";

        li.appendChild(a);
        weekLinksList.appendChild(li);
    });
}


// ===================== CREATE COMMENT =====================
function createCommentArticle(comment) {

    const article = document.createElement('article');

    article.innerHTML = `
        <p>${comment.text}</p>
        <footer>Posted by: ${comment.author}</footer>
    `;

    return article;
}


// ===================== RENDER COMMENTS =====================
function renderComments() {

    commentList.innerHTML = "";

    currentComments.forEach(comment => {
        commentList.appendChild(createCommentArticle(comment));
    });
}


// ===================== ADD COMMENT =====================
async function handleAddComment(event) {
    event.preventDefault();

    const text = newCommentInput.value.trim();

    if (!text) return;

    const res = await fetch('./api/index.php?action=comment', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            week_id: currentWeekId,
            author: "Student",
            text: text
        })
    });

    const result = await res.json();

    if (result.success) {

        currentComments.push({
            id: result.id,
            week_id: currentWeekId,
            author: "Student",
            text: text
        });

        renderComments();
        newCommentInput.value = "";
    }
}


// ===================== INIT PAGE =====================
async function initializePage() {

    currentWeekId = getWeekIdFromURL();

    if (!currentWeekId) {
        weekTitle.textContent = "Week not found.";
        return;
    }

    const [weekRes, commentsRes] = await Promise.all([
        fetch(`./api/index.php?id=${currentWeekId}`),
        fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
    ]);

    const weekData = await weekRes.json();
    const commentsData = await commentsRes.json();

    if (weekData.success) {
        renderWeekDetails(weekData.data);

        currentComments = commentsData.success ? commentsData.data : [];

        renderComments();

        commentForm.addEventListener('submit', handleAddComment);

    } else {
        weekTitle.textContent = "Week not found.";
    }
}


// Start
initializePage();