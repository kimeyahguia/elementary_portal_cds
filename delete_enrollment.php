<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $enrollment_id = $_POST['enrollment_id'] ?? null;
    
    if (!$enrollment_id) {
        echo json_encode(['status' => 'error', 'message' => 'Enrollment ID is required']);
        exit();
    }
    
    try {
        $user_id = $_SESSION['user_id'];
        
        // Get student code from the logged-in user
        $stmt = $conn->prepare("SELECT student_code FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            echo json_encode(['status' => 'error', 'message' => 'Student information not found']);
            exit();
        }
        
        // Verify that the enrollment belongs to this student and can be deleted
        $check_stmt = $conn->prepare("
            SELECT id, status FROM enrollment 
            WHERE id = ? AND student_code = ? AND status IN ('pending', 'cancelled')
        ");
        $check_stmt->execute([$enrollment_id, $student['student_code']]);
        $enrollment = $check_stmt->fetch();
        
        if (!$enrollment) {
            echo json_encode(['status' => 'error', 'message' => 'Enrollment not found or cannot be deleted. Only pending or cancelled enrollments can be deleted.']);
            exit();
        }
        
        // Delete the enrollment
        $delete_stmt = $conn->prepare("DELETE FROM enrollment WHERE id = ?");
        $delete_stmt->execute([$enrollment_id]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Enrollment deleted successfully'
        ]);
        
    } catch(PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>  