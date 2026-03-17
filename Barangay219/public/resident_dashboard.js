const requestTable = document.getElementById("requestTable");
const emptyState = document.getElementById("emptyState");
const mainDateBadge = document.getElementById("mainDateBadge");
const statCards = document.querySelectorAll(".stat-card[data-href]");

function formatToday() {
  const now = new Date();
  return now.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "2-digit"
  });
}

function setDateBadges() {
  const today = formatToday();
  if (mainDateBadge) {
    mainDateBadge.textContent = today;
  }
}

function syncRecentRequestState() {
  if (!requestTable || !emptyState) return;
  const rowCount = requestTable.querySelectorAll("tbody tr").length;
  const isEmpty = rowCount === 0;
  requestTable.hidden = isEmpty;
  emptyState.hidden = !isEmpty;
}

function navigateFromStatCard(card) {
  const target = card.getAttribute("data-href");
  if (!target) return;
  window.location.href = target;
}

statCards.forEach((card) => {
  card.addEventListener("click", () => navigateFromStatCard(card));
  card.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      navigateFromStatCard(card);
    }
  });
});

setDateBadges();
syncRecentRequestState();
