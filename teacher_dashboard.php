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
$teacher_code = $_SESSION['username']; // Teacher code from login

// Fetch teacher details from database
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

// Initialize default values
$totalStudents = 0;
$pendingGrades = 0;
$todayAttendance = 0;
$averageGrade = 0;

// Fetch teacher statistics
try {
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

    // Total students in all sections taught by this teacher
    $stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.student_id) as total 
    FROM students s
    INNER JOIN sections sec ON s.section_id = sec.section_id
    INNER JOIN section_schedules ss ON sec.section_id = ss.section_id
    WHERE ss.teacher_code = ? AND ss.is_active = 1 AND s.status = 'active'
");
    $stmt->execute([$teacher_code]);
    $result = $stmt->fetch();
    $totalStudents = $result ? (int)$result['total'] : 0;

    $stmt = $pdo->prepare("
    SELECT ss.setting_value FROM system_settings ss WHERE ss.setting_key = 'current_quarter'
");
    $stmt->execute();
    $currentQuarter = $stmt->fetchColumn() ?: '1st';

    $stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.student_code) as total
    FROM students s
    INNER JOIN sections sec ON s.section_id = sec.section_id
    INNER JOIN section_schedules ssch ON sec.section_id = ssch.section_id
    INNER JOIN grade_schedule_template gst ON ssch.template_id = gst.template_id
    LEFT JOIN grades g ON s.student_code = g.student_code 
        AND g.subject_code = gst.subject_code 
        AND g.teacher_code = ?
        AND g.quarter = ?
    WHERE ssch.teacher_code = ? 
    AND ssch.is_active = 1 
    AND s.status = 'active'
    AND g.grade_id IS NULL
