const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const topDateBadge = document.getElementById("topDateBadge");
const mainDateBadge = document.getElementById("mainDateBadge");

const toggleButtons = document.querySelectorAll(".toggle-btn");
const verificationIdInput = document.getElementById("verificationIdInput");
const verificationPreview = document.getElementById("verificationPreview");

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
  if (!profileTrigger || !dropdownMenu) {
    return;
  }
  const expanded = profileTrigger.getAttribute("aria-expanded") === "true";
  profileTrigger.setAttribute("aria-expanded", String(!expanded));
  dropdownMenu.classList.toggle("open", !expanded);
}

function closeDropdownIfOutside(event) {
  if (!profileTrigger || !dropdownMenu) {
    return;
  }
  if (!event.target.closest("#profileDropdown")) {
    profileTrigger.setAttribute("aria-expanded", "false");
    dropdownMenu.classList.remove("open");
  }
}

function toggleSidebarOnMobile() {
  if (!sidebar) {
    return;
  }
  sidebar.classList.toggle("expanded");
}

function closeOtherForms(exceptId) {
  document.querySelectorAll(".edit-form").forEach((form) => {
    if (form.id !== exceptId && !form.id.includes("verification")) {
      form.classList.add("hidden");
      form.classList.remove("visible");
    }
  });
}

function handleToggleButton(event) {
  const button = event.currentTarget;
  const targetId = button.getAttribute("data-target");
  if (!targetId) {
    return;
  }

  const form = document.getElementById(targetId);
  if (!form) {
    return;
  }

  const isHidden = form.classList.contains("hidden");
  closeOtherForms(targetId);

  if (isHidden) {
    form.classList.remove("hidden");
    form.classList.add("visible");
    const firstInput = form.querySelector("input");
    if (firstInput) {
      firstInput.focus();
    }
  } else {
    form.classList.add("hidden");
    form.classList.remove("visible");
  }
}

function bytesToReadable(size) {
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
  return `${(size / (1024 * 1024)).toFixed(2)} MB`;
}

function previewVerificationFile() {
  if (!verificationIdInput || !verificationPreview) {
    return;
  }

  const file = verificationIdInput.files && verificationIdInput.files[0];
  if (!file) {
    verificationPreview.textContent = "No file selected";
    return;
  }

  const isImage = file.type.startsWith("image/");
  const fileLabel = `${file.name} (${bytesToReadable(file.size)})`;

  if (!isImage) {
    verificationPreview.innerHTML = `<div><i class="fa-regular fa-file-pdf"></i><br>${fileLabel}</div>`;
    return;
  }

  const reader = new FileReader();
  reader.onload = (e) => {
    const src = e.target && e.target.result ? e.target.result : "";
    verificationPreview.innerHTML = `<div><img src="${src}" alt="ID preview"><div>${fileLabel}</div></div>`;
  };
  reader.readAsDataURL(file);
}

function initProfilePage() {
  if (profileTrigger) {
    profileTrigger.addEventListener("click", toggleDropdown);
  }

  if (menuToggle) {
    menuToggle.addEventListener("click", toggleSidebarOnMobile);
  }

  document.addEventListener("click", closeDropdownIfOutside);

  toggleButtons.forEach((button) => {
    button.addEventListener("click", handleToggleButton);
  });

  if (verificationIdInput) {
    verificationIdInput.addEventListener("change", previewVerificationFile);
  }

  window.addEventListener("resize", () => {
    if (window.innerWidth > 991 && sidebar) {
      sidebar.classList.remove("expanded");
    }
  });

  setDateBadges();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initProfilePage);
} else {
  initProfilePage();
}
