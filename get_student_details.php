<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$host = 'localhost';
$dbname = 'u545996239_cdsportal';
$username = 'u545996239_cdsportal'; // Changed variable name to avoid conflict
$password = 'B@nana2025';     //

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$student_code = $_GET['student_code'] ?? '';
$for_edit = isset($_GET['for_edit']) && $_GET['for_edit'] == '1';

if (empty($student_code)) {
    echo json_encode(['success' => false, 'message' => 'Student code is required']);
    exit();
}

try {
    // Fetch student details with section and parent info
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            u.username,
            sec.grade_level,
            sec.section_name,
            sec.section_id,
            p.parent_id,
            p.parent_code,
            p.first_name as parent_first_name,
            p.last_name as parent_last_name,
            p.middle_name as parent_middle_name,
            p.relationship,
            p.email as parent_email,
            p.contact_number,
            p.occupation,
            p.address as parent_address,
            t.first_name as adviser_first_name,
            t.last_name as adviser_last_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        LEFT JOIN teachers t ON sec.adviser_code = t.teacher_code
        WHERE s.student_code = ?
    ");
    $stmt->execute([$student_code]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
    $response = [
        'success' => true,
        'student' => $student,
        'adviser' => null,
        'parent' => null
    ];
    
    // Format adviser name
    if ($student['adviser_first_name']) {
        $response['adviser'] = $student['adviser_first_name'] . ' ' . $student['adviser_last_name'];
    }
    
    // Format parent info
    if ($student['parent_id']) {
        $response['parent'] = [
            'parent_id' => $student['parent_id'],
            'parent_code' => $student['parent_code'],
            'first_name' => $student['parent_first_name'],
            'last_name' => $student['parent_last_name'],
            'middle_name' => $student['parent_middle_name'],
            'relationship' => $student['relationship'],
            'email' => $student['parent_email'],
            'contact_number' => $student['contact_number'],
            'occupation' => $student['occupation'],
            'address' => $student['parent_address']
        ];
    }
    
    // If for edit, fetch sections list
    if ($for_edit) {
        $stmt = $pdo->query("SELECT section_id, grade_level, section_name FROM sections WHERE is_active = 1 ORDER BY grade_level, section_name");
        $response['sections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($response);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>