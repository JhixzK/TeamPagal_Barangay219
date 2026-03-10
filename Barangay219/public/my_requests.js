const profileTrigger = document.getElementById("profileTrigger");
const dropdownMenu = document.getElementById("dropdownMenu");
const sidebar = document.getElementById("sidebar");
const menuToggle = document.getElementById("menuToggle");
const topDateBadge = document.getElementById("topDateBadge");
const mainDateBadge = document.getElementById("mainDateBadge");

const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const tableBody = document.getElementById("requestsTableBody");
const emptyState = document.getElementById("emptyState");
const tableWrap = document.getElementById("tableWrap");
const pagination = document.getElementById("pagination");

const totalRequestsEl = document.getElementById("totalRequests");
const pendingRequestsEl = document.getElementById("pendingRequests");
const approvedRequestsEl = document.getElementById("approvedRequests");
const rejectedRequestsEl = document.getElementById("rejectedRequests");

const detailsModal = document.getElementById("detailsModal");
const modalContent = document.getElementById("modalContent");
const modalActions = document.getElementById("modalActions");
const closeModalBtn = document.getElementById("closeModalBtn");

const PAGE_SIZE = 5;
const LIST_API_URL = "../api/certificates/list.php";
const CANCEL_API_URL = "../api/certificates/cancel.php";
let currentPage = 1;
let requests = [];

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

function slugStatus(status) {
  return status.toLowerCase().replace(/\s+/g, "-");
}

function displayStatus(status) {
  const labelMap = {
    pending: "Pending",
    under_review: "Under Review",
    approved: "Approved",
    rejected: "Rejected",
    issued: "Issued",
    cancelled: "Cancelled"
  };
  return labelMap[status] || status;
}

function normalizeFilterStatus(label) {
  const map = {
    Pending: ["pending"],
    "Under Review": ["under_review"],
    Approved: ["approved"],
    Rejected: ["rejected"],
    "Ready for Pickup": ["issued"],
    Completed: ["issued"],
    Issued: ["issued"],
    Cancelled: ["cancelled"]
  };
  return map[label] || [];
}

function formatDate(dateValue) {
  const date = new Date(dateValue);
  if (Number.isNaN(date.getTime())) {
    return "-";
  }
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "2-digit"
  });
}

function filteredRequests() {
  const searchValue = searchInput.value.trim().toLowerCase();
  const selectedStatus = statusFilter.value;
  const allowedStatuses = normalizeFilterStatus(selectedStatus);

  return requests.filter((item) => {
    const matchesSearch =
      item.reference_number.toLowerCase().includes(searchValue) ||
      item.certificateType.toLowerCase().includes(searchValue);

    const matchesStatus = selectedStatus === "All" || allowedStatuses.includes(item.rawStatus);
    return matchesSearch && matchesStatus;
  });
}

function renderSummaryCards() {
  totalRequestsEl.textContent = requests.length;
  pendingRequestsEl.textContent = requests.filter((item) => item.rawStatus === "pending").length;
  approvedRequestsEl.textContent = requests.filter((item) => item.rawStatus === "approved").length;
  rejectedRequestsEl.textContent = requests.filter((item) => item.rawStatus === "rejected").length;
}

function buildActionButtons(request) {
  const container = document.createElement("div");
  container.className = "action-group";

  const viewBtn = document.createElement("button");
  viewBtn.className = "btn-action view";
  viewBtn.textContent = "View Details";
  viewBtn.type = "button";
  viewBtn.dataset.action = "view";
  viewBtn.dataset.id = String(request.numericId);
  container.appendChild(viewBtn);

  if (request.rawStatus === "pending") {
    const cancelBtn = document.createElement("button");
    cancelBtn.className = "btn-action cancel";
    cancelBtn.textContent = "Cancel Request";
    cancelBtn.type = "button";
    cancelBtn.dataset.action = "cancel";
    cancelBtn.dataset.id = String(request.numericId);
    container.appendChild(cancelBtn);
  }

  return container;
}

