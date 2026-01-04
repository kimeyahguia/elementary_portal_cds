<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'u545996239_cdsportal';
$username = 'u545996239_cdsportal';
$password = 'B@nana2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if student_code is provided
if (!isset($_POST['student_code']) || empty($_POST['student_code'])) {
    echo json_encode(['success' => false, 'message' => 'Student code is required']);
    exit();
}

$student_code = $_POST['student_code'];

try {
    $pdo->beginTransaction();

    // First, get student information including user_id and section_id
    $stmt = $pdo->prepare("SELECT user_id, section_id, first_name, last_name FROM students WHERE student_code = ?");
    $stmt->execute([$student_code]);
    $student = $stmt->fetch();

    if (!$student) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    $user_id = $student['user_id'];
    $section_id = $student['section_id'];

    // Delete from student_balances table
    $stmt = $pdo->prepare("DELETE FROM student_balances WHERE student_code = ?");
    $stmt->execute([$student_code]);

    // Delete from students table
    $stmt = $pdo->prepare("DELETE FROM students WHERE student_code = ?");
    $stmt->execute([$student_code]);

    // Delete from users table
    if ($user_id) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }

    // Update section enrollment count
    if ($section_id) {
        $stmt = $pdo->prepare("UPDATE sections SET current_enrollment = GREATEST(current_enrollment - 1, 0) WHERE section_id = ?");
        $stmt->execute([$section_id]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Student ' . $student['first_name'] . ' ' . $student['last_name'] . ' has been successfully deleted from the system.'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting student: ' . $e->getMessage()]);
}
?>