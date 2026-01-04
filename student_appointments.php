<?php
require_once 'student_header.php';
require_once 'student_layout.php';


// Fetch appointments from database
$stmt = $conn->prepare("
    SELECT 
        appointment_id,
        appointment_type,
        DATE_FORMAT(appointment_date, '%Y-%m-%d') as appointment_date,
        appointment_time,
        status,
        notes,
        DATE_FORMAT(created_at, '%M %d, %Y') as date_created
    FROM appointments
    WHERE student_code = :student_code
    ORDER BY appointment_date DESC, appointment_time DESC
");
$stmt->execute(['student_code' => $student_code]);
$appointments = $stmt->fetchAll();

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
                <h4 class="fw-bold mb-3">Cancel Appointment?</h4>
                <p class="mb-4">Are you sure you want to cancel this appointment? This action cannot be undone.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="button" class="btn btn-danger px-4" id="confirmCancelBtn">Yes, Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<h4 class="fw-bold mb-3">Schedule Appointment</h4>

<div class="row">
    <div class="col-md-6">
        <div class="card-box">
            <h6 class="fw-bold mb-3">Book New Appointment</h6>
            <form id="appointmentForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Appointment Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="appointmentType" name="appointment_type" required>
                        <option value="">Select Type</option>
                        <option value="Tuition Payment">💰 Tuition Payment</option>
                        <option value="Parent-Teacher Meeting">👨‍👩‍👧 Parent-Teacher Meeting</option>
                        <option value="Document Inquiry">📄 Document Inquiry</option>
                        <option value="Guidance Counseling">💭 Guidance Counseling</option>
                        <option value="Academic Concerns">📚 Academic Concerns</option>
                        <option value="Others">📋 Others</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Preferred Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="appointmentDate" name="appointment_date" required>
                    <small class="text-muted"><i class="bi bi-info-circle"></i> Weekends are not available</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Preferred Time <span class="text-danger">*</span></label>
                    <select class="form-select" id="appointmentTime" name="appointment_time" required>
                        <option value="">Select Time</option>
                        <option value="08:00 AM">🕐 08:00 AM</option>
                        <option value="09:00 AM">🕑 09:00 AM</option>
                        <option value="10:00 AM">🕙 10:00 AM</option>
                        <option value="11:00 AM">🕚 11:00 AM</option>
                        <option value="01:00 PM">🕐 01:00 PM</option>
                        <option value="02:00 PM">🕑 02:00 PM</option>
                        <option value="03:00 PM">🕒 03:00 PM</option>
                        <option value="04:00 PM">🕓 04:00 PM</option>
                    </select>
                    <small id="timeSlotMessage" class="text-muted"></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes (Optional)</label>
                    <textarea class="form-control" id="appointmentNotes" name="notes" rows="3" placeholder="Add any additional information"></textarea>
                </div>
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-calendar-plus"></i> Book Appointment
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card-box" id="appointmentsContainer">
            <h6 class="fw-bold mb-3">My Appointments</h6>
            <div id="appointmentsList">
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-x" style="font-size: 64px; color: #e0e0e0;"></i>
                        <p class="mt-3 mb-0">No appointments yet</p>
                        <small>Book your first appointment using the form</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $apt): ?>
                        <div class="appointment-card card-box mb-2" style="background-color: #f8f9fa;" data-id="<?php echo $apt['appointment_id']; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1">
                                        <i class="bi bi-bookmark-fill text-success"></i>
                                        <?php echo htmlspecialchars($apt['appointment_type']); ?>
                                    </h6>
                                    <p class="mb-1 small">
                                        <i class="bi bi-calendar3 text-primary"></i>
                                        <strong><?php echo date('F d, Y', strtotime($apt['appointment_date'])); ?></strong>
                                    </p>
                                    <p class="mb-1 small">
                                        <i class="bi bi-clock text-info"></i>
                                        <strong><?php echo $apt['appointment_time']; ?></strong>
                                    </p>
                                    <?php if ($apt['notes']): ?>
                                        <p class="mb-2 small text-muted">
                                            <i class="bi bi-chat-dots"></i>
                                            <?php echo htmlspecialchars($apt['notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($apt['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-danger mt-2 cancel-appointment-btn"
                                            data-id="<?php echo $apt['appointment_id']; ?>">
                                            <i class="bi bi-x-circle"></i> Cancel Appointment
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php
                                    $status_class = [
                                        'confirmed' => 'success',
                                        'pending' => 'warning',
                                        'completed' => 'secondary',
                                        'cancelled' => 'danger'
                                    ];
                                    $icons = [
                                        'confirmed' => 'check-circle-fill',
                                        'pending' => 'hourglass-split',
                                        'completed' => 'check-all',
                                        'cancelled' => 'x-circle-fill'
                                    ];
                                    $class = $status_class[$apt['status']] ?? 'secondary';
                                    $icon = $icons[$apt['status']] ?? 'circle';
                                    ?>
                                    <span class="badge bg-<?php echo $class; ?> text-white">
                                        <i class="bi bi-<?php echo $icon; ?>"></i>
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-box mt-3" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
            <h6 class="fw-bold mb-3">
                <i class="bi bi-info-circle text-primary"></i> Appointment Guidelines
            </h6>
            <ul class="small mb-0" style="list-style: none; padding-left: 0;">
                <li class="mb-2"><i class="bi bi-calendar-week text-primary"></i> Appointments available Monday to Friday only</li>
                <li class="mb-2"><i class="bi bi-alarm text-primary"></i> Please arrive 10 minutes before your scheduled time</li>
                <li class="mb-2"><i class="bi bi-file-earmark-text text-primary"></i> Bring necessary documents related to your appointment</li>
                <li class="mb-0"><i class="bi bi-telephone text-primary"></i> Contact the office if you need to reschedule</li>
            </ul>
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
// APPOINTMENT FORM HANDLERS
// ============================================================

const appointmentDateInput = document.getElementById('appointmentDate');
const appointmentTimeInput = document.getElementById('appointmentTime');
const timeSlotMessage = document.getElementById('timeSlotMessage');

// Set minimum date to today
const today = new Date();
appointmentDateInput.min = today.toISOString().split('T')[0];

// Disable weekends
appointmentDateInput.addEventListener('input', function() {
    const selectedDate = new Date(this.value);
    const dayOfWeek = selectedDate.getDay();

    if (dayOfWeek === 0 || dayOfWeek === 6) {
        showErrorModal('Weekends are not available for appointments. Please select a weekday (Monday-Friday).');
        this.value = '';
        return;
    }

    appointmentTimeInput.value = '';
    timeSlotMessage.textContent = 'Please select a time slot';
    timeSlotMessage.className = 'text-muted';
});

// Check time slot availability
appointmentTimeInput.addEventListener('change', function() {
    const date = appointmentDateInput.value;
    const time = this.value;

    if (!date || !time) return;

    const formData = new FormData();
    formData.append('action', 'get_booked_slots');
    formData.append('date', date);

    fetch('appointment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.booked_times.includes(time)) {
                timeSlotMessage.textContent = '⚠ This time slot is already booked. Please select another time.';
                timeSlotMessage.className = 'text-danger';
                this.value = '';
            } else {
                timeSlotMessage.textContent = '✓ This time slot is available';
                timeSlotMessage.className = 'text-success';
            }
        }
    })
    .catch(error => console.error('Error:', error));
});

