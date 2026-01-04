<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
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
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameter
$studentTypeFilter = isset($_GET['student_type']) ? $_GET['student_type'] : 'all';
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'available-dates';

// Function to get appointment count for a specific date
function getAppointmentCountByDate($pdo, $date) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as appointment_count FROM enrollment WHERE preferred_date = ? AND status IN ('pending', 'processing')");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        return $result['appointment_count'];
    } catch(PDOException $e) {
        error_log("Error fetching appointment count: " . $e->getMessage());
        return 0;
    }
}

// Function to update current_slots for a specific date
function updateCurrentSlots($pdo, $date) {
    try {
        $appointmentCount = getAppointmentCountByDate($pdo, $date);
        
        $stmt = $pdo->prepare("UPDATE available_dates SET current_slots = ? WHERE available_date = ?");
        $stmt->execute([$appointmentCount, $date]);
        
        return $appointmentCount;
    } catch(PDOException $e) {
        error_log("Error updating current slots: " . $e->getMessage());
        return 0;
    }
}

// Function to update all current_slots
function updateAllCurrentSlots($pdo) {
    try {
        $stmt = $pdo->query("SELECT available_date FROM available_dates");
        $dates = $stmt->fetchAll();
        
        foreach($dates as $date) {
            updateCurrentSlots($pdo, $date['available_date']);
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Error updating all current slots: " . $e->getMessage());
        return false;
    }
}

// Handle form submissions
$message = '';
$messageType = '';

// Approve enrollment appointment (Move from pending to processing)
if (isset($_POST['approve_appointment'])) {
    $appointment_id = $_POST['appointment_id'];
    try {
        $stmt = $pdo->prepare("UPDATE enrollment SET status = 'processing', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        // Update current slots for the appointment date
        $stmt = $pdo->prepare("SELECT preferred_date FROM enrollment WHERE id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        if ($appointment) {
            updateCurrentSlots($pdo, $appointment['preferred_date']);
        }
        
        $message = "Enrollment appointment approved and moved to processing!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error approving appointment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Mark enrollment as completed
if (isset($_POST['complete_enrollment'])) {
    $appointment_id = $_POST['appointment_id'];
    $appointment_notes = $_POST['appointment_notes'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE enrollment SET status = 'completed', appointment_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$appointment_notes, $appointment_id]);
        
        // Update current slots for the appointment date
        $stmt = $pdo->prepare("SELECT preferred_date FROM enrollment WHERE id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        if ($appointment) {
            updateCurrentSlots($pdo, $appointment['preferred_date']);
        }
        
        $message = "Enrollment marked as completed!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error completing enrollment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Cancel enrollment appointment
if (isset($_POST['cancel_enrollment'])) {
    $appointment_id = $_POST['appointment_id'];
    $cancellation_reason = $_POST['cancellation_reason'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    
    try {
        // For processing appointments, no reason is required
        if ($status === 'processing') {
            $cancellation_reason = 'Cancelled by admin during processing';
        }
        
        // Update status to 'cancelled' (not the reason text)
        $stmt = $pdo->prepare("UPDATE enrollment SET status = 'cancelled', appointment_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$cancellation_reason, $appointment_id]);
        
        // Update current slots for the appointment date
        $stmt = $pdo->prepare("SELECT preferred_date FROM enrollment WHERE id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        if ($appointment) {
            updateCurrentSlots($pdo, $appointment['preferred_date']);
        }
        
        $message = "Enrollment appointment cancelled!";
        $messageType = "warning";
    } catch(PDOException $e) {
        $message = "Error cancelling enrollment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle Add Available Enrollment Date
if (isset($_POST['add_available_date'])) {
    $available_date = $_POST['available_date'];
    $max_slots = $_POST['max_slots'];

    try {
        // Check if date already exists
        $stmt = $pdo->prepare("SELECT id FROM available_dates WHERE available_date = ?");
        $stmt->execute([$available_date]);
        
        if ($stmt->fetch()) {
            $message = "This date is already in the available dates list!";
            $messageType = "danger";
        } else {
            $stmt = $pdo->prepare("INSERT INTO available_dates (available_date, max_slots, current_slots) VALUES (?, ?, 0)");
            $stmt->execute([$available_date, $max_slots]);
            
            $message = "Available enrollment date added successfully!";
            $messageType = "success";
        }
    } catch(PDOException $e) {
        $message = "Error adding available date: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle Delete Available Date
if (isset($_POST['delete_available_date'])) {
    $date_id = $_POST['date_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM available_dates WHERE id = ?");
        $stmt->execute([$date_id]);
        
        $message = "Available date deleted successfully!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error deleting available date: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Handle Toggle Date Status
if (isset($_POST['toggle_date_status'])) {
    $date_id = $_POST['date_id'];
    $current_status = $_POST['current_status'];
    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    
    try {
        $stmt = $pdo->prepare("UPDATE available_dates SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $date_id]);
        
        $message = "Date status updated successfully!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error updating date status: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Function to build query with filter
function buildEnrollmentQuery($status, $studentTypeFilter) {
    $query = "SELECT * FROM enrollment WHERE status = :status";
    $params = ['status' => $status];
    
    if ($studentTypeFilter !== 'all') {
        $query .= " AND enrollment_type = :enrollment_type";
        $params['enrollment_type'] = $studentTypeFilter;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    return ['query' => $query, 'params' => $params];
}

// Fetch enrollment appointments from enrollment table with filter
$pendingAppointments = [];
try {
    $queryData = buildEnrollmentQuery('pending', $studentTypeFilter);
    $stmt = $pdo->prepare($queryData['query']);
    $stmt->execute($queryData['params']);
    $pendingAppointments = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching pending appointments: " . $e->getMessage());
}

// Fetch processing enrollments with filter
$processingEnrollments = [];
try {
    $queryData = buildEnrollmentQuery('processing', $studentTypeFilter);
    $stmt = $pdo->prepare($queryData['query']);
    $stmt->execute($queryData['params']);
    $processingEnrollments = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching processing enrollments: " . $e->getMessage());
}

// Fetch completed enrollments with filter
$completedEnrollments = [];
try {
    $queryData = buildEnrollmentQuery('completed', $studentTypeFilter);
    $queryData['query'] .= " LIMIT 10";
    $stmt = $pdo->prepare($queryData['query']);
    $stmt->execute($queryData['params']);
    $completedEnrollments = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching completed enrollments: " . $e->getMessage());
}

// Fetch cancelled enrollments with filter
$cancelledEnrollments = [];
try {
    $queryData = buildEnrollmentQuery('cancelled', $studentTypeFilter);
    $queryData['query'] .= " LIMIT 10";
    $stmt = $pdo->prepare($queryData['query']);
    $stmt->execute($queryData['params']);
    $cancelledEnrollments = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching cancelled enrollments: " . $e->getMessage());
}

// Get counts for each student type
$studentTypeCounts = [
    'all' => 0,
    'inquiry' => 0,
    'returning_student' => 0
];

try {
    // Get total counts
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM enrollment");
    $studentTypeCounts['all'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM enrollment WHERE enrollment_type = 'inquiry'");
    $studentTypeCounts['inquiry'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM enrollment WHERE enrollment_type = 'returning_student'");
    $studentTypeCounts['returning_student'] = $stmt->fetch()['count'];
} catch(PDOException $e) {
    error_log("Error fetching student type counts: " . $e->getMessage());
}

// Fetch available dates - UPDATED WITH REAL-TIME SLOTS
$availableDates = [];
try {
    $stmt = $pdo->query("
        SELECT ad.*, 
               (SELECT COUNT(*) FROM enrollment WHERE preferred_date = ad.available_date AND status IN ('pending', 'processing')) as real_time_slots 
        FROM available_dates ad 
        ORDER BY ad.available_date ASC
    ");
    $availableDates = $stmt->fetchAll();
    
    // Update the database current_slots as well
    updateAllCurrentSlots($pdo);
    
} catch(PDOException $e) {
    error_log("Error fetching available dates: " . $e->getMessage());
}

$adminName = isset($_SESSION['admin_name']) ? htmlspecialchars($_SESSION['admin_name']) : 'Admin';

// Get statistics
$totalPending = count($pendingAppointments);
$totalProcessing = count($processingEnrollments);
$totalCompleted = count($completedEnrollments);
$totalCancelled = count($cancelledEnrollments);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - Creative Dreams School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .top-header {
            background: linear-gradient(135deg, #5a9c4e 0%, #4a8240 100%);
            padding: 15px 30px;
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            font-size: 24px;
        }

        .brand-text h1 {
            color: white;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .brand-text p {
            color: rgba(255,255,255,0.9);
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
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .icon-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }

        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .welcome-section {
            text-align: center;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-avatar i {
            font-size: 40px;
            color: white;
        }

        .welcome-section h5 {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: #52a347;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 14px;
        }

        .menu-item {
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
        }

        .menu-item:hover {
            background: #e6f1e6;
            color: #52a347;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
        }

        .menu-item i {
            font-size: 20px;
            width: 25px;
        }

        .logout-btn {
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
        }

        .logout-btn:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }

        .main-content {
            padding: 20px;
        }

        .page-title {
            color: #2d5a24;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
            border-top: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }

        .stats-card.pending {
            border-top-color: #ff9800;
        }

        .stats-card.processing {
            border-top-color: #2196f3;
        }

        .stats-card.completed {
            border-top-color: #52a347;
        }

        .stats-card.cancelled {
            border-top-color: #f44336;
        }

        .stats-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stats-card.pending .icon {
            color: #ff9800;
        }

        .stats-card.processing .icon {
            color: #2196f3;
        }

        .stats-card.completed .icon {
            color: #52a347;
        }

        .stats-card.cancelled .icon {
            color: #f44336;
        }

        .stats-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
        }

        .stats-card .label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: white;
            border: none;
            padding: 20px;
            font-weight: bold;
            font-size: 18px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: #52a347;
        }

        .student-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }

        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #52a347, #3d6e35);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .student-details h5 {
            margin: 0;
            color: #2c3e50;
            font-weight: bold;
        }

        .student-details p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3e0;
            color: #ff9800;
        }

        .status-processing {
            background: #e3f2fd;
            color: #2196f3;
        }

        .status-completed {
            background: #e6f1e6;
            color: #52a347;
        }

        .status-cancelled {
            background: #ffebee;
            color: #f44336;
        }

        .student-type-badge {
            padding: 5px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            display: inline-block;
            margin-left: 10px;
        }

        .student-type-inquiry {
            background: #e3f2fd;
            color: #1976d2;
        }

        .student-type-returning {
            background: #e8f5e9;
            color: #388e3c;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 15px;
            color: #2c3e50;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .btn-approve {
            background: #52a347;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-approve:hover {
            background: #3d6e35;
            transform: translateY(-2px);
        }

        .btn-complete {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-complete:hover {
            background: #388e3c;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #ff9800;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }

        .btn-print {
            background: #2196f3;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-print:hover {
            background: #1976d2;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h4 {
            color: #666;
            margin-bottom: 10px;
        }

        .tab-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 25px;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab-btn:hover {
            color: #52a347;
        }

        .tab-btn.active {
            color: #52a347;
            border-bottom-color: #52a347;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Filter Section */
        .filter-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: #52a347;
            color: #52a347;
        }

        .filter-btn.active {
            background: #52a347;
            color: white;
            border-color: #52a347;
        }

        .filter-btn .badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .filter-btn:not(.active) .badge {
            background: #e0e0e0;
            color: #666;
        }

        /* Available Dates Styles */
        .date-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .date-card:hover {
            border-color: #52a347;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .date-card.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }

        .date-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .date-info h5 {
            margin: 0;
            color: #2c3e50;
        }

        .date-info p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .slots-info {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .slots-number {
            font-size: 24px;
            font-weight: bold;
            color: #52a347;
        }

        .slots-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .appointment-count {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin-top: 10px;
        }

        .appointment-count-number {
            font-size: 20px;
            font-weight: bold;
            color: #1976d2;
        }

        .appointment-count-label {
            font-size: 12px;
            color: #666;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 2px;
        }

        .btn-edit {
            background: #2196f3;
            color: white;
        }

        .btn-edit:hover {
            background: #1976d2;
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #d32f2f;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }

            .student-header {
                flex-direction: column;
                gap: 15px;
            }

            .tab-buttons {
                flex-direction: column;
            }

            .tab-btn {
                width: 100%;
                text-align: left;
            }

            .filter-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-buttons {
                width: 100%;
                justify-content: space-between;
            }

            .filter-btn {
                flex: 1;
                justify-content: center;
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
                    <i class="fas fa-graduation-cap" style="color: #7cb342;"></i>
                </div>
                <div class="brand-text">
                    <h1>Creative Dreams</h1>
                    <p>Inspire. Learn. Achieve.</p>
                </div>
            </div>
            <div class="header-actions">
                <button class="icon-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="icon-btn" title="Profile">
                    <i class="fas fa-user"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 mb-4">
                <div class="sidebar">
                    <div class="welcome-section">
                        <div class="admin-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h5>WELCOME <?php echo strtoupper($adminName); ?>!</h5>
                        <p><i class="fas fa-check-circle"></i> Logged in</p>
                    </div>
                    <nav>
                        <a href="admin_dashboard.php" class="menu-item">
                            <i class="fas fa-chart-line"></i>
                            <span>DASHBOARD</span>
                        </a>
                        <a href="enrollment_management.php" class="menu-item active">
                            <i class="fas fa-user-graduate"></i>
                            <span>ENROLLMENT</span>
                        </a>
                        <a href="request.php" class="menu-item">
                            <i class="fas fa-user-graduate"></i>
                            <span>REQUESTS & APPOINTMENTS</span>
                        </a>
                        <a href="fees_payment.php" class="menu-item">
                            <i class="fas fa-credit-card"></i>
                            <span>FEES & PAYMENT</span>
                        </a>
                        <a href="manage_accounts.php" class="menu-item">
                            <i class="fas fa-users-cog"></i>
                            <span>MANAGE ACCOUNTS</span>
                        </a>
                        <a href="manage_sections.php" class="menu-item">
                            <i class="fas fa-door-open"></i>
                            <span>MANAGE SECTIONS</span>
                        </a>
                        <a href="announcement.php" class="menu-item">
                            <i class="fas fa-bullhorn"></i>
                            <span>ANNOUNCEMENT</span>
                        </a>
                        <a href="settings.php" class="menu-item">
                            <i class="fas fa-cog"></i>
                            <span>SETTINGS</span>
                        </a>
                    </nav>
                    <form action="logout.php" method="POST">
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> LOGOUT
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-9">
                <div class="main-content">
                    <h2 class="page-title">
                        <i class="fas fa-user-graduate"></i> Enrollment Management
                    </h2>

                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Student Type Filter -->
                    <div class="filter-container">
                        <div class="filter-header">
                            <div>
                                <h4 style="color: #2d5a24; margin: 0;">
                                    <i class="fas fa-filter"></i> Filter Students by Type
                                </h4>
                                <p class="text-muted mb-0" style="font-size: 14px;">
                                    Showing: <?php 
                                        if ($studentTypeFilter === 'all') echo 'All Students';
                                        elseif ($studentTypeFilter === 'inquiry') echo 'Inquiry Students Only';
                                        else echo 'Returning Students Only';
                                    ?>
                                </p>
                            </div>
                            <div class="filter-buttons">
                                <a href="?student_type=all&tab=<?php echo $currentTab; ?>" 
                                   class="filter-btn <?php echo $studentTypeFilter === 'all' ? 'active' : ''; ?>">
                                    <i class="fas fa-users"></i> All Students
                                    <span class="badge"><?php echo $studentTypeCounts['all']; ?></span>
                                </a>
                                <a href="?student_type=inquiry&tab=<?php echo $currentTab; ?>" 
                                   class="filter-btn <?php echo $studentTypeFilter === 'inquiry' ? 'active' : ''; ?>">
                                    <i class="fas fa-question-circle"></i> Inquiry Students
                                    <span class="badge"><?php echo $studentTypeCounts['inquiry']; ?></span>
                                </a>
                                <a href="?student_type=returning_student&tab=<?php echo $currentTab; ?>" 
                                   class="filter-btn <?php echo $studentTypeFilter === 'returning_student' ? 'active' : ''; ?>">
                                    <i class="fas fa-redo"></i> Returning Students
                                    <span class="badge"><?php echo $studentTypeCounts['returning_student']; ?></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card pending">
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="value"><?php echo $totalPending; ?></div>
                                <div class="label">Pending Appointments</div>
                            </div>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card processing">
                                <div class="icon">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="value"><?php echo $totalProcessing; ?></div>
                                <div class="label">Processing</div>
                            </div>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card completed">
                                <div class="icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="value"><?php echo $totalCompleted; ?></div>
                                <div class="label">Completed</div>
                            </div>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <div class="stats-card cancelled">
                                <div class="icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="value"><?php echo $totalCancelled; ?></div>
                                <div class="label">Cancelled</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-btn <?php echo $currentTab === 'available-dates' ? 'active' : ''; ?>" onclick="showTab('available-dates')">
                                <i class="fas fa-calendar-plus"></i> Available Dates (<?php echo count($availableDates); ?>)
                            </button>
                            <button class="tab-btn <?php echo $currentTab === 'pending' ? 'active' : ''; ?>" onclick="showTab('pending')">
                                <i class="fas fa-clock"></i> Pending Appointments (<?php echo $totalPending; ?>)
                            </button>
                            <button class="tab-btn <?php echo $currentTab === 'processing' ? 'active' : ''; ?>" onclick="showTab('processing')">
                                <i class="fas fa-cog"></i> Processing (<?php echo $totalProcessing; ?>)
                            </button>
                            <button class="tab-btn <?php echo $currentTab === 'completed' ? 'active' : ''; ?>" onclick="showTab('completed')">
                                <i class="fas fa-check-circle"></i> Completed (<?php echo $totalCompleted; ?>)
                            </button>
                            <button class="tab-btn <?php echo $currentTab === 'cancelled' ? 'active' : ''; ?>" onclick="showTab('cancelled')">
                                <i class="fas fa-times-circle"></i> Cancelled (<?php echo $totalCancelled; ?>)
                            </button>
                        </div>

                        <!-- Available Enrollment Dates Tab -->
                        <div id="available-dates-tab" class="tab-content <?php echo $currentTab === 'available-dates' ? 'active' : ''; ?>">
                            <div class="row">
                                <div class="col-md-5 mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="fas fa-calendar-plus"></i> Add Available Enrollment Date
                                        </div>
                                        <div class="card-body">
                                            <form method="POST">
                                                <div class="mb-3">
                                                    <label for="available_date" class="form-label">Available Date *</label>
                                                    <input type="date" class="form-control" name="available_date" id="available_date" 
                                                           min="<?php echo date('Y-m-d'); ?>" required>
                                                    <small class="text-muted">Select dates when enrollment appointments are available</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="max_slots" class="form-label">Maximum Slots *</label>
                                                    <input type="number" class="form-control" name="max_slots" id="max_slots" 
                                                           min="1" max="50" value="10" required>
                                                    <small class="text-muted">Maximum number of appointments per day</small>
                                                </div>

                                                <div class="text-end">
                                                    <button type="submit" name="add_available_date" class="btn-approve">
                                                        <i class="fas fa-plus"></i> Add Date
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="card mt-4">
                                        <div class="card-header">
                                            <i class="fas fa-info-circle"></i> Information
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="fas fa-lightbulb"></i>
                                                <strong>How it works:</strong>
                                                <ul class="mt-2 mb-0">
                                                    <li>Parents can only select from these available dates</li>
                                                    <li>Each date has limited slots to manage capacity</li>
                                                    <li>Inactive dates won't be shown to parents</li>
                                                    <li>Set enrollment to "Open" in Academic Year settings</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-7 mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="fas fa-calendar-check"></i> Available Enrollment Dates (<?php echo count($availableDates); ?>)
                                        </div>
                                        <div class="card-body">
                                            <?php if (empty($availableDates)): ?>
                                                <div class="empty-state">
                                                    <i class="fas fa-calendar-times"></i>
                                                    <h4>No Available Dates</h4>
                                                    <p>No enrollment dates configured yet.</p>
                                                    <p class="small">Add dates using the form on the left.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="row">
                                                    <?php foreach ($availableDates as $date): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="date-card <?php echo $date['status'] === 'inactive' ? 'inactive' : ''; ?>">
                                                                <div class="date-header">
                                                                    <div class="date-info">
                                                                        <h5><?php echo date('M d, Y', strtotime($date['available_date'])); ?></h5>
                                                                        <p><?php echo date('l', strtotime($date['available_date'])); ?></p>
                                                                    </div>
                                                                    <span class="status-badge <?php echo $date['status'] === 'active' ? 'status-completed' : 'status-pending'; ?>">
                                                                        <?php echo ucfirst($date['status']); ?>
                                                                    </span>
                                                                </div>

                                                                <div class="slots-info">
                                                                    <div class="slots-number"><?php echo $date['max_slots']; ?></div>
                                                                    <div class="slots-label">Maximum Slots</div>
                                                                </div>

                                                                <!-- Appointment Count Display -->
                                                                <div class="appointment-count">
                                                                    <?php
                                                                    $appointmentCount = $date['real_time_slots'];
                                                                    ?>
                                                                    <div class="appointment-count-number">
                                                                        <?php echo $appointmentCount; ?>
                                                                    </div>
                                                                    <div class="appointment-count-label">
                                                                        Booked Appointments
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        <?php echo $appointmentCount; ?> of <?php echo $date['max_slots']; ?> slots filled
                                                                        (<?php echo $date['max_slots'] > 0 ? round(($appointmentCount / $date['max_slots']) * 100, 1) : 0; ?>%)
                                                                    </small>
                                                                </div>

                                                                <div class="d-flex gap-2 mt-3">
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="date_id" value="<?php echo $date['id']; ?>">
                                                                        <input type="hidden" name="current_status" value="<?php echo $date['status']; ?>">
                                                                        <button type="submit" name="toggle_date_status" class="btn-action <?php echo $date['status'] === 'active' ? 'btn-edit' : 'btn-edit'; ?>" title="<?php echo $date['status'] === 'active' ? 'Deactivate Date' : 'Activate Date'; ?>">
                                                                            <i class="fas fa-<?php echo $date['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                                        </button>
                                                                    </form>
                                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this date? This cannot be undone.');">
                                                                        <input type="hidden" name="date_id" value="<?php echo $date['id']; ?>">
                                                                        <button type="submit" name="delete_available_date" class="btn-action btn-delete" title="Delete Date">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Appointments Tab -->
                        <div id="pending-tab" class="tab-content <?php echo $currentTab === 'pending' ? 'active' : ''; ?>">
                            <?php if (empty($pendingAppointments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar"></i>
                                    <h4>No Pending Appointments</h4>
                                    <p>There are no enrollment appointment requests at this time.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingAppointments as $appointment): ?>
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <i class="fas fa-calendar"></i>
                                            </div>
                                            <div class="student-details">
                                                <h5><?php echo htmlspecialchars($appointment['student_name']); ?>
                                                    <span class="student-type-badge student-type-<?php echo $appointment['enrollment_type']; ?>">
                                                        <i class="fas fa-<?php echo $appointment['enrollment_type'] === 'inquiry' ? 'question-circle' : 'redo'; ?>"></i>
                                                        <?php echo $appointment['enrollment_type'] === 'inquiry' ? 'Inquiry' : 'Returning'; ?>
                                                    </span>
                                                </h5>
                                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock"></i> Pending Schedule
                                        </span>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Appointment ID</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Parent/Guardian</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['parent_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Contact Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['phone']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Preferred Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['preferred_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Preferred Time</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['preferred_time']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['grade_level']); ?></span>
                                        </div>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['current_grade_level'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Current Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['current_grade_level']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['academic_year'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Academic Year</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['academic_year']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['student_code'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Student Code</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['student_code']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="info-item">
                                            <span class="info-label">Request Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['created_at'])); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($appointment['message'])): ?>
                                    <div class="info-item mb-3">
                                        <span class="info-label">Message</span>
                                        <span class="info-value"><?php echo htmlspecialchars($appointment['message']); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="action-buttons">
                                        <button class="btn-approve" onclick="approveEnrollmentAppointment(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-calendar-check"></i> Approve Appointment
                                        </button>
                                        <button class="btn-cancel" onclick="cancelEnrollmentAppointment(<?php echo $appointment['id']; ?>, 'pending')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Processing Tab -->
                        <div id="processing-tab" class="tab-content <?php echo $currentTab === 'processing' ? 'active' : ''; ?>">
                            <?php if (empty($processingEnrollments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-cog"></i>
                                    <h4>No Processing Enrollments</h4>
                                    <p>There are no enrollment appointments currently being processed.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($processingEnrollments as $appointment): ?>
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <i class="fas fa-cog"></i>
                                            </div>
                                            <div class="student-details">
                                                <h5><?php echo htmlspecialchars($appointment['student_name']); ?>
                                                    <span class="student-type-badge student-type-<?php echo $appointment['enrollment_type']; ?>">
                                                        <i class="fas fa-<?php echo $appointment['enrollment_type'] === 'inquiry' ? 'question-circle' : 'redo'; ?>"></i>
                                                        <?php echo $appointment['enrollment_type'] === 'inquiry' ? 'Inquiry' : 'Returning'; ?>
                                                    </span>
                                                </h5>
                                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge status-processing">
                                            <i class="fas fa-cog"></i> Processing
                                        </span>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Appointment ID</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Parent/Guardian</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['parent_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Contact Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['phone']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Scheduled Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['preferred_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Scheduled Time</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['preferred_time']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['grade_level']); ?></span>
                                        </div>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['current_grade_level'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Current Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['current_grade_level']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['academic_year'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Academic Year</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['academic_year']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['student_code'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Student Code</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['student_code']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="info-item">
                                            <span class="info-label">Processing Since</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['updated_at'])); ?></span>
                                        </div>
                                    </div>

                                    <div class="action-buttons">
                                        <button class="btn-complete" onclick="completeEnrollment(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-check-circle"></i> Mark as Completed
                                        </button>
                                        <button class="btn-cancel" onclick="cancelEnrollmentAppointment(<?php echo $appointment['id']; ?>, 'processing')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Completed Tab -->
                        <div id="completed-tab" class="tab-content <?php echo $currentTab === 'completed' ? 'active' : ''; ?>">
                            <?php if (empty($completedEnrollments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <h4>No Completed Enrollments</h4>
                                    <p>Completed enrollment appointments will appear here.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($completedEnrollments as $appointment): ?>
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="student-details">
                                                <h5><?php echo htmlspecialchars($appointment['student_name']); ?>
                                                    <span class="student-type-badge student-type-<?php echo $appointment['enrollment_type']; ?>">
                                                        <i class="fas fa-<?php echo $appointment['enrollment_type'] === 'inquiry' ? 'question-circle' : 'redo'; ?>"></i>
                                                        <?php echo $appointment['enrollment_type'] === 'inquiry' ? 'Inquiry' : 'Returning'; ?>
                                                    </span>
                                                </h5>
                                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge status-completed">
                                            <i class="fas fa-check-circle"></i> Completed
                                        </span>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Appointment ID</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Parent/Guardian</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['parent_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Contact Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['phone']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Appointment Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['preferred_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Appointment Time</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['preferred_time']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['grade_level']); ?></span>
                                        </div>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['current_grade_level'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Current Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['current_grade_level']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['academic_year'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Academic Year</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['academic_year']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['student_code'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Student Code</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['student_code']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="info-item">
                                            <span class="info-label">Completed On</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['updated_at'])); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($appointment['appointment_notes'])): ?>
                                    <div class="info-item mb-3">
                                        <span class="info-label">Admin Notes</span>
                                        <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_notes']); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="action-buttons">
                                        <button class="btn-print" onclick="printConfirmation(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-print"></i> Print Confirmation
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Cancelled Tab -->
                        <div id="cancelled-tab" class="tab-content <?php echo $currentTab === 'cancelled' ? 'active' : ''; ?>">
                            <?php if (empty($cancelledEnrollments)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-times-circle"></i>
                                    <h4>No Cancelled Enrollments</h4>
                                    <p>Cancelled enrollment appointments will appear here.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($cancelledEnrollments as $appointment): ?>
                                <div class="student-card">
                                    <div class="student-header">
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <i class="fas fa-times-circle"></i>
                                            </div>
                                            <div class="student-details">
                                                <h5><?php echo htmlspecialchars($appointment['student_name']); ?>
                                                    <span class="student-type-badge student-type-<?php echo $appointment['enrollment_type']; ?>">
                                                        <i class="fas fa-<?php echo $appointment['enrollment_type'] === 'inquiry' ? 'question-circle' : 'redo'; ?>"></i>
                                                        <?php echo $appointment['enrollment_type'] === 'inquiry' ? 'Inquiry' : 'Returning'; ?>
                                                    </span>
                                                </h5>
                                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['email']); ?></p>
                                            </div>
                                        </div>
                                        <span class="status-badge status-cancelled">
                                            <i class="fas fa-times-circle"></i> Cancelled
                                        </span>
                                    </div>

                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">Appointment ID</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Parent/Guardian</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['parent_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Contact Number</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['phone']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Appointment Date</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['preferred_date'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Appointment Time</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['preferred_time']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['grade_level']); ?></span>
                                        </div>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['current_grade_level'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Current Grade Level</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['current_grade_level']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['academic_year'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Academic Year</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['academic_year']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($appointment['enrollment_type'] === 'returning_student' && !empty($appointment['student_code'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Student Code</span>
                                            <span class="info-value"><?php echo htmlspecialchars($appointment['student_code']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="info-item">
                                            <span class="info-label">Cancelled On</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['updated_at'])); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($appointment['appointment_notes'])): ?>
                                    <div class="info-item mb-3">
                                        <span class="info-label">Cancellation Reason</span>
                                        <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_notes']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Enrollment Appointment Modal -->
    <div class="modal fade" id="approveAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #7cb342, #689f38); color: white;">
                    <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Approve Enrollment Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="approve_appointment_id">
                        <p>Are you sure you want to approve this enrollment appointment?</p>
                        <p class="text-muted">The appointment will be moved to processing status.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_appointment" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Complete Enrollment Modal -->
    <div class="modal fade" id="completeEnrollmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4caf50, #388e3c); color: white;">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> Complete Enrollment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="complete_appointment_id">
                        <div class="mb-3">
                            <label for="appointment_notes" class="form-label">Completion Notes (Optional)</label>
                            <textarea class="form-control" name="appointment_notes" id="appointment_notes" rows="3" placeholder="Add any notes about the enrollment completion..."></textarea>
                        </div>
                        <p class="text-muted">Mark this enrollment appointment as completed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="complete_enrollment" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Mark as Completed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Enrollment Modal -->
    <div class="modal fade" id="cancelEnrollmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: #ff9800; color: white;">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Cancel Enrollment Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="cancel_appointment_id">
                        <input type="hidden" name="status" id="cancel_status">
                        <div class="mb-3" id="cancellation_reason_container">
                            <label for="cancellation_reason" class="form-label">Reason for Cancellation *</label>
                            <textarea class="form-control" name="cancellation_reason" id="cancellation_reason" rows="4" required placeholder="Please provide a reason for cancelling this appointment..."></textarea>
                        </div>
                        <p class="text-muted">The parent will be notified of this cancellation.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="cancel_enrollment" class="btn btn-warning">
                            <i class="fas fa-times"></i> Cancel Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching
        function showTab(tabName) {
            // Update URL parameter
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            url.searchParams.set('student_type', '<?php echo $studentTypeFilter; ?>');
            window.history.pushState({}, '', url);
            
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.closest('.tab-btn').classList.add('active');
        }

        // Approve enrollment appointment
        function approveEnrollmentAppointment(appointmentId) {
            document.getElementById('approve_appointment_id').value = appointmentId;
            const modal = new bootstrap.Modal(document.getElementById('approveAppointmentModal'));
            modal.show();
        }

        // Complete enrollment
        function completeEnrollment(appointmentId) {
            document.getElementById('complete_appointment_id').value = appointmentId;
            document.getElementById('appointment_notes').value = '';
            const modal = new bootstrap.Modal(document.getElementById('completeEnrollmentModal'));
            modal.show();
        }

        // Cancel enrollment appointment
        function cancelEnrollmentAppointment(appointmentId, status) {
            document.getElementById('cancel_appointment_id').value = appointmentId;
            document.getElementById('cancel_status').value = status;
            
            // For processing appointments, no reason is required
            if (status === 'processing') {
                document.getElementById('cancellation_reason_container').style.display = 'none';
                document.getElementById('cancellation_reason').required = false;
            } else {
                document.getElementById('cancellation_reason_container').style.display = 'block';
                document.getElementById('cancellation_reason').required = true;
            }
            
            document.getElementById('cancellation_reason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('cancelEnrollmentModal'));
            modal.show();
        }

        // Print confirmation
        function printConfirmation(appointmentId) {
            window.open(`print_confirmation.php?appointment_id=${appointmentId}`, '_blank');
        }

        // Animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.student-card, .stats-card, .date-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

