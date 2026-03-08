const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const topDateBadge = document.getElementById("topDateBadge");
const mainDateBadge = document.getElementById("mainDateBadge");
const idUploadInput = document.getElementById("idUploadInput");
const uploadedIdValue = document.getElementById("uploadedIdValue");

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

function handleActionClick(event) {
  const button = event.target.closest("[data-action]");
  if (!button) {
    return;
  }

  const action = button.getAttribute("data-action");

  if (action === "upload-id") {
    idUploadInput.click();
    return;
  }

  if (action === "change-password") {
    window.alert("Redirecting to Change Password page.");
    return;
  }

  window.alert("Edit mode for this section can be connected to your form modal/API.");
}

function handleUploadChange() {
  if (!idUploadInput.files.length) {
    return;
  }

  const uploadedFile = idUploadInput.files[0].name;
  if (uploadedIdValue) {
    uploadedIdValue.textContent = uploadedFile;
  }

  window.alert("ID selected: " + uploadedFile);
}

profileTrigger.addEventListener("click", toggleDropdown);
document.addEventListener("click", closeDropdownIfOutside);
menuToggle.addEventListener("click", toggleSidebarOnMobile);
document.addEventListener("click", handleActionClick);
idUploadInput.addEventListener("change", handleUploadChange);
window.addEventListener("resize", () => {
  if (window.innerWidth > 991) {
    sidebar.classList.remove("expanded");
  }
});

setDateBadges();
