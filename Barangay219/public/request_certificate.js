const mainDateBadge = document.getElementById("mainDateBadge");

const requestForm = document.getElementById("requestForm");
const certificateType = document.getElementById("certificateType");
const purposeFieldWrap = document.getElementById("purposeFieldWrap");
const purpose = document.getElementById("purpose");
const purposeOtherWrap = document.getElementById("purposeOtherWrap");
const purposeOther = document.getElementById("purposeOther");
const businessNameWrap = document.getElementById("businessNameWrap");
const businessAddressWrap = document.getElementById("businessAddressWrap");
const businessName = document.getElementById("businessName");
const businessAddress = document.getElementById("businessAddress");
const declaration = document.getElementById("declaration");

const uploadBox = document.getElementById("uploadBox");
const documentsInput = document.getElementById("documents");
const browseBtn = document.getElementById("browseBtn");
const fileList = document.getElementById("fileList");

const summaryCertificate = document.getElementById("summaryCertificate");
const summaryPurpose = document.getElementById("summaryPurpose");
const summaryDocuments = document.getElementById("summaryDocuments");
const requirementsList = document.getElementById("requirementsList");
const hasSavedValidId = requestForm && requestForm.dataset && requestForm.dataset.hasValidId === "1";

const requirementMap = {
  "Barangay Certificate": [
    hasSavedValidId ? "Valid ID (auto-attached from your profile)" : "Valid ID",
    "Supporting files (optional)"
  ],
  "Transfer Request": [
    hasSavedValidId ? "Valid ID (auto-attached from your profile)" : "Valid ID",
    "Supporting files (optional)"
  ],
  "Barangay Indigency": [
    hasSavedValidId ? "Valid ID (auto-attached from your profile)" : "Valid ID",
    "Supporting proof (optional)"
  ],
  "Barangay Clearance": [
    hasSavedValidId ? "Valid ID (auto-attached from your profile)" : "Valid ID",
    "Supporting files (optional)"
  ],
  "Certificate of Residency": [
    hasSavedValidId ? "Valid ID (auto-attached from your profile)" : "Valid ID",
    "Supporting files (optional)"
  ]
};

const purposeOptionsByType = {
  "Barangay Certificate": [
    "Application for Employment",
    "School Admission/Requirement",
    "Hospital Purpose",
    "Processing of Calamity",
    "Medical Purpose",
    "For Livelihood Loan",
    "Bank Transaction",
    "Indigent Family",
    "Organized Vending Permit",
    "DSWD Requirement",
    "For Travel Abroad",
    "Transfer of Residence",
    "Others"
  ],
  "Transfer Request": [
    "Transfer of Residence (Relocation)",
    "Change of Address",
    "School Credentials / TOR Transfer",
    "COMELEC Voter Transfer",
    "Land Title / Ownership Transfer",
    "Job Reassignment / Office Transfer",
    "Transfer of Business Location",
    "Other (Please Specify)"
  ],
  "Barangay Indigency": [
    "Financial Assistance",
    "Medical Purpose",
    "Hospital Purpose",
    "DSWD Requirement"
  ],
  "Barangay Clearance": [
    "Job Application",
    "National ID Application",
    "Police Clearance Requirement",
    "Bank Account Opening",
    "School Enrollment",
    "Scholarship Application",
    "Business Permit Application",
    "Passport Application",
    "Utility Connection",
    "First Time Jobseeker (RA 11261)"
  ],
  "Certificate of Residency": [
    "Application for Employment",
    "School Admission/Requirement",
    "Hospital Purpose",
    "Processing of Calamity",
    "Medical Purpose",
    "For Livelihood Loan",
    "Bank Transaction",
    "Indigent Family",
    "Organized Vending Permit",
    "DSWD Requirement",
    "For Travel Abroad",
    "Transfer of Residence"
  ]
};

const allowedMimeTypes = ["image/jpeg", "image/png", "application/pdf"];
const maxFileCount = 3;
const maxFileSize = 5 * 1024 * 1024;
let selectedFiles = [];

function setError(id, message) {
  const el = document.getElementById(id);
  if (el) {
    el.textContent = message || "";
  }
}

