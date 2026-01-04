<?php
session_start();

// Check login + role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$host = 'localhost';
$dbname = 'u545996239_cdsportal';
$username = 'u545996239_cdsportal'; // Changed variable name to avoid conflict
$password = 'B@nana2025';     //

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get teacher info from session
$teacher_code = $_SESSION['username'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $contact_number = trim($_POST['contact_number']);
        $address = trim($_POST['address']);

        // Handle profile photo upload
        $photo_path = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_photo']['type'];

            if (in_array($file_type, $allowed_types)) {
                $file_size = $_FILES['profile_photo']['size'];
                if ($file_size <= 5 * 1024 * 1024) { // 5MB max
                    $upload_dir = 'uploads/teachers/';

                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                    $new_filename = $teacher_code . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                        $photo_path = $upload_path;

                        // Delete old photo if exists
                        $stmt = $pdo->prepare("SELECT profile_photo FROM teachers WHERE teacher_code = ?");
                        $stmt->execute([$teacher_code]);
                        $old_photo = $stmt->fetchColumn();
                        if ($old_photo && file_exists($old_photo)) {
                            unlink($old_photo);
                        }
                    }
                }
            }
        }

        // Update database
        if ($photo_path) {
            $stmt = $pdo->prepare("UPDATE teachers SET contact_number = ?, address = ?, profile_photo = ? WHERE teacher_code = ?");
            $stmt->execute([$contact_number, $address, $photo_path, $teacher_code]);
        } else {
            $stmt = $pdo->prepare("UPDATE teachers SET contact_number = ?, address = ? WHERE teacher_code = ?");
            $stmt->execute([$contact_number, $address, $teacher_code]);
        }

        $_SESSION['success_message'] = 'Profile updated successfully!';
        header("Location: faculty_profile.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error updating profile: ' . $e->getMessage();
    }
}

