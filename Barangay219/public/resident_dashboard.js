const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const requestTable = document.getElementById("requestTable");
const emptyState = document.getElementById("emptyState");
const topDateBadge = document.getElementById("topDateBadge");
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
  if (topDateBadge) {
    topDateBadge.textContent = today;
  }
  if (mainDateBadge) {
    mainDateBadge.textContent = today;
  }
}

function toggleDropdown() {
  if (!profileTrigger || !dropdownMenu) return;
  const expanded = profileTrigger.getAttribute("aria-expanded") === "true";
  profileTrigger.setAttribute("aria-expanded", String(!expanded));
  dropdownMenu.classList.toggle("open", !expanded);
}

function closeDropdownIfOutside(event) {
  if (!profileTrigger || !dropdownMenu) return;
  if (!event.target.closest("#profileDropdown")) {
    profileTrigger.setAttribute("aria-expanded", "false");
    dropdownMenu.classList.remove("open");
  }
}

function toggleSidebarOnMobile() {
  if (!sidebar) return;
  sidebar.classList.toggle("expanded");
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

if (profileTrigger) {
  profileTrigger.addEventListener("click", toggleDropdown);
}
if (menuToggle) {
  menuToggle.addEventListener("click", toggleSidebarOnMobile);
}
document.addEventListener("click", closeDropdownIfOutside);
statCards.forEach((card) => {
  card.addEventListener("click", () => navigateFromStatCard(card));
  card.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      navigateFromStatCard(card);
    }
  });
});
window.addEventListener("resize", () => {
  if (sidebar && window.innerWidth > 991) {
    sidebar.classList.remove("expanded");
  }
});

setDateBadges();
syncRecentRequestState();
