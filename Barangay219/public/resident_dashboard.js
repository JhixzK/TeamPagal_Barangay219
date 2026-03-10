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
  topDateBadge.textContent = today;
  mainDateBadge.textContent = today;
}

function toggleDropdown() {
  const expanded = profileTrigger.getAttribute("aria-expanded") === "true";
  profileTrigger.setAttribute("aria-expanded", String(!expanded));
  dropdownMenu.classList.toggle("open", !expanded);
}

function closeDropdownIfOutside(event) {
  if (!event.target.closest("#profileDropdown")) {
    profileTrigger.setAttribute("aria-expanded", "false");
    dropdownMenu.classList.remove("open");
  }
}

function toggleSidebarOnMobile() {
  sidebar.classList.toggle("expanded");
}

function syncRecentRequestState() {
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

profileTrigger.addEventListener("click", toggleDropdown);
document.addEventListener("click", closeDropdownIfOutside);
menuToggle.addEventListener("click", toggleSidebarOnMobile);
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
  if (window.innerWidth > 991) {
    sidebar.classList.remove("expanded");
  }
});

setDateBadges();
syncRecentRequestState();
