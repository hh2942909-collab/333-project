const weekListSection = document.getElementById('week-list-section');


// ===================== CREATE ARTICLE =====================
function createWeekArticle(week) {

    const article = document.createElement('article');

    article.innerHTML = `
        <h2>${week.title}</h2>
        <p>Starts on: ${week.start_date}</p>
        <p>${week.description}</p>
        <a href="details.html?id=${week.id}">View Details & Discussion</a>
    `;

    return article;
}


// ===================== LOAD WEEKS =====================
async function loadWeeks() {

    const res = await fetch('./api/index.php');
    const result = await res.json();

    if (result.success) {

        weekListSection.innerHTML = "";

        result.data.forEach(week => {
            weekListSection.appendChild(createWeekArticle(week));
        });
    }
}


// Run on page load
loadWeeks();