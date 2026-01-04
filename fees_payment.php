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
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Handle payment recording
if (isset($_POST['record_payment'])) {
    $student_id = $_POST['student_id'];
    $payment_type = $_POST['payment_type'];
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $reference_number = $_POST['reference_number'];
    $notes = $_POST['notes'];
    $school_year = $_POST['school_year'];
    $payment_period = $_POST['payment_period'];

    try {
        // Get student_code from student_id
        $stmt = $pdo->prepare("SELECT student_code FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();

        if (!$student) {
            throw new Exception("Student not found");
        }

        $student_code = $student['student_code'];

        // Generate receipt number
        $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Insert payment record
        $stmt = $pdo->prepare("INSERT INTO payments (student_code, payment_type, amount, payment_method, payment_date, receipt_number, notes, school_year, quarter, status, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?)");
        $stmt->execute([$student_code, $payment_type, $amount, $payment_method, $payment_date, $receipt_number, $notes, $school_year, $payment_period, $_SESSION['user_id']]);

        // Update student_balances table
        $stmt = $pdo->prepare("
    UPDATE student_balances 
    SET amount_paid = amount_paid + ?,
        balance = balance - ?,
        status = CASE 
            WHEN balance - ? <= 0 THEN 'fully_paid'
            WHEN amount_paid + ? > 0 THEN 'partially_paid'
            ELSE 'unpaid'
        END,
        last_updated = NOW()
    WHERE student_code = ? AND school_year = ?
");
        $stmt->execute([$amount, $amount, $amount, $amount, $student_code, $school_year]);

        $message = "Payment recorded successfully! Receipt #: $receipt_number";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error recording payment: " . $e->getMessage();
        $messageType = "danger";
    }
}

// API endpoint for fetching payment details
if (isset($_GET['action']) && $_GET['action'] == 'get_payment_details') {
    header('Content-Type: application/json');

    if (!isset($_GET['receipt_id'])) {
        echo json_encode(['success' => false, 'message' => 'Receipt ID is required']);
        exit();
    }

    $receipt_id = $_GET['receipt_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.student_code
            FROM payments p
            JOIN students s ON p.student_code = s.student_code
            WHERE p.receipt_number = ?
        ");

        $stmt->execute([$receipt_id]);
        $payment = $stmt->fetch();

        if ($payment) {
            echo json_encode([
                'success' => true,
                'data' => $payment
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Payment not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Get current year and dates for statistics
$current_year = date('Y') . '-' . (date('Y') + 1);
$today = date('Y-m-d');
$month_start = date('Y-m-01');
$year_start = date('Y-01-01');

// Initialize statistics
$totalCollections = 0;
$pendingPayments = 0;
$paidToday = 0;
$unpaidCount = 0;
$monthCollections = 0;

try {
    // Total collections this year
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid' AND school_year = ?");
    $stmt->execute([$current_year]);
    $result = $stmt->fetch();
    $totalCollections = $result ? (float)$result['total'] : 0;

    // Pending payments from student_balances
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) as total FROM student_balances WHERE balance > 0 AND school_year = ?");
    $stmt->execute([$current_year]);
    $result = $stmt->fetch();
    $pendingPayments = $result ? (float)$result['total'] : 0;

    // Paid today
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid' AND payment_date = ?");
    $stmt->execute([$today]);
    $result = $stmt->fetch();
    $paidToday = $result ? (float)$result['total'] : 0;

    // Students with unpaid balances
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM student_balances WHERE balance > 0 AND school_year = ?");
    $stmt->execute([$current_year]);
    $result = $stmt->fetch();
    $unpaidCount = $result ? (int)$result['total'] : 0;

    // This month collections
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid' AND payment_date BETWEEN ? AND ?");
    $stmt->execute([$month_start, $today]);
    $result = $stmt->fetch();
    $monthCollections = $result ? (float)$result['total'] : 0;
} catch (PDOException $e) {
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Fetch payment methods (using ENUM values from database)
$paymentMethods = ['cash'];

// Fetch payment types (using ENUM values from database)
$paymentTypes = ['tuition'];

// Fetch payment periods
$paymentPeriods = ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter', 'Full Payment'];

// Search and filter logic for payment records
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

// Build query for payment records
$sql = "
    SELECT p.*, 
           CONCAT(s.first_name, ' ', s.last_name) as student_name,
           s.student_code
    FROM payments p
    JOIN students s ON p.student_code = s.student_code
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR p.receipt_number LIKE ? OR s.student_code LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($status_filter != 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($type_filter != 'all') {
    $sql .= " AND p.payment_type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY p.payment_date DESC LIMIT 50";

// Fetch payment records with filters
$recentPayments = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recentPayments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching payments: " . $e->getMessage());
}

// Fetch all payment types for filter (distinct from database)
$allPaymentTypes = ['tuition', 'miscellaneous', 'books', 'other'];

// Fetch all payment statuses for filter
$allPaymentStatuses = ['paid', 'pending', 'cancelled'];

// Fetch students with unpaid balances
$unpaidStudents = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.student_id, sb.student_code,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               sb.amount_paid as total_paid,
               sb.balance as pending_amount
        FROM student_balances sb
        JOIN students s ON sb.student_code = s.student_code
        WHERE sb.balance > 0 AND sb.school_year = ? AND s.status = 'active'
        ORDER BY sb.balance DESC
    ");
    $stmt->execute([$current_year]);
    $unpaidStudents = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching unpaid students: " . $e->getMessage());
}

// Fetch all active students for payment form
$activeStudents = [];
try {
    $stmt = $pdo->query("
        SELECT student_id, student_code, 
               CONCAT(first_name, ' ', last_name) as full_name
        FROM students 
        WHERE status = 'active'
        ORDER BY last_name, first_name
    ");
    $activeStudents = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching students: " . $e->getMessage());
}

// Fetch payment type breakdown for reports
$paymentTypeBreakdown = [];
try {
    $stmt = $pdo->prepare("
        SELECT payment_type, 
               COUNT(*) as count,
               SUM(amount) as total
        FROM payments 
        WHERE status = 'paid' 
          AND school_year = ?
        GROUP BY payment_type
        ORDER BY total DESC
    ");
    $stmt->execute([$current_year]);
    $paymentTypeBreakdown = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching payment type breakdown: " . $e->getMessage());
}

// Fetch daily transactions for report preview
$dailyTransactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_code
        FROM payments p
        JOIN students s ON p.student_code = s.student_code
        WHERE p.status = 'paid' 
          AND p.payment_date = ?
        ORDER BY p.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute([$today]);
    $dailyTransactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching daily transactions: " . $e->getMessage());
}

$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees & Payment Management - Creative Dreams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #52a347;
            --primary-dark: #3d6e35;
            --secondary-color: #7cb342;
            --danger-color: #f44336;
            --warning-color: #ff9800;
            --info-color: #2196f3;
            --success-color: #4caf50;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            min-height: 100vh;
        }

        .top-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 15px 30px;
            border-radius: 15px;
            margin: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

        .sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: #e0f7fa;
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s;
            border-top: 4px solid;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .stats-card.total {
            border-top-color: var(--success-color);
        }

        .stats-card.pending {
            border-top-color: var(--warning-color);
        }

        .stats-card.today {
            border-top-color: var(--info-color);
        }

        .stats-card.unpaid {
            border-top-color: var(--danger-color);
        }

        .stats-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stats-card.total .icon {
            color: var(--success-color);
        }

        .stats-card.pending .icon {
            color: var(--warning-color);
        }

        .stats-card.today .icon {
            color: var(--info-color);
        }

        .stats-card.unpaid .icon {
            color: var(--danger-color);
        }

        .stats-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .status-paid {
            background: #e8f5e9;
            color: var(--success-color);
        }

        .status-pending {
            background: #fff3e0;
            color: var(--warning-color);
        }

        .status-cancelled {
            background: #ffebee;
            color: var(--danger-color);
        }

        .btn-record {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-record:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
            color: var(--primary-color);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .search-box {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .report-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
            height: 100%;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        /* Payment Transactions Table Styling */
        .payment-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .payment-table-header {
            background: white;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-table th {
            background: #f5f5f5;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
            text-align: left;
        }

        .payment-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .payment-table tr:hover {
            background: #f9f9f9;
        }

        .amount-cell {
            font-weight: bold;
            color: var(--success-color);
        }

        .student-cell {
            display: flex;
            flex-direction: column;
        }

        .student-name {
            font-weight: bold;
            color: #2c3e50;
        }

        .student-id {
            font-size: 12px;
            color: #666;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-view {
            background: #2196f3;
            color: white;
        }

        .btn-print {
            background: var(--primary-color);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Payment Details Modal Styling */
        .payment-details-modal .modal-dialog {
            max-width: 500px;
        }

        .payment-details-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .payment-details-modal .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-bottom: none;
            padding: 20px 25px;
        }

        .payment-details-modal .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }

        .payment-details-modal .btn-close {
            background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
            opacity: 0.8;
        }

        .payment-details-modal .btn-close:hover {
            opacity: 1;
        }

        .payment-details-modal .modal-body {
            padding: 25px;
        }

        .payment-details-modal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 20px 25px;
        }

        .payment-detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .payment-detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .payment-detail-label {
            font-weight: 600;
            color: #555;
            flex: 1;
        }

        .payment-detail-value {
            color: #333;
            flex: 1;
            text-align: right;
        }

        .payment-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--success-color);
        }

        .payment-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            display: inline-block;
        }

        .payment-status-paid {
            background: #e8f5e9;
            color: var(--success-color);
        }

        .payment-status-pending {
            background: #fff3e0;
            color: var(--warning-color);
        }

        .payment-status-cancelled {
            background: #ffebee;
            color: var(--danger-color);
        }

        /* Report Modal Styling */
        .report-modal .modal-dialog {
            max-width: 700px;
        }

        .report-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .report-modal .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-bottom: none;
            padding: 20px 25px;
        }

        .report-modal .modal-title {
            font-weight: 600;
            font-size: 1.5rem;
        }

        .report-modal .modal-body {
            padding: 25px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .report-modal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 20px 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .report-description {
            color: #666;
            margin-bottom: 25px;
            font-size: 1rem;
        }

        .report-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .summary-label {
            font-weight: 600;
            color: #555;
        }

        .summary-value {
            font-weight: bold;
            color: var(--primary-color);
        }

        .report-transactions {
            margin-top: 20px;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-date {
            color: #666;
            font-size: 0.9rem;
        }

        .transaction-student {
            flex: 1;
            margin: 0 15px;
        }

        .transaction-amount {
            font-weight: bold;
            color: var(--success-color);
        }

        .date-range-selector {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .date-input-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .date-input-group label {
            min-width: 100px;
            font-weight: 600;
        }

        .date-input-group input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        /* Modal Animation */
        .modal.fade .modal-dialog {
            transform: translate(0, -50px);
            transition: transform 0.3s ease-out;
        }

        .modal.show .modal-dialog {
            transform: none;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        @media (max-width: 768px) {
            .stats-card .value {
                font-size: 24px;
            }

            .tab-buttons {
                flex-direction: column;
            }

            .tab-btn {
                text-align: left;
            }

            .payment-table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .date-input-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .date-input-group label {
                min-width: auto;
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
                    <i class="fas fa-graduation-cap" style="color: var(--secondary-color);"></i>
                </div>
                <div class="brand-text text-white">
                    <h1 class="mb-0">Creative Dreams</h1>
                    <p class="mb-0 opacity-90 fst-italic">Inspire. Learn. Achieve.</p>
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline-light btn-sm">
                    <i class="fas fa-bell"></i>
                </button>
                <button class="btn btn-outline-light btn-sm">
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
                            <i class="fas fa-user-shield text-white"></i>
                        </div>
                        <h5 class="fw-bold text-dark">WELCOME <?php echo strtoupper($adminName); ?>!</h5>
                        <p class="text-success mb-0">
                            <i class="fas fa-check-circle"></i> Logged in
                        </p>
                    </div>
                    <nav>
                        <a href="admin_dashboard.php" class="menu-item">
                            <i class="fas fa-chart-line"></i>
                            <span>DASHBOARD</span>
                        </a>
                        <a href="enrollment_management.php" class="menu-item">
                            <i class="fas fa-user-graduate"></i>
                            <span>ENROLLMENT</span>
                        </a>
                        <a href="request.php" class="menu-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>REQUESTS & APPOINTMENTS</span>
                        </a>
                        <a href="fees_payment.php" class="menu-item active">
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
                    <form action="logout.php" method="POST" class="mt-3">
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-sign-out-alt"></i> LOGOUT
                        </button>
                    </form>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-9">
                <div class="main-content">
                    <h2 class="page-title text-dark fw-bold mb-4">
                        <i class="fas fa-credit-card text-success"></i> Fees & Payment Management
                    </h2>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card total">
                                <div class="icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="value">₱<?php echo number_format($totalCollections, 2); ?></div>
                                <div class="label">Total Collections</div>
                                <small class="text-muted">This Year (<?php echo $current_year; ?>)</small>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card pending">
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="value">₱<?php echo number_format($pendingPayments, 2); ?></div>
                                <div class="label">Pending Payments</div>
                                <small class="text-muted">Unpaid Balance</small>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card today">
                                <div class="icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="value">₱<?php echo number_format($paidToday, 2); ?></div>
                                <div class="label">Paid Today</div>
                                <small class="text-muted"><?php echo date('M d, Y'); ?></small>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card unpaid">
                                <div class="icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="value"><?php echo $unpaidCount; ?></div>
                                <div class="label">Students with Balance</div>
                                <small class="text-muted">Needs Follow-up</small>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <div class="tab-container">
                        <div class="tab-buttons">
                            <button class="tab-btn active" onclick="showTab('payments')">
                                <i class="fas fa-list"></i> Payment Records
                            </button>
                            <button class="tab-btn" onclick="showTab('unpaid')">
                                <i class="fas fa-exclamation-circle"></i> Unpaid Balances
                                <?php if ($unpaidCount > 0): ?>
                                    <span class="badge bg-danger ms-1"><?php echo $unpaidCount; ?></span>
                                <?php endif; ?>
                            </button>
                            <button class="tab-btn" onclick="showTab('record')">
                                <i class="fas fa-plus-circle"></i> Record Payment
                            </button>
                            <button class="tab-btn" onclick="showTab('reports')">
                                <i class="fas fa-chart-bar"></i> Financial Reports
                            </button>
                        </div>

                        <!-- Payment Records Tab -->
                        <div id="payments-tab" class="tab-content active">
                            <!-- Search and Filter Section -->
                            <div class="search-filter-bar bg-light p-3 rounded mb-3">
                                <form method="GET" action="" id="filterForm">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <input type="text" name="search" class="search-box"
                                                placeholder="Search by student name, receipt number, student code..."
                                                value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
                                        </div>
                                        <div class="col-md-3">
                                            <select class="search-box" name="status" id="statusFilter">
                                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="search-box" name="type" id="typeFilter">
                                                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                                <?php foreach ($allPaymentTypes as $type): ?>
                                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn-record w-100" id="searchButton">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Payment Transactions Table -->
                            <div class="payment-table-container">
                                <div class="payment-table-header">
                                    <h5>
                                        <i class="fas fa-receipt text-success"></i> Payment Transactions
                                        <?php if ($search || $status_filter !== 'all' || $type_filter !== 'all'): ?>
                                            <small class="text-muted ms-2">
                                                (Filtered:
                                                <?php
                                                $filter_text = [];
                                                if ($search) $filter_text[] = "Search: '$search'";
                                                if ($status_filter !== 'all') $filter_text[] = "Status: $status_filter";
                                                if ($type_filter !== 'all') $filter_text[] = "Type: $type_filter";
                                                echo implode(', ', $filter_text);
                                                ?>)
                                            </small>
                                        <?php endif; ?>
                                    </h5>
                                    <div class="d-flex gap-2">
                                        <?php if ($search || $status_filter !== 'all' || $type_filter !== 'all'): ?>
                                            <a href="fees_payment.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i> Clear Filters
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn-record" onclick="showTab('record')">
                                            <i class="fas fa-plus"></i> Record New Payment
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="payment-table">
                                        <thead>
                                            <tr>
                                                <th>Receipt #</th>
                                                <th>Date</th>
                                                <th>Student</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentPayments)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-5">
                                                        <div class="empty-state">
                                                            <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                                                            <h4 class="text-muted">No Payment Records Found</h4>
                                                            <p class="text-muted">
                                                                <?php if ($search || $status_filter !== 'all' || $type_filter !== 'all'): ?>
                                                                    No payments match your filters. Try different criteria or <a href="fees_payment.php">clear all filters</a>.
                                                                <?php else: ?>
                                                                    Payment transactions will appear here.
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recentPayments as $payment): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($payment['receipt_number']); ?></strong></td>
                                                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                                        <td>
                                                            <div class="student-cell">
                                                                <span class="student-name"><?php echo htmlspecialchars($payment['student_name']); ?></span>
                                                                <span class="student-id">#<?php echo htmlspecialchars($payment['student_code']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                                                        <td class="amount-cell">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                                <?php echo ucfirst($payment['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <button class="btn-action btn-view"
                                                                    onclick="viewPaymentDetails('<?php echo $payment['receipt_number']; ?>')"
                                                                    title="View Details">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <button class="btn-action btn-print"
                                                                    onclick="printPaymentReceipt('<?php echo $payment['receipt_number']; ?>')"
                                                                    title="Print Receipt">
                                                                    <i class="fas fa-print"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    <?php if (!empty($recentPayments)): ?>
                                        <div class="p-3 text-center text-muted">
                                            Showing <?php echo count($recentPayments); ?> payment record(s)
                                            <?php if (count($recentPayments) >= 50): ?>
                                                <br><small>(Limited to 50 most recent records)</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Unpaid Balances Tab -->
                        <div id="unpaid-tab" class="tab-content">
                            <div class="bg-light p-3 rounded mb-3">
                                <input type="text" class="search-box" id="searchUnpaid"
                                    placeholder="Search by student name or student number...">
                            </div>

                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-triangle text-warning"></i> Students with Unpaid Balances
                                    </h5>
                                    <?php if ($unpaidCount > 0): ?>
                                        <span class="badge bg-danger fs-6">
                                            <?php echo $unpaidCount; ?> Student<?php echo $unpaidCount > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($unpaidStudents)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                            <h4 class="text-success">All Payments Up to Date!</h4>
                                            <p class="text-muted">There are no students with unpaid balances.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="row g-3" id="unpaidStudentsContainer">
                                            <?php foreach ($unpaidStudents as $student): ?>
                                                <div class="col-lg-6 unpaid-student-card">
                                                    <div class="card border-warning">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                                <div>
                                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($student['student_name']); ?></h6>
                                                                    <p class="text-muted small mb-1">
                                                                        <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($student['student_code']); ?>
                                                                    </p>
                                                                </div>
                                                                <div class="text-end">
                                                                    <div class="h4 text-danger mb-0">₱<?php echo number_format($student['pending_amount'], 2); ?></div>
                                                                    <small class="text-muted">Pending Amount</small>
                                                                </div>
                                                            </div>

                                                            <div class="row small text-center mb-3">
                                                                <div class="col-6">
                                                                    <div class="text-muted">Total Paid</div>
                                                                    <div class="fw-bold text-success">₱<?php echo number_format($student['total_paid'], 2); ?></div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="text-muted">Pending</div>
                                                                    <div class="fw-bold text-warning">₱<?php echo number_format($student['pending_amount'], 2); ?></div>
                                                                </div>
                                                            </div>

                                                            <div class="d-flex gap-2 flex-wrap">
                                                                <button class="btn btn-outline-primary btn-sm" onclick="viewStudentPayments(<?php echo $student['student_id']; ?>)">
                                                                    <i class="fas fa-history"></i> History
                                                                </button>
                                                                <button class="btn btn-outline-warning btn-sm" onclick="sendReminder(<?php echo $student['student_id']; ?>)">
                                                                    <i class="fas fa-envelope"></i> Reminder
                                                                </button>
                                                                <button class="btn btn-success btn-sm" onclick="recordPaymentFor(<?php echo $student['student_id']; ?>)">
                                                                    <i class="fas fa-plus"></i> Record Payment
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Record Payment Tab -->
                        <div id="record-tab" class="tab-content">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-plus-circle text-success"></i> Record New Payment
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="paymentForm">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Student *</label>
                                                <select class="form-select" name="student_id" id="student_id" required>
                                                    <option value="">Select Student</option>
                                                    <?php foreach ($activeStudents as $student): ?>
                                                        <option value="<?php echo $student['student_id']; ?>">
                                                            <?php echo htmlspecialchars($student['full_name']); ?> -
                                                            (<?php echo htmlspecialchars($student['student_code']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Payment Type *</label>
                                                <select class="form-select" name="payment_type" required>
                                                    <option value="">Select Type</option>
                                                    <?php foreach ($paymentTypes as $type): ?>
                                                        <option value="<?php echo htmlspecialchars($type); ?>">
                                                            <?php echo htmlspecialchars($type); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Amount (₱) *</label>
                                                <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Payment Method *</label>
                                                <select class="form-select" name="payment_method" required>
                                                    <option value="">Select Method</option>
                                                    <?php foreach ($paymentMethods as $method): ?>
                                                        <option value="<?php echo htmlspecialchars($method); ?>">
                                                            <?php echo htmlspecialchars($method); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Payment Date *</label>
                                                <input type="date" class="form-control" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Reference Number</label>
                                                <input type="text" class="form-control" name="reference_number" placeholder="Check/Transaction Number">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">School Year *</label>
                                                <input type="text" class="form-control" name="school_year" required value="<?php echo $current_year; ?>">
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Payment Period *</label>
                                                <select class="form-select" name="payment_period" required>
                                                    <option value="">Select Period</option>
                                                    <?php foreach ($paymentPeriods as $period): ?>
                                                        <option value="<?php echo htmlspecialchars($period); ?>">
                                                            <?php echo htmlspecialchars($period); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label">Notes/Remarks</label>
                                                <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes or remarks about this payment..."></textarea>
                                            </div>
                                        </div>

                                        <div class="text-end mt-4">
                                            <button type="reset" class="btn btn-secondary">
                                                <i class="fas fa-redo"></i> Clear Form
                                            </button>
                                            <button type="submit" name="record_payment" class="btn-record">
                                                <i class="fas fa-save"></i> Record Payment
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Reports Tab -->
                        <div id="reports-tab" class="tab-content">
                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <i class="fas fa-chart-line text-success"></i> Collection Summary
                                            </h5>
                                            <button class="btn btn-success btn-sm" onclick="exportCollectionSummary()">
                                                <i class="fas fa-file-excel"></i> Export
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <?php
                                            $progressData = [
                                                'today' => $paidToday > 0 ? 100 : 0,
                                                'month' => $monthCollections > 0 ? min(($monthCollections / ($totalCollections ?: 1)) * 100, 100) : 0,
                                                'year' => $totalCollections > 0 ? min(($totalCollections / ($totalCollections + $pendingPayments)) * 100, 100) : 0,
                                                'pending' => $pendingPayments > 0 ? min(($pendingPayments / ($totalCollections + $pendingPayments)) * 100, 100) : 0
                                            ];
                                            ?>

                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Today's Collections</span>
                                                    <strong class="text-success">₱<?php echo number_format($paidToday, 2); ?></strong>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $progressData['today']; ?>%"></div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>This Month</span>
                                                    <strong class="text-info">₱<?php echo number_format($monthCollections, 2); ?></strong>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-info" style="width: <?php echo $progressData['month']; ?>%"></div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>This Year</span>
                                                    <strong class="text-primary">₱<?php echo number_format($totalCollections, 2); ?></strong>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" style="width: <?php echo $progressData['year']; ?>%; background-color: var(--secondary-color);"></div>
                                                </div>
                                            </div>

                                            <div class="mb-0">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Pending Payments</span>
                                                    <strong class="text-warning">₱<?php echo number_format($pendingPayments, 2); ?></strong>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-warning" style="width: <?php echo $progressData['pending']; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-chart-pie text-success"></i> Payment Type Breakdown
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="paymentTypeChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-download text-success"></i> Generate Reports
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <div class="report-card" onclick="openReportModal('daily')">
                                                        <i class="fas fa-calendar-day fa-3x text-success mb-3"></i>
                                                        <h5>Daily Report</h5>
                                                        <p class="text-muted">Generate daily collection report</p>
                                                        <button class="btn btn-primary w-100">Generate</button>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="report-card" onclick="openReportModal('monthly')">
                                                        <i class="fas fa-calendar-alt fa-3x text-success mb-3"></i>
                                                        <h5>Monthly Report</h5>
                                                        <p class="text-muted">Generate monthly collection report</p>
                                                        <button class="btn btn-primary w-100">Generate</button>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="report-card" onclick="openReportModal('yearly')">
                                                        <i class="fas fa-calendar fa-3x text-success mb-3"></i>
                                                        <h5>Yearly Report</h5>
                                                        <p class="text-muted">Generate yearly collection report</p>
                                                        <button class="btn btn-primary w-100">Generate</button>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="report-card" onclick="openReportModal('custom')">
                                                        <i class="fas fa-cog fa-3x text-success mb-3"></i>
                                                        <h5>Custom Report</h5>
                                                        <p class="text-muted">Generate custom date range report</p>
                                                        <button class="btn btn-primary w-100">Generate</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade payment-details-modal" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentDetailsModalLabel">
                        <i class="fas fa-receipt me-2"></i> Payment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Receipt Number:</span>
                        <span class="payment-detail-value" id="modalReceiptNumber">-</span>
                    </div>
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Date:</span>
                        <span class="payment-detail-value" id="modalDate">-</span>
                    </div>
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Student Name:</span>
                        <span class="payment-detail-value" id="modalStudentName">-</span>
                    </div>
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Student ID:</span>
                        <span class="payment-detail-value" id="modalStudentId">-</span>
                    </div>
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Payment Type:</span>
                        <span class="payment-detail-value" id="modalPaymentType">-</span>
                    </div>
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Amount:</span>
                        <span class="payment-detail-value">
                            <span id="modalAmount" class="payment-amount">-</span>
                        </span>
                    </div>
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Payment Method:</span>
                        <span class="payment-detail-value" id="modalPaymentMethod">-</span>
                    </div>
                    <div class="payment-detail-item">
                        <span class="payment-detail-label">Status:</span>
                        <span class="payment-detail-value">
                            <span id="modalStatus" class="payment-status-badge">-</span>
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="modalPrintBtn">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <div class="modal fade report-modal" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalLabel">
                        <i class="fas fa-chart-bar me-2"></i> Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="report-description" id="reportDescription">
                        View, download, or print the financial report.
                    </p>

                    <div id="dateRangeSelector" class="date-range-selector" style="display: none;">
                        <h6 class="mb-3">Select Date Range</h6>
                        <div class="date-input-group">
                            <label for="startDate">From Date:</label>
                            <input type="date" id="startDate" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="date-input-group">
                            <label for="endDate">To Date:</label>
                            <input type="date" id="endDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="report-summary">
                        <h6 class="mb-3">Report Summary</h6>
                        <div class="summary-item">
                            <span class="summary-label">Total Collections:</span>
                            <span class="summary-value" id="reportTotalCollections">₱0.00</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Total Payments:</span>
                            <span class="summary-value" id="reportTotalPayments">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Report Period:</span>
                            <span class="summary-value" id="reportPeriod">-</span>
                        </div>
                    </div>

                    <div class="report-transactions" id="reportTransactions" style="display: none;">
                        <h6 class="mb-3">Recent Transactions</h6>
                        <?php if (!empty($dailyTransactions)): ?>
                            <?php foreach ($dailyTransactions as $transaction): ?>
                                <div class="transaction-item">
                                    <span class="transaction-date"><?php echo date('M d, Y', strtotime($transaction['payment_date'])); ?></span>
                                    <span class="transaction-student"><?php echo htmlspecialchars($transaction['student_name']); ?></span>
                                    <span class="transaction-amount">₱<?php echo number_format($transaction['amount'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No transactions found for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printReportBtn">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cache for payment data
        const paymentCache = new Map();
        let currentReceiptId = null;
        let currentReportType = null;
        let isPrintMode = false;

        // Tab switching
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Open Report Modal
        function openReportModal(reportType) {
            currentReportType = reportType;
            const modal = new bootstrap.Modal(document.getElementById('reportModal'));

            // Set modal title based on report type
            const modalTitle = document.getElementById('reportModalLabel');
            const dateRangeSelector = document.getElementById('dateRangeSelector');
            const reportTransactions = document.getElementById('reportTransactions');

            switch (reportType) {
                case 'daily':
                    modalTitle.innerHTML = '<i class="fas fa-calendar-day me-2"></i> Daily Report';
                    updateReportSummary('Today', '<?php echo date("M d, Y"); ?>');
                    dateRangeSelector.style.display = 'none';
                    reportTransactions.style.display = 'block';
                    break;
                case 'monthly':
                    modalTitle.innerHTML = '<i class="fas fa-calendar-alt me-2"></i> Monthly Report';
                    updateReportSummary('This Month', '<?php echo date("F Y"); ?>');
                    dateRangeSelector.style.display = 'none';
                    reportTransactions.style.display = 'none';
                    break;
                case 'yearly':
                    modalTitle.innerHTML = '<i class="fas fa-calendar me-2"></i> Yearly Report';
                    updateReportSummary('This Year', '<?php echo $current_year; ?>');
                    dateRangeSelector.style.display = 'none';
                    reportTransactions.style.display = 'none';
                    break;
                case 'custom':
                    modalTitle.innerHTML = '<i class="fas fa-cog me-2"></i> Custom Report';
                    updateReportSummary('Custom Range', 'Select dates below');
                    dateRangeSelector.style.display = 'block';
                    reportTransactions.style.display = 'none';
                    break;
            }

            modal.show();
        }

        // Update report summary
        function updateReportSummary(periodType, periodValue) {
            document.getElementById('reportPeriod').textContent = periodValue;

            switch (currentReportType) {
                case 'daily':
                    document.getElementById('reportTotalCollections').textContent = '₱<?php echo number_format($paidToday, 2); ?>';
                    document.getElementById('reportTotalPayments').textContent = '<?php echo count($dailyTransactions); ?>';
                    break;
                case 'monthly':
                    document.getElementById('reportTotalCollections').textContent = '₱<?php echo number_format($monthCollections, 2); ?>';
                    // You would fetch actual count for monthly payments here
                    document.getElementById('reportTotalPayments').textContent = 'Estimated';
                    break;
                case 'yearly':
                    document.getElementById('reportTotalCollections').textContent = '₱<?php echo number_format($totalCollections, 2); ?>';
                    document.getElementById('reportTotalPayments').textContent = 'Estimated';
                    break;
                case 'custom':
                    document.getElementById('reportTotalCollections').textContent = '₱0.00';
                    document.getElementById('reportTotalPayments').textContent = '0';
                    break;
            }
        }

        // Print report
        document.getElementById('printReportBtn').addEventListener('click', function() {
            const reportType = currentReportType.charAt(0).toUpperCase() + currentReportType.slice(1);
            const period = document.getElementById('reportPeriod').textContent;
            const totalCollections = document.getElementById('reportTotalCollections').textContent;

            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${reportType} Report - Creative Dreams</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 2px solid #52a347;
                            padding-bottom: 15px;
                        }
                        .report-header h1 {
                            color: #52a347;
                            margin: 0;
                            font-size: 24px;
                        }
                        .report-header h2 {
                            color: #666;
                            margin: 5px 0 0 0;
                            font-size: 18px;
                        }
                        .report-info {
                            margin: 20px 0;
                        }
                        .report-info .row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 10px;
                            padding-bottom: 10px;
                            border-bottom: 1px solid #eee;
                        }
                        .report-info .label {
                            font-weight: bold;
                            color: #555;
                        }
                        .report-info .value {
                            color: #333;
                        }
                        .summary-box {
                            background: #f8f9fa;
                            border-radius: 8px;
                            padding: 20px;
                            margin: 20px 0;
                        }
                        .summary-title {
                            font-weight: bold;
                            color: #52a347;
                            margin-bottom: 15px;
                        }
                        .summary-item {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 10px;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 40px;
                            padding-top: 20px;
                            border-top: 1px solid #eee;
                            color: #666;
                            font-size: 12px;
                        }
                        @media print {
                            body {
                                margin: 0;
                                padding: 20px;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Creative Dreams School</h1>
                        <h2>${reportType} Financial Report</h2>
                        <p>Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                    </div>
                    
                    <div class="report-info">
                        <div class="row">
                            <div class="label">Report Type:</div>
                            <div class="value">${reportType} Report</div>
                        </div>
                        <div class="row">
                            <div class="label">Report Period:</div>
                            <div class="value">${period}</div>
                        </div>
                        <div class="row">
                            <div class="label">Generated By:</div>
                            <div class="value"><?php echo htmlspecialchars($adminName); ?></div>
                        </div>
                    </div>
                    
                    <div class="summary-box">
                        <div class="summary-title">Financial Summary</div>
                        <div class="summary-item">
                            <span>Total Collections:</span>
                            <span><strong>${totalCollections}</strong></span>
                        </div>
                        <div class="summary-item">
                            <span>Total Payments Count:</span>
                            <span><strong>${document.getElementById('reportTotalPayments').textContent}</strong></span>
                        </div>
                        <div class="summary-item">
                            <span>Pending Payments:</span>
                            <span><strong>₱<?php echo number_format($pendingPayments, 2); ?></strong></span>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>This is an official financial report from Creative Dreams School.</p>
                        <p>Creative Dreams School • Inspire. Learn. Achieve.</p>
                        <p>Report ID: RPT-${new Date().getTime()}</p>
                    </div>
                </body>
                </html>
            `;

            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();

            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        });

        // View payment details
        function viewPaymentDetails(receiptId, autoPrint = false) {
            currentReceiptId = receiptId;
            isPrintMode = autoPrint;

            // Show loading state in modal
            document.getElementById('modalReceiptNumber').textContent = 'Loading...';
            document.getElementById('modalDate').textContent = 'Loading...';
            document.getElementById('modalStudentName').textContent = 'Loading...';
            document.getElementById('modalStudentId').textContent = 'Loading...';
            document.getElementById('modalPaymentType').textContent = 'Loading...';
            document.getElementById('modalAmount').textContent = 'Loading...';
            document.getElementById('modalPaymentMethod').textContent = 'Loading...';
            document.getElementById('modalStatus').textContent = 'Loading...';

            // Check cache first
            if (paymentCache.has(receiptId)) {
                populatePaymentModal(paymentCache.get(receiptId));
                showPaymentModal();
                return;
            }

            // Fetch payment data
            fetchPaymentData(receiptId)
                .then(data => {
                    paymentCache.set(receiptId, data);
                    populatePaymentModal(data);
                    showPaymentModal();
                })
                .catch(error => {
                    alert('Error loading payment details: ' + error.message);
                });
        }

        // Print payment receipt
        function printPaymentReceipt(receiptId) {
            viewPaymentDetails(receiptId, true);
        }

        // Fetch payment data from server
        async function fetchPaymentData(receiptId) {
            const response = await fetch(`?action=get_payment_details&receipt_id=${encodeURIComponent(receiptId)}`);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            return result.data;
        }

        // Show payment modal
        function showPaymentModal() {
            const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
            modal.show();

            // If in print mode, wait for modal to show then trigger print
            if (isPrintMode) {
                document.getElementById('paymentDetailsModal').addEventListener('shown.bs.modal', function() {
                    setTimeout(() => {
                        printReceiptContent();
                    }, 500);
                }, {
                    once: true
                });
            }
        }

        // Populate payment modal with data
        function populatePaymentModal(data) {
            document.getElementById('modalReceiptNumber').textContent = data.receipt_number;
            document.getElementById('modalDate').textContent = formatDate(data.payment_date);
            document.getElementById('modalStudentName').textContent = data.student_name;
            document.getElementById('modalStudentId').textContent = data.student_code;
            document.getElementById('modalPaymentType').textContent = data.payment_type;
            document.getElementById('modalAmount').textContent = formatCurrency(data.amount);
            document.getElementById('modalPaymentMethod').textContent = data.payment_method;

            // Set status badge
            const statusBadge = document.getElementById('modalStatus');
            statusBadge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            statusBadge.className = 'payment-status-badge ';
            if (data.status === 'paid') {
                statusBadge.classList.add('payment-status-paid');
            } else if (data.status === 'pending') {
                statusBadge.classList.add('payment-status-pending');
            } else {
                statusBadge.classList.add('payment-status-cancelled');
            }

            // Update print button
            document.getElementById('modalPrintBtn').onclick = function() {
                printReceiptContent();
            };
        }

        // Print receipt content
        function printReceiptContent() {
            const receiptNumber = document.getElementById('modalReceiptNumber').textContent;
            const date = document.getElementById('modalDate').textContent;
            const studentName = document.getElementById('modalStudentName').textContent;
            const studentId = document.getElementById('modalStudentId').textContent;
            const paymentType = document.getElementById('modalPaymentType').textContent;
            const amount = document.getElementById('modalAmount').textContent;
            const paymentMethod = document.getElementById('modalPaymentMethod').textContent;
            const status = document.getElementById('modalStatus').textContent;
            const statusClass = document.getElementById('modalStatus').className;

            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Payment Receipt - ${receiptNumber}</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .receipt-header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 2px solid #52a347;
                            padding-bottom: 15px;
                        }
                        .receipt-header h1 {
                            color: #52a347;
                            margin: 0;
                        }
                        .receipt-header h2 {
                            color: #666;
                            margin: 5px 0 0 0;
                            font-size: 18px;
                        }
                        .receipt-details {
                            width: 100%;
                            margin: 20px 0;
                        }
                        .receipt-details .row {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 12px;
                            padding-bottom: 12px;
                            border-bottom: 1px solid #eee;
                        }
                        .receipt-details .label {
                            font-weight: bold;
                            color: #555;
                            flex: 1;
                        }
                        .receipt-details .value {
                            color: #333;
                            flex: 1;
                            text-align: right;
                        }
                        .amount {
                            font-size: 24px;
                            font-weight: bold;
                            color: #4caf50;
                            text-align: center;
                            margin: 30px 0;
                            padding: 15px;
                            background: #f8f9fa;
                            border-radius: 8px;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 6px 12px;
                            border-radius: 20px;
                            font-weight: bold;
                            font-size: 12px;
                            text-transform: uppercase;
                        }
                        .status-paid {
                            background: #e8f5e9;
                            color: #4caf50;
                        }
                        .status-pending {
                            background: #fff3e0;
                            color: #ff9800;
                        }
                        .status-cancelled {
                            background: #ffebee;
                            color: #f44336;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 40px;
                            padding-top: 20px;
                            border-top: 1px solid #eee;
                            color: #666;
                            font-size: 12px;
                        }
                        @media print {
                            body {
                                margin: 0;
                                padding: 20px;
                            }
                            .no-print {
                                display: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-header">
                        <h1>Creative Dreams School</h1>
                        <h2>Payment Receipt</h2>
                        <p>Date Printed: ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="receipt-details">
                        <div class="row">
                            <div class="label">Receipt Number:</div>
                            <div class="value"><strong>${receiptNumber}</strong></div>
                        </div>
                        <div class="row">
                            <div class="label">Date:</div>
                            <div class="value">${date}</div>
                        </div>
                        <div class="row">
                            <div class="label">Student Name:</div>
                            <div class="value">${studentName}</div>
                        </div>
                        <div class="row">
                            <div class="label">Student ID:</div>
                            <div class="value">${studentId}</div>
                        </div>
                        <div class="row">
                            <div class="label">Payment Type:</div>
                            <div class="value">${paymentType}</div>
                        </div>
                        <div class="row">
                            <div class="label">Payment Method:</div>
                            <div class="value">${paymentMethod}</div>
                        </div>
                        <div class="row">
                            <div class="label">Status:</div>
                            <div class="value"><span class="status-badge ${statusClass}">${status}</span></div>
                        </div>
                    </div>
                    
                    <div class="amount">
                        ${amount}
                    </div>
                    
                    <div class="footer">
                        <p>Thank you for your payment!</p>
                        <p>Creative Dreams School • Inspire. Learn. Achieve.</p>
                        <p>This is a computer-generated receipt. No signature required.</p>
                    </div>
                </body>
                </html>
            `;

            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();

            // Wait for content to load before printing
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }

        // Format currency
        function formatCurrency(amount) {
            return '₱' + parseFloat(amount).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // View student payment history
        function viewStudentPayments(studentId) {
            alert(`Viewing payment history for student ID: ${studentId}\n\nThis would show all payment transactions for this student.`);
        }

        // Send payment reminder
        function sendReminder(studentId) {
            if (confirm('Send payment reminder to this student\'s parent/guardian?')) {
                alert(`Payment reminder sent for student ID: ${studentId}\n\nIn a complete implementation, this would send an email/SMS notification.`);
            }
        }

        // Record payment for specific student
        function recordPaymentFor(studentId) {
            showTab('record');
            document.getElementById('student_id').value = studentId;
            document.getElementById('student_id').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Export collection summary
        function exportCollectionSummary() {
            alert('Exporting collection summary to Excel...\n\nIn a complete implementation, this would download an Excel file.');
        }

        // Search functionality for unpaid students
        document.getElementById('searchUnpaid')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('#unpaidStudentsContainer .unpaid-student-card');

            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        // Form validation
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const amount = parseFloat(document.querySelector('input[name="amount"]').value);
            if (amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount greater than 0.');
                return false;
            }
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Initialize payment type chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('paymentTypeChart');

            if (ctx) {
                const paymentTypes = <?php echo json_encode(array_column($paymentTypeBreakdown, 'payment_type')); ?>;
                const amounts = <?php echo json_encode(array_column($paymentTypeBreakdown, 'total')); ?>;

                const backgroundColors = [
                    '#4caf50', '#2196f3', '#ff9800', '#f44336', '#9c27b0',
                    '#00bcd4', '#ffeb3b', '#795548', '#607d8b', '#8bc34a'
                ];

                if (paymentTypes.length > 0) {
                    new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: paymentTypes,
                            datasets: [{
                                data: amounts,
                                backgroundColor: backgroundColors,
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = Math.round((value / total) * 100);
                                            return `${label}: ₱${value.toFixed(2)} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else {
                    ctx.parentNode.innerHTML = '<p class="text-muted text-center">No payment data available</p>';
                }
            }
        });

        // Handle form submission
        document.getElementById('filterForm')?.addEventListener('submit', function(e) {
            // Let the form submit normally
        });
    </script>
</body>

</html>