function renderTableRows() {
  const list = filteredRequests();
  const totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));

  if (currentPage > totalPages) {
    currentPage = totalPages;
  }

  const start = (currentPage - 1) * PAGE_SIZE;
  const rows = list.slice(start, start + PAGE_SIZE);

  tableBody.innerHTML = "";
  rows.forEach((item) => {
    const row = document.createElement("tr");
    row.innerHTML =
      "<td>" + item.reference_number + "</td>" +
      "<td>" + item.certificateType + "</td>" +
      "<td>" + item.purpose + "</td>" +
      "<td>" + item.dateRequested + "</td>" +
      "<td><span class='badge " + slugStatus(item.status) + "'>" + item.status + "</span></td>" +
      "<td><span class='badge unpaid'>Unpaid</span></td>";

    const actionsCell = document.createElement("td");
    actionsCell.appendChild(buildActionButtons(item));
    row.appendChild(actionsCell);

    tableBody.appendChild(row);
  });

  const hasRows = list.length > 0;
  tableWrap.classList.toggle("hidden", !hasRows);
  emptyState.classList.toggle("hidden", hasRows);

  renderPagination(totalPages, hasRows);
}

function renderPagination(totalPages, showPagination) {
  pagination.innerHTML = "";
  pagination.classList.toggle("hidden", !showPagination);

  if (!showPagination) {
    return;
  }

  const prevBtn = document.createElement("button");
  prevBtn.className = "page-btn";
  prevBtn.textContent = "Previous";
  prevBtn.disabled = currentPage === 1;
  prevBtn.addEventListener("click", () => {
    if (currentPage > 1) {
      currentPage -= 1;
      renderTableRows();
    }
  });
  pagination.appendChild(prevBtn);

  for (let i = 1; i <= totalPages; i += 1) {
    const pageBtn = document.createElement("button");
    pageBtn.className = "page-btn" + (i === currentPage ? " active" : "");
    pageBtn.textContent = String(i);
    pageBtn.addEventListener("click", () => {
      currentPage = i;
      renderTableRows();
    });
    pagination.appendChild(pageBtn);
  }

  const nextBtn = document.createElement("button");
  nextBtn.className = "page-btn";
  nextBtn.textContent = "Next";
  nextBtn.disabled = currentPage === totalPages;
  nextBtn.addEventListener("click", () => {
    if (currentPage < totalPages) {
      currentPage += 1;
      renderTableRows();
    }
  });
  pagination.appendChild(nextBtn);
}

function openDetailsModal(request) {
  const uploadedDocs = request.attachmentName || "None";

  let detailsHtml = "";
  detailsHtml += "<div class='modal-row'><span>Request ID</span><strong>" + request.reference_number + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Certificate Type</span><strong>" + request.certificateType + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Purpose</span><strong>" + request.purpose + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Date Requested</span><strong>" + request.dateRequested + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Status</span><strong><span class='badge " + slugStatus(request.status) + "'>" + request.status + "</span></strong></div>";
  detailsHtml += "<div class='modal-row'><span>Payment Status</span><strong><span class='badge unpaid'>Unpaid</span></strong></div>";
  detailsHtml += "<div class='modal-row'><span>Uploaded Documents</span><strong>" + uploadedDocs + "</strong></div>";

  modalContent.innerHTML = detailsHtml;
  modalActions.innerHTML = "";

  if (request.rawStatus === "pending") {
    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "btn-primary";
    cancelBtn.textContent = "Cancel Request";
    cancelBtn.dataset.action = "cancel";
    cancelBtn.dataset.id = String(request.numericId);
    modalActions.appendChild(cancelBtn);
  }

  const closeBtn = document.createElement("button");
  closeBtn.type = "button";
  closeBtn.className = "btn-secondary";
  closeBtn.id = "closeModalFooterBtn";
  closeBtn.textContent = "Close";
  modalActions.appendChild(closeBtn);

  detailsModal.classList.remove("hidden");
}

function closeModal() {
  detailsModal.classList.add("hidden");
}

