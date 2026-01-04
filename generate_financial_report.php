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


// Get report parameters
$type = $_GET['type'] ?? 'daily';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$autoprint = $_GET['autoprint'] ?? 0;


// Set date range based on report type
switch ($type) {
    case 'daily':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        $title = "Daily Financial Report - " . date('F d, Y');
        $filename = "daily_report_" . date('Y_m_d');
        break;
    case 'monthly':
        $start_date = date('Y-m-01', strtotime($month));
        $end_date = date('Y-m-t', strtotime($month));
        $title = "Monthly Financial Report - " . date('F Y', strtotime($month));
        $filename = "monthly_report_" . date('Y_m', strtotime($month));
        break;
    case 'yearly':
        $start_date = $year . '-01-01';
        $end_date = $year . '-12-31';
        $title = "Yearly Financial Report - " . $year;
        $filename = "yearly_report_" . $year;
        break;
    case 'custom':
        $title = "Custom Financial Report - " . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date));
        $filename = "custom_report_" . date('Y_m_d', strtotime($start_date)) . "_to_" . date('Y_m_d', strtotime($end_date));
        break;
    default:
        $title = "Financial Report";
        $filename = "financial_report";
}


// Fetch payment data
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.student_code,
               s.grade_level
        FROM payments p
        JOIN students s ON p.student_id = s.student_id
        WHERE p.payment_date BETWEEN ? AND ?
        AND p.status = 'paid'
        ORDER BY p.payment_date DESC, p.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $payments = $stmt->fetchAll();
   
    // Calculate totals and statistics
    $total_amount = 0;
    $payment_counts = [];
    $payment_methods = [];
    $grade_totals = [];
   
    foreach ($payments as $payment) {
        $total_amount += $payment['amount'];
       
        // Payment type breakdown
        $payment_type = $payment['payment_type'];
        if (!isset($payment_counts[$payment_type])) {
            $payment_counts[$payment_type] = 0;
        }
        $payment_counts[$payment_type] += $payment['amount'];
       
        // Payment method breakdown
        $payment_method = $payment['payment_method'];
        if (!isset($payment_methods[$payment_method])) {
            $payment_methods[$payment_method] = 0;
        }
        $payment_methods[$payment_method] += $payment['amount'];
       
        // Grade level breakdown
        $grade_level = $payment['grade_level'] ?? 'Not Specified';
        if (!isset($grade_totals[$grade_level])) {
            $grade_totals[$grade_level] = 0;
        }
        $grade_totals[$grade_level] += $payment['amount'];
    }
   
    $transaction_count = count($payments);
    $average_transaction = $transaction_count > 0 ? $total_amount / $transaction_count : 0;
   
} catch (PDOException $e) {
    $payments = [];
    $total_amount = 0;
    $payment_counts = [];
    $payment_methods = [];
    $grade_totals = [];
    $transaction_count = 0;
    $average_transaction = 0;
    error_log("Error fetching report data: " . $e->getMessage());
}


