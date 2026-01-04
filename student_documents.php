<?php
require_once 'student_header.php';
require_once 'student_layout.php';



// Fetch document requests from database
$stmt = $conn->prepare("
    SELECT 
        request_id,
        document_type,
        purpose,
        copies,
        status,
        DATE_FORMAT(date_requested, '%M %d, %Y') as date_requested,
        DATE_FORMAT(date_processed, '%M %d, %Y') as date_processed,
        notes
    FROM document_requests
    WHERE student_code = :student_code
    ORDER BY date_requested DESC
");
$stmt->execute(['student_code' => $student_code]);
$document_requests = $stmt->fetchAll();

ob_start();
?>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 80px;"></i>
                </div>
                <h4 class="fw-bold mb-3">Success!</h4>
                <p id="successMessage" class="mb-4"></p>
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-exclamation-circle-fill text-danger" style="font-size: 80px;"></i>
                </div>
                <h4 class="fw-bold mb-3">Oops!</h4>
                <p id="errorMessage" class="mb-4"></p>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Cancel Modal -->
<div class="modal fade" id="confirmCancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-question-circle-fill text-warning" style="font-size: 80px;"></i>
                </div>
                <h4 class="fw-bold mb-3">Cancel Document Request?</h4>
                <p class="mb-4">Are you sure you want to cancel this document request? This action cannot be undone.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="button" class="btn btn-danger px-4" id="confirmCancelBtn">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<h4 class="fw-bold mb-3">Document Requests</h4>

<div class="row mb-4">
    <div class="col-md-8">
        <!-- Request Form -->
        <div class="card-box mb-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-plus"></i> Request New Document</h6>
            <form id="documentRequestForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="documentType" name="document_type" required>
                            <option value="">Select Document</option>
                            <option value="Certificate of Enrollment">Certificate of Enrollment</option>
                            <option value="Report Card">Report Card</option>
                            <option value="Good Moral Certificate">Good Moral Certificate</option>
                            <option value="Certificate of Registration">Certificate of Registration</option>
                            <option value="Transcript of Records">Transcript of Records</option>
                            <option value="Honorable Dismissal">Honorable Dismissal</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Number of Copies <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="documentCopies" name="copies" min="1" max="10" value="1" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="documentPurpose" name="purpose" rows="3" placeholder="State the purpose of this document request" required></textarea>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-send"></i> Submit Request
                </button>
            </form>
        </div>

        <!-- Request History -->
        <div class="card-box">
            <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> My Document Requests</h6>
            <div id="documentRequestsList">
                <?php if (empty($document_requests)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size: 64px; color: #e0e0e0;"></i>
                        <p class="mt-3 mb-0">No document requests yet</p>
                        <small>Submit your first document request above</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Document</th>
                                    <th class="text-center">Copies</th>
                                    <th>Date Requested</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($document_requests as $req): ?>
                                    <tr class="document-row" data-id="<?php echo $req['request_id']; ?>">
                                        <td>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($req['document_type']); ?></strong>
                                                <small class="text-muted"><?php echo htmlspecialchars($req['purpose']); ?></small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo $req['copies']; ?></span>
                                        </td>
                                        <td>
                                            <small><i class="bi bi-calendar3"></i> <?php echo $req['date_requested']; ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $status_class = [
                                                'requested' => 'warning',
                                                'processing' => 'info',
                                                'approved' => 'primary',
                                                'ready' => 'success',
                                                'claimed' => 'secondary',
                                                'rejected' => 'danger'
                                            ];
                                            $class = $status_class[$req['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?> text-white">
                                                <?php echo ucfirst($req['status']); ?>
                                            </span>
                                            <?php if ($req['notes']): ?>
                                                <br><small class="text-muted mt-1 d-block"><?php echo htmlspecialchars($req['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (in_array($req['status'], ['requested', 'processing'])): ?>
                                                <button class="btn btn-sm btn-outline-danger cancel-document-btn"
                                                    data-id="<?php echo $req['request_id']; ?>">
                                                    <i class="bi bi-x-circle"></i> Cancel
                                                </button>
                                            <?php elseif ($req['status'] === 'ready'): ?>
                                                <span class="badge bg-success text-white">
                                                    <i class="bi bi-check-circle"></i> Ready for Pick-up
                                                </span>
                                            <?php elseif ($req['status'] === 'claimed'): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-check-all"></i> Claimed
                                                    <?php if ($req['date_processed']): ?>
                                                        <br><?php echo $req['date_processed']; ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-box" style="background: linear-gradient(135deg, #e0f7fa 0%, #c4d6b7 100%);">
            <h6 class="fw-bold mb-3">
                <i class="bi bi-info-circle text-success"></i> Important Information
            </h6>
            <ul class="small mb-0" style="list-style: none; padding-left: 0;">
                <li class="mb-2"><i class="bi bi-clock text-success"></i> Processing time: 3-5 business days</li>
                <li class="mb-2"><i class="bi bi-building text-success"></i> Pick-up: Registrar's Office</li>
                <li class="mb-2"><i class="bi bi-calendar-check text-success"></i> Office hours: Mon-Fri, 8AM-5PM</li>
                <li class="mb-2"><i class="bi bi-person-badge text-success"></i> Bring valid ID when claiming</li>
                <li class="mb-0"><i class="bi bi-arrow-repeat text-success"></i> Check status regularly for updates</li>
            </ul>
        </div>

        <div class="card-box mt-3" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
            <h6 class="fw-bold mb-2">
                <i class="bi bi-question-circle text-primary"></i> Need Help?
            </h6>
            <p class="small mb-2">Contact the Registrar's Office:</p>
            <p class="small mb-1">
                <i class="bi bi-telephone-fill text-primary"></i> 
                <strong><?php echo getSystemSetting($conn, 'school_contact', '(02) 1234-5678'); ?></strong>
            </p>
            <p class="small mb-0">
                <i class="bi bi-envelope-fill text-primary"></i> 
                <strong>registrar@creativedreams.edu.ph</strong>
            </p>
        </div>
    </div>
</div>

<script>
// ============================================================
// MODAL UTILITY FUNCTIONS
// ============================================================

function showSuccessModal(message) {
    document.getElementById('successMessage').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}

function showErrorModal(message) {
    document.getElementById('errorMessage').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('errorModal'));
    modal.show();
}

function showConfirmModal(callback) {
    const modal = new bootstrap.Modal(document.getElementById('confirmCancelModal'));
    modal.show();
    
    // Remove any existing event listeners
    const confirmBtn = document.getElementById('confirmCancelBtn');
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
    
    // Add new event listener
    newBtn.addEventListener('click', function() {
        modal.hide();
        callback();
    });
}

function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

function showLoading(button) {
    const originalHTML = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    return originalHTML;
}

function hideLoading(button, originalHTML) {
    button.disabled = false;
    button.innerHTML = originalHTML;
}

// ============================================================
// DOCUMENT REQUEST HANDLERS
// ============================================================

// Document Request Form Handler
document.getElementById('documentRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHTML = showLoading(submitBtn);

    const formData = new FormData(this);
    formData.append('action', 'submit');

    fetch('document_request_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading(submitBtn, originalHTML);
        
        if (data.success) {
            showSuccessModal(data.message);
            this.reset();
            loadDocumentRequests();
        } else {
            showErrorModal(data.message);
        }
    })
    .catch(error => {
        hideLoading(submitBtn, originalHTML);
        console.error('Error:', error);
        showErrorModal('An error occurred while submitting your request. Please try again.');
    });
});

// Load document requests dynamically
function loadDocumentRequests() {
    fetch('get_document_requests.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDocumentRequestsDisplay(data.requests);
            }
        })
        .catch(error => console.error('Error loading documents:', error));
}

// Update document requests display
function updateDocumentRequestsDisplay(requests) {
    const listContainer = document.getElementById('documentRequestsList');
    
    if (!requests || requests.length === 0) {
        listContainer.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size: 64px; color: #e0e0e0;"></i>
                <p class="mt-3 mb-0">No document requests yet</p>
                <small>Submit your first document request above</small>
            </div>
        `;
        return;
    }

    const statusClasses = {
        'requested': 'warning',
        'processing': 'info',
        'approved': 'primary',
        'ready': 'success',
        'claimed': 'secondary',
        'rejected': 'danger'
    };

    let tableHTML = `
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Document</th>
                        <th class="text-center">Copies</th>
                        <th>Date Requested</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
    `;

    requests.forEach(req => {
        const statusClass = statusClasses[req.status] || 'secondary';
        const notesHTML = req.notes ? `<br><small class="text-muted mt-1 d-block">${escapeHtml(req.notes)}</small>` : '';
        
        let actionHTML = '';
        if (req.status === 'requested' || req.status === 'processing') {
            actionHTML = `
                <button class="btn btn-sm btn-outline-danger cancel-document-btn" 
                        data-id="${req.request_id}">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
            `;
        } else if (req.status === 'ready') {
            actionHTML = `
                <span class="badge bg-success text-white">
                    <i class="bi bi-check-circle"></i> Ready for Pick-up
                </span>
            `;
        } else if (req.status === 'claimed') {
            actionHTML = `
                <small class="text-muted">
                    <i class="bi bi-check-all"></i> Claimed
                    ${req.date_processed ? `<br>${req.date_processed}` : ''}
                </small>
            `;
        }

        tableHTML += `
            <tr class="document-row" data-id="${req.request_id}">
                <td>
                    <div>
                        <strong class="d-block">${escapeHtml(req.document_type)}</strong>
                        <small class="text-muted">${escapeHtml(req.purpose)}</small>
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary">${req.copies}</span>
                </td>
                <td>
                    <small><i class="bi bi-calendar3"></i> ${req.date_requested}</small>
                </td>
                <td class="text-center">
                    <span class="badge bg-${statusClass} text-white">
                        ${capitalize(req.status)}
                    </span>
                    ${notesHTML}
                </td>
                <td class="text-center">${actionHTML}</td>
            </tr>
        `;
    });

    tableHTML += `
                </tbody>
            </table>
        </div>
    `;

    listContainer.innerHTML = tableHTML;
    attachCancelHandlers();
}

// Attach cancel handlers
function attachCancelHandlers() {
    document.querySelectorAll('.cancel-document-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const requestId = this.getAttribute('data-id');
            const buttonElement = this;
            
            showConfirmModal(() => {
                const originalHTML = showLoading(buttonElement);

                const formData = new FormData();
                formData.append('action', 'cancel');
                formData.append('request_id', requestId);

                fetch('document_request_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal(data.message);
                        // Fade out and remove the row
                        const row = document.querySelector(`.document-row[data-id="${requestId}"]`);
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => loadDocumentRequests(), 300);
                        }
                    } else {
                        hideLoading(buttonElement, originalHTML);
                        showErrorModal(data.message);
                    }
                })
                .catch(error => {
                    hideLoading(buttonElement, originalHTML);
                    console.error('Error:', error);
                    showErrorModal('An error occurred while cancelling your request. Please try again.');
                });
            });
        });
    });
}

// Utility functions
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Initial attachment of cancel handlers
attachCancelHandlers();
</script>

<?php
$content = ob_get_clean();
renderLayout('Page Title', $content, 'documents', $student_info, $initials, $profile_picture_url);
?>