function clearErrors() {
  [
    "certificateTypeError",
    "purposeError",
    "purposeOtherError",
    "businessNameError",
    "businessAddressError",
    "documentsError",
    "declarationError"
  ].forEach((id) => setError(id, ""));
}

function formatToday() {
  const now = new Date();
  return now.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "2-digit"
  });
}

function setDateBadges() {
  const text = formatToday();
  if (mainDateBadge) {
    mainDateBadge.textContent = text;
  }
}

function syncInputFiles() {
  if (!documentsInput) return;
  const dataTransfer = new DataTransfer();
  selectedFiles.forEach((file) => dataTransfer.items.add(file));
  documentsInput.files = dataTransfer.files;
}

function renderFileList() {
  if (!fileList) return;
  fileList.innerHTML = "";

  if (!selectedFiles.length) {
    summaryDocuments.textContent = hasSavedValidId ? "Saved Valid ID (auto-attached)" : "None";
    return;
  }

  selectedFiles.forEach((file, index) => {
    const li = document.createElement("li");

    const meta = document.createElement("span");
    const sizeKb = Math.max(1, Math.round(file.size / 1024));
    meta.textContent = `${file.name} (${sizeKb} KB)`;

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn-remove-file";
    btn.textContent = "Remove";
    btn.addEventListener("click", () => {
      selectedFiles.splice(index, 1);
      syncInputFiles();
      renderFileList();
      updateSummary();
    });

    li.appendChild(meta);
    li.appendChild(btn);
    fileList.appendChild(li);
  });

  summaryDocuments.textContent = selectedFiles.map((file) => file.name).join(", ");
}

function updateRequirements() {
  if (!requirementsList) return;
  const selectedType = certificateType.value;
  const items = requirementMap[selectedType] || ["Select a certificate type to view document requirements."];

  requirementsList.innerHTML = "";
  items.forEach((item) => {
    const li = document.createElement("li");
    li.textContent = item;
    requirementsList.appendChild(li);
  });
}

function populatePurposeOptions() {
  if (!purpose) return;
  const selectedType = certificateType ? certificateType.value : "";
  const isIndigency = selectedType === "Barangay Indigency";

  if (purposeFieldWrap) {
    purposeFieldWrap.classList.toggle("hidden", isIndigency);
  }

  if (isIndigency) {
    purpose.value = "";
    purpose.disabled = true;
    if (purposeOtherWrap) purposeOtherWrap.classList.add("hidden");
    if (purposeOther) purposeOther.value = "";
    setError("purposeError", "");
    setError("purposeOtherError", "");
    return;
  }

  const options = purposeOptionsByType[selectedType] || [];
  const previousValue = purpose.value;

  purpose.innerHTML = "";
  const defaultOption = document.createElement("option");
  defaultOption.value = "";
  defaultOption.textContent = options.length ? "Select purpose" : "Select certificate type first";
  purpose.appendChild(defaultOption);

  options.forEach((optionText) => {
    const option = document.createElement("option");
    option.value = optionText;
    option.textContent = optionText;
    purpose.appendChild(option);
  });

  purpose.disabled = options.length === 0;
  if (options.includes(previousValue)) {
    purpose.value = previousValue;
  } else {
    purpose.value = "";
  }
}

function toggleConditionalFields() {
  if (businessNameWrap) businessNameWrap.classList.add("hidden");
  if (businessAddressWrap) businessAddressWrap.classList.add("hidden");
  if (businessName) businessName.value = "";
  if (businessAddress) businessAddress.value = "";
  setError("businessNameError", "");
  setError("businessAddressError", "");

  const isIndigency = certificateType && certificateType.value === "Barangay Indigency";
  const isBarangayCertificate = certificateType && certificateType.value === "Barangay Certificate";
  const isTransferRequest = certificateType && certificateType.value === "Transfer Request";
  const isOthers = !isIndigency && (
    (isBarangayCertificate && purpose.value === "Others")
    || (isTransferRequest && purpose.value === "Other (Please Specify)")
  );
  purposeOtherWrap.classList.toggle("hidden", !isOthers);

  if (!isOthers) {
    purposeOther.value = "";
    setError("purposeOtherError", "");
  }
}

