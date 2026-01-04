<?php
session_start();

$host = "localhost";
$user = "u545996239_cdsportal";
$pass = "B@nana2025";
$db   = "u545996239_cdsportal";
$conn = new mysqli($host, $user, $pass, $db);

if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

// FUNCTION: GENERATE APPOINTMENT ID
function generateAppointmentId($conn) {
    $prefix = "CDS";
    $unique = false;
    $appointment_id = '';
    
    while (!$unique) {
        $random = mt_rand(1000, 9999);
        $appointment_id = $prefix . $random;
        
        // Check if it exists in database
        $check_sql = "SELECT id FROM enrollment WHERE appointment_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $appointment_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            $unique = true;
        }
        $stmt->close();
    }
    return $appointment_id;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appointment_id = generateAppointmentId($conn);
    $student_name = $_POST['studentName'];
    $parent_name = $_POST['parentName'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $preferred_date = $_POST['date'];
    $preferred_time = $_POST['time'];
    $grade_level = $_POST['gradeLevel'];
    $message = $_POST['message'] ?? '';

    $sql = "INSERT INTO enrollment (
        appointment_id, student_name, parent_name, email, phone, 
        preferred_date, preferred_time, grade_level, message, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", 
        $appointment_id,
        $student_name,
        $parent_name,
        $email,
        $phone,
        $preferred_date,
        $preferred_time,
        $grade_level,
        $message
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'appointment_id' => $appointment_id,
            'message' => 'Appointment scheduled successfully!'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to schedule appointment: ' . $conn->error
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
?>