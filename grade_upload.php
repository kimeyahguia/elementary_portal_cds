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

// Get current school year and quarter
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'current_quarter'");
    $stmt->execute();
    $result = $stmt->fetch();
    $current_quarter = $result ? $result['setting_value'] : '1st';
} catch (PDOException $e) {
    $current_quarter = '1st';
}

// Get teacher's subject assignments with section details
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
            $quarter = $_POST['quarter'];

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

            // Get students for this section with their grades (FIXED SQL)
            $stmt = $pdo->prepare("
                SELECT 
                    s.student_id,
                    s.student_code,
                    s.first_name,
                    s.last_name,
                    s.middle_name,
                    g.grade_id,
                    g.written_work,
                    g.performance_task,
                    g.quarterly_exam,
                    g.final_grade,
                    g.remarks
                FROM students s
                LEFT JOIN grades g ON s.student_code = g.student_code 
                    AND g.subject_code = ? 
                    AND g.teacher_code = ?
                    AND g.quarter = ?
                WHERE s.section_id = ? AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ");
            $stmt->execute([$assignment['subject_code'], $teacher_code, $quarter, $assignment['section_id']]);
            $students = $stmt->fetchAll();

            echo json_encode(['success' => true, 'students' => $students, 'assignment' => $assignment, 'quarter' => $quarter]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($_POST['action'] === 'save_grades') {
        try {
            $assignment_id = $_POST['assignment_id'];
            $quarter = $_POST['quarter'];
            $grades_data = json_decode($_POST['grades_data'], true);

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

            $saved_count = 0;
            foreach ($grades_data as $grade) {
                $student_code = $grade['student_code'];
                $written_work = !empty($grade['written_work']) ? floatval($grade['written_work']) : null;
                $performance_task = !empty($grade['performance_task']) ? floatval($grade['performance_task']) : null;
                $quarterly_exam = !empty($grade['quarterly_exam']) ? floatval($grade['quarterly_exam']) : null;

                // Calculate final grade (30% WW, 50% PT, 20% QE)
                $final_grade = null;
                $remarks = null;
                if ($written_work !== null && $performance_task !== null && $quarterly_exam !== null) {
                    $final_grade = round(($written_work * 0.30) + ($performance_task * 0.50) + ($quarterly_exam * 0.20), 2);
                    $remarks = $final_grade >= 75 ? 'Passed' : 'Failed';
                }

                // Check if grade exists
                $stmt = $pdo->prepare("
                    SELECT grade_id FROM grades 
                    WHERE student_code = ? AND subject_code = ? AND teacher_code = ? AND quarter = ?
                ");
                $stmt->execute([$student_code, $assignment['subject_code'], $teacher_code, $quarter]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Update existing grade
                    $stmt = $pdo->prepare("
                        UPDATE grades 
                        SET written_work = ?, performance_task = ?, quarterly_exam = ?, 
                            final_grade = ?, remarks = ?, date_recorded = NOW()
                        WHERE grade_id = ?
                    ");
                    $stmt->execute([$written_work, $performance_task, $quarterly_exam, $final_grade, $remarks, $existing['grade_id']]);
                    $saved_count++;
                } else {
                    // Only insert if at least one grade component is provided
                    if ($written_work !== null || $performance_task !== null || $quarterly_exam !== null) {
                        $stmt = $pdo->prepare("
                            INSERT INTO grades (student_code, subject_code, teacher_code, quarter, 
                                              written_work, performance_task, quarterly_exam, final_grade, remarks, date_recorded)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $student_code,
                            $assignment['subject_code'],
                            $teacher_code,
                            $quarter,
                            $written_work,
                            $performance_task,
                            $quarterly_exam,
                            $final_grade,
                            $remarks
                        ]);
                        $saved_count++;
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Successfully saved grades for $saved_count students"]);
        } catch (PDOException $e) {
            $pdo->rollBack();
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
    <title>Grade Upload - Creative Dreams</title>
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

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #f44336;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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

        .grade-input {
            width: 80px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
        }

        .grade-input:focus {
            outline: none;
            border-color: var(--sage-green);
            box-shadow: 0 0 0 3px rgba(82, 163, 71, 0.1);
        }

        .grade-input.invalid {
            border-color: #f44336;
        }

        .final-grade {
            font-size: 18px;
            font-weight: bold;
            color: var(--sage-green);
            padding: 10px;
            background: var(--pale-green);
            border-radius: 8px;
            text-align: center;
            min-width: 80px;
        }

        .final-grade.failed {
            color: #f44336;
            background: #ffebee;
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
            background: linear-gradient(135deg, var(--forest-green), var(--sage-green));
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

        .grade-legend {
            background: var(--pale-green);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .grade-legend h6 {
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .grade-legend p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }

        .quarter-selector {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .quarter-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            margin: 0 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .quarter-btn:hover {
            border-color: var(--sage-green);
        }

        .quarter-btn.active {
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
            color: white;
            border-color: var(--sage-green);
        }

        @media (max-width: 768px) {
            .grade-input {
                width: 60px;
                font-size: 12px;
            }

            .table {
                font-size: 12px;
            }

            .page-title {
                font-size: 22px;
            }

            .quarter-btn {
                margin: 5px;
                font-size: 14px;
                padding: 8px 15px;
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
                        <a href="grade_upload.php" class="menu-item active">
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
                        <i class="fas fa-file-upload"></i> Grade Upload
                    </h2>

                    <!-- Alert Messages -->
                    <div id="alertContainer"></div>

                    <!-- Quarter Selection -->
                    <div class="quarter-selector text-center">
                        <h6 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-calendar-alt"></i> Select Quarter</h6>
                        <button class="quarter-btn <?php echo $current_quarter === '1st' ? 'active' : ''; ?>" onclick="selectQuarter('1st', event)">1st Quarter</button>
                        <button class="quarter-btn <?php echo $current_quarter === '2nd' ? 'active' : ''; ?>" onclick="selectQuarter('2nd', event)">2nd Quarter</button>
                        <button class="quarter-btn <?php echo $current_quarter === '3rd' ? 'active' : ''; ?>" onclick="selectQuarter('3rd', event)">3rd Quarter</button>
                        <button class="quarter-btn <?php echo $current_quarter === '4th' ? 'active' : ''; ?>" onclick="selectQuarter('4th', event)">4th Quarter</button>
                    </div>

                    <!-- Grade Calculation Legend -->
                    <div class="grade-legend">
                        <h6><i class="fas fa-calculator"></i> Grade Calculation Formula (DepEd K-12)</h6>
                        <p><strong>Final Grade = </strong>(Written Work × 30%) + (Performance Task × 50%) + (Quarterly Exam × 20%)</p>
                        <p class="mb-0"><small><i class="fas fa-info-circle"></i> Grades must be between 0-100. Passing grade is 75. Final grade is automatically calculated.</small></p>
                    </div>

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
                                            <div class="subject-card" onclick="selectSubject(<?php echo $assignment['assignment_id']; ?>, event)">
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

                    <!-- Grade Input Section -->
                    <div class="card" id="gradeSection" style="display: none;">
                        <div class="card-header">
                            <span><i class="fas fa-edit"></i> ENTER GRADES - <span id="quarterInfo"></span></span>
                            <span id="subjectInfo" style="color: #7cb342;"></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Student Code</th>
                                            <th>Student Name</th>
                                            <th>Written Work (30%)</th>
                                            <th>Performance Task (50%)</th>
                                            <th>Quarterly Exam (20%)</th>
                                            <th>Final Grade</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="studentGradeList">
                                        <!-- Students will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-primary" onclick="saveGrades()">
                                    <i class="fas fa-save"></i> SAVE ALL GRADES
                                </button>
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
        let currentQuarter = '<?php echo $current_quarter; ?>';
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

        function selectQuarter(quarter, event) {
            currentQuarter = quarter;

            // Update button states
            document.querySelectorAll('.quarter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // Reload students if a subject is selected
            if (currentAssignment) {
                loadStudents(currentAssignment, currentQuarter);
            }
        }

        function selectSubject(assignmentId, event) {
            // Remove selected class from all cards
            document.querySelectorAll('.subject-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Add selected class to clicked card
            event.currentTarget.classList.add('selected');

            currentAssignment = assignmentId;
            loadStudents(assignmentId, currentQuarter);
        }

        function loadStudents(assignmentId, quarter) {
            showLoading(true);

            fetch('grade_upload.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_students&assignment_id=${assignmentId}&quarter=${quarter}`
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);

                    if (data.success) {
                        studentsData = data.students;
                        displayStudents(data.students, data.assignment, data.quarter);
                        document.getElementById('gradeSection').style.display = 'block';
                        document.getElementById('gradeSection').scrollIntoView({
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

        function displayStudents(students, assignment, quarter) {
            const tbody = document.getElementById('studentGradeList');
            tbody.innerHTML = '';

            document.getElementById('subjectInfo').textContent =
                `${assignment.subject_name} - Grade ${assignment.grade_level} ${assignment.section_name}`;
            document.getElementById('quarterInfo').textContent = quarter + ' Quarter';

            if (students.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-user-slash" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                            <p class="text-muted">No students found in this class.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            students.forEach((student, index) => {
                const row = document.createElement('tr');
                const finalGrade = student.final_grade || '-';
                const remarks = student.remarks || '-';
                const gradeClass = student.final_grade && parseFloat(student.final_grade) < 75 ? 'failed' : '';

                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td><strong>${student.student_code}</strong></td>
                    <td>${student.last_name}, ${student.first_name} ${student.middle_name || ''}</td>
                    <td>
                        <input type="number" 
                               class="grade-input" 
                               min="0" 
                               max="100" 
                               step="0.01"
                               value="${student.written_work || ''}"
                               data-student="${student.student_code}"
                               data-type="written_work"
                               onchange="calculateFinalGrade('${student.student_code}')"
                               oninput="validateGrade(this)">
                    </td>
                    <td>
                        <input type="number" 
                               class="grade-input" 
                               min="0" 
                               max="100" 
                               step="0.01"
                               value="${student.performance_task || ''}"
                               data-student="${student.student_code}"
                               data-type="performance_task"
                               onchange="calculateFinalGrade('${student.student_code}')"
                               oninput="validateGrade(this)">
                    </td>
                    <td>
                        <input type="number" 
                               class="grade-input" 
                               min="0" 
                               max="100" 
                               step="0.01"
                               value="${student.quarterly_exam || ''}"
                               data-student="${student.student_code}"
                               data-type="quarterly_exam"
                               onchange="calculateFinalGrade('${student.student_code}')"
                               oninput="validateGrade(this)">
                    </td>
                    <td>
                        <div class="final-grade ${gradeClass}" id="final-${student.student_code}">
                            ${finalGrade !== '-' ? parseFloat(finalGrade).toFixed(2) : '-'}
                        </div>
                    </td>
                    <td>
                        <span id="remarks-${student.student_code}" class="badge ${remarks === 'Passed' ? 'bg-success' : remarks === 'Failed' ? 'bg-danger' : 'bg-secondary'}">
                            ${remarks}
                        </span>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function validateGrade(input) {
            const value = parseFloat(input.value);
            if (input.value && (value < 0 || value > 100)) {
                input.classList.add('invalid');
            } else {
                input.classList.remove('invalid');
            }
        }

        function calculateFinalGrade(studentCode) {
            const writtenWork = parseFloat(document.querySelector(`input[data-student="${studentCode}"][data-type="written_work"]`).value) || 0;
            const performanceTask = parseFloat(document.querySelector(`input[data-student="${studentCode}"][data-type="performance_task"]`).value) || 0;
            const quarterlyExam = parseFloat(document.querySelector(`input[data-student="${studentCode}"][data-type="quarterly_exam"]`).value) || 0;

            // Validate grades
            const inputs = document.querySelectorAll(`input[data-student="${studentCode}"]`);
            let allValid = true;
            inputs.forEach(input => {
                const value = parseFloat(input.value);
                if (input.value && (value < 0 || value > 100)) {
                    input.classList.add('invalid');
                    allValid = false;
                } else {
                    input.classList.remove('invalid');
                }
            });

            // Calculate final grade if all inputs have values
            const finalGradeElement = document.getElementById(`final-${studentCode}`);
            const remarksElement = document.getElementById(`remarks-${studentCode}`);

            if (writtenWork && performanceTask && quarterlyExam && allValid) {
                const finalGrade = (writtenWork * 0.30) + (performanceTask * 0.50) + (quarterlyExam * 0.20);
                finalGradeElement.textContent = finalGrade.toFixed(2);

                // Update styling based on pass/fail
                if (finalGrade >= 75) {
                    finalGradeElement.classList.remove('failed');
                    remarksElement.textContent = 'Passed';
                    remarksElement.className = 'badge bg-success';
                } else {
                    finalGradeElement.classList.add('failed');
                    remarksElement.textContent = 'Failed';
                    remarksElement.className = 'badge bg-danger';
                }
            } else {
                finalGradeElement.textContent = '-';
                finalGradeElement.classList.remove('failed');
                remarksElement.textContent = '-';
                remarksElement.className = 'badge bg-secondary';
            }
        }

        function saveGrades() {
            if (!currentAssignment) {
                showAlert('Please select a subject first', 'warning');
                return;
            }

            // Collect all grades
            const gradesData = [];
            const inputs = document.querySelectorAll('.grade-input');
            let hasInvalid = false;

            // Group inputs by student
            const studentGrades = {};
            inputs.forEach(input => {
                const studentCode = input.dataset.student;
                const gradeType = input.dataset.type;
                const value = input.value;

                // Validate
                if (value && (parseFloat(value) < 0 || parseFloat(value) > 100)) {
                    input.classList.add('invalid');
                    hasInvalid = true;
                } else {
                    input.classList.remove('invalid');
                }

                if (!studentGrades[studentCode]) {
                    studentGrades[studentCode] = {
                        student_code: studentCode,
                        written_work: '',
                        performance_task: '',
                        quarterly_exam: ''
                    };
                }

                studentGrades[studentCode][gradeType] = value;
            });

            if (hasInvalid) {
                showAlert('Please correct invalid grades (must be between 0-100)', 'danger');
                return;
            }

            // Convert to array
            for (let studentCode in studentGrades) {
                gradesData.push(studentGrades[studentCode]);
            }

            // Confirm before saving
            if (!confirm(`Save grades for ${gradesData.length} students in ${currentQuarter} Quarter?`)) {
                return;
            }

            showLoading(true);

            fetch('grade_upload.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=save_grades&assignment_id=${currentAssignment}&quarter=${currentQuarter}&grades_data=${encodeURIComponent(JSON.stringify(gradesData))}`
                })
                .then(response => response.json())
                .then(data => {
                    showLoading(false);

                    if (data.success) {
                        showAlert('✓ ' + data.message, 'success');
                        // Reload students to show updated data
                        loadStudents(currentAssignment, currentQuarter);
                    } else {
                        showAlert('Error saving grades: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    showLoading(false);
                    showAlert('Error: ' + error.message, 'danger');
                });
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveGrades();
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