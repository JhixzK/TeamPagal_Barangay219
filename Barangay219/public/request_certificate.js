const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const topDateBadge = document.getElementById("topDateBadge");
const mainDateBadge = document.getElementById("mainDateBadge");

const requestForm = document.getElementById("requestForm");
const certificateType = document.getElementById("certificateType");
const purpose = document.getElementById("purpose");
const purposeOtherWrap = document.getElementById("purposeOtherWrap");
const purposeOther = document.getElementById("purposeOther");
const cancelBtn = document.getElementById("cancelBtn");
const submissionResult = document.getElementById("submissionResult");
const referenceNumber = document.getElementById("referenceNumber");

const uploadBox = document.getElementById("uploadBox");
const documentsInput = document.getElementById("documents");
const browseBtn = document.getElementById("browseBtn");
const fileList = document.getElementById("fileList");

const summaryCertificate = document.getElementById("summaryCertificate");
const summaryPurpose = document.getElementById("summaryPurpose");
const summaryDocuments = document.getElementById("summaryDocuments");

const additionalFieldEls = [...document.querySelectorAll(".additional-field")];
const allAdditionalInputs = {
  yearsResidency: document.getElementById("yearsResidency"),
  monthlyIncome: document.getElementById("monthlyIncome"),
  businessName: document.getElementById("businessName"),
  businessAddress: document.getElementById("businessAddress"),
  dependents: document.getElementById("dependents")
};

const requiredByCertificate = {
  "Barangay Clearance": ["yearsResidency"],
  "Certificate of Residency": ["yearsResidency"],
  "Certificate of Indigency": ["monthlyIncome", "dependents"],
  "Certificate of Good Moral Character": ["yearsResidency"],
  "Business Clearance": ["businessName", "businessAddress"],
  "Barangay ID Request": []
};

let selectedFiles = [];
const CREATE_CERTIFICATE_API_URL = "../api/certificates/create.php";

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

function setError(id, message) {
  const target = document.getElementById(id);
  if (target) {
    target.textContent = message;
  }
}

function clearAllErrors() {
  [
    "certificateTypeError",
    "purposeError",
    "purposeOtherError",
    "documentsError",
    "yearsResidencyError",
    "monthlyIncomeError",
    "businessNameError",
    "businessAddressError",
    "dependentsError"
  ].forEach((errorId) => setError(errorId, ""));
}

function showRelevantAdditionalFields() {
  const selectedType = certificateType.value;
  const requiredFields = requiredByCertificate[selectedType] || [];

  additionalFieldEls.forEach((fieldEl) => {
    const key = fieldEl.getAttribute("data-key");
    const shouldShow = requiredFields.includes(key);
    fieldEl.classList.toggle("hidden", !shouldShow);
    if (!shouldShow && allAdditionalInputs[key]) {
      allAdditionalInputs[key].value = "";
      setError(key + "Error", "");
    }
  });
}

function updateSummary() {
  const certType = certificateType.value;
  summaryCertificate.textContent = certType || "-";
  summaryPurpose.textContent = purpose.value === "Others" ? (purposeOther.value || "Others") : (purpose.value || "-");
  summaryDocuments.textContent = selectedFiles.length ? selectedFiles.map((file) => file.name).join(", ") : "None";
}

function togglePurposeOther() {
  const showOther = purpose.value === "Others";
  purposeOtherWrap.classList.toggle("hidden", !showOther);
  if (!showOther) {
    purposeOther.value = "";
    setError("purposeOtherError", "");
  }
  updateSummary();
}

function isValidFile(file) {
  const allowedTypes = ["image/jpeg", "image/png", "application/pdf"];
  const maxSize = 5 * 1024 * 1024;
  return allowedTypes.includes(file.type) && file.size <= maxSize;
}

function renderFiles() {
  fileList.innerHTML = "";
  if (!selectedFiles.length) {
    return;
  }

  selectedFiles.forEach((file, index) => {
    const item = document.createElement("li");
    const fileText = document.createElement("span");
    const removeBtn = document.createElement("button");

    fileText.textContent = file.name + " (" + Math.ceil(file.size / 1024) + " KB)";
    removeBtn.type = "button";
    removeBtn.textContent = "Remove";
    removeBtn.className = "btn-secondary";
    removeBtn.addEventListener("click", () => {
      selectedFiles.splice(index, 1);
      renderFiles();
      updateSummary();
    });

    item.appendChild(fileText);
    item.appendChild(removeBtn);
    fileList.appendChild(item);
  });
}