");
    $stmt->execute([$teacher_code, $currentQuarter, $teacher_code]);
    $result = $stmt->fetch();
    $pendingGrades = $result ? (int)$result['total'] : 0;

    // Today's attendance count (using student_code instead of student_id)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM attendance a
        INNER JOIN students s ON a.student_code = s.student_code
        WHERE a.teacher_code = ? 
        AND DATE(a.date) = CURDATE() 
        AND a.status = 'present'
    ");
    $stmt->execute([$teacher_code]);
    $result = $stmt->fetch();
    $todayAttendance = $result ? (int)$result['total'] : 0;

    // Average grade for this teacher's subjects
    $stmt = $pdo->prepare("
        SELECT AVG(g.final_grade) as avg 
        FROM grades g
        WHERE g.teacher_code = ? AND g.final_grade > 0
    ");
    $stmt->execute([$teacher_code]);
    $result = $stmt->fetch();
    $averageGrade = $result && $result['avg'] ? round((float)$result['avg'], 1) : 0;

    // Get today's schedule with proper joins
    $currentDay = date('l'); // Monday, Tuesday, etc.
    $stmt = $pdo->prepare("
    SELECT 
        mts.start_time,
        mts.end_time,
        CASE 
            WHEN gst.subject_code IN ('MAPEH', 'COMP') THEN gst.room_type
            ELSE sec.room_assignment
        END as room,
        sub.subject_name,
        sec.grade_level,
        sec.section_name as section
    FROM section_schedules ssch
    INNER JOIN grade_schedule_template gst ON ssch.template_id = gst.template_id
    INNER JOIN sections sec ON ssch.section_id = sec.section_id
    INNER JOIN subjects sub ON gst.subject_code = sub.subject_code
    INNER JOIN master_time_slots mts ON gst.slot_id = mts.slot_id
    WHERE ssch.teacher_code = ? 
    AND gst.day_of_week = ?
    AND ssch.is_active = 1
    AND mts.slot_type = 'CLASS'
    ORDER BY mts.start_time
");
    $stmt->execute([$teacher_code, $currentDay]);
    $todaySchedule = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Teacher dashboard query error: " . $e->getMessage());
}

$teacherName = htmlspecialchars($first_name . ' ' . $last_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Creative Dreams</title>
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
            color: #2c3e50;
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
            transition: all 0.3s;
            height: 100%;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: white;
            border: none;
            padding: 20px;
            font-weight: bold;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-header i {
            font-size: 20px;
            color: var(--sage-green);
        }

        .info-card {
            background: white;
            padding: 30px 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .info-card .icon {
            font-size: 45px;
            margin-bottom: 15px;
            color: var(--accent-green);
        }

        .info-card h3 {
            font-size: 13px;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .info-card .value {
            font-size: 36px;
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .info-card .label {
            font-size: 12px;
            color: var(--sage-green);
            font-weight: 600;
        }

        .action-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
            text-align: center;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
            border-color: var(--sage-green);
        }

        .action-card .action-icon {
            width: 70px;
            height: 70px;
            background: var(--pale-green);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-card .action-icon i {
            font-size: 32px;
            color: var(--accent-green);
        }

        .action-card h4 {
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .action-card p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .search-box {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            color: white;
            width: 300px;
        }

        .search-box::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .search-box:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.3);
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }

            .search-box {
                width: 150px;
                font-size: 14px;
            }

            .brand-text h1 {
                font-size: 20px;
            }

            .brand-text p {
                font-size: 12px;
            }

            .page-title {
                font-size: 22px;
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

                    <!-- Faculty ID Section -->
                    <div class="faculty-id-section">
                        <h6>Faculty ID</h6>
                        <div class="id-number"><?php echo htmlspecialchars($teacher_code); ?></div>
                        <div class="subject">
                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject_list); ?>
                        </div>
                    </div>

                    <nav>
                        <a href="teacher_dashboard.php" class="menu-item active">
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
                        <i class="fas fa-tachometer-alt"></i> Teacher Dashboard
                    </h2>

                    <!-- Main Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3>My Students</h3>
                                <div class="value"><?php echo number_format($totalStudents); ?></div>
                                <div class="label">Total Students</div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <h3>Pending Grades</h3>
                                <div class="value"><?php echo number_format($pendingGrades); ?></div>
                                <div class="label">To Upload</div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <h3>Today's Attendance</h3>
                                <div class="value"><?php echo number_format($todayAttendance); ?></div>
                                <div class="label">Present Today</div>
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h3>Class Average</h3>
                                <div class="value"><?php echo number_format($averageGrade, 1); ?>%</div>
                                <div class="label">Overall Performance</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h4 class="mb-3" style="color: #2c3e50; font-weight: bold;">
                                <i class="fas fa-bolt"></i> Quick Actions
                            </h4>
                        </div>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <a href="grade_upload.php" style="text-decoration: none;">
                                <div class="action-card">
                                    <div class="action-icon">
                                        <i class="fas fa-file-upload"></i>
                                    </div>
                                    <h4>Upload Grades</h4>
                                    <p>Submit student grades and assessments</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-4 col-md-6 mb-4">
                            <a href="attendance.php" style="text-decoration: none;">
                                <div class="action-card">
                                    <div class="action-icon">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <h4>Mark Attendance</h4>
                                    <p>Record student attendance for today</p>
                                </div>
                            </a>
                        </div>

                        <div class="col-lg-4 col-md-6 mb-4">
                            <a href="faculty_profile.php" style="text-decoration: none;">
                                <div class="action-card">
                                    <div class="action-icon">
                                        <i class="fas fa-id-card"></i>
                                    </div>
                                    <h4>View Faculty ID</h4>
                                    <p>Access your faculty information</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="row">
                        <!-- Recent Activity -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-history"></i>
                                    RECENT ACTIVITY
                                </div>
                                <div class="card-body">
                                    <div class="mb-3 pb-3" style="border-bottom: 1px solid #e0e0e0;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div style="width: 40px; height: 40px; background: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-check" style="color: #7cb342;"></i>
                                            </div>
                                            <div>
                                                <strong>Attendance Recorded</strong>
                                                <p class="mb-0 text-muted" style="font-size: 12px;">Today at <?php echo date('g:i A'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3 pb-3" style="border-bottom: 1px solid #e0e0e0;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div style="width: 40px; height: 40px; background: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-upload" style="color: #7cb342;"></i>
                                            </div>
                                            <div>
                                                <strong>Grades Updated</strong>
                                                <p class="mb-0 text-muted" style="font-size: 12px;">Yesterday</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-3">
                                            <div style="width: 40px; height: 40px; background: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-bell" style="color: #7cb342;"></i>
                                            </div>
                                            <div>
                                                <strong>New Announcement</strong>
                                                <p class="mb-0 text-muted" style="font-size: 12px;">2 days ago</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Class Schedule -->
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-calendar-alt"></i>
                                    TODAY'S SCHEDULE (<?php echo date('l, F j, Y'); ?>)
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($todaySchedule)): ?>
                                        <?php
                                        $currentTime = date('H:i:s');
                                        $scheduleCount = count($todaySchedule);
                                        foreach ($todaySchedule as $index => $schedule):
                                            $isLast = ($index === $scheduleCount - 1);

                                            // Determine status
                                            $status = 'Upcoming';
                                            $badgeClass = 'bg-warning text-dark';

                                            if ($currentTime > $schedule['end_time']) {
                                                $status = 'Completed';
                                                $badgeClass = 'bg-success';
                                            } elseif ($currentTime >= $schedule['start_time'] && $currentTime <= $schedule['end_time']) {
                                                $status = 'In Progress';
                                                $badgeClass = 'bg-primary';
                                            }
                                        ?>
                                            <div class="mb-3 pb-3" <?php if (!$isLast) echo 'style="border-bottom: 1px solid #e0e0e0;"'; ?>>
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div style="flex: 1;">
                                                        <strong style="color: #7cb342;">
                                                            <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> -
                                                            <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                                        </strong>
                                                        <p class="mb-1" style="font-size: 15px; font-weight: 600; color: #2c3e50;">
                                                            <?php echo htmlspecialchars($schedule['subject_name']); ?>
                                                        </p>
                                                        <p class="mb-0 text-muted" style="font-size: 13px;">
                                                            <i class="fas fa-chalkboard"></i> Grade <?php echo htmlspecialchars($schedule['grade_level']); ?> -
                                                            <?php echo htmlspecialchars($schedule['section']); ?> |
                                                            <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($schedule['room']); ?>
                                                        </p>
                                                    </div>
                                                    <span class="badge <?php echo $badgeClass; ?>" style="white-space: nowrap;">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                            <p>No classes scheduled for today.</p>
                                        </div>
                                    <?php endif; ?>
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
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

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

        document.querySelectorAll('.card, .info-card, .action-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });

        // Search functionality
        const searchBox = document.getElementById('searchBox');
        if (searchBox) {
            searchBox.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                if (searchTerm.length > 0) {
                    console.log('Searching for:', searchTerm);
                    // Add your search logic here
                }
            });
        }
    </script>
</body>

</html>