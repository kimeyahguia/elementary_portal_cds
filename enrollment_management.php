<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'cdsportal';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$messageType = '';

// Approve enrollment
if (isset($_POST['approve_enrollment'])) {
    $student_id = $_POST['student_id'];
    try {
        $stmt = $pdo->prepare("UPDATE students SET status = 'active', approval_date = NOW() WHERE id = ?");
        $stmt->execute([$student_id]);
        $message = "Student enrollment approved successfully!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error approving enrollment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Reject enrollment
if (isset($_POST['reject_enrollment'])) {
    $student_id = $_POST['student_id'];
    $rejection_reason = $_POST['rejection_reason'];
    try {
        $stmt = $pdo->prepare("UPDATE students SET status = 'rejected', rejection_reason = ?, rejection_date = NOW() WHERE id = ?");
        $stmt->execute([$rejection_reason, $student_id]);
        $message = "Student enrollment rejected.";
        $messageType = "warning";
    } catch(PDOException $e) {
        $message = "Error rejecting enrollment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Approve appointment
if (isset($_POST['approve_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    $scheduled_date = $_POST['scheduled_date'];
    $scheduled_time = $_POST['scheduled_time'];
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'approved', scheduled_date = ?, scheduled_time = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$scheduled_date, $scheduled_time, $appointment_id]);
        $message = "Appointment approved and scheduled!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error approving appointment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch pending enrollments
$pendingEnrollments = [];
try {
    $stmt = $pdo->query("SELECT * FROM students WHERE status = 'pending' ORDER BY application_date DESC");
    $pendingEnrollments = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching pending enrollments: " . $e->getMessage());
}

// Fetch approved enrollments (recent)
$approvedEnrollments = [];
try {
    $stmt = $pdo->query("SELECT * FROM students WHERE status = 'active' ORDER BY approval_date DESC LIMIT 10");
    $approvedEnrollments = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching approved enrollments: " . $e->getMessage());
}

// Fetch appointments
$pendingAppointments = [];
try {
    $stmt = $pdo->query("SELECT * FROM appointments WHERE status = 'pending' ORDER BY request_date DESC");
    $pendingAppointments = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
}

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';

// Get statistics
$totalPending = count($pendingEnrollments);
$totalApproved = 0;
$totalRejected = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $result = $stmt->fetch();
    $totalApproved = $result ? (int)$result['total'] : 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM students WHERE status = 'rejected'");
    $result = $stmt->fetch();
    $totalRejected = $result ? (int)$result['total'] : 0;
} catch(PDOException $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - Creative Dreams</title>
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
            background: linear-gradient(135deg, #7cb342 0%, #689f38 100%);
            padding: 15px 30px;
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            color: rgba(255,255,255,0.9);
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
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .icon-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            background: linear-gradient(135deg, #7cb342, #689f38);
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
            background: #e8f5e9;
            color: #7cb342;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #7cb342, #689f38);
            color: white;
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
        }

        /* Main Content */
        .main-content {
            padding: 20px;
        }

        .page-title {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
            border-top: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }

        .stats-card.pending {
            border-top-color: #ff9800;
        }

        .stats-card.approved {
            border-top-color: #4caf50;
        }

        .stats-card.rejected {
            border-top-color: #f44336;
        }

        .stats-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stats-card.pending .icon {
            color: #ff9800;
        }

        .stats-card.approved .icon {
            color: #4caf50;
        }

        .stats-card.rejected .icon {
            color: #f44336;
        }

        .stats-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
        }

        .stats-card .label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: white;
            border: none;
            padding: 20px;
            font-weight: bold;
            font-size: 18px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: #7cb342;
        }

        .student-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }

        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #7cb342, #689f38);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .student-details h5 {
            margin: 0;
            color: #2c3e50;
            font-weight: bold;
        }

        .student-details p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3e0;
            color: #ff9800;
        }

        .status-approved {
            background: #e8f5e9;
            color: #4caf50;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 15px;
            color: #2c3e50;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-approve {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-approve:hover {
            background: #388e3c;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #f44336;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-reject:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .btn-view {
            background: #2196f3;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-view:hover {
            background: #1976d2;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h4 {
            color: #666;
            margin-bottom: 10px;
        }

        .tab-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-btn {
            padding: 12px 25px;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab-btn:hover {
            color: #7cb342;
        }

        .tab-btn.active {
            color: #7cb342;
            border-bottom-color: #7cb342;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }

            .student-header {
                flex-direction: column;
                gap: 15px;
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
                        <a href="enrollment_management.php" class="menu-item active">
                            <i class="fas fa-user-graduate"></i>
                            <span>ENROLLMENT</span>
                        </a>
                        <a href="fees_payment.php" class="menu-item">
                            <i class="fas fa-credit-card"></i>
                            <span>FEES & PAYMENT</span>
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
                        <i class="fas fa-user-graduate"></i> Enrollment Management
                    </h2>

                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-4 mb-3">
                            <div class="stats-card pending">
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="value"><?php echo $totalPending; ?></div>
                                <div class="label">Pending Applications</div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="stats-card approved">
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="value"><?php echo $totalApproved; ?></div>
                                <div class="label">Approved Enrollments</div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="stats-card rejected">
                                <div class="icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="value"><?php echo $totalRejected; ?></div>
                                <div class="label">Rejected Applications</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-btn active" onclick="showTab('pending')">
                                <i class="fas fa-clock"></i> Pending Applications (<?php echo $totalPending; ?>)
                            </button>
                            <button class="tab-btn" onclick="showTab('appointments')">
                                <i class="fas fa-calendar-check"></i> Appointments (<?php echo count($pendingAppointments); ?>)
                            </button>
                            <button class="tab-btn" onclick="showTab('approved')">
                                <i class="fas fa-check-circle"></i> Recently Approved
                            </button>
                        </div>

                        <!-- Pending Applications Tab -->
                        <div id="pending-tab" class="tab-content active">
                            <?php if (empty($pendingEnrollments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>No Pending Applications</h4>
                                    <p>All enrollment applications have been processed.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingEnrollments as $student): ?>
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="student-details">
                                                <h5><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></h5>
                                                <p><i class="fas fa-id-card"></i> Application #<?php echo str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock"></i> Pending Review
                                        </span>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['grade_level']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Date of Birth</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($student['date_of_birth'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Gender</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['gender']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Application Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($student['application_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Parent/Guardian</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['parent_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Contact Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['contact_number']); ?></span>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewDetails(<?php echo $student['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <button class="btn-approve" onclick="approveStudent(<?php echo $student['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn-reject" onclick="rejectStudent(<?php echo $student['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Appointments Tab -->
                        <div id="appointments-tab" class="tab-content">
                            <?php if (empty($pendingAppointments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar"></i>
                                    <h4>No Pending Appointments</h4>
                                    <p>There are no appointment requests at this time.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingAppointments as $appointment): ?>
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                            <div class="student-details">
                                                <h5><?php echo htmlspecialchars($appointment['parent_name']); ?></h5>
                                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock"></i> Pending Schedule
                                        </span>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Contact Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['contact_number']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Preferred Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['preferred_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Request Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['request_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Purpose</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['purpose']); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($appointment['message'])): ?>
                                    <div class="info-item mb-3">
                                        <span class="info-label">Message</span>
                                        <span class="info-value"><?php echo htmlspecialchars($appointment['message']); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="action-buttons">
                                        <button class="btn-approve" onclick="scheduleAppointment(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-calendar-check"></i> Schedule Appointment
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Approved Enrollments Tab -->
                        <div id="approved-tab" class="tab-content">
                            <?php if (empty($approvedEnrollments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-check"></i>
                                    <h4>No Approved Enrollments</h4>
                                    <p>Recently approved students will appear here.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($approvedEnrollments as $student): ?>
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                            <div class="student-details">
                                                <h5><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></h5>
                                                <p><i class="fas fa-id-badge"></i> Student ID: <?php echo str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge status-approved">
                                            <i class="fas fa-check-circle"></i> Approved
                                        </span>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['grade_level']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Approval Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($student['approval_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Section</span>
                                            <span class="info-value"><?php echo !empty($student['section']) ? htmlspecialchars($student['section']) : 'Not Assigned'; ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Parent/Guardian</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['parent_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Contact Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['contact_number']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Email</span>
                                            <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewDetails(<?php echo $student['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Full Profile
                                        </button>
                                        <button class="btn-view" onclick="printCOR(<?php echo $student['id']; ?>)">
                                            <i class="fas fa-print"></i> Print COR
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Student Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #7cb342, #689f38); color: white;">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Enrollment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="approve_student_id">
                        <p>Are you sure you want to approve this student's enrollment?</p>
                        <p class="text-muted">The student and parent will receive a confirmation notification.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_enrollment" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve Enrollment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Student Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: #f44336; color: white;">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="reject_student_id">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection *</label>
                            <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="4" required placeholder="Please provide a reason for rejecting this application..."></textarea>
                        </div>
                        <p class="text-muted">The parent will be notified of this decision.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reject_enrollment" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Schedule Appointment Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #7cb342, #689f38); color: white;">
                    <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Schedule Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="schedule_appointment_id">
                        <div class="mb-3">
                            <label for="scheduled_date" class="form-label">Appointment Date *</label>
                            <input type="date" class="form-control" name="scheduled_date" id="scheduled_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="scheduled_time" class="form-label">Appointment Time *</label>
                            <input type="time" class="form-control" name="scheduled_time" id="scheduled_time" required>
                        </div>
                        <p class="text-muted">The parent will receive a confirmation with the scheduled date and time.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_appointment" class="btn btn-success">
                            <i class="fas fa-check"></i> Confirm Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #7cb342, #689f38); color: white;">
                    <h5 class="modal-title"><i class="fas fa-user"></i> Student Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.closest('.tab-btn').classList.add('active');
        }

        // Approve student
        function approveStudent(studentId) {
            document.getElementById('approve_student_id').value = studentId;
            const modal = new bootstrap.Modal(document.getElementById('approveModal'));
            modal.show();
        }

        // Reject student
        function rejectStudent(studentId) {
            document.getElementById('reject_student_id').value = studentId;
            document.getElementById('rejection_reason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }

        // Schedule appointment
        function scheduleAppointment(appointmentId) {
            document.getElementById('schedule_appointment_id').value = appointmentId;
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('scheduled_date').min = today;
            
            const modal = new bootstrap.Modal(document.getElementById('scheduleModal'));
            modal.show();
        }

        // View student details
        function viewDetails(studentId) {
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
            
            // Fetch student details via AJAX (you'll need to create the endpoint)
            fetch(`get_student_details.php?id=${studentId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('studentDetailsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('studentDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Error loading student details.</div>';
                });
        }

        // Print Certificate of Registration
        function printCOR(studentId) {
            window.open(`print_cor.php?student_id=${studentId}`, '_blank');
        }

        // Animations
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

        document.querySelectorAll('.student-card, .stats-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>