<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once 'db_connection.php';

$user_id = $_SESSION['user_id'];

// Fetch student information with parent details
$stmt = $conn->prepare("
    SELECT 
        s.*,
        u.username,
        sec.section_name,
        sec.grade_level,
        p.first_name as parent_first_name,
        p.last_name as parent_last_name,
        p.contact_number as parent_contact,
        p.email as parent_email
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    LEFT JOIN parents p ON s.parent_id = p.parent_id
    WHERE s.user_id = ?
");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch();

if (!$student_info) {
    die("Student information not found.");
}

// Get system settings
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('enrollment_status', 'current_school_year')");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$enrollment_status = $settings['enrollment_status'] ?? 'closed';
$current_year = $settings['current_school_year'] ?? date('Y');

// Calculate next academic year and next grade level
$years = explode('-', $current_year);
$next_year = ($years[0] + 1) . '-' . ($years[1] + 1);
$current_grade = $student_info['grade_level'];

// Determine next grade level (Elementary only - up to Grade 6)
$grade_progression = [
    'Preschool' => 'Grade 1',
    '1' => '2',
    '2' => '3',
    '3' => '4',
    '4' => '5',
    '5' => '6',
    '6' => 'Graduated'
];

$next_grade = $grade_progression[$current_grade] ?? 'N/A';

// Fetch student's enrollment history
$stmt = $conn->prepare("
    SELECT * FROM enrollment 
    WHERE student_code = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$student_info['student_code']]);
$enrollment_history = $stmt->fetchAll();