// Fetch teacher details with subjects
try {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_code = ?");
    $stmt->execute([$teacher_code]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        header("Location: login.php");
        exit();
    }

    // Get subjects taught by this teacher
    $stmt = $pdo->prepare("
    SELECT DISTINCT
        gst.subject_code,
        s.subject_name,
        sec.grade_level,
        sec.section_name
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN subjects s ON gst.subject_code = s.subject_code
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.teacher_code = ? AND ss.is_active = 1
    ORDER BY sec.grade_level, sec.section_name, s.subject_name
");
    $stmt->execute([$teacher_code]);
    $subjects = $stmt->fetchAll();

    // Get teaching schedule
    $stmt = $pdo->prepare("
    SELECT 
        gst.day_of_week as day,
        mts.start_time,
        mts.end_time,
        mts.slot_type,
        mts.slot_name,
        s.subject_name,
        sec.grade_level,
        sec.section_name,
        CASE 
            WHEN gst.subject_code IN ('MAPEH', 'COMP') THEN gst.room_type
            ELSE sec.room_assignment
        END as room
    FROM section_schedules ssch
    INNER JOIN grade_schedule_template gst ON ssch.template_id = gst.template_id
    INNER JOIN subjects s ON gst.subject_code = s.subject_code
    INNER JOIN sections sec ON ssch.section_id = sec.section_id
    INNER JOIN master_time_slots mts ON gst.slot_id = mts.slot_id
    WHERE ssch.teacher_code = ? AND ssch.is_active = 1
    ORDER BY 
        FIELD(gst.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
        mts.slot_order
");
    $stmt->execute([$teacher_code]);
    $schedules = $stmt->fetchAll();

    // Group schedules by day
    $schedule_by_day = [];
    foreach ($schedules as $schedule) {
        $schedule_by_day[$schedule['day']][] = $schedule;
    }
} catch (PDOException $e) {
    die("Error fetching teacher data: " . $e->getMessage());
}

$teacherName = htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']);
$fullName = htmlspecialchars($teacher['first_name'] . ' ' . ($teacher['middle_name'] ? $teacher['middle_name'] . ' ' : '') . $teacher['last_name']);
$profilePhoto = !empty($teacher['profile_photo']) && file_exists($teacher['profile_photo']) ? $teacher['profile_photo'] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Profile - Creative Dreams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #5a9c4e;
            --light-green: #6db560;
            --dark-green: #4a8240;
            --text-dark: #2d5a24;
            --sage-green: #52a347;
            --accent-green: #68b85d;
            --pale-green: #e0f7fa;
            --light-sage: #7ec274;
            --forest-green: #3d6e35;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            min-height: 100vh;
        }

        /* Header */
        .top-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            padding: 15px 30px;
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .brand-text h1 {
            color: white;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .brand-text p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin: 0;
            font-style: italic;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .icon-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .welcome-section {
            text-align: center;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .teacher-avatar {
            width: 80px;
            height: 80px;
            /* Using source avatar gradient */
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .teacher-avatar i {
            font-size: 40px;
            color: white;
        }

        .teacher-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .welcome-section h5 {
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--sage-green);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 14px;
        }

        .faculty-id-section {
            background: var(--pale-green);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .faculty-id-section h6 {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .faculty-id-section .id-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--sage-green);
            font-family: 'Courier New', monospace;
        }

        .menu-item {
            padding: 15px 20px;
            margin: 8px 0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
        }

        .menu-item:hover {
            background: var(--pale-green);
            color: var(--sage-green);
            transform: translateX(5px);
        }

        .menu-item.active {
            /* Using source active gradient */
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
            color: white;
        }

        .menu-item i {
            font-size: 20px;
            width: 25px;
        }

        .logout-btn {
            margin-top: 20px;
            padding: 15px 20px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 10px;
            width: 100%;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            padding: 20px;
        }

        .page-title {
            color: var(--text-dark);
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            background: white;
        }

        .card-header {
            background: white;
            border: none;
            padding: 20px;
            font-weight: bold;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-header i {
            font-size: 20px;
            color: var(--sage-green);
        }

        /* Faculty ID Card */
        .faculty-id-card {
            width: 350px;
            height: 550px;
            background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            margin: 0 auto;
        }

        .id-card-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            padding: 20px;
            text-align: center;
            color: white;
        }

        .id-card-header h4 {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .id-card-header p {
            font-size: 12px;
            margin: 5px 0 0 0;
            opacity: 0.9;
        }

        .id-photo-section {
            text-align: center;
            padding: 30px 20px 20px;
        }

        .id-photo {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 5px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .id-photo i {
            font-size: 70px;
            color: white;
        }

        .id-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .id-info-section {
            padding: 20px 30px;
        }

        .id-field {
            margin-bottom: 15px;
        }

        .id-field-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .id-field-value {
            font-size: 16px;
            color: var(--text-dark);
            font-weight: bold;
            padding: 8px;
            background: #f5f5f5;
            border-radius: 5px;
            border-left: 3px solid var(--primary-green);
        }

        .id-number-display {
            font-size: 24px;
            font-family: 'Courier New', monospace;
            color: var(--sage-green);
            text-align: center;
            padding: 15px;
            background: var(--pale-green);
            border-radius: 10px;
            margin: 20px 0;
            letter-spacing: 2px;
        }

        .id-card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: #f5f5f5;
            padding: 15px;
            text-align: center;
            border-top: 2px solid #e0e0e0;
        }

        .id-card-footer p {
            font-size: 10px;
            color: #666;
            margin: 0;
        }

        .qr-code-placeholder {
            width: 80px;
            height: 80px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin: 10px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #999;
        }

        .qr-code-placeholder img {
            max-width: 100%;
            max-height: 100%;
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }

            .faculty-id-card,
            .faculty-id-card * {
                visibility: visible;
            }

            .faculty-id-card {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
            }

            .no-print {
                display: none !important;
            }
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .info-row {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 200px;
            font-weight: bold;
            color: #666;
        }

        .info-value {
            flex: 1;
            color: var(--text-dark);
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table th {
            background: var(--pale-green);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--primary-green);
        }

        .schedule-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .schedule-table tr:hover {
            background: #f5f5f5;
        }

        .day-header {
            background: var(--primary-green);
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            margin-top: 15px;
            border-radius: 5px;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .photo-upload-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 5px solid var(--primary-green);
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview i {
            font-size: 60px;
            color: #ccc;
        }

        .file-upload-label {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-green);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-label:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        @media (max-width: 768px) {
            .faculty-id-card {
                width: 100%;
                max-width: 350px;
            }

            .page-title {
                font-size: 22px;
            }

            .info-row {
                flex-direction: column;
            }

            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="top-header no-print">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-graduation-cap" style="color: #7cb342;"></i>
                </div>
                <div class="brand-text">
                    <h1>Creative Dreams</h1>
                    <p>Inspire. Learn. Achieve.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 mb-4 no-print">
                <div class="sidebar">
                    <div class="welcome-section">
                        <div class="teacher-avatar">
                            <?php if ($profilePhoto): ?>
                                <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
                            <?php else: ?>
                                <i class="fas fa-chalkboard-teacher"></i>
                            <?php endif; ?>
                        </div>
                        <h5>WELCOME!</h5>
                        <p style="font-size: 16px; color: #2c3e50; font-weight: bold;">
                            <?php echo strtoupper($teacherName); ?>
                        </p>
                        <p><i class="fas fa-check-circle"></i> Teacher Portal</p>
                    </div>

                    <div class="faculty-id-section">
                        <h6>Faculty ID</h6>
                        <div class="id-number"><?php echo htmlspecialchars($teacher_code); ?></div>
                    </div>

                    <nav>
                        <a href="teacher_dashboard.php" class="menu-item">
                            <i class="fas fa-chart-line"></i>
                            <span>DASHBOARD</span>
                        </a>
                        <a href="teacher_classes.php" class="menu-item">
                            <i class="fas fa-users"></i>
                            <span>MY CLASSES</span>
                        </a>
                        <a href="grade_upload.php" class="menu-item">
                            <i class="fas fa-file-upload"></i>
                            <span>GRADE UPLOAD</span>
                        </a>
                        <a href="attendance.php" class="menu-item">
                            <i class="fas fa-clipboard-check"></i>
                            <span>ATTENDANCE</span>
                        </a>
                        <a href="teacher_reports.php" class="menu-item">
                            <i class="fas fa-file-alt"></i>
                            <span>ANALYTICS</span>
                        </a>
                        <a href="faculty_profile.php" class="menu-item active">
                            <i class="fas fa-id-card"></i>
                            <span>MY PROFILE</span>
                        </a>
                        <a href="teacher_announcements.php" class="menu-item">
                            <i class="fas fa-bullhorn"></i>
                            <span>ANNOUNCEMENTS</span>
                        </a>
                    </nav>
                    <form action="../logout.php" method="POST" style="margin-top: auto;">
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> LOGOUT
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-9">
                <div class="main-content">
                    <h2 class="page-title no-print">
                        <i class="fas fa-id-card"></i> Faculty Profile & ID
                    </h2>

                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                                unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message'];
                                                                        unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Faculty ID Card -->
                        <div class="col-lg-5 mb-4">
                            <div class="card">
                                <div class="card-header no-print">
                                    <span><i class="fas fa-id-card-alt"></i> FACULTY ID CARD</span>
                                    <button class="btn btn-sm btn-primary" onclick="window.print()">
                                        <i class="fas fa-print"></i> Print ID
                                    </button>
                                </div>
                                <div class="card-body text-center">
                                    <div class="faculty-id-card">
                                        <div class="id-card-header">
                                            <h4>CREATIVE DREAMS SCHOOL</h4>
                                            <p>Faculty Identification Card</p>
                                        </div>

                                        <div class="id-photo-section">
                                            <div class="id-photo">
                                                <?php if ($profilePhoto): ?>
                                                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
                                                <?php else: ?>
                                                    <i class="fas fa-user-tie"></i>
                                                <?php endif; ?>
                                            </div>
                                            <h5 style="color: #2c3e50; font-weight: bold; margin-bottom: 5px;">
                                                <?php echo strtoupper($fullName); ?>
                                            </h5>
                                            <p style="color: #7cb342; font-weight: 600; margin: 0;">
                                                <?php echo htmlspecialchars($teacher['position']); ?>
                                            </p>
                                        </div>

                                        <div class="id-number-display">
                                            <?php echo htmlspecialchars($teacher_code); ?>
                                        </div>

                                        <div class="id-info-section">
                                            <div class="id-field">
                                                <div class="id-field-label">Department</div>
                                                <div class="id-field-value"><?php echo htmlspecialchars($teacher['department']); ?></div>
                                            </div>

                                            <div class="id-field">
                                                <div class="id-field-label">Date Hired</div>
                                                <div class="id-field-value">
                                                    <?php echo $teacher['date_hired'] ? date('F j, Y', strtotime($teacher['date_hired'])) : 'N/A'; ?>
                                                </div>
                                            </div>

                                            <div class="id-field">
                                                <div class="id-field-label">Status</div>
                                                <div class="id-field-value">
                                                    <span class="badge bg-success"><?php echo strtoupper($teacher['status']); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="id-card-footer">
                                            <div class="qr-code-placeholder" id="qrCodeContainer"></div>
                                            <p>This card is property of Creative Dreams School</p>
                                            <p>Valid for School Year 2025-2026</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Faculty Information -->
                        <div class="col-lg-7 mb-4">
                            <!-- Personal Information -->
                            <div class="card">
                                <div class="card-header no-print">
                                    <span><i class="fas fa-user"></i> PERSONAL INFORMATION</span>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="fas fa-edit"></i> Edit Profile
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-id-badge"></i> Faculty Code:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($teacher_code); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-user"></i> Full Name:</div>
                                        <div class="info-value"><?php echo $fullName; ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-briefcase"></i> Position:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($teacher['position']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-building"></i> Department:</div>
                                        <div class="info-value"><?php echo htmlspecialchars($teacher['department']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-phone"></i> Contact Number:</div>
                                        <div class="info-value"><?php echo $teacher['contact_number'] ?: 'N/A'; ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> Address:</div>
                                        <div class="info-value"><?php echo $teacher['address'] ?: 'N/A'; ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-calendar-alt"></i> Date Hired:</div>
                                        <div class="info-value">
                                            <?php echo $teacher['date_hired'] ? date('F j, Y', strtotime($teacher['date_hired'])) : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label"><i class="fas fa-circle"></i> Status:</div>
                                        <div class="info-value">
                                            <span class="badge bg-success"><?php echo strtoupper($teacher['status']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Subjects Taught -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <span><i class="fas fa-book-open"></i> SUBJECTS TAUGHT</span>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($subjects)): ?>
                                        <p class="text-muted text-center py-3">No subjects assigned.</p>
                                    <?php else: ?>
                                        <table class="schedule-table">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Grade Level</th>
                                                    <th>Section</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subjects as $subject): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                                        <td>Grade <?php echo htmlspecialchars($subject['grade_level']); ?></td>
                                                        <td><?php echo htmlspecialchars($subject['section_name']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Teaching Schedule -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <span><i class="fas fa-calendar-week"></i> TEACHING SCHEDULE (SY 2025-2026)</span>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($schedules)): ?>
                                        <p class="text-muted text-center py-3">No schedule assigned.</p>
                                    <?php else: ?>
                                        <?php
                                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                                        foreach ($days as $day):
                                            if (isset($schedule_by_day[$day])):
                                        ?>
                                                <div class="day-header">
                                                    <i class="fas fa-calendar-day"></i> <?php echo $day; ?>
                                                </div>
                                                <table class="schedule-table mb-3">
                                                    <thead>
                                                        <tr>
                                                            <th>Time</th>
                                                            <th>Subject</th>
                                                            <th>Class</th>
                                                            <th>Room</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($schedule_by_day[$day] as $schedule): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong><?php echo date('g:i A', strtotime($schedule['start_time'])); ?> -
                                                                        <?php echo date('g:i A', strtotime($schedule['end_time'])); ?></strong>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                                                                <td>Grade <?php echo htmlspecialchars($schedule['grade_level']); ?> -
                                                                    <?php echo htmlspecialchars($schedule['section_name']); ?>
                                                                </td>
                                                                <td><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($schedule['room']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">
                        <i class="fas fa-edit"></i> Edit Profile
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="faculty_profile.php" method="POST" enctype="multipart/form-data" id="editProfileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="modal-body">
                        <!-- Photo Upload -->
                        <div class="photo-upload-container">
                            <div class="photo-preview" id="photoPreview">
                                <?php if ($profilePhoto): ?>
                                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo" id="previewImage">
                                <?php else: ?>
                                    <i class="fas fa-user-circle" id="previewIcon"></i>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                            <label for="profilePhotoInput" class="file-upload-label">
                                <i class="fas fa-camera"></i> Choose Photo
                            </label>
                            <p class="text-muted mt-2 mb-0" style="font-size: 12px;">Max file size: 5MB (JPG, PNG, GIF)</p>
                        </div>

                        <!-- Contact Number -->
                        <div class="mb-3">
                            <label for="contactNumber" class="form-label fw-bold">
                                <i class="fas fa-phone"></i> Contact Number
                            </label>
                            <input type="text" class="form-control" id="contactNumber" name="contact_number"
                                value="<?php echo htmlspecialchars($teacher['contact_number'] ?? ''); ?>"
                                placeholder="e.g., 09123456789">
                        </div>

                        <!-- Address -->
                        <div class="mb-3">
                            <label for="address" class="form-label fw-bold">
                                <i class="fas fa-map-marker-alt"></i> Address
                            </label>
                            <textarea class="form-control" id="address" name="address" rows="3"
                                placeholder="Enter your complete address"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong>Note:</strong> Other information like name, position, and department can only be updated by the administrator.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Preview photo before upload
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            const previewImage = document.getElementById('previewImage');
            const previewIcon = document.getElementById('previewIcon');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    if (previewImage) {
                        previewImage.src = e.target.result;
                    } else {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Profile Photo" id="previewImage" style="width: 100%; height: 100%; object-fit: cover;">`;
                    }
                };

                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form validation
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            const contactNumber = document.getElementById('contactNumber').value.trim();
            const fileInput = document.getElementById('profilePhotoInput');

            if (contactNumber && !/^[0-9+\-\s()]+$/.test(contactNumber)) {
                e.preventDefault();
                alert('Please enter a valid contact number');
                return false;
            }

            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024;
                if (fileSize > 5) {
                    e.preventDefault();
                    alert('File size must not exceed 5MB');
                    return false;
                }
            }
        });

        // Generate QR Code with teacher code only (simpler)
        function generateQRCode() {
            const qrContainer = document.getElementById('qrCodeContainer');
            if (qrContainer) {
                qrContainer.innerHTML = '';

                // Just encode the teacher code - simple and scannable
                const teacherCode = '<?php echo htmlspecialchars($teacher_code); ?>';

                new QRCode(qrContainer, {
                    text: teacherCode,
                    width: 80,
                    height: 80,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        }

        // Page load animations and QR generation
        window.addEventListener('load', function() {
            generateQRCode();

            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';

                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            const idCard = document.querySelector('.faculty-id-card');
            if (idCard) {
                idCard.style.opacity = '0';
                idCard.style.transform = 'scale(0.9)';
                idCard.style.transition = 'all 0.8s ease';

                setTimeout(() => {
                    idCard.style.opacity = '1';
                    idCard.style.transform = 'scale(1)';
                }, 300);
            }
        });

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>

</html>