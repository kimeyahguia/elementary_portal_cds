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

// Get current admin information - join users and admin tables
$user_id = $_SESSION['user_id'] ?? null;
$adminInfo = [];

if ($user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, a.* 
            FROM users u 
            LEFT JOIN admin a ON u.user_id = a.user_id 
            WHERE u.user_id = ? AND u.role = 'admin'
        ");
        $stmt->execute([$user_id]);
        $adminInfo = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching admin info: " . $e->getMessage());
    }
}

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';

// Check if view_teachers parameter is set
$showTeachers = isset($_GET['view']) && $_GET['view'] === 'teachers';

// Check if my_account parameter is set
$showMyAccount = isset($_GET['view']) && $_GET['view'] === 'my_account';

// Check if manage_admins parameter is set
$showManageAdmins = isset($_GET['view']) && $_GET['view'] === 'manage_admins';

// Handle Update My Account
if (isset($_POST['update_my_account'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $address = $_POST['address'];
    
    // Check if password is being updated
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Verify current password if trying to change password - check against users table
        if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
            // All password fields must be filled
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = "All password fields are required to change password.";
                $messageType = "danger";
            } else {
                // Get current password from users table
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                $currentPasswordCorrect = false;
                
                if ($user) {
                    $stored_password = $user['password'];
                    
                    // Try multiple password verification methods
                    // Method 1: Direct comparison (plain text)
                    if ($current_password === $stored_password) {
                        $currentPasswordCorrect = true;
                    }
                    // Method 2: Password verify (for hashed passwords)
                    else if (password_verify($current_password, $stored_password)) {
                        $currentPasswordCorrect = true;
                    }
                    // Method 3: MD5 comparison
                    else if (md5($current_password) === $stored_password) {
                        $currentPasswordCorrect = true;
                    }
                    // Method 4: Try with trimmed spaces
                    else if (trim($current_password) === trim($stored_password)) {
                        $currentPasswordCorrect = true;
                    }
                }
                
                if ($currentPasswordCorrect) {
                    // Check if new password matches confirmation
                    if ($new_password === $confirm_password) {
                        // Start transaction
                        $pdo->beginTransaction();
                        
                        try {
                            // Update username and email in users table with new password
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?");
                            $stmt->execute([$username, $email, $hashed_password, $user_id]);
                            
                            // Check if admin record exists
                            $stmt = $pdo->prepare("SELECT admin_id FROM admin WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $adminExists = $stmt->fetch();
                            
                            if ($adminExists) {
                                // Update existing admin record
                                $stmt = $pdo->prepare("UPDATE admin SET full_name = ?, username = ?, email_address = ?, address = ? WHERE user_id = ?");
                                $stmt->execute([$full_name, $username, $email, $address, $user_id]);
                            } else {
                                // Insert new admin record
                                $stmt = $pdo->prepare("INSERT INTO admin (user_id, full_name, username, email_address, address, password) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$user_id, $full_name, $username, $email, $address, $hashed_password]);
                            }
                            
                            // Commit transaction
                            $pdo->commit();
                            
                            $message = "Account information and password updated successfully!";
                            $messageType = "success";
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $pdo->rollBack();
                            throw $e;
                        }
                    } else {
                        $message = "New password and confirmation do not match.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Current password is incorrect. Please check your current password.";
                    $messageType = "danger";
                }
            }
        } else {
            // Update without changing password
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Update username and email in users table
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$username, $email, $user_id]);
                
                // Check if admin record exists
                $stmt = $pdo->prepare("SELECT admin_id FROM admin WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $adminExists = $stmt->fetch();
                
                if ($adminExists) {
                    // Update existing admin record
                    $stmt = $pdo->prepare("UPDATE admin SET full_name = ?, username = ?, email_address = ?, address = ? WHERE user_id = ?");
                    $stmt->execute([$full_name, $username, $email, $address, $user_id]);
                } else {
                    // Insert new admin record
                    $stmt = $pdo->prepare("INSERT INTO admin (user_id, full_name, username, email_address, address, password) VALUES (?, ?, ?, ?, ?, ?)");
                    // Get current password from users table for the admin record
                    $stmtPass = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                    $stmtPass->execute([$user_id]);
                    $userPass = $stmtPass->fetch();
                    $stmt->execute([$user_id, $full_name, $username, $email, $address, $userPass['password']]);
                }
                
                // Commit transaction
                $pdo->commit();
                
                $message = "Account information updated successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                throw $e;
            }
        }
        
        // Update session variables
        $_SESSION['admin_name'] = $full_name;
        $adminName = $full_name;
        
        // Refresh admin info
        $stmt = $pdo->prepare("
            SELECT u.*, a.* 
            FROM users u 
            LEFT JOIN admin a ON u.user_id = a.user_id 
            WHERE u.user_id = ? AND u.role = 'admin'
        ");
        $stmt->execute([$user_id]);
        $adminInfo = $stmt->fetch();
        
        $showMyAccount = true;
    } catch (PDOException $e) {
        $message = "Error updating account: " . $e->getMessage();
        $messageType = "danger";
        $showMyAccount = true;
    }
}

// Handle Add Teacher Account
if (isset($_POST['add_teacher'])) {
    $teacher_code = $_POST['teacher_code'];
    $first_name = $_POST['teacher_first_name'];
    $middle_name = $_POST['teacher_middle_name'];
    $last_name = $_POST['teacher_last_name'];
    $department = 'Elementary Department'; // Fixed to Elementary Department only
    $position = $_POST['teacher_position'];
    $contact_number = $_POST['teacher_contact'];
    $address = $_POST['teacher_address'];
    $date_hired = $_POST['teacher_date_hired'];

    try {
        // Generate username and email
        $username = strtolower($teacher_code); // Use teacher code as username
        $email = $username . '@cds.edu.ph'; // Fixed domain
        
        // Generate default password based on teacher code (e.g., TC001 → teacher1)
        $password_base = strtolower($teacher_code); // Convert to lowercase
        $default_password = $password_base . '1'; // Append '1' to create default password
        $password = password_hash($default_password, PASSWORD_DEFAULT);
        
        // Insert into users table first
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'teacher', 'active')");
        $stmt->execute([$username, $password, $email]);
        $user_id = $pdo->lastInsertId();
        
        // Then insert into teachers table
        $stmt = $pdo->prepare("INSERT INTO teachers 
            (user_id, teacher_code, first_name, middle_name, last_name, department, position, contact_number, address, date_hired, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$user_id, $teacher_code, $first_name, $middle_name, $last_name, $department, $position, $contact_number, $address, $date_hired]);

        $message = "Teacher account created successfully! Username: $username, Default Password: $default_password";
        $messageType = "success";
        $showTeachers = true; // Show teachers section after adding
        
        // Refresh teachers list immediately
        $stmt = $pdo->query("SELECT * FROM teachers ORDER BY last_name, first_name");
        $teachers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Error creating teacher account: " . $e->getMessage();
        $messageType = "danger";
        $showTeachers = true; // Show teachers section even if error
    }
}

