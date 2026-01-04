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

// Get current quarter
try {
    $stmt = $pdo->prepare("SELECT ss.setting_value FROM system_settings ss WHERE ss.setting_key = 'current_quarter'");
    $stmt->execute();
    $currentQuarter = $stmt->fetchColumn() ?: '1st';
} catch (PDOException $e) {
    $currentQuarter = '1st';
}

// Fetch analytics data for all sections and overall
$sectionsData = [];
$overallAnalytics = [
    'total_students' => 0,
    'total_sections' => 0,
    'overall_avg_grade' => 0,
    'gender_distribution' => ['Male' => 0, 'Female' => 0],
    'performance_distribution' => [
        'excellent' => 0,
        'good' => 0,
        'average' => 0,
        'needs_improvement' => 0
    ],
    'attendance_stats' => [
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'excused' => 0
    ],
    'subject_performance' => []
];

try {
    // Get all sections taught by this teacher
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            sec.section_id,
            sec.grade_level,
            sec.section_name,
            sec.room_assignment,
            COUNT(DISTINCT s.student_id) as student_count
        FROM sections sec
        INNER JOIN section_schedules ss ON sec.section_id = ss.section_id
        INNER JOIN students s ON sec.section_id = s.section_id
        WHERE ss.teacher_code = ? 
        AND ss.is_active = 1
        AND sec.is_active = 1
        AND s.status = 'active'
        GROUP BY sec.section_id, sec.grade_level, sec.section_name, sec.room_assignment
        ORDER BY sec.grade_level, sec.section_name
    ");
    $stmt->execute([$teacher_code]);
    $sections = $stmt->fetchAll();

    $overallAnalytics['total_sections'] = count($sections);

    // For each section, get detailed analytics
    foreach ($sections as $section) {
        $sectionId = $section['section_id'];
        
        // Get students in this section
        $stmt = $pdo->prepare("
            SELECT 
                s.student_id,
                s.student_code,
                s.first_name,
                s.last_name,
                s.gender,
                s.profile_picture
            FROM students s
            WHERE s.section_id = ? 
            AND s.status = 'active'
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$sectionId]);
        $students = $stmt->fetchAll();

        $overallAnalytics['total_students'] += count($students);

        // Section statistics
        $sectionStats = [
            'total_students' => count($students),
            'male_count' => 0,
            'female_count' => 0,
            'avg_grade' => 0,
            'performance_distribution' => [
                'excellent' => 0,
                'good' => 0,
                'average' => 0,
                'needs_improvement' => 0
            ],
            'top_students' => [],
            'at_risk_students' => [],
            'subject_performance' => [],
            'attendance_stats' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0
            ]
        ];

        // Calculate gender distribution and collect grades
        $allGrades = [];
        foreach ($students as $student) {
            // Count gender for overall analytics
            if ($student['gender'] === 'Male') {
                $sectionStats['male_count']++;
                $overallAnalytics['gender_distribution']['Male']++;
            } else {
                $sectionStats['female_count']++;
                $overallAnalytics['gender_distribution']['Female']++;
            }

            // Get student grades
            $stmt = $pdo->prepare("
                SELECT 
                    g.subject_code,
                    sub.subject_name,
                    g.final_grade
                FROM grades g
                INNER JOIN subjects sub ON g.subject_code = sub.subject_code
                WHERE g.student_code = ? 
                AND g.teacher_code = ?
                AND g.quarter = ?
            ");
            $stmt->execute([$student['student_code'], $teacher_code, $currentQuarter]);
            $studentGrades = $stmt->fetchAll();

            $studentAvg = 0;
            $gradeCount = 0;
            foreach ($studentGrades as $grade) {
                if ($grade['final_grade'] > 0) {
                    $studentAvg += $grade['final_grade'];
                    $gradeCount++;
                    $allGrades[] = $grade['final_grade'];
                }
            }

            if ($gradeCount > 0) {
                $studentAvg = $studentAvg / $gradeCount;
                
                // Classify student performance
                if ($studentAvg >= 90) {
                    $sectionStats['performance_distribution']['excellent']++;
                    $overallAnalytics['performance_distribution']['excellent']++;
                } elseif ($studentAvg >= 80) {
                    $sectionStats['performance_distribution']['good']++;
                    $overallAnalytics['performance_distribution']['good']++;
                } elseif ($studentAvg >= 75) {
                    $sectionStats['performance_distribution']['average']++;
                    $overallAnalytics['performance_distribution']['average']++;
                } else {
                    $sectionStats['performance_distribution']['needs_improvement']++;
                    $overallAnalytics['performance_distribution']['needs_improvement']++;
                }

                // Add to top students or at-risk students
                $studentData = [
                    'name' => $student['first_name'] . ' ' . $student['last_name'],
                    'avg_grade' => round($studentAvg, 1),
                    'student_code' => $student['student_code']
                ];

                if ($studentAvg >= 90) {
                    $sectionStats['top_students'][] = $studentData;
                } elseif ($studentAvg < 75) {
                    $sectionStats['at_risk_students'][] = $studentData;
                }
            }
        }

        // Calculate section average grade
        if (!empty($allGrades)) {
            $sectionStats['avg_grade'] = round(array_sum($allGrades) / count($allGrades), 1);
            $overallAnalytics['overall_avg_grade'] += $sectionStats['avg_grade'];
        }

        // Get subject performance for this section
        $stmt = $pdo->prepare("
            SELECT 
                g.subject_code,
                sub.subject_name,
                AVG(g.final_grade) as avg_grade,
                COUNT(g.student_code) as graded_students
            FROM grades g
            INNER JOIN subjects sub ON g.subject_code = sub.subject_code
            INNER JOIN students s ON g.student_code = s.student_code
            WHERE s.section_id = ?
            AND g.teacher_code = ?
            AND g.quarter = ?
            AND g.final_grade > 0
            GROUP BY g.subject_code, sub.subject_name
            ORDER BY avg_grade DESC
        ");
        $stmt->execute([$sectionId, $teacher_code, $currentQuarter]);
        $sectionStats['subject_performance'] = $stmt->fetchAll();

        // Get attendance stats for this section (last 30 days)
        $stmt = $pdo->prepare("
            SELECT 
                a.status,
                COUNT(*) as count
            FROM attendance a
            INNER JOIN students s ON a.student_code = s.student_code
            WHERE s.section_id = ?
            AND a.teacher_code = ?
            AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY a.status
        ");
        $stmt->execute([$sectionId, $teacher_code]);
        $attendanceResults = $stmt->fetchAll();

        foreach ($attendanceResults as $result) {
            if (isset($sectionStats['attendance_stats'][$result['status']])) {
                $sectionStats['attendance_stats'][$result['status']] = (int)$result['count'];
                $overallAnalytics['attendance_stats'][$result['status']] += (int)$result['count'];
            }
        }

        $sectionsData[] = [
            'section_info' => $section,
            'analytics' => $sectionStats
        ];
    }

    // Calculate overall average grade
    if ($overallAnalytics['total_sections'] > 0) {
        $overallAnalytics['overall_avg_grade'] = round($overallAnalytics['overall_avg_grade'] / $overallAnalytics['total_sections'], 1);
    }

    // Get overall subject performance
    $stmt = $pdo->prepare("
        SELECT 
            g.subject_code,
            sub.subject_name,
            AVG(g.final_grade) as avg_grade,
            COUNT(DISTINCT g.student_code) as student_count
        FROM grades g
        INNER JOIN subjects sub ON g.subject_code = sub.subject_code
        INNER JOIN students s ON g.student_code = s.student_code
        INNER JOIN sections sec ON s.section_id = sec.section_id
        INNER JOIN section_schedules ss ON sec.section_id = ss.section_id
        WHERE ss.teacher_code = ?
        AND g.teacher_code = ?
        AND g.quarter = ?
        AND g.final_grade > 0
        AND ss.is_active = 1
        GROUP BY g.subject_code, sub.subject_name
        ORDER BY avg_grade DESC
    ");
    $stmt->execute([$teacher_code, $teacher_code, $currentQuarter]);
    $overallAnalytics['subject_performance'] = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Teacher reports query error: " . $e->getMessage());
}

$teacherName = htmlspecialchars($first_name . ' ' . $last_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Creative Dreams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    :root {
        --primary-green: #5a9c4e;
        --light-green: #6db560;
        --dark-green: #4a8240;
        --text-dark: #2d5a24;
        --sage-green: #52a347;
        --accent-green: #68b85d;
        --pale-green: #e0f7fa; /* Updated to match target */
        --light-sage: #7ec274;
        --forest-green: #3d6e35;
        --gradient-primary: linear-gradient(135deg, #5a9c4e 0%, #4a8240 100%);
        --gradient-secondary: linear-gradient(135deg, #52a347 0%, #3d6e35 100%);
        --gradient-light: linear-gradient(135deg, #e0f7fa 0%, #c8e6c9 100%);
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
        background: var(--gradient-primary);
        padding: 15px 30px;
        border-radius: 15px;
        margin: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: none;
        backdrop-filter: none;
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
        box-shadow: none;
    }

    .brand-text h1 {
        color: white;
        font-size: 28px;
        font-weight: bold;
        margin: 0;
        text-shadow: none;
    }

    .brand-text p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
        margin: 0;
        font-style: italic;
        font-weight: 500;
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
        backdrop-filter: none;
    }

    .icon-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
        box-shadow: none;
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
        border: none;
        backdrop-filter: none;
    }

    .welcome-section {
        text-align: center;
        padding: 20px;
        border-bottom: 2px solid #e0e0e0;
        margin-bottom: 20px;
        background: transparent;
        border-radius: 0;
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
        box-shadow: none;
        border: none;
    }

    .teacher-avatar i {
        font-size: 40px;
        color: white;
    }

    .welcome-section h5 {
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
        font-size: 18px;
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
        border: none;
        box-shadow: none;
    }

    .faculty-id-section h6 {
        color: #666;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
        font-weight: bold;
    }

    .faculty-id-section .id-number {
        font-size: 24px;
        font-weight: bold;
        color: var(--sage-green);
        font-family: 'Courier New', monospace;
        text-shadow: none;
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
        border: none;
        background: transparent;
    }

    .menu-item:hover {
        background: var(--pale-green);
        color: var(--sage-green);
        transform: translateX(5px);
        border-color: transparent;
        box-shadow: none;
    }

    .menu-item.active {
        background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
        color: white;
        border-color: transparent;
        box-shadow: none;
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
        box-shadow: none;
    }

    .logout-btn:hover {
        background: #d32f2f;
        transform: translateY(-2px);
        box-shadow: none;
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
        text-shadow: none;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .page-title i {
        background: none;
        -webkit-text-fill-color: var(--text-dark);
        color: var(--text-dark);
    }

    /* Analytics Card converted to Target 'Card' Style */
    .analytics-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        overflow: hidden;
        transition: all 0.3s;
        border: none;
        backdrop-filter: none;
    }

    .analytics-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        background: white;
        color: var(--text-dark);
        padding: 20px;
        font-weight: bold;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 2px solid #e0e0e0;
        cursor: pointer;
        transition: all 0.3s;
    }

    .card-header:hover {
        background: white;
    }

    .card-header i {
        font-size: 20px;
        color: var(--sage-green);
        transition: transform 0.3s ease;
    }

    .card-header.collapsed i {
        transform: rotate(-90deg);
    }

    .card-body {
        padding: 25px;
        background: white;
    }

    .chart-container {
        position: relative;
        height: 320px;
        width: 100%;
        margin-bottom: 25px;
        background: white;
        border-radius: 15px;
        padding: 0;
        box-shadow: none;
        border: none;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    /* Stat Card converted to Target 'Info Card' Style */
    .stat-card {
        background: white;
        padding: 30px 25px;
        border-radius: 15px;
        text-align: center;
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        border-color: transparent;
    }

    .stat-value {
        font-size: 36px;
        font-weight: bold;
        color: var(--text-dark);
        margin-bottom: 5px;
        text-shadow: none;
    }

    .stat-label {
        font-size: 12px;
        color: var(--sage-green);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .performance-badge {
        display: inline-block;
        padding: 6px 15px;
        border-radius: 25px;
        font-size: 13px;
        font-weight: 700;
        margin: 3px;
        box-shadow: none;
        border: none;
    }

    .badge-excellent {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        color: white;
    }
    .badge-good {
        background: linear-gradient(135deg, #20c997 0%, #199d76 100%);
        color: white;
    }
    .badge-average {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: black;
    }
    .badge-needs-improvement {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }

    .student-list {
        max-height: 220px;
        overflow-y: auto;
        background: white;
        border-radius: 12px;
        box-shadow: none;
        border: 1px solid #e0e0e0;
    }

    .student-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    .student-item:hover {
        background: var(--pale-green);
        transform: translateX(5px);
    }

    .student-item:last-child {
        border-bottom: none;
    }

    .search-box {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 25px;
        padding: 8px 20px;
        color: white;
        width: 300px;
        font-size: 15px;
        backdrop-filter: none;
        transition: all 0.3s ease;
        box-shadow: none;
    }

    .search-box::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }

    .search-box:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.3);
        box-shadow: none;
        width: 300px;
    }

    .section-header {
        background: white;
        color: var(--text-dark);
        padding: 20px;
        border-radius: 15px 15px 0 0;
        box-shadow: none;
        border-bottom: 2px solid #e0e0e0;
    }

    .section-title {
        font-size: 20px;
        font-weight: bold;
        margin: 0;
        text-shadow: none;
        color: var(--text-dark);
    }

    .section-subtitle {
        opacity: 0.8;
        margin: 5px 0 0 0;
        font-weight: 500;
        font-size: 14px;
        color: #666;
    }

    /* Enhanced Tables adapted to new style */
    .data-table {
        width: 100%;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin: 20px 0;
        border: none;
    }

    .data-table thead {
        background: var(--gradient-primary);
        color: white;
    }

    .data-table th {
        padding: 15px 20px;
        font-weight: bold;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1px;
        border: none;
    }

    .data-table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid #f0f0f0;
    }

    .data-table tbody tr:hover {
        background: var(--pale-green);
        transform: translateX(5px);
    }

    .data-table tbody tr:last-child {
        border-bottom: none;
    }

    .data-table td {
        padding: 15px 20px;
        border: none;
        font-weight: 500;
        color: #495057;
        vertical-align: middle;
    }

    .data-table .highlight {
        background: var(--pale-green);
        font-weight: 600;
    }

    /* Performance Indicators */
    .performance-indicator {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
    }

    .indicator-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            margin-bottom: 20px;
        }

        .search-box {
            width: 150px;
            font-size: 14px;
        }

        .search-box:focus {
            width: 200px;
        }

        .brand-text h1 {
            font-size: 20px;
        }

        .page-title {
            font-size: 22px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .chart-container {
            height: 280px;
        }
    }

    @media (max-width: 576px) {
        .top-header {
            margin: 15px;
            padding: 15px;
        }

        .main-content {
            padding: 15px;
        }

        .card-body {
            padding: 20px;
        }

        .stat-card {
            padding: 20px 15px;
        }
    }

    /* Custom Scrollbar - kept for usability but styled to match */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: var(--sage-green);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--dark-green);
    }
