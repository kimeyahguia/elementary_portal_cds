<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in as student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_connection.php';

$student_code = $_SESSION['student_code'];

try {
    // Fetch appointments from database
    $stmt = $conn->prepare("
        SELECT 
            appointment_id,
            appointment_type,
            appointment_date,
            appointment_time,
            status,
            notes,
            DATE_FORMAT(created_at, '%M %d, %Y') as date_created
        FROM appointments
        WHERE student_code = :student_code
        ORDER BY appointment_date DESC, appointment_time DESC
    ");
    $stmt->execute(['student_code' => $student_code]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'appointments' => $appointments
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>