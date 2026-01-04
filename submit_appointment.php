<?php
header('Content-Type: application/json');

$host = "localhost";
$dbname = "u545996239_cdsportal";
$user = "u545996239_cdsportal";
$pass = "B@nana2025";

$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    echo json_encode(['status'=>'error','message'=>"DB connection failed: ".mysqli_connect_error()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentName = isset($_POST['studentName']) ? mysqli_real_escape_string($conn, trim($_POST['studentName'])) : '';
    $parentName = isset($_POST['parentName']) ? mysqli_real_escape_string($conn, trim($_POST['parentName'])) : '';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : '';
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, trim($_POST['phone'])) : '';
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $time = isset($_POST['time']) ? $_POST['time'] : '';
    $gradeLevel = isset($_POST['gradeLevel']) ? $_POST['gradeLevel'] : '';
    $message = isset($_POST['message']) ? mysqli_real_escape_string($conn, trim($_POST['message'])) : '';

    if (!$studentName || !$parentName || !$email || !$phone || !$date || !$time || !$gradeLevel) {
        echo json_encode(['status'=>'error','message'=>'Please fill all required fields.']);
        exit;
    }

    $sql = "INSERT INTO appointments 
            (student_name, parent_name, email, phone, preferred_date, preferred_time, grade_level, additional_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['status'=>'error','message'=>'Prepare failed: '.mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ssssssss", $studentName, $parentName, $email, $phone, $date, $time, $gradeLevel, $message);

    if (mysqli_stmt_execute($stmt)) {
        // Get the inserted ID
        $lastId = mysqli_insert_id($conn);
        echo json_encode([
            'status'=>'success', 
            'appointment_id'=>$lastId,
            'message'=>'Appointment successfully scheduled!'
        ]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Insert failed: '.mysqli_stmt_error($stmt)]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
} else {
    echo json_encode(['status'=>'error','message'=>'Invalid request method']);
}
?>
