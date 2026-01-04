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

// Handle form submissions
$message = '';
$messageType = '';

// Process Document Request
if (isset($_POST['process_document_request'])) {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';

    try {
        // Kunin ang user_id ng admin mula sa users table gamit ang username
        $adminUsername = $_SESSION['admin_name'];
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$adminUsername]);
        $admin = $stmt->fetch();

        if ($admin && isset($admin['user_id'])) {
            $processed_by = $admin['user_id'];

            $stmt = $pdo->prepare("UPDATE document_requests SET status = ?, notes = ?, date_processed = NOW(), processed_by = ? WHERE request_id = ?");
            $stmt->execute([$status, $notes, $processed_by, $request_id]);

            $message = "Document request processed successfully!";
            $messageType = "success";
        } else {
            $message = "Error: Admin user not found in database!";
            $messageType = "danger";
        }
    } catch (PDOException $e) {
        $message = "Error processing document request: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Update Appointment Status - STRICT WORKFLOW
if (isset($_POST['update_appointment_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';

    try {
        // Check current status to enforce workflow
        $stmt = $pdo->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
        $stmt->execute([$appointment_id]);
        $currentAppointment = $stmt->fetch();

        if (!$currentAppointment) {
            throw new Exception("Appointment not found!");
        }

        $currentStatus = $currentAppointment['status'];

        // STRICT WORKFLOW ENFORCEMENT
        // Pending -> Approved or Cancelled ONLY
        if ($currentStatus === 'pending') {
            if ($status !== 'approved' && $status !== 'cancelled') {
                throw new Exception("Pending appointments can only be approved or cancelled!");
            }
        }

        // Approved -> Completed ONLY
        if ($currentStatus === 'approved') {
            if ($status !== 'completed') {
                throw new Exception("Approved appointments can only be marked as completed!");
            }
        }

        // Completed and Cancelled cannot be changed
        if ($currentStatus === 'completed') {
            throw new Exception("Completed appointments cannot be modified!");
        }

        if ($currentStatus === 'cancelled') {
            throw new Exception("Cancelled appointments cannot be modified!");
        }

        // Update appointment status
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = ? WHERE appointment_id = ?");
        $stmt->execute([$status, $notes, $appointment_id]);

        $statusMessages = [
            'approved' => 'Appointment approved successfully! You can now mark it as completed from the Approved tab.',
            'completed' => 'Appointment marked as completed successfully!',
            'cancelled' => 'Appointment cancelled successfully!'
        ];

        $message = $statusMessages[$status] ?? 'Appointment status updated successfully!';
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch data from database
// Fetch all appointments
$allAppointments = [];
try {
    $stmt = $pdo->query("SELECT * FROM appointments ORDER BY appointment_date DESC, appointment_time DESC");
    $allAppointments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
}

// Filter appointments by status
$pendingAppointments = array_filter($allAppointments, function ($a) {
    return $a['status'] === 'pending';
});
$approvedAppointments = array_filter($allAppointments, function ($a) {
    return $a['status'] === 'approved';
});
$completedAppointments = array_filter($allAppointments, function ($a) {
    return $a['status'] === 'completed';
});
$cancelledAppointments = array_filter($allAppointments, function ($a) {
    return $a['status'] === 'cancelled';
});

// Fetch all document requests
$allDocumentRequests = [];
try {
    $stmt = $pdo->query("SELECT * FROM document_requests ORDER BY date_requested DESC");
    $allDocumentRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching document requests: " . $e->getMessage());
}

// Filter document requests by status
$requestedDocuments = array_filter($allDocumentRequests, function ($dr) {
    return $dr['status'] === 'requested';
});
$processingDocuments = array_filter($allDocumentRequests, function ($dr) {
    return $dr['status'] === 'processing';
});
$claimedDocuments = array_filter($allDocumentRequests, function ($dr) {
    return $dr['status'] === 'claimed';
});

// Get statistics
$totalAppointments = count($allAppointments);
$pendingAppointmentsCount = count($pendingAppointments);
$approvedAppointmentsCount = count($approvedAppointments);
$completedAppointmentsCount = count($completedAppointments);
$cancelledAppointmentsCount = count($cancelledAppointments);

$totalDocumentRequests = count($allDocumentRequests);
$requestedDocumentsCount = count($requestedDocuments);
$processingDocumentsCount = count($processingDocuments);
$claimedDocumentsCount = count($claimedDocuments);

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';

// Helper function to get status icons
function getStatusIcon($status)
{
    $icons = [
        'pending' => 'clock',
        'approved' => 'check-circle',
        'completed' => 'calendar-check',
        'cancelled' => 'times-circle',
        'requested' => 'clock',
        'processing' => 'cog',
        'claimed' => 'check-circle'
    ];

    return $icons[$status] ?? 'circle';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request & Appointment Management - Creative Dreams School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        {
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
            background: #e6f1e6;
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s;
            border-top: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .stats-card.appointments {
            border-top-color: #2196f3;
        }

        .stats-card.documents {
            border-top-color: #ff9800;
        }

        .stats-card.pending {
            border-top-color: #ff9800;
        }

        .stats-card.approved {
            border-top-color: #2196f3;
        }

        .stats-card.completed {
            border-top-color: #52a347;
        }

        .stats-card.cancelled {
            border-top-color: #f44336;
        }

        .stats-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stats-card.appointments .icon {
            color: #2196f3;
        }

        .stats-card.documents .icon {
            color: #ff9800;
        }

        .stats-card.pending .icon {
            color: #ff9800;
        }

        .stats-card.approved .icon {
            color: #2196f3;
        }

        .stats-card.completed .icon {
            color: #52a347;
        }

        .stats-card.cancelled .icon {
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            color: #52a347;
        }

        .request-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .request-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .request-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .request-details h5 {
            margin: 0;
            color: #2c3e50;
            font-weight: bold;
        }

        .request-details p {
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
            background: #e3f2fd;
            color: #2196f3;
        }

        .status-completed {
            background: #e6f1e6;
            color: #52a347;
        }

        .status-cancelled {
            background: #ffebee;
            color: #f44336;
        }

        .status-requested {
            background: #fff3e0;
            color: #ff9800;
        }

        .status-processing {
            background: #e3f2fd;
            color: #2196f3;
        }

        .status-claimed {
            background: #e6f1e6;
            color: #52a347;
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
            flex-wrap: wrap;
        }

        .btn-approve {
            background: #2196f3;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-approve:hover {
            background: #1976d2;
            transform: translateY(-2px);
        }

        .btn-complete {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-complete:hover {
            background: #388e3c;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #f44336;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .btn-disabled {
            background: #9e9e9e;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: not-allowed;
            opacity: 0.6;
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
            flex-wrap: wrap;
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
            color: #52a347;
        }

        .tab-btn.active {
            color: #52a347;
            border-bottom-color: #52a347;
        }

        .appointment-status-tabs,
        .document-status-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .status-tab-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 8px;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-tab-btn:hover {
            background: #e0e0e0;
        }

        .status-tab-btn.active {
            background: #52a347;
            color: white;
        }

        .status-tab-btn .badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .status-tab-btn:not(.active) .badge {
            background: #666;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .appointment-status-section,
        .document-status-section {
            display: none;
        }

        .appointment-status-section.active,
        .document-status-section.active {
            display: block;
        }

        .no-actions-message {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            background: #f9f9f9;
            border-radius: 8px;
            margin-top: 10px;
        }

        .workflow-guide {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .workflow-guide h6 {
            color: #2d5a24;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .workflow-guide ul {
            margin: 0;
            padding-left: 20px;
            color: #666;
        }

        .workflow-guide li {
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .request-header {
                flex-direction: column;
                gap: 15px;
            }

            .tab-buttons,
            .appointment-status-tabs,
            .document-status-tabs {
                flex-direction: column;
            }

            .tab-btn,
            .status-tab-btn {
                width: 100%;
                text-align: left;
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
                        <a href="enrollment_management.php" class="menu-item">
                            <i class="fas fa-user-graduate"></i>
                            <span>ENROLLMENT</span>
                        </a>
                        <a href="request.php" class="menu-item active">
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
                    <h2 class="page-title">
                        <i class="fas fa-calendar-check"></i> Request & Appointment Management
                    </h2>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card appointments">
                                <div class="icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="value"><?php echo $totalAppointments; ?></div>
                                <div class="label">Total Appointments</div>
                            </div>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card pending">
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="value"><?php echo $pendingAppointmentsCount; ?></div>
                                <div class="label">Pending Appointments</div>
                            </div>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card approved">
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="value"><?php echo $approvedAppointmentsCount; ?></div>
                                <div class="label">Approved Appointments</div>
                            </div>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card documents">
                                <div class="icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="value"><?php echo $totalDocumentRequests; ?></div>
                                <div class="label">Document Requests</div>
                            </div>
                        </div>
                    </div>

                    <!-- Workflow Guide -->
                    <div class="workflow-guide">
                        <h6><i class="fas fa-info-circle"></i> Appointment Workflow Guide</h6>
                        <ul>
                            <li><strong>Pending:</strong> Can be <span style="color: #2196f3; font-weight: bold;">Approved</span> or <span style="color: #f44336; font-weight: bold;">Cancelled</span></li>
                            <li><strong>Approved:</strong> Can ONLY be <span style="color: #4caf50; font-weight: bold;">Marked as Complete</span></li>
                            <li><strong>Completed:</strong> No further actions available (Final state)</li>
                            <li><strong>Cancelled:</strong> No further actions available (Final state)</li>
                        </ul>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-btn active" onclick="showTab('appointments')">
                                <i class="fas fa-calendar-alt"></i> Appointments (<?php echo $totalAppointments; ?>)
                            </button>
                            <button class="tab-btn" onclick="showTab('documents')">
                                <i class="fas fa-file-alt"></i> Document Requests (<?php echo $totalDocumentRequests; ?>)
                            </button>
                        </div>

                        <!-- Appointments Tab -->
                        <div id="appointments-tab" class="tab-content active">
                            <!-- Appointment Status Tabs -->
                            <div class="appointment-status-tabs mb-4">
                                <button class="status-tab-btn active" onclick="showAppointmentStatus('pending')">
                                    <i class="fas fa-clock"></i> Pending
                                    <span class="badge"><?php echo $pendingAppointmentsCount; ?></span>
                                </button>
                                <button class="status-tab-btn" onclick="showAppointmentStatus('approved')">
                                    <i class="fas fa-check-circle"></i> Approved
                                    <span class="badge"><?php echo $approvedAppointmentsCount; ?></span>
                                </button>
                                <button class="status-tab-btn" onclick="showAppointmentStatus('completed')">
                                    <i class="fas fa-calendar-check"></i> Completed
                                    <span class="badge"><?php echo $completedAppointmentsCount; ?></span>
                                </button>
                                <button class="status-tab-btn" onclick="showAppointmentStatus('cancelled')">
                                    <i class="fas fa-times-circle"></i> Cancelled
                                    <span class="badge"><?php echo $cancelledAppointmentsCount; ?></span>
                                </button>
                            </div>

                            <!-- Pending Appointments -->
                            <div id="pending-appointments" class="appointment-status-section active">
                                <?php if (empty($pendingAppointments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-clock"></i>
                                        <h4>No Pending Appointments</h4>
                                        <p>There are no pending appointments at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($pendingAppointments as $appointment): ?>
                                        <div class="request-card">
                                            <div class="request-header">
                                                <div class="request-info">
                                                    <div class="request-avatar">
                                                        <i class="fas fa-calendar"></i>
                                                    </div>
                                                    <div class="request-details">
                                                        <h5><?php echo htmlspecialchars($appointment['appointment_type']); ?></h5>
                                                        <p><i class="fas fa-user-graduate"></i> Student Code: <?php echo htmlspecialchars($appointment['student_code']); ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                    <i class="fas fa-<?php echo getStatusIcon($appointment['status']); ?>"></i>
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </div>

                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="info-label">Appointment ID</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Date</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Time</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_time']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Type</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_type']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Created</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['created_at'])); ?></span>
                                                </div>
                                            </div>

                                            <?php if (!empty($appointment['notes'])): ?>
                                                <div class="info-item mb-3">
                                                    <span class="info-label">Notes</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="action-buttons">
                                                <button class="btn-approve" onclick="updateAppointmentStatus('<?php echo $appointment['appointment_id']; ?>', 'approved')">
                                                    <i class="fas fa-check-circle"></i> Approve
                                                </button>
                                                <button class="btn-cancel" onclick="updateAppointmentStatus('<?php echo $appointment['appointment_id']; ?>', 'cancelled')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Approved Appointments -->
                            <div id="approved-appointments" class="appointment-status-section">
                                <?php if (empty($approvedAppointments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <h4>No Approved Appointments</h4>
                                        <p>There are no approved appointments at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($approvedAppointments as $appointment): ?>
                                        <div class="request-card">
                                            <div class="request-header">
                                                <div class="request-info">
                                                    <div class="request-avatar">
                                                        <i class="fas fa-calendar-check"></i>
                                                    </div>
                                                    <div class="request-details">
                                                        <h5><?php echo htmlspecialchars($appointment['appointment_type']); ?></h5>
                                                        <p><i class="fas fa-user-graduate"></i> Student Code: <?php echo htmlspecialchars($appointment['student_code']); ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                    <i class="fas fa-<?php echo getStatusIcon($appointment['status']); ?>"></i>
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </div>

                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="info-label">Appointment ID</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Date</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Time</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_time']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Type</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_type']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Created</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['created_at'])); ?></span>
                                                </div>
                                            </div>

                                            <?php if (!empty($appointment['notes'])): ?>
                                                <div class="info-item mb-3">
                                                    <span class="info-label">Notes</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="action-buttons">
                                                <button class="btn-complete" onclick="updateAppointmentStatus('<?php echo $appointment['appointment_id']; ?>', 'completed')">
                                                    <i class="fas fa-calendar-check"></i> Mark Complete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Completed Appointments -->
                            <div id="completed-appointments" class="appointment-status-section">
                                <?php if (empty($completedAppointments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-check"></i>
                                        <h4>No Completed Appointments</h4>
                                        <p>There are no completed appointments at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($completedAppointments as $appointment): ?>
                                        <div class="request-card">
                                            <div class="request-header">
                                                <div class="request-info">
                                                    <div class="request-avatar">
                                                        <i class="fas fa-calendar-check"></i>
                                                    </div>
                                                    <div class="request-details">
                                                        <h5><?php echo htmlspecialchars($appointment['appointment_type']); ?></h5>
                                                        <p><i class="fas fa-user-graduate"></i> Student Code: <?php echo htmlspecialchars($appointment['student_code']); ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                    <i class="fas fa-<?php echo getStatusIcon($appointment['status']); ?>"></i>
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </div>

                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="info-label">Appointment ID</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Date</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Time</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_time']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Type</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_type']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Created</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['created_at'])); ?></span>
                                                </div>
                                            </div>

                                            <?php if (!empty($appointment['notes'])): ?>
                                                <div class="info-item mb-3">
                                                    <span class="info-label">Notes</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="no-actions-message">
                                                <i class="fas fa-check-circle"></i> This appointment has been completed. No further actions are available.
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Cancelled Appointments -->
                            <div id="cancelled-appointments" class="appointment-status-section">
                                <?php if (empty($cancelledAppointments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-times-circle"></i>
                                        <h4>No Cancelled Appointments</h4>
                                        <p>There are no cancelled appointments at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($cancelledAppointments as $appointment): ?>
                                        <div class="request-card">
                                            <div class="request-header">
                                                <div class="request-info">
                                                    <div class="request-avatar">
                                                        <i class="fas fa-calendar-times"></i>
                                                    </div>
                                                    <div class="request-details">
                                                        <h5><?php echo htmlspecialchars($appointment['appointment_type']); ?></h5>
                                                        <p><i class="fas fa-user-graduate"></i> Student Code: <?php echo htmlspecialchars($appointment['student_code']); ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                    <i class="fas fa-<?php echo getStatusIcon($appointment['status']); ?>"></i>
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </div>

                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="info-label">Appointment ID</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Date</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Time</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_time']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Type</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_type']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Created</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['created_at'])); ?></span>
                                                </div>
                                            </div>

                                            <?php if (!empty($appointment['notes'])): ?>
                                                <div class="info-item mb-3">
                                                    <span class="info-label">Notes</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="no-actions-message">
                                                <i class="fas fa-times-circle"></i> This appointment has been cancelled. No further actions are available.
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Document Requests Tab -->
                        <div id="documents-tab" class="tab-content">
                            <!-- Document Status Tabs -->
                            <div class="document-status-tabs mb-4">
                                <button class="status-tab-btn active" onclick="showDocumentStatus('requested')">
                                    <i class="fas fa-clock"></i> Requested
                                    <span class="badge"><?php echo $requestedDocumentsCount; ?></span>
                                </button>
                                <button class="status-tab-btn" onclick="showDocumentStatus('processing')">
                                    <i class="fas fa-cog"></i> Processing
                                    <span class="badge"><?php echo $processingDocumentsCount; ?></span>
                                </button>
                                <button class="status-tab-btn" onclick="showDocumentStatus('claimed')">
                                    <i class="fas fa-check-circle"></i> Claimed
                                    <span class="badge"><?php echo $claimedDocumentsCount; ?></span>
                                </button>
                            </div>

                            <!-- Requested Documents -->
                            <div id="requested-documents" class="document-status-section active">
                                <?php if (empty($requestedDocuments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-file-clock"></i>
                                        <h4>No Requested Documents</h4>
                                        <p>There are no requested documents at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($requestedDocuments as $request): ?>
                                        <div class="request-card">
                                            <div class="request-header">
                                                <div class="request-info">
                                                    <div class="request-avatar">
                                                        <i class="fas fa-file-alt"></i>
                                                    </div>
                                                    <div class="request-details">
                                                        <h5><?php echo htmlspecialchars($request['document_type']); ?></h5>
                                                        <p><i class="fas fa-user-graduate"></i> Student Code: <?php echo htmlspecialchars($request['student_code']); ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                                    <i class="fas fa-<?php echo getStatusIcon($request['status']); ?>"></i>
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </div>

                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="info-label">Request ID</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['request_id']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Document Type</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['document_type']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Purpose</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['purpose']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Copies</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['copies']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Requested</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($request['date_requested'])); ?></span>
                                                </div>
                                            </div>

                                            <?php if (!empty($request['notes'])): ?>
                                                <div class="info-item mb-3">
                                                    <span class="info-label">Notes</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="action-buttons">
                                                <button class="btn-approve" onclick="processDocumentRequest('<?php echo $request['request_id']; ?>', 'processing')">
                                                    <i class="fas fa-cog"></i> Start Processing
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Processing Documents -->
                            <div id="processing-documents" class="document-status-section">
                                <?php if (empty($processingDocuments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-cog"></i>
                                        <h4>No Processing Documents</h4>
                                        <p>There are no documents being processed at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($processingDocuments as $request): ?>
                                        <div class="request-card">
                                            <div class="request-header">
                                                <div class="request-info">
                                                    <div class="request-avatar">
                                                        <i class="fas fa-file-cog"></i>
                                                    </div>
                                                    <div class="request-details">
                                                        <h5><?php echo htmlspecialchars($request['document_type']); ?></h5>
                                                        <p><i class="fas fa-user-graduate"></i> Student Code: <?php echo htmlspecialchars($request['student_code']); ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                                    <i class="fas fa-<?php echo getStatusIcon($request['status']); ?>"></i>
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </div>

                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="info-label">Request ID</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['request_id']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Document Type</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['document_type']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Purpose</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['purpose']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Copies</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['copies']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Requested</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($request['date_requested'])); ?></span>
                                                </div>
                                            </div>

                                            <?php if (!empty($request['notes'])): ?>
                                                <div class="info-item mb-3">
                                                    <span class="info-label">Notes</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="action-buttons">
                                                <button class="btn-complete" onclick="processDocumentRequest('<?php echo $request['request_id']; ?>', 'claimed')">
                                                    <i class="fas fa-check-circle"></i> Mark as Complete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Claimed Documents -->
                            <div id="claimed-documents" class="document-status-section">
                                <?php if (empty($claimedDocuments)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-file-check"></i>
                                        <h4>No Claimed Documents</h4>
                                        <p>There are no claimed documents at this time.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($claimedDocuments as $request): ?>
                                        <div class="request-card">
                                            <div class="request-header">
                                                <div class="request-info">
                                                    <div class="request-avatar">
                                                        <i class="fas fa-file-check"></i>
                                                    </div>
                                                    <div class="request-details">
                                                        <h5><?php echo htmlspecialchars($request['document_type']); ?></h5>
                                                        <p><i class="fas fa-user-graduate"></i> Student Code: <?php echo htmlspecialchars($request['student_code']); ?></p>
                                                    </div>
                                                </div>
                                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                                    <i class="fas fa-<?php echo getStatusIcon($request['status']); ?>"></i>
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </div>

                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="info-label">Request ID</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['request_id']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Document Type</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['document_type']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Purpose</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['purpose']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Copies</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['copies']); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="info-label">Requested</span>
                                                    <span class="info-value"><?php echo date('M d, Y', strtotime($request['date_requested'])); ?></span>
                                                </div>
                                                <?php if ($request['date_processed']): ?>
                                                    <div class="info-item">
                                                        <span class="info-label">Processed</span>
                                                        <span class="info-value"><?php echo date('M d, Y', strtotime($request['date_processed'])); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($request['notes'])): ?>
                                                <div class="info-item mb-3">
                                                    <span class="info-label">Notes</span>
                                                    <span class="info-value"><?php echo htmlspecialchars($request['notes']); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="no-actions-message">
                                                <i class="fas fa-check-circle"></i> This document has been claimed by the student. No further actions are available.
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
    </div>

    <!-- Update Appointment Status Modal -->
    <div class="modal fade" id="updateAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2196f3, #1976d2); color: white;">
                    <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Update Appointment Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="update_appointment_id">
                        <input type="hidden" name="status" id="update_appointment_status">
                        <div class="mb-3">
                            <label for="appointment_notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" id="appointment_notes" rows="3" placeholder="Add any notes about this status change..."></textarea>
                        </div>
                        <p class="text-muted" id="appointment_status_message">Update the appointment status.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_appointment_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Process Document Request Modal -->
    <div class="modal fade" id="processDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #ff9800, #f57c00); color: white;">
                    <h5 class="modal-title"><i class="fas fa-file-alt"></i> Process Document Request</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="process_request_id">
                        <input type="hidden" name="status" id="process_request_status">
                        <div class="mb-3">
                            <label for="document_notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" id="document_notes" rows="3" placeholder="Add any notes about processing this document..."></textarea>
                        </div>
                        <p class="text-muted" id="document_status_message">Update the document request status.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="process_document_request" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
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

        // Show appointment status section
        function showAppointmentStatus(status) {
            // Hide all appointment status sections
            document.querySelectorAll('.appointment-status-section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all status buttons
            document.querySelectorAll('.appointment-status-tabs .status-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected status section
            document.getElementById(status + '-appointments').classList.add('active');

            // Add active class to clicked button
            event.target.closest('.status-tab-btn').classList.add('active');
        }

        // Show document status section
        function showDocumentStatus(status) {
            // Hide all document status sections
            document.querySelectorAll('.document-status-section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all status buttons
            document.querySelectorAll('.document-status-tabs .status-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected status section
            document.getElementById(status + '-documents').classList.add('active');

            // Add active class to clicked button
            event.target.closest('.status-tab-btn').classList.add('active');
        }

        // Update appointment status
        function updateAppointmentStatus(appointmentId, status) {
            document.getElementById('update_appointment_id').value = appointmentId;
            document.getElementById('update_appointment_status').value = status;
            document.getElementById('appointment_notes').value = '';

            // Set appropriate message based on status
            const statusMessages = {
                'approved': 'Approve this appointment. Once approved, you can mark it as completed from the Approved tab.',
                'completed': 'Mark this appointment as completed.',
                'cancelled': 'Cancel this appointment. This action cannot be undone.'
            };

            document.getElementById('appointment_status_message').textContent = statusMessages[status] || 'Update the appointment status.';

            const modal = new bootstrap.Modal(document.getElementById('updateAppointmentModal'));
            modal.show();
        }

        // Process document request
        function processDocumentRequest(requestId, status) {
            document.getElementById('process_request_id').value = requestId;
            document.getElementById('process_request_status').value = status;
            document.getElementById('document_notes').value = '';

            // Set appropriate message based on status
            const statusMessages = {
                'processing': 'Start processing this document request.',
                'claimed': 'Mark this document as complete/claimed by the student.'
            };

            document.getElementById('document_status_message').textContent = statusMessages[status] || 'Update the document request status.';

            const modal = new bootstrap.Modal(document.getElementById('processDocumentModal'));
            modal.show();
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

        document.querySelectorAll('.request-card, .stats-card').forEach(el => {
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