// Appointment Form Submit
document.getElementById('appointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHTML = showLoading(submitBtn);

    const formData = new FormData(this);
    formData.append('action', 'book');

    fetch('appointment_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading(submitBtn, originalHTML);
        
        if (data.success) {
            showSuccessModal(data.message);
            this.reset();
            timeSlotMessage.textContent = '';
            loadAppointments();
        } else {
            showErrorModal(data.message);
        }
    })
    .catch(error => {
        hideLoading(submitBtn, originalHTML);
        console.error('Error:', error);
        showErrorModal('An error occurred while booking your appointment. Please try again.');
    });
});

// Load appointments dynamically
function loadAppointments() {
    fetch('get_appointments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAppointmentsDisplay(data.appointments);
            }
        })
        .catch(error => console.error('Error loading appointments:', error));
}

// Update appointments display
function updateAppointmentsDisplay(appointments) {
    const listContainer = document.getElementById('appointmentsList');
    
    if (!appointments || appointments.length === 0) {
        listContainer.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x" style="font-size: 64px; color: #e0e0e0;"></i>
                <p class="mt-3 mb-0">No appointments yet</p>
                <small>Book your first appointment using the form</small>
            </div>
        `;
        return;
    }

    const statusClasses = {
        'confirmed': 'success',
        'pending': 'warning',
        'completed': 'secondary',
        'cancelled': 'danger'
    };

    const statusIcons = {
        'confirmed': 'check-circle-fill',
        'pending': 'hourglass-split',
        'completed': 'check-all',
        'cancelled': 'x-circle-fill'
    };

    let html = '';

    appointments.forEach(apt => {
        const statusClass = statusClasses[apt.status] || 'secondary';
        const statusIcon = statusIcons[apt.status] || 'circle';
        const notesHTML = apt.notes ? `
            <p class="mb-2 small text-muted">
                <i class="bi bi-chat-dots"></i>
                ${escapeHtml(apt.notes)}
            </p>
        ` : '';
        
        const cancelButtonHTML = apt.status === 'pending' ? `
            <button class="btn btn-sm btn-outline-danger mt-2 cancel-appointment-btn" 
                    data-id="${apt.appointment_id}">
                <i class="bi bi-x-circle"></i> Cancel Appointment
            </button>
        ` : '';

        const appointmentDate = new Date(apt.appointment_date);
        const formattedDate = appointmentDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });

        html += `
            <div class="appointment-card card-box mb-2" style="background-color: #f8f9fa;" data-id="${apt.appointment_id}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">
                            <i class="bi bi-bookmark-fill text-success"></i>
                            ${escapeHtml(apt.appointment_type)}
                        </h6>
                        <p class="mb-1 small">
                            <i class="bi bi-calendar3 text-primary"></i>
                            <strong>${formattedDate}</strong>
                        </p>
                        <p class="mb-1 small">
                            <i class="bi bi-clock text-info"></i>
                            <strong>${apt.appointment_time}</strong>
                        </p>
                        ${notesHTML}
                        ${cancelButtonHTML}
                    </div>
                    <div>
                        <span class="badge bg-${statusClass} text-white">
                            <i class="bi bi-${statusIcon}"></i>
                            ${capitalize(apt.status)}
                        </span>
                    </div>
                </div>
            </div>
        `;
    });

    listContainer.innerHTML = html;
    attachCancelHandlers();
}

// Attach cancel handlers
function attachCancelHandlers() {
    document.querySelectorAll('.cancel-appointment-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const appointmentId = this.getAttribute('data-id');
            const buttonElement = this;
            
            showConfirmModal(() => {
                const originalHTML = showLoading(buttonElement);

                const formData = new FormData();
                formData.append('action', 'cancel');
                formData.append('appointment_id', appointmentId);

                fetch('appointment_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal(data.message);
                        // Fade out and remove the card
                        const card = document.querySelector(`.appointment-card[data-id="${appointmentId}"]`);
                        if (card) {
                            card.style.transition = 'opacity 0.3s';
                            card.style.opacity = '0';
                            setTimeout(() => loadAppointments(), 300);
                        }
                    } else {
                        hideLoading(buttonElement, originalHTML);
                        showErrorModal(data.message);
                    }
                })
                .catch(error => {
                    hideLoading(buttonElement, originalHTML);
                    console.error('Error:', error);
                    showErrorModal('An error occurred while cancelling your appointment. Please try again.');
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
renderLayout('Page Title', $content, 'appointments', $student_info, $initials, $profile_picture_url);
?>