// Handle Update Teacher Account
if (isset($_POST['update_teacher'])) {
    $teacher_id = $_POST['teacher_id'];
    $teacher_code = $_POST['teacher_code'];
    $first_name = $_POST['teacher_first_name'];
    $middle_name = $_POST['teacher_middle_name'];
    $last_name = $_POST['teacher_last_name'];
    $department = 'Elementary Department'; // Fixed to Elementary Department only
    $position = $_POST['teacher_position'];
    $contact_number = $_POST['teacher_contact'];
    $address = $_POST['teacher_address'];
    $date_hired = $_POST['teacher_date_hired'];
    $status = $_POST['teacher_status'];

    try {
        $stmt = $pdo->prepare("UPDATE teachers SET 
            teacher_code = ?, first_name = ?, middle_name = ?, last_name = ?, 
            department = ?, position = ?, contact_number = ?, address = ?, 
            date_hired = ?, status = ? 
            WHERE teacher_id = ?");
        $stmt->execute([$teacher_code, $first_name, $middle_name, $last_name, $department, $position, $contact_number, $address, $date_hired, $status, $teacher_id]);

        $message = "Teacher account updated successfully!";
        $messageType = "success";
        $showTeachers = true;
        
        // Refresh teachers list immediately
        $stmt = $pdo->query("SELECT * FROM teachers ORDER BY last_name, first_name");
        $teachers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Error updating teacher account: " . $e->getMessage();
        $messageType = "danger";
        $showTeachers = true;
    }
}

// Handle Delete Teacher Account
if (isset($_GET['delete_teacher'])) {
    $teacher_id = $_GET['delete_teacher'];
    
    try {
        // First get the user_id to delete from users table
        $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $teacher = $stmt->fetch();
        
        if ($teacher) {
            // Delete from teachers table (this should cascade to users table due to foreign key constraint)
            $stmt = $pdo->prepare("DELETE FROM teachers WHERE teacher_id = ?");
            $stmt->execute([$teacher_id]);
            
            $message = "Teacher account deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Teacher not found!";
            $messageType = "danger";
        }
        $showTeachers = true;
        
        // Refresh teachers list immediately
        $stmt = $pdo->query("SELECT * FROM teachers ORDER BY last_name, first_name");
        $teachers = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message = "Error deleting teacher account: " . $e->getMessage();
        $messageType = "danger";
        $showTeachers = true;
    }
}

// Handle Add Admin Account
if (isset($_POST['add_admin'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $address = $_POST['address'];

    try {
        // Generate default password based on username (e.g., admin1 → admin11)
        $default_password = $username . '1';
        $password = password_hash($default_password, PASSWORD_DEFAULT);
        
        // Insert into users table first
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->execute([$username, $password, $email]);
        $new_user_id = $pdo->lastInsertId();
        
        // Then insert into admin table
        $stmt = $pdo->prepare("INSERT INTO admin 
            (user_id, full_name, username, email_address, address, password) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$new_user_id, $full_name, $username, $email, $address, $password]);

        $message = "Admin account created successfully! Username: $username, Default Password: $default_password";
        $messageType = "success";
        $showManageAdmins = true;
        
        // Refresh admin list immediately after adding
        $stmt = $pdo->prepare("
            SELECT a.*, u.status 
            FROM admin a 
            JOIN users u ON a.user_id = u.user_id 
            WHERE u.role = 'admin' AND a.user_id != ?
            ORDER BY a.full_name
        ");
        $stmt->execute([$user_id]);
        $admins = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $message = "Error creating admin account: " . $e->getMessage();
        $messageType = "danger";
        $showManageAdmins = true;
    }
}

// Handle Update Admin Account
if (isset($_POST['update_admin'])) {
    $admin_id = $_POST['admin_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $address = $_POST['address'];
    $status = $_POST['status'];

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get user_id from admin table
        $stmt = $pdo->prepare("SELECT user_id FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            $user_id_to_update = $admin['user_id'];
            
            // Update users table
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE user_id = ?");
            $stmt->execute([$username, $email, $status, $user_id_to_update]);
            
            // Update admin table
            $stmt = $pdo->prepare("UPDATE admin SET 
                full_name = ?, username = ?, email_address = ?, address = ? 
                WHERE admin_id = ?");
            $stmt->execute([$full_name, $username, $email, $address, $admin_id]);
            
            $pdo->commit();
            $message = "Admin account updated successfully!";
            $messageType = "success";
            
            // Refresh admin list immediately after update
            $stmt = $pdo->prepare("
                SELECT a.*, u.status 
                FROM admin a 
                JOIN users u ON a.user_id = u.user_id 
                WHERE u.role = 'admin' AND a.user_id != ?
                ORDER BY a.full_name
            ");
            $stmt->execute([$user_id]);
            $admins = $stmt->fetchAll();
            
        } else {
            $message = "Admin account not found!";
            $messageType = "danger";
        }
        $showManageAdmins = true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error updating admin account: " . $e->getMessage();
        $messageType = "danger";
        $showManageAdmins = true;
    }
}

// Handle Delete Admin Account
if (isset($_GET['delete_admin'])) {
    $admin_id = $_GET['delete_admin'];
    
    try {
        // Get user_id from admin table
        $stmt = $pdo->prepare("SELECT user_id FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            $user_id_to_delete = $admin['user_id'];
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Delete from admin table
            $stmt = $pdo->prepare("DELETE FROM admin WHERE admin_id = ?");
            $stmt->execute([$admin_id]);
            
            // Delete from users table
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id_to_delete]);
            
            $pdo->commit();
            $message = "Admin account deleted successfully!";
            $messageType = "success";
            
            // Refresh admin list immediately after delete
            $stmt = $pdo->prepare("
                SELECT a.*, u.status 
                FROM admin a 
                JOIN users u ON a.user_id = u.user_id 
                WHERE u.role = 'admin' AND a.user_id != ?
                ORDER BY a.full_name
            ");
            $stmt->execute([$user_id]);
            $admins = $stmt->fetchAll();
            
        } else {
            $message = "Admin account not found!";
            $messageType = "danger";
        }
        $showManageAdmins = true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error deleting admin account: " . $e->getMessage();
        $messageType = "danger";
        $showManageAdmins = true;
    }
}

// Fetch teacher data for editing
$edit_teacher = null;
if (isset($_GET['edit_teacher'])) {
    $teacher_id = $_GET['edit_teacher'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_id = ?");
        $stmt->execute([$teacher_id]);
        $edit_teacher = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching teacher for edit: " . $e->getMessage());
    }
}

// Fetch admin data for editing
$edit_admin = null;
if (isset($_GET['edit_admin'])) {
    $admin_id = $_GET['edit_admin'];
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.status 
            FROM admin a 
            JOIN users u ON a.user_id = u.user_id 
            WHERE a.admin_id = ?
        ");
        $stmt->execute([$admin_id]);
        $edit_admin = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching admin for edit: " . $e->getMessage());
    }
}

// Fetch all teachers
$teachers = [];
try {
    $stmt = $pdo->query("SELECT * FROM teachers ORDER BY last_name, first_name");
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
}

// Fetch all admin accounts (excluding current admin)
$admins = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.status 
        FROM admin a 
        JOIN users u ON a.user_id = u.user_id 
        WHERE u.role = 'admin' AND a.user_id != ?
        ORDER BY a.full_name
    ");
    $stmt->execute([$user_id]);
    $admins = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching admins: " . $e->getMessage());
}

// Quick Stats
$activeStudents = 0;
$activeTeachers = 0;
$activeSections = 0;
$activeAdmins = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
    $activeStudents = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teachers WHERE status = 'active'");
    $activeTeachers = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sections WHERE is_active = 1");
    $activeSections = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
    $activeAdmins = $stmt->fetch()['count'];
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - Creative Dreams School</title>
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            background: white;
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

        .action-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px6px rgba(0, 0, 0, 0.1);
            height: 100%;
        }

        .action-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .action-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
            color: white;
        }

        .action-title {
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .action-description {
            color: #666;
            font-size: 14px;
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

        .btn-primary {
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .info-box {
            background: #e8f5e9;
            border-left: 4px solid #52a347;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .user-table {
            width: 100%;
        }

        .user-table th {
            background: #f5f5f5;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        .user-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .user-table tr:hover {
            background: #f9f9f9;
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
            background: #e0f7fa;
            color: #2d5a24;
        }

        .status-inactive {
            background: #ffebee;
            color: #f44336;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 2px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-edit {
            background: #2196f3;
            color: white;
        }

        .btn-edit:hover {
            background: #1976d2;
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #d32f2f;
        }

        .back-btn {
            background: #6c757d;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .stats-row {
            margin-bottom: 30px;
        }

        .department-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        /* Custom Modal Styles */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            align-items: center;
            justify-content: center;
        }

        .custom-modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .custom-modal-icon {
            font-size: 48px;
            color: #f44336;
            margin-bottom: 20px;
        }

        .custom-modal-title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .custom-modal-message {
            color: #666;
            margin-bottom: 25px;
            font-size: 16px;
        }

        .custom-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .custom-modal-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .custom-modal-btn-confirm {
            background: #f44336;
            color: white;
        }

        .custom-modal-btn-confirm:hover {
            background: #d32f2f;
        }

        .custom-modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .custom-modal-btn-cancel:hover {
            background: #5a6268;
        }

        .role-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            display: inline-block;
        }

        .role-admin {
            background: #e3f2fd;
            color: #1976d2;
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

    <!-- Custom Delete Confirmation Modal -->
    <div class="custom-modal" id="deleteModal">
        <div class="custom-modal-content">
            <div class="custom-modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="custom-modal-title">Confirm Delete</div>
            <div class="custom-modal-message" id="deleteModalMessage">
                Are you sure you want to delete this account? This action cannot be undone.
            </div>
            <div class="custom-modal-buttons">
                <button class="custom-modal-btn custom-modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="custom-modal-btn custom-modal-btn-confirm" id="confirmDeleteBtn">Delete</button>
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
                    <?php if ($showTeachers): ?>
                        <!-- Back Button -->
                        <button class="back-btn" onclick="window.location.href='manage_accounts.php'">
                            <i class="fas fa-arrow-left"></i> Back to Manage Accounts
                        </button>
                        
                        <h2 class="page-title">
                            <i class="fas fa-chalkboard-teacher"></i> Manage Teacher Accounts
                        </h2>

                        <?php if (isset($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Department Info -->
                        <div class="department-info">
                            <i class="fas fa-info-circle text-primary"></i>
                            <strong>Elementary Department Only:</strong> This system is dedicated to managing elementary level teachers and classes.
                        </div>

                        <!-- Add/Edit Teacher Section -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-chalkboard-teacher"></i> 
                                        <?php echo $edit_teacher ? 'Edit Teacher Account' : 'Add New Teacher Account'; ?>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <?php if ($edit_teacher): ?>
                                                <input type="hidden" name="teacher_id" value="<?php echo $edit_teacher['teacher_id']; ?>">
                                            <?php endif; ?>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="teacher_code" class="form-label">Teacher Code *</label>
                                                    <input type="text" class="form-control" name="teacher_code" id="teacher_code" 
                                                           value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['teacher_code']) : ''; ?>" 
                                                           required placeholder="e.g. TC001">
                                                    <small class="text-muted">This will be used as username and for email generation</small>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="teacher_department" class="form-label">Department *</label>
                                                    <input type="text" class="form-control" value="Elementary Department" readonly>
                                                    <input type="hidden" name="teacher_department" value="Elementary Department">
                                                    <small class="text-muted">This system is for Elementary Department only</small>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="teacher_first_name" class="form-label">First Name *</label>
                                                    <input type="text" class="form-control" name="teacher_first_name" id="teacher_first_name" 
                                                           value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['first_name']) : ''; ?>" required>
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="teacher_middle_name" class="form-label">Middle Name</label>
                                                    <input type="text" class="form-control" name="teacher_middle_name" id="teacher_middle_name"
                                                           value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['middle_name']) : ''; ?>">
                                                </div>

                                                <div class="col-md-4 mb-3">
                                                    <label for="teacher_last_name" class="form-label">Last Name *</label>
                                                    <input type="text" class="form-control" name="teacher_last_name" id="teacher_last_name"
                                                           value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['last_name']) : ''; ?>" required>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="teacher_position" class="form-label">Position *</label>
                                                    <select class="form-select" name="teacher_position" id="teacher_position" required>
                                                        <option value="">Select Position</option>
                                                        <option value="Head Teacher" <?php echo ($edit_teacher && $edit_teacher['position'] == 'Head Teacher') ? 'selected' : ''; ?>>Head Teacher</option>
                                                        <option value="Teacher" <?php echo ($edit_teacher && $edit_teacher['position'] == 'Teacher') ? 'selected' : ''; ?>>Teacher</option>
                                                        <option value="Subject Teacher" <?php echo ($edit_teacher && $edit_teacher['position'] == 'Subject Teacher') ? 'selected' : ''; ?>>Subject Teacher</option>
                                                        <option value="Adviser" <?php echo ($edit_teacher && $edit_teacher['position'] == 'Adviser') ? 'selected' : ''; ?>>Adviser</option>
                                                    </select>
                                                </div>

                                                <?php if ($edit_teacher): ?>
                                                <div class="col-md-6 mb-3">
                                                    <label for="teacher_status" class="form-label">Status *</label>
                                                    <select class="form-select" name="teacher_status" id="teacher_status" required>
                                                        <option value="active" <?php echo ($edit_teacher && $edit_teacher['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo ($edit_teacher && $edit_teacher['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </div>
                                                <?php endif; ?>

                                                <div class="col-md-6 mb-3">
                                                    <label for="teacher_contact" class="form-label">Contact Number</label>
                                                    <input type="text" class="form-control" name="teacher_contact" id="teacher_contact" 
                                                           value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['contact_number']) : ''; ?>" 
                                                           placeholder="e.g. 09123456789">
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="teacher_address" class="form-label">Address</label>
                                                    <input type="text" class="form-control" name="teacher_address" id="teacher_address"
                                                           value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['address']) : ''; ?>" 
                                                           placeholder="Enter complete address">
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="teacher_date_hired" class="form-label">Date Hired *</label>
                                                    <input type="date" class="form-control" name="teacher_date_hired" id="teacher_date_hired"
                                                           value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['date_hired']) : date('Y-m-d'); ?>" 
                                                           min="<?php echo date('Y-m-d'); ?>" required>
                                                    <small class="text-muted">Cannot select past dates</small>
                                                </div>

                                                <?php if (!$edit_teacher): ?>
                                                <div class="col-12">
                                                    <div class="info-box">
                                                        <i class="fas fa-info-circle text-primary"></i>
                                                        <strong>Account Information:</strong> 
                                                        The system will automatically generate:<br>
                                                        - Username: Teacher Code (e.g., TC001)<br>
                                                        - Email: TeacherCode@cds.edu.ph (e.g., TC001@cds.edu.ph)<br>
                                                        - Default Password: TeacherCode + "1" (e.g., tc0011)
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="text-end">
                                                <?php if ($edit_teacher): ?>
                                                    <button type="submit" name="update_teacher" class="btn-primary">
                                                        <i class="fas fa-save"></i> Update Teacher Account
                                                    </button>
                                                    <a href="manage_accounts.php?view=teachers" class="btn btn-secondary">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                <?php else: ?>
                                                    <button type="submit" name="add_teacher" class="btn-primary">
                                                        <i class="fas fa-plus"></i> Create Teacher Account
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Accounts List -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-list"></i> Teacher Accounts (<?php echo count($teachers); ?>)
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="user-table">
                                                <thead>
                                                    <tr>
                                                        <th>Teacher Code</th>
                                                        <th>Name</th>
                                                        <th>Department</th>
                                                        <th>Position</th>
                                                        <th>Contact</th>
                                                        <th>Date Hired</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($teachers)): ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center text-muted">No teacher accounts yet.</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($teachers as $teacher): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($teacher['teacher_code']); ?></strong></td>
                                                                <td>
                                                                    <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                                                    <?php if (!empty($teacher['middle_name'])): ?>
                                                                        <br><small class="text-muted"><?php echo htmlspecialchars($teacher['middle_name']); ?></small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($teacher['department']); ?></td>
                                                                <td><?php echo htmlspecialchars($teacher['position']); ?></td>
                                                                <td><?php echo htmlspecialchars($teacher['contact_number'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($teacher['date_hired'] ?? 'N/A'); ?></td>
                                                                <td>
                                                                    <span class="status-badge status-<?php echo $teacher['status']; ?>">
                                                                        <?php echo ucfirst($teacher['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <a href="manage_accounts.php?view=teachers&edit_teacher=<?php echo $teacher['teacher_id']; ?>" class="btn-action btn-edit" title="Edit">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <button class="btn-action btn-delete" title="Delete" onclick="showDeleteModal(<?php echo $teacher['teacher_id']; ?>, '<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>', 'teacher')">
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
                            </div>
                        </div>

                    <?php elseif ($showMyAccount): ?>
                        <!-- My Account Section -->
                        <button class="back-btn" onclick="window.location.href='manage_accounts.php'">
                            <i class="fas fa-arrow-left"></i> Back to Manage Accounts
                        </button>
                        
                        <h2 class="page-title">
                            <i class="fas fa-user-cog"></i> My Account
                        </h2>

                        <?php if (isset($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-user-edit"></i> Update Account Information
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="full_name" class="form-label">Full Name *</label>
                                                    <input type="text" class="form-control" name="full_name" id="full_name" 
                                                           value="<?php echo isset($adminInfo['full_name']) ? htmlspecialchars($adminInfo['full_name']) : ''; ?>" 
                                                           required>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label">Email Address *</label>
                                                    <input type="email" class="form-control" name="email" id="email"
                                                           value="<?php echo isset($adminInfo['email']) ? htmlspecialchars($adminInfo['email']) : (isset($adminInfo['email_address']) ? htmlspecialchars($adminInfo['email_address']) : ''); ?>" 
                                                           required>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="username" class="form-label">Username *</label>
                                                    <input type="text" class="form-control" name="username" id="username"
                                                           value="<?php echo isset($adminInfo['username']) ? htmlspecialchars($adminInfo['username']) : ''; ?>" 
                                                           required>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="address" class="form-label">Address</label>
                                                    <input type="text" class="form-control" name="address" id="address"
                                                           value="<?php echo isset($adminInfo['address']) ? htmlspecialchars($adminInfo['address']) : ''; ?>" 
                                                           placeholder="Enter your complete address">
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="role" class="form-label">Role</label>
                                                    <input type="text" class="form-control" value="Administrator" readonly>
                                                </div>

                                                <div class="col-12">
                                                    <hr>
                                                    <h5 class="mb-3">Change Password (Optional)</h5>
                                                    <div class="row">
                                                        <div class="col-md-4 mb-3">
                                                            <label for="current_password" class="form-label">Current Password</label>
                                                            <input type="password" class="form-control" name="current_password" id="current_password">
                                                            <small class="text-muted">Required if changing password</small>
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label for="new_password" class="form-label">New Password</label>
                                                            <input type="password" class="form-control" name="new_password" id="new_password">
                                                            <small class="text-muted">Leave blank if not changing</small>
                                                        </div>

                                                        <div class="col-md-4 mb-3">
                                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                                                            <small class="text-muted">Must match new password</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="text-end">
                                                <button type="submit" name="update_my_account" class="btn-primary">
                                                    <i class="fas fa-save"></i> Update Account
                                                </button>
                                                <a href="manage_accounts.php" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($showManageAdmins): ?>
                        <!-- Manage Admin Accounts Section -->
                        <button class="back-btn" onclick="window.location.href='manage_accounts.php'">
                            <i class="fas fa-arrow-left"></i> Back to Manage Accounts
                        </button>
                        
                        <h2 class="page-title">
                            <i class="fas fa-user-shield"></i> Manage Admin Accounts
                        </h2>

                        <?php if (isset($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Add/Edit Admin Section -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-user-plus"></i> 
                                        <?php echo $edit_admin ? 'Edit Admin Account' : 'Add New Admin Account'; ?>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <?php if ($edit_admin): ?>
                                                <input type="hidden" name="admin_id" value="<?php echo $edit_admin['admin_id']; ?>">
                                            <?php endif; ?>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="full_name" class="form-label">Full Name *</label>
                                                    <input type="text" class="form-control" name="full_name" id="full_name" 
                                                           value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['full_name']) : ''; ?>" 
                                                           required>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label">Email Address *</label>
                                                    <input type="email" class="form-control" name="email" id="email"
                                                           value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['email_address']) : ''; ?>" 
                                                           required>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="username" class="form-label">Username *</label>
                                                    <input type="text" class="form-control" name="username" id="username"
                                                           value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['username']) : ''; ?>" 
                                                           required>
                                                </div>

                                                <div class="col-md-6 mb-3">
                                                    <label for="address" class="form-label">Address</label>
                                                    <input type="text" class="form-control" name="address" id="address"
                                                           value="<?php echo $edit_admin ? htmlspecialchars($edit_admin['address']) : ''; ?>" 
                                                           placeholder="Enter complete address">
                                                </div>

                                                <?php if ($edit_admin): ?>
                                                <div class="col-md-6 mb-3">
                                                    <label for="status" class="form-label">Status *</label>
                                                    <select class="form-select" name="status" id="status" required>
                                                        <option value="active" <?php echo ($edit_admin && $edit_admin['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo ($edit_admin && $edit_admin['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </div>
                                                <?php endif; ?>

                                                <?php if (!$edit_admin): ?>
                                                <div class="col-12">
                                                    <div class="info-box">
                                                        <i class="fas fa-info-circle text-primary"></i>
                                                        <strong>Account Information:</strong> 
                                                        The system will automatically generate:<br>
                                                        - Default Password: Username + "1" (e.g., admin1 → admin11)<br>
                                                        - Users will be prompted to change their password on first login
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="text-end">
                                                <?php if ($edit_admin): ?>
                                                    <button type="submit" name="update_admin" class="btn-primary">
                                                        <i class="fas fa-save"></i> Update Admin Account
                                                    </button>
                                                    <a href="manage_accounts.php?view=manage_admins" class="btn btn-secondary">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
                                                <?php else: ?>
                                                    <button type="submit" name="add_admin" class="btn-primary">
                                                        <i class="fas fa-plus"></i> Create Admin Account
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Accounts List -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-list"></i> Admin Accounts (<?php echo count($admins); ?>)
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="user-table">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Username</th>
                                                        <th>Email</th>
                                                        <th>Address</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($admins)): ?>
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted">No other admin accounts yet.</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($admins as $admin): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($admin['full_name']); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                                <td><?php echo htmlspecialchars($admin['email_address']); ?></td>
                                                                <td><?php echo htmlspecialchars($admin['address'] ?? 'N/A'); ?></td>
                                                                <td>
                                                                    <span class="status-badge status-<?php echo $admin['status']; ?>">
                                                                        <?php echo ucfirst($admin['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <a href="manage_accounts.php?view=manage_admins&edit_admin=<?php echo $admin['admin_id']; ?>" class="btn-action btn-edit" title="Edit">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <button class="btn-action btn-delete" title="Delete" onclick="showDeleteModal(<?php echo $admin['admin_id']; ?>, '<?php echo htmlspecialchars($admin['full_name']); ?>', 'admin')">
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
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Default View - Action Cards -->
                        <h2 class="page-title">
                            <i class="fas fa-users-cog"></i> Manage Accounts
                        </h2>

                        <div class="row">
                            <!-- View Students Card -->
                            <div class="col-md-3 mb-4">
                                <div class="action-card" onclick="window.location.href='view_students.php'">
                                    <div class="action-icon">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <h3 class="action-title">View Students</h3>
                                    <p class="action-description">
                                        View, add, edit, and manage all student accounts and information
                                    </p>
                                </div>
                            </div>

                            <!-- View Teachers Card -->
                            <div class="col-md-3 mb-4">
                                <div class="action-card" onclick="window.location.href='manage_accounts.php?view=teachers'">
                                    <div class="action-icon">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                    </div>
                                    <h3 class="action-title">View Teachers</h3>
                                    <p class="action-description">
                                        View, add, edit, and manage all teacher accounts and assignments
                                    </p>
                                </div>
                            </div>

                            <!-- My Account Card -->
                            <div class="col-md-3 mb-4">
                                <div class="action-card" onclick="window.location.href='manage_accounts.php?view=my_account'">
                                    <div class="action-icon">
                                        <i class="fas fa-user-cog"></i>
                                    </div>
                                    <h3 class="action-title">My Account</h3>
                                    <p class="action-description">
                                        View and update your account information, username, email, and password
                                    </p>
                                </div>
                            </div>

                            <!-- Manage Admin Accounts Card -->
                            <div class="col-md-3 mb-4">
                                <div class="action-card" onclick="window.location.href='manage_accounts.php?view=manage_admins'">
                                    <div class="action-icon">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                    <h3 class="action-title">Manage Admin Accounts</h3>
                                    <p class="action-description">
                                        Manage administrator accounts, permissions, and access levels
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                        <h4><?php echo $activeStudents; ?></h4>
                                        <p class="text-muted small">Active Students</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chalkboard-teacher fa-2x text-success mb-2"></i>
                                        <h4><?php echo $activeTeachers; ?></h4>
                                        <p class="text-muted small">Active Teachers</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-door-open fa-2x text-warning mb-2"></i>
                                        <h4><?php echo $activeSections; ?></h4>
                                        <p class="text-muted small">Active Sections</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-user-shield fa-2x text-info mb-2"></i>
                                        <h4><?php echo $activeAdmins; ?></h4>
                                        <p class="text-muted small">Admin Accounts</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete Modal Functions
        let currentDeleteId = null;
        let currentDeleteType = null;

        function showDeleteModal(id, name, type) {
            currentDeleteId = id;
            currentDeleteType = type;
            const modal = document.getElementById('deleteModal');
            const message = document.getElementById('deleteModalMessage');
            
            if (type === 'teacher') {
                message.innerHTML = `Are you sure you want to delete <strong>${name}</strong>'s teacher account? This action cannot be undone.`;
            } else if (type === 'admin') {
                message.innerHTML = `Are you sure you want to delete <strong>${name}</strong>'s admin account? This action cannot be undone.`;
            }
            
            modal.style.display = 'flex';
            
            // Set up confirm button
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.onclick = function() {
                if (currentDeleteType === 'teacher') {
                    window.location.href = `manage_accounts.php?view=teachers&delete_teacher=${currentDeleteId}`;
                } else if (currentDeleteType === 'admin') {
                    window.location.href = `manage_accounts.php?view=manage_admins&delete_admin=${currentDeleteId}`;
                }
            };
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'none';
            currentDeleteId = null;
            currentDeleteType = null;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }

        // Set min date for date hired field to today (cannot select past dates)
        document.addEventListener('DOMContentLoaded', function() {
            const dateHiredInput = document.getElementById('teacher_date_hired');
            if (dateHiredInput) {
                const today = new Date().toISOString().split('T')[0];
                dateHiredInput.setAttribute('min', today);
                
                // If no value is set, set it to today
                if (!dateHiredInput.value) {
                    dateHiredInput.value = today;
                }
            }
        });

        // Auto-dismiss alerts
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