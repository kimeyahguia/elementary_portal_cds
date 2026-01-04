<?php
session_start();

// Only admin should access
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
</head>
<body>

<h1>Welcome Admin!</h1>
<p>You are logged in as: <?php echo $username; ?></p>

<br>
<a href="logout.php">Logout</a>

</body>
</html>
