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
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, category, priority, target_audience, posted_by, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['content'],
                        $_POST['category'],
                        $_POST['priority'],
                        $_POST['target_audience'],
                        $_SESSION['user_id'] ?? 1
                    ]);
                    $_SESSION['success_message'] = "Announcement added successfully!";
                    break;
                    
                case 'edit':
                    $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, category = ?, priority = ?, target_audience = ? WHERE announcement_id = ?");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['content'],
                        $_POST['category'],
                        $_POST['priority'],
                        $_POST['target_audience'],
                        $_POST['announcement_id']
                    ]);
                    $_SESSION['success_message'] = "Announcement updated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = ?");
                    $stmt->execute([$_POST['announcement_id']]);
                    $_SESSION['success_message'] = "Announcement deleted successfully!";
                    break;
                    
                case 'toggle_status':
                    $stmt = $pdo->prepare("UPDATE announcements SET is_active = ? WHERE announcement_id = ?");
                    $stmt->execute([$_POST['is_active'], $_POST['announcement_id']]);
                    $_SESSION['success_message'] = "Announcement status updated!";
                    break;
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Fetch all announcements
$announcements = [];
try {
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY date_posted DESC");
    $announcements = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error_message'] = "Error fetching announcements: " . $e->getMessage();
}

// Get statistics
$totalAnnouncements = count($announcements);
$activeAnnouncements = count(array_filter($announcements, fn($a) => $a['is_active'] == 1));
$highPriority = count(array_filter($announcements, fn($a) => $a['priority'] == 'high'));

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement Management - Creative Dreams</title>
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
            /* Updated gradient to Forest Green */
            background: linear-gradient(135deg, #5a9c4e 0%, #4a8240 100%);
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
            /* Updated gradient */
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
            /* Updated text color */
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
            /* Updated hover colors */
            background: #e0f7fa;
            color: #52a347;
            transform: translateX(5px);
        }

        .menu-item.active {
            /* Updated active gradient */
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
            /* Updated darker title color */
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .info-card {
            background: white;
            padding: 30px 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
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
            /* Updated gradient strip */
            background: linear-gradient(135deg, #52a347, #3d6e35);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }

        .info-card .icon {
            font-size: 45px;
            margin-bottom: 15px;
            /* Updated icon color */
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
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .info-card .label {
            font-size: 12px;
            /* Updated label color */
            color: #52a347;
            font-weight: 600;
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
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-header i {
            font-size: 20px;
            /* Updated icon color */
            color: #52a347;
            margin-right: 10px;
        }

        .btn-primary {
            /* Updated button gradient */
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4a8240, #2d5a24);
            transform: translateY(-2px);
        }

        .announcement-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            /* Updated border color */
            border-left: 4px solid #52a347;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .announcement-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .announcement-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .announcement-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-event {
            background: #2196F3;
            color: white;
        }

        .badge-academic {
            background: #FF9800;
            color: white;
        }

        .badge-information {
            background: #9C27B0;
            color: white;
        }

        .badge-high {
            background: #f44336;
            color: white;
        }

        .badge-medium {
            background: #ffc107;
            color: #000;
        }

        .badge-low {
            background: #4caf50;
            color: white;
        }

        .badge-active {
            background: #4caf50;
            color: white;
        }

        .badge-inactive {
            background: #9e9e9e;
            color: white;
        }

        .announcement-content {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .announcement-actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 5px 15px;
            font-size: 12px;
            border-radius: 5px;
        }

        .btn-warning {
            background: #ffc107;
            border: none;
            color: #000;
        }

        .btn-danger {
            background: #f44336;
            border: none;
            color: white;
        }

        .btn-success {
            background: #4caf50;
            border: none;
            color: white;
        }

        .btn-secondary {
            background: #9e9e9e;
            border: none;
            color: white;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            /* Updated modal header gradient */
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 10px 15px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            /* Updated focus colors */
            border-color: #52a347;
            box-shadow: 0 0 0 0.2rem rgba(82, 163, 71, 0.25);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }
            
            .brand-text h1 {
                font-size: 20px;
            }

            .announcement-header {
                flex-direction: column;
            }

            .announcement-actions {
                flex-wrap: wrap;
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
                        <a href="request_appointment.php" class="menu-item">
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
                        <a href="announcement.php" class="menu-item active">
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
                        <i class="fas fa-bullhorn"></i> Announcement Management
                    </h2>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                                <h3>Total Announcements</h3>
                                <div class="value"><?php echo $totalAnnouncements; ?></div>
                                <div class="label">All Announcements</div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3>Active Announcements</h3>
                                <div class="value"><?php echo $activeAnnouncements; ?></div>
                                <div class="label">Currently Active</div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="info-card">
                                <div class="icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h3>High Priority</h3>
                                <div class="value"><?php echo $highPriority; ?></div>
                                <div class="label">Urgent Announcements</div>
                            </div>
                        </div>
                    </div>

                    <!-- Announcements List -->
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <i class="fas fa-list"></i>
                                ALL ANNOUNCEMENTS
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                                <i class="fas fa-plus"></i> Add New Announcement
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($announcements)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox" style="font-size: 60px; color: #ccc;"></i>
                                    <p class="mt-3 text-muted">No announcements yet. Create your first announcement!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($announcements as $announcement): ?>
                                    <div class="announcement-item">
                                        <div class="announcement-header">
                                            <div style="flex: 1;">
                                                <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                                <div class="announcement-meta">
                                                    <span class="badge badge-<?php echo strtolower($announcement['category']); ?>">
                                                        <?php echo htmlspecialchars($announcement['category']); ?>
                                                    </span>
                                                    <span class="badge badge-<?php echo strtolower($announcement['priority']); ?>">
                                                        <?php echo htmlspecialchars($announcement['priority']); ?> priority
                                                    </span>
                                                    <span class="badge badge-<?php echo $announcement['is_active'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $announcement['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                    <span class="text-muted" style="font-size: 12px;">
                                                        <i class="fas fa-users"></i> <?php echo htmlspecialchars($announcement['target_audience']); ?>
                                                    </span>
                                                    <span class="text-muted" style="font-size: 12px;">
                                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($announcement['date_posted'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="announcement-content">
                                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                        </div>
                                        <div class="announcement-actions">
                                            <button class="btn btn-warning btn-sm" onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['announcement_id']; ?>">
                                                <input type="hidden" name="is_active" value="<?php echo $announcement['is_active'] ? 0 : 1; ?>">
                                                <button type="submit" class="btn <?php echo $announcement['is_active'] ? 'btn-secondary' : 'btn-success'; ?> btn-sm">
                                                    <i class="fas fa-<?php echo $announcement['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    <?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['announcement_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
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

    <!-- Add Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Announcement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea class="form-control" name="content" rows="5" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" required>
                                    <option value="Event">Event</option>
                                    <option value="Academic">Academic</option>
                                    <option value="Information">Information</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-select" name="target_audience" required>
                                    <option value="all">All</option>
                                    <option value="students">Students</option>
                                    <option value="teachers">Teachers</option>
                                    <option value="parents">Parents</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Announcement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="announcement_id" id="edit_announcement_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea class="form-control" name="content" id="edit_content" rows="5" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" id="edit_category" required>
                                    <option value="Event">Event</option>
                                    <option value="Academic">Academic</option>
                                    <option value="Information">Information</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority" id="edit_priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-select" name="target_audience" id="edit_target_audience" required>
                                    <option value="all">All</option>
                                    <option value="students">Students</option>
                                    <option value="teachers">Teachers</option>
                                    <option value="parents">Parents</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editAnnouncement(announcement) {
            document.getElementById('edit_announcement_id').value = announcement.announcement_id;
            document.getElementById('edit_title').value = announcement.title;
            document.getElementById('edit_content').value = announcement.content;
            document.getElementById('edit_category').value = announcement.category;
            document.getElementById('edit_priority').value = announcement.priority;
            document.getElementById('edit_target_audience').value = announcement.target_audience;
            
            var editModal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
            editModal.show();
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

        document.querySelectorAll('.card, .info-card, .announcement-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>