<?php
session_start();

// Check if admin is logged in
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

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';

// Fetch all sections with adviser details and student count
$sections = [];
try {
    $stmt = $pdo->query("
        SELECT 
            s.*,
            t.first_name as adviser_first_name,
            t.last_name as adviser_last_name,
            COUNT(DISTINCT st.student_id) as student_count
        FROM sections s
        LEFT JOIN teachers t ON s.adviser_code = t.teacher_code
        LEFT JOIN students st ON s.section_id = st.section_id AND st.status = 'active'
        WHERE s.is_active = 1
        GROUP BY s.section_id
        ORDER BY s.grade_level, s.section_name
    ");
    $sections = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching sections: " . $e->getMessage());
}

// Fetch all teachers for dropdown
$teachers = [];
try {
    $stmt = $pdo->query("SELECT * FROM teachers WHERE status = 'active' ORDER BY last_name, first_name");
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
}

// Fetch all students for assignment
$students = [];
try {
    $stmt = $pdo->query("
        SELECT 
            s.*,
            sec.section_name,
            sec.grade_level
        FROM students s
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        WHERE s.status = 'active'
        ORDER BY s.last_name, s.first_name
    ");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
}

// Function to get section details
function getSectionDetails($pdo, $section_id)
{
    // Get students in section
    $stmt = $pdo->prepare("
        SELECT * FROM students 
        WHERE section_id = ? AND status = 'active'
        ORDER BY last_name, first_name
    ");
    $stmt->execute([$section_id]);
    $students = $stmt->fetchAll();

    // Get section info for grade level and current adviser
    $stmt = $pdo->prepare("
        SELECT s.grade_level, s.adviser_code,
               CONCAT(t.first_name, ' ', t.last_name) as current_adviser
        FROM sections s
        LEFT JOIN teachers t ON s.adviser_code = t.teacher_code
        WHERE s.section_id = ?
    ");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch();

    // Get schedule with teachers assigned
    $stmt = $pdo->prepare("
        SELECT 
            ss.schedule_id,
            ss.teacher_code,
            gst.day_of_week,
            gst.subject_code,
            gst.room_type,
            mts.slot_name,
            mts.start_time,
            mts.end_time,
            mts.slot_type,
            sub.subject_name,
            t.first_name as teacher_first_name,
            t.last_name as teacher_last_name
        FROM section_schedules ss
        JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
        JOIN master_time_slots mts ON gst.slot_id = mts.slot_id
        JOIN subjects sub ON gst.subject_code = sub.subject_code
        LEFT JOIN teachers t ON ss.teacher_code = t.teacher_code
        WHERE ss.section_id = ? AND ss.is_active = 1
        ORDER BY 
            FIELD(gst.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
            mts.slot_order
    ");
    $stmt->execute([$section_id]);
    $schedules = $stmt->fetchAll();

    // Get available teachers
    $stmt = $pdo->query("SELECT * FROM teachers WHERE status = 'active' ORDER BY last_name, first_name");
    $teachers = $stmt->fetchAll();

    return [
        'students' => $students,
        'schedules' => $schedules,
        'teachers' => $teachers,
        'grade_level' => $section['grade_level'],
        'current_adviser' => $section['current_adviser']
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'get_section_details':
                $section_id = $_POST['section_id'];
                $details = getSectionDetails($pdo, $section_id);
                echo json_encode(['success' => true, 'data' => $details]);
                break;

            case 'update_adviser':
                $section_id = $_POST['section_id'];
                $teacher_code = $_POST['teacher_code'];

                $stmt = $pdo->prepare("UPDATE sections SET adviser_code = ? WHERE section_id = ?");
                $stmt->execute([$teacher_code, $section_id]);

                echo json_encode(['success' => true, 'message' => 'Adviser updated successfully']);
                break;

            case 'assign_student':
                $student_id = $_POST['student_id'];
                $section_id = $_POST['section_id'];

                $stmt = $pdo->prepare("UPDATE students SET section_id = ? WHERE student_id = ?");
                $stmt->execute([$section_id, $student_id]);

                echo json_encode(['success' => true, 'message' => 'Student assigned successfully']);
                break;

            case 'remove_student':
                $student_id = $_POST['student_id'];

                $stmt = $pdo->prepare("UPDATE students SET section_id = NULL WHERE student_id = ?");
                $stmt->execute([$student_id]);

                echo json_encode(['success' => true, 'message' => 'Student removed from section']);
                break;

            case 'update_schedule_teacher':
                $schedule_id = $_POST['schedule_id'];
                $teacher_code = $_POST['teacher_code'];

                $stmt = $pdo->prepare("UPDATE section_schedules SET teacher_code = ? WHERE schedule_id = ?");
                $stmt->execute([$teacher_code, $schedule_id]);

                echo json_encode(['success' => true, 'message' => 'Teacher assigned successfully']);
                break;

            case 'remove_schedule_teacher':
                $schedule_id = $_POST['schedule_id'];

                $stmt = $pdo->prepare("UPDATE section_schedules SET teacher_code = NULL WHERE schedule_id = ?");
                $stmt->execute([$schedule_id]);

                echo json_encode(['success' => true, 'message' => 'Teacher removed successfully']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections - Creative Dreams</title>
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

        .main-content {
            padding: 20px;
        }

        .page-title {
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .section-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }

        .grade-badge {
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .section-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item i {
            color: #52a347;
            font-size: 20px;
            width: 30px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: bold;
        }

        .section-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .stat-box {
            flex: 1;
            background: #f5f5f5;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #52a347;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Modal Styles */
        .modal-header {
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .student-list-item {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .student-list-item:hover {
            border-color: #52a347;
            background: #f5f5f5;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }

        .schedule-table {
            width: 100%;
            margin-top: 15px;
        }

        .schedule-table th {
            background: #52a347;
            color: white;
            padding: 10px;
            text-align: left;
        }

        .schedule-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .filter-section select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
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
                <button class="icon-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                </button>
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
                        <a href="admin_dashboard.php" class="menu-item">
                            <i class="fas fa-chart-line"></i>
                            <span>DASHBOARD</span>
                        </a>
                        <a href="enrollment_management.php" class="menu-item">
                            <i class="fas fa-user-graduate"></i>
                            <span>ENROLLMENT</span>
                        </a>
                        <a href="request.php" class="menu-item">
                            <i class="fas fa-calendar-check"></i>
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
                        <a href="manage_sections.php" class="menu-item active">
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
                    <form action="logout.php" method="POST">
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
                        <i class="fas fa-door-open"></i> Manage Sections
                    </h2>

                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="form-label"><strong>Filter by Grade Level</strong></label>
                                <select id="gradeFilter" class="form-select">
                                    <option value="">All Grades</option>
                                    <option value="1">Grade 1</option>
                                    <option value="2">Grade 2</option>
                                    <option value="3">Grade 3</option>
                                    <option value="4">Grade 4</option>
                                    <option value="5">Grade 5</option>
                                    <option value="6">Grade 6</option>
                                </select>
                            </div>
                            <div class="col-md-9 text-end">
                                <span class="text-muted">Click on any section card to view details</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sections Grid -->
                    <div id="sectionsContainer" class="row">
                        <?php foreach ($sections as $section): ?>
                            <div class="col-lg-6 col-xl-4 section-item" data-grade="<?php echo $section['grade_level']; ?>">
                                <div class="section-card" onclick="viewSectionDetails(<?php echo $section['section_id']; ?>)">
                                    <div class="section-header">
                                        <h3 class="section-title"><?php echo htmlspecialchars($section['section_name']); ?></h3>
                                        <div class="grade-badge">Grade <?php echo $section['grade_level']; ?></div>
                                    </div>

                                    <div class="section-info">
                                        <div class="info-item">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                            <div>
                                                <div class="info-label">Adviser</div>
                                                <div class="info-value">
                                                    <?php
                                                    if ($section['adviser_first_name']) {
                                                        echo htmlspecialchars($section['adviser_first_name'] . ' ' . $section['adviser_last_name']);
                                                    } else {
                                                        echo 'Not Assigned';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="info-item">
                                            <i class="fas fa-door-closed"></i>
                                            <div>
                                                <div class="info-label">Room</div>
                                                <div class="info-value"><?php echo htmlspecialchars($section['room_assignment']); ?></div>
                                            </div>
                                        </div>

                                        <div class="info-item">
                                            <i class="fas fa-calendar-alt"></i>
                                            <div>
                                                <div class="info-label">School Year</div>
                                                <div class="info-value"><?php echo htmlspecialchars($section['school_year']); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="section-stats">
                                        <div class="stat-box">
                                            <div class="stat-number"><?php echo $section['student_count']; ?></div>
                                            <div class="stat-label">Students</div>
                                        </div>
                                        <div class="stat-box">
                                            <div class="stat-number"><?php echo $section['max_capacity']; ?></div>
                                            <div class="stat-label">Capacity</div>
                                        </div>
                                        <div class="stat-box">
                                            <div class="stat-number">
                                                <?php echo $section['max_capacity'] - $section['student_count']; ?>
                                            </div>
                                            <div class="stat-label">Available</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($sections)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No sections found</h3>
                            <p>There are no active sections in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Details Modal -->
    <div class="modal fade" id="sectionDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-door-open"></i> Section Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="sectionDetailsContent">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Student Modal -->
    <div class="modal fade" id="assignStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> Assign Student to Section
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="assignSectionId">
                    <div class="mb-3">
                        <label class="form-label">Select Student</label>
                        <select id="assignStudentSelect" class="form-select">
                            <option value="">Choose a student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>">
                                    <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['student_code'] . ')'); ?>
                                    <?php if ($student['section_name']): ?>
                                        - Currently in Grade <?php echo $student['grade_level']; ?> - <?php echo htmlspecialchars($student['section_name']); ?>
                                    <?php else: ?>
                                        - Not assigned
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmAssignStudent()">
                        <i class="fas fa-check"></i> Assign Student
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Adviser Modal -->
    <div class="modal fade" id="changeAdviserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit"></i> Change Section Adviser
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="adviserSectionId">
                    <div id="currentAdviserInfo" style="display: none; margin-bottom: 15px;"></div>
                    <div class="mb-3">
                        <label class="form-label">Select New Adviser</label>
                        <select id="adviserTeacherSelect" class="form-select">
                            <option value="">Choose a teacher...</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['teacher_code']; ?>">
                                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['teacher_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmChangeAdviser()">
                        <i class="fas fa-check"></i> Update Adviser
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Schedule Teacher Modal -->
    <div class="modal fade" id="changeScheduleTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit"></i> Assign Teacher
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- ADD THIS DIV - it was missing -->
                    <div id="currentScheduleTeacherInfo" style="display: none; margin-bottom: 15px;"></div>

                    <div class="mb-3">
                        <label class="form-label">Select Teacher</label>
                        <select id="scheduleTeacherSelect" class="form-select">
                            <option value="">Choose a teacher...</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['teacher_code']; ?>">
                                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['teacher_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmScheduleTeacherChange()">
                        <i class="fas fa-check"></i> Assign Teacher
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grade filter functionality
        document.getElementById('gradeFilter').addEventListener('change', function() {
            const selectedGrade = this.value;
            const sectionItems = document.querySelectorAll('.section-item');

            sectionItems.forEach(item => {
                if (selectedGrade === '' || item.dataset.grade === selectedGrade) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // View section details
        function viewSectionDetails(sectionId) {
            // Show loading
            const modal = new bootstrap.Modal(document.getElementById('sectionDetailsModal'));
            document.getElementById('sectionDetailsContent').innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                <p class="mt-3">Loading section details...</p>
                </div>
`;
            modal.show();
            // Fetch details
            fetch('manage_sections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_section_details&section_id=' + sectionId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySectionDetails(sectionId, data.data);
                    } else {
                        showError('Failed to load section details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred while loading section details');
                });
        }

        // Display section details
        function displaySectionDetails(sectionId, data) {
            // Get section name from the sections array
            const sectionCard = document.querySelector(`[onclick="viewSectionDetails(${sectionId})"]`);
            const sectionName = sectionCard ? sectionCard.querySelector('.section-title').textContent : '';

            let html = `
        <div class="row">
            <!-- Students Section -->
            <div class="col-lg-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-users text-success"></i> Students (${data.students.length})</h5>
                    <button class="btn btn-sm btn-primary" onclick="openAssignStudentModal(${sectionId})">
                        <i class="fas fa-user-plus"></i> Assign Student
                    </button>
                </div>
                <div style="max-height: 600px; overflow-y: auto;">
    `;

            if (data.students.length > 0) {
                data.students.forEach(student => {
                    html += `
                <div class="student-list-item">
                    <div class="student-info">
                        <div class="student-avatar">
                            ${student.first_name.charAt(0)}${student.last_name.charAt(0)}
                        </div>
                        <div>
                            <strong>${student.last_name}, ${student.first_name}</strong>
                            <br>
                            <small class="text-muted">${student.student_code}</small>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="removeStudent(${student.student_id})">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
                });
            } else {
                html += `
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <p>No students assigned to this section</p>
            </div>
        `;
            }

            html += `
                </div>
            </div>

            <!-- Schedule Section -->
            <div class="col-lg-7">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="fas fa-calendar-alt text-success"></i> Grade ${data.grade_level} - ${sectionName}</h5>
                    <button class="btn btn-sm btn-primary" onclick="openChangeAdviserModal(${sectionId}, '${sectionName}', '${data.current_adviser || ''}')">
                        <i class="fas fa-user-edit"></i> Change Adviser
                    </button>
                </div>
                <div style="max-height: 600px; overflow-y: auto;">
    `;

            if (data.schedules.length > 0) {
                // Group schedules by day
                const schedulesByDay = {};
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

                days.forEach(day => {
                    schedulesByDay[day] = data.schedules.filter(s => s.day_of_week === day);
                });

                // Display schedule in a table format
                html += '<table class="table table-bordered table-sm schedule-table" style="font-size: 0.85rem;">';
                html += '<thead><tr><th width="80">Time</th>';
                days.forEach(day => {
                    html += `<th class="text-center">${day}</th>`;
                });
                html += '</tr></thead><tbody>';

                // Get unique time slots
                const timeSlots = [...new Set(data.schedules.map(s => s.slot_name))];
                const slots = [];
                data.schedules.forEach(s => {
                    const key = `${s.slot_name}|${s.start_time}|${s.end_time}|${s.slot_type}`;
                    if (!slots.find(slot => slot.key === key)) {
                        slots.push({
                            key: key,
                            name: s.slot_name,
                            start: s.start_time,
                            end: s.end_time,
                            type: s.slot_type
                        });
                    }
                });

                // Sort slots by start time
                slots.sort((a, b) => a.start.localeCompare(b.start));

                slots.forEach(slot => {
                    const startTime = formatTime(slot.start);
                    const endTime = formatTime(slot.end);

                    html += `<tr>`;
                    html += `<td style="vertical-align: middle;"><small><strong>${startTime}</strong><br>${endTime}</small></td>`;

                    days.forEach(day => {
                        const schedule = schedulesByDay[day].find(s => s.slot_name === slot.name);

                        if (schedule) {
                            if (schedule.slot_type === 'RECESS' || schedule.slot_type === 'LUNCH') {
                                html += `<td colspan="1" class="text-center" style="background: #f0f0f0; vertical-align: middle;">
                            <strong>${schedule.slot_name}</strong>
                        </td>`;
                            } else {
                                const teacherName = schedule.teacher_first_name ?
                                    `${schedule.teacher_first_name} ${schedule.teacher_last_name}` :
                                    '<span class="text-danger">Not Assigned</span>';

                                const currentTeacher = schedule.teacher_first_name ?
                                    `${schedule.teacher_first_name} ${schedule.teacher_last_name}` :
                                    '';

                                html += `<td style="vertical-align: middle; padding: 5px;">
                            <div style="font-size: 0.75rem;">
                                <strong>${schedule.subject_name}</strong><br>
                                <small class="text-muted">${teacherName}</small><br>
                                <small class="text-info">${schedule.room_type}</small><br>
                                <div class="btn-group mt-1" role="group">
                                    <button class="btn btn-xs btn-outline-primary" style="font-size: 0.7rem; padding: 2px 6px;" 
                                            onclick="changeScheduleTeacher(${schedule.schedule_id}, '${schedule.subject_name}', '${schedule.teacher_code || ''}', '${currentTeacher}')">
                                        <i class="fas fa-edit"></i> Change
                                    </button>
                                    ${schedule.teacher_code ? `
                                    <button class="btn btn-xs btn-outline-danger" style="font-size: 0.7rem; padding: 2px 6px;" 
                                            onclick="removeScheduleTeacher(${schedule.schedule_id}, '${schedule.subject_name}')">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                    ` : ''}
                                </div>
                            </div>
                        </td>`;
                            }
                        } else {
                            html += `<td></td>`;
                        }
                    });

                    html += `</tr>`;
                });

                html += '</tbody></table>';
            } else {
                html += `
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>No schedule assigned to this section</p>
            </div>
        `;
            }

            html += `
                </div>
            </div>
        </div>
    `;

            document.getElementById('sectionDetailsContent').innerHTML = html;
        }

        // Helper function to format time
        function formatTime(timeString) {
            const time = new Date('2000-01-01 ' + timeString);
            return time.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        // Open assign student modal
        function openAssignStudentModal(sectionId) {
            document.getElementById('assignSectionId').value = sectionId;
            document.getElementById('assignStudentSelect').value = '';
            const modal = new bootstrap.Modal(document.getElementById('assignStudentModal'));
            modal.show();
        }

        // Confirm assign student
        function confirmAssignStudent() {
            const sectionId = document.getElementById('assignSectionId').value;
            const studentId = document.getElementById('assignStudentSelect').value;

            if (!studentId) {
                alert('Please select a student');
                return;
            }

            fetch('manage_sections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=assign_student&section_id=${sectionId}&student_id=${studentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('assignStudentModal')).hide();
                        showSuccess(data.message);
                        // Refresh section details
                        viewSectionDetails(sectionId);
                        // Reload page to update counts
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred');
                });
        }

        // Remove student from section
        function removeStudent(studentId) {
            if (!confirm('Are you sure you want to remove this student from the section?')) {
                return;
            }

            fetch('manage_sections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove_student&student_id=${studentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message);
                        // Reload page to update
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred');
                });
        }

        // Open change adviser modal
        function openChangeAdviserModal(sectionId, sectionName, currentAdviser) {
            document.getElementById('adviserSectionId').value = sectionId;

            // Update modal title with section name
            document.querySelector('#changeAdviserModal .modal-title').innerHTML =
                `<i class="fas fa-user-edit"></i> Change Adviser for ${sectionName}`;

            // Show current adviser if exists
            const currentAdviserDiv = document.getElementById('currentAdviserInfo');
            if (currentAdviser && currentAdviser !== 'null' && currentAdviser !== '') {
                currentAdviserDiv.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <strong>Current Adviser:</strong> ${currentAdviser}
            </div>
        `;
                currentAdviserDiv.style.display = 'block';
            } else {
                currentAdviserDiv.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <strong>No adviser currently assigned</strong>
            </div>
        `;
                currentAdviserDiv.style.display = 'block';
            }

            document.getElementById('adviserTeacherSelect').value = '';
            const modal = new bootstrap.Modal(document.getElementById('changeAdviserModal'));
            modal.show();
        }

        // Confirm change adviser
        function confirmChangeAdviser() {
            const sectionId = document.getElementById('adviserSectionId').value;
            const teacherCode = document.getElementById('adviserTeacherSelect').value;

            if (!teacherCode) {
                alert('Please select a teacher');
                return;
            }

            fetch('manage_sections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_adviser&section_id=${sectionId}&teacher_code=${teacherCode}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('changeAdviserModal')).hide();
                        showSuccess(data.message);
                        // Reload page to update
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred');
                });
        }
        // Change schedule teacher
        function changeScheduleTeacher(scheduleId, subjectName, currentTeacherCode, currentTeacherName) {
            // Store schedule ID for later use
            window.currentScheduleId = scheduleId;

            // Update modal title
            document.querySelector('#changeScheduleTeacherModal .modal-title').innerHTML =
                `<i class="fas fa-user-edit"></i> Assign Teacher for ${subjectName}`;

            // Show current teacher if exists
            const currentTeacherDiv = document.getElementById('currentScheduleTeacherInfo');
            if (currentTeacherName && currentTeacherName !== '') {
                currentTeacherDiv.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <strong>Current Teacher:</strong> ${currentTeacherName}
            </div>
        `;
                currentTeacherDiv.style.display = 'block';
            } else {
                currentTeacherDiv.innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <strong>No teacher currently assigned</strong>
            </div>
        `;
                currentTeacherDiv.style.display = 'block';
            }

            // Reset select
            document.getElementById('scheduleTeacherSelect').value = currentTeacherCode || '';

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('changeScheduleTeacherModal'));
            modal.show();
        }

        // Confirm schedule teacher change
        function confirmScheduleTeacherChange() {
            const scheduleId = window.currentScheduleId;
            const teacherCode = document.getElementById('scheduleTeacherSelect').value;

            if (!teacherCode) {
                alert('Please select a teacher');
                return;
            }

            fetch('manage_sections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_schedule_teacher&schedule_id=${scheduleId}&teacher_code=${teacherCode}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('changeScheduleTeacherModal')).hide();
                        bootstrap.Modal.getInstance(document.getElementById('sectionDetailsModal')).hide();
                        showSuccess(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred');
                });
        }

        // Remove schedule teacher
        function removeScheduleTeacher(scheduleId, subjectName) {
            if (!confirm(`Are you sure you want to remove the teacher from ${subjectName}?`)) {
                return;
            }

            fetch('manage_sections.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove_schedule_teacher&schedule_id=${scheduleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('sectionDetailsModal')).hide();
                        showSuccess(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred');
                });
        }

        // Show success message
        function showSuccess(message) {
            // You can implement a toast notification here
            alert(message);
        }

        // Show error message
        function showError(message) {
            alert('Error: ' + message);
        }
    </script>
</body>

</html>