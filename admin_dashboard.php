<?php
session_start();


// Check if admin is logged in
// Only admin should access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
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


// Initialize default values
$totalStudents = 0;
$activeTeachers = 0;
$pendingEnrollments = 0;
$totalCollections = 0;
$highAchievers = 0;
$averagePerformers = 0;
$needSupport = 0;


// Initialize arrays for student lists
$highAchieverStudents = [];
$averagePerformerStudents = [];
$needSupportStudents = [];


// Get grade level filter from URL or default to 'all'
$gradeLevelFilter = isset($_GET['grade_level']) && $_GET['grade_level'] !== 'all' ? $_GET['grade_level'] : null;


// Fetch dashboard statistics with error handling
try {
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $result = $stmt->fetch();
    $totalStudents = $result ? (int)$result['total'] : 0;


    // Active teachers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM teachers WHERE status = 'active'");
    $result = $stmt->fetch();
    $activeTeachers = $result ? (int)$result['total'] : 0;


    // Pending enrollments from enrollment table
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM enrollment WHERE status = 'pending'");
    $result = $stmt->fetch();
    $pendingEnrollments = $result ? (int)$result['total'] : 0;


    // Total collections this semester
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid' AND YEAR(payment_date) = YEAR(CURDATE())");
    $result = $stmt->fetch();
    $totalCollections = $result ? (float)$result['total'] : 0;


    // Fetch all available grade levels for the filter
    $gradeLevelsQuery = $pdo->query("SELECT DISTINCT grade_level FROM sections WHERE grade_level IS NOT NULL ORDER BY CAST(grade_level AS UNSIGNED)");
    $gradeLevels = $gradeLevelsQuery->fetchAll();


    // Build query for student performance with optional grade level filter
    $performanceQuery = "
        SELECT
            g.student_code,
            AVG(g.final_grade) as avg_grade,
            s.first_name,
            s.last_name,
            s.middle_name,
            s.gender,
            sec.section_name,
            sec.grade_level
        FROM grades g
        JOIN students s ON g.student_code = s.student_code
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE g.final_grade IS NOT NULL
    ";
   
    if ($gradeLevelFilter) {
        $performanceQuery .= " AND sec.grade_level = :grade_level";
    }
   
    $performanceQuery .= "
        GROUP BY g.student_code, s.first_name, s.last_name, s.middle_name, s.gender, sec.section_name, sec.grade_level
        ORDER BY avg_grade DESC
    ";
   
    $stmt = $pdo->prepare($performanceQuery);
    if ($gradeLevelFilter) {
        $stmt->bindParam(':grade_level', $gradeLevelFilter);
    }
    $stmt->execute();
    $studentPerformance = $stmt->fetchAll();
   
    // Reset counters and clear arrays
    $highAchievers = 0;
    $averagePerformers = 0;
    $needSupport = 0;
    $highAchieverStudents = [];
    $averagePerformerStudents = [];
    $needSupportStudents = [];
   
    foreach ($studentPerformance as $student) {
        $avgGrade = (float)$student['avg_grade'];
       
        $studentInfo = [
            'student_code' => $student['student_code'],
            'name' => $student['first_name'] . ' ' . $student['last_name'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'middle_name' => $student['middle_name'],
            'gender' => $student['gender'],
            'section' => $student['section_name'],
            'grade_level' => $student['grade_level'],
            'avg_grade' => number_format($avgGrade, 2)
        ];
       
        if ($avgGrade >= 90) {
            $highAchievers++;
            $highAchieverStudents[] = $studentInfo;
        } elseif ($avgGrade >= 75 && $avgGrade < 90) {
            $averagePerformers++;
            $averagePerformerStudents[] = $studentInfo;
        } else {
            $needSupport++;
            $needSupportStudents[] = $studentInfo;
        }
    }


} catch (PDOException $e) {
    // Silently handle errors and use default values
    error_log("Dashboard query error: " . $e->getMessage());
}


$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Creative Dreams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            background: linear-gradient(135deg, #5a9c4e 0%, #4a8240 100%);
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


        .admin-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }


        .admin-avatar i {
            font-size: 40px;
            color: white;
        }


        .welcome-section h5 {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }


        .welcome-section p {
            color: #52a347;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 14px;
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
            background: #e0f7fa;
            color: #52a347;
            transform: translateX(5px);
        }


        .menu-item.active {
            background: linear-gradient(135deg, #52a347, #3d6e35);
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
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
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
            height: 100%;
        }


        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #52a347, #3d6e35);
        }


        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }


        .info-card .icon {
            font-size: 45px;
            margin-bottom: 15px;
            color: #68b85d;
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
            color: #2d5a24;
            margin-bottom: 5px;
        }


        .info-card .label {
            font-size: 12px;
            color: #52a347;
            font-weight: 600;
        }


        .info-card.with-action {
            cursor: pointer;
        }


        .info-card.with-action:hover .value {
            color: #52a347;
        }


        .performance-badge {
            padding: 15px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 15px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            cursor: pointer;
        }


        .performance-badge:hover {
            transform: translateX(5px);
        }


        .high-achievers {
            background: #4caf50;
            color: white;
        }


        .average-performers {
            background: #ffc107;
            color: #000;
        }


        .need-support {
            background: #f44336;
            color: white;
        }


        .compact-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }


        .compact-section h6 {
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }


        .compact-section h6 i {
            color: #52a347;
        }


        .modal-header .badge {
            font-size: 14px;
            padding: 5px 10px;
        }


        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }


        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }


        .student-count {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }


        /* Grade Level Filter Styles */
        .grade-filter-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }


        .filter-label {
            font-weight: 600;
            color: #2d5a24;
            margin-bottom: 10px;
            font-size: 14px;
        }


        .grade-filter-btn {
            padding: 8px 15px;
            border-radius: 20px;
            border: 2px solid #dee2e6;
            background: white;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s;
            margin-right: 8px;
            margin-bottom: 8px;
            text-decoration: none;
            display: inline-block;
        }


        .grade-filter-btn:hover {
            border-color: #52a347;
            color: #52a347;
            transform: translateY(-2px);
        }


        .grade-filter-btn.active {
            background: #52a347;
            border-color: #52a347;
            color: white;
        }


        .grade-filter-btn i {
            margin-right: 5px;
        }


        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
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


            .info-card {
                padding: 20px 15px;
            }


            .info-card .value {
                font-size: 28px;
            }
           
            .grade-filter-btn {
                padding: 6px 12px;
                font-size: 13px;
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
            <div class="header-actions">
                <button class="icon-btn" title="Profile">
                    <i class="fas fa-user"></i>
                </button>
            </div>
        </div>
    </div>


    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 mb-4">
                <div class="sidebar">
                    <div class="welcome-section">
                        <div class="admin-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h5>WELCOME <?php echo strtoupper($adminName); ?>!</h5>
                        <p><i class="fas fa-check-circle"></i> Logged in</p>
                    </div>
                    <nav>
                        <a href="admin_dashboard.php" class="menu-item active">
                            <i class="fas fa-chart-line"></i>
                            <span>DASHBOARD</span>
                        </a>
                        <a href="enrollment_management.php" class="menu-item">
                            <i class="fas fa-user-graduate"></i>
                            <span>ENROLLMENT</span>
                        </a>
                        <a href="request.php" class="menu-item">
                            <i class="fas fa-user-graduate"></i>
                            <span>REQUESTS & APPOINTMENTS</span>
                        </a>
                        <a href="fees_payment.php" class="menu-item">
                            <i class="fas fa-credit-card"></i>
                            <span>FEES & PAYMENT</span>
                        </a>
                        <a href="manage_accounts.php" class="menu-item">
                            <i class="fas fa-users-cog"></i>
                            <span>MANAGE ACCOUNTS</span>
                        </a>
                        <a href="manage_sections.php" class="menu-item">
                            <i class="fas fa-door-open"></i>
                            <span>MANAGE SECTIONS</span>
                        </a>
                        <a href="announcement.php" class="menu-item">
                            <i class="fas fa-bullhorn"></i>
                            <span>ANNOUNCEMENT</span>
                        </a>
                        <a href="settings.php" class="menu-item">
                            <i class="fas fa-cog"></i>
                            <span>SETTINGS</span>
                        </a>
                    </nav>
                    <form action="logout.php" method="POST" style="margin-top: auto;">
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
                        <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                    </h2>


                    <!-- Main Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card with-action" onclick="window.location.href='enrollment_management.php'">
                                <div class="icon">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <h3>Total Students</h3>
                                <div class="value"><?php echo number_format($totalStudents); ?></div>
                                <div class="label">Enrolled Students</div>
                            </div>
                        </div>


                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card with-action" onclick="window.location.href='manage_accounts.php'">
                                <div class="icon">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <h3>Active Teachers</h3>
                                <div class="value"><?php echo number_format($activeTeachers); ?></div>
                                <div class="label">Teaching Staff</div>
                            </div>
                        </div>


                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card with-action" onclick="window.location.href='enrollment_management.php'">
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3>Pending Enrollments</h3>
                                <div class="value"><?php echo number_format($pendingEnrollments); ?></div>
                                <div class="label">Awaiting Approval</div>
                            </div>
                        </div>


                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="info-card with-action" onclick="window.location.href='fees_payment.php'">
                                <div class="icon">
                                    <i class="fas fa-peso-sign"></i>
                                </div>
                                <h3>Total Collections</h3>
                                <div class="value">₱<?php echo number_format($totalCollections, 2); ?></div>
                                <div class="label">This Semester</div>
                            </div>
                        </div>
                    </div>


                    <!-- Performance Prediction Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="compact-section">
                                <h6><i class="fas fa-chart-bar"></i> STUDENT PERFORMANCE OVERVIEW</h6>
                               
                                <!-- Grade Level Filter -->
                                <div class="grade-filter-container">
                                    <div class="filter-label">
                                        <i class="fas fa-filter"></i> Filter by Grade Level:
                                    </div>
                                    <div class="filter-options">
                                        <a href="?grade_level=all" class="grade-filter-btn <?php echo !$gradeLevelFilter ? 'active' : ''; ?>">
                                            <i class="fas fa-globe"></i> All Grades
                                        </a>
                                        <?php foreach ($gradeLevels as $grade):
                                            $gradeLevel = $grade['grade_level'];
                                            if (!empty($gradeLevel)): ?>
                                                <a href="?grade_level=<?php echo urlencode($gradeLevel); ?>"
                                                   class="grade-filter-btn <?php echo $gradeLevelFilter == $gradeLevel ? 'active' : ''; ?>">
                                                    <i class="fas fa-graduation-cap"></i> Grade <?php echo htmlspecialchars($gradeLevel); ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                   
                                    <?php if ($gradeLevelFilter): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-info">
                                                <i class="fas fa-filter"></i> Filtered: Grade <?php echo htmlspecialchars($gradeLevelFilter); ?>
                                            </span>
                                            <a href="?grade_level=all" class="ms-2 btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-times"></i> Clear Filter
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                               
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="performance-badge high-achievers" data-bs-toggle="modal" data-bs-target="#highAchieversModal">
                                            <span>HIGH ACHIEVERS</span>
                                            <strong><?php echo $highAchievers; ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="performance-badge average-performers" data-bs-toggle="modal" data-bs-target="#averagePerformersModal">
                                            <span>AVERAGE PERFORMERS</span>
                                            <strong><?php echo $averagePerformers; ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="performance-badge need-support" data-bs-toggle="modal" data-bs-target="#needSupportModal">
                                            <span>NEED SUPPORT</span>
                                            <strong><?php echo $needSupport; ?></strong>
                                        </div>
                                    </div>
                                </div>
                               
                                <!-- Additional performance metrics -->
                                <div class="row mt-4">
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3">
                                            <div class="value" style="font-size: 24px;">
                                                <?php
                                                $totalWithGrades = $highAchievers + $averagePerformers + $needSupport;
                                                echo $totalWithGrades > 0 ? round(($highAchievers/$totalWithGrades)*100) : 0;
                                                ?>%
                                            </div>
                                            <div class="label" style="font-size: 11px;">High Achievers</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3">
                                            <div class="value" style="font-size: 24px;">
                                                <?php
                                                $totalWithGrades = $highAchievers + $averagePerformers + $needSupport;
                                                echo $totalWithGrades > 0 ? round(($averagePerformers/$totalWithGrades)*100) : 0;
                                                ?>%
                                            </div>
                                            <div class="label" style="font-size: 11px;">Average</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3">
                                            <div class="value" style="font-size: 24px;">
                                                <?php
                                                $totalWithGrades = $highAchievers + $averagePerformers + $needSupport;
                                                echo $totalWithGrades > 0 ? round(($needSupport/$totalWithGrades)*100) : 0;
                                                ?>%
                                            </div>
                                            <div class="label" style="font-size: 11px;">Need Support</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="text-center p-3">
                                            <div class="value" style="font-size: 24px;"><?php echo $totalWithGrades; ?></div>
                                            <div class="label" style="font-size: 11px;">Total Assessed</div>
                                        </div>
                                    </div>
                                </div>
                               
                                <!-- Removed the note about "students who have recorded grades" -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- High Achievers Modal -->
    <div class="modal fade" id="highAchieversModal" tabindex="-1" aria-labelledby="highAchieversModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="highAchieversModalLabel">
                        <i class="fas fa-trophy me-2"></i> High Achievers
                        <span class="badge bg-light text-dark ms-2"><?php echo count($highAchieverStudents); ?> Students</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($highAchieverStudents)): ?>
                        <div class="no-data">
                            <i class="fas fa-trophy"></i>
                            <h5>No High Achievers Found</h5>
                            <p>No students have achieved an average grade of 90% or higher.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Code</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Average Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($highAchieverStudents as $index => $student): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                                            <td>
                                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                <?php if (!empty($student['gender'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['gender']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo !empty($student['grade_level']) ? htmlspecialchars($student['grade_level']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($student['section']) ? htmlspecialchars($student['section']) : 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $student['avg_grade']; ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Average Performers Modal -->
    <div class="modal fade" id="averagePerformersModal" tabindex="-1" aria-labelledby="averagePerformersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="averagePerformersModalLabel">
                        <i class="fas fa-chart-line me-2"></i> Average Performers
                        <span class="badge bg-dark text-white ms-2"><?php echo count($averagePerformerStudents); ?> Students</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($averagePerformerStudents)): ?>
                        <div class="no-data">
                            <i class="fas fa-chart-line"></i>
                            <h5>No Average Performers Found</h5>
                            <p>No students have average grades between 75% and 89.99%.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Code</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Average Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($averagePerformerStudents as $index => $student): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                                            <td>
                                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                <?php if (!empty($student['gender'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['gender']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo !empty($student['grade_level']) ? htmlspecialchars($student['grade_level']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($student['section']) ? htmlspecialchars($student['section']) : 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark"><?php echo $student['avg_grade']; ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Need Support Modal -->
    <div class="modal fade" id="needSupportModal" tabindex="-1" aria-labelledby="needSupportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="needSupportModalLabel">
                        <i class="fas fa-hands-helping me-2"></i> Students Needing Support
                        <span class="badge bg-light text-dark ms-2"><?php echo count($needSupportStudents); ?> Students</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($needSupportStudents)): ?>
                        <div class="no-data">
                            <i class="fas fa-hands-helping"></i>
                            <h5>No Students Needing Support</h5>
                            <p>No students have average grades below 75%.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student Code</th>
                                        <th>Name</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Average Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($needSupportStudents as $index => $student): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                                            <td>
                                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                <?php if (!empty($student['gender'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['gender']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo !empty($student['grade_level']) ? htmlspecialchars($student['grade_level']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($student['section']) ? htmlspecialchars($student['section']) : 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $student['avg_grade']; ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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


        document.querySelectorAll('.info-card, .compact-section, .performance-badge').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });


        // Auto-refresh statistics every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);


        // Add click handlers for info cards
        document.querySelectorAll('.info-card.with-action').forEach(card => {
            card.addEventListener('click', function() {
                // Navigation is already handled by onclick attribute
                console.log('Navigating to section...');
            });
        });


        // Performance badges click effect
        document.querySelectorAll('.performance-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });


        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
       
        // Function to apply grade level filter
        function applyGradeFilter(gradeLevel) {
            if (gradeLevel === 'all') {
                window.location.href = 'admin_dashboard.php';
            } else {
                window.location.href = 'admin_dashboard.php?grade_level=' + gradeLevel;
            }
        }
    </script>
</body>


</html>