function appendSelectedFiles(fileCollection) {
  const incomingFiles = Array.from(fileCollection);
  const invalidFiles = incomingFiles.filter((file) => !isValidFile(file));

  if (invalidFiles.length) {
    setError("documentsError", "Only JPG, PNG, PDF up to 5MB are allowed.");
  } else {
    setError("documentsError", "");
  }

  incomingFiles
    .filter((file) => isValidFile(file))
    .forEach((file) => {
      const duplicate = selectedFiles.some((existing) => existing.name === file.name && existing.size === file.size);
      if (!duplicate) {
        selectedFiles.push(file);
      }
    });

  renderFiles();
  updateSummary();
}

function validateAdditionalFields() {
  let isValid = true;
  const selectedType = certificateType.value;
  const requiredFields = requiredByCertificate[selectedType] || [];

  requiredFields.forEach((key) => {
    const input = allAdditionalInputs[key];
    if (!input || !input.value.trim()) {
      setError(key + "Error", "This field is required.");
      isValid = false;
    } else {
      setError(key + "Error", "");
    }
  });

  return isValid;
}

function validateForm() {
  clearAllErrors();
  let valid = true;

  if (!certificateType.value) {
    setError("certificateTypeError", "Please select a certificate type.");
    valid = false;
  }

  if (!purpose.value) {
    setError("purposeError", "Please select a purpose.");
    valid = false;
  }

  if (purpose.value === "Others" && !purposeOther.value.trim()) {
    setError("purposeOtherError", "Please specify your purpose.");
    valid = false;
  }

  if (!selectedFiles.length) {
    setError("documentsError", "Please upload at least one supporting document.");
    valid = false;
  }

  if (!validateAdditionalFields()) {
    valid = false;
  }

  return valid;
}

async function handleSubmit(event) {
  event.preventDefault();

  if (!validateForm()) {
    return;
  }

  const submitButton = requestForm.querySelector("button[type='submit']");
  if (submitButton) {
    submitButton.disabled = true;
  }

  const selectedPurpose = purpose.value === "Others" ? purposeOther.value.trim() : purpose.value;
  const formData = new FormData();
  formData.append("certificate_type", certificateType.value);
  formData.append("purpose", selectedPurpose);

  Object.entries(allAdditionalInputs).forEach(([key, input]) => {
    if (input && input.value.trim()) {
      formData.append(key, input.value.trim());
    }
  });

  selectedFiles.forEach((file, index) => {
    if (index === 0) {
      formData.append("documents", file);
    }
    formData.append("documents[]", file);
  });

  try {
    const response = await fetch(CREATE_CERTIFICATE_API_URL, {
      method: "POST",
      body: formData,
      credentials: "same-origin"
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to submit certificate request.");
    }

    const reference = result.reference_number || (result.data && result.data.reference_number) || "-";
    referenceNumber.textContent = reference;
    submissionResult.classList.remove("hidden");
    submissionResult.scrollIntoView({ behavior: "smooth", block: "nearest" });

    sessionStorage.setItem("latest_certificate_reference", reference);
    setTimeout(() => {
      window.location.href = "my_requests.php";
    }, 900);
  } catch (error) {
    setError("documentsError", error.message || "Unable to submit certificate request.");
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
    }
  }
}

function handleCancel() {
  requestForm.reset();
  selectedFiles = [];
  renderFiles();
  clearAllErrors();
  submissionResult.classList.add("hidden");
  purposeOtherWrap.classList.add("hidden");
  additionalFieldEls.forEach((fieldEl) => fieldEl.classList.add("hidden"));
  updateSummary();
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

browseBtn.addEventListener("click", () => documentsInput.click());
documentsInput.addEventListener("change", (event) => appendSelectedFiles(event.target.files));

uploadBox.addEventListener("dragover", (event) => {
  event.preventDefault();
  uploadBox.classList.add("dragover");
});

uploadBox.addEventListener("dragleave", () => {
  uploadBox.classList.remove("dragover");
});

uploadBox.addEventListener("drop", (event) => {
  event.preventDefault();
  uploadBox.classList.remove("dragover");
  appendSelectedFiles(event.dataTransfer.files);
});

certificateType.addEventListener("change", () => {
  showRelevantAdditionalFields();
  updateSummary();
});

purpose.addEventListener("change", togglePurposeOther);
purposeOther.addEventListener("input", updateSummary);
requestForm.addEventListener("submit", handleSubmit);
cancelBtn.addEventListener("click", handleCancel);
profileTrigger.addEventListener("click", toggleDropdown);
document.addEventListener("click", closeDropdownIfOutside);
menuToggle.addEventListener("click", toggleSidebarOnMobile);
window.addEventListener("resize", () => {
  if (window.innerWidth > 991) {
    sidebar.classList.remove("expanded");
  }
});

setDateBadges();
showRelevantAdditionalFields();
updateSummary();
