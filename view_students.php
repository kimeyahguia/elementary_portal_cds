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

// Handle Add Student
if (isset($_POST['add_student'])) {
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?: null;
    $last_name = $_POST['last_name'];
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $address = $_POST['address'] ?: null;
    $section_id = $_POST['section_id'];
    $date_enrolled = $_POST['date_enrolled'];
    $status = $_POST['status'];
    $parent_option = $_POST['parent_option'];

    try {
        $pdo->beginTransaction();

        // Get current school year from system_settings
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_school_year'");
        $school_year = $stmt->fetch()['setting_value'];
        $year_prefix = substr(explode('-', $school_year)[0], -2);

        // Generate student code
        $stmt = $pdo->query("SELECT student_code FROM students ORDER BY student_id DESC LIMIT 1");
        $lastStudent = $stmt->fetch();

        if ($lastStudent) {
            $lastNumber = intval(substr($lastStudent['student_code'], -5));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $student_code = $year_prefix . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
        $username = $student_code;
        $password = 'student' . $newNumber;
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users table
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, status) 
                               VALUES (?, ?, 'student', ?, 'active')");
        $student_email = strtolower($student_code) . '@cds.edu.ph';
        $stmt->execute([$username, $hashed_password, $student_email]);
        $user_id = $pdo->lastInsertId();

        // Handle parent
        $parent_id = null;
        if ($parent_option === 'existing' && !empty($_POST['existing_parent_id'])) {
            $parent_id = $_POST['existing_parent_id'];
        } elseif ($parent_option === 'new' && !empty($_POST['parent_first_name'])) {
            // Generate parent code
            $stmt = $pdo->query("SELECT parent_code FROM parents ORDER BY parent_id DESC LIMIT 1");
            $lastParent = $stmt->fetch();

            if ($lastParent) {
                $lastParentNumber = intval(substr($lastParent['parent_code'], -5));
                $newParentNumber = $lastParentNumber + 1;
            } else {
                $newParentNumber = 1;
            }

            $parent_code = 'P-' . $year_prefix . '-' . str_pad($newParentNumber, 5, '0', STR_PAD_LEFT);

            // Insert parent
            $stmt = $pdo->prepare("INSERT INTO parents 
                (parent_code, first_name, middle_name, last_name, relationship, email, contact_number, address, occupation) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $parent_code,
                $_POST['parent_first_name'],
                $_POST['parent_middle_name'] ?: null,
                $_POST['parent_last_name'],
                $_POST['parent_relationship'],
                $_POST['parent_email'] ?: null,
                $_POST['parent_contact'] ?: null,
                $_POST['parent_address'] ?: null,
                $_POST['parent_occupation'] ?: null
            ]);
            $parent_id = $pdo->lastInsertId();
        }

        // Insert into students table
        $stmt = $pdo->prepare("INSERT INTO students 
            (user_id, student_code, first_name, middle_name, last_name, section_id, parent_id, gender, birthdate, address, status, date_enrolled) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $student_code,
            $first_name,
            $middle_name,
            $last_name,
            $section_id,
            $parent_id,
            $gender,
            $birthdate,
            $address,
            $status,
            $date_enrolled
        ]);

        // Get the default tuition fee from system settings or use a default value
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'default_tuition_fee'");
        $tuition_fee_result = $stmt->fetch();
        $default_tuition_fee = $tuition_fee_result ? floatval($tuition_fee_result['setting_value']) : 25000.00;

        // Calculate due date (e.g., end of school year or 6 months from enrollment)
        $due_date = date('Y-12-15'); // Default: December 15 of current year

        // Insert into student_balances table
        $stmt = $pdo->prepare("INSERT INTO student_balances 
            (student_code, school_year, total_fee, amount_paid, balance, due_date, status, last_updated) 
            VALUES (?, ?, ?, 0.00, ?, ?, 'unpaid', NOW())");
        $stmt->execute([
            $student_code,
            $school_year,
            $default_tuition_fee,
            $default_tuition_fee, // balance = total_fee since amount_paid is 0
            $due_date
        ]);

        // Update section enrollment count
        $stmt = $pdo->prepare("UPDATE sections SET current_enrollment = current_enrollment + 1 WHERE section_id = ?");
        $stmt->execute([$section_id]);

        $pdo->commit();

        $_SESSION['success_message'] = "Student added successfully! Student Code: $student_code | Default Password: $password | Balance entry created with ₱" . number_format($default_tuition_fee, 2) . " tuition fee.";
        header("Location: view_students.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error adding student: " . $e->getMessage();
        header("Location: view_students.php");
        exit();
    }
}

