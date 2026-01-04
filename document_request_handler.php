<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in as student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'db_connection.php';

$action = $_POST['action'] ?? '';
$student_code = $_SESSION['student_code'];

try {
    switch ($action) {
        case 'submit':
            // Validate inputs
            $document_type = trim($_POST['document_type'] ?? '');
            $purpose = trim($_POST['purpose'] ?? '');
            $copies = intval($_POST['copies'] ?? 1);

            if (empty($document_type) || empty($purpose)) {
                throw new Exception('Please fill in all required fields');
            }

            if ($copies < 1 || $copies > 10) {
                throw new Exception('Number of copies must be between 1 and 10');
            }

            // Insert document request
            $stmt = $conn->prepare("
                INSERT INTO document_requests 
                (student_code, document_type, purpose, copies, status, date_requested) 
                VALUES 
                (:student_code, :document_type, :purpose, :copies, 'requested', NOW())
            ");
            
            $stmt->execute([
                'student_code' => $student_code,
                'document_type' => $document_type,
                'purpose' => $purpose,
                'copies' => $copies
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Document request submitted successfully! Processing time: 3-5 business days.'
            ]);
            break;

        case 'cancel':
            $request_id = intval($_POST['request_id'] ?? 0);

            if ($request_id <= 0) {
                throw new Exception('Invalid request ID');
            }

            // Verify the request belongs to this student
            $stmt = $conn->prepare("
                SELECT status 
                FROM document_requests 
                WHERE request_id = :id AND student_code = :student_code
            ");
            $stmt->execute([
                'id' => $request_id,
                'student_code' => $student_code
            ]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Document request not found');
            }

            if ($request['status'] === 'claimed') {
                throw new Exception('Cannot cancel claimed documents');
            }

            if ($request['status'] === 'rejected') {
                throw new Exception('Cannot cancel rejected requests');
            }

            // Delete the request
            $stmt = $conn->prepare("
                DELETE FROM document_requests 
                WHERE request_id = :id AND student_code = :student_code
            ");
            $stmt->execute([
                'id' => $request_id,
                'student_code' => $student_code
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Document request cancelled successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>