// Check if student already has pending enrollment for next year
$stmt = $conn->prepare("
    SELECT * FROM enrollment 
    WHERE student_code = ? 
    AND academic_year = ? 
    AND status IN ('pending', 'processing')
");
$stmt->execute([$student_info['student_code'], $next_year]);
$existing_enrollment = $stmt->fetch();

// Generate initials
$initials = strtoupper(substr($student_info['first_name'], 0, 1) . substr($student_info['last_name'], 0, 1));

// Profile picture
$profile_picture_url = !empty($student_info['profile_picture'])
    ? '../uploads/student_photos/' . $student_info['profile_picture']
    : null;

// Prepare student info array for layout
$student_layout_info = [
    'lrn' => $student_info['student_code'],
    'grade_level' => $student_info['grade_level'] ?? 'N/A',
    'section' => $student_info['section_name'] ?? 'Not Assigned'
];

// Start output buffering for content
ob_start();
?>

<style>
    .enrollment-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .enrollment-header {
        background: linear-gradient(135deg, #5a9c4e, #4a8240);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(88, 129, 87, 0.3);
    }

    .enrollment-header h2 {
        margin: 0 0 10px 0;
        font-size: 28px;
    }

    .enrollment-header p {
        margin: 0;
        opacity: 0.9;
    }

    .status-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 14px;
        margin-top: 15px;
    }

    .status-open {
        background: #4caf50;
        color: white;
    }

    .status-closed {
        background: #f44336;
        color: white;
    }

    .info-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .info-card h4 {
        color: #2d5a24;
        margin-bottom: 20px;
        font-size: 20px;
        border-bottom: 2px solid #5a9c4e;
        padding-bottom: 10px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
    }

    .info-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }

    .info-value {
        font-size: 16px;
        color: #333;
        font-weight: 600;
    }

    .enrollment-form {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 600;
    }

    .form-control {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: #5a9c4e;
    }

    .form-control:disabled {
        background: #f5f5f5;
        cursor: not-allowed;
    }

    .btn-submit {
        background: linear-gradient(135deg, #5a9c4e, #4a8240);
        color: white;
        padding: 15px 40px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(88, 129, 87, 0.3);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(88, 129, 87, 0.4);
    }

    .btn-submit:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .btn-download {
        background: linear-gradient(135deg, #2196f3, #1976d2);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        color: white;
        text-decoration: none;
    }

    .btn-delete {
        background: #f44336;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-delete:hover {
        background: #d32f2f;
        transform: translateY(-1px);
    }

    .btn-delete:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-info {
        background: #e3f2fd;
        color: #1976d2;
        border-left: 4px solid #1976d2;
    }

    .alert-warning {
        background: #fff3e0;
        color: #f57c00;
        border-left: 4px solid #f57c00;
    }

    .alert-success {
        background: #e8f5e9;
        color: #388e3c;
        border-left: 4px solid #388e3c;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .history-table th {
        background: #f5f5f5;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #e0e0e0;
    }

    .history-table td {
        padding: 12px;
        border-bottom: 1px solid #e0e0e0;
    }

    .history-table tr:hover {
        background: #f9f9f9;
    }

    .status-pill {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-pending {
        background: #fff3e0;
        color: #f57c00;
    }

    .status-processing {
        background: #e3f2fd;
        color: #1976d2;
    }

    .status-completed {
        background: #e8f5e9;
        color: #388e3c;
    }

    .status-cancelled {
        background: #ffebee;
        color: #d32f2f;
    }

    .enrollment-status-card {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 25px;
        border: 2px solid #4caf50;
    }

    .enrollment-status-card h3 {
        color: #2d5a24;
        margin-bottom: 20px;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .enrollment-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #4caf50;
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 20px;
    }

    .certificate-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
    }

    .certificate-section h5 {
        color: #2d5a24;
        margin-bottom: 15px;
        font-size: 18px;
    }

    .closed-message {
        text-align: center;
        padding: 40px 20px;
    }

    .closed-message i {
        font-size: 48px;
        color: #ff9800;
        margin-bottom: 15px;
    }

    .closed-message h4 {
        color: #333;
        margin-bottom: 10px;
    }

    .closed-message p {
        color: #666;
    }

    .slot-info {
        display: inline-block;
        margin-left: 10px;
        padding: 3px 8px;
        background: #e8f5e9;
        border-radius: 12px;
        font-size: 11px;
        color: #2e7d32;
        font-weight: 600;
    }

    .slot-warning {
        background: #fff3e0;
        color: #e65100;
    }

    .slot-full {
        background: #ffebee;
        color: #c62828;
    }

    #dateHelp {
        margin-top: 5px;
        font-size: 12px;
        color: #666;
    }
</style>

<div class="enrollment-container">
    <div class="enrollment-header">
        <h2><i class="bi bi-clipboard-check"></i> Student Enrollment</h2>
        <p>Academic Year <?php echo htmlspecialchars($enrollment_status === 'open' ? $next_year : $current_year); ?></p>
        <span class="status-badge <?php echo $enrollment_status === 'open' ? 'status-open' : 'status-closed'; ?>">
            <i class="bi bi-<?php echo $enrollment_status === 'open' ? 'unlock' : 'lock'; ?>"></i>
            Enrollment <?php echo ucfirst($enrollment_status); ?>
        </span>
    </div>

    <?php if ($enrollment_status === 'closed'): ?>
        <!-- Show Current Enrollment Status When Closed -->
        <div class="enrollment-status-card">
            <h3>
                <i class="bi bi-check-circle-fill" style="color: #4caf50;"></i>
                Current Enrollment Status
            </h3>

            <div class="enrollment-badge">
                <i class="bi bi-person-check-fill"></i>
                ENROLLED
            </div>

            <div class="info-grid" style="margin-bottom: 20px;">
                <div class="info-item">
                    <span class="info-label">Student Code (LRN)</span>
                    <span class="info-value"><?php echo htmlspecialchars($student_info['student_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Academic Year</span>
                    <span class="info-value"><?php echo htmlspecialchars($current_year); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Grade Level</span>
                    <span class="info-value"><?php echo htmlspecialchars($current_grade); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Section</span>
                    <span class="info-value"><?php echo htmlspecialchars($student_info['section_name'] ?? 'Not Assigned'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Enrollment Status</span>
                    <span class="info-value" style="color: #4caf50;">
                        <?php echo ucfirst($student_info['status']); ?>
                    </span>
                </div>
            </div>

            <div class="certificate-section">
                <h5><i class="bi bi-file-earmark-text"></i> Certificate of Enrollment</h5>
                <p style="color: #666; margin-bottom: 15px;">
                    Download your official Certificate of Enrollment for Academic Year <?php echo htmlspecialchars($current_year); ?>
                </p>
                <a href="download_enrollment_certificate.php" class="btn-download" target="_blank">
                    <i class="bi bi-download"></i>
                    Download Certificate
                </a>
            </div>
        </div>

        <!-- Information About Next Enrollment -->
        <div class="info-card">
            <div class="closed-message">
                <i class="bi bi-info-circle-fill"></i>
                <h4>Enrollment for Next Academic Year</h4>
                <p>Enrollment for Academic Year <?php echo htmlspecialchars($next_year); ?> is not yet open.</p>
                <p style="margin-top: 10px;">Please check back later or contact the registrar's office for more information.</p>
            </div>
        </div>

    <?php else: ?>

        <?php if ($existing_enrollment): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <strong>Enrollment Request Submitted!</strong><br>
                    You have already submitted an enrollment appointment request for Academic Year <?php echo htmlspecialchars($next_year); ?>.
                    Appointment ID: <strong><?php echo htmlspecialchars($existing_enrollment['appointment_id']); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <!-- Current Student Information -->
        <div class="info-card">
            <h4><i class="bi bi-person-badge"></i> Current Student Information</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Student Code (LRN)</span>
                    <span class="info-value"><?php echo htmlspecialchars($student_info['student_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Grade Level</span>
                    <span class="info-value"><?php echo htmlspecialchars($current_grade); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Section</span>
                    <span class="info-value"><?php echo htmlspecialchars($student_info['section_name'] ?? 'Not Assigned'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Parent/Guardian</span>
                    <span class="info-value"><?php echo htmlspecialchars(($student_info['parent_first_name'] ?? '') . ' ' . ($student_info['parent_last_name'] ?? '')); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($student_info['parent_contact'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Next Year Enrollment Information -->
        <div class="info-card">
            <h4><i class="bi bi-arrow-right-circle"></i> Next Academic Year Information</h4>
            <?php if ($next_grade === 'Graduated'): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <div>Congratulations! You are currently in Grade 6, the final grade level for elementary. You will be graduating this academic year.</div>
                </div>
            <?php else: ?>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Academic Year</span>
                        <span class="info-value"><?php echo htmlspecialchars($next_year); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Next Grade Level</span>
                        <span class="info-value"><?php echo htmlspecialchars($next_grade); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($next_grade !== 'Graduated' && !$existing_enrollment): ?>
            <!-- Enrollment Form -->
            <div class="enrollment-form">
                <h4><i class="bi bi-calendar-check"></i> Schedule Enrollment Appointment</h4>
                <p style="color: #666; margin-bottom: 25px;">Book an appointment for onsite enrollment processing. Please ensure all information is correct.</p>

                <form id="enrollmentForm" method="POST">
                    <div class="info-grid">
                        <div class="form-group">
                            <label>Student Name *</label>
                            <input type="text" class="form-control" name="studentName"
                                value="<?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?>"
                                readonly required>
                        </div>

                        <div class="form-group">
                            <label>Parent/Guardian Name *</label>
                            <input type="text" class="form-control" name="parentName"
                                value="<?php echo htmlspecialchars(($student_info['parent_first_name'] ?? '') . ' ' . ($student_info['parent_last_name'] ?? '')); ?>"
                                readonly required>
                        </div>

                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" class="form-control" name="email"
                                value="<?php echo htmlspecialchars($student_info['parent_email'] ?? ''); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Contact Number *</label>
                            <input type="tel" class="form-control" name="phone"
                                value="<?php echo htmlspecialchars($student_info['parent_contact'] ?? ''); ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Current Grade Level *</label>
                            <input type="text" class="form-control" name="currentGradeLevel"
                                value="<?php echo htmlspecialchars($current_grade); ?>"
                                readonly required>
                        </div>

                        <div class="form-group">
                            <label>Enrolling for Grade Level *</label>
                            <input type="text" class="form-control" name="gradeLevel"
                                value="<?php echo htmlspecialchars($next_grade); ?>"
                                readonly required>
                        </div>

                        <div class="form-group">
                            <label>Preferred Appointment Date *</label>
                            <select class="form-control" name="date" id="appointmentDate" required>
                                <option value="">Select Date</option>
                            </select>
                            <small class="form-text text-muted" id="dateHelp">Loading available dates...</small>
                        </div>

                        <div class="form-group">
                            <label>Preferred Appointment Time *</label>
                            <select class="form-control" name="time" required>
                                <option value="">Select Time</option>
                                <option value="9:00 AM">9:00 AM</option>
                                <option value="10:00 AM">10:00 AM</option>
                                <option value="11:00 AM">11:00 AM</option>
                                <option value="1:00 PM">1:00 PM</option>
                                <option value="2:00 PM">2:00 PM</option>
                                <option value="3:00 PM">3:00 PM</option>
                                <option value="4:00 PM">4:00 PM</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Additional Message (Optional)</label>
                            <textarea class="form-control" name="message" rows="4"
                                placeholder="Any special requests or information we should know..."></textarea>
                        </div>

                        <input type="hidden" name="studentCode" value="<?php echo htmlspecialchars($student_info['student_code']); ?>">
                        <input type="hidden" name="academicYear" value="<?php echo htmlspecialchars($next_year); ?>">

                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn-submit">
                                <i class="bi bi-calendar-check"></i> Submit Enrollment Request
                            </button>
                        </div>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Enrollment History -->
    <?php if (!empty($enrollment_history)): ?>
        <div class="info-card">
            <h4><i class="bi bi-clock-history"></i> Enrollment History</h4>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Academic Year</th>
                        <th>Grade Level</th>
                        <th>Appointment Date</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollment_history as $history): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($history['appointment_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($history['academic_year'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($history['grade_level']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($history['preferred_date'])); ?> at <?php echo htmlspecialchars($history['preferred_time']); ?></td>
                            <td>
                                <span class="status-pill status-<?php echo $history['status']; ?>">
                                    <?php echo ucfirst($history['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($history['created_at'])); ?></td>
                            <td>
                                <?php if (in_array($history['status'], ['pending', 'cancelled'])): ?>
                                    <button class="btn-delete" onclick="deleteEnrollment(<?php echo $history['id']; ?>, '<?php echo htmlspecialchars($history['appointment_id']); ?>')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 13px;">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    // Load available dates when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadAvailableDates();
    });

    async function loadAvailableDates() {
        const dateSelect = document.getElementById('appointmentDate');
        const dateHelp = document.getElementById('dateHelp');

        if (!dateSelect) return;

        try {
            const response = await fetch('fetch_available_dates.php');
            const result = await response.json();

            if (result.status === 'success' && result.dates.length > 0) {
                dateSelect.innerHTML = '<option value="">Select Date</option>';
                dateHelp.textContent = 'Select an available date for your appointment';

                result.dates.forEach(date => {
                    const option = document.createElement('option');
                    option.value = date.date;

                    let slotText = '';
                    if (date.available_slots <= 2) {
                        slotText = `(${date.available_slots} slots left)`;
                    } else {
                        slotText = `(${date.available_slots} slots available)`;
                    }

                    option.textContent = `${date.formatted_date} ${slotText}`;
                    dateSelect.appendChild(option);
                });

                dateHelp.style.color = '#4caf50';
            } else {
                dateSelect.innerHTML = '<option value="">No available dates</option>';
                dateSelect.disabled = true;
                dateHelp.textContent = 'No appointment dates are currently available.';
                dateHelp.style.color = '#f44336';
            }
        } catch (error) {
            console.error('Error loading dates:', error);
            dateHelp.textContent = 'Error loading dates. Please refresh the page.';
            dateHelp.style.color = '#f44336';
        }
    }

    // Form submission handler
    document.getElementById('enrollmentForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('.btn-submit');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

        const formData = new FormData(this);

        try {
            const response = await fetch('submit_student_enrollment.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                alert('✓ Enrollment appointment successfully scheduled!\n\nAppointment ID: ' + result.appointment_id + '\n\nYou will receive a confirmation email shortly.');
                window.location.reload();
            } else {
                alert('✗ Error: ' + result.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            alert('✗ An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    async function deleteEnrollment(enrollmentId, appointmentId) {
        if (!confirm(`Are you sure you want to delete enrollment appointment ${appointmentId}?\n\nThis action cannot be undone.`)) {
            return;
        }

        try {
            const response = await fetch('delete_enrollment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `enrollment_id=${enrollmentId}`
            });

            const result = await response.json();

            if (result.status === 'success') {
                alert('✓ Enrollment appointment deleted successfully!');
                window.location.reload();
            } else {
                alert('✗ Error: ' + result.message);
            }
        } catch (error) {
            alert('✗ An error occurred while deleting the enrollment.');
        }
    }
</script>

<?php
$content = ob_get_clean();

// Include the layout wrapper
require_once 'student_layout.php';
renderLayout('Enrollment', $content, 'enrollment', $student_layout_info, $initials, $profile_picture_url);
?>