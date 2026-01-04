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

// Get appointment ID from URL
$appointment_id = $_GET['appointment_id'] ?? 0;

// Fetch appointment details
$appointment = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM enrollment WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
} catch(PDOException $e) {
    die("Error fetching appointment details: " . $e->getMessage());
}

if (!$appointment) {
    die("Appointment not found.");
}

// Generate enrollment confirmation number
$enrollment_number = 'ENR-' . str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) . '-' . date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Completion Certificate - Creative Dreams School</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: white;
            color: #333;
            line-height: 1.4;
            font-size: 12px;
        }

        .print-container {
            width: 21cm;
            min-height: 29.7cm;
            padding: 1.5cm;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #4a8240;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .school-name {
            font-size: 18px;
            font-weight: bold;
            color: #2d5a24;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .school-tagline {
            font-size: 11px;
            color: #666;
            font-style: italic;
        }

        .certificate-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #2d5a24;
            margin: 15px 0;
            text-transform: uppercase;
        }

        .completion-badge {
            text-align: center;
            background: linear-gradient(135deg, #52a347, #3d6e35);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 11px;
        }

        .completion-badge .status {
            font-size: 14px;
            font-weight: bold;
            margin: 5px 0;
        }

        .enrollment-number {
            text-align: center;
            background: #f8f9fa;
            padding: 8px;
            border: 1px dashed #4a8240;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 11px;
        }

        .enrollment-number .number {
            font-size: 14px;
            font-weight: bold;
            color: #2d5a24;
        }

        .student-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 11px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #2d5a24;
            margin-bottom: 10px;
            border-bottom: 1px solid #4a8240;
            padding-bottom: 3px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 11px;
            color: #333;
            font-weight: 600;
        }

        .appointment-summary {
            background: #e6f1e6;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 11px;
        }

        .next-steps {
            background: #fff3e0;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 3px solid #ff9800;
            font-size: 11px;
        }

        .next-steps h3 {
            color: #ff9800;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .next-steps ul {
            padding-left: 15px;
            margin-bottom: 0;
        }

        .next-steps li {
            margin-bottom: 4px;
            font-size: 10px;
        }

        .completion-message {
            text-align: center;
            font-style: italic;
            color: #666;
            margin: 20px 0;
            padding: 15px;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 9px;
        }

        .signature-area {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 200px;
            text-align: center;
            padding-top: 3px;
            font-size: 9px;
            color: #666;
        }

        .print-btn {
            text-align: center;
            margin: 15px 0;
        }

        .print-btn button {
            background: #4a8240;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
        }

        .notes-section {
            background: #fff3e0;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 10px;
        }

        @media print {
            .print-btn {
                display: none;
            }
            
            body {
                background: white;
                font-size: 10px;
            }
            
            .print-container {
                padding: 1cm;
                width: 100%;
                min-height: auto;
            }
            
            .student-info,
            .appointment-summary,
            .next-steps {
                break-inside: avoid;
            }
        }

        @page {
            size: A4;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Print Button for Screen -->
        <div class="print-btn">
            <button onclick="window.print()">
                <i class="fas fa-print"></i> Print Certificate
            </button>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="school-name">Creative Dreams School</div>
            <div class="school-tagline">Inspire. Learn. Achieve.</div>
        </div>

        <!-- Certificate Title -->
        <div class="certificate-title">
            Enrollment Completion Certificate
        </div>

        <!-- Completion Badge -->
        <div class="completion-badge">
            <div class="status">ENROLLMENT COMPLETED</div>
            <div class="date"><?php echo date('M d, Y', strtotime($appointment['updated_at'])); ?></div>
        </div>

        <!-- Enrollment Number -->
        <div class="enrollment-number">
            <div class="label">Reference No:</div>
            <div class="number"><?php echo $enrollment_number; ?></div>
        </div>

        <!-- Student Information -->
        <div class="student-info">
            <div class="section-title">STUDENT INFORMATION</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Student Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($appointment['student_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Grade Level</span>
                    <span class="info-value"><?php echo htmlspecialchars($appointment['grade_level']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Parent/Guardian</span>
                    <span class="info-value"><?php echo htmlspecialchars($appointment['parent_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact No</span>
                    <span class="info-value"><?php echo htmlspecialchars($appointment['phone']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($appointment['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Appointment ID</span>
                    <span class="info-value"><?php echo htmlspecialchars($appointment['appointment_id']); ?></span>
                </div>
            </div>
        </div>

        <!-- Appointment Summary -->
        <div class="appointment-summary">
            <div class="section-title">APPOINTMENT SUMMARY</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Appointment Date</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['preferred_date'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Appointment Time</span>
                    <span class="info-value"><?php echo htmlspecialchars($appointment['preferred_time']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Completion Date</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($appointment['updated_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value" style="color: #52a347;">COMPLETED</span>
                </div>
            </div>
        </div>

        <!-- Completion Message -->
        <div class="completion-message">
            "Congratulations! Your enrollment process has been successfully completed. 
            We look forward to welcoming you to Creative Dreams School."
        </div>

        <!-- Next Steps -->
        <div class="next-steps">
            <h3>NEXT STEPS</h3>
            <ul>
                <li><strong>Orientation:</strong> Check email for schedule</li>
                <li><strong>School ID:</strong> Available on first day</li>
                <li><strong>Class Schedule:</strong> Provided during orientation</li>
                <li><strong>Uniform:</strong> Available at school store</li>
            </ul>
        </div>

        <!-- Admin Notes (if any) -->
        <?php if (!empty($appointment['appointment_notes'])): ?>
        <div class="notes-section">
            <div class="section-title">ADMIN NOTES</div>
            <p><?php echo htmlspecialchars($appointment['appointment_notes']); ?></p>
        </div>
        <?php endif; ?>

        <!-- Signature Area -->
        <div class="signature-area">
            <div class="signature-line">
                Parent/Guardian Signature
            </div>
            <div class="signature-line">
                School Registrar
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Creative Dreams School</strong></p>
            <p>123 Learning Street, Education City | Phone: (02) 1234-5678</p>
            <p>Email: info@creativedreams.edu.ph</p>
            <p>Generated on: <?php echo date('M d, Y g:i A'); ?></p>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        window.onload = function() {
            // Uncomment the line below if you want auto-print
            // window.print();
        };

        // Add Font Awesome for icons
        const faLink = document.createElement('link');
        faLink.rel = 'stylesheet';
        faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(faLink);
    </script>
</body>
</html>