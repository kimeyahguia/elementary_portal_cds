<?php
// student_header.php - Common header and authentication
session_start();

// Check if user is student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Include database connection and functions
require_once 'db_connection.php';
require_once 'system_functions.php';

// Get student code from session
if (!isset($_SESSION['student_code']) || empty($_SESSION['student_code'])) {
    die("Error: Student code not found in session. Please log in again.");
}

$student_code = $_SESSION['student_code'];

// Get system settings
$current_school_year = getSystemSetting($conn, 'current_school_year', '2025-2026');
$current_quarter = getSystemSetting($conn, 'current_quarter', '2nd');
$school_name = getSystemSetting($conn, 'school_name', 'Creative Dreams School');

try {
    // Fetch student information with section, parent, and adviser details
    $stmt = $conn->prepare("
        SELECT 
            s.student_code,
            s.first_name,
            s.last_name,
            s.middle_name,
            s.gender,
            s.birthdate,
            s.address as student_address,
            s.status,
            s.date_enrolled,
            s.profile_picture,
            sec.section_id,
            sec.grade_level,
            sec.section_name,
            sec.room_assignment,
            sec.school_year,
            CONCAT(adv.first_name, ' ', adv.last_name) as adviser_name,
            adv.teacher_code as adviser_code,
            p.first_name as parent_first_name,
            p.last_name as parent_last_name,
            p.email as parent_email,
            p.contact_number as parent_contact,
            p.address as parent_address
        FROM students s
        LEFT JOIN sections sec ON s.section_id = sec.section_id
        LEFT JOIN teachers adv ON sec.adviser_code = adv.teacher_code
        LEFT JOIN parents p ON s.parent_id = p.parent_id
        WHERE s.student_code = :student_code
    ");
    $stmt->execute(['student_code' => $student_code]);
    $student_data = $stmt->fetch();

    if (!$student_data) {
        die("Student record not found for student code: " . htmlspecialchars($student_code));
    }

    // Fetch balance data
    $stmt = $conn->prepare("
        SELECT total_fee, amount_paid, balance, due_date, status
        FROM student_balances
        WHERE student_code = :student_code AND school_year = :school_year
        LIMIT 1
    ");
    $stmt->execute(['student_code' => $student_code, 'school_year' => $current_school_year]);
    $balance_data = $stmt->fetch();

    // Format payment status properly
    $payment_status = 'Unpaid';
    if ($balance_data && isset($balance_data['status'])) {
        $status = $balance_data['status'];
        if ($status === 'fully_paid') {
            $payment_status = 'Fully Paid';
        } elseif ($status === 'partial') {
            $payment_status = 'Partially Paid';
        } elseif ($status === 'unpaid') {
            $payment_status = 'Unpaid';
        } else {
            $payment_status = ucfirst(str_replace('_', ' ', $status));
        }
    }

    // Prepare student information
    $student_info = [
        'full_name' => $student_data['first_name'] . ' ' . $student_data['last_name'],
        'first_name' => $student_data['first_name'],
        'last_name' => $student_data['last_name'],
        'grade_level' => 'Grade ' . $student_data['grade_level'],
        'section' => $student_data['section_name'] ?? 'Not Assigned',
        'room_number' => $student_data['room_assignment'] ?? 'N/A',
        'adviser' => $student_data['adviser_name'] ?? 'Not Assigned',
        'adviser_code' => $student_data['adviser_code'] ?? 'N/A',
        'lrn' => $student_data['student_code'],
        'school_year' => $current_school_year,
        'current_quarter' => $current_quarter . ' Quarter',
        'enrollment_status' => ucfirst($student_data['status'] ?? 'active'),
        'date_enrolled' => $student_data['date_enrolled'] ? date('F d, Y', strtotime($student_data['date_enrolled'])) : 'N/A',
        'payment_status' => $payment_status,
        'balance' => $balance_data['balance'] ?? 25000.00,
        'profile_picture' => $student_data['profile_picture']
    ];

    // Get initials for avatar
    $initials = strtoupper(substr($student_data['first_name'], 0, 1) . substr($student_data['last_name'], 0, 1));

    // Check if profile picture exists
    $has_profile_picture = !empty($student_data['profile_picture']) && file_exists('../uploads/student_profiles/' . $student_data['profile_picture']);
    $profile_picture_url = $has_profile_picture ? '../uploads/student_profiles/' . $student_data['profile_picture'] : null;

    // Prepare parent information
    $parent_info = [
        'name' => trim(($student_data['parent_first_name'] ?? '') . ' ' . ($student_data['parent_last_name'] ?? '')),
        'contact' => $student_data['parent_contact'] ?? 'Not Provided',
        'email' => $student_data['parent_email'] ?? 'Not Provided',
        'address' => $student_data['parent_address'] ?? 'Not Provided'
    ];

    if (empty($parent_info['name'])) {
        $parent_info['name'] = 'Not Provided';
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>