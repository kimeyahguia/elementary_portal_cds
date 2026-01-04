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

// Fetch all teachers with their advisory section info and teaching load
$teachers = [];
try {
    $stmt = $pdo->query("
        SELECT 
            t.*,
            u.status as account_status,
            s.grade_level,
            s.section_name,
            (SELECT COUNT(DISTINCT gst.subject_code) 
             FROM section_schedules ss 
             JOIN grade_schedule_template gst ON ss.template_id = gst.template_id 
             WHERE ss.teacher_code = t.teacher_code 
             AND ss.is_active = 1) as subject_count,
            (SELECT COUNT(*) 
             FROM section_schedules ss 
             WHERE ss.teacher_code = t.teacher_code 
             AND ss.is_active = 1) as total_periods
        FROM teachers t
        LEFT JOIN users u ON t.user_id = u.user_id
        LEFT JOIN sections s ON t.teacher_code = s.adviser_code AND s.is_active = 1
        ORDER BY t.last_name, t.first_name
    ");
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
}
// Count statistics
$totalTeachers = count($teachers);
$activeTeachers = count(array_filter($teachers, function ($t) {
    return $t['status'] == 'active';
}));
$inactiveTeachers = count(array_filter($teachers, function ($t) {
    return $t['status'] != 'active';
}));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Teachers - Creative Dreams</title>
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
            /* Background gradient is kept from the original request */
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            min-height: 100vh;
        }

        .top-header {
            /* Updated header gradient: #7cb342 -> #5a9c4e, #689f38 -> #4a8240 */
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
            /* Updated title color: #2c3e50 -> #2d5a24 */
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-add-new {
            /* Updated button gradient: #7cb342 -> #52a347, #689f38 -> #3d6e35 */
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
            /* Updated stat number color: #7cb342 -> #52a347 */
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

        .teacher-table {
            width: 100%;
            margin: 0;
        }

        .teacher-table thead {
            background: #f5f5f5;
        }

        .teacher-table th {
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        .teacher-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .teacher-table tr:hover {
            background: #f9f9f9;
        }

        .teacher-code {
            font-weight: bold;
            /* Updated teacher code color: #7cb342 -> #52a347 */
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

        .status-on_leave {
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
            /* Updated no-profile-img gradient: #7cb342 -> #52a347, #689f38 -> #3d6e35 */
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
            /* Updated focus border/shadow color: #7cb342 -> #52a347 */
            border-color: #52a347;
            box-shadow: 0 0 0 0.2rem rgba(82, 163, 71, 0.25);
            outline: none;
        }

        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
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
                <div class="page-title">
                    <div>
                        <button class="btn btn-secondary me-3" onclick="window.location.href='manage_accounts.php'" style="padding: 10px 20px; border-radius: 8px;">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <i class="fas fa-chalkboard-teacher"></i> Teacher Management
                    </div>
                    <button class="btn-add-new" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-plus"></i> Add New Teacher
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $totalTeachers; ?></div>
                                <div class="stat-label">Total Teachers</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $activeTeachers; ?></div>
                                <div class="stat-label">Active Teachers</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $inactiveTeachers; ?></div>
                                <div class="stat-label">Inactive Teachers</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Bar -->
                <div class="search-filter-bar">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Search by Teacher Code</label>
                            <input type="text" class="form-control filter-input" id="searchCode" placeholder="Enter teacher code...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Search by Name</label>
                            <input type="text" class="form-control filter-input" id="searchName" placeholder="Enter teacher name...">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select filter-input" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Role</label>
                            <select class="form-select filter-input" id="roleFilter">
                                <option value="">All Roles</option>
                                <option value="adviser">Advisers</option>
                                <option value="subject">Subject Teachers</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Department</label>
                            <select class="form-select filter-input" id="departmentFilter">
                                <option value="">All Departments</option>
                                <option value="Elementary Department">Elementary</option>
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

                <!-- Teachers Table -->
                <div class="table-container">
                    <div class="table-header">
                        <i class="fas fa-list"></i> Teacher List (<?php echo $totalTeachers; ?> total)
                    </div>
                    <div class="table-responsive">
                        <table class="teacher-table" id="teacherTable">
                            <thead>
                                <tr>
                                    <th>Teacher Code</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Advisory Class</th>
                                    <th>Subjects</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teachers)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>No teachers found in the system.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr data-status="<?php echo htmlspecialchars($teacher['status']); ?>"
                                            data-role="<?php echo $teacher['grade_level'] ? 'adviser' : 'subject'; ?>">
                                            <td class="teacher-code"><?php echo htmlspecialchars($teacher['teacher_code']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                                <?php if ($teacher['middle_name']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($teacher['middle_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($teacher['position']); ?></td>
                                            <td>
                                                <?php if ($teacher['grade_level'] && $teacher['section_name']): ?>
                                                    <strong>Grade <?php echo htmlspecialchars($teacher['grade_level']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($teacher['section_name']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($teacher['subject_count'] > 0): ?>
                                                    <span class="badge-info"><?php echo $teacher['subject_count']; ?> subject<?php echo $teacher['subject_count'] > 1 ? 's' : ''; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($teacher['contact_number'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($teacher['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $teacher['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn-action btn-view" title="View Details"
                                                    onclick="viewTeacher('<?php echo $teacher['teacher_code']; ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action btn-edit" title="Edit"
                                                    onclick="editTeacher('<?php echo $teacher['teacher_code']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-action btn-delete" title="Delete"
                                                    onclick="confirmDelete('<?php echo $teacher['teacher_code']; ?>', '<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>')">
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
            <!-- Add Teacher Modal -->
            <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #52a347, #3d6e35); color: white;">
                            <h5 class="modal-title" id="addTeacherModalLabel">
                                <i class="fas fa-user-plus"></i> Add New Teacher
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="addTeacherForm" method="POST">
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> Teacher code will be auto-generated. Default password will be "teacher" + last 3 digits of teacher code (e.g., teacher001)
                                </div>

                                <!-- Personal Information -->
                                <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
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
                                        <label for="contact_number" class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" id="contact_number" name="contact_number">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="date_hired" class="form-label">Date Hired</label>
                                        <input type="date" class="form-control" id="date_hired" name="date_hired" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                </div>

                                <hr>

                                <!-- Professional Information -->
                                <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                                    <i class="fas fa-briefcase"></i> Professional Information
                                </h6>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="position" class="form-label">Position *</label>
                                        <input type="text" class="form-control" id="position" name="position" value="Teacher" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="department" class="form-label">Department *</label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="Elementary Department">Elementary Department</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="specialization" class="form-label">Specialization</label>
                                        <input type="text" class="form-control" id="specialization" name="specialization" placeholder="e.g., Mathematics, Science">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="on_leave">On Leave</option>
                                        </select>
                                    </div>
                                </div>

                                <hr>

                                <!-- Account Information -->
                                <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                                    <i class="fas fa-envelope"></i> Account Information
                                </h6>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email">
                                        <small class="text-muted">If not provided, system will generate based on teacher code</small>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-primary" name="add_teacher">
                                    <i class="fas fa-save"></i> Add Teacher
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- View Teacher Modal -->
            <div class="modal fade" id="viewTeacherModal" tabindex="-1" aria-labelledby="viewTeacherModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #52a347, #3d6e35); color: white;">
                            <h5 class="modal-title" id="viewTeacherModalLabel">
                                <i class="fas fa-eye"></i> View Teacher Details
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="viewTeacherContent">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status" style="color: #52a347 !important;">
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

            <!-- Edit Teacher Modal -->
            <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #52a347, #3d6e35); color: white;">
                            <h5 class="modal-title" id="editTeacherModalLabel">
                                <i class="fas fa-edit"></i> Edit Teacher Information
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="editTeacherForm" method="POST">
                            <div class="modal-body" id="editTeacherContent">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-warning" role="status" style="color: #52a347 !important;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-primary" name="update_teacher" style="background: linear-gradient(135deg, #52a347, #3d6e35); border: none;">
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
            const statusValue = document.getElementById('statusFilter').value;
            const roleValue = document.getElementById('roleFilter').value;
            const departmentValue = document.getElementById('departmentFilter').value;

            const rows = document.querySelectorAll('#teacherTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip "no data" row

                const teacherCode = row.cells[0].textContent.toLowerCase();
                const teacherName = row.cells[1].textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                const role = row.getAttribute('data-role');
                const department = row.cells[2].textContent;

                const matchesCode = searchCode === '' || teacherCode.includes(searchCode);
                const matchesName = searchName === '' || teacherName.includes(searchName);
                const matchesStatus = statusValue === '' || status === statusValue;
                const matchesRole = roleValue === '' || role === roleValue;
                const matchesDepartment = departmentValue === '' || department.includes(departmentValue);

                if (matchesCode && matchesName && matchesStatus && matchesRole && matchesDepartment) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function resetFilters() {
            document.getElementById('searchCode').value = '';
            document.getElementById('searchName').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('roleFilter').value = '';
            document.getElementById('departmentFilter').value = '';
            filterTable();
        }

        function confirmDelete(teacherCode, teacherName) {
            if (confirm(`Are you sure you want to delete ${teacherName} (${teacherCode})?\n\nThis action cannot be undone and will remove all associated assignments.`)) {
                // Will implement delete functionality later
                alert('Delete functionality - Coming Soon!');
            }
        }
    </script>
    <script>
        // Add confirmation before submitting add teacher form
        document.getElementById('addTeacherForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            const position = document.getElementById('position').value;

            if (confirm(`Are you sure you want to add this teacher?\n\nName: ${firstName} ${lastName}\nPosition: ${position}\n\nA user account will be created automatically.`)) {
                this.submit();
            }
        });

        // View Teacher Function
        function viewTeacher(teacherCode) {
            const modal = new bootstrap.Modal(document.getElementById('viewTeacherModal'));
            modal.show();

            // Fetch teacher details via AJAX
            fetch('get_teacher_details.php?teacher_code=' + teacherCode)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewTeacherContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card" style="border: 2px solid #52a347;">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        ${data.teacher.profile_photo ? 
                                            `<img src="${data.teacher.profile_photo}" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #52a347;">` :
                                            `<div class="rounded-circle mx-auto" style="width: 120px; height: 120px; background: linear-gradient(135deg, #52a347, #3d6e35); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; font-weight: bold;">${data.teacher.first_name.charAt(0)}</div>`
                                        }
                                    </div>
                                    <h4 class="mb-1">${data.teacher.first_name} ${data.teacher.middle_name ? data.teacher.middle_name + ' ' : ''}${data.teacher.last_name}</h4>
                                    <p class="text-muted mb-2">${data.teacher.teacher_code}</p>
                                    <span class="badge bg-${data.teacher.status === 'active' ? 'success' : 'danger'} px-3 py-2">
                                        ${data.teacher.status.toUpperCase().replace('_', ' ')}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header" style="background: #f8f9fa;">
                                    <i class="fas fa-user" style="color: #52a347;"></i> <strong>Personal Information</strong>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <td width="40%" class="text-muted"><i class="fas fa-venus-mars"></i> Gender:</td>
                                            <td><strong>${data.teacher.gender || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-phone"></i> Contact:</td>
                                            <td><strong>${data.teacher.contact_number || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-envelope"></i> Email:</td>
                                            <td><strong>${data.teacher.email || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-map-marker-alt"></i> Address:</td>
                                            <td><strong>${data.teacher.address || 'N/A'}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Professional Information -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header" style="background: #f8f9fa;">
                                    <i class="fas fa-briefcase" style="color: #52a347;"></i> <strong>Professional Information</strong>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <td width="40%" class="text-muted"><i class="fas fa-id-badge"></i> Employee ID:</td>
                                            <td><strong>${data.teacher.employee_id || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-user-tie"></i> Position:</td>
                                            <td><strong>${data.teacher.position || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-building"></i> Department:</td>
                                            <td><strong>${data.teacher.department || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-star"></i> Specialization:</td>
                                            <td><strong>${data.teacher.specialization || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-calendar-check"></i> Date Hired:</td>
                                            <td><strong>${data.teacher.date_hired || 'N/A'}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Teaching Assignment -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header" style="background: #f8f9fa;">
                                    <i class="fas fa-chalkboard-teacher" style="color: #52a347;"></i> <strong>Teaching Assignment</strong>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="mb-3"><i class="fas fa-door-open"></i> Advisory Class</h6>
                                            ${data.advisory ? `
                                                <p class="mb-1"><strong>Grade ${data.advisory.grade_level} - ${data.advisory.section_name}</strong></p>
                                                <p class="text-muted mb-0">Room: ${data.advisory.room_assignment || 'Not assigned'}</p>
                                            ` : '<p class="text-muted mb-0">No advisory class assigned</p>'}
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="mb-3"><i class="fas fa-book"></i> Subjects Teaching</h6>
                                            ${data.subjects && data.subjects.length > 0 ? `
                                                <ul class="list-unstyled mb-0">
                                                    ${data.subjects.map(subject => `
                                                        <li class="mb-2">
                                                            <span class="badge" style="background: #52a347;">${subject.subject_code}</span>
                                                            <strong>${subject.subject_name}</strong>
                                                            <small class="text-muted">(${subject.section_count} section${subject.section_count > 1 ? 's' : ''})</small>
                                                        </li>
                                                    `).join('')}
                                                </ul>
                                            ` : '<p class="text-muted mb-0">No subjects assigned</p>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="col-md-12 mt-3">
                            <div class="card">
                                <div class="card-header" style="background: #f8f9fa;">
                                    <i class="fas fa-user-circle" style="color: #52a347;"></i> <strong>Account Information</strong>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless mb-0">
                                        <tr>
                                            <td width="20%" class="text-muted"><i class="fas fa-key"></i> Username:</td>
                                            <td><strong>${data.teacher.username || 'N/A'}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted"><i class="fas fa-toggle-on"></i> Account Status:</td>
                                            <td><span class="badge bg-${data.teacher.account_status === 'active' ? 'success' : 'danger'}">${data.teacher.account_status ? data.teacher.account_status.toUpperCase() : 'N/A'}</span></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                    } else {
                        document.getElementById('viewTeacherContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error loading teacher details: ${data.message}
                    </div>
                `;
                    }
                })
                .catch(error => {
                    document.getElementById('viewTeacherContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error loading teacher details. Please try again.
                </div>
            `;
                    console.error('Error:', error);
                });
        }

        // Edit Teacher Function
        function editTeacher(teacherCode) {
            const modal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
            modal.show();

            // Fetch teacher details for editing via AJAX
            fetch('get_teacher_details.php?teacher_code=' + teacherCode + '&for_edit=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editTeacherContent').innerHTML = `
                    <input type="hidden" name="teacher_code" value="${data.teacher.teacher_code}">
                    <input type="hidden" name="user_id" value="${data.teacher.user_id}">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Teacher Code:</strong> ${data.teacher.teacher_code} (Cannot be changed)
                    </div>

                    <!-- Personal Information -->
                    <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                        <i class="fas fa-user"></i> Personal Information
                    </h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" value="${data.teacher.first_name}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="edit_middle_name" name="middle_name" value="${data.teacher.middle_name || ''}">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" value="${data.teacher.last_name}" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_gender" class="form-label">Gender *</label>
                            <select class="form-select" id="edit_gender" name="gender" required>
                                <option value="Male" ${data.teacher.gender === 'Male' ? 'selected' : ''}>Male</option>
                                <option value="Female" ${data.teacher.gender === 'Female' ? 'selected' : ''}>Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="edit_contact_number" name="contact_number" value="${data.teacher.contact_number || ''}">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" value="${data.teacher.email || ''}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2">${data.teacher.address || ''}</textarea>
                    </div>

                    <hr>

                    <!-- Professional Information -->
                    <h6 class="mb-3" style="color: #52a347; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                        <i class="fas fa-briefcase"></i> Professional Information
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_position" class="form-label">Position *</label>
                            <input type="text" class="form-control" id="edit_position" name="position" value="${data.teacher.position}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_department" class="form-label">Department *</label>
                            <select class="form-select" id="edit_department" name="department" required>
                                <option value="Elementary Department" ${data.teacher.department === 'Elementary Department' ? 'selected' : ''}>Elementary Department</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="edit_specialization" name="specialization" value="${data.teacher.specialization || ''}">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_status" class="form-label">Status *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active" ${data.teacher.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${data.teacher.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                <option value="on_leave" ${data.teacher.status === 'on_leave' ? 'selected' : ''}>On Leave</option>
                            </select>
                        </div>
                    </div>
                `;

                        // Add submit event listener
                        setTimeout(() => {
                            const editForm = document.getElementById('editTeacherForm');
                            editForm.replaceWith(editForm.cloneNode(true));
                            const newForm = document.getElementById('editTeacherForm');

                            newForm.addEventListener('submit', function(e) {
                                e.preventDefault();

                                const firstName = document.getElementById('edit_first_name').value;
                                const lastName = document.getElementById('edit_last_name').value;
                                const position = document.getElementById('edit_position').value;

                                if (confirm(`Are you sure you want to save these changes?\n\nTeacher: ${firstName} ${lastName}\nPosition: ${position}`)) {
                                    const formData = new FormData(this);

                                    fetch('view_teachers.php', {
                                            method: 'POST',
                                            body: formData
                                        })
                                        .then(response => {
                                            if (response.ok) {
                                                window.location.reload();
                                            } else {
                                                alert('Error updating teacher. Please try again.');
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            alert('Error updating teacher. Please try again.');
                                        });
                                }
                            });
                        }, 100);
                    } else {
                        document.getElementById('editTeacherContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error loading teacher details: ${data.message}
                    </div>
                `;
                    }
                })
                .catch(error => {
                    document.getElementById('editTeacherContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error loading teacher details. Please try again.
                </div>
            `;
                    console.error('Error:', error);
                });
        }

        function confirmDelete(teacherCode, teacherName) {
            if (confirm(`Are you sure you want to delete ${teacherName} (${teacherCode})?\n\nThis action cannot be undone and will remove all associated assignments.`)) {
                alert('Delete functionality - Coming Soon!');
            }
        }
    </script>
</body>

</html>