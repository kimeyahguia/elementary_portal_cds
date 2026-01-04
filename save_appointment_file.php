<?php
// save_appointment_file.php
header('Content-Type: application/json');

// --- CHECK FOR POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Collect input
$studentName = isset($_POST['studentName']) ? trim($_POST['studentName']) : '';
$parentName = isset($_POST['parentName']) ? trim($_POST['parentName']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$date = isset($_POST['date']) ? $_POST['date'] : '';
$time = isset($_POST['time']) ? $_POST['time'] : '';
$gradeLevel = isset($_POST['gradeLevel']) ? trim($_POST['gradeLevel']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Basic validation
if (!$studentName || !$parentName || !$email || !$phone || !$date || !$time || !$gradeLevel) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
    exit;
}

// Prepare data
$appointmentData = [
    'studentName' => $studentName,
    'parentName' => $parentName,
    'email' => $email,
    'phone' => $phone,
    'preferredDate' => $date,
    'preferredTime' => $time,
    'gradeLevel' => $gradeLevel,
    'additionalNotes' => $message,
    'dateCreated' => date('Y-m-d H:i:s')
];

// File to save
$filePath = __DIR__ . '/appointments_backup.json';

// Check if file exists
if (file_exists($filePath)) {
    $existingData = json_decode(file_get_contents($filePath), true);
    if (!is_array($existingData)) $existingData = [];
} else {
    $existingData = [];
}

// Add new appointment
$existingData[] = $appointmentData;

// Save back to file
if (file_put_contents($filePath, json_encode($existingData, JSON_PRETTY_PRINT))) {
    echo json_encode(['status' => 'success', 'message' => 'Appointment saved to file.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save appointment to file.']);
}
?>
