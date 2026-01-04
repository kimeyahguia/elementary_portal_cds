<?php
session_start();

// Check if user is student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
  header(header: "Location: login.php");
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
    'payment_status' => 'Partially Paid',
    'balance' => 5000.00
  ];

  // Get initials for avatar
  $initials = strtoupper(substr($student_data['first_name'], 0, 1) . substr($student_data['last_name'], 0, 1));

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

  // Fetch grades for all quarters
  $stmt = $conn->prepare(query: "
        SELECT 
            g.quarter,
            s.subject_name,
            g.final_grade as grade,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
        FROM grades g
        INNER JOIN subjects s ON g.subject_code = s.subject_code
        LEFT JOIN teachers t ON g.teacher_code = t.teacher_code
        WHERE g.student_code = :student_code
        ORDER BY 
            FIELD(g.quarter, '1st', '2nd', '3rd', '4th'),
            s.subject_name
    ");
  $stmt->execute(['student_code' => $student_code]);
  $all_grades = $stmt->fetchAll();

  // Organize grades by quarter
  $grades_data = [
    '1st' => [],
    '2nd' => [],
    '3rd' => [],
    '4th' => []
  ];

  // Get all subjects assigned to the student's section
  if ($student_data['section_id']) {
    $stmt = $conn->prepare("
            SELECT 
                subj.subject_name,
                subj.subject_code,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM subject_assignments sa
            INNER JOIN subjects subj ON sa.subject_code = subj.subject_code
            INNER JOIN teachers t ON sa.teacher_code = t.teacher_code
            WHERE sa.section_id = :section_id
            AND sa.school_year = :school_year
            AND sa.is_active = TRUE
            ORDER BY subj.subject_name
        ");
    $stmt->execute([
      'section_id' => $student_data['section_id'],
      'school_year' => $current_school_year
    ]);
    $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $all_subjects = [];
  }

  // If no subjects found, use default subjects list
  if (empty($all_subjects)) {
    $all_subjects = [
      ['subject_name' => 'Mathematics', 'teacher_name' => 'N/A'],
      ['subject_name' => 'Science', 'teacher_name' => 'N/A'],
      ['subject_name' => 'English', 'teacher_name' => 'N/A'],
      ['subject_name' => 'Filipino', 'teacher_name' => 'N/A'],
      ['subject_name' => 'Araling Panlipunan', 'teacher_name' => 'N/A'],
      ['subject_name' => 'MAPEH', 'teacher_name' => 'N/A'],
      ['subject_name' => 'GMRC', 'teacher_name' => 'N/A'],
    ];
  }

  // Populate grades data
  foreach (['1st', '2nd', '3rd', '4th'] as $quarter) {
    foreach ($all_subjects as $subject) {
      $grade_found = false;
      foreach ($all_grades as $grade_row) {
        if ($grade_row['quarter'] == $quarter && $grade_row['subject_name'] == $subject['subject_name']) {
          $grade_value = (float)$grade_row['grade'];
          $grades_data[$quarter][] = [
            'subject' => $subject['subject_name'],
            'teacher' => $grade_row['teacher_name'] ?? $subject['teacher_name'] ?? 'N/A',
            'grade' => $grade_value,
            'remarks' => $grade_value >= 75 ? 'Passed' : ($grade_value > 0 ? 'Failed' : 'Not Available')
          ];
          $grade_found = true;
          break;
        }
      }
      if (!$grade_found) {
        $grades_data[$quarter][] = [
          'subject' => $subject['subject_name'],
          'teacher' => $subject['teacher_name'] ?? 'N/A',
          'grade' => 0,
          'remarks' => 'Not Available'
        ];
      }
    }
  }

  // Calculate averages for each quarter
  $quarter_averages = [];
  foreach (['1st', '2nd', '3rd', '4th'] as $quarter) {
    $grades = array_filter(array_column($grades_data[$quarter], 'grade'), function ($g) {
      return $g > 0;
    });
    $quarter_averages[$quarter] = count($grades) > 0 ? round(array_sum($grades) / count($grades), 2) : 0;
  }

  // Calculate general average
  $valid_averages = array_filter($quarter_averages, function ($avg) {
    return $avg > 0;
  });
  $general_average = count($valid_averages) > 0 ? round(array_sum($valid_averages) / count($valid_averages), 2) : 0;

  // Fetch schedule for the student's section
  $schedule_data = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => []
  ];

  if ($student_data['section_id']) {
    $stmt = $conn->prepare("
            SELECT 
                ss.day,
                ss.start_time,
                ss.end_time,
                subj.subject_name,
                ss.room,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM subject_schedules ss
            INNER JOIN subject_assignments sa ON ss.assignment_id = sa.assignment_id
            INNER JOIN subjects subj ON sa.subject_code = subj.subject_code
            INNER JOIN teachers t ON sa.teacher_code = t.teacher_code
            WHERE sa.section_id = :section_id
            AND sa.school_year = :school_year
            AND sa.is_active = TRUE
            ORDER BY 
                FIELD(ss.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                ss.start_time
        ");
    $stmt->execute([
      'section_id' => $student_data['section_id'],
      'school_year' => $current_school_year
    ]);
    $schedule_results = $stmt->fetchAll();

    foreach ($schedule_results as $schedule) {
      $start = date('g:i A', strtotime($schedule['start_time']));
      $end = date('g:i A', strtotime($schedule['end_time']));

      $schedule_data[$schedule['day']][] = [
        'time' => $start . ' - ' . $end,
        'subject' => $schedule['subject_name'],
        'room' => $schedule['room'],
        'teacher' => $schedule['teacher_name'] ?? 'N/A'
      ];
    }
  }

  // Add placeholder if no schedule
  foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
    if (empty($schedule_data[$day])) {
      $schedule_data[$day][] = [
        'time' => '-',
        'subject' => 'No Schedule Available',
        'room' => '-',
        'teacher' => '-'
      ];
    }
  }

  // Fetch announcements from database
  $stmt = $conn->prepare("
        SELECT 
            title,
            content,
            DATE_FORMAT(date_posted, '%M %d, %Y') as date_posted,
            category,
            priority
        FROM announcements
        WHERE target_audience IN ('all', 'students')
        AND is_active = TRUE
        ORDER BY 
            FIELD(priority, 'high', 'medium', 'low'),
            date_posted DESC
        LIMIT 5
    ");
  $stmt->execute();
  $db_announcements = $stmt->fetchAll();

  // Prepare announcements data
  $announcements = [];
  foreach ($db_announcements as $announcement) {
    $announcements[] = [
      'title' => $announcement['title'],
      'date' => $announcement['date_posted'],
      'category' => $announcement['category'] ?? 'Information',
      'content' => $announcement['content'],
      'priority' => $announcement['priority'] ?? 'medium'
    ];
  }

  // Fallback to static announcements if database is empty
  if (empty($announcements)) {
    $announcements = [
      [
        'title' => 'Christmas Party Celebration',
        'date' => 'November 10, 2025',
        'category' => 'Event',
        'content' => 'Join us for our annual Christmas Party on December 20, 2025. Students are encouraged to wear their costumes and participate in various activities.',
        'priority' => 'high'
      ],
      [
        'title' => 'Report Card Distribution',
        'date' => 'November 08, 2025',
        'category' => 'Academic',
        'content' => 'Report cards for the 2nd Quarter will be distributed on November 25, 2025. Parents are requested to check the grades and sign the cards.',
        'priority' => 'medium'
      ],
    ];
  }
} catch (PDOException $e) {
  die("Database error: " . $e->getMessage());
}

// Fetch document requests from database
$stmt = $conn->prepare("
    SELECT 
        request_id,
        document_type,
        purpose,
        copies,
        status,
        DATE_FORMAT(date_requested, '%M %d, %Y') as date_requested,
        DATE_FORMAT(date_processed, '%M %d, %Y') as date_processed,
        notes
    FROM document_requests
    WHERE student_code = :student_code
    ORDER BY date_requested DESC
");
$stmt->execute(['student_code' => $student_code]);
$document_requests = $stmt->fetchAll();

// Fetch appointments from database
$stmt = $conn->prepare("
    SELECT 
        appointment_id,
        appointment_type,
        DATE_FORMAT(appointment_date, '%Y-%m-%d') as appointment_date,
        appointment_time,
        status,
        notes,
        DATE_FORMAT(created_at, '%M %d, %Y') as date_created
    FROM appointments
    WHERE student_code = :student_code
    ORDER BY appointment_date DESC, appointment_time DESC
");
$stmt->execute(['student_code' => $student_code]);
$appointments = $stmt->fetchAll();

// Fetch payment details from database
$stmt = $conn->prepare("
    SELECT 
        total_fee,
        amount_paid,
        balance,
        due_date,
        status
    FROM student_balances
    WHERE student_code = :student_code
    AND school_year = :school_year
    LIMIT 1
");
$stmt->execute([
  'student_code' => $student_code,
  'school_year' => $current_school_year
]);
$balance_data = $stmt->fetch();

// Fetch payment history
$stmt = $conn->prepare("
    SELECT 
        payment_date as date,
        amount,
        receipt_number as receipt,
        payment_method as method
    FROM payments
    WHERE student_code = :student_code
    AND school_year = :school_year
    AND status = 'paid'
    ORDER BY payment_date DESC
");
$stmt->execute([
  'student_code' => $student_code,
  'school_year' => $current_school_year
]);
$payment_history = $stmt->fetchAll();

$payment_details = [
  'tuition_fee' => $balance_data['total_fee'] ?? 25000.00,
  'paid_amount' => $balance_data['amount_paid'] ?? 0.00,
  'balance' => $balance_data['balance'] ?? 25000.00,
  'due_date' => $balance_data['due_date'] ?? '2025-12-15',
  'payment_history' => !empty($payment_history) ? $payment_history : [
    ['date' => '2025-08-15', 'amount' => 10000.00, 'receipt' => 'RCP-2025-001', 'method' => 'cash'],
    ['date' => '2025-10-10', 'amount' => 10000.00, 'receipt' => 'RCP-2025-002', 'method' => 'bank_transfer'],
  ]
];

// Update student info with payment status
$student_info['payment_status'] = ucfirst($balance_data['status'] ?? 'unpaid');
$student_info['balance'] = $payment_details['balance'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - Creative Dreams School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
      min-height: 100vh;
    }

    /* Header */
    .top-header {
      background: linear-gradient(135deg, #7cb342 0%, #689f38 100%);
      padding: 15px 30px;
      border-radius: 15px;
      margin: 20px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      position: fixed;
      top: 0;
      left: 250px;
      right: 20px;
      z-index: 1030;
    }

    .logo-section {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .logo {
      width: 50px;
      height: 50px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .brand-text h1 {
      color: white;
      font-size: 28px;
      font-weight: bold;
      margin: 0;
    }

    .brand-text p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 14px;
      margin: 0;
      font-style: italic;
    }

    .header-actions {
      display: flex;
      gap: 15px;
      align-items: center;
    }

    .icon-btn {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      cursor: pointer;
      transition: all 0.3s;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .icon-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: scale(1.1);
    }

    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #f44336;
      color: white;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      font-size: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }

    .user-info-header {
      display: flex;
      align-items: center;
      gap: 10px;
      color: white;
    }

    .user-avatar-header {
      width: 38px;
      height: 38px;
      background: white;
      color: #7cb342;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 14px;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      height: 100vh;
      background: white;
      position: fixed;
      top: 0;
      left: 0;
      padding: 20px;
      color: #2c3e50;
      overflow-y: auto;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      z-index: 1040;
    }

    .sidebar-logo {
      text-align: center;
      padding: 20px 0;
      border-bottom: 2px solid #e0e0e0;
      margin-bottom: 20px;
    }

    .sidebar-logo img {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      margin-bottom: 10px;
    }

    .sidebar-logo h6 {
      color: #7cb342;
      font-weight: bold;
      margin: 0;
    }

    .welcome-section {
      text-align: center;
      padding: 20px;
      border-bottom: 2px solid #e0e0e0;
      margin-bottom: 20px;
    }

    .profile-avatar {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #7cb342, #689f38);
      border-radius: 50%;
      margin: 0 auto 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      color: white;
      font-weight: bold;
    }

    .welcome-section h5 {
      font-weight: bold;
      color: #2c3e50;
      margin-bottom: 5px;
      font-size: 16px;
    }

    .welcome-section .badge {
      background: #7cb342;
      padding: 5px 15px;
      border-radius: 20px;
      font-size: 12px;
    }

    .nav-link {
      padding: 15px 20px;
      margin: 8px 0;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 15px;
      text-decoration: none;
      color: #2c3e50;
      font-weight: 500;
      font-size: 14px;
    }

    .nav-link:hover {
      background: #e8f5e9;
      color: #7cb342;
      transform: translateX(5px);
    }

    .nav-link.active {
      background: linear-gradient(135deg, #7cb342, #689f38);
      color: white;
    }

    .nav-link i {
      font-size: 20px;
      width: 25px;
    }

    .logout-link {
      margin-top: 20px;
      padding: 15px 20px;
      background: #f44336;
      color: white;
      border: none;
      border-radius: 10px;
      width: 100%;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 15px;
      text-decoration: none;
      justify-content: center;
    }

    .logout-link:hover {
      background: #d32f2f;
      color: white;
      transform: translateY(-2px);
    }

    /* Main Content */
    .main-content {
      margin-left: 250px;
      padding: 25px;
      margin-top: 100px;
    }

    .page-title {
      color: #2c3e50;
      font-weight: bold;
      margin-bottom: 25px;
      font-size: 28px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-box {
      border-radius: 15px;
      background: white;
      padding: 25px;
      margin-bottom: 20px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: all 0.3s;
    }

    .card-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
    }

    .section-content {
      display: none;
    }

    .section-content.active {
      display: block;
      animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
    }

    .info-card {
      background: white;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      text-align: center;
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
      height: 100%;
    }

    .info-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(135deg, #7cb342, #689f38);
    }

    .info-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
    }

    .info-card .icon {
      font-size: 45px;
      margin-bottom: 15px;
      color: #7cb342;
    }

    .info-card h3 {
      font-size: 13px;
      font-weight: bold;
      color: #666;
      text-transform: uppercase;
      margin-bottom: 10px;
      letter-spacing: 1px;
    }

    .info-card .value {
      font-size: 36px;
      font-weight: bold;
      color: #2c3e50;
      margin-bottom: 5px;
    }

    .info-card .label {
      font-size: 12px;
      color: #7cb342;
      font-weight: 600;
    }

    .schedule-day {
      background: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: all 0.3s;
    }

    .schedule-day:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
    }

    .schedule-day h6 {
      color: #7cb342;
      font-weight: bold;
      margin-bottom: 15px;
      font-size: 18px;
      border-bottom: 2px solid #e8f5e9;
      padding-bottom: 10px;
    }

    .schedule-item {
      padding: 12px 15px;
      border-left: 4px solid #7cb342;
      margin-bottom: 10px;
      background: #f8f9fa;
      border-radius: 8px;
      transition: all 0.3s;
    }

    .schedule-item:hover {
      background: #e8f5e9;
      transform: translateX(5px);
    }

    .document-card {
      border: 2px solid #e9ecef;
      border-radius: 15px;
      padding: 25px;
      transition: all 0.3s;
      cursor: pointer;
      text-align: center;
    }

    .document-card:hover {
      border-color: #7cb342;
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
      transform: translateY(-5px);
    }

    .document-card i {
      font-size: 48px;
      color: #7cb342;
      margin-bottom: 15px;
    }

    .status-badge {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .announcement-card {
      border-left: 4px solid #7cb342;
      transition: all 0.3s;
    }

    .announcement-card:hover {
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
      transform: translateX(5px);
    }

    .priority-high {
      border-left-color: #dc3545 !important;
    }

    .priority-medium {
      border-left-color: #ffc107 !important;
    }

    .priority-low {
      border-left-color: #6c757d !important;
    }

    .chart-container {
      position: relative;
      height: 300px;
    }

    .grading-table {
      font-size: 13px;
    }

    .grading-table td {
      padding: 8px;
    }

    .table {
      font-size: 14px;
    }

    .table thead {
      background: #f8f9fa;
      font-weight: 600;
    }

    .table-hover tbody tr:hover {
      background: #f8f9fa;
    }

    .btn-success {
      background: linear-gradient(135deg, #7cb342, #689f38);
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(124, 179, 66, 0.3);
    }

    .btn-outline-success {
      border: 2px solid #7cb342;
      color: #7cb342;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn-outline-success:hover {
      background: #7cb342;
      color: white;
      transform: translateY(-2px);
    }

    .form-control,
    .form-select {
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      padding: 10px 15px;
      transition: all 0.3s;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: #7cb342;
      box-shadow: 0 0 0 0.2rem rgba(124, 179, 66, 0.25);
    }

    .alert {
      border-radius: 10px;
      border: none;
      padding: 15px 20px;
    }

    .progress {
      border-radius: 10px;
      overflow: hidden;
    }

    .badge {
      padding: 6px 12px;
      border-radius: 6px;
      font-weight: 600;
    }

    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s;
      }

      .sidebar.show {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
        margin-top: 100px;
      }

      .top-header {
        left: 20px;
      }

      .brand-text h1 {
        font-size: 20px;
      }

      .brand-text p {
        font-size: 12px;
      }

      .page-title {
        font-size: 22px;
      }

      .info-card .value {
        font-size: 28px;
      }

      .performance-dropdown {
        position: relative;
      }

      .nav-parent {
        padding-right: 15px !important;
      }

      .dropdown-arrow {
        font-size: 12px;
        transition: transform 0.3s ease;
      }

      .nav-parent.expanded .dropdown-arrow {
        transform: rotate(180deg);
      }

      .performance-submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        padding-left: 0;
      }

      .performance-submenu.show {
        max-height: 300px;
      }

      .nav-child {
        padding-left: 60px !important;
        font-size: 13px;
        margin: 4px 0;
      }

      .nav-child:hover {
        padding-left: 65px !important;
      }

      .nav-child.active {
        background: linear-gradient(135deg, #7cb342, #689f38);
        color: white;
      }
    }
  </style>
</head>

<body>

  <!-- Header -->
  <div class="top-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <div class="logo-section">
        <div class="logo">
          <img src="images/cdslogo.png" alt="School Logo">
        </div>
        <div class="brand-text">
          <h1>Creative Dreams</h1>
          <p>Inspire. Learn. Achieve.</p>
        </div>
      </div>
      <div class="header-actions">
        <button class="icon-btn" title="Notifications">
          <i class="bi bi-bell-fill"></i>
          <span class="notification-badge">3</span>
        </button>
        <div class="user-info-header">
          <span class="fw-semibold"><?php echo $student_info['full_name']; ?></span>
          <div class="user-avatar-header">
            <?php echo $initials; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-logo">
      <img src="images/cdslogo.png" alt="Logo">
      <h6>CDS Portal</h6>
    </div>

    <div class="welcome-section">
      <div class="profile-avatar">
        <?php echo $initials; ?>
      </div>
      <h5><?php echo strtoupper($student_info['first_name']); ?></h5>
      <span class="badge">Student</span>
    </div>

    <nav>
      <a href="#" class="nav-link active" data-section="overview">
        <i class="bi bi-house-door-fill"></i>
        <span>DASHBOARD</span>
      </a>
      <!-- Performance Dropdown Menu -->
      <div class="performance-dropdown">
        <a href="#" class="nav-link nav-parent" id="performanceToggle">
          <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-3">
              <i class="bi bi-graph-up-arrow"></i>
              <span>PERFORMANCE</span>
            </div>
            <i class="bi bi-chevron-down dropdown-arrow"></i>
          </div>
        </a>
        <div class="performance-submenu">
          <a href="#" class="nav-link nav-child" data-section="performance-analytics">
            <i class="bi bi-cpu"></i>
            <span>AI Analytics</span>
          </a>
          <a href="#" class="nav-link nav-child" data-section="grades">
            <i class="bi bi-journal-text"></i>
            <span>Grades</span>
          </a>
          <a href="#" class="nav-link nav-child" data-section="attendance">
            <i class="bi bi-calendar-check"></i>
            <span>Attendance</span>
          </a>
        </div>
      </div>
      <a href="#" class="nav-link" data-section="schedule">
        <i class="bi bi-calendar-week"></i>
        <span>SCHEDULE</span>
      </a>
      <a href="#" class="nav-link" data-section="documents">
        <i class="bi bi-folder2-open"></i>
        <span>DOCUMENTS</span>
      </a>
      <a href="#" class="nav-link" data-section="appointments">
        <i class="bi bi-calendar-check"></i>
        <span>APPOINTMENTS</span>
      </a>
      <a href="#" class="nav-link" data-section="payment">
        <i class="bi bi-credit-card"></i>
        <span>PAYMENT</span>
      </a>
      <a href="#" class="nav-link" data-section="announcements">
        <i class="bi bi-megaphone"></i>
        <span>ANNOUNCEMENTS</span>
      </a>
    </nav>

    <a href="logout.php" class="logout-link">
      <i class="bi bi-box-arrow-right"></i>
      <span>LOGOUT</span>
    </a>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <!-- OVERVIEW SECTION -->
    <div id="overview-section" class="section-content active">
      <h4 class="page-title">
        <i class="bi bi-speedometer2"></i> Student Dashboard
      </h4>

      <!-- Student Information Card -->
      <div class="card-box mb-4">
        <h6 class="fw-bold mb-4" style="color: #7cb342; font-size: 16px;">
          <i class="bi bi-person-circle"></i> STUDENT INFORMATION
        </h6>
        <div class="row">
          <div class="col-md-3 text-center border-end">
            <div class="profile-avatar mb-3"><?php echo $initials; ?></div>
            <span class="badge bg-success">Enrolled</span>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">FULL NAME</small>
              <strong style="color: #2c3e50;"><?php echo $student_info['full_name']; ?></strong>
            </div>
            <div class="mb-3">
              <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">GRADE AND SECTION</small>
              <strong style="color: #2c3e50;"><?php echo $student_info['grade_level'] . ' - ' . $student_info['section']; ?></strong>
            </div>
            <div>
              <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">CLASS ADVISER</small>
              <strong style="color: #2c3e50;"><?php echo $student_info['adviser']; ?></strong>
            </div>
          </div>
          <div class="col-md-5">
            <div class="mb-3">
              <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">STUDENT CODE (LRN)</small>
              <strong style="color: #2c3e50;"><?php echo $student_info['lrn']; ?></strong>
            </div>
            <div class="mb-3">
              <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">SCHOOL YEAR</small>
              <strong style="color: #2c3e50;"><?php echo $student_info['school_year']; ?></strong>
            </div>
            <div>
              <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">CURRENT QUARTER</small>
              <strong style="color: #2c3e50;"><?php echo $student_info['current_quarter']; ?></strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Cards -->
      <div class="row mb-4">
        <div class="col-md-6 mb-3">
          <div class="info-card">
            <div class="d-flex align-items-center">
              <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                <i class="bi bi-check-circle-fill"></i>
              </div>
              <div class="text-start">
                <h3>ENROLLMENT STATUS</h3>
                <div class="value" style="font-size: 24px;"><?php echo $student_info['enrollment_status']; ?></div>
                <div class="label">Academic Year: <?php echo $student_info['school_year']; ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6 mb-3">
          <div class="info-card">
            <div class="d-flex align-items-center">
              <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                <i class="bi bi-credit-card-fill"></i>
              </div>
              <div class="text-start">
                <h3>PAYMENT STATUS</h3>
                <div class="value" style="font-size: 24px;"><?php echo $student_info['payment_status']; ?></div>
                <div class="label">Balance: ₱<?php echo number_format($student_info['balance'], 2); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Parent Information Card -->
      <div class="card-box mb-4">
        <h6 class="fw-bold mb-4" style="color: #7cb342; font-size: 16px;">
          <i class="bi bi-people-fill"></i> PARENT/GUARDIAN INFORMATION
        </h6>
        <div class="row">
          <div class="col-md-6">
            <div class="d-flex align-items-start mb-3">
              <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3" style="width: 45px; height: 45px; font-size: 20px;">
                <i class="bi bi-person-fill"></i>
              </div>
              <div>
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">PARENT/GUARDIAN NAME</small>
                <strong style="color: #2c3e50;"><?php echo $parent_info['name']; ?></strong>
              </div>
            </div>
            <div class="d-flex align-items-start mb-3">
              <div class="stat-icon bg-success bg-opacity-10 text-success me-3" style="width: 45px; height: 45px; font-size: 20px;">
                <i class="bi bi-telephone-fill"></i>
              </div>
              <div>
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">CONTACT NUMBER</small>
                <strong style="color: #2c3e50;"><?php echo $parent_info['contact']; ?></strong>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="d-flex align-items-start mb-3">
              <div class="stat-icon bg-info bg-opacity-10 text-info me-3" style="width: 45px; height: 45px; font-size: 20px;">
                <i class="bi bi-envelope-fill"></i>
              </div>
              <div>
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">EMAIL ADDRESS</small>
                <strong style="color: #2c3e50;"><?php echo $parent_info['email']; ?></strong>
              </div>
            </div>
            <div class="d-flex align-items-start mb-3">
              <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3" style="width: 45px; height: 45px; font-size: 20px;">
                <i class="bi bi-house-fill"></i>
              </div>
              <div>
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">HOME ADDRESS</small>
                <strong style="color: #2c3e50;"><?php echo $parent_info['address']; ?></strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Performance Analytics Title -->
      <h5 class="fw-bold mb-3" style="color: #2c3e50; font-size: 22px;">
        <i class="bi bi-graph-up-arrow"></i> Academic Performance Analytics
      </h5>

      <!-- Performance Statistics -->
      <div class="row mb-4">
        <div class="col-md-3 mb-3">
          <div class="info-card">
            <div class="icon">
              <i class="bi bi-bar-chart-fill"></i>
            </div>
            <h3>CURRENT AVERAGE</h3>
            <div class="value">87.5%</div>
            <div class="label">
              <i class="bi bi-arrow-up"></i> Improving
            </div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="info-card">
            <div class="icon">
              <i class="bi bi-trophy-fill"></i>
            </div>
            <h3>PASSING SUBJECTS</h3>
            <div class="value">7/7</div>
            <div class="label">All subjects passed</div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="info-card">
            <div class="icon">
              <i class="bi bi-calendar-check-fill"></i>
            </div>
            <h3>ATTENDANCE RATE</h3>
            <div class="value">95%</div>
            <div class="label">Excellent</div>
          </div>
        </div>
        <div class="col-md-3 mb-3">
          <div class="info-card">
            <div class="icon">
              <i class="bi bi-graph-up"></i>
            </div>
            <h3>PERFORMANCE LEVEL</h3>
            <div class="value" style="font-size: 28px;">Good</div>
            <div class="label">Keep it up!</div>
          </div>
        </div>
      </div>

      <!-- Performance Charts -->
      <div class="row">
        <div class="col-md-8 mb-4">
          <div class="card-box">
            <h6 class="fw-bold mb-3" style="color: #7cb342;">
              <i class="bi bi-activity"></i> SUBJECT PERFORMANCE TRENDS
            </h6>
            <div class="chart-container">
              <canvas id="performanceChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="card-box">
            <h6 class="fw-bold mb-3" style="color: #7cb342;">
              <i class="bi bi-shield-check"></i> PERFORMANCE RISK ASSESSMENT
            </h6>
            <div class="alert alert-success" role="alert">
              <i class="bi bi-check-circle-fill"></i>
              <strong>Low Risk</strong>
              <p class="mb-0 small">Student is performing well across all subjects.</p>
            </div>
            <div class="mt-3">
              <h6 class="small fw-bold" style="color: #7cb342;">Strengths:</h6>
              <ul class="small mb-3">
                <li>MAPEH (94%)</li>
                <li>Science (88%)</li>
                <li>Mathematics (86%)</li>
              </ul>
              <h6 class="small fw-bold" style="color: #ffc107;">Areas to Improve:</h6>
              <ul class="small mb-0">
                <li>Filipino (84%)</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- AI Performance Insights -->
      <div class="card-box" style="background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-left: 5px solid #ffc107;">
        <h6 class="fw-bold mb-3" style="color: #2c3e50;">
          <i class="bi bi-lightning-fill text-warning"></i> AI PERFORMANCE INSIGHTS
        </h6>
        <p class="mb-2"><strong>Prediction:</strong> Based on current trends, the student is likely to maintain good academic standing.</p>
        <p class="mb-2"><strong>Recommendation:</strong> Focus on improving Filipino subject to achieve excellent grades across all subjects.</p>
        <p class="mb-0"><strong>Attendance Impact:</strong> Excellent attendance rate contributes positively to overall performance.</p>
      </div>
    </div>

    <!-- GRADES SECTION -->
    <div id="grades-section" class="section-content">
      <h4 class="fw-bold mb-3">Academic Grades</h4>

      <div class="row">
        <div class="col-md-9">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div>
                <h5 class="fw-bold mb-1">Grade Report</h5>
                <p class="text-muted mb-0">Academic Year <?php echo $student_info['school_year']; ?></p>
              </div>
              <div>
                <label class="me-2 fw-semibold">Select Quarter:</label>
                <select id="quarterSelect" class="form-select d-inline-block w-auto">
                  <option value="1st">1st Quarter</option>
                  <option value="2nd" selected>2nd Quarter</option>
                  <option value="3rd">3rd Quarter</option>
                  <option value="4th">4th Quarter</option>
                </select>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Subject</th>
                    <th class="text-center">Grade</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody id="gradesTableBody">
                  <?php foreach ($grades_data['2nd'] as $grade): ?>
                    <tr>
                      <td><strong><?php echo $grade['subject']; ?></strong></td>
                      <td class="text-center">
                        <h5 class="mb-0 fw-bold <?php echo $grade['grade'] >= 90 ? 'text-success' : ($grade['grade'] >= 80 ? 'text-primary' : ($grade['grade'] > 0 ? 'text-warning' : 'text-muted')); ?>">
                          <?php echo $grade['grade'] > 0 ? $grade['grade'] : '-'; ?>
                        </h5>
                      </td>
                      <td>
                        <?php
                        if ($grade['grade'] >= 90) {
                          echo '<span class="badge bg-success">Outstanding</span>';
                        } elseif ($grade['grade'] >= 85) {
                          echo '<span class="badge bg-primary">Very Satisfactory</span>';
                        } elseif ($grade['grade'] >= 80) {
                          echo '<span class="badge bg-info">Satisfactory</span>';
                        } elseif ($grade['grade'] >= 75) {
                          echo '<span class="badge bg-warning">Fairly Satisfactory</span>';
                        } elseif ($grade['grade'] > 0) {
                          echo '<span class="badge bg-danger">Did Not Meet</span>';
                        } else {
                          echo '<span class="badge bg-secondary">Not Available</span>';
                        }
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="row mt-4 pt-3 border-top">
              <div class="col-md-4 text-center">
                <h4 class="fw-bold mb-0" id="quarterAverage">87.0</h4>
                <p class="text-muted mb-0">General Average</p>
              </div>
              <div class="col-md-4 text-center">
                <h4 class="fw-bold mb-0" id="passingSubjects">7/7</h4>
                <p class="text-muted mb-0">Passed Subjects</p>
              </div>
              <div class="col-md-4 text-center">
                <h4 class="fw-bold mb-0 text-success" id="quarterStatus">Passed</h4>
                <p class="text-muted mb-0">Quarter Status</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Grading Scale -->
        <div class="col-md-3">
          <div class="card-box">
            <h6 class="fw-bold mb-3">Grading Scale</h6>
            <table class="table table-sm grading-table">
              <thead>
                <tr>
                  <th>Grade</th>
                  <th>Descriptor</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>90-100</strong></td>
                  <td>Outstanding</td>
                </tr>
                <tr>
                  <td><strong>85-89</strong></td>
                  <td>Very Satisfactory</td>
                </tr>
                <tr>
                  <td><strong>80-84</strong></td>
                  <td>Satisfactory</td>
                </tr>
                <tr>
                  <td><strong>75-79</strong></td>
                  <td>Fairly Satisfactory</td>
                </tr>
                <tr>
                  <td><strong>Below 75</strong></td>
                  <td>Did Not Meet</td>
                </tr>
              </tbody>
            </table>

            <div class="alert alert-info mt-3" role="alert">
              <small><strong>Note:</strong> Passing grade is 75 and above for all subjects.</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SCHEDULE SECTION -->
    <div id="schedule-section" class="section-content">
      <h4 class="fw-bold mb-3">Class Schedule</h4>

      <div class="card-box mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="fw-bold mb-1">Weekly Class Schedule</h6>
            <p class="text-muted mb-0">Academic Year <?php echo $student_info['school_year']; ?> | <?php echo $student_info['current_quarter']; ?></p>
          </div>
          <button class="btn btn-sm btn-outline-success" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
          </button>
        </div>
      </div>

      <?php
      $days = array_keys($schedule_data);
      for ($i = 0; $i < count($days); $i += 2):
      ?>
        <div class="row mb-3">
          <!-- First Day -->
          <div class="col-md-6">
            <div class="schedule-day">
              <h6 class="fw-bold text-success mb-3">
                <i class="bi bi-calendar-day"></i> <?php echo $days[$i]; ?>
              </h6>
              <?php foreach ($schedule_data[$days[$i]] as $class): ?>
                <div class="schedule-item">
                  <div class="d-flex justify-content-between">
                    <div>
                      <strong><?php echo $class['subject']; ?></strong>
                      <small class="d-block text-muted"><?php echo $class['room']; ?></small>
                    </div>
                    <div class="text-end">
                      <small class="text-muted"><?php echo $class['time']; ?></small>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Second Day -->
          <?php if (isset($days[$i + 1])): ?>
            <div class="col-md-6">
              <div class="schedule-day">
                <h6 class="fw-bold text-success mb-3">
                  <i class="bi bi-calendar-day"></i> <?php echo $days[$i + 1]; ?>
                </h6>
                <?php foreach ($schedule_data[$days[$i + 1]] as $class): ?>
                  <div class="schedule-item">
                    <div class="d-flex justify-content-between">
                      <div>
                        <strong><?php echo $class['subject']; ?></strong>
                        <small class="d-block text-muted"><?php echo $class['room']; ?></small>
                      </div>
                      <div class="text-end">
                        <small class="text-muted"><?php echo $class['time']; ?></small>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>

    <!-- DOCUMENTS SECTION - Replace the existing documents section -->
    <div id="documents-section" class="section-content">
      <h4 class="fw-bold mb-3">Document Requests</h4>

      <div class="row mb-4">
        <div class="col-md-8">
          <!-- Request Form -->
          <div class="card-box mb-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-plus"></i> Request New Document</h6>
            <form id="documentRequestForm">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
                  <select class="form-select" id="documentType" name="document_type" required>
                    <option value="">Select Document</option>
                    <option value="Certificate of Enrollment">Certificate of Enrollment</option>
                    <option value="Report Card">Report Card</option>
                    <option value="Good Moral Certificate">Good Moral Certificate</option>
                    <option value="Certificate of Registration">Certificate of Registration</option>
                    <option value="Transcript of Records">Transcript of Records</option>
                    <option value="Honorable Dismissal">Honorable Dismissal</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Number of Copies <span class="text-danger">*</span></label>
                  <input type="number" class="form-control" id="documentCopies" name="copies" min="1" max="10" value="1" required>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
                <textarea class="form-control" id="documentPurpose" name="purpose" rows="3" placeholder="State the purpose of this document request" required></textarea>
              </div>
              <button type="submit" class="btn btn-success">
                <i class="bi bi-send"></i> Submit Request
              </button>
            </form>
          </div>

          <!-- Request History -->
          <div class="card-box">
            <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> My Document Requests</h6>
            <?php if (empty($document_requests)): ?>
              <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                <p class="mt-2">No document requests yet</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Document</th>
                      <th>Copies</th>
                      <th>Date Requested</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($document_requests as $req): ?>
                      <tr>
                        <td>
                          <strong><?php echo htmlspecialchars($req['document_type']); ?></strong>
                          <br><small class="text-muted"><?php echo htmlspecialchars($req['purpose']); ?></small>
                        </td>
                        <td><?php echo $req['copies']; ?></td>
                        <td><?php echo $req['date_requested']; ?></td>
                        <td>
                          <?php
                          $status_class = [
                            'requested' => 'warning',
                            'processing' => 'info',
                            'approved' => 'primary',
                            'ready' => 'success',
                            'claimed' => 'secondary',
                            'rejected' => 'danger'
                          ];
                          $class = $status_class[$req['status']] ?? 'secondary';
                          ?>
                          <span class="badge bg-<?php echo $class; ?>">
                            <?php echo ucfirst($req['status']); ?>
                          </span>
                          <?php if ($req['notes']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($req['notes']); ?></small>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if (in_array($req['status'], ['requested', 'processing'])): ?>
                            <button class="btn btn-sm btn-outline-danger cancel-document-btn"
                              data-id="<?php echo $req['request_id']; ?>">
                              <i class="bi bi-x-circle"></i> Cancel
                            </button>
                          <?php elseif ($req['status'] === 'ready'): ?>
                            <span class="badge bg-success">
                              <i class="bi bi-check-circle"></i> Ready for Pick-up
                            </span>
                          <?php elseif ($req['status'] === 'claimed'): ?>
                            <span class="text-muted small">
                              Claimed: <?php echo $req['date_processed']; ?>
                            </span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card-box" style="background-color: #e8f5e9;">
            <h6 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> Important Information</h6>
            <ul class="small mb-0">
              <li class="mb-2">Processing time: 3-5 business days</li>
              <li class="mb-2">Pick-up: Registrar's Office</li>
              <li class="mb-2">Office hours: Mon-Fri, 8AM-5PM</li>
              <li class="mb-2">Bring valid ID when claiming</li>
              <li class="mb-0">Check status regularly for updates</li>
            </ul>
          </div>

          <div class="card-box mt-3" style="background-color: #fff3cd;">
            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle"></i> Requirements</h6>
            <p class="small mb-2">All account balances must be settled before requesting documents.</p>
            <p class="small mb-0">Current Balance: <strong class="text-danger">₱<?php echo number_format($student_info['balance'], 2); ?></strong></p>
          </div>

          <div class="card-box mt-3" style="background-color: #e3f2fd;">
            <h6 class="fw-bold mb-2"><i class="bi bi-question-circle"></i> Need Help?</h6>
            <p class="small mb-2">Contact the Registrar's Office for inquiries:</p>
            <p class="small mb-1"><i class="bi bi-telephone"></i> <?php echo getSystemSetting($conn, 'school_contact', '(02) 1234-5678'); ?></p>
            <p class="small mb-0"><i class="bi bi-envelope"></i> registrar@creativedreams.edu.ph</p>
          </div>
        </div>
      </div>
    </div>

    <!-- APPOINTMENTS SECTION - Replace the existing appointments section -->
    <div id="appointments-section" class="section-content">
      <h4 class="fw-bold mb-3">Schedule Appointment</h4>

      <div class="row">
        <div class="col-md-6">
          <div class="card-box">
            <h6 class="fw-bold mb-3">Book New Appointment</h6>
            <form id="appointmentForm">
              <div class="mb-3">
                <label class="form-label fw-semibold">Appointment Type <span class="text-danger">*</span></label>
                <select class="form-select" id="appointmentType" name="appointment_type" required>
                  <option value="">Select Type</option>
                  <option value="Tuition Payment">Tuition Payment</option>
                  <option value="Parent-Teacher Meeting">Parent-Teacher Meeting</option>
                  <option value="Document Inquiry">Document Inquiry</option>
                  <option value="Guidance Counseling">Guidance Counseling</option>
                  <option value="Academic Concerns">Academic Concerns</option>
                  <option value="Others">Others</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Preferred Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="appointmentDate" name="appointment_date" required>
                <small class="text-muted">Weekends are not available</small>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Preferred Time <span class="text-danger">*</span></label>
                <select class="form-select" id="appointmentTime" name="appointment_time" required>
                  <option value="">Select Time</option>
                  <option value="08:00 AM">08:00 AM</option>
                  <option value="09:00 AM">09:00 AM</option>
                  <option value="10:00 AM">10:00 AM</option>
                  <option value="11:00 AM">11:00 AM</option>
                  <option value="01:00 PM">01:00 PM</option>
                  <option value="02:00 PM">02:00 PM</option>
                  <option value="03:00 PM">03:00 PM</option>
                  <option value="04:00 PM">04:00 PM</option>
                </select>
                <small id="timeSlotMessage" class="text-muted"></small>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Notes (Optional)</label>
                <textarea class="form-control" id="appointmentNotes" name="notes" rows="3" placeholder="Add any additional information"></textarea>
              </div>
              <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-calendar-plus"></i> Book Appointment
              </button>
            </form>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card-box">
            <h6 class="fw-bold mb-3">My Appointments</h6>
            <?php if (empty($appointments)): ?>
              <div class="text-center py-4 text-muted">
                <i class="bi bi-calendar-x" style="font-size: 48px;"></i>
                <p class="mt-2">No appointments yet</p>
              </div>
            <?php else: ?>
              <?php foreach ($appointments as $apt): ?>
                <div class="card-box mb-2" style="background-color: #f8f9fa;">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($apt['appointment_type']); ?></h6>
                      <p class="mb-1 small">
                        <i class="bi bi-calendar3"></i> <?php echo date('F d, Y', strtotime($apt['appointment_date'])); ?>
                      </p>
                      <p class="mb-1 small">
                        <i class="bi bi-clock"></i> <?php echo $apt['appointment_time']; ?>
                      </p>
                      <?php if ($apt['notes']): ?>
                        <p class="mb-0 small text-muted">
                          <i class="bi bi-chat-dots"></i> <?php echo htmlspecialchars($apt['notes']); ?>
                        </p>
                      <?php endif; ?>
                    </div>
                    <div>
                      <?php
                      $status_class = [
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'completed' => 'secondary',
                        'cancelled' => 'danger'
                      ];
                      $class = $status_class[$apt['status']] ?? 'secondary';
                      ?>
                      <span class="badge bg-<?php echo $class; ?>">
                        <?php echo ucfirst($apt['status']); ?>
                      </span>
                    </div>
                  </div>
                  <?php if ($apt['status'] === 'pending'): ?>
                    <div class="mt-2">
                      <button class="btn btn-sm btn-outline-danger cancel-appointment-btn"
                        data-id="<?php echo $apt['appointment_id']; ?>">
                        <i class="bi bi-x-circle"></i> Cancel
                      </button>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="card-box mt-3" style="background-color: #e3f2fd;">
            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle"></i> Appointment Guidelines</h6>
            <ul class="small mb-0">
              <li class="mb-2">Appointments available Monday to Friday only</li>
              <li class="mb-2">Please arrive 10 minutes before your scheduled time</li>
              <li class="mb-2">Bring necessary documents related to your appointment</li>
              <li class="mb-0">Contact the office if you need to reschedule</li>
            </ul>
          </div>
        </div>
      </div>
    </div>


    <!-- PAYMENT SECTION -->
    <div id="payment-section" class="section-content">
      <h4 class="fw-bold mb-3">Payment Information</h4>

      <div class="row">
        <div class="col-md-8">
          <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="fw-bold mb-0">Tuition Fee Summary</h6>
              <button class="btn btn-success btn-sm" onclick="printReceipt()">
                <i class="bi bi-printer"></i> Print Statement
              </button>
            </div>

            <div class="row mb-4">
              <div class="col-md-4 text-center border-end">
                <h3 class="fw-bold text-primary mb-1">₱<?php echo number_format($payment_details['tuition_fee'], 2); ?></h3>
                <p class="text-muted mb-0">Total Tuition Fee</p>
              </div>
              <div class="col-md-4 text-center border-end">
                <h3 class="fw-bold text-success mb-1">₱<?php echo number_format($payment_details['paid_amount'], 2); ?></h3>
                <p class="text-muted mb-0">Amount Paid</p>
              </div>
              <div class="col-md-4 text-center">
                <h3 class="fw-bold text-warning mb-1">₱<?php echo number_format($payment_details['balance'], 2); ?></h3>
                <p class="text-muted mb-0">Remaining Balance</p>
              </div>
            </div>

            <div class="alert alert-info" role="alert">
              <i class="bi bi-info-circle-fill"></i>
              <strong>Payment Due Date:</strong> <?php echo date('F d, Y', strtotime($payment_details['due_date'])); ?>
            </div>

            <div class="progress mb-4" style="height: 30px;">
              <?php
              $percentage = ($payment_details['paid_amount'] / $payment_details['tuition_fee']) * 100;
              ?>
              <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%">
                <?php echo round($percentage); ?>% Paid
              </div>
            </div>

            <div class="mt-4">
              <h6 class="fw-bold mb-3">Payment History</h6>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Receipt No.</th>
                      <th>Amount</th>
                      <th>Payment Method</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($payment_details['payment_history'] as $payment): ?>
                      <tr>
                        <td><?php echo date('M d, Y', strtotime($payment['date'])); ?></td>
                        <td><strong><?php echo $payment['receipt']; ?></strong></td>
                        <td class="text-success fw-bold">₱<?php echo number_format($payment['amount'], 2); ?></td>
                        <td>
                          <span class="badge bg-primary">
                            <i class="bi bi-<?php echo $payment['method'] == 'Cash' ? 'cash' : 'bank'; ?>"></i>
                            <?php echo $payment['method']; ?>
                          </span>
                        </td>
                        <td>
                          <button class="btn btn-sm btn-outline-success" onclick="printIndividualReceipt('<?php echo $payment['receipt']; ?>')">
                            <i class="bi bi-printer"></i> Print
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card-box" style="background-color: #e8f5e9;">
            <h6 class="fw-bold mb-3"><i class="bi bi-cash-coin"></i> Payment Information</h6>
            <p class="small mb-2"><strong>Payment Methods Available:</strong></p>
            <ul class="small mb-3">
              <li>Cash Payment - School Cashier</li>
              <li>Bank Deposit/Transfer</li>
              <li>Check Payment</li>
            </ul>

            <div class="alert alert-warning small" role="alert">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <strong>Note:</strong> All payments must be made at the school cashier or designated bank. Online payments are not available at this time.
            </div>

            <p class="small mb-2 mt-3"><strong>Cashier Office Hours:</strong></p>
            <p class="small mb-0">
              <i class="bi bi-clock"></i> Monday - Friday<br>
              8:00 AM - 12:00 PM<br>
              1:00 PM - 5:00 PM
            </p>
          </div>

          <div class="card-box mt-3" style="background-color: #e3f2fd;">
            <h6 class="fw-bold mb-3"><i class="bi bi-bank"></i> Bank Details</h6>
            <p class="small mb-1"><strong>Bank Name:</strong> BDO Unibank</p>
            <p class="small mb-1"><strong>Account Name:</strong><br>Creative Dreams School</p>
            <p class="small mb-3"><strong>Account Number:</strong><br>1234-5678-9012</p>
            <p class="small text-muted mb-0">
              <i class="bi bi-info-circle"></i> Please bring the deposit slip to the cashier for verification.
            </p>
          </div>

          <div class="card-box mt-3">
            <h6 class="fw-bold mb-2"><i class="bi bi-question-circle"></i> Need Help?</h6>
            <p class="small mb-2">Contact the Accounting Office:</p>
            <p class="small mb-0">
              <i class="bi bi-telephone-fill"></i> (02) 1234-5678<br>
              <i class="bi bi-envelope-fill"></i> accounting@cds.edu.ph
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- ANNOUNCEMENTS SECTION -->
    <div id="announcements-section" class="section-content">
      <h4 class="fw-bold mb-3">School Announcements</h4>

      <?php foreach ($announcements as $announcement): ?>
        <div class="card-box announcement-card mb-3 priority-<?php echo $announcement['priority']; ?>">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h5 class="fw-bold mb-1"><?php echo $announcement['title']; ?></h5>
              <div>
                <span class="badge bg-secondary me-2"><?php echo $announcement['category']; ?></span>
                <?php if ($announcement['priority'] == 'high'): ?>
                  <span class="badge bg-danger">Important</span>
                <?php endif; ?>
              </div>
            </div>
            <small class="text-muted">
              <i class="bi bi-calendar3"></i> <?php echo $announcement['date']; ?>
            </small>
          </div>
          <p class="mb-0"><?php echo $announcement['content']; ?></p>
        </div>
      <?php endforeach; ?>

      <div class="text-center mt-4">
        <button class="btn btn-outline-success">
          <i class="bi bi-arrow-clockwise"></i> Load More Announcements
        </button>
      </div>
    </div>

  </div>

  <!-- Document Request Modal -->
  <div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog modal-document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDocumentTitle">Request Document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="documentRequestForm">
            <div class="mb-3">
              <label class="form-label fw-semibold">Document Type</label>
              <input type="text" class="form-control" id="modalDocType" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Number of Copies</label>
              <input type="number" class="form-control" min="1" max="5" value="1" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Purpose</label>
              <textarea class="form-control" rows="3" placeholder="e.g., Transfer to another school, Scholarship application" required></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Additional Notes (Optional)</label>
              <textarea class="form-control" rows="2"></textarea>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" onclick="submitDocumentRequest()">Submit Request</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Navigation
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.section-content').forEach(section => section.classList.remove('active'));
        const sectionId = this.getAttribute('data-section') + '-section';
        document.getElementById(sectionId).classList.add('active');
      });
    });

    // Grades data
    const gradesData = <?php echo json_encode($grades_data); ?>;

    // Quarter selection
    document.getElementById('quarterSelect').addEventListener('change', function() {
      const quarter = this.value;
      const grades = gradesData[quarter];

      let tableHTML = '';
      let total = 0;
      let count = 0;
      let passing = 0;

      grades.forEach(grade => {
        let badge = '';
        let colorClass = '';

        if (grade.grade > 0) {
          if (grade.grade >= 90) {
            badge = '<span class="badge bg-success">Outstanding</span>';
            colorClass = 'text-success';
            passing++;
          } else if (grade.grade >= 85) {
            badge = '<span class="badge bg-primary">Very Satisfactory</span>';
            colorClass = 'text-primary';
            passing++;
          } else if (grade.grade >= 80) {
            badge = '<span class="badge bg-info">Satisfactory</span>';
            colorClass = 'text-primary';
            passing++;
          } else if (grade.grade >= 75) {
            badge = '<span class="badge bg-warning">Fairly Satisfactory</span>';
            colorClass = 'text-warning';
            passing++;
          } else {
            badge = '<span class="badge bg-danger">Did Not Meet</span>';
            colorClass = 'text-danger';
          }
          total += grade.grade;
          count++;
        } else {
          badge = '<span class="badge bg-secondary">Not Available</span>';
          colorClass = 'text-muted';
        }

        tableHTML += `
          <tr>
            <td><strong>${grade.subject}</strong></td>
            <td class="text-center"><h5 class="mb-0 fw-bold ${colorClass}">${grade.grade > 0 ? grade.grade : '-'}</h5></td>
            <td>${badge}</td>
          </tr>
        `;
      });

      document.getElementById('gradesTableBody').innerHTML = tableHTML;

      const average = count > 0 ? (total / count).toFixed(1) : '0.0';
      document.getElementById('quarterAverage').textContent = average;
      document.getElementById('passingSubjects').textContent = `${passing}/${grades.length}`;

      if (count === 0) {
        document.getElementById('quarterStatus').textContent = 'Not Available';
        document.getElementById('quarterStatus').className = 'fw-bold mb-0 text-secondary';
      } else if (passing === grades.length) {
        document.getElementById('quarterStatus').textContent = 'Passed';
        document.getElementById('quarterStatus').className = 'fw-bold mb-0 text-success';
      } else {
        document.getElementById('quarterStatus').textContent = 'Needs Improvement';
        document.getElementById('quarterStatus').className = 'fw-bold mb-0 text-warning';
      }
    });

    // Performance Chart
    const ctx = document.getElementById('performanceChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'],
        datasets: [{
          label: 'Mathematics',
          data: [88, 86, null, null],
          borderColor: '#6a8e79',
          backgroundColor: 'rgba(106, 142, 121, 0.1)',
          tension: 0.4
        }, {
          label: 'Science',
          data: [90, 88, null, null],
          borderColor: '#4a90e2',
          backgroundColor: 'rgba(74, 144, 226, 0.1)',
          tension: 0.4
        }, {
          label: 'English',
          data: [85, 87, null, null],
          borderColor: '#f5a623',
          backgroundColor: 'rgba(245, 166, 35, 0.1)',
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: false,
            min: 70,
            max: 100
          }
        }
      }
    });

    // Document Request Form Handler
    document.getElementById('documentRequestForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const formData = new FormData(this);
      formData.append('action', 'submit');

      fetch('document_request_handler.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred. Please try again.');
        });
    });

    // Cancel Document Request
    document.querySelectorAll('.cancel-document-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to cancel this document request?')) {
          return;
        }

        const requestId = this.getAttribute('data-id');
        const formData = new FormData();
        formData.append('action', 'cancel');
        formData.append('request_id', requestId);

        fetch('document_request_handler.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert(data.message);
              location.reload();
            } else {
              alert('Error: ' + data.message);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
          });
      });
    });

    // Appointment Form - Date validation and time slot checking
    const appointmentDateInput = document.getElementById('appointmentDate');
    const appointmentTimeInput = document.getElementById('appointmentTime');
    const timeSlotMessage = document.getElementById('timeSlotMessage');

    // Set minimum date to today
    const today = new Date();
    appointmentDateInput.min = today.toISOString().split('T')[0];

    // Disable weekends
    appointmentDateInput.addEventListener('input', function() {
      const selectedDate = new Date(this.value);
      const dayOfWeek = selectedDate.getDay();

      if (dayOfWeek === 0 || dayOfWeek === 6) {
        alert('Weekends are not available for appointments. Please select a weekday.');
        this.value = '';
        return;
      }

      // Reset time selection when date changes
      appointmentTimeInput.value = '';
      timeSlotMessage.textContent = 'Please select a time slot';
      timeSlotMessage.className = 'text-muted';
    });

    // Check time slot availability
    appointmentTimeInput.addEventListener('change', function() {
      const date = appointmentDateInput.value;
      const time = this.value;

      if (!date || !time) return;

      const formData = new FormData();
      formData.append('action', 'get_booked_slots');
      formData.append('date', date);

      fetch('appointment_handler.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            if (data.booked_times.includes(time)) {
              timeSlotMessage.textContent = '⚠ This time slot is already booked. Please select another time.';
              timeSlotMessage.className = 'text-danger';
              this.value = '';
            } else {
              timeSlotMessage.textContent = '✓ This time slot is available';
              timeSlotMessage.className = 'text-success';
            }
          }
        })
        .catch(error => {
          console.error('Error:', error);
        });
    });

    // Appointment Form Handler
    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const formData = new FormData(this);
      formData.append('action', 'book');

      fetch('appointment_handler.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            location.reload();
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred. Please try again.');
        });
    });

    // Cancel Appointment
    document.querySelectorAll('.cancel-appointment-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to cancel this appointment?')) {
          return;
        }

        const appointmentId = this.getAttribute('data-id');
        const formData = new FormData();
        formData.append('action', 'cancel');
        formData.append('appointment_id', appointmentId);

        fetch('appointment_handler.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert(data.message);
              location.reload();
            } else {
              alert('Error: ' + data.message);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
          });
      });
    });

    // Print receipt functions
    function printReceipt() {
      alert('Printing payment statement...\n\nTrial only');
      // In actual implementation, this would generate and print a formatted receipt
      window.print();
    }

    function printIndividualReceipt(receiptNo) {
      alert(`Printing receipt: ${receiptNo}\n\nTry only`);
      // In actual implementation, this would generate and print the specific receipt
    }
  </script>
</body>

</html>