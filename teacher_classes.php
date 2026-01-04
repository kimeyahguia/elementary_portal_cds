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

    // Get subjects taught
    $stmt = $pdo->prepare("
    SELECT DISTINCT sub.subject_name 
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN subjects sub ON gst.subject_code = sub.subject_code
    WHERE ss.teacher_code = ? AND ss.is_active = 1
");
    $stmt->execute([$teacher_code]);
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $subject_list = !empty($subjects) ? implode(', ', $subjects) : 'No subjects assigned';
} catch (PDOException $e) {
    die("Error fetching teacher data: " . $e->getMessage());
}

// Fetch classes taught by this teacher
try {
    $stmt = $pdo->prepare("
    SELECT DISTINCT
        sec.section_id,
        gst.subject_code,
        sub.subject_name,
        sec.grade_level,
        sec.section_name,
        sec.room_assignment,
        (SELECT COUNT(DISTINCT s.student_id) 
         FROM students s 
         WHERE s.section_id = sec.section_id 
         AND s.status = 'active') as student_count
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN subjects sub ON gst.subject_code = sub.subject_code
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.teacher_code = ? AND ss.is_active = 1
    ORDER BY sec.grade_level, sec.section_name, sub.subject_name
");
    $stmt->execute([$teacher_code]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching classes: " . $e->getMessage());
}

// Fetch advisory class
try {
    $stmt = $pdo->prepare("
        SELECT 
            sec.section_id,
            sec.grade_level,
            sec.section_name,
            sec.room_assignment,
            COUNT(DISTINCT s.student_id) as student_count
        FROM sections sec
        LEFT JOIN students s ON sec.section_id = s.section_id AND s.status = 'active'
        WHERE sec.adviser_code = ? AND sec.is_active = 1
        GROUP BY sec.section_id, sec.grade_level, sec.section_name, sec.room_assignment
    ");
    $stmt->execute([$teacher_code]);
    $advisory_class = $stmt->fetch();
} catch (PDOException $e) {
    die("Error fetching advisory class: " . $e->getMessage());
}

// View mode: class_list, student_list, student_detail, advisory_list
$view_mode = 'class_list';
$selected_class = null;
$students = [];
$selected_student = null;
$is_advisory = false;

// Check if viewing advisory class
if (isset($_GET['view']) && $_GET['view'] === 'advisory' && isset($_GET['section_id']) && !isset($_GET['student_id'])) {
    $view_mode = 'advisory_list';
    $is_advisory = true;
    $section_id = $_GET['section_id'];

    // Get sorting preference
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'asc';

    try {
        // Verify this teacher is the adviser for this section
        $stmt = $pdo->prepare("
            SELECT 
                sec.grade_level,
                sec.section_name,
                sec.room_assignment
            FROM sections sec
            WHERE sec.adviser_code = ? 
            AND sec.section_id = ?
            AND sec.is_active = 1
        ");
        $stmt->execute([$teacher_code, $section_id]);
        $selected_class = $stmt->fetch();

        if ($selected_class) {
            // Determine ORDER BY clause based on sort parameter
            $order_clause = '';
            switch ($sort_by) {
                case 'desc':
                    $order_clause = 'ORDER BY s.last_name DESC, s.first_name DESC';
                    break;
                case 'asc':
                default:
                    $order_clause = 'ORDER BY s.last_name ASC, s.first_name ASC';
                    break;
            }

            // Fetch all students in advisory class (no grades shown)
            $stmt = $pdo->prepare("
                SELECT 
                    s.student_id,
                    s.student_code,
                    s.first_name,
                    s.last_name,
                    s.middle_name,
                    s.gender,
                    s.birthdate,
                    p.contact_number,
                    p.email as parent_email
                FROM students s
                LEFT JOIN parents p ON s.parent_id = p.parent_id
                WHERE s.section_id = ? AND s.status = 'active'
                $order_clause
            ");
            $stmt->execute([$section_id]);
            $students = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        die("Error fetching advisory students: " . $e->getMessage());
    }
}

// Check if viewing subject class student list
elseif (isset($_GET['section_id']) && isset($_GET['subject_code']) && !isset($_GET['student_id'])) {
    $view_mode = 'student_list';
    $section_id = $_GET['section_id'];
    $subject_code = $_GET['subject_code'];

    // Get sorting preference
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'asc';

    try {
        // Verify this teacher teaches this class
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                sub.subject_name,
                sec.grade_level,
                sec.section_name,
                sec.room_assignment
            FROM section_schedules ss
            INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
            INNER JOIN subjects sub ON gst.subject_code = sub.subject_code
            INNER JOIN sections sec ON ss.section_id = sec.section_id
            WHERE ss.teacher_code = ? 
            AND ss.section_id = ? 
            AND gst.subject_code = ?
            AND ss.is_active = 1
        ");
        $stmt->execute([$teacher_code, $section_id, $subject_code]);
        $selected_class = $stmt->fetch();

        if ($selected_class) {
            // Determine ORDER BY clause based on sort parameter
            $order_clause = '';
            switch ($sort_by) {
                case 'desc':
                    $order_clause = 'ORDER BY s.last_name DESC, s.first_name DESC';
                    break;
                case 'grade_high':
                    $order_clause = 'ORDER BY average_grade DESC, s.last_name ASC';
                    break;
                case 'grade_low':
                    $order_clause = 'ORDER BY average_grade ASC, s.last_name ASC';
                    break;
                case 'asc':
                default:
                    $order_clause = 'ORDER BY s.last_name ASC, s.first_name ASC';
                    break;
            }

            // Fetch students in this class
            $stmt = $pdo->prepare("
                SELECT 
                    s.student_id,
                    s.student_code,
                    s.first_name,
                    s.last_name,
                    s.middle_name,
                    s.gender,
                    s.birthdate,
                    COALESCE(AVG(g.final_grade), 0) as average_grade,
                    p.contact_number,
                    p.email as parent_email
                FROM students s
                LEFT JOIN parents p ON s.parent_id = p.parent_id
                LEFT JOIN grades g ON s.student_code = g.student_code 
                    AND g.subject_code = ?
                    AND g.teacher_code = ?
                WHERE s.section_id = ? 
                AND s.status = 'active'
                GROUP BY s.student_id, s.student_code, s.first_name, s.last_name, 
                         s.middle_name, s.gender, s.birthdate, p.contact_number, p.email
                $order_clause
            ");
            $stmt->execute([$subject_code, $teacher_code, $section_id]);
            $students = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        die("Error fetching students: " . $e->getMessage());
    }
}

// Check if viewing individual student
if (isset($_GET['student_id'])) {
    $view_mode = 'student_detail';
    $student_id = $_GET['student_id'];
    $section_id = $_GET['section_id'];
    $is_advisory = isset($_GET['view']) && $_GET['view'] === 'advisory';

    try {
        if ($is_advisory) {
            // Verify advisory class
            $stmt = $pdo->prepare("
                SELECT 
                    sec.grade_level,
                    sec.section_name,
                    sec.room_assignment
                FROM sections sec
                WHERE sec.adviser_code = ? 
                AND sec.section_id = ?
                AND sec.is_active = 1
            ");
            $stmt->execute([$teacher_code, $section_id]);
            $selected_class = $stmt->fetch();
            // DEBUG - Remove this after testing
            if (!$selected_class) {
                echo "<div class='alert alert-danger'>";
                echo "DEBUG INFO:<br>";
                echo "Teacher Code: " . htmlspecialchars($teacher_code) . "<br>";
                echo "Section ID: " . htmlspecialchars($section_id) . "<br>";
                echo "Is Advisory: " . ($is_advisory ? 'Yes' : 'No') . "<br>";

                // Check what's actually in the database
                $debug_stmt = $pdo->prepare("SELECT section_id, grade_level, section_name, adviser_code, is_active FROM sections WHERE section_id = ?");
                $debug_stmt->execute([$section_id]);
                $debug_result = $debug_stmt->fetch();
                echo "Database shows:<br>";
                echo "Section ID: " . ($debug_result ? $debug_result['section_id'] : 'NOT FOUND') . "<br>";
                echo "Grade/Section: " . ($debug_result ? $debug_result['grade_level'] . '-' . $debug_result['section_name'] : 'N/A') . "<br>";
                echo "Adviser Code: " . ($debug_result ? $debug_result['adviser_code'] : 'N/A') . "<br>";
                echo "Is Active: " . ($debug_result ? $debug_result['is_active'] : 'N/A') . "<br>";
                echo "</div>";
            }
        } else {
            $subject_code = $_GET['subject_code'];
            // Verify subject class
            $stmt = $pdo->prepare("
    SELECT DISTINCT
        sub.subject_name,
        gst.subject_code,
        sec.grade_level,
        sec.section_name,
        sec.room_assignment
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN subjects sub ON gst.subject_code = sub.subject_code
    INNER JOIN sections sec ON ss.section_id = sec.section_id
    WHERE ss.teacher_code = ? 
    AND ss.section_id = ? 
    AND gst.subject_code = ?
    AND ss.is_active = 1
");
            $stmt->execute([$teacher_code, $section_id, $subject_code]);
            $selected_class = $stmt->fetch();
        }

        if ($selected_class) {
            // Fetch student details
            $stmt = $pdo->prepare("
                SELECT 
                    s.student_id,
                    s.student_code,
                    s.first_name,
                    s.last_name,
                    s.middle_name,
                    s.gender,
                    s.birthdate,
                    s.address as student_address,
                    s.date_enrolled,
                    p.parent_code,
                    p.first_name as parent_first_name,
                    p.last_name as parent_last_name,
                    p.relationship,
                    p.email as parent_email,
                    p.contact_number,
                    p.address as parent_address,
                    p.occupation
                FROM students s
                LEFT JOIN parents p ON s.parent_id = p.parent_id
                WHERE s.student_id = ? AND s.section_id = ? AND s.status = 'active'
            ");
            $stmt->execute([$student_id, $section_id]);
            $selected_student = $stmt->fetch();

            // Fetch quarterly grades
            if (!$is_advisory && isset($subject_code)) {
                $stmt = $pdo->prepare("
        SELECT 
            g.quarter,
            g.final_grade
        FROM grades g
        WHERE g.student_code = ? 
        AND g.subject_code = ?
        AND g.teacher_code = ?
        ORDER BY g.quarter
    ");
                $stmt->execute([$selected_student['student_code'], $subject_code, $teacher_code]);
                $quarterly_grades = $stmt->fetchAll();
            } else {
                // For advisory view, get all subject averages per quarter
                $stmt = $pdo->prepare("
                    SELECT 
                        quarter,
                        AVG(final_grade) as average_grade
                    FROM grades
                    WHERE student_code = ?
                    GROUP BY quarter
                    ORDER BY quarter
                ");
                $stmt->execute([$selected_student['student_code']]);
                $quarterly_grades = $stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        die("Error fetching student details: " . $e->getMessage());
    }
}

$teacherName = htmlspecialchars($first_name . ' ' . $last_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - Teacher Portal</title>
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
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--sage-green);
            font-weight: 600;
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

        .faculty-id-section .subject {
            font-size: 14px;
            color: #666;
            margin-top: 8px;
            font-weight: 600;
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
            color: #2c3e50;
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

        .main-content {
            padding: 20px;
        }

        .page-title {
            color: var(--text-dark);
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .class-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
            cursor: pointer;
            border-left: 5px solid var(--sage-green);
        }

        .class-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .advisory-card {
            border-left: 5px solid #ff9800;
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
        }

        .back-btn {
            background: var(--sage-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .back-btn:hover {
            background: var(--forest-green);
            color: white;
            transform: translateY(-2px);
        }

        .student-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .student-row {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            transition: all 0.3s;
            cursor: pointer;
        }

        .student-row:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .student-row:last-child {
            border-bottom: none;
        }

        .student-avatar-small {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            font-weight: bold;
        }

        .student-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
            margin: 0 auto 20px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .sort-btn {
            background: white;
            border: 2px solid var(--sage-green);
            color: var(--sage-green);
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
            cursor: pointer;
        }

        .sort-btn:hover {
            background: var(--sage-green);
            color: white;
        }

        .sort-btn.active {
            background: var(--sage-green);
            color: white;
        }

        .search-box-custom {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            width: 300px;
            transition: all 0.3s;
        }

        .search-box-custom:focus {
            outline: none;
            border-color: var(--sage-green);
        }

        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Prevent scroll jump on page load */
        .main-content {
            scroll-margin-top: 20px;
        }

        .quarter-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            margin: 5px;
        }

        .quarter-badge.q1 {
            background: #e3f2fd;
            color: #1976d2;
        }

        .quarter-badge.q2 {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .quarter-badge.q3 {
            background: #fff3e0;
            color: #f57c00;
        }

        .quarter-badge.q4 {
            background: #e8f5e9;
            color: #388e3c;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }

            .brand-text h1 {
                font-size: 20px;
            }

            .page-title {
                font-size: 22px;
            }

            .search-box-custom {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>
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
                        <div class="subject">
                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject_list); ?>
                        </div>
                    </div>

                    <nav>
                        <a href="teacher_dashboard.php" class="menu-item">
                            <i class="fas fa-chart-line"></i>
                            <span>DASHBOARD</span>
                        </a>
                        <a href="teacher_classes.php" class="menu-item active">
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
                    <?php if ($view_mode === 'class_list'): ?>
                        <!-- Class Selection View -->
                        <h2 class="page-title">
                            <i class="fas fa-users"></i> My Classes
                        </h2>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Select a class to view student list and contact information
                        </div>

                        <div class="row">
                            <?php if ($advisory_class): ?>
                                <!-- Advisory Class Card -->
                                <div class="col-lg-6 mb-4">
                                    <a href="?view=advisory&section_id=<?php echo $advisory_class['section_id']; ?>"
                                        style="text-decoration: none;">
                                        <div class="class-card advisory-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <div class="badge bg-warning text-dark mb-2">
                                                        <i class="fas fa-star"></i> ADVISORY CLASS
                                                    </div>
                                                    <h4 class="fw-bold mb-2" style="color: #2c3e50;">
                                                        Grade <?php echo htmlspecialchars($advisory_class['grade_level']); ?> -
                                                        <?php echo htmlspecialchars($advisory_class['section_name']); ?>
                                                    </h4>
                                                    <p class="mb-0" style="color: #666; font-size: 14px;">
                                                        <i class="fas fa-door-open"></i>
                                                        <?php echo htmlspecialchars($advisory_class['room_assignment']); ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-warning text-dark" style="font-size: 18px; padding: 10px 15px;">
                                                        <?php echo $advisory_class['student_count']; ?>
                                                    </span>
                                                    <small class="d-block mt-2" style="color: #666;">Students</small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span style="color: #ff9800; font-weight: 600;">
                                                    View Advisory Class <i class="fas fa-arrow-right"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($classes as $class): ?>
                                <div class="col-lg-6 mb-4">
                                    <a href="?section_id=<?php echo $class['section_id']; ?>&subject_code=<?php echo urlencode($class['subject_code']); ?>"
                                        style="text-decoration: none;">
                                        <div class="class-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h4 class="fw-bold mb-2" style="color: #2c3e50;">
                                                        <?php echo htmlspecialchars($class['subject_name']); ?>
                                                    </h4>
                                                    <p class="mb-1" style="color: #666; font-size: 16px;">
                                                        <i class="fas fa-graduation-cap"></i>
                                                        Grade <?php echo htmlspecialchars($class['grade_level']); ?> -
                                                        <?php echo htmlspecialchars($class['section_name']); ?>
                                                    </p>
                                                    <p class="mb-0" style="color: #666; font-size: 14px;">
                                                        <i class="fas fa-door-open"></i>
                                                        <?php echo htmlspecialchars($class['room_assignment']); ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-success" style="font-size: 18px; padding: 10px 15px;">
                                                        <?php echo $class['student_count']; ?>
                                                    </span>
                                                    <small class="d-block mt-2" style="color: #666;">Students</small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span style="color: #7cb342; font-weight: 600;">
                                                    View Students <i class="fas fa-arrow-right"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($view_mode === 'student_list' || $view_mode === 'advisory_list'): ?>
                        <!-- Student List View -->
                        <div class="mb-4">
                            <a href="teacher_classes.php" class="back-btn">
                                <i class="fas fa-arrow-left"></i> Back to Classes
                            </a>
                        </div>

                        <h2 class="page-title">
                            <?php if ($is_advisory): ?>
                                <i class="fas fa-star"></i> Advisory Class
                            <?php else: ?>
                                <?php echo htmlspecialchars($selected_class['subject_name']); ?>
                            <?php endif; ?>
                        </h2>

                        <div class="alert <?php echo $is_advisory ? 'alert-warning' : 'alert-success'; ?> mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="mb-2">
                                        <i class="fas fa-chalkboard"></i>
                                        Grade <?php echo htmlspecialchars($selected_class['grade_level']); ?> -
                                        <?php echo htmlspecialchars($selected_class['section_name']); ?>
                                    </h5>
                                    <p class="mb-0">
                                        <i class="fas fa-door-open"></i>
                                        <?php echo htmlspecialchars($selected_class['room_assignment']); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <h3 class="mb-0"><?php echo count($students); ?> Students</h3>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($students)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No students enrolled in this class yet.
                            </div>
                        <?php else: ?>
                            <div class="student-table">
                                <!-- Search and Sort Controls -->
                                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap" id="sortControls">
                                    <div class="mb-2 mb-md-0">
                                        <input type="text" id="studentSearch" class="search-box-custom"
                                            placeholder="Search students..." onkeyup="searchStudents()">
                                    </div>
                                    <div>
                                        <span class="me-2" style="color: #666; font-weight: 600;">
                                            <i class="fas fa-sort"></i> Sort:
                                        </span>
                                        <?php
                                        $current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'asc';
                                        if ($is_advisory) {
                                            $base_url = "?view=advisory&section_id=$section_id";
                                        } else {
                                            $base_url = "?section_id=$section_id&subject_code=" . urlencode($subject_code);
                                        }
                                        ?>
                                        <button onclick="sortStudents('asc')"
                                            class="sort-btn <?php echo $current_sort === 'asc' ? 'active' : ''; ?>"
                                            data-sort="asc">
                                            <i class="fas fa-sort-alpha-down"></i> A-Z
                                        </button>
                                        <button onclick="sortStudents('desc')"
                                            class="sort-btn <?php echo $current_sort === 'desc' ? 'active' : ''; ?>"
                                            data-sort="desc">
                                            <i class="fas fa-sort-alpha-up"></i> Z-A
                                        </button>
                                        <?php if (!$is_advisory): ?>
                                            <button onclick="sortStudents('grade_high')"
                                                class="sort-btn <?php echo $current_sort === 'grade_high' ? 'active' : ''; ?>"
                                                data-sort="grade_high">
                                                <i class="fas fa-sort-numeric-down"></i> High-Low
                                            </button>
                                            <button onclick="sortStudents('grade_low')"
                                                class="sort-btn <?php echo $current_sort === 'grade_low' ? 'active' : ''; ?>"
                                                data-sort="grade_low">
                                                <i class="fas fa-sort-numeric-up"></i> Low-High
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Student List -->
                                <div id="studentList">
                                    <?php foreach ($students as $student):
                                        $initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
                                        $fullName = $student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'];

                                        if ($is_advisory) {
                                            $detail_url = "?view=advisory&section_id=$section_id&student_id=" . $student['student_id'];
                                        } else {
                                            $detail_url = "?section_id=$section_id&subject_code=" . urlencode($subject_code) . "&student_id=" . $student['student_id'];
                                        }
                                    ?>
                                        <a href="<?php echo $detail_url; ?>"
                                            style="text-decoration: none; color: inherit;">
                                            <div class="student-row" data-student-name="<?php echo strtolower($fullName); ?>">
                                                <div class="row align-items-center">
                                                    <div class="col-auto">
                                                        <div class="student-avatar-small">
                                                            <?php echo $initials; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                                            <h6 class="mb-0 fw-bold" style="color: #2c3e50;">
                                                                <?php echo htmlspecialchars($fullName); ?>
                                                            </h6>
                                                            <?php if (!$is_advisory && isset($student['average_grade']) && $student['average_grade'] > 0): ?>
                                                                <span class="badge <?php echo $student['average_grade'] >= 75 ? 'bg-success' : 'bg-danger'; ?>">
                                                                    <?php echo number_format($student['average_grade'], 2); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-3">
                                                            <small class="text-muted">
                                                                <i class="fas fa-id-card"></i>
                                                                LRN: <?php echo htmlspecialchars($student['student_code']); ?>
                                                            </small>
                                                            <?php if ($student['contact_number']): ?>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-phone"></i>
                                                                    <?php echo htmlspecialchars($student['contact_number']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-auto">
                                                        <i class="fas fa-chevron-right" style="color: #7cb342;"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($view_mode === 'student_detail' && $selected_student): ?>
                        <!-- Student Detail View -->
                        <div class="mb-4">
                            <?php if ($is_advisory): ?>
                                <a href="?view=advisory&section_id=<?php echo $section_id; ?>" class="back-btn">
                                    <i class="fas fa-arrow-left"></i> Back to Advisory Class
                                </a>
                            <?php else: ?>
                                <a href="?section_id=<?php echo $section_id; ?>&subject_code=<?php echo urlencode($subject_code); ?>" class="back-btn">
                                    <i class="fas fa-arrow-left"></i> Back to Student List
                                </a>
                            <?php endif; ?>
                        </div>

                        <h2 class="page-title">
                            Student Details
                        </h2>

                        <?php
                        $initials = strtoupper(substr($selected_student['first_name'], 0, 1) . substr($selected_student['last_name'], 0, 1));
                        $fullName = $selected_student['first_name'] . ' ' . ($selected_student['middle_name'] ? $selected_student['middle_name'] . ' ' : '') . $selected_student['last_name'];
                        $parentName = ($selected_student['parent_first_name'] && $selected_student['parent_last_name']) ?
                            $selected_student['parent_first_name'] . ' ' . $selected_student['parent_last_name'] : 'Not Available';
                        $age = $selected_student['birthdate'] ? floor((time() - strtotime($selected_student['birthdate'])) / 31556926) : 'N/A';
                        ?>

                        <div class="row">
                            <!-- Student Information Card -->
                            <div class="col-lg-6 mb-4">
                                <div class="info-card">
                                    <div class="text-center mb-4">
                                        <div class="student-avatar-large">
                                            <?php echo $initials; ?>
                                        </div>
                                        <h3 class="fw-bold" style="color: #2c3e50;">
                                            <?php echo htmlspecialchars($fullName); ?>
                                        </h3>
                                        <p class="text-muted mb-0">
                                            <?php if ($is_advisory): ?>
                                                Advisory Class •
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($selected_class['subject_name']); ?> •
                                            <?php endif; ?>
                                            Grade <?php echo htmlspecialchars($selected_class['grade_level']); ?> -
                                            <?php echo htmlspecialchars($selected_class['section_name']); ?>
                                        </p>
                                    </div>

                                    <div class="info-section">
                                        <h5 class="fw-bold mb-3" style="color: #7cb342;">
                                            <i class="fas fa-user"></i> Personal Information
                                        </h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="info-label">Student Code / LRN</div>
                                                <div class="info-value"><?php echo htmlspecialchars($selected_student['student_code']); ?></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="info-label">Gender</div>
                                                <div class="info-value"><?php echo htmlspecialchars($selected_student['gender'] ?? 'N/A'); ?></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="info-label">Date of Birth</div>
                                                <div class="info-value">
                                                    <?php echo $selected_student['birthdate'] ? date('F d, Y', strtotime($selected_student['birthdate'])) : 'N/A'; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="info-label">Age</div>
                                                <div class="info-value"><?php echo $age; ?> years old</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="info-label">Date Enrolled</div>
                                                <div class="info-value">
                                                    <?php echo $selected_student['date_enrolled'] ? date('F d, Y', strtotime($selected_student['date_enrolled'])) : 'N/A'; ?>
                                                </div>
                                            </div>
                                            <?php if ($selected_student['student_address']): ?>
                                                <div class="col-12 mb-3">
                                                    <div class="info-label">Student Address</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($selected_student['student_address']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Quarterly Grades Section (Only for Subject Classes) -->
                                    <?php if (!$is_advisory): ?>
                                        <?php if (isset($quarterly_grades) && !empty($quarterly_grades)): ?>
                                            <div class="info-section">
                                                <h5 class="fw-bold mb-3" style="color: #7cb342;">
                                                    <i class="fas fa-chart-bar"></i> Quarterly Grades
                                                </h5>
                                                <div class="text-center">
                                                    <?php
                                                    $quarter_classes = ['q1', 'q2', 'q3', 'q4'];
                                                    foreach ($quarterly_grades as $grade):
                                                        $quarter_num = intval($grade['quarter']);
                                                        $quarter_class = $quarter_classes[$quarter_num - 1] ?? 'q1';
                                                        $grade_value = floatval($grade['final_grade'] ?? 0);
                                                    ?>
                                                        <div class="quarter-badge <?php echo $quarter_class; ?>">
                                                            <strong>Quarter <?php echo $quarter_num; ?>:</strong>
                                                            <span class="ms-2" style="font-size: 16px;">
                                                                <?php echo number_format($grade_value, 2); ?>
                                                            </span>
                                                            <?php if ($grade_value >= 75): ?>
                                                                <i class="fas fa-check-circle ms-1" style="color: #388e3c;"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-exclamation-circle ms-1" style="color: #d32f2f;"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <?php if (count($quarterly_grades) > 0): ?>
                                                        <div class="mt-3 p-3 bg-light rounded">
                                                            <strong style="color: #2c3e50;">Average: </strong>
                                                            <span style="font-size: 20px; font-weight: bold; color: #7cb342;">
                                                                <?php
                                                                $total = 0;
                                                                $valid_count = 0;
                                                                foreach ($quarterly_grades as $g) {
                                                                    $grade_val = floatval($g['final_grade'] ?? 0);
                                                                    if ($grade_val > 0) {
                                                                        $total += $grade_val;
                                                                        $valid_count++;
                                                                    }
                                                                }
                                                                $overall_avg = $valid_count > 0 ? ($total / $valid_count) : 0;
                                                                echo number_format($overall_avg, 2);
                                                                ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i> No grades recorded yet.
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Parent/Guardian Information Card -->
                            <div class="col-lg-6 mb-4">
                                <div class="info-card">
                                    <div class="info-section">
                                        <h5 class="fw-bold mb-3" style="color: #7cb342;">
                                            <i class="fas fa-user-friends"></i> Parent/Guardian Information
                                        </h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="info-label">Full Name</div>
                                                <div class="info-value"><?php echo htmlspecialchars($parentName); ?></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="info-label">Relationship</div>
                                                <div class="info-value"><?php echo htmlspecialchars($selected_student['relationship'] ?? 'N/A'); ?></div>
                                            </div>
                                            <?php if ($selected_student['parent_code']): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="info-label">Parent Code</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($selected_student['parent_code']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($selected_student['occupation']): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="info-label">Occupation</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($selected_student['occupation']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="info-section">
                                        <h5 class="fw-bold mb-3" style="color: #7cb342;">
                                            <i class="fas fa-address-book"></i> Contact Information
                                        </h5>
                                        <div class="row">
                                            <div class="col-12 mb-3">
                                                <div class="info-label">Contact Number</div>
                                                <div class="info-value">
                                                    <?php if ($selected_student['contact_number']): ?>
                                                        <a href="tel:<?php echo htmlspecialchars($selected_student['contact_number']); ?>"
                                                            style="color: #7cb342; text-decoration: none;">
                                                            <i class="fas fa-phone-alt"></i>
                                                            <?php echo htmlspecialchars($selected_student['contact_number']); ?>
                                                        </a>
                                                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($selected_student['contact_number']); ?>')"
                                                            class="btn btn-sm btn-outline-success ms-2">
                                                            <i class="fas fa-copy"></i> Copy
                                                        </button>
                                                    <?php else: ?>
                                                        Not Available
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-12 mb-3">
                                                <div class="info-label">Email Address</div>
                                                <div class="info-value">
                                                    <?php if ($selected_student['parent_email']): ?>
                                                        <a href="mailto:<?php echo htmlspecialchars($selected_student['parent_email']); ?>"
                                                            style="color: #7cb342; text-decoration: none;">
                                                            <i class="fas fa-envelope"></i>
                                                            <?php echo htmlspecialchars($selected_student['parent_email']); ?>
                                                        </a>
                                                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($selected_student['parent_email']); ?>')"
                                                            class="btn btn-sm btn-outline-success ms-2">
                                                            <i class="fas fa-copy"></i> Copy
                                                        </button>
                                                    <?php else: ?>
                                                        Not Available
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($selected_student['parent_address']): ?>
                                                <div class="col-12 mb-3">
                                                    <div class="info-label">Home Address</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($selected_student['parent_address']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store student data for client-side sorting
        const studentData = [];
        const isAdvisory = <?php echo $view_mode === 'advisory_list' || $view_mode === 'student_list' ? ($is_advisory ? 'true' : 'false') : 'false'; ?>;

        <?php if ($view_mode === 'student_list' || $view_mode === 'advisory_list'): ?>
            // Initialize student data
            <?php foreach ($students as $student):
                $initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
                $fullName = $student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'];
                if ($is_advisory) {
                    $detail_url = "?view=advisory&section_id=$section_id&student_id=" . $student['student_id'];
                } else {
                    $detail_url = "?section_id=$section_id&subject_code=" . urlencode($subject_code) . "&student_id=" . $student['student_id'];
                }
            ?>
                studentData.push({
                    id: <?php echo $student['student_id']; ?>,
                    initials: '<?php echo $initials; ?>',
                    fullName: '<?php echo addslashes(htmlspecialchars($fullName)); ?>',
                    firstName: '<?php echo addslashes(htmlspecialchars($student['first_name'])); ?>',
                    lastName: '<?php echo addslashes(htmlspecialchars($student['last_name'])); ?>',
                    middleName: '<?php echo addslashes(htmlspecialchars($student['middle_name'] ?? '')); ?>',
                    studentCode: '<?php echo htmlspecialchars($student['student_code']); ?>',
                    contactNumber: '<?php echo htmlspecialchars($student['contact_number'] ?? ''); ?>',
                    averageGrade: <?php echo isset($student['average_grade']) ? floatval($student['average_grade']) : 0; ?>,
                    detailUrl: '<?php echo $detail_url; ?>'
                });
            <?php endforeach; ?>
        <?php endif; ?>

        // Sort students function
        function sortStudents(sortType) {
            // Prevent default button behavior
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Store scroll position FIRST
            const scrollPos = window.pageYOffset || document.documentElement.scrollTop;

            // Update active button
            document.querySelectorAll('.sort-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.sort-btn').classList.add('active');

            // Sort the data
            let sortedData = [...studentData];

            switch (sortType) {
                case 'asc':
                    sortedData.sort((a, b) => {
                        if (a.lastName.toLowerCase() === b.lastName.toLowerCase()) {
                            return a.firstName.toLowerCase().localeCompare(b.firstName.toLowerCase());
                        }
                        return a.lastName.toLowerCase().localeCompare(b.lastName.toLowerCase());
                    });
                    break;
                case 'desc':
                    sortedData.sort((a, b) => {
                        if (a.lastName.toLowerCase() === b.lastName.toLowerCase()) {
                            return b.firstName.toLowerCase().localeCompare(a.firstName.toLowerCase());
                        }
                        return b.lastName.toLowerCase().localeCompare(a.lastName.toLowerCase());
                    });
                    break;
                case 'grade_high':
                    sortedData.sort((a, b) => {
                        if (b.averageGrade === a.averageGrade) {
                            return a.lastName.toLowerCase().localeCompare(b.lastName.toLowerCase());
                        }
                        return b.averageGrade - a.averageGrade;
                    });
                    break;
                case 'grade_low':
                    sortedData.sort((a, b) => {
                        if (a.averageGrade === b.averageGrade) {
                            return a.lastName.toLowerCase().localeCompare(b.lastName.toLowerCase());
                        }
                        return a.averageGrade - b.averageGrade;
                    });
                    break;
            }

            // Rebuild the student list
            renderStudentList(sortedData);

            // Restore scroll position
            setTimeout(() => {
                window.scrollTo(0, scrollPos);
            }, 10);
        }

        // Render student list
        function renderStudentList(data) {
            const studentList = document.getElementById('studentList');
            studentList.innerHTML = '';

            data.forEach(student => {
                const studentRow = document.createElement('a');
                studentRow.href = student.detailUrl;
                studentRow.style.textDecoration = 'none';
                studentRow.style.color = 'inherit';

                let gradeHtml = '';
                if (!isAdvisory && student.averageGrade > 0) {
                    const badgeClass = student.averageGrade >= 75 ? 'bg-success' : 'bg-danger';
                    gradeHtml = `<span class="badge ${badgeClass}">${student.averageGrade.toFixed(2)}</span>`;
                }

                let contactHtml = '';
                if (student.contactNumber) {
                    contactHtml = `
                        <small class="text-muted">
                            <i class="fas fa-phone"></i> ${student.contactNumber}
                        </small>
                    `;
                }

                studentRow.innerHTML = `
                    <div class="student-row" data-student-name="${student.fullName.toLowerCase()}">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="student-avatar-small">
                                    ${student.initials}
                                </div>
                            </div>
                            <div class="col">
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                    <h6 class="mb-0 fw-bold" style="color: #2c3e50;">
                                        ${student.fullName}
                                    </h6>
                                    ${gradeHtml}
                                </div>
                                <div class="d-flex flex-wrap gap-3">
                                    <small class="text-muted">
                                        <i class="fas fa-id-card"></i> 
                                        LRN: ${student.studentCode}
                                    </small>
                                    ${contactHtml}
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chevron-right" style="color: #7cb342;"></i>
                            </div>
                        </div>
                    </div>
                `;

                studentList.appendChild(studentRow);
            });
        }

        // Search functionality
        function searchStudents() {
            const input = document.getElementById('studentSearch');
            const filter = input.value.toLowerCase();
            const studentList = document.getElementById('studentList');
            const students = studentList.getElementsByClassName('student-row');

            for (let i = 0; i < students.length; i++) {
                const studentName = students[i].getAttribute('data-student-name');
                if (studentName.includes(filter)) {
                    students[i].parentElement.style.display = '';
                } else {
                    students[i].parentElement.style.display = 'none';
                }
            }
        }

        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copied to clipboard: ' + text);
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.class-card, .student-row, .info-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>

</html>