function updateSummary() {
  const isIndigency = certificateType && certificateType.value === "Barangay Indigency";
  const isBarangayCertificate = certificateType && certificateType.value === "Barangay Certificate";
  const isTransferRequest = certificateType && certificateType.value === "Transfer Request";
  const selectedPurpose = isIndigency
    ? "Not required"
    : (((isBarangayCertificate && purpose.value === "Others")
      || (isTransferRequest && purpose.value === "Other (Please Specify)"))
        ? (purposeOther.value.trim() || purpose.value || "-")
        : (purpose.value || "-"));
  summaryCertificate.textContent = certificateType.value || "-";
  summaryPurpose.textContent = selectedPurpose;
  summaryDocuments.textContent = selectedFiles.length
    ? selectedFiles.map((file) => file.name).join(", ")
    : (hasSavedValidId ? "Saved Valid ID (auto-attached)" : "None");
}

function validatePickedFiles(files) {
  const nextFiles = Array.from(files);

  if (selectedFiles.length + nextFiles.length > maxFileCount) {
    setError("documentsError", "Maximum of 3 files only.");
    return;
  }

  for (const file of nextFiles) {
    if (!allowedMimeTypes.includes(file.type)) {
      setError("documentsError", "Only JPG, PNG, and PDF files are allowed.");
      return;
    }

    if (file.size > maxFileSize) {
      setError("documentsError", "Each file must be 5MB or below.");
      return;
    }

    const alreadyPicked = selectedFiles.some((picked) => picked.name === file.name && picked.size === file.size);
    if (!alreadyPicked) {
      selectedFiles.push(file);
    }
  }

  setError("documentsError", "");
  syncInputFiles();
  renderFileList();
  updateSummary();
}

function validateFormClient() {
  clearErrors();
  let valid = true;

  if (!certificateType.value) {
    setError("certificateTypeError", "Please select a certificate type.");
    valid = false;
  }

  const isIndigency = certificateType.value === "Barangay Indigency";
  const isBarangayCertificate = certificateType.value === "Barangay Certificate";
  const isTransferRequest = certificateType.value === "Transfer Request";
  if (!isIndigency) {
    if (!purpose.value) {
      setError("purposeError", "Please select a purpose category.");
      valid = false;
    }

    if (((isBarangayCertificate && purpose.value === "Others")
      || (isTransferRequest && purpose.value === "Other (Please Specify)")) && !purposeOther.value.trim()) {
      setError("purposeOtherError", "Please specify the purpose.");
      valid = false;
    }
  }

  if (!declaration.checked) {
    setError("declarationError", "You must agree to the declaration.");
    valid = false;
  }

  return valid;
}

if (browseBtn && documentsInput) {
  browseBtn.addEventListener("click", () => documentsInput.click());
}

if (documentsInput) {
  documentsInput.addEventListener("change", (event) => {
    validatePickedFiles(event.target.files);
  });
}

if (uploadBox) {
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
    validatePickedFiles(event.dataTransfer.files);
  });
}

if (certificateType) {
  certificateType.addEventListener("change", () => {
    populatePurposeOptions();
    toggleConditionalFields();
    updateRequirements();
    updateSummary();
  });
}

if (purpose) {
  purpose.addEventListener("change", () => {
    toggleConditionalFields();
    updateSummary();
  });
}

if (purposeOther) {
  purposeOther.addEventListener("input", updateSummary);
}

if (requestForm) {
  requestForm.addEventListener("submit", (event) => {
    const valid = validateFormClient();
    if (!valid) {
      event.preventDefault();
    }
  });

  requestForm.addEventListener("reset", () => {
    selectedFiles = [];
    setTimeout(() => {
      syncInputFiles();
      renderFileList();
      toggleConditionalFields();
      updateRequirements();
      updateSummary();
      clearErrors();
    }, 0);
  });
}

setDateBadges();
populatePurposeOptions();
toggleConditionalFields();
updateRequirements();
updateSummary();
renderFileList();