async function cancelRequest(requestId) {
  const body = new FormData();
  body.append("id", String(requestId));

  const response = await fetch(CANCEL_API_URL, {
    method: "POST",
    body,
    credentials: "same-origin"
  });

  const result = await response.json();
  if (!response.ok || !result.success) {
    throw new Error(result.message || "Unable to cancel request.");
  }
}

async function handleTableAction(event) {
  const button = event.target.closest("button[data-action]");
  if (!button) {
    return;
  }

  const action = button.dataset.action;
  const requestId = Number(button.dataset.id || 0);
  const request = requests.find((item) => item.numericId === requestId);
  if (!request) {
    return;
  }

  if (action === "view") {
    openDetailsModal(request);
    return;
  }

  if (action === "cancel") {
    const confirmCancel = window.confirm("Cancel this pending request?");
    if (!confirmCancel) {
      return;
    }

    try {
      await cancelRequest(request.numericId);
      closeModal();
      await loadRequests();
    } catch (error) {
      window.alert(error.message || "Unable to cancel request.");
    }
  }
}

function mapApiRequest(row) {
  const status = row.status || "pending";
  const attachment = row.attachment || "";
  const attachmentName = attachment ? attachment.split("/").pop() : "";

  return {
    numericId: Number(row.id),
    reference_number: row.reference_number || "-",
    certificateType: row.certificate_type || "-",
    purpose: row.purpose || "-",
    dateRequested: formatDate(row.created_at),
    status: displayStatus(status),
    rawStatus: status,
    attachmentName
  };
}

async function loadRequests() {
  const response = await fetch(LIST_API_URL, {
    method: "GET",
    credentials: "same-origin"
  });

  const result = await response.json();
  if (!response.ok || !result.success) {
    throw new Error(result.message || "Unable to load requests.");
  }

  const rows = (result.data && Array.isArray(result.data.requests)) ? result.data.requests : [];
  requests = rows.map(mapApiRequest);

  renderSummaryCards();
  renderTableRows();
}

function showLatestReferenceIfAny() {
  const latestReference = sessionStorage.getItem("latest_certificate_reference");
  if (!latestReference) {
    return;
  }

  sessionStorage.removeItem("latest_certificate_reference");
  window.alert("Request submitted. Reference Number: " + latestReference);
}

function applyInitialStatusFilterFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const raw = (params.get("status") || "").trim();
  if (!raw || !statusFilter) {
    return;
  }

  const normalized = raw.toLowerCase();
  const map = {
    all: "All",
    pending: "Pending",
    under_review: "Under Review",
    "under review": "Under Review",
    approved: "Approved",
    rejected: "Rejected",
    issued: "Ready for Pickup",
    "ready for pickup": "Ready for Pickup",
    completed: "Completed",
    cancelled: "Cancelled"
  };

  const target = map[normalized];
  if (!target) {
    return;
  }

  const exists = Array.from(statusFilter.options).some((option) => option.value === target);
  if (exists) {
    statusFilter.value = target;
  }
}

function handleFilterChange() {
  currentPage = 1;
  renderTableRows();
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

searchInput.addEventListener("input", handleFilterChange);
statusFilter.addEventListener("change", handleFilterChange);
tableBody.addEventListener("click", handleTableAction);
closeModalBtn.addEventListener("click", closeModal);
modalActions.addEventListener("click", (event) => {
  if (event.target.closest("#closeModalFooterBtn")) {
    closeModal();
    return;
  }
  if (event.target.closest("button[data-action]")) {
    handleTableAction(event);
  }
});
detailsModal.addEventListener("click", (event) => {
  if (event.target === detailsModal) {
    closeModal();
  }
});
profileTrigger.addEventListener("click", toggleDropdown);
document.addEventListener("click", closeDropdownIfOutside);
menuToggle.addEventListener("click", toggleSidebarOnMobile);
window.addEventListener("resize", () => {
  if (window.innerWidth > 991) {
    sidebar.classList.remove("expanded");
  }
});

setDateBadges();
showLatestReferenceIfAny();
applyInitialStatusFilterFromUrl();
loadRequests().catch((error) => {
  requests = [];
  renderSummaryCards();
  renderTableRows();
  window.alert(error.message || "Unable to load requests.");
});
