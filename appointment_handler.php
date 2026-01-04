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
        case 'book':
            // Validate inputs
            $appointment_type = trim($_POST['appointment_type'] ?? '');
            $appointment_date = trim($_POST['appointment_date'] ?? '');
            $appointment_time = trim($_POST['appointment_time'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($appointment_type) || empty($appointment_date) || empty($appointment_time)) {
                throw new Exception('Please fill in all required fields');
            }

            // Validate date is not in the past
            $selected_date = new DateTime($appointment_date);
            $today = new DateTime('today');
            
            if ($selected_date < $today) {
                throw new Exception('Cannot book appointments for past dates');
            }

            // Validate date is not a weekend
            $day_of_week = $selected_date->format('N'); // 1 (Monday) to 7 (Sunday)
            if ($day_of_week >= 6) { // 6 = Saturday, 7 = Sunday
                throw new Exception('Appointments are not available on weekends');
            }

            // Check if the time slot is already booked
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM appointments 
                WHERE appointment_date = :date 
                AND appointment_time = :time 
                AND status != 'cancelled'
            ");
            $stmt->execute([
                'date' => $appointment_date,
                'time' => $appointment_time
            ]);
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                throw new Exception('This time slot is already booked. Please select another time.');
            }

            // Insert appointment
            $stmt = $conn->prepare("
                INSERT INTO appointments 
                (student_code, appointment_type, appointment_date, appointment_time, notes, status, created_at) 
                VALUES 
                (:student_code, :type, :date, :time, :notes, 'pending', NOW())
            ");
            
            $stmt->execute([
                'student_code' => $student_code,
                'type' => $appointment_type,
                'date' => $appointment_date,
                'time' => $appointment_time,
                'notes' => $notes
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Appointment booked successfully! Waiting for confirmation.'
            ]);
            break;

        case 'cancel':
            $appointment_id = intval($_POST['appointment_id'] ?? 0);

            if ($appointment_id <= 0) {
                throw new Exception('Invalid appointment ID');
            }

            // Verify the appointment belongs to this student
            $stmt = $conn->prepare("
                SELECT status 
                FROM appointments 
                WHERE appointment_id = :id AND student_code = :student_code
            ");
            $stmt->execute([
                'id' => $appointment_id,
                'student_code' => $student_code
            ]);
            $appointment = $stmt->fetch();

            if (!$appointment) {
                throw new Exception('Appointment not found');
            }

            if ($appointment['status'] === 'completed') {
                throw new Exception('Cannot cancel completed appointments');
            }

            if ($appointment['status'] === 'cancelled') {
                throw new Exception('Appointment is already cancelled');
            }

            // Delete the appointment from database
            $stmt = $conn->prepare("
                DELETE FROM appointments 
                WHERE appointment_id = :id AND student_code = :student_code
            ");
            $stmt->execute([
                'id' => $appointment_id,
                'student_code' => $student_code
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Appointment cancelled and removed successfully'
            ]);
            break;

        case 'get_booked_slots':
            $date = trim($_POST['date'] ?? '');
            
            if (empty($date)) {
                throw new Exception('Date is required');
            }

            $stmt = $conn->prepare("
                SELECT appointment_time 
                FROM appointments 
                WHERE appointment_date = :date 
                AND status != 'cancelled'
            ");
            $stmt->execute(['date' => $date]);
            $booked_times = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'success' => true,
                'booked_times' => $booked_times
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