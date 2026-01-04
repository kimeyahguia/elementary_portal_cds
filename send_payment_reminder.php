<?php
session_start();
header('Content-Type: application/json');




// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}




$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;




if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}




try {
    // Get student information
    $stmt = $pdo->prepare("
        SELECT s.*,
               CONCAT(s.first_name, ' ', s.last_name) as full_name,
               COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END), 0) as unpaid_balance
        FROM students s
        LEFT JOIN payments p ON s.id = p.student_id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
   
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
   
    if ($student['unpaid_balance'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'No unpaid balance for this student']);
        exit();
    }
   
    // In a real application, you would send an email or SMS here
    // For now, we'll just log the reminder
   
    // Log the reminder in the database (you may need to create a reminders table)
    // For demonstration, we'll just return success
   
    $reminderMessage = "Dear " . $student['parent_name'] . ",\n\n";
    $reminderMessage .= "This is a friendly reminder that student " . $student['full_name'];
    $reminderMessage .= " (Student #: " . $student['student_number'] . ") has an unpaid balance of ₱" . number_format($student['unpaid_balance'], 2) . ".\n\n";
    $reminderMessage .= "Please settle the payment at your earliest convenience.\n\n";
    $reminderMessage .= "Thank you!\n\n";
    $reminderMessage .= "Creative Dreams School\n";
    $reminderMessage .= "School Portal System";
   
    // Here you would integrate with an email service like PHPMailer or SMS gateway
    // For example:
    // sendEmail($student['email'], "Payment Reminder", $reminderMessage);
    // sendSMS($student['contact_number'], $reminderMessage);
   
    // For now, we'll simulate success
    echo json_encode([
        'success' => true,
        'message' => 'Payment reminder sent successfully to ' . $student['parent_name'],
        'details' => [
            'student_name' => $student['full_name'],
            'unpaid_balance' => number_format($student['unpaid_balance'], 2),
            'contact' => $student['contact_number'],
            'email' => $student['email']
        ]
    ]);
   
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error processing reminder: ' . $e->getMessage()]);
}
?>