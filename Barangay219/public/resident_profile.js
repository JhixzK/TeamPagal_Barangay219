const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const topDateBadge = document.getElementById("topDateBadge");
const mainDateBadge = document.getElementById("mainDateBadge");
const idUploadInput = document.getElementById("idUploadInput");
const uploadedIdValue = document.getElementById("uploadedIdValue");
const profileApiUrl = "../api/profile.php";
let isSavingProfile = false;

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

function getRowValue(labelText) {
  const rows = document.querySelectorAll(".info-row");
  for (const row of rows) {
    const label = row.querySelector("span");
    const value = row.querySelector("strong");
    if (label && value && label.textContent.trim() === labelText) {
      return value.textContent.trim();
    }
  }
  return "";
}

function ask(label, currentValue) {
  const safeCurrent = currentValue === "N/A" ? "" : currentValue;
  const result = window.prompt(label, safeCurrent);
  if (result === null) {
    return null;
  }
  return result.trim();
}

async function saveProfileUpdates(payload) {
  if (isSavingProfile) {
    return false;
  }

  isSavingProfile = true;
  try {
    const formData = new FormData();
    formData.append("action", "update");

    Object.entries(payload).forEach(([key, value]) => {
      formData.append(key, value);
    });

    const response = await fetch(profileApiUrl, {
      method: "POST",
      body: formData
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || "Failed to update profile.");
    }

    window.alert("Profile updated successfully.");
    window.location.reload();
    return true;
  } catch (error) {
    window.alert(error.message || "Unable to save profile updates.");
    return false;
  } finally {
    isSavingProfile = false;
  }
}

async function editPersonalInfo() {
  const firstName = ask("First Name", getRowValue("First Name"));
  if (firstName === null) return;
  const middleName = ask("Middle Name", getRowValue("Middle Name"));
  if (middleName === null) return;
  const lastName = ask("Last Name", getRowValue("Last Name"));
  if (lastName === null) return;
  const suffix = ask("Suffix", getRowValue("Suffix"));
  if (suffix === null) return;
  const dateOfBirth = ask("Date of Birth (YYYY-MM-DD)", getRowValue("Date of Birth"));
  if (dateOfBirth === null) return;
  const placeOfBirth = ask("Place of Birth", getRowValue("Place of Birth"));
  if (placeOfBirth === null) return;
  const gender = ask("Gender (male/female/other)", getRowValue("Gender"));
  if (gender === null) return;
  const civilStatus = ask("Civil Status", getRowValue("Civil Status"));
  if (civilStatus === null) return;

  if (!firstName || !lastName) {
    window.alert("First name and last name are required.");
    return;
  }

  await saveProfileUpdates({
    first_name: firstName,
    middle_name: middleName,
    last_name: lastName,
    suffix: suffix,
    birth_date: dateOfBirth,
    place_of_birth: placeOfBirth,
    gender: gender,
    civil_status: civilStatus
  });
}

async function editContactInfo() {
  const mobileNumber = ask("Mobile Number", getRowValue("Mobile Number"));
  if (mobileNumber === null) return;
  const emailAddress = ask("Email Address", getRowValue("Email Address"));
  if (emailAddress === null) return;
  const emergencyName = ask("Emergency Contact Person", getRowValue("Emergency Contact Person"));
  if (emergencyName === null) return;
  const emergencyNumber = ask("Emergency Contact Number", getRowValue("Emergency Contact Number"));
  if (emergencyNumber === null) return;

  await saveProfileUpdates({
    contact_number: mobileNumber,
    email: emailAddress,
    emergency_contact_name: emergencyName,
    emergency_contact_number: emergencyNumber
  });
}

async function editAddressInfo() {
  const houseStreet = ask("House Number / Street", getRowValue("House Number / Street"));
  if (houseStreet === null) return;
  const yearsResidency = ask("Length of Residency (years)", getRowValue("Length of Residency").replace(/\D+/g, ""));
  if (yearsResidency === null) return;

  const parts = houseStreet.split(",");
  const houseNumber = (parts[0] || "").trim();
  const street = (parts.slice(1).join(",") || parts[0] || "").trim();

  await saveProfileUpdates({
    house_number: houseNumber,
    street: street,
    address: houseStreet,
    length_of_residency_years: yearsResidency
  });
}

async function editEmploymentInfo() {
  const occupation = ask("Occupation", getRowValue("Occupation"));
  if (occupation === null) return;
  const employmentStatus = ask("Employment Status", getRowValue("Employment Status"));
  if (employmentStatus === null) return;

  await saveProfileUpdates({
    occupation: occupation,
    employment_status: employmentStatus
  });
}

function handleActionClick(event) {
  const button = event.target.closest("[data-action]");
  if (!button) {
    return;
  }

  event.preventDefault();

  const action = button.getAttribute("data-action");

  if (action === "upload-id") {
    if (idUploadInput) {
      idUploadInput.click();
    }
    return;
  }

  if (action === "change-password") {
    window.alert("Redirecting to Change Password page.");
    return;
  }

  if (action === "edit-profile" || action === "edit-personal") {
    editPersonalInfo();
    return;
  }

  if (action === "edit-contact") {
    editContactInfo();
    return;
  }

  if (action === "edit-address") {
    editAddressInfo();
    return;
  }

  if (action === "edit-employment") {
    editEmploymentInfo();
    return;
  }

  if (action === "edit-household") {
    window.alert("Household details are currently read-only in this page.");
    return;
  }

  window.alert("This action is not yet available.");
}

function handleUploadChange() {
  if (!idUploadInput) {
    return;
  }

  if (!idUploadInput.files.length) {
    return;
  }

  const uploadedFile = idUploadInput.files[0].name;
  if (uploadedIdValue) {
    uploadedIdValue.textContent = uploadedFile;
  }

  window.alert("ID selected: " + uploadedFile);
}

function initProfilePage() {
  if (profileTrigger) {
    profileTrigger.addEventListener("click", toggleDropdown);
  }

  if (menuToggle) {
    menuToggle.addEventListener("click", toggleSidebarOnMobile);
  }

  if (idUploadInput) {
    idUploadInput.addEventListener("change", handleUploadChange);
  }

  document.addEventListener("click", closeDropdownIfOutside);

  // Bind directly to action buttons so edit clicks still work even if delegation is interrupted.
  document.querySelectorAll("[data-action]").forEach((button) => {
    button.addEventListener("click", handleActionClick);
  });

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
