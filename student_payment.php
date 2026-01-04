<?php
require_once 'student_header.php';
require_once 'student_layout.php';

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
    'payment_history' => $payment_history
];

ob_start();
?>

<style>
@media print {
    /* Hide everything except print content */
    body * {
        visibility: hidden;
    }
    
    #printArea, #printArea * {
        visibility: visible;
    }
    
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    /* Hide buttons and navigation when printing */
    .no-print,
    .sidebar,
    .navbar,
    button,
    .btn {
        display: none !important;
    }
    
    /* Print styles */
    .print-receipt {
        padding: 20px;
        font-family: Arial, sans-serif;
    }
    
    .print-header {
        text-align: center;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    
    .print-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .print-table th,
    .print-table td {
        border: 1px solid #000;
        padding: 8px;
        text-align: left;
    }
    
    .print-footer {
        margin-top: 30px;
        border-top: 2px solid #000;
        padding-top: 10px;
        text-align: center;
    }
}

/* Modal styles */
.modal-receipt {
    font-family: Arial, sans-serif;
}

.receipt-header {
    text-align: center;
    border-bottom: 2px solid #333;
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.receipt-body {
    margin: 20px 0;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.receipt-footer {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 2px solid #333;
    text-align: center;
}
</style>

<h4 class="fw-bold mb-3">Payment Information</h4>

<div class="row">
    <div class="col-md-8">
        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Tuition Fee Summary</h6>
                <button class="btn btn-success btn-sm no-print" onclick="printStatement()">
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
                    <?php if (empty($payment_details['payment_history'])): ?>
                        <div class="alert alert-warning text-center" role="alert">
                            <i class="bi bi-info-circle"></i> No payments made yet.
                        </div>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Receipt No.</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th class="no-print">Action</th>
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
                                                <?php echo ucwords(str_replace('_', ' ', $payment['method'])); ?>
                                            </span>
                                        </td>
                                        <td class="no-print">
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick='printIndividualReceipt(<?php echo json_encode($payment); ?>)'>
                                                <i class="bi bi-printer"></i> Print
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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
            </ul>

            <div class="alert alert-warning small" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Note:</strong> All payments must be made at the school cashier. Online payments are not available at this time.
            </div>

            <p class="small mb-2 mt-3"><strong>Cashier Office Hours:</strong></p>
            <p class="small mb-0">
                <i class="bi bi-clock"></i> Monday - Friday<br>
                8:00 AM - 12:00 PM<br>
                1:00 PM - 5:00 PM
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

<!-- Hidden Print Area for Statement -->
<div id="printArea" style="display: none;">
    <div class="print-receipt">
        <div class="print-header">
            <h2>Creative Dreams School</h2>
            <p>Payment Statement</p>
            <p>School Year: <?php echo $current_school_year; ?></p>
        </div>
        
        <div style="margin: 20px 0;">
            <p><strong>Student Name:</strong> <?php echo $student_info['full_name']; ?></p>
            <p><strong>Student Code:</strong> <?php echo $student_code; ?></p>
            <p><strong>Date Printed:</strong> <?php echo date('F d, Y'); ?></p>
        </div>

        <table class="print-table">
            <tr>
                <td><strong>Total Tuition Fee:</strong></td>
                <td style="text-align: right;">₱<?php echo number_format($payment_details['tuition_fee'], 2); ?></td>
            </tr>
            <tr>
                <td><strong>Amount Paid:</strong></td>
                <td style="text-align: right;">₱<?php echo number_format($payment_details['paid_amount'], 2); ?></td>
            </tr>
            <tr>
                <td><strong>Remaining Balance:</strong></td>
                <td style="text-align: right;">₱<?php echo number_format($payment_details['balance'], 2); ?></td>
            </tr>
            <tr>
                <td><strong>Due Date:</strong></td>
                <td style="text-align: right;"><?php echo date('F d, Y', strtotime($payment_details['due_date'])); ?></td>
            </tr>
        </table>

        <?php if (!empty($payment_details['payment_history'])): ?>
            <h4 style="margin-top: 30px;">Payment History</h4>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Receipt No.</th>
                        <th>Payment Method</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_details['payment_history'] as $payment): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($payment['date'])); ?></td>
                            <td><?php echo $payment['receipt']; ?></td>
                            <td><?php echo ucwords(str_replace('_', ' ', $payment['method'])); ?></td>
                            <td style="text-align: right;">₱<?php echo number_format($payment['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="print-footer">
            <p><em>This is a computer-generated statement. No signature required.</em></p>
            <p>Creative Dreams School | Tel: (02) 1234-5678 | Email: accounting@cds.edu.ph</p>
        </div>
    </div>
</div>

<!-- Modal for Individual Receipt -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalLabel">Payment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="printModalContent()">
                    <i class="bi bi-printer"></i> Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const studentInfo = {
    name: '<?php echo addslashes($student_info['full_name']); ?>',
    code: '<?php echo $student_code; ?>',
    schoolYear: '<?php echo $current_school_year; ?>'
};

function printStatement() {
    const printArea = document.getElementById('printArea');
    printArea.style.display = 'block';
    window.print();
    printArea.style.display = 'none';
}

function printIndividualReceipt(payment) {
    const receiptContent = `
        <div class="modal-receipt" id="modalPrintArea">
            <div class="receipt-header">
                <h3>Creative Dreams School</h3>
                <p>Official Receipt</p>
                <p style="margin: 5px 0;"><strong>Receipt No: ${payment.receipt}</strong></p>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-row">
                    <strong>Student Name:</strong>
                    <span>${studentInfo.name}</span>
                </div>
                <div class="receipt-row">
                    <strong>Student Code:</strong>
                    <span>${studentInfo.code}</span>
                </div>
                <div class="receipt-row">
                    <strong>School Year:</strong>
                    <span>${studentInfo.schoolYear}</span>
                </div>
                <div class="receipt-row">
                    <strong>Payment Date:</strong>
                    <span>${new Date(payment.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                <div class="receipt-row">
                    <strong>Payment Method:</strong>
                    <span>${payment.method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                </div>
                <div class="receipt-row" style="border-top: 2px solid #333; margin-top: 20px; padding-top: 15px; font-size: 1.2em;">
                    <strong>Amount Paid:</strong>
                    <strong style="color: #28a745;">₱${parseFloat(payment.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                </div>
            </div>
            
            <div class="receipt-footer">
                <p style="margin: 5px 0;">Thank you for your payment!</p>
                <p style="margin: 5px 0; font-size: 0.9em;"><em>This is a computer-generated receipt. No signature required.</em></p>
                <p style="margin: 5px 0; font-size: 0.85em;">Creative Dreams School | Tel: (02) 1234-5678</p>
                <p style="margin: 5px 0; font-size: 0.85em;">Email: accounting@cds.edu.ph</p>
            </div>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = receiptContent;
    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    modal.show();
}

function printModalContent() {
    const printWindow = window.open('', '_blank');
    const receiptContent = document.getElementById('modalPrintArea').innerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Receipt</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    padding: 20px;
                    max-width: 800px;
                    margin: 0 auto;
                }
                .receipt-header {
                    text-align: center;
                    border-bottom: 2px solid #333;
                    padding-bottom: 15px;
                    margin-bottom: 20px;
                }
                .receipt-body {
                    margin: 20px 0;
                }
                .receipt-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }
                .receipt-footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 2px solid #333;
                    text-align: center;
                }
                @media print {
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            ${receiptContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}
</script>

<?php
$content = ob_get_clean();
renderLayout('Payment Information', $content, 'payment', $student_info, $initials, $profile_picture_url);
?>