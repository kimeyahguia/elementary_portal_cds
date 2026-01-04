<?php
// fetch_available_dates.php
// Place this file in the same directory as student_enrollment.php

session_start();

// Check if student is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "cdsportal";
$conn = new mysqli($host, $user, $pass, $db);

if($conn->connect_error){
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

// Fetch available dates that are active and have available slots
$sql = "SELECT 
            id,
            available_date,
            max_slots,
            current_slots,
            (max_slots - current_slots) as available_slots
        FROM available_dates 
        WHERE status = 'active' 
        AND available_date >= CURDATE()
        AND current_slots < max_slots
        ORDER BY available_date ASC";

$result = $conn->query($sql);
$dates = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dates[] = [
            'date' => $row['available_date'],
            'formatted_date' => date('F d, Y', strtotime($row['available_date'])),
            'available_slots' => $row['available_slots'],
            'max_slots' => $row['max_slots']
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'dates' => $dates
]);

$conn->close();
?>