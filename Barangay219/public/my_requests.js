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
const closeModalFooterBtn = document.getElementById("closeModalFooterBtn");

const PAGE_SIZE = 5;
let currentPage = 1;

const requests = [
  {
    id: "BRGY-REQ-2026-000123",
    certificateType: "Barangay Clearance",
    purpose: "Employment",
    dateRequested: "March 01, 2026",
    status: "Pending",
    paymentStatus: "Unpaid",
    residentName: "Juan Dela Cruz",
    address: "Blk 12 Lot 8, Barangay 219, Tondo, Manila",
    uploadedDocuments: ["Valid-ID.jpg", "Barangay-ID.pdf"],
    rejectionReason: ""
  },
  {
    id: "BRGY-REQ-2026-000124",
    certificateType: "Certificate of Residency",
    purpose: "School Requirement",
    dateRequested: "March 02, 2026",
    status: "Under Review",
    paymentStatus: "Paid",
    residentName: "Juan Dela Cruz",
    address: "Blk 12 Lot 8, Barangay 219, Tondo, Manila",
    uploadedDocuments: ["School-Form.pdf"],
    rejectionReason: ""
  },
  {
    id: "BRGY-REQ-2026-000125",
    certificateType: "Certificate of Indigency",
    purpose: "Scholarship",
    dateRequested: "March 03, 2026",
    status: "Approved",
    paymentStatus: "Paid",
    residentName: "Juan Dela Cruz",
    address: "Blk 12 Lot 8, Barangay 219, Tondo, Manila",
    uploadedDocuments: ["Income-Declaration.pdf", "Student-ID.jpg"],
    rejectionReason: ""
  },
  {
    id: "BRGY-REQ-2026-000126",
    certificateType: "Business Clearance",
    purpose: "Business Requirement",
    dateRequested: "March 04, 2026",
    status: "Rejected",
    paymentStatus: "Unpaid",
    residentName: "Juan Dela Cruz",
    address: "Blk 12 Lot 8, Barangay 219, Tondo, Manila",
    uploadedDocuments: ["Business-Permit-Application.pdf"],
    rejectionReason: "Business address document is incomplete."
  },
  {
    id: "BRGY-REQ-2026-000127",
    certificateType: "Barangay ID Request",
    purpose: "Government Requirement",
    dateRequested: "March 05, 2026",
    status: "Ready for Pickup",
    paymentStatus: "Paid",
    residentName: "Juan Dela Cruz",
    address: "Blk 12 Lot 8, Barangay 219, Tondo, Manila",
    uploadedDocuments: ["Birth-Certificate.pdf", "2x2-Photo.jpg"],
    rejectionReason: ""
  },
  {
    id: "BRGY-REQ-2026-000128",
    certificateType: "Certificate of Good Moral Character",
    purpose: "Employment",
    dateRequested: "March 06, 2026",
    status: "Completed",
    paymentStatus: "Paid",
    residentName: "Juan Dela Cruz",
    address: "Blk 12 Lot 8, Barangay 219, Tondo, Manila",
    uploadedDocuments: ["Character-Reference.pdf"],
    rejectionReason: ""
  }
];

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

function filteredRequests() {
  const searchValue = searchInput.value.trim().toLowerCase();
  const selectedStatus = statusFilter.value;

  return requests.filter((item) => {
    const matchesSearch =
      item.id.toLowerCase().includes(searchValue) ||
      item.certificateType.toLowerCase().includes(searchValue);

    const matchesStatus = selectedStatus === "All" || item.status === selectedStatus;
    return matchesSearch && matchesStatus;
  });
}

function renderSummaryCards() {
  totalRequestsEl.textContent = requests.length;
  pendingRequestsEl.textContent = requests.filter((item) => item.status === "Pending").length;
  approvedRequestsEl.textContent = requests.filter((item) => item.status === "Approved").length;
  rejectedRequestsEl.textContent = requests.filter((item) => item.status === "Rejected").length;
}

