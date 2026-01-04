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

// Function to get appointment count for a specific date
function getAppointmentCountByDate($pdo, $date) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as appointment_count FROM enrollment WHERE preferred_date = ?");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        return $result['appointment_count'];
    } catch (PDOException $e) {
        error_log("Error fetching appointment count: " . $e->getMessage());
        return 0;
    }
}

$message = '';
$messageType = '';

// Handle School Information Update
if (isset($_POST['update_school_info'])) {
    $school_name = $_POST['school_name'];
    $school_address = $_POST['school_address'];
    $school_phone = $_POST['school_phone'];
    $school_email = $_POST['school_email'];
    $mission = $_POST['mission'];
    $vision = $_POST['vision'];

    try {
        // Update individual settings
        $settings = [
            'school_name' => $school_name,
            'school_address' => $school_address,
            'school_contact' => $school_phone,
            'school_email' => $school_email,
            'school_mission' => $mission,
            'school_vision' => $vision
        ];

        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, last_updated = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }

        $message = "School information updated successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error updating school information: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle Academic Year Settings
if (isset($_POST['update_academic_year'])) {
    $current_sy = $_POST['current_sy'];
    $quarter = $_POST['quarter'];
    $enrollment_status = $_POST['enrollment_status'];

    try {
        // Update academic year settings
        $settings = [
            'current_school_year' => $current_sy,
            'current_quarter' => $quarter,
            'enrollment_status' => $enrollment_status
        ];

        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, last_updated = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }

        $message = "Academic year settings updated successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error updating academic year: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle Fee Structure Update
if (isset($_POST['update_fees'])) {
    $grade_level = $_POST['grade_level'];
    $tuition_fee = $_POST['tuition_fee'];
    $miscellaneous_fee = $_POST['miscellaneous_fee'];
    $books_fee = $_POST['books_fee'];
    $uniform_fee = $_POST['uniform_fee'];

    try {
        // Check if fee structure exists for this grade level
        $stmt = $pdo->prepare("SELECT id FROM fee_structure WHERE grade_level = ?");
        $stmt->execute([$grade_level]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE fee_structure SET 
                tuition_fee = ?, 
                miscellaneous_fee = ?, 
                books_fee = ?, 
                uniform_fee = ?,
                updated_at = NOW()
                WHERE grade_level = ?");
            $stmt->execute([$tuition_fee, $miscellaneous_fee, $books_fee, $uniform_fee, $grade_level]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO fee_structure 
                (grade_level, tuition_fee, miscellaneous_fee, books_fee, uniform_fee) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$grade_level, $tuition_fee, $miscellaneous_fee, $books_fee, $uniform_fee]);
        }

        $message = "Fee structure updated successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error updating fee structure: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch current school settings
$schoolSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $results = $stmt->fetchAll();

    // Convert to associative array for easy access
    foreach ($results as $row) {
        $schoolSettings[$row['setting_key']] = $row['setting_value'];
    }

    // Check if required settings exist, if not create them
    $requiredSettings = [
        'school_name' => 'Creative Dreams School',
        'school_address' => 'Nasugbu, Batangas',
        'school_contact' => '(02) 1234-5678',
        'school_email' => 'info@creativedreams.edu.ph',
        'current_school_year' => '2025-2026',
        'current_quarter' => '1st',
        'enrollment_status' => 'open',
        'school_mission' => '',
        'school_vision' => ''
    ];

    foreach ($requiredSettings as $key => $defaultValue) {
        if (!isset($schoolSettings[$key])) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            $stmt->execute([$key, $defaultValue, ucfirst(str_replace('_', ' ', $key))]);
            $schoolSettings[$key] = $defaultValue;
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching school settings: " . $e->getMessage());
}

// Fetch fee structures
$feeStructures = [];
try {
    $stmt = $pdo->query("SELECT * FROM fee_structure ORDER BY grade_level");
    $feeStructures = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching fee structures: " . $e->getMessage());
}

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';
$adminId = $_SESSION['admin_id'] ?? 1;

// Get current admin details
$currentAdmin = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$adminId]);
    $currentAdmin = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching admin details: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Creative Dreams</title>
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

        .main-content {
            padding: 20px;
        }

        .page-title {
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
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

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .btn-save {
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #52a347;
            box-shadow: 0 0 0 0.2rem rgba(82, 163, 71, 0.25);
        }

        .info-box {
            background: #e0f7fa;
            border-left: 4px solid #52a347;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box i {
            color: #52a347;
            margin-right: 10px;
        }

        .fee-structure-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .fee-structure-card:hover {
            border-color: #52a347;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .fee-header {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .fee-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .fee-item:last-child {
            border-bottom: none;
        }

        .fee-label {
            color: #666;
            font-weight: 500;
        }

        .fee-amount {
            color: #2d5a24;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .tab-buttons {
                flex-direction: column;
            }

            .tab-btn {
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
                        <a href="manage_sections.php" class="menu-item">
                            <i class="fas fa-door-open"></i>
                            <span>MANAGE SECTIONS</span>
                        </a>
                        <a href="announcement.php" class="menu-item">
                            <i class="fas fa-bullhorn"></i>
                            <span>ANNOUNCEMENT</span>
                        </a>
                        <a href="settings.php" class="menu-item active">
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
                        <i class="fas fa-cog"></i> System Settings
                    </h2>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Tab Navigation -->
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-btn active" onclick="showTab('school')">
                                <i class="fas fa-school"></i> School Information
                            </button>
                            <button class="tab-btn" onclick="showTab('academic')">
                                <i class="fas fa-calendar-alt"></i> Academic Year
                            </button>
                        </div>

                        <!-- School Information Tab -->
                        <div id="school-tab" class="tab-content active">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-school"></i> School Information
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="school_name" class="form-label">School Name *</label>
                                                <input type="text" class="form-control" name="school_name" id="school_name"
                                                    value="<?php echo htmlspecialchars($schoolSettings['school_name'] ?? 'Creative Dreams School'); ?>" required>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="school_email" class="form-label">School Email *</label>
                                                <input type="email" class="form-control" name="school_email" id="school_email"
                                                    value="<?php echo htmlspecialchars($schoolSettings['school_email'] ?? ''); ?>" required>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label for="school_address" class="form-label">School Address *</label>
                                                <textarea class="form-control" name="school_address" id="school_address" rows="2" required><?php echo htmlspecialchars($schoolSettings['school_address'] ?? ''); ?></textarea>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="school_phone" class="form-label">Contact Number *</label>
                                                <input type="text" class="form-control" name="school_phone" id="school_phone"
                                                    value="<?php echo htmlspecialchars($schoolSettings['school_contact'] ?? ''); ?>" required>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label for="mission" class="form-label">Mission Statement *</label>
                                                <textarea class="form-control" name="mission" id="mission" rows="3" required><?php echo htmlspecialchars($schoolSettings['school_mission'] ?? ''); ?></textarea>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label for="vision" class="form-label">Vision Statement *</label>
                                                <textarea class="form-control" name="vision" id="vision" rows="3" required><?php echo htmlspecialchars($schoolSettings['school_vision'] ?? ''); ?></textarea>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" name="update_school_info" class="btn-save">
                                                <i class="fas fa-save"></i> Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Academic Year Tab -->
                        <div id="academic-tab" class="tab-content">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-calendar-alt"></i> Academic Year Settings
                                </div>
                                <div class="card-body">
                                    <div class="info-box">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Important:</strong> Changing these settings will affect enrollment schedules and academic records.
                                    </div>

                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="current_sy" class="form-label">Current School Year *</label>
                                                <input type="text" class="form-control" name="current_sy" id="current_sy"
                                                    value="<?php echo htmlspecialchars($schoolSettings['current_school_year'] ?? '2025-2026'); ?>"
                                                    placeholder="2025-2026" required>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="quarter" class="form-label">Current Quarter *</label>
                                                <select class="form-select" name="quarter" id="quarter" required>
                                                    <option value="1st" <?php echo ($schoolSettings['current_quarter'] ?? '') == '1st' ? 'selected' : ''; ?>>1st Quarter</option>
                                                    <option value="2nd" <?php echo ($schoolSettings['current_quarter'] ?? '') == '2nd' ? 'selected' : ''; ?>>2nd Quarter</option>
                                                    <option value="3rd" <?php echo ($schoolSettings['current_quarter'] ?? '') == '3rd' ? 'selected' : ''; ?>>3rd Quarter</option>
                                                    <option value="4th" <?php echo ($schoolSettings['current_quarter'] ?? '') == '4th' ? 'selected' : ''; ?>>4th Quarter</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label for="enrollment_status" class="form-label">Enrollment Status *</label>
                                                <select class="form-select" name="enrollment_status" id="enrollment_status" required>
                                                    <option value="open" <?php echo ($schoolSettings['enrollment_status'] ?? '') == 'open' ? 'selected' : ''; ?>>Open</option>
                                                    <option value="closed" <?php echo ($schoolSettings['enrollment_status'] ?? '') == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                </select>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                                                    <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                                    <strong>Current Status:</strong>
                                                    Enrollment is currently <strong><?php echo strtoupper($schoolSettings['enrollment_status'] ?? 'OPEN'); ?></strong>
                                                    for School Year <strong><?php echo htmlspecialchars($schoolSettings['current_school_year'] ?? '2025-2026'); ?></strong> -
                                                    <strong><?php echo htmlspecialchars($schoolSettings['current_quarter'] ?? '1st'); ?> Quarter</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" name="update_academic_year" class="btn-save">
                                                <i class="fas fa-save"></i> Update Academic Settings
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
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.closest('.tab-btn').classList.add('active');
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

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

        document.querySelectorAll('.card, .fee-structure-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>