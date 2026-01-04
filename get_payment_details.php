<?php
session_start();


$host = 'localhost';
$dbname = 'u545996239_cdsportal';
$username = 'u545996239_cdsportal'; // Changed variable name to avoid conflict
$password = 'B@nana2025';     //


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}


if (isset($_GET['payment_id'])) {
    $payment_id = $_GET['payment_id'];
   
    try {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.section_id
            FROM payments p
            JOIN students s ON p.student_code = s.student_code
            WHERE p.payment_id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
       
        if ($payment) {
            ?>
            <div class="receipt-container">
                <div class="receipt-header">
                    <h3>Creative Dreams Academy</h3>
                    <p class="mb-1">Payment Receipt</p>
                    <h4 class="text-success"><?php echo htmlspecialchars($payment['receipt_number']); ?></h4>
                </div>
               
                <div class="receipt-details">
                    <div class="row">
                        <div class="col-6">
                            <strong>Student Name:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo htmlspecialchars($payment['student_name']); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Student Code:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo htmlspecialchars($payment['student_code']); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Section:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo htmlspecialchars($payment['section_id']); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Payment Type:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo ucfirst(htmlspecialchars($payment['payment_type'])); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Amount:</strong>
                        </div>
                        <div class="col-6">
                            <h5 class="text-success">₱<?php echo number_format($payment['amount'], 2); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Payment Method:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($payment['payment_method']))); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Payment Date:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo date('F d, Y', strtotime($payment['payment_date'])); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>School Year:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo htmlspecialchars($payment['school_year']); ?>
                        </div>
                    </div>
                    <?php if ($payment['quarter']): ?>
                    <div class="row">
                        <div class="col-6">
                            <strong>Quarter:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo htmlspecialchars($payment['quarter']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($payment['notes']): ?>
                    <div class="row">
                        <div class="col-6">
                            <strong>Notes:</strong>
                        </div>
                        <div class="col-6">
                            <?php echo htmlspecialchars($payment['notes']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-6">
                            <strong>Status:</strong>
                        </div>
                        <div class="col-6">
                            <span class="badge bg-<?php echo $payment['status'] == 'paid' ? 'success' : ($payment['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
               
                <div class="receipt-footer">
                    <p class="mb-1">Thank you for your payment!</p>
                    <small class="text-muted">Creative Dreams Academy - Inspire. Learn. Achieve.</small>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-danger">Payment not found.</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error loading payment details: ' . $e->getMessage() . '</div>';
    }
} else {
    echo '<div class="alert alert-danger">No payment ID specified.</div>';
}
?>

