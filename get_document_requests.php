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
    // Fetch document requests from database
    $stmt = $conn->prepare("
        SELECT 
            request_id,
            document_type,
            purpose,
            copies,
            status,
            DATE_FORMAT(date_requested, '%M %d, %Y') as date_requested,
            DATE_FORMAT(date_processed, '%M %d, %Y') as date_processed,
            notes
        FROM document_requests
        WHERE student_code = :student_code
        ORDER BY date_requested DESC
    ");
    $stmt->execute(['student_code' => $student_code]);
    $document_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'requests' => $document_requests
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>