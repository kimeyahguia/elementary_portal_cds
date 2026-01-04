<?php
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'student':
                header("Location: student/student_dashboard.php");
                exit();
            case 'teacher':
                header("Location: teacher/teacher_dashboard.php");
                exit();
            case 'admin':
                header("Location: admin_dashboard.php");
                exit();
            default:
                header("Location: index.php");
                exit();
        }
    } else {
        header("Location: index.php");
        exit();
    }
}

// Error messages
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty_fields':
            $error_message = 'Please fill in all fields!';
            break;
        case 'invalid_password':
            $error_message = 'Invalid password!';
            break;
        case 'user_not_found':
            $error_message = 'User not found!';
            break;
        case 'account_inactive':
            $error_message = 'Your account is inactive. Please contact admin.';
            break;
        case 'database_error':
            $error_message = 'Database error. Please try again later.';
            break;
        case 'logged_out':
            $error_message = 'You have been logged out successfully.';
            break;
        case 'student_profile_not_found':
            $error_message = 'Student profile not found. Please contact admin.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Creative Dreams School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #688A65;
            --light-green: #82b37dff;
            --dark-green: #4a6a48;
            --text-dark: #2c3e2b;
            --sage-green: #4c8c4a;
            --accent-green: #66a65c;
            --pale-green: #e0f7fa;
            --light-sage: #c4d6b7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(74,124,89,.85), rgba(45,90,61,.9)),
                        url('cdspic.jpg') center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(76, 140, 74, 0.1);
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.5s ease-in-out;
            position: relative;
            z-index: 1;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(249,255,249,0.9) 100%);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--light-sage), var(--accent-green), var(--light-sage));
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: 0% 0%; }
            50% { background-position: 100% 0%; }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--light-sage), white);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.5), 0 0 0 5px rgba(255,255,255,0.2);
            border: 3px solid white;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .login-header h2 {
            color: var(--text-dark);
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .login-header p {
            color: var(--sage-green);
            font-size: 14px;
            font-style: italic;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: var(--sage-green);
            font-size: 16px;
        }

        .form-control {
            border: 2px solid rgba(76, 140, 74, 0.2);
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--sage-green);
            box-shadow: 0 0 0 0.2rem rgba(76, 140, 74, 0.25);
            outline: none;
            background: white;
        }

        .forgot-link {
            color: var(--sage-green);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: var(--primary-green);
            text-decoration: underline;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(88, 129, 87, 0.3);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 12px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            border-color: rgba(255, 255, 255, 0.6);
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 12px 15px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }

        .alert-success {
            background: var(--pale-green);
            color: var(--dark-green);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 25px 0;
            color: #999;
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e0e0e0;
        }

        .divider span {
            padding: 0 15px;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 13px;
        }

        .footer-text a {
            color: var(--sage-green);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .login-card {
                padding: 30px 25px;
            }

            .logo {
                width: 80px;
                height: 80px;
            }

            .login-header h2 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Back to Home Button -->
        <div class="text-center">
            <a href="index.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back to Home
            </a>
        </div>

        <div class="login-card">
            <!-- Logo and Header -->
            <div class="logo-container">
                <div class="logo">
                    <img src="cdslogo.jpg" alt="CDS Logo">
                </div>
                <div class="login-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to continue to your portal</p>
                </div>
            </div>

            <!-- Error/Success Message -->
            <?php if (!empty($error_message)): ?>
                <div class="alert <?php echo (isset($_GET['error']) && $_GET['error'] === 'logged_out') ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                    <i class="bi <?php echo (isset($_GET['error']) && $_GET['error'] === 'logged_out') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login-process.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="bi bi-person-circle"></i>
                        School ID / Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" required placeholder="Enter your ID or username">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock-fill"></i>
                        Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                </div>

                <div class="text-end mb-4">
                
                </div>

                <button type="submit" class="btn-login">
                    Sign In <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </form>

            <!-- Footer Text -->
            <div class="footer-text">
                Need help? <a href="index.php#contact">Contact Support</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>