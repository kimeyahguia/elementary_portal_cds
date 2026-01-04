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

// Fetch teacher details
try {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_code = ?");
    $stmt->execute([$teacher_code]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        header("Location: login.php");
        exit();
    }

    $first_name = $teacher['first_name'];
    $last_name = $teacher['last_name'];
    $teacher_id = $teacher['teacher_id'];
} catch (PDOException $e) {
    die("Error fetching teacher data: " . $e->getMessage());
}

$teacherName = htmlspecialchars($first_name . ' ' . $last_name);

// NEW CODE - PREVENTS DUPLICATES:
try {
    $stmt = $pdo->prepare("
        SELECT 
            MIN(ss.schedule_id) as assignment_id,
            gst.subject_code,
            s.subject_name,
            sec.grade_level,
            sec.section_name,
            sec.section_id
        FROM section_schedules ss
        INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
        INNER JOIN subjects s ON gst.subject_code = s.subject_code
        INNER JOIN sections sec ON ss.section_id = sec.section_id
        WHERE ss.teacher_code = ? AND ss.is_active = 1
        GROUP BY gst.subject_code, s.subject_name, sec.grade_level, sec.section_name, sec.section_id
        ORDER BY sec.grade_level, sec.section_name, s.subject_name
    ");
    $stmt->execute([$teacher_code]);
    $subject_assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching subjects: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'get_students') {
        try {
            $assignment_id = $_POST['assignment_id'];
            $date = $_POST['date'];

            // Get assignment details
            $stmt = $pdo->prepare("
    SELECT 
        ss.schedule_id as assignment_id,
        gst.subject_code,
        s.subject_name,
        sec.grade_level,
        sec.section_name,
        sec.section_id,
        ss.teacher_code
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN subjects s ON gst.subject_code = s.subject_code
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.schedule_id = ? AND ss.teacher_code = ?
");
            $stmt->execute([$assignment_id, $teacher_code]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                echo json_encode(['success' => false, 'message' => 'Invalid assignment']);
                exit();
            }

            // Get students with attendance status for the selected date - FIXED QUERY
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    a.attendance_id,
                    a.status,
                    a.remarks
                FROM students s
                LEFT JOIN attendance a ON s.student_code = a.student_code 
                    AND a.subject_code = ? 
                    AND a.teacher_code = ?
                    AND DATE(a.date) = ?
                WHERE s.section_id = ? AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ");
            $stmt->execute([$assignment['subject_code'], $teacher_code, $date, $assignment['section_id']]);
            $students = $stmt->fetchAll();

            echo json_encode(['success' => true, 'students' => $students, 'assignment' => $assignment]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($_POST['action'] === 'save_attendance') {
        try {
            $assignment_id = $_POST['assignment_id'];
            $date = $_POST['date'];
            $attendance_data = json_decode($_POST['attendance_data'], true);

            // Validate date format
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
                echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                exit();
            }

            // Get assignment details
            $stmt = $pdo->prepare("
    SELECT gst.subject_code, sec.section_id
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.schedule_id = ? AND ss.teacher_code = ?
");
            $stmt->execute([$assignment_id, $teacher_code]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                echo json_encode(['success' => false, 'message' => 'Invalid assignment']);
                exit();
            }

            $pdo->beginTransaction();

            $successCount = 0;
            foreach ($attendance_data as $attendance) {
                $student_code = $attendance['student_code'];
                $status = $attendance['status'];
                $remarks = isset($attendance['remarks']) ? trim($attendance['remarks']) : null;

                // Validate status
                if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
                    continue;
                }

                // Verify student belongs to this section
                $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_code = ? AND section_id = ? AND status = 'active'");
                $stmt->execute([$student_code, $assignment['section_id']]);
                if (!$stmt->fetch()) {
                    continue;
                }

                // Check if attendance exists
                $stmt = $pdo->prepare("
                    SELECT attendance_id FROM attendance 
                    WHERE student_code = ? AND subject_code = ? AND teacher_code = ? AND DATE(date) = ?
                ");
                $stmt->execute([$student_code, $assignment['subject_code'], $teacher_code, $date]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Update existing attendance
                    $stmt = $pdo->prepare("
                        UPDATE attendance 
                        SET status = ?, remarks = ?
                        WHERE attendance_id = ?
                    ");
                    $stmt->execute([$status, $remarks, $existing['attendance_id']]);
                } else {
                    // Insert new attendance
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (student_code, subject_code, teacher_code, date, status, remarks)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$student_code, $assignment['subject_code'], $teacher_code, $date, $status, $remarks]);
                }
                $successCount++;
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Attendance saved successfully for $successCount student(s)"]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($_POST['action'] === 'get_attendance_summary') {
        try {
            $assignment_id = $_POST['assignment_id'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];

            // Validate dates
            $startObj = DateTime::createFromFormat('Y-m-d', $start_date);
            $endObj = DateTime::createFromFormat('Y-m-d', $end_date);

            if (
                !$startObj || $startObj->format('Y-m-d') !== $start_date ||
                !$endObj || $endObj->format('Y-m-d') !== $end_date
            ) {
                echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                exit();
            }

            if ($start_date > $end_date) {
                echo json_encode(['success' => false, 'message' => 'Start date must be before end date']);
                exit();
            }

            // Get assignment details
            $stmt = $pdo->prepare("
    SELECT 
        ss.schedule_id as assignment_id,
        gst.subject_code,
        sec.section_id,
        ss.teacher_code
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.schedule_id = ? AND ss.teacher_code = ?
");
            $stmt->execute([$assignment_id, $teacher_code]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                echo json_encode(['success' => false, 'message' => 'Invalid assignment']);
                exit();
            }

            // Get attendance summary
            $stmt = $pdo->prepare("
                SELECT 
                    s.student_code,
                    s.first_name,
                    s.last_name,
                    s.middle_name,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_count,
                    COUNT(a.attendance_id) as total_days
                FROM students s
                LEFT JOIN attendance a ON s.student_code = a.student_code 
                    AND a.subject_code = ? 
                    AND a.teacher_code = ?
                    AND DATE(a.date) BETWEEN ? AND ?
                WHERE s.section_id = ? AND s.status = 'active'
                GROUP BY s.student_code, s.first_name, s.last_name, s.middle_name
                ORDER BY s.last_name, s.first_name
            ");
            $stmt->execute([$assignment['subject_code'], $teacher_code, $start_date, $end_date, $assignment['section_id']]);
            $summary = $stmt->fetchAll();

            echo json_encode(['success' => true, 'summary' => $summary]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Creative Dreams</title>
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
            position: relative;
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

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
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .teacher-avatar i {
            font-size: 40px;
            color: white;
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

        .subject-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            margin-bottom: 15px;
        }

        .subject-card:hover {
            border-color: var(--sage-green);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .subject-card.selected {
            border-color: var(--sage-green);
            background: var(--pale-green);
        }

        .attendance-controls {
            background: var(--pale-green);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .date-input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-weight: 600;
        }

        .date-input:focus {
            outline: none;
            border-color: var(--sage-green);
        }

        .status-btn {
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 14px;
            margin: 0 3px;
        }

        .status-btn:hover {
            transform: translateY(-2px);
        }

        .status-btn.present {
            background: #4caf50;
            color: white;
            border-color: #4caf50;
        }

        .status-btn.absent {
            background: #f44336;
            color: white;
            border-color: #f44336;
        }

        .status-btn.late {
            background: #ff9800;
            color: white;
            border-color: #ff9800;
        }

        .status-btn.excused {
            background: #2196f3;
            color: white;
            border-color: #2196f3;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
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

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin: 0;
        }

        .table thead {
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
        }

        .table tbody tr {
            transition: all 0.3s;
        }

        .table tbody tr:hover {
            background: #f5f5f5;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.show {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--sage-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .tab-btn {
            padding: 12px 25px;
            border: none;
            background: #e0e0e0;
            color: #666;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            font-weight: 600;
            margin-right: 5px;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: white;
            color: var(--sage-green);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--sage-green);
            margin-bottom: 15px;
        }

        .stat-box {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 8px;
            margin: 0 5px;
            font-weight: 600;
        }

        .stat-box.present {
            background: #e8f5e9;
            color: #4caf50;
        }

        .stat-box.absent {
            background: #ffebee;
            color: #f44336;
        }

        .stat-box.late {
            background: #fff3e0;
            color: #ff9800;
        }

        .stat-box.excused {
            background: #e3f2fd;
            color: #2196f3;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 22px;
            }

            .status-btn {
                padding: 6px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Header -->
    <div class="top-header">
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
            <div class="col-lg-2 col-md-3 mb-4">
                <div class="sidebar">
                    <div class="welcome-section">
                        <div class="teacher-avatar">
                            <i class="fas fa-chalkboard-teacher"></i>
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
                        <a href="attendance.php" class="menu-item active">
                            <i class="fas fa-clipboard-check"></i>
                            <span>ATTENDANCE</span>
                        </a>
                        <a href="teacher_reports.php" class="menu-item">
                            <i class="fas fa-file-alt"></i>
                            <span>ANALYTICS</span>
                        </a>
                        <a href="faculty_profile.php" class="menu-item">
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
                    <h2 class="page-title">
                        <i class="fas fa-clipboard-check"></i> Attendance Management
                    </h2>

                    <!-- Alert Messages -->
                    <div id="alertContainer"></div>

                    <!-- Tabs -->
                    <div class="mb-3">
                        <button class="tab-btn active" onclick="switchTab('record', event)">
                            <i class="fas fa-user-check"></i> Record Attendance
                        </button>
                        <button class="tab-btn" onclick="switchTab('summary', event)">
                            <i class="fas fa-chart-bar"></i> Attendance Summary
                        </button>
                    </div>

                    <!-- Tab 1: Record Attendance -->
                    <div id="recordTab" class="tab-content active">
                        <!-- Subject Selection -->
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-book"></i> SELECT SUBJECT & CLASS</span>
                            </div>
                            <div class="card-body">
                                <div class="row" id="subjectList">
                                    <?php if (empty($subject_assignments)): ?>
                                        <div class="col-12 text-center py-4">
                                            <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                            <p class="text-muted">No subject assignments found.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($subject_assignments as $assignment): ?>
                                            <div class="col-lg-4 col-md-6 mb-3">
                                                <div class="subject-card" onclick="selectSubject(<?php echo $assignment['assignment_id']; ?>)">
                                                    <h5 style="color: #7cb342; margin-bottom: 10px;">
                                                        <i class="fas fa-book-open"></i> <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                                    </h5>
                                                    <p class="mb-0" style="color: #666;">
                                                        <i class="fas fa-users"></i> Grade <?php echo htmlspecialchars($assignment['grade_level']); ?> -
                                                        <?php echo htmlspecialchars($assignment['section_name']); ?>
                                                    </p>
                                                    <p class="mb-0" style="color: #999; font-size: 12px;">
                                                        <i class="fas fa-barcode"></i> <?php echo htmlspecialchars($assignment['subject_code']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Recording Section -->
                        <div class="card" id="attendanceSection" style="display: none;">
                            <div class="card-header">
                                <span><i class="fas fa-clipboard-check"></i> MARK ATTENDANCE</span>
                                <span id="subjectInfo" style="color: #7cb342;"></span>
                            </div>
                            <div class="card-body">
                                <!-- Date Selector and Quick Actions -->
                                <div class="attendance-controls">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Select Date:</label>
                                            <input type="date" id="attendanceDate" class="form-control date-input" value="<?php echo date('Y-m-d'); ?>" onchange="loadStudents(currentAssignment, this.value)">
                                        </div>
                                        <div class="col-md-8 text-end">
                                            <label class="form-label fw-bold">Quick Actions:</label><br>
                                            <button class="btn btn-sm btn-success" onclick="markAll('present')">
                                                <i class="fas fa-check-double"></i> Mark All Present
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="markAll('absent')">
                                                <i class="fas fa-times"></i> Mark All Absent
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Student Code</th>
                                                <th>Student Name</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody id="studentAttendanceList">
                                            <!-- Students will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-3">
                                    <button type="button" class="btn btn-primary" onclick="saveAttendance()">
                                        <i class="fas fa-save"></i> SAVE ATTENDANCE
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Attendance Summary -->
                    <div id="summaryTab" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fas fa-chart-bar"></i> ATTENDANCE SUMMARY</span>
                            </div>
                            <div class="card-body">
                                <!-- Date Range Selector -->
                                <div class="attendance-controls mb-4">
                                    <div class="row align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> From Date:</label>
                                            <input type="date" id="summaryStartDate" class="form-control date-input" value="<?php echo date('Y-m-01'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> To Date:</label>
                                            <input type="date" id="summaryEndDate" class="form-control date-input" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold"><i class="fas fa-book"></i> Select Subject:</label>
                                            <select id="summarySubject" class="form-select date-input">
                                                <option value="">-- Select Subject --</option>
                                                <?php foreach ($subject_assignments as $assignment): ?>
                                                    <option value="<?php echo $assignment['assignment_id']; ?>">
                                                        <?php echo htmlspecialchars($assignment['subject_name']); ?> -
                                                        Grade <?php echo htmlspecialchars($assignment['grade_level']); ?>
                                                        <?php echo htmlspecialchars($assignment['section_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-primary w-100" onclick="loadSummary()">
                                                <i class="fas fa-search"></i> View Summary
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary Results -->
                                <div id="summaryResults" style="display: none;">
                                    <div id="summaryContent"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentAssignment = null;
        let studentsData = [];

        function showAlert(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.getElementById('alertContainer').appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (show) {
                overlay.classList.add('show');
            } else {
                overlay.classList.remove('show');
            }
        }

        function switchTab(tab, event) {
            // Update button states
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Show/hide content
            document.getElementById('recordTab').classList.remove('active');
            document.getElementById('summaryTab').classList.remove('active');

            if (tab === 'record') {
                document.getElementById('recordTab').classList.add('active');
            } else {
                document.getElementById('summaryTab').classList.add('active');
            }
        }

        function selectSubject(assignmentId) {
            // Remove selected class from all cards
            document.querySelectorAll('.subject-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');

            currentAssignment = assignmentId;
            const date = document.getElementById('attendanceDate').value;
            loadStudents(assignmentId, date);
        }

        function loadStudents(assignmentId, date) {
            showLoading(true);

            fetch('attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_students&assignment_id=${assignmentId}&date=${date}`
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);

                    if (data.success) {
                        studentsData = data.students;
                        displayStudents(data.students, data.assignment);
                        document.getElementById('attendanceSection').style.display = 'block';
                        document.getElementById('attendanceSection').scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    } else {
                        showAlert('Error loading students: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    showLoading(false);
                    showAlert('Error: ' + error.message, 'danger');
                });
        }

        function displayStudents(students, assignment) {
            const tbody = document.getElementById('studentAttendanceList');
            tbody.innerHTML = '';

            document.getElementById('subjectInfo').textContent =
                `${assignment.subject_name} - Grade ${assignment.grade_level} ${assignment.section_name}`;

            if (students.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="fas fa-user-slash" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                            <p class="text-muted">No students found in this class.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            students.forEach((student, index) => {
                const currentStatus = student.status || 'present';
                const currentRemarks = student.remarks || '';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td><strong>${student.student_code}</strong></td>
                    <td>${student.last_name}, ${student.first_name} ${student.middle_name || ''}</td>
                    <td>
                        <button class="status-btn ${currentStatus === 'present' ? 'present' : ''}" 
                                onclick="setStatus('${student.student_code}', 'present')"
                                data-student="${student.student_code}"
                                data-status="present">
                            <i class="fas fa-check"></i> Present
                        </button>
                        <button class="status-btn ${currentStatus === 'absent' ? 'absent' : ''}" 
                                onclick="setStatus('${student.student_code}', 'absent')"
                                data-student="${student.student_code}"
                                data-status="absent">
                            <i class="fas fa-times"></i> Absent
                        </button>
                        <button class="status-btn ${currentStatus === 'late' ? 'late' : ''}" 
                                onclick="setStatus('${student.student_code}', 'late')"
                                data-student="${student.student_code}"
                                data-status="late">
                            <i class="fas fa-clock"></i> Late
                        </button>
                        <button class="status-btn ${currentStatus === 'excused' ? 'excused' : ''}" 
                                onclick="setStatus('${student.student_code}', 'excused')"
                                data-student="${student.student_code}"
                                data-status="excused">
                            <i class="fas fa-file-medical"></i> Excused
                        </button>
                    </td>
                    <td>
                        <input type="text" 
                               class="form-control form-control-sm" 
                               placeholder="Optional remarks..."
                               value="${currentRemarks}"
                               data-student="${student.student_code}"
                               data-field="remarks"
                               style="max-width: 200px;">
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function setStatus(studentCode, status) {
            // Remove active class from all buttons for this student
            document.querySelectorAll(`button[data-student="${studentCode}"]`).forEach(btn => {
                btn.classList.remove('present', 'absent', 'late', 'excused');
            });

            // Add active class to clicked button
            event.target.classList.add(status);
        }

        function markAll(status) {
            document.querySelectorAll('.status-btn').forEach(btn => {
                btn.classList.remove('present', 'absent', 'late', 'excused');
                if (btn.dataset.status === status) {
                    btn.classList.add(status);
                }
            });
            showAlert(`All students marked as ${status}`, 'info');
        }

        function saveAttendance() {
            if (!currentAssignment) {
                showAlert('Please select a subject first', 'warning');
                return;
            }

            const date = document.getElementById('attendanceDate').value;
            const attendanceData = [];

            // Collect attendance data
            studentsData.forEach(student => {
                const statusButtons = document.querySelectorAll(`button[data-student="${student.student_code}"]`);
                let selectedStatus = 'present'; // default

                statusButtons.forEach(btn => {
                    if (btn.classList.contains('present')) selectedStatus = 'present';
                    else if (btn.classList.contains('absent')) selectedStatus = 'absent';
                    else if (btn.classList.contains('late')) selectedStatus = 'late';
                    else if (btn.classList.contains('excused')) selectedStatus = 'excused';
                });

                const remarksInput = document.querySelector(`input[data-student="${student.student_code}"][data-field="remarks"]`);
                const remarks = remarksInput ? remarksInput.value : '';

                attendanceData.push({
                    student_code: student.student_code,
                    status: selectedStatus,
                    remarks: remarks
                });
            });

            // Confirm before saving
            if (!confirm(`Save attendance for ${attendanceData.length} students on ${date}?`)) {
                return;
            }

            showLoading(true);

            fetch('attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=save_attendance&assignment_id=${currentAssignment}&date=${date}&attendance_data=${encodeURIComponent(JSON.stringify(attendanceData))}`
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);

                    if (data.success) {
                        showAlert('✓ ' + data.message, 'success');
                        // Reload to show updated data
                        loadStudents(currentAssignment, date);
                    } else {
                        showAlert('Error saving attendance: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    showLoading(false);
                    showAlert('Error: ' + error.message, 'danger');
                });
        }

        function loadSummary() {
            const assignmentId = document.getElementById('summarySubject').value;
            const startDate = document.getElementById('summaryStartDate').value;
            const endDate = document.getElementById('summaryEndDate').value;

            if (!assignmentId) {
                showAlert('Please select a subject', 'warning');
                return;
            }

            if (!startDate || !endDate) {
                showAlert('Please select date range', 'warning');
                return;
            }

            showLoading(true);

            fetch('attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_attendance_summary&assignment_id=${assignmentId}&start_date=${startDate}&end_date=${endDate}`
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);

                    if (data.success) {
                        displaySummary(data.summary);
                    } else {
                        showAlert('Error loading summary: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    showLoading(false);
                    showAlert('Error: ' + error.message, 'danger');
                });
        }

        function displaySummary(summary) {
            const container = document.getElementById('summaryContent');

            if (summary.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                        <p class="text-muted">No attendance records found for the selected period.</p>
                    </div>
                `;
                document.getElementById('summaryResults').style.display = 'block';
                return;
            }

            let html = '';

            summary.forEach((student, index) => {
                const totalDays = parseInt(student.total_days) || 0;
                const presentCount = parseInt(student.present_count) || 0;
                const absentCount = parseInt(student.absent_count) || 0;
                const lateCount = parseInt(student.late_count) || 0;
                const excusedCount = parseInt(student.excused_count) || 0;

                const attendanceRate = totalDays > 0 ? ((presentCount / totalDays) * 100).toFixed(1) : 0;

                html += `
                    <div class="summary-card">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h6 class="mb-1"><strong>${index + 1}. ${student.last_name}, ${student.first_name} ${student.middle_name || ''}</strong></h6>
                                <p class="mb-0 text-muted"><small>${student.student_code}</small></p>
                            </div>
                            <div class="col-md-6">
                                <span class="stat-box present">
                                    <i class="fas fa-check"></i> Present: ${presentCount}
                                </span>
                                <span class="stat-box absent">
                                    <i class="fas fa-times"></i> Absent: ${absentCount}
                                </span>
                                <span class="stat-box late">
                                    <i class="fas fa-clock"></i> Late: ${lateCount}
                                </span>
                                <span class="stat-box excused">
                                    <i class="fas fa-file-medical"></i> Excused: ${excusedCount}
                                </span>
                            </div>
                            <div class="col-md-2 text-end">
                                <div style="font-size: 24px; font-weight: bold; color: ${attendanceRate >= 90 ? '#4caf50' : attendanceRate >= 75 ? '#ff9800' : '#f44336'};">
                                    ${attendanceRate}%
                                </div>
                                <small class="text-muted">Attendance Rate</small>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            document.getElementById('summaryResults').style.display = 'block';
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                if (currentAssignment) {
                    saveAttendance();
                }
            }
        });

        // Add animation on load
        window.addEventListener('load', function() {
            const cards = document.querySelectorAll('.subject-card, .card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';

                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>

</html>