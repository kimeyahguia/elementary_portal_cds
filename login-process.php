<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validate input
    if (empty($username) || empty($password)) {
        header("Location: login.php?error=empty_fields");
        exit();
    }

    try {
        // Step 1: Check if user exists in USERS table
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() != 1) {
            header("Location: login.php?error=user_not_found");
            exit();
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Step 2: Verify Password
        // IMPORTANT: Change to password_verify() when passwords are hashed
        if ($password !== $user['password']) {
            header("Location: login.php?error=invalid_password");
            exit();
        }

        // Step 3: Setup basic session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        // Step 4: Load role-specific profile data and redirect
        switch ($user['role']) {
            case 'student':
                // Get student profile
                $stmt = $conn->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
                $stmt->bindParam(':uid', $user['user_id']);
                $stmt->execute();
                $student_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student_data) {
                    $_SESSION['student_profile'] = $student_data;
                    $_SESSION['student_code'] = $student_data['student_code'];
                    $_SESSION['first_name'] = $student_data['first_name'];
                    $_SESSION['last_name'] = $student_data['last_name'];
                } else {
                    header("Location: login.php?error=student_profile_not_found");
                    exit();
                }
                
                // Redirect to student dashboard
                header("Location: student/student_dashboard.php");
                exit();

            case 'teacher':
                // Get teacher profile
                $stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = :uid LIMIT 1");
                $stmt->bindParam(':uid', $user['user_id']);
                $stmt->execute();
                $teacher_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($teacher_data) {
                    $_SESSION['teacher_profile'] = $teacher_data;
                    $_SESSION['teacher_code'] = $teacher_data['teacher_code'];
                    $_SESSION['first_name'] = $teacher_data['first_name'];
                    $_SESSION['last_name'] = $teacher_data['last_name'];
                }
                
                // Redirect to teacher dashboard
                header("Location: teacher/teacher_dashboard.php");
                exit();

            case 'admin':
                // Set admin name for dashboard
                $_SESSION['admin_name'] = $user['username'];
                
                // Redirect to admin dashboard
                header("Location: admin_dashboard.php");
                exit();

            default:
                // Unknown role - redirect to index
                header("Location: index.php");
                exit();
        }

    } catch (PDOException $e) {
        // Log error and show generic message
        error_log("Login error: " . $e->getMessage());
        header("Location: login.php?error=database_error");
        exit();
    }

} else {
    // Not a POST request - redirect to login
    header("Location: login.php");
    exit();
}
?>