function buildActionButtons(request) {
  const container = document.createElement("div");
  container.className = "action-group";

  const viewBtn = document.createElement("button");
  viewBtn.className = "btn-action view";
  viewBtn.textContent = "View Details";
  viewBtn.type = "button";
  viewBtn.dataset.action = "view";
  viewBtn.dataset.id = request.id;
  container.appendChild(viewBtn);

  if (request.status === "Approved") {
    const downloadBtn = document.createElement("button");
    downloadBtn.className = "btn-action download";
    downloadBtn.textContent = "Download Certificate";
    downloadBtn.type = "button";
    downloadBtn.dataset.action = "download";
    downloadBtn.dataset.id = request.id;
    container.appendChild(downloadBtn);
  }

  if (request.status === "Pending") {
    const cancelBtn = document.createElement("button");
    cancelBtn.className = "btn-action cancel";
    cancelBtn.textContent = "Cancel Request";
    cancelBtn.type = "button";
    cancelBtn.dataset.action = "cancel";
    cancelBtn.dataset.id = request.id;
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
      "<td>" + item.id + "</td>" +
      "<td>" + item.certificateType + "</td>" +
      "<td>" + item.purpose + "</td>" +
      "<td>" + item.dateRequested + "</td>" +
      "<td><span class='badge " + slugStatus(item.status) + "'>" + item.status + "</span></td>" +
      "<td><span class='badge " + item.paymentStatus.toLowerCase() + "'>" + item.paymentStatus + "</span></td>";

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
  const uploadedDocs = request.uploadedDocuments.length
    ? request.uploadedDocuments.join(", ")
    : "None";

  let detailsHtml = "";
  detailsHtml += "<div class='modal-row'><span>Request ID</span><strong>" + request.id + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Certificate Type</span><strong>" + request.certificateType + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Purpose</span><strong>" + request.purpose + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Resident Name</span><strong>" + request.residentName + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Address</span><strong>" + request.address + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Date Requested</span><strong>" + request.dateRequested + "</strong></div>";
  detailsHtml += "<div class='modal-row'><span>Status</span><strong><span class='badge " + slugStatus(request.status) + "'>" + request.status + "</span></strong></div>";
  detailsHtml += "<div class='modal-row'><span>Payment Status</span><strong><span class='badge " + request.paymentStatus.toLowerCase() + "'>" + request.paymentStatus + "</span></strong></div>";
  detailsHtml += "<div class='modal-row'><span>Uploaded Documents</span><strong>" + uploadedDocs + "</strong></div>";

  if (request.status === "Rejected") {
    detailsHtml += "<div class='modal-row'><span>Rejection Reason</span><strong>" + (request.rejectionReason || "No reason provided.") + "</strong></div>";
  }

  modalContent.innerHTML = detailsHtml;
  modalActions.innerHTML = "";

  if (request.status === "Approved") {
    const downloadBtn = document.createElement("button");
    downloadBtn.type = "button";
    downloadBtn.className = "btn-primary";
    downloadBtn.textContent = "Download Certificate";
    downloadBtn.addEventListener("click", () => {
      window.alert("Downloading certificate for " + request.id);
    });
    modalActions.appendChild(downloadBtn);
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

function handleTableAction(event) {
  const button = event.target.closest("button[data-action]");
  if (!button) {
    return;
  }

  const action = button.dataset.action;
  const requestId = button.dataset.id;
  const request = requests.find((item) => item.id === requestId);
  if (!request) {
    return;
  }

  if (action === "view") {
    openDetailsModal(request);
    return;
  }

  if (action === "download") {
    window.alert("Downloading certificate for " + request.id);
    return;
  }

  if (action === "cancel") {
    const confirmCancel = window.confirm("Cancel this pending request?");
    if (!confirmCancel) {
      return;
    }

    request.status = "Rejected";
    request.rejectionReason = "Request cancelled by resident.";
    request.paymentStatus = "Unpaid";
    renderSummaryCards();
    renderTableRows();
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
closeModalFooterBtn.addEventListener("click", closeModal);
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
renderSummaryCards();
renderTableRows();