// Get school information for header
$school_name = "Creative Dreams International School";
$school_address = "123 Learning Avenue, Education City";
$school_contact = "(123) 456-7890 | info@creativedreams.edu";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Creative Dreams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table {
                font-size: 12px;
            }
            .report-header {
                margin-bottom: 10px !important;
                padding-bottom: 10px !important;
            }
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: white;
        }
        .report-header {
            border-bottom: 3px double #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .summary-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .text-success { color: #28a745 !important; }
        .text-primary { color: #007bff !important; }
        .text-warning { color: #ffc107 !important; }
        .text-info { color: #17a2b8 !important; }
        .table th {
            background-color: #343a40;
            color: white;
            border: 1px solid #454d55;
        }
        .breakdown-section {
            page-break-inside: avoid;
        }
        .footer-note {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-style: italic;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Report Header -->
        <div class="report-header">
            <div class="row align-items-center">
                <div class="col-2 text-start">
                    <div style="width: 80px; height: 80px; background: #52a347; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fas fa-graduation-cap fa-2x text-white"></i>
                    </div>
                </div>
                <div class="col-8">
                    <h2 class="mb-1"><?php echo $school_name; ?></h2>
                    <p class="mb-1 text-muted"><?php echo $school_address; ?></p>
                    <p class="mb-1 text-muted"><?php echo $school_contact; ?></p>
                    <h4 class="mt-3 text-dark"><?php echo $title; ?></h4>
                    <p class="text-muted mb-0">Generated on: <?php echo date('F d, Y \a\t h:i A'); ?></p>
                    <p class="text-muted">Report Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                </div>
                <div class="col-2 text-end">
                    <small class="text-muted">Confidential</small>
                </div>
            </div>
        </div>


        <!-- Executive Summary -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Executive Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="summary-card">
                                    <i class="fas fa-wallet fa-2x text-success mb-2"></i>
                                    <div class="stat-number text-success">₱<?php echo number_format($total_amount, 2); ?></div>
                                    <small class="text-muted">Total Collections</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-card">
                                    <i class="fas fa-receipt fa-2x text-primary mb-2"></i>
                                    <div class="stat-number text-primary"><?php echo $transaction_count; ?></div>
                                    <small class="text-muted">Total Transactions</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-card">
                                    <i class="fas fa-calculator fa-2x text-warning mb-2"></i>
                                    <div class="stat-number text-warning">₱<?php echo number_format($average_transaction, 2); ?></div>
                                    <small class="text-muted">Average per Transaction</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-card">
                                    <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                                    <div class="stat-number text-info"><?php echo date('M d, Y', strtotime($start_date)); ?></div>
                                    <small class="text-muted">to <?php echo date('M d, Y', strtotime($end_date)); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Payment Type Breakdown -->
        <?php if (!empty($payment_counts)): ?>
        <div class="row mb-4 breakdown-section">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Payment Type Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Payment Type</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_counts as $type => $amount):
                                        $percentage = $total_amount > 0 ? ($amount / $total_amount) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type); ?></td>
                                        <td class="text-end">₱<?php echo number_format($amount, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td class="text-end"><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                                        <td class="text-end"><strong>100%</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Payment Method Breakdown -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-credit-card"></i> Payment Method Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Payment Method</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_methods as $method => $amount):
                                        $percentage = $total_amount > 0 ? ($amount / $total_amount) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($method); ?></td>
                                        <td class="text-end">₱<?php echo number_format($amount, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- Grade Level Breakdown -->
        <?php if (!empty($grade_totals)): ?>
        <div class="row mb-4 breakdown-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Grade Level Collections</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Grade Level</th>
                                        <th class="text-end">Number of Transactions</th>
                                        <th class="text-end">Total Amount</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $grade_transactions = [];
                                    foreach ($payments as $payment) {
                                        $grade = $payment['grade_level'] ?? 'Not Specified';
                                        if (!isset($grade_transactions[$grade])) {
                                            $grade_transactions[$grade] = 0;
                                        }
                                        $grade_transactions[$grade]++;
                                    }
                                   
                                    foreach ($grade_totals as $grade => $amount):
                                        $percentage = $total_amount > 0 ? ($amount / $total_amount) * 100 : 0;
                                        $trans_count = $grade_transactions[$grade] ?? 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grade); ?></td>
                                        <td class="text-end"><?php echo $trans_count; ?></td>
                                        <td class="text-end">₱<?php echo number_format($amount, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


        <!-- Detailed Transactions -->
        <div class="row breakdown-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list-alt"></i> Detailed Transactions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No transactions found for the selected period</h5>
                                <p class="text-muted">There were no payment records between <?php echo date('M d, Y', strtotime($start_date)); ?> and <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Receipt #</th>
                                            <th>Student</th>
                                            <th>Grade</th>
                                            <th>Payment Type</th>
                                            <th class="text-end">Amount</th>
                                            <th>Method</th>
                                            <th>Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('m/d/Y', strtotime($payment['payment_date'])); ?></td>
                                            <td><small><?php echo htmlspecialchars($payment['receipt_number']); ?></small></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($payment['student_name']); ?></div>
                                                <small class="text-muted">#<?php echo htmlspecialchars($payment['student_code']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['grade_level'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                                            <td class="text-end fw-bold text-success">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                            <td><small><?php echo htmlspecialchars($payment['payment_period']); ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-dark">
                                        <tr>
                                            <td colspan="5" class="text-end fw-bold">Grand Total:</td>
                                            <td class="text-end fw-bold">₱<?php echo number_format($total_amount, 2); ?></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


        <!-- Report Footer -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="footer-note text-center">
                    <p class="mb-1">This report was automatically generated by the Creative Dreams School Management System</p>
                    <p class="mb-0">For questions or concerns, please contact the Accounting Office</p>
                </div>
            </div>
        </div>


        <!-- Action Buttons -->
        <div class="row mt-4 no-print">
            <div class="col-12 text-center">
                <button class="btn btn-primary me-2" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button class="btn btn-success me-2" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
                <button class="btn btn-secondary" onclick="window.close()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>


    <script>
        // Auto-print if requested
        <?php if ($autoprint): ?>
        window.onload = function() {
            window.print();
        };
        <?php endif; ?>


        function exportToExcel() {
            // Get current date for filename
            const today = new Date().toISOString().slice(0, 10);
           
            // Create a simple Excel export (in a real implementation, this would be server-side)
            alert('Excel export functionality would be implemented here.\n\nFile: <?php echo $filename; ?>_' + today + '.xlsx');
           
            // In a complete implementation, this would make an AJAX call to generate and download an Excel file
            // window.location.href = 'export_excel.php?type=<?php echo $type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>';
        }
    </script>
</body>
</html>

