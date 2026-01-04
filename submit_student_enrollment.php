<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "cdsportal";
$conn = new mysqli($host, $user, $pass, $db);

if($conn->connect_error){
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
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
    // Check if enrollment is open
    $settings_sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'enrollment_status'";
    $settings_result = $conn->query($settings_sql);
    $enrollment_status = 'closed';
    
    if ($settings_result && $row = $settings_result->fetch_assoc()) {
        $enrollment_status = $row['setting_value'];
    }
    
    if ($enrollment_status !== 'open') {
        echo json_encode(['status' => 'error', 'message' => 'Enrollment is currently closed']);
        exit();
    }
    
    $student_code = $_POST['studentCode'];
    $academic_year = $_POST['academicYear'];
    $preferred_date = $_POST['date'];
    
    // Check if student already has pending enrollment
    $check_sql = "SELECT id FROM enrollment WHERE student_code = ? AND academic_year = ? AND status IN ('pending', 'processing')";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $student_code, $academic_year);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You already have a pending enrollment request for this academic year']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    // Check if selected date is available and has slots
    $date_check_sql = "SELECT id, max_slots, current_slots FROM available_dates 
                       WHERE available_date = ? 
                       AND status = 'active' 
                       AND current_slots < max_slots";
    $date_stmt = $conn->prepare($date_check_sql);
    $date_stmt->bind_param("s", $preferred_date);
    $date_stmt->execute();
    $date_result = $date_stmt->get_result();
    
    if ($date_result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Selected date is no longer available. Please choose another date.']);
        $date_stmt->close();
        exit();
    }
    
    $date_info = $date_result->fetch_assoc();
    $date_stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Generate appointment ID
        $appointment_id = generateAppointmentId($conn);
        
        // Get form data
        $student_name = $_POST['studentName'];
        $parent_name = $_POST['parentName'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $preferred_time = $_POST['time'];
        $current_grade_level = $_POST['currentGradeLevel'];
        $grade_level = $_POST['gradeLevel'];
        $message = $_POST['message'] ?? '';

        // Insert enrollment
        $sql = "INSERT INTO enrollment (
            appointment_id, 
            student_code,
            enrollment_type,
            student_name, 
            parent_name, 
            email, 
            phone, 
            preferred_date, 
            preferred_time, 
            current_grade_level,
            grade_level, 
            academic_year,
            message, 
            status
        ) VALUES (?, ?, 'returning_student', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssss", 
            $appointment_id,
            $student_code,
            $student_name,
            $parent_name,
            $email,
            $phone,
            $preferred_date,
            $preferred_time,
            $current_grade_level,
            $grade_level,
            $academic_year,
            $message
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create enrollment: ' . $stmt->error);
        }
        $stmt->close();
        
        // Update available_dates slot count
        $update_sql = "UPDATE available_dates 
                       SET current_slots = current_slots + 1 
                       WHERE available_date = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("s", $preferred_date);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to update slot count: ' . $update_stmt->error);
        }
        $update_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'status' => 'success', 
            'appointment_id' => $appointment_id,
            'message' => 'Enrollment appointment scheduled successfully!'
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode([
            'status' => 'error', 
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
?>