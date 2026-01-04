<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'cdsportal';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Fetch student information
$stmt = $pdo->prepare("
    SELECT 
        s.*,
        u.username,
        sec.section_name,
        sec.grade_level,
        p.first_name as parent_first_name,
        p.last_name as parent_last_name,
        p.middle_name as parent_middle_name,
        p.contact_number as parent_contact,
        p.email as parent_email,
        p.address as parent_address
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    LEFT JOIN parents p ON s.parent_id = p.parent_id
    WHERE s.user_id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student information not found.");
}

// Get system settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$current_year = $settings['current_school_year'] ?? date('Y');
$school_name = $settings['school_name'] ?? 'Creative Dreams School';
$school_address = $settings['school_address'] ?? '';
$school_contact = $settings['school_contact'] ?? '';

// Generate certificate number
$certificate_number = 'COE-' . $current_year . '-' . str_pad($student['student_id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Enrollment - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', serif;
            background: white;
            color: #333;
            line-height: 1.6;
        }

        .certificate-container {
            width: 21cm;
            min-height: 29.7cm;
            padding: 2cm;
            margin: 0 auto;
            background: white;
            position: relative;
        }

        .certificate-border {
            border: 8px double #2d5a24;
            padding: 1.5cm;
            min-height: 25.7cm;
            position: relative;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2d5a24;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
        }

        .school-name {
            font-size: 28px;
            font-weight: bold;
            color: #2d5a24;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .school-address {
            font-size: 14px;
            color: #666;
            margin-bottom: 3px;
        }

        .certificate-title {
            text-align: center;
            margin: 40px 0 30px;
        }

        .certificate-title h1 {
            font-size: 32px;
            color: #2d5a24;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 10px;
        }

        .certificate-number {
            font-size: 12px;
            color: #666;
            font-family: 'Arial', sans-serif;
        }

        .content {
            margin: 30px 0;
            text-align: center;
            font-size: 16px;
        }

        .content p {
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .student-name {
            font-size: 24px;
            font-weight: bold;
            color: #2d5a24;
            text-decoration: underline;
            margin: 15px 0;
            text-transform: uppercase;
        }

        .details-box {
            background: #f8f9fa;
            border: 2px solid #2d5a24;
            border-radius: 10px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 15px;
            font-size: 14px;
        }

        .details-label {
            font-weight: bold;
            color: #2d5a24;
        }

        .details-value {
            color: #333;
        }

        .footer-text {
            margin-top: 40px;
            text-align: center;
            font-size: 13px;
            font-style: italic;
            color: #666;
        }

        .signatures {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            padding: 0 50px;
        }

        .signature-block {
            text-align: center;
            width: 250px;
        }

        .signature-line {
            border-top: 2px solid #333;
            margin-bottom: 5px;
            padding-top: 5px;
        }

        .signature-title {
            font-size: 12px;
            font-weight: bold;
            color: #333;
        }

        .signature-name {
            font-size: 11px;
            color: #666;
        }

        .issue-date {
            text-align: right;
            margin-top: 40px;
            font-size: 13px;
            color: #666;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            font-size: 120px;
            font-weight: bold;
            color: #2d5a24;
            pointer-events: none;
            z-index: 0;
        }

        .print-btn {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
        }

        .print-btn button {
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
            transition: all 0.3s;
        }

        .print-btn button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }

        @media print {
            .print-btn {
                display: none;
            }
            
            body {
                background: white;
            }
            
            .certificate-container {
                padding: 0;
                width: 100%;
            }
        }

        @page {
            size: A4;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="print-btn">
        <button onclick="window.print()">
            <i>🖨️</i> Print Certificate
        </button>
    </div>

    <div class="certificate-container">
        <div class="certificate-border">
            <div class="watermark">OFFICIAL</div>

            <!-- Header -->
            <div class="header">
                <div class="school-logo">
                    <img src="../images/cdslogo.png" alt="School Logo" style="width: 100%; height: 100%;">
                </div>
                <div class="school-name"><?php echo htmlspecialchars($school_name); ?></div>
                <div class="school-address"><?php echo htmlspecialchars($school_address); ?></div>
                <div class="school-address"><?php echo htmlspecialchars($school_contact); ?></div>
            </div>

            <!-- Certificate Title -->
            <div class="certificate-title">
                <h1>Certificate of Enrollment</h1>
                <div class="certificate-number">Certificate No: <?php echo htmlspecialchars($certificate_number); ?></div>
            </div>

            <!-- Content -->
            <div class="content">
                <p>This is to certify that</p>
                
                <div class="student-name">
                    <?php echo htmlspecialchars(strtoupper($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'])); ?>
                </div>

                <p>is currently enrolled at <?php echo htmlspecialchars($school_name); ?><br>
                for the Academic Year <strong><?php echo htmlspecialchars($current_year); ?></strong></p>
            </div>

            <!-- Student Details -->
            <div class="details-box">
                <div class="details-grid">
                    <div class="details-label">Student Code (LRN):</div>
                    <div class="details-value"><?php echo htmlspecialchars($student['student_code']); ?></div>

                    <div class="details-label">Grade Level:</div>
                    <div class="details-value"><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></div>

                    <div class="details-label">Section:</div>
                    <div class="details-value"><?php echo htmlspecialchars($student['section_name'] ?? 'Not Assigned'); ?></div>

                    <div class="details-label">Academic Year:</div>
                    <div class="details-value"><?php echo htmlspecialchars($current_year); ?></div>

                    <div class="details-label">Date of Birth:</div>
                    <div class="details-value"><?php echo $student['birthdate'] ? date('F d, Y', strtotime($student['birthdate'])) : 'N/A'; ?></div>

                    <div class="details-label">Date Enrolled:</div>
                    <div class="details-value"><?php echo $student['date_enrolled'] ? date('F d, Y', strtotime($student['date_enrolled'])) : 'N/A'; ?></div>

                    <div class="details-label">Parent/Guardian:</div>
                    <div class="details-value">
                        <?php 
                        $parent_name = trim(($student['parent_first_name'] ?? '') . ' ' . 
                                           ($student['parent_middle_name'] ?? '') . ' ' . 
                                           ($student['parent_last_name'] ?? ''));
                        echo htmlspecialchars($parent_name ?: 'N/A'); 
                        ?>
                    </div>

                    <div class="details-label">Contact Number:</div>
                    <div class="details-value"><?php echo htmlspecialchars($student['parent_contact'] ?? 'N/A'); ?></div>
                </div>
            </div>

            <!-- Footer Text -->
            <div class="footer-text">
                This certificate is issued for whatever legal purpose it may serve.
            </div>

            <!-- Issue Date -->
            <div class="issue-date">
                Issued this <?php echo date('jS \d\a\y \o\f F, Y'); ?>
            </div>

            <!-- Signatures -->
            <div class="signatures">
                <div class="signature-block">
                    <div class="signature-line">_________________________</div>
                    <div class="signature-title">Class Adviser</div>
                    <div class="signature-name"><?php echo htmlspecialchars($student['section_name'] ?? ''); ?></div>
                </div>

                <div class="signature-block">
                    <div class="signature-line">_________________________</div>
                    <div class="signature-title">School Registrar</div>
                    <div class="signature-name"><?php echo htmlspecialchars($school_name); ?></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print option (uncomment if needed)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>