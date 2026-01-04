<?php
session_start();
require_once 'db_connection.php';



// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submission for student info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $address = $_POST['address'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birthdate = $_POST['birthdate'] ?? null;

    try {
        $stmt = $conn->prepare("
            UPDATE students 
            SET address = :address,
                gender = :gender,
                birthdate = :birthdate
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            'address' => $address,
            'gender' => $gender,
            'birthdate' => $birthdate,
            'user_id' => $user_id
        ]);

        echo json_encode(['success' => true, 'message' => 'Student information updated successfully!']);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating information: ' . $e->getMessage()]);
        exit();
    }
}

// Handle form submission for parent info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_parent'])) {
    $parent_contact = $_POST['parent_contact'] ?? '';
    $parent_address = $_POST['parent_address'] ?? '';
    $parent_email = $_POST['parent_email'] ?? '';
    $parent_id = $_POST['parent_id'] ?? null;

    if ($parent_id) {
        try {
            $stmt = $conn->prepare("
                UPDATE parents 
                SET contact_number = :contact_number,
                    address = :address,
                    email = :email
                WHERE parent_id = :parent_id
            ");
            $stmt->execute([
                'contact_number' => $parent_contact,
                'address' => $parent_address,
                'email' => $parent_email,
                'parent_id' => $parent_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Parent information updated successfully!']);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating parent information: ' . $e->getMessage()]);
            exit();
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $target_dir = "../uploads/student_profiles/";

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file = $_FILES['profile_picture'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_extension, $allowed_extensions)) {
        if ($file['size'] <= 5000000) {
            $new_filename = "student_" . $user_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                try {
                    $stmt = $conn->prepare("
                        UPDATE students 
                        SET profile_picture = :profile_picture
                        WHERE user_id = :user_id
                    ");
                    $stmt->execute([
                        'profile_picture' => $new_filename,
                        'user_id' => $user_id
                    ]);

                    $success_message = "Profile picture updated successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Error updating profile picture: " . $e->getMessage();
                }
            } else {
                $error_message = "Error uploading file.";
            }
        } else {
            $error_message = "File size exceeds 5MB limit.";
        }
    } else {
        $error_message = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
    }
}

// Handle profile picture removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_profile_picture'])) {
    try {
        // Get current profile picture
        $stmt = $conn->prepare("SELECT profile_picture FROM students WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $current_picture = $stmt->fetchColumn();

        // Delete file if exists
        if ($current_picture) {
            $file_path = "../uploads/student_profiles/" . $current_picture;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Update database
        $stmt = $conn->prepare("UPDATE students SET profile_picture = NULL WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);

        echo json_encode(['success' => true, 'message' => 'Profile picture removed successfully!']);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error removing profile picture: ' . $e->getMessage()]);
        exit();
    }
}

// Fetch student information
$stmt = $conn->prepare("
    SELECT 
        s.*,
        CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as full_name,
        sec.grade_level,
        sec.section_name as section,
        CONCAT(t.first_name, ' ', t.last_name) as adviser,
        u.username as lrn,
        u.email as user_email
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    LEFT JOIN teachers t ON sec.adviser_code = t.teacher_code
    WHERE s.user_id = :user_id
");
$stmt->execute(['user_id' => $user_id]);
$student_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student_data) {
    die("Student record not found");
}

// Fetch parent information
$stmt = $conn->prepare("
    SELECT 
        p.*,
        CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) as full_name
    FROM parents p
    WHERE p.parent_id = :parent_id
");
$stmt->execute(['parent_id' => $student_data['parent_id']]);
$parent_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current school year and quarter
$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_school_year'");
$current_school_year = $stmt->fetchColumn() ?: '2025-2026';

$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_quarter'");
$current_quarter = $stmt->fetchColumn() ?: '2nd';

// Get school information from system_settings
$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'school_name'");
$school_name = $stmt->fetchColumn() ?: 'Creative Dreams School';

$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'school_address'");
$school_address = $stmt->fetchColumn() ?: 'Nasugbu, Batangas';

// Get current school year and quarter
$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_school_year'");
$current_school_year = $stmt->fetchColumn() ?: '2025-2026';

$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_quarter'");
$current_quarter = $stmt->fetchColumn() ?: '2nd';

// Prepare student info array
$student_info = [
    'first_name' => $student_data['first_name'],
    'full_name' => trim($student_data['full_name']),
    'lrn' => $student_data['lrn'],
    'grade_level' => $student_data['grade_level'] ?? 'N/A',
    'section' => $student_data['section'] ?? 'N/A',
    'adviser' => $student_data['adviser'] ?? 'N/A',
    'school_year' => $current_school_year,
    'current_quarter' => $current_quarter
];

// Get initials
$names = explode(' ', $student_data['full_name']);
$initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));

// Profile picture path
$profile_picture = !empty($student_data['profile_picture'])
    ? "../uploads/student_profiles/" . $student_data['profile_picture']
    : null;

require_once 'student_layout.php';

ob_start();
?>

<style>
    .student-id-card {
        width: 240px;
        height: 380px;
        border-radius: 12px;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        position: relative;
        overflow: hidden;
        font-family: 'Arial', sans-serif;
    }

    .id-front {
        background: linear-gradient(135deg, #4c8c4a 0%, #6ba869 100%);
        color: white;
        padding: 15px 14px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .id-back {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        color: #333;
        padding: 15px 14px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .id-header {
        text-align: center;
        border-bottom: 2px solid rgba(255, 255, 255, 0.3);
        padding-bottom: 6px;
        margin-bottom: 10px;
    }

    .id-school-logo {
        width: 40px;
        height: 40px;
        background: white;
        border-radius: 50%;
        margin: 0 auto 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #4c8c4a;
        font-size: 16px;
    }

    .id-school-name {
        font-size: 8px;
        font-weight: bold;
        margin: 0;
        text-transform: uppercase;
        line-height: 1.1;
    }

    .id-school-address {
        font-size: 7px;
        line-height: 1.1;
        margin: 2px 0 0 0;
    }

    .id-card-type {
        font-size: 7px;
        font-weight: 600;
        margin: 2px 0 0 0;
    }

    .id-student-photo-container {
        text-align: center;
        margin: 8px 0;
    }

    .id-student-photo {
        width: 100px;
        height: 100px;
        border-radius: 8px;
        border: 3px solid white;
        object-fit: cover;
        background: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        font-weight: bold;
        color: #4c8c4a;
    }

    .id-info-section {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        padding: 10px;
        margin-top: 8px;
        flex-grow: 0;
    }

    .id-info-row {
        margin-bottom: 7px;
    }

    .id-info-row:last-child {
        margin-bottom: 0;
    }

    .id-label {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 7px;
        opacity: 0.9;
        margin-bottom: 1px;
        letter-spacing: 0.2px;
    }

    .id-value {
        font-weight: bold;
        font-size: 10px;
        line-height: 1.2;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .id-back .id-header {
        border-bottom-color: rgba(0, 0, 0, 0.1);
    }

    .id-back .id-info-section {
        background: white;
        border: 1px solid #dee2e6;
        flex-grow: 1;
        overflow: auto;
    }

    .id-back .id-label {
        color: #6c757d;
    }

    .id-back .id-value {
        color: #333;
        font-size: 9px;
    }

    .id-emergency-note {
        text-align: center;
        font-size: 7px;
        opacity: 0.7;
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        line-height: 1.2;
    }

    .id-signature-line {
        border-top: 1px solid rgba(255, 255, 255, 0.3);
        margin-top: auto;
        padding-top: 6px;
        text-align: center;
        font-size: 7px;
    }

    .id-back .id-signature-line {
        border-top-color: rgba(0, 0, 0, 0.2);
        color: #6c757d;
    }

    .profile-avatar {
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4c8c4a 0%, #5fa85d 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 72px;
        font-weight: 700;
        color: white;
        box-shadow: 0 4px 12px rgba(76, 140, 74, 0.3);
        border: 5px solid white;
    }
</style>

<h4 class="page-title">
    <i class="bi bi-person-circle"></i> My Profile
</h4>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Profile Picture Section -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card-box text-center">
            <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                <i class="bi bi-camera-fill"></i> PROFILE PICTURE
            </h6>

            <?php if ($profile_picture && file_exists($profile_picture)): ?>
                <img src="<?php echo $profile_picture; ?>" alt="Profile Picture"
                    class="img-fluid rounded-circle mb-3"
                    style="width: 200px; height: 200px; object-fit: cover; border: 5px solid #4c8c4a;">
            <?php else: ?>
                <div class="profile-avatar mx-auto mb-3" style="width: 200px; height: 200px; font-size: 72px;">
                    <?php echo $initials; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-3">
                    <label for="profile_picture" class="btn btn-success btn-sm">
                        <i class="bi bi-upload"></i> Upload New Picture
                    </label>
                    <input type="file" class="d-none" id="profile_picture" name="profile_picture"
                        accept="image/jpeg,image/png,image/gif" onchange="this.form.submit()">

                    <?php if ($profile_picture && file_exists($profile_picture)): ?>
                        <button type="button" class="btn btn-danger btn-sm ms-2" onclick="removeProfilePicture()">
                            <i class="bi bi-trash"></i> Remove Picture
                        </button>
                    <?php endif; ?>
                </div>
                <small class="text-muted">Max file size: 5MB<br>Allowed: JPG, PNG, GIF</small>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-box">
            <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                <i class="bi bi-info-circle-fill"></i> BASIC INFORMATION
            </h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">STUDENT CODE (LRN)</label>
                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($student_data['lrn']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">FULL NAME</label>
                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($student_data['full_name']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">GRADE & SECTION</label>
                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($student_data['grade_level'] . ' - ' . $student_data['section']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">CLASS ADVISER</label>
                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($student_data['adviser']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">ENROLLMENT STATUS</label>
                    <p class="mb-0">
                        <span class="badge bg-success"><?php echo ucfirst($student_data['status']); ?></span>
                    </p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="text-muted small">DATE ENROLLED</label>
                    <p class="fw-bold mb-0">
                        <?php echo $student_data['date_enrolled'] ? date('F d, Y', strtotime($student_data['date_enrolled'])) : 'N/A'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student ID Card Preview -->
<div class="card-box mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0" style="color: #4c8c4a;">
            <i class="bi bi-credit-card-2-front"></i> STUDENT ID CARD
        </h6>
        <button type="button" class="btn btn-sm btn-success" onclick="downloadStudentID()">
            <i class="bi bi-download"></i> Download as PDF
        </button>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-3 col-md-6 text-center mb-4">
            <p class="small text-muted mb-2"><strong>FRONT SIDE</strong></p>
            <div class="student-id-card id-front mx-auto" id="id-front">
                <!-- Header -->
                <div class="id-header">
                    <div class="id-school-logo">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <p class="id-school-name"><?php echo strtoupper(htmlspecialchars($school_name)); ?></p>
                    <p class="id-school-address"><?php echo htmlspecialchars($school_address); ?></p>
                    <p class="id-card-type">STUDENT ID CARD</p>
                </div>

                <!-- Photo -->
                <div class="id-student-photo-container">
                    <?php if ($profile_picture && file_exists($profile_picture)): ?>
                        <img src="<?php echo $profile_picture; ?>" alt="Student Photo" class="id-student-photo">
                    <?php else: ?>
                        <div class="id-student-photo">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Student Info -->
                <div class="id-info-section">
                    <div class="id-info-row">
                        <div class="id-label">Full Name</div>
                        <div class="id-value"><?php echo strtoupper(htmlspecialchars($student_data['full_name'])); ?></div>
                    </div>
                    <div class="id-info-row">
                        <div class="id-label">LRN / Student Code</div>
                        <div class="id-value"><?php echo htmlspecialchars($student_data['lrn']); ?></div>
                    </div>
                    <div class="id-info-row">
                        <div class="id-label">Grade & Section</div>
                        <div class="id-value"><?php echo htmlspecialchars($student_data['grade_level'] . ' - ' . $student_data['section']); ?></div>
                    </div>
                    <div class="id-info-row">
                        <div class="id-label">School Year</div>
                        <div class="id-value"><?php echo htmlspecialchars($current_school_year); ?></div>
                    </div>
                </div>

                <!-- Signature -->
                <div class="id-signature-line">
                    <div style="margin-bottom: 3px;">_________________</div>
                    <div>Authorized Signature</div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 text-center mb-4">
            <p class="small text-muted mb-2"><strong>BACK SIDE</strong></p>
            <div class="student-id-card id-back mx-auto" id="id-back">
                <!-- Header -->
                <div class="id-header">
                    <div class="id-school-logo" style="background: #4c8c4a; color: white;">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                    <p class="id-school-name" style="color: #4c8c4a;">EMERGENCY CONTACT</p>
                </div>

                <!-- Parent Info -->
                <?php if ($parent_data): ?>
                    <div class="id-info-section">
                        <div class="id-info-row">
                            <div class="id-label">Parent/Guardian</div>
                            <div class="id-value"><?php echo strtoupper(htmlspecialchars($parent_data['full_name'])); ?></div>
                        </div>
                        <div class="id-info-row">
                            <div class="id-label">Relationship</div>
                            <div class="id-value"><?php echo htmlspecialchars($parent_data['relationship']); ?></div>
                        </div>
                        <div class="id-info-row">
                            <div class="id-label">Contact Number</div>
                            <div class="id-value"><?php echo htmlspecialchars($parent_data['contact_number'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="id-info-row">
                            <div class="id-label">Email Address</div>
                            <div class="id-value" style="font-size: 10px; text-transform: lowercase;"><?php echo htmlspecialchars($parent_data['email'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="id-info-row">
                            <div class="id-label">Home Address</div>
                            <div class="id-value" style="font-size: 10px;"><?php echo htmlspecialchars($parent_data['address'] ?? 'N/A'); ?></div>
                        </div>

                        <div class="id-emergency-note">
                            <i class="bi bi-exclamation-circle"></i> IN CASE OF EMERGENCY<br>
                            Please contact the person above immediately
                        </div>
                    </div>
                <?php else: ?>
                    <div class="id-info-section">
                        <div class="text-center">
                            <i class="bi bi-info-circle" style="font-size: 24px; color: #6c757d;"></i>
                            <p class="small mt-2">No emergency contact information available</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Back Signature -->
                <div class="id-signature-line">
                    <div style="font-size: 8px;">This card is property of the school</div>
                    <div style="font-size: 8px;">If found, please return to school office</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Personal Information Card -->
<div class="card-box mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0" style="color: #4c8c4a;">
            <i class="bi bi-pencil-square"></i> PERSONAL INFORMATION
        </h6>
        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editStudentModal">
            <i class="bi bi-pencil"></i> Edit Information
        </button>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="text-muted small">GENDER</label>
            <p class="fw-bold mb-0"><?php echo htmlspecialchars($student_data['gender'] ?? 'Not Set'); ?></p>
        </div>
        <div class="col-md-6 mb-3">
            <label class="text-muted small">BIRTHDATE</label>
            <p class="fw-bold mb-0">
                <?php echo $student_data['birthdate'] ? date('F d, Y', strtotime($student_data['birthdate'])) : 'Not Set'; ?>
            </p>
        </div>
        <div class="col-md-12 mb-3">
            <label class="text-muted small">HOME ADDRESS</label>
            <p class="fw-bold mb-0"><?php echo htmlspecialchars($student_data['address'] ?? 'Not Set'); ?></p>
        </div>
    </div>
</div>

<!-- Parent/Guardian Information -->
<?php if ($parent_data): ?>
    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0" style="color: #4c8c4a;">
                <i class="bi bi-people-fill"></i> PARENT/GUARDIAN INFORMATION
            </h6>
            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editParentModal">
                <i class="bi bi-pencil"></i> Edit Information
            </button>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="text-muted small">FULL NAME</label>
                <p class="fw-bold"><?php echo htmlspecialchars($parent_data['full_name']); ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted small">RELATIONSHIP</label>
                <p class="fw-bold"><?php echo htmlspecialchars($parent_data['relationship']); ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted small">CONTACT NUMBER</label>
                <p class="fw-bold"><?php echo htmlspecialchars($parent_data['contact_number'] ?? 'Not Set'); ?></p>
            </div>
            <div class="col-md-6 mb-3">
                <label class="text-muted small">EMAIL ADDRESS</label>
                <p class="fw-bold"><?php echo htmlspecialchars($parent_data['email'] ?? 'Not Set'); ?></p>
            </div>
            <div class="col-md-12 mb-3">
                <label class="text-muted small">ADDRESS</label>
                <p class="fw-bold mb-0"><?php echo htmlspecialchars($parent_data['address'] ?? 'Not Set'); ?></p>
            </div>
            <?php if ($parent_data['occupation']): ?>
                <div class="col-md-12 mb-3">
                    <label class="text-muted small">OCCUPATION</label>
                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($parent_data['occupation']); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card-box">
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle"></i> No parent/guardian information found.
        </div>
    </div>
<?php endif; ?>

<!-- Edit Student Information Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #4c8c4a; color: white;">
                <h5 class="modal-title" id="editStudentModalLabel">
                    <i class="bi bi-pencil-square"></i> Edit Personal Information
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="studentForm" onsubmit="handleStudentUpdate(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                        <select name="gender" id="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $student_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $student_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="birthdate" class="form-label">Birthdate <span class="text-danger">*</span></label>
                        <input type="date" name="birthdate" id="birthdate" class="form-control"
                            value="<?php echo htmlspecialchars($student_data['birthdate'] ?? ''); ?>"
                            max="<?php echo date('Y-m-d'); ?>" required>
                        <small class="text-muted">You must be at least 5 years old</small>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Home Address <span class="text-danger">*</span></label>
                        <textarea name="address" id="address" class="form-control" rows="3" required><?php echo htmlspecialchars($student_data['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Please make sure all information is correct before saving.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Parent Information Modal -->
<div class="modal fade" id="editParentModal" tabindex="-1" aria-labelledby="editParentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #4c8c4a; color: white;">
                <h5 class="modal-title" id="editParentModalLabel">
                    <i class="bi bi-people-fill"></i> Edit Parent/Guardian Information
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="parentForm" onsubmit="handleParentUpdate(event)">
                <input type="hidden" name="parent_id" value="<?php echo $parent_data['parent_id'] ?? ''; ?>">

                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Note:</strong> Name and relationship cannot be changed. Contact your school administrator for these changes.
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">Full Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($parent_data['full_name'] ?? ''); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">Relationship</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($parent_data['relationship'] ?? ''); ?>" disabled>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="parent_contact" class="form-label">Contact Number <span class="text-danger">*</span></label>
                        <input type="tel" name="parent_contact" id="parent_contact" class="form-control"
                            value="<?php echo htmlspecialchars($parent_data['contact_number'] ?? ''); ?>"
                            pattern="[0-9]{10,11}"
                            placeholder="09123456789" required>
                        <small class="text-muted">Enter 10-11 digit phone number</small>
                    </div>

                    <div class="mb-3">
                        <label for="parent_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="parent_email" id="parent_email" class="form-control"
                            value="<?php echo htmlspecialchars($parent_data['email'] ?? ''); ?>"
                            placeholder="parent@example.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="parent_address" class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea name="parent_address" id="parent_address" class="form-control" rows="3" required><?php echo htmlspecialchars($parent_data['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This information is used for emergency contacts.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="confirmationModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i> Confirm Changes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="confirmationMessage">Are you sure you want to save these changes?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmSaveBtn">
                    <i class="bi bi-check-circle"></i> Yes, Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    let pendingFormData = null;
    let pendingFormType = null;

    // Handle Student Update Form
    function handleStudentUpdate(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        pendingFormData = formData;
        pendingFormType = 'student';

        // Show confirmation modal
        document.getElementById('confirmationMessage').textContent =
            'Are you sure you want to update your personal information? Please verify all details are correct.';

        const confirmModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmModal.show();

        return false;
    }

    // Handle Parent Update Form
    function handleParentUpdate(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        pendingFormData = formData;
        pendingFormType = 'parent';

        // Show confirmation modal
        document.getElementById('confirmationMessage').textContent =
            'Are you sure you want to update the parent/guardian information? This will update emergency contact details.';

        const confirmModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmModal.show();

        return false;
    }

    // Confirm Save Button Handler
    document.getElementById('confirmSaveBtn').addEventListener('click', function() {
        if (pendingFormData && pendingFormType) {
            if (pendingFormType === 'student') {
                pendingFormData.append('update_student', '1');
                submitForm(pendingFormData, 'student');
            } else if (pendingFormType === 'parent') {
                pendingFormData.append('update_parent', '1');
                submitForm(pendingFormData, 'parent');
            }
        }

        // Close confirmation modal
        bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide();
    });

    // Submit Form via AJAX
    function submitForm(formData, type) {
        // Show loading state
        const submitBtn = document.getElementById('confirmSaveBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        submitBtn.disabled = true;

        fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;

                if (data.success) {
                    // Close the edit modal
                    const modalId = type === 'student' ? 'editStudentModal' : 'editParentModal';
                    bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();

                    // Show success message
                    showAlert('success', data.message);

                    // Reload page after 1.5 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showAlert('danger', 'An error occurred while saving. Please try again.');
                console.error('Error:', error);
            });
    }

    // Show Alert Message
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'}"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

        const pageTitle = document.querySelector('.page-title');
        pageTitle.parentNode.insertBefore(alertDiv, pageTitle.nextSibling);

        // Auto dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    // Download Student ID as PDF - Both sides, no instructions
    async function downloadStudentID() {
        const button = event.target.closest('button');
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating PDF...';
        button.disabled = true;

        try {
            const {
                jsPDF
            } = window.jspdf;

            // Create PDF in portrait mode, A4 size
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            // A4 dimensions: 210mm x 297mm
            const pageWidth = 210;
            const pageHeight = 297;

            // ID card dimensions (same as original size)
            const cardWidth = 70; // 70mm width
            const cardHeight = 110; // 110mm height

            // Calculate positions to center cards
            const spacing = 15; // Space between front and back
            const totalWidth = (cardWidth * 2) + spacing;
            const startX = (pageWidth - totalWidth) / 2;
            const startY = (pageHeight - cardHeight) / 2; // Center vertically

            // Capture front of ID
            const frontElement = document.getElementById('id-front');
            const frontCanvas = await html2canvas(frontElement, {
                scale: 4,
                backgroundColor: null,
                logging: false,
                width: 280,
                height: 440,
                useCORS: true
            });

            const frontImgData = frontCanvas.toDataURL('image/png', 1.0);

            // Capture back of ID
            const backElement = document.getElementById('id-back');
            const backCanvas = await html2canvas(backElement, {
                scale: 4,
                backgroundColor: null,
                logging: false,
                width: 280,
                height: 440,
                useCORS: true
            });

            const backImgData = backCanvas.toDataURL('image/png', 1.0);

            // Add front side
            pdf.addImage(frontImgData, 'PNG', startX, startY, cardWidth, cardHeight, '', 'FAST');

            // Add back side (next to front)
            pdf.addImage(backImgData, 'PNG', startX + cardWidth + spacing, startY, cardWidth, cardHeight, '', 'FAST');

            // Save PDF
            const studentName = '<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $student_data['full_name']); ?>';
            const timestamp = new Date().toISOString().split('T')[0];
            pdf.save(`Student_ID_${studentName}_${timestamp}.pdf`);

            showAlert('success', 'Student ID downloaded successfully!');
        } catch (error) {
            console.error('Error generating PDF:', error);
            showAlert('danger', 'Error generating PDF. Please try again.');
        } finally {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }

    // Form validation on submit
    document.getElementById('studentForm').addEventListener('submit', function(e) {
        const birthdate = new Date(document.getElementById('birthdate').value);
        const today = new Date();
        const age = today.getFullYear() - birthdate.getFullYear();

        if (age < 5 || age > 100) {
            e.preventDefault();
            showAlert('danger', 'Please enter a valid birthdate. Student must be between 5-100 years old.');
            return false;
        }
    });

    document.getElementById('parentForm').addEventListener('submit', function(e) {
        const phone = document.getElementById('parent_contact').value;
        const phoneRegex = /^[0-9]{10,11}$/;

        if (!phoneRegex.test(phone)) {
            e.preventDefault();
            showAlert('danger', 'Please enter a valid 10-11 digit phone number.');
            return false;
        }
    });

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-info):not(.alert-warning)');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        });
    });

    // Remove Profile Picture
    function removeProfilePicture() {
        if (confirm('Are you sure you want to remove your profile picture?')) {
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Removing...';
            button.disabled = true;

            const formData = new FormData();
            formData.append('remove_profile_picture', '1');

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert('danger', data.message);
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    showAlert('danger', 'An error occurred. Please try again.');
                    button.innerHTML = originalText;
                    button.disabled = false;
                    console.error('Error:', error);
                });
        }
    }
</script>

<?php
$content = ob_get_clean();
renderLayout('Page Title', $content, 'profile', $student_info, $initials, $profile_picture_url);
?>