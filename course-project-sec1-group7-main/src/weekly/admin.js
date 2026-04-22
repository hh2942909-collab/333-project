let weeks = [];

// Element selections
const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');
const submitBtn = document.getElementById('add-week');


// ===================== CREATE ROW =====================
function createWeekRow(week) {

    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td>${week.title}</td>
        <td>${week.start_date}</td>
        <td>${week.description}</td>
        <td>
            <button class="edit-btn" data-id="${week.id}">Edit</button>
            <button class="delete-btn" data-id="${week.id}">Delete</button>
        </td>
    `;

    return tr;
}


// ===================== RENDER TABLE =====================
function renderTable() {

    weeksTbody.innerHTML = "";

    weeks.forEach(week => {
        weeksTbody.appendChild(createWeekRow(week));
    });
}


// ===================== ADD WEEK =====================
async function handleAddWeek(event) {
    event.preventDefault();

    const title = document.getElementById('week-title').value;
    const start_date = document.getElementById('week-start-date').value;
    const description = document.getElementById('week-description').value;
    const links = document.getElementById('week-links')
        .value
        .split("\n")
        .map(l => l.trim())
        .filter(l => l !== "");

    const editId = submitBtn.dataset.editId;

    // UPDATE MODE
    if (editId) {
        return handleUpdateWeek(editId, {
            title,
            start_date,
            description,
            links
        });
    }

    // CREATE MODE
    const res = await fetch('./api/index.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            title,
            start_date,
            description,
            links
        })
    });

    const result = await res.json();

    if (result.success) {

        weeks.push({
            id: result.id,
            title,
            start_date,
            description,
            links
        });

        renderTable();
        weekForm.reset();
    }
}


// ===================== UPDATE WEEK =====================
async function handleUpdateWeek(id, fields) {

    const res = await fetch('./api/index.php', {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id,
            ...fields
        })
    });

    const result = await res.json();

    if (result.success) {

        const index = weeks.findIndex(w => w.id == id);

        if (index !== -1) {
            weeks[index] = { id: Number(id), ...fields };
        }

        renderTable();
        weekForm.reset();

        submitBtn.textContent = "Add Week";
        delete submitBtn.dataset.editId;
    }
}


// ===================== TABLE CLICK (EDIT / DELETE) =====================
async function handleTableClick(event) {

    const id = event.target.dataset.id;

    // DELETE
    if (event.target.classList.contains("delete-btn")) {

        const res = await fetch(`./api/index.php?id=${id}`, {
            method: "DELETE"
        });

        const result = await res.json();

        if (result.success) {
            weeks = weeks.filter(w => w.id != id);
            renderTable();
        }
    }

    // EDIT
    if (event.target.classList.contains("edit-btn")) {

        const week = weeks.find(w => w.id == id);

        document.getElementById('week-title').value = week.title;
        document.getElementById('week-start-date').value = week.start_date;
        document.getElementById('week-description').value = week.description;
        document.getElementById('week-links').value = week.links.join("\n");

        submitBtn.textContent = "Update Week";
        submitBtn.dataset.editId = id;
    }
}


// ===================== INIT =====================
async function loadAndInitialize() {

    const res = await fetch('./api/index.php');
    const result = await res.json();

    if (result.success) {
        weeks = result.data;
        renderTable();
    }

    weekForm.addEventListener('submit', handleAddWeek);
    weeksTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();