// Handle Update Student
if (isset($_POST['update_student'])) {
    $student_code = $_POST['student_code'];
    $user_id = $_POST['user_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?: null;
    $last_name = $_POST['last_name'];
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $address = $_POST['address'] ?: null;
    $section_id = $_POST['section_id'];
    $date_enrolled = $_POST['date_enrolled'];
    $status = $_POST['status'];

    try {
        $pdo->beginTransaction();

        // Get old section_id
        $stmt = $pdo->prepare("SELECT section_id FROM students WHERE student_code = ?");
        $stmt->execute([$student_code]);
        $old_section_id = $stmt->fetch()['section_id'];

        // Update students table
        $stmt = $pdo->prepare("UPDATE students SET 
            first_name = ?, middle_name = ?, last_name = ?, gender = ?, 
            birthdate = ?, address = ?, section_id = ?, date_enrolled = ?, status = ?
            WHERE student_code = ?");
        $stmt->execute([
            $first_name,
            $middle_name,
            $last_name,
            $gender,
            $birthdate,
            $address,
            $section_id,
            $date_enrolled,
            $status,
            $student_code
        ]);

        // Update user status if student status changed
        $user_status = ($status === 'active') ? 'active' : 'inactive';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->execute([$user_status, $user_id]);

        // Update section enrollment counts if section changed
        if ($old_section_id != $section_id) {
            // Decrease old section count
            if ($old_section_id) {
                $stmt = $pdo->prepare("UPDATE sections SET current_enrollment = current_enrollment - 1 WHERE section_id = ?");
                $stmt->execute([$old_section_id]);
            }
            // Increase new section count
            $stmt = $pdo->prepare("UPDATE sections SET current_enrollment = current_enrollment + 1 WHERE section_id = ?");
            $stmt->execute([$section_id]);
        }

        $pdo->commit();

        $_SESSION['success_message'] = "Student information updated successfully!";
        header("Location: view_students.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating student: " . $e->getMessage();
        header("Location: view_students.php");
        exit();
    }
}

// Display messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert" style="margin: 20px;">
            <i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success_message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin: 20px;">
            <i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['error_message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    unset($_SESSION['error_message']);
}

// Fetch all students with their section and parent info
$students = [];
try {
    $stmt = $pdo->query("
        SELECT 
            s.*,
            sec.grade_level,
            sec.section_name,
            p.first_name as parent_first_name,
            p.last_name as parent_last_name,
            p.contact_number as parent_contact,
            u.status as account_status
        FROM students s
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        LEFT JOIN users u ON s.user_id = u.user_id
        ORDER BY s.student_code DESC
    ");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
}

// Count statistics
$totalStudents = count($students);
$activeStudents = count(array_filter($students, function ($s) {
    return $s['status'] == 'active';
}));
$inactiveStudents = count(array_filter($students, function ($s) {
    return $s['status'] == 'inactive';
}));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students - Creative Dreams</title>
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
            /* Kept the light background as it matches the sample target's body style */
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            min-height: 100vh;
        }

        .top-header {
            /* Updated to target's header gradient: #5a9c4e to #4a8240 */
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
            /* Updated to target's darker text color: #2d5a24 */
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-add-new {
            /* Updated to target's primary button gradient: #52a347 to #3d6e35 */
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-add-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            /* Updated to target's main accent color: #52a347 */
            color: #52a347;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }

        .search-filter-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            font-weight: bold;
            font-size: 18px;
        }

        .student-table {
            width: 100%;
            margin: 0;
        }

        .student-table thead {
            background: #f5f5f5;
        }

        .student-table th {
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        .student-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .student-table tr:hover {
            background: #f9f9f9;
        }

        .student-code {
            font-weight: bold;
            /* Updated to target's main accent color: #52a347 */
            color: #52a347;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-active {
            background: #e8f5e9;
            color: #4caf50;
        }

        .status-inactive {
            background: #ffebee;
            color: #f44336;
        }

        .status-graduated {
            background: #e3f2fd;
            color: #2196f3;
        }

        .status-transferred {
            background: #fff3e0;
            color: #ff9800;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 2px;
        }

        .btn-view {
            background: #2196f3;
            color: white;
        }

        .btn-view:hover {
            background: #1976d2;
        }

        .btn-edit {
            background: #ff9800;
            color: white;
        }

        .btn-edit:hover {
            background: #f57c00;
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #d32f2f;
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
        }

        .no-profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            /* Updated to target's primary green gradient: #52a347 to #3d6e35 */
            background: linear-gradient(135deg, #52a347, #3d6e35);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .filter-input {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
        }

        .filter-input:focus {
            /* Updated to target's focus color: #52a347 and matching RGBA */
            border-color: #52a347;
            box-shadow: 0 0 0 0.2rem rgba(82, 163, 71, 0.25);
            outline: none;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-body h6 {
            margin-bottom: 15px;
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
                        <a href="fees_payment.php" class="menu-item">
                            <i class="fas fa-credit-card"></i>
                            <span>FEES & PAYMENT</span>
                        </a>
                        <a href="manage_accounts.php" class="menu-item active">
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
                    <div class="page-title">
                        <div>
                            <button class="btn btn-secondary me-3" onclick="window.location.href='manage_accounts.php'" style="padding: 10px 20px; border-radius: 8px;">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <i class="fas fa-user-graduate"></i> Student Management
                        </div>
                        <button class="btn-add-new" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                            <i class="fas fa-plus"></i> Add New Student
                        </button>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $totalStudents; ?></div>
                                    <div class="stat-label">Total Students</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $activeStudents; ?></div>
                                    <div class="stat-label">Active Students</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $inactiveStudents; ?></div>
                                    <div class="stat-label">Inactive Students</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter Bar -->
                    <div class="search-filter-bar">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Search by Student Code</label>
                                <input type="text" class="form-control filter-input" id="searchCode" placeholder="Enter student code...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Search by Name</label>
                                <input type="text" class="form-control filter-input" id="searchName" placeholder="Enter student name...">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Grade Level</label>
                                <select class="form-select filter-input" id="gradeFilter">
                                    <option value="">All Grade Levels</option>
                                    <option value="1">Grade 1</option>
                                    <option value="2">Grade 2</option>
                                    <option value="3">Grade 3</option>
                                    <option value="4">Grade 4</option>
                                    <option value="5">Grade 5</option>
                                    <option value="6">Grade 6</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Status</label>
                                <select class="form-select filter-input" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="graduated">Graduated</option>
                                    <option value="transferred">Transferred</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Gender</label>
                                <select class="form-select filter-input" id="genderFilter">
                                    <option value="">All Genders</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary w-50" onclick="filterTable()" style="background: linear-gradient(135deg, #7cb342, #689f38); border: none; font-weight: 600;">
                                        <i class="fas fa-filter"></i> Apply
                                    </button>
                                    <button class="btn btn-secondary w-50" onclick="resetFilters()">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Students Table -->
                    <div class="table-container">
                        <div class="table-header">
                            <i class="fas fa-list"></i> Student List (<?php echo $totalStudents; ?> total)
                        </div>
                        <div class="table-responsive">
                            <table class="student-table" id="studentTable">
                                <thead>
                                    <tr>
                                        <th>Student Code</th>
                                        <th>Name</th>
                                        <th>Grade & Section</th>
                                        <th>Gender</th>
                                        <th>Parent/Guardian</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <p>No students found in the system.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr data-grade="<?php echo htmlspecialchars($student['grade_level'] ?? ''); ?>"
                                                data-status="<?php echo htmlspecialchars($student['status']); ?>">
                                                <td class="student-code"><?php echo htmlspecialchars($student['student_code']); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                    <?php if ($student['middle_name']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($student['middle_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['grade_level'] && $student['section_name']): ?>
                                                        <strong>Grade <?php echo htmlspecialchars($student['grade_level']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($student['section_name']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($student['parent_first_name']): ?>
                                                        <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No Guardian</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['parent_contact'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo htmlspecialchars($student['status']); ?>">
                                                        <?php echo ucfirst($student['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn-action btn-view" title="View Details"
                                                        onclick="viewStudent('<?php echo $student['student_code']; ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn-action btn-edit" title="Edit"
                                                        onclick="editStudent('<?php echo $student['student_code']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-action btn-delete" title="Delete"
                                                        onclick="confirmDelete('<?php echo $student['student_code']; ?>', '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Add Student Modal -->
                <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header" style="background: linear-gradient(135deg, #52a347, #3d6e35); color: white;">
                                <h5 class="modal-title" id="addStudentModalLabel">
                                    <i class="fas fa-user-plus"></i> Add New Student
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="addStudentForm" method="POST" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Note:</strong> Student code will be auto-generated. Default password will be "student" + student_code (e.g., student25-00009)
                                    </div>

                                    <!-- Personal Information -->
                                    <h6 class="mb-3" style="color: #52a347; font-weight: bold;">
                                        <i class="fas fa-user"></i> Personal Information
                                    </h6>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="middle_name" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="middle_name" name="middle_name">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="gender" class="form-label">Gender *</label>
                                            <select class="form-select" id="gender" name="gender" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="birthdate" class="form-label">Birthdate *</label>
                                            <input type="date" class="form-control" id="birthdate" name="birthdate" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="status" class="form-label">Status *</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                    </div>

                                    <hr>

                                    <!-- Academic Information -->
                                    <h6 class="mb-3" style="color: #52a347; font-weight: bold;">
                                        <i class="fas fa-school"></i> Academic Information
                                    </h6>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="section_id" class="form-label">Assign to Section *</label>
                                            <select class="form-select" id="section_id" name="section_id" required>
                                                <option value="">Select Section</option>
                                                <?php
                                                // Fetch active sections
                                                try {
                                                    $stmt = $pdo->query("SELECT s.section_id, s.grade_level, s.section_name, s.current_enrollment, s.max_capacity 
                                                        FROM sections s 
                                                        WHERE s.is_active = 1 
                                                        ORDER BY s.grade_level, s.section_name");
                                                    $sections = $stmt->fetchAll();
                                                    foreach ($sections as $section) {
                                                        $available = $section['max_capacity'] - $section['current_enrollment'];
                                                        echo '<option value="' . $section['section_id'] . '">';
                                                        echo 'Grade ' . $section['grade_level'] . ' - ' . $section['section_name'];
                                                        echo ' (' . $available . ' slots available)';
                                                        echo '</option>';
                                                    }
                                                } catch (PDOException $e) {
                                                    echo '<option value="">Error loading sections</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="date_enrolled" class="form-label">Date Enrolled *</label>
                                            <input type="date" class="form-control" id="date_enrolled" name="date_enrolled" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>

                                    <hr>

                                    <!-- Parent/Guardian Information -->
                                    <h6 class="mb-3" style="color: #52a347; font-weight: bold;">
                                        <i class="fas fa-users"></i> Parent/Guardian Information
                                    </h6>

                                    <div class="mb-3">
                                        <label class="form-label">Select Existing Parent or Create New</label>
                                        <select class="form-select" id="parent_option" name="parent_option" onchange="toggleParentFields()">
                                            <option value="new">Create New Parent/Guardian</option>
                                            <option value="existing">Select Existing Parent/Guardian</option>
                                        </select>
                                    </div>

                                    <!-- Existing Parent Selection -->
                                    <div id="existingParentDiv" style="display: none;">
                                        <div class="mb-3">
                                            <label for="existing_parent_id" class="form-label">Select Parent/Guardian</label>
                                            <select class="form-select" id="existing_parent_id" name="existing_parent_id">
                                                <option value="">Select Parent/Guardian</option>
                                                <?php
                                                try {
                                                    $stmt = $pdo->query("SELECT parent_id, parent_code, first_name, last_name, relationship FROM parents ORDER BY last_name, first_name");
                                                    $parents = $stmt->fetchAll();
                                                    foreach ($parents as $parent) {
                                                        echo '<option value="' . $parent['parent_id'] . '">';
                                                        echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']);
                                                        echo ' (' . htmlspecialchars($parent['relationship']) . ') - ' . htmlspecialchars($parent['parent_code']);
                                                        echo '</option>';
                                                    }
                                                } catch (PDOException $e) {
                                                    echo '<option value="">Error loading parents</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- New Parent Fields -->
                                    <div id="newParentDiv">
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="parent_first_name" class="form-label">Parent First Name</label>
                                                <input type="text" class="form-control" id="parent_first_name" name="parent_first_name">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="parent_middle_name" class="form-label">Parent Middle Name</label>
                                                <input type="text" class="form-control" id="parent_middle_name" name="parent_middle_name">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="parent_last_name" class="form-label">Parent Last Name</label>
                                                <input type="text" class="form-control" id="parent_last_name" name="parent_last_name">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label for="parent_relationship" class="form-label">Relationship</label>
                                                <select class="form-select" id="parent_relationship" name="parent_relationship">
                                                    <option value="Mother">Mother</option>
                                                    <option value="Father">Father</option>
                                                    <option value="Guardian">Guardian</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="parent_email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="parent_email" name="parent_email">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="parent_contact" class="form-label">Contact Number</label>
                                                <input type="text" class="form-control" id="parent_contact" name="parent_contact">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="parent_address" class="form-label">Parent Address</label>
                                            <textarea class="form-control" id="parent_address" name="parent_address" rows="2"></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="parent_occupation" class="form-label">Occupation</label>
                                            <input type="text" class="form-control" id="parent_occupation" name="parent_occupation">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary" name="add_student">
                                        <i class="fas fa-save"></i> Add Student
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- View Student Modal -->
                <div class="modal fade" id="viewStudentModal" tabindex="-1" aria-labelledby="viewStudentModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header" style="background: linear-gradient(135deg, #52a347, #3d6e35); color: white;">
                                <h5 class="modal-title" id="viewStudentModalLabel">
                                    <i class="fas fa-eye"></i> View Student Details
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="viewStudentContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Edit Student Modal -->
                <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header" style="background: linear-gradient(135deg, #52a347, #3d6e35); color: white;">
                                <h5 class="modal-title" id="editStudentModalLabel">
                                    <i class="fas fa-edit"></i> Edit Student Information
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="editStudentForm" method="POST">
                                <div class="modal-body" id="editStudentContent">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-warning" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary" name="update_student" style="background: linear-gradient(135deg, #52a347, #3d6e35); border: none;">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        // Search functionality - removed auto-trigger, now only on Apply button
        function filterTable() {
            const searchCode = document.getElementById('searchCode').value.toLowerCase();
            const searchName = document.getElementById('searchName').value.toLowerCase();
            const gradeValue = document.getElementById('gradeFilter').value;
            const statusValue = document.getElementById('statusFilter').value;
            const genderValue = document.getElementById('genderFilter').value;

            const rows = document.querySelectorAll('#studentTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip "no data" row

                const studentCode = row.cells[0].textContent.toLowerCase();
                const studentName = row.cells[1].textContent.toLowerCase();
                const grade = row.getAttribute('data-grade');
                const status = row.getAttribute('data-status');
                const gender = row.cells[3].textContent;

                const matchesCode = searchCode === '' || studentCode.includes(searchCode);
                const matchesName = searchName === '' || studentName.includes(searchName);
                const matchesGrade = gradeValue === '' || grade === gradeValue;
                const matchesStatus = statusValue === '' || status === statusValue;
                const matchesGender = genderValue === '' || gender === genderValue;

                if (matchesCode && matchesName && matchesGrade && matchesStatus && matchesGender) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function resetFilters() {
            document.getElementById('searchCode').value = '';
            document.getElementById('searchName').value = '';
            document.getElementById('gradeFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('genderFilter').value = '';
            filterTable();
        }

        // Enhanced Delete Student Function with AJAX
        function confirmDelete(studentCode, studentName) {
            // Create a custom confirmation modal for better UX
            const confirmModal = `
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border: 3px solid #f44336;">
                    <div class="modal-header" style="background: linear-gradient(135deg, #f44336, #d32f2f); color: white;">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> Confirm Delete
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-user-times fa-4x text-danger mb-3"></i>
                            <h5>Are you absolutely sure?</h5>
                        </div>
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                        <p><strong>Student:</strong> ${studentName}</p>
                        <p><strong>Student Code:</strong> ${studentCode}</p>
                        <p class="mb-0">This will permanently delete:</p>
                        <ul>
                            <li>Student record</li>
                            <li>User account</li>
                            <li>Balance records</li>
                            <li>All associated data</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash"></i> Yes, Delete Student
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Remove existing modal if any
            const existingModal = document.getElementById('deleteConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', confirmModal);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();

            // Handle delete confirmation
            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                // Disable button and show loading
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

                // Send delete request via AJAX
                fetch('delete_student.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'student_code=' + encodeURIComponent(studentCode)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Close modal
                            modal.hide();

                            // Show success message
                            const successAlert = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin: 20px; position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="fas fa-check-circle"></i> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                            document.body.insertAdjacentHTML('beforeend', successAlert);

                            // Remove the student row from table with fade effect
                            const rows = document.querySelectorAll('#studentTable tbody tr');
                            rows.forEach(row => {
                                if (row.cells[0] && row.cells[0].textContent.trim() === studentCode) {
                                    row.style.transition = 'opacity 0.5s';
                                    row.style.opacity = '0';
                                    setTimeout(() => {
                                        row.remove();
                                        // Update statistics
                                        updateStatistics();
                                    }, 500);
                                }
                            });

                            // Auto-dismiss success message after 5 seconds
                            setTimeout(() => {
                                const alerts = document.querySelectorAll('.alert-success');
                                alerts.forEach(alert => {
                                    const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                                    bsAlert.close();
                                });
                            }, 5000);
                        } else {
                            // Show error message
                            alert('Error: ' + data.message);
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-trash"></i> Yes, Delete Student';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the student. Please try again.');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash"></i> Yes, Delete Student';
                    });
            });

            // Clean up modal when hidden
            document.getElementById('deleteConfirmModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Function to update statistics after deletion
        function updateStatistics() {
            const rows = document.querySelectorAll('#studentTable tbody tr');
            let totalStudents = 0;
            let activeStudents = 0;
            let inactiveStudents = 0;

            rows.forEach(row => {
                if (row.cells.length > 1) { // Skip "no data" row
                    totalStudents++;
                    const status = row.getAttribute('data-status');
                    if (status === 'active') activeStudents++;
                    else if (status === 'inactive') inactiveStudents++;
                }
            });

            // Update the statistics cards
            const statNumbers = document.querySelectorAll('.stat-number');
            if (statNumbers[0]) statNumbers[0].textContent = totalStudents;
            if (statNumbers[1]) statNumbers[1].textContent = activeStudents;
            if (statNumbers[2]) statNumbers[2].textContent = inactiveStudents;

            // Update table header count
            const tableHeader = document.querySelector('.table-header');
            if (tableHeader) {
                tableHeader.innerHTML = `<i class="fas fa-list"></i> Student List (${totalStudents} total)`;
            }

            // Show "no students" message if all deleted
            if (totalStudents === 0) {
                const tbody = document.querySelector('#studentTable tbody');
                tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No students found in the system.</p>
                </td>
            </tr>
        `;
            }
        }

        function toggleParentFields() {
            const parentOption = document.getElementById('parent_option').value;
            const existingParentDiv = document.getElementById('existingParentDiv');
            const newParentDiv = document.getElementById('newParentDiv');
            const existingParentSelect = document.getElementById('existing_parent_id');
            const newParentInputs = newParentDiv.querySelectorAll('input, select, textarea');

            if (parentOption === 'existing') {
                existingParentDiv.style.display = 'block';
                newParentDiv.style.display = 'none';
                existingParentSelect.required = true;
                newParentInputs.forEach(input => input.required = false);
            } else {
                existingParentDiv.style.display = 'none';
                newParentDiv.style.display = 'block';
                existingParentSelect.required = false;
                // Optional: make new parent fields required
            }
        }
        // Fixed Add Student Form Submission
        // Replace the existing addStudentForm event listener with this code

        document.addEventListener('DOMContentLoaded', function() {
            const addStudentForm = document.getElementById('addStudentForm');

            if (addStudentForm) {
                addStudentForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const firstName = document.getElementById('first_name').value;
                    const lastName = document.getElementById('last_name').value;
                    const sectionSelect = document.getElementById('section_id');
                    const section = sectionSelect.options[sectionSelect.selectedIndex].text;

                    // Validate required fields
                    if (!firstName || !lastName || !sectionSelect.value) {
                        alert('Please fill in all required fields (First Name, Last Name, and Section)');
                        return;
                    }

                    if (confirm(`Are you sure you want to add this student?\n\nName: ${firstName} ${lastName}\nSection: ${section}\n\nA user account will be created automatically.`)) {
                        // Show loading state
                        const submitBtn = this.querySelector('button[name="add_student"]');
                        const originalBtnText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Student...';

                        // Create FormData object
                        const formData = new FormData(this);

                        // Make sure add_student parameter is included
                        formData.append('add_student', '1');

                        // Submit via AJAX for better error handling
                        fetch('view_students.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                if (response.ok) {
                                    // Success - reload page to show new student
                                    window.location.reload();
                                } else {
                                    throw new Error('Server responded with an error');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error adding student. Please try again.');
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalBtnText;
                            });
                    }
                });
            }
        });

        // Toggle parent fields function (keep this as is)
        function toggleParentFields() {
            const parentOption = document.getElementById('parent_option').value;
            const existingParentDiv = document.getElementById('existingParentDiv');
            const newParentDiv = document.getElementById('newParentDiv');
            const existingParentSelect = document.getElementById('existing_parent_id');
            const newParentInputs = newParentDiv.querySelectorAll('input, select, textarea');

            if (parentOption === 'existing') {
                existingParentDiv.style.display = 'block';
                newParentDiv.style.display = 'none';
                existingParentSelect.required = false;
                newParentInputs.forEach(input => input.required = false);
            } else {
                existingParentDiv.style.display = 'none';
                newParentDiv.style.display = 'block';
                existingParentSelect.required = false;
                newParentInputs.forEach(input => input.required = false);
            }
        }
    </script>
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Add Student Form
            const addStudentForm = document.getElementById('addStudentForm');

            if (addStudentForm) {
                addStudentForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const firstName = document.getElementById('first_name').value;
                    const lastName = document.getElementById('last_name').value;
                    const sectionSelect = document.getElementById('section_id');
                    const section = sectionSelect.options[sectionSelect.selectedIndex].text;

                    // Validate required fields
                    if (!firstName || !lastName || !sectionSelect.value) {
                        alert('Please fill in all required fields (First Name, Last Name, and Section)');
                        return;
                    }

                    if (confirm(`Are you sure you want to add this student?\n\nName: ${firstName} ${lastName}\nSection: ${section}\n\nA user account will be created automatically.`)) {
                        // Show loading state
                        const submitBtn = this.querySelector('button[name="add_student"]');
                        const originalBtnText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Student...';

                        // Submit the form normally (not AJAX, since PHP handles redirects)
                        this.submit();
                    }
                });
            }
        });

        // Search/Filter functionality
        function filterTable() {
            const searchCode = document.getElementById('searchCode').value.toLowerCase();
            const searchName = document.getElementById('searchName').value.toLowerCase();
            const gradeValue = document.getElementById('gradeFilter').value;
            const statusValue = document.getElementById('statusFilter').value;
            const genderValue = document.getElementById('genderFilter').value;

            const rows = document.querySelectorAll('#studentTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip "no data" row

                const studentCode = row.cells[0].textContent.toLowerCase();
                const studentName = row.cells[1].textContent.toLowerCase();
                const grade = row.getAttribute('data-grade');
                const status = row.getAttribute('data-status');
                const gender = row.cells[3].textContent;

                const matchesCode = searchCode === '' || studentCode.includes(searchCode);
                const matchesName = searchName === '' || studentName.includes(searchName);
                const matchesGrade = gradeValue === '' || grade === gradeValue;
                const matchesStatus = statusValue === '' || status === statusValue;
                const matchesGender = genderValue === '' || gender === genderValue;

                if (matchesCode && matchesName && matchesGrade && matchesStatus && matchesGender) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function resetFilters() {
            document.getElementById('searchCode').value = '';
            document.getElementById('searchName').value = '';
            document.getElementById('gradeFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('genderFilter').value = '';
            filterTable();
        }

        // Toggle parent fields
        function toggleParentFields() {
            const parentOption = document.getElementById('parent_option').value;
            const existingParentDiv = document.getElementById('existingParentDiv');
            const newParentDiv = document.getElementById('newParentDiv');
            const existingParentSelect = document.getElementById('existing_parent_id');
            const newParentInputs = newParentDiv.querySelectorAll('input, select, textarea');

            if (parentOption === 'existing') {
                existingParentDiv.style.display = 'block';
                newParentDiv.style.display = 'none';
                existingParentSelect.required = false;
                newParentInputs.forEach(input => input.required = false);
            } else {
                existingParentDiv.style.display = 'none';
                newParentDiv.style.display = 'block';
                existingParentSelect.required = false;
                newParentInputs.forEach(input => input.required = false);
            }
        }

        // View Student Function
        function viewStudent(studentCode) {
            const modal = new bootstrap.Modal(document.getElementById('viewStudentModal'));
            modal.show();

            fetch('get_student_details.php?student_code=' + studentCode)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewStudentContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card" style="border: 2px solid #52a347;">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        ${data.student.profile_picture ? 
                                            `<img src="uploads/students/${data.student.profile_picture}" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #52a347;">` :
                                            `<div class="rounded-circle mx-auto" style="width: 120px; height: 120px; background: linear-gradient(135deg, #52a347, #3d6e35); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; font-weight: bold;">${data.student.first_name.charAt(0)}</div>`
                                        }
                                    </div>
                                    <h4 class="mb-1">${data.student.first_name} ${data.student.middle_name ? data.student.middle_name + ' ' : ''}${data.student.last_name}</h4>
                                    <p class="text-muted mb-2">${data.student.student_code}</p>
                                    <span class="badge bg-${data.student.status === 'active' ? 'success' : 'danger'} px-3 py-2">
                                        ${data.student.status.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header" style="background: #f8f9fa;">
                                    <i class="fas fa-user" style="color: #52a347;"></i> <strong>Personal Information</strong>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <td width="40%" class="text-muted"><i class="fas fa-venus-mars"></i> Gender:</td>
                                            <td><strong>${data.student.gender || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-birthday-cake"></i> Birthdate:</td>
                                            <td><strong>${data.student.birthdate || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-map-marker-alt"></i> Address:</td>
                                            <td><strong>${data.student.address || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-calendar-check"></i> Date Enrolled:</td>
                                            <td><strong>${data.student.date_enrolled || 'N/A'}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header" style="background: #f8f9fa;">
                                    <i class="fas fa-school" style="color: #52a347;"></i> <strong>Academic Information</strong>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <td width="40%" class="text-muted"><i class="fas fa-layer-group"></i> Grade Level:</td>
                                            <td><strong>${data.student.grade_level ? 'Grade ' + data.student.grade_level : 'Not Assigned'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-door-open"></i> Section:</td>
                                            <td><strong>${data.student.section_name || 'Not Assigned'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-user-tie"></i> Adviser:</td>
                                            <td><strong>${data.adviser || 'Not Assigned'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-id-card"></i> Username:</td>
                                            <td><strong>${data.student.username || 'N/A'}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header" style="background: #f8f9fa;">
                                    <i class="fas fa-users" style="color: #52a347;"></i> <strong>Parent/Guardian Information</strong>
                                </div>
                                <div class="card-body">
                                    ${data.parent ? `
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-borderless mb-0">
                                                    <tr>
                                                        <td width="40%" class="text-muted"><i class="fas fa-user"></i> Name:</td>
                                                        <td><strong>${data.parent.first_name} ${data.parent.last_name}</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted"><i class="fas fa-heart"></i> Relationship:</td>
                                                        <td><strong>${data.parent.relationship}</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted"><i class="fas fa-id-badge"></i> Parent Code:</td>
                                                        <td><strong>${data.parent.parent_code}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-borderless mb-0">
                                                    <tr>
                                                        <td width="40%" class="text-muted"><i class="fas fa-phone"></i> Contact:</td>
                                                        <td><strong>${data.parent.contact_number || 'N/A'}</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted"><i class="fas fa-envelope"></i> Email:</td>
                                                        <td><strong>${data.parent.email || 'N/A'}</strong></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="text-muted"><i class="fas fa-briefcase"></i> Occupation:</td>
                                                        <td><strong>${data.parent.occupation || 'N/A'}</strong></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    ` : '<p class="text-muted mb-0"><i class="fas fa-exclamation-circle"></i> No parent/guardian information available</p>'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                    } else {
                        document.getElementById('viewStudentContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error loading student details: ${data.message}
                    </div>
                `;
                    }
                })
                .catch(error => {
                    document.getElementById('viewStudentContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error loading student details. Please try again.
                </div>
            `;
                    console.error('Error:', error);
                });
        }

        // Edit Student Function
        function editStudent(studentCode) {
            const modal = new bootstrap.Modal(document.getElementById('editStudentModal'));
            modal.show();

            fetch('get_student_details.php?student_code=' + studentCode + '&for_edit=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editStudentContent').innerHTML = `
                    <input type="hidden" name="student_code" value="${data.student.student_code}">
                    <input type="hidden" name="user_id" value="${data.student.user_id}">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Student Code:</strong> ${data.student.student_code} (Cannot be changed)
                    </div>

                    <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                        <i class="fas fa-user"></i> Personal Information
                    </h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" value="${data.student.first_name}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name" value="${data.student.middle_name || ''}">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" value="${data.student.last_name}" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_gender" class="form-label">Gender *</label>
                            <select class="form-select" id="edit_gender" name="gender" required>
                                <option value="Male" ${data.student.gender === 'Male' ? 'selected' : ''}>Male</option>
                                <option value="Female" ${data.student.gender === 'Female' ? 'selected' : ''}>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_birthdate" class="form-label">Birthdate *</label>
                            <input type="date" class="form-control" id="edit_birthdate" name="birthdate" value="${data.student.birthdate || ''}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_status" class="form-label">Status *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active" ${data.student.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${data.student.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                <option value="graduated" ${data.student.status === 'graduated' ? 'selected' : ''}>Graduated</option>
                                <option value="transferred" ${data.student.status === 'transferred' ? 'selected' : ''}>Transferred</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2">${data.student.address || ''}</textarea>
                    </div>

                    <hr>

                    <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                        <i class="fas fa-school"></i> Academic Information
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_section_id" class="form-label">Section *</label>
                            <select class="form-select" id="edit_section_id" name="section_id" required>
                                ${data.sections.map(section => 
                                    `<option value="${section.section_id}" ${section.section_id == data.student.section_id ? 'selected' : ''}>
                                        Grade ${section.grade_level} - ${section.section_name}
                                    </option>`
                                ).join('')}
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_date_enrolled" class="form-label">Date Enrolled *</label>
                            <input type="date" class="form-control" id="edit_date_enrolled" name="date_enrolled" value="${data.student.date_enrolled || ''}" required>
                        </div>
                    </div>

                    <hr>

                    <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                        <i class="fas fa-users"></i> Parent/Guardian Information
                    </h6>
                    
                    ${data.parent ? `
                        <div class="alert alert-info">
                            <strong>Current Parent/Guardian:</strong> ${data.parent.first_name} ${data.parent.last_name} (${data.parent.relationship})
                            <br><small>To change parent/guardian, please contact system administrator.</small>
                        </div>
                    ` : '<div class="alert alert-warning">No parent/guardian assigned</div>'}
                `;

                        setTimeout(() => {
                            const editForm = document.getElementById('editStudentForm');
                            editForm.replaceWith(editForm.cloneNode(true));
                            const newForm = document.getElementById('editStudentForm');

                            newForm.addEventListener('submit', function(e) {
                                e.preventDefault();

                                const firstName = document.getElementById('edit_first_name').value;
                                const lastName = document.getElementById('edit_last_name').value;
                                const section = document.getElementById('edit_section_id').options[document.getElementById('edit_section_id').selectedIndex].text;

                                if (confirm(`Are you sure you want to save these changes?\n\nStudent: ${firstName} ${lastName}\nSection: ${section}`)) {
                                    const formData = new FormData(this);
                                    formData.append('update_student', '1');

                                    fetch('view_students.php', {
                                            method: 'POST',
                                            body: formData
                                        })
                                        .then(response => {
                                            if (response.ok) {
                                                window.location.reload();
                                            } else {
                                                alert('Error updating student. Please try again.');
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            alert('Error updating student. Please try again.');
                                        });
                                }
                            });
                        }, 100);
                    } else {
                        document.getElementById('editStudentContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error loading student details: ${data.message}
                    </div>
                `;
                    }
                })
                .catch(error => {
                    document.getElementById('editStudentContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error loading student details. Please try again.
                </div>
            `;
                    console.error('Error:', error);
                });
        }

        // Delete Student Function (with confirmation modal)
        function confirmDelete(studentCode, studentName) {
            const confirmModal = `
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border: 3px solid #f44336;">
                    <div class="modal-header" style="background: linear-gradient(135deg, #f44336, #d32f2f); color: white;">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> Confirm Delete
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-user-times fa-4x text-danger mb-3"></i>
                            <h5>Are you absolutely sure?</h5>
                        </div>
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action cannot be undone!
                        </div>
                        <p><strong>Student:</strong> ${studentName}</p>
                        <p><strong>Student Code:</strong> ${studentCode}</p>
                        <p class="mb-0">This will permanently delete:</p>
                        <ul>
                            <li>Student record</li>
                            <li>User account</li>
                            <li>Balance records</li>
                            <li>All associated data</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash"></i> Yes, Delete Student
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            const existingModal = document.getElementById('deleteConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }

            document.body.insertAdjacentHTML('beforeend', confirmModal);

            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();

            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

                fetch('delete_student.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'student_code=' + encodeURIComponent(studentCode)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            modal.hide();

                            const successAlert = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin: 20px; position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
                        <i class="fas fa-check-circle"></i> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                            document.body.insertAdjacentHTML('beforeend', successAlert);

                            const rows = document.querySelectorAll('#studentTable tbody tr');
                            rows.forEach(row => {
                                if (row.cells[0] && row.cells[0].textContent.trim() === studentCode) {
                                    row.style.transition = 'opacity 0.5s';
                                    row.style.opacity = '0';
                                    setTimeout(() => {
                                        row.remove();
                                        updateStatistics();
                                    }, 500);
                                }
                            });

                            setTimeout(() => {
                                const alerts = document.querySelectorAll('.alert-success');
                                alerts.forEach(alert => {
                                    const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                                    bsAlert.close();
                                });
                            }, 5000);
                        } else {
                            alert('Error: ' + data.message);
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-trash"></i> Yes, Delete Student';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the student. Please try again.');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash"></i> Yes, Delete Student';
                    });
            });

            document.getElementById('deleteConfirmModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        // Update statistics after deletion
        function updateStatistics() {
            const rows = document.querySelectorAll('#studentTable tbody tr');
            let totalStudents = 0;
            let activeStudents = 0;
            let inactiveStudents = 0;

            rows.forEach(row => {
                if (row.cells.length > 1) {
                    totalStudents++;
                    const status = row.getAttribute('data-status');
                    if (status === 'active') activeStudents++;
                    else if (status === 'inactive') inactiveStudents++;
                }
            });

            const statNumbers = document.querySelectorAll('.stat-number');
            if (statNumbers[0]) statNumbers[0].textContent = totalStudents;
            if (statNumbers[1]) statNumbers[1].textContent = activeStudents;
            if (statNumbers[2]) statNumbers[2].textContent = inactiveStudents;

            const tableHeader = document.querySelector('.table-header');
            if (tableHeader) {
                tableHeader.innerHTML = `<i class="fas fa-list"></i> Student List (${totalStudents} total)`;
            }

            if (totalStudents === 0) {
                const tbody = document.querySelector('#studentTable tbody');
                tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No students found in the system.</p>
                </td>
            </tr>
        `;
            }
        }
    </script>
</body>

</html>