</style>
</head>

<body>
    <!-- Header -->
    <div class="top-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-graduation-cap" style="color: var(--sage-green);"></i>
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
                        <p style="font-size: 17px; color: #2c3e50; font-weight: bold;">
                            <?php echo strtoupper($teacherName); ?>
                        </p>
                        <p><i class="fas fa-check-circle"></i> Teacher Portal</p>
                    </div>

                    <!-- Faculty ID Section -->
                    <div class="faculty-id-section">
                        <h6>Faculty ID</h6>
                        <div class="id-number"><?php echo htmlspecialchars($teacher_code); ?></div>
                        <div class="subject">
                            <i class="fas fa-book"></i> Quarter: <?php echo htmlspecialchars($currentQuarter); ?>
                        </div>
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
                        <a href="teacher_reports.php" class="menu-item active">
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
                        <i class="fas fa-chart-bar"></i> Analytics & Reports
                    </h2>

                    <!-- Overall Analytics Section -->
                    <div class="analytics-card">
                        <div class="section-header">
                            <div>
                                <h3 class="section-title">📊 Overall Analytics Dashboard</h3>
                                <p class="section-subtitle"></p>
                            </div>
                            <div class="performance-indicator" style="background: rgba(255,255,255,0.2); color: white;">
                                <span class="indicator-dot" style="background: #28a745;"></span>
                                Current Quarter: <?php echo htmlspecialchars($currentQuarter); ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Overall Statistics -->
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $overallAnalytics['total_students']; ?></div>
                                    <div class="stat-label">Total Students</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $overallAnalytics['total_sections']; ?></div>
                                    <div class="stat-label">Active Sections</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $overallAnalytics['overall_avg_grade']; ?>%</div>
                                    <div class="stat-label">Overall Average</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value">
                                        <?php 
                                            $attendanceTotal = array_sum($overallAnalytics['attendance_stats']);
                                            $presentRate = $attendanceTotal > 0 ? 
                                                round(($overallAnalytics['attendance_stats']['present'] / $attendanceTotal) * 100) : 0;
                                            echo $presentRate . '%';
                                        ?>
                                    </div>
                                    <div class="stat-label">Attendance Rate</div>
                                </div>
                            </div>

                            <!-- Performance Distribution Table -->
                            <div class="data-table">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Performance Category</th>
                                            <th>Number of Students</th>
                                            <th>Percentage</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $performanceData = [
                                            ['Excellent (90-100%)', $overallAnalytics['performance_distribution']['excellent'], '#28a745'],
                                            ['Good (80-89%)', $overallAnalytics['performance_distribution']['good'], '#20c997'],
                                            ['Average (75-79%)', $overallAnalytics['performance_distribution']['average'], '#ffc107'],
                                            ['Needs Improvement (<75%)', $overallAnalytics['performance_distribution']['needs_improvement'], '#dc3545']
                                        ];
                                        
                                        $totalStudents = $overallAnalytics['total_students'];
                                        foreach ($performanceData as $index => $data):
                                            $percentage = $totalStudents > 0 ? round(($data[1] / $totalStudents) * 100, 1) : 0;
                                        ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'highlight' : ''; ?>">
                                            <td>
                                                <div class="performance-indicator">
                                                    <span class="indicator-dot" style="background: <?php echo $data[2]; ?>"></span>
                                                    <?php echo $data[0]; ?>
                                                </div>
                                            </td>
                                            <td><strong><?php echo $data[1]; ?></strong> students</td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" style="width: <?php echo $percentage; ?>%; background: <?php echo $data[2]; ?>"></div>
                                                </div>
                                                <small><?php echo $percentage; ?>%</small>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill" style="background: <?php echo $data[2]; ?>; color: white;">
                                                    <?php echo $percentage >= 60 ? 'Excellent' : ($percentage >= 40 ? 'Good' : ($percentage >= 20 ? 'Fair' : 'Needs Attention')); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Overall Charts Row -->
                            <div class="row mt-4">
                                <div class="col-lg-6 mb-4">
                                    <h5 class="mb-3"><i class="fas fa-venus-mars text-primary"></i> Gender Distribution</h5>
                                    <div class="chart-container">
                                        <canvas id="overallGenderChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-4">
                                    <h5 class="mb-3"><i class="fas fa-chart-pie text-success"></i> Performance Overview</h5>
                                    <div class="chart-container">
                                        <canvas id="overallPerformanceChart"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Subject Performance -->
                            <div class="row mt-2">
                                <div class="col-12">
                                    <h5 class="mb-3"><i class="fas fa-book text-info"></i> Subject Performance Analysis</h5>
                                    <div class="chart-container">
                                        <canvas id="overallSubjectChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Analytics -->
                    <div class="d-flex align-items-center mb-4">
                        <h3 style="color: var(--text-dark); margin: 0;">
                            <i class="fas fa-layer-group"></i> Section Analytics
                        </h3>
                        <span class="badge bg-primary ms-3" style="font-size: 14px; padding: 8px 12px;">
                            <?php echo count($sectionsData); ?> Sections
                        </span>
                    </div>

                    <?php if (empty($sectionsData)): ?>
                        <div class="analytics-card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-users-slash" style="font-size: 72px; color: #dee2e6; margin-bottom: 20px;"></i>
                                <h4 style="color: #6c757d; margin-bottom: 15px;">No Sections Found</h4>
                                <p class="text-muted">You are not currently assigned to any active sections.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sectionsData as $index => $sectionData): 
                            $section = $sectionData['section_info'];
                            $analytics = $sectionData['analytics'];
                            $sectionId = 'section-' . $section['section_id'];
                        ?>
                            <div class="analytics-card">
                                <div class="card-header" data-bs-toggle="collapse" data-bs-target="#<?php echo $sectionId; ?>" aria-expanded="true">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-chevron-down me-3"></i>
                                        <div>
                                            <strong>Grade <?php echo htmlspecialchars($section['grade_level']); ?> - <?php echo htmlspecialchars($section['section_name']); ?></strong>
                                            <div class="mt-1">
                                                <small class="opacity-90">
                                                    <i class="fas fa-door-open"></i> Room <?php echo htmlspecialchars($section['room_assignment']); ?>
                                                    • <i class="fas fa-user-graduate"></i> <?php echo $analytics['total_students']; ?> Students
                                                    • <i class="fas fa-chart-line"></i> Average: <?php echo $analytics['avg_grade']; ?>%
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="performance-indicator" style="background: rgba(255,255,255,0.2); color: white;">
                                            Section <?php echo $index + 1; ?>
                                        </span>
                                    </div>
                                </div>

                                <div id="<?php echo $sectionId; ?>" class="collapse show">
                                    <div class="card-body">
                                        <!-- Section Statistics -->
                                        <div class="stats-grid">
                                            <div class="stat-card">
                                                <div class="stat-value"><?php echo $analytics['male_count']; ?></div>
                                                <div class="stat-label">Male Students</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value"><?php echo $analytics['female_count']; ?></div>
                                                <div class="stat-label">Female Students</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value"><?php echo $analytics['avg_grade']; ?>%</div>
                                                <div class="stat-label">Average Grade</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value">
                                                    <?php 
                                                        $attendanceTotal = array_sum($analytics['attendance_stats']);
                                                        $presentRate = $attendanceTotal > 0 ? 
                                                            round(($analytics['attendance_stats']['present'] / $attendanceTotal) * 100) : 0;
                                                        echo $presentRate . '%';
                                                    ?>
                                                </div>
                                                <div class="stat-label">Attendance Rate</div>
                                            </div>
                                        </div>

                                        <!-- Section Charts -->
                                        <div class="row mt-4">
                                            <div class="col-lg-4 mb-4">
                                                <h6 class="text-center mb-3"><i class="fas fa-venus-mars text-primary"></i> Gender</h6>
                                                <div class="chart-container" style="height: 220px;">
                                                    <canvas id="genderChart<?php echo $section['section_id']; ?>"></canvas>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 mb-4">
                                                <h6 class="text-center mb-3"><i class="fas fa-chart-pie text-success"></i> Performance</h6>
                                                <div class="chart-container" style="height: 220px;">
                                                    <canvas id="performanceChart<?php echo $section['section_id']; ?>"></canvas>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 mb-4">
                                                <h6 class="text-center mb-3"><i class="fas fa-calendar-check text-warning"></i> Attendance</h6>
                                                <div class="chart-container" style="height: 220px;">
                                                    <canvas id="attendanceChart<?php echo $section['section_id']; ?>"></canvas>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Student Performance Tables -->
                                        <div class="row mt-2">
                                            <div class="col-lg-6 mb-4">
                                                <h6 class="mb-3"><i class="fas fa-trophy text-warning"></i> Top Performing Students</h6>
                                                <div class="data-table">
                                                    <table class="table table-hover mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Student Name</th>
                                                                <th>Average Grade</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (!empty($analytics['top_students'])): ?>
                                                                <?php foreach ($analytics['top_students'] as $student): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                                        <td><strong><?php echo $student['avg_grade']; ?>%</strong></td>
                                                                        <td><span class="badge-excellent">Excellent</span></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr>
                                                                    <td colspan="3" class="text-center text-muted py-3">No top students found</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 mb-4">
                                                <h6 class="mb-3"><i class="fas fa-exclamation-triangle text-danger"></i> Students Needing Attention</h6>
                                                <div class="data-table">
                                                    <table class="table table-hover mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Student Name</th>
                                                                <th>Average Grade</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (!empty($analytics['at_risk_students'])): ?>
                                                                <?php foreach ($analytics['at_risk_students'] as $student): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                                        <td><strong><?php echo $student['avg_grade']; ?>%</strong></td>
                                                                        <td><span class="badge-needs-improvement">Needs Help</span></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr>
                                                                    <td colspan="3" class="text-center text-muted py-3">No students at risk</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Subject Performance -->
                                        <?php if (!empty($analytics['subject_performance'])): ?>
                                            <div class="row mt-2">
                                                <div class="col-12">
                                                    <h6 class="mb-3"><i class="fas fa-book text-info"></i> Subject Performance</h6>
                                                    <div class="chart-container" style="height: 280px;">
                                                        <canvas id="subjectChart<?php echo $section['section_id']; ?>"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Overall Charts
        // Gender Distribution
        const overallGenderCtx = document.getElementById('overallGenderChart').getContext('2d');
        new Chart(overallGenderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [<?php echo $overallAnalytics['gender_distribution']['Male']; ?>, <?php echo $overallAnalytics['gender_distribution']['Female']; ?>],
                    backgroundColor: ['#36a2eb', '#ff6384'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Performance Distribution
        const overallPerformanceCtx = document.getElementById('overallPerformanceChart').getContext('2d');
        new Chart(overallPerformanceCtx, {
            type: 'pie',
            data: {
                labels: ['Excellent', 'Good', 'Average', 'Needs Improvement'],
                datasets: [{
                    data: [
                        <?php echo $overallAnalytics['performance_distribution']['excellent']; ?>,
                        <?php echo $overallAnalytics['performance_distribution']['good']; ?>,
                        <?php echo $overallAnalytics['performance_distribution']['average']; ?>,
                        <?php echo $overallAnalytics['performance_distribution']['needs_improvement']; ?>
                    ],
                    backgroundColor: ['#28a745', '#20c997', '#ffc107', '#dc3545'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Subject Performance
        const overallSubjectCtx = document.getElementById('overallSubjectChart').getContext('2d');
        new Chart(overallSubjectCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(', ', array_map(function($subject) { return "'" . addslashes($subject['subject_name']) . "'"; }, $overallAnalytics['subject_performance'])); ?>],
                datasets: [{
                    label: 'Average Grade (%)',
                    data: [<?php echo implode(', ', array_map(function($subject) { return number_format($subject['avg_grade'], 1); }, $overallAnalytics['subject_performance'])); ?>],
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { 
                            display: true, 
                            text: 'Average Grade (%)',
                            font: { weight: 'bold' }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Section-specific charts
        <?php foreach ($sectionsData as $sectionData): 
            $section = $sectionData['section_info'];
            $analytics = $sectionData['analytics'];
        ?>
            // Gender Chart for Section
            new Chart(document.getElementById('genderChart<?php echo $section['section_id']; ?>').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Male', 'Female'],
                    datasets: [{
                        data: [<?php echo $analytics['male_count']; ?>, <?php echo $analytics['female_count']; ?>],
                        backgroundColor: ['#36a2eb', '#ff6384'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    cutout: '60%'
                }
            });

            // Performance Chart for Section
            new Chart(document.getElementById('performanceChart<?php echo $section['section_id']; ?>').getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Excellent', 'Good', 'Average', 'Needs Improvement'],
                    datasets: [{
                        data: [
                            <?php echo $analytics['performance_distribution']['excellent']; ?>,
                            <?php echo $analytics['performance_distribution']['good']; ?>,
                            <?php echo $analytics['performance_distribution']['average']; ?>,
                            <?php echo $analytics['performance_distribution']['needs_improvement']; ?>
                        ],
                        backgroundColor: ['#28a745', '#20c997', '#ffc107', '#dc3545'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });

            // Attendance Chart for Section
            new Chart(document.getElementById('attendanceChart<?php echo $section['section_id']; ?>').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Present', 'Absent', 'Late', 'Excused'],
                    datasets: [{
                        data: [
                            <?php echo $analytics['attendance_stats']['present']; ?>,
                            <?php echo $analytics['attendance_stats']['absent']; ?>,
                            <?php echo $analytics['attendance_stats']['late']; ?>,
                            <?php echo $analytics['attendance_stats']['excused']; ?>
                        ],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            <?php if (!empty($analytics['subject_performance'])): ?>
                // Subject Performance for Section
                new Chart(document.getElementById('subjectChart<?php echo $section['section_id']; ?>').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: [<?php echo implode(', ', array_map(function($subject) { return "'" . addslashes($subject['subject_name']) . "'"; }, $analytics['subject_performance'])); ?>],
                        datasets: [{
                            label: 'Average Grade',
                            data: [<?php echo implode(', ', array_map(function($subject) { return number_format($subject['avg_grade'], 1); }, $analytics['subject_performance'])); ?>],
                            backgroundColor: 'rgba(76, 175, 80, 0.8)',
                            borderColor: 'rgba(76, 175, 80, 1)',
                            borderWidth: 2,
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: { 
                                    display: true, 
                                    text: 'Average Grade (%)',
                                    font: { weight: 'bold' }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            <?php endif; ?>
        <?php endforeach; ?>

        // Add animation to cards
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.analytics-card').forEach(el => {
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
                // Add search logic here
            });
        }
    </script>
</body>
</html>