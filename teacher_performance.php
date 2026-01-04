<?php
session_start();

// Check login + role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'teacher') {
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

// Get teacher info from session
$teacher_code = $_SESSION['username'];

// Fetch teacher details
try {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE teacher_code = ?");
    $stmt->execute([$teacher_code]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        header("Location: login.php");
        exit();
    }
    
    $first_name = $teacher['first_name'];
    $last_name = $teacher['last_name'];
    
    // Get subjects taught
    $stmt = $pdo->prepare("
        SELECT DISTINCT sub.subject_name 
        FROM subject_assignments sa
        INNER JOIN subjects sub ON sa.subject_code = sub.subject_code
        WHERE sa.teacher_code = ? AND sa.is_active = 1
    ");
    $stmt->execute([$teacher_code]);
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $subject_list = !empty($subjects) ? implode(', ', $subjects) : 'No subjects assigned';
    
} catch(PDOException $e) {
    die("Error fetching teacher data: " . $e->getMessage());
}

// Fetch classes taught by this teacher
try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.assignment_id,
            sa.section_id,
            sub.subject_code,
            sub.subject_name,
            sec.grade_level,
            sec.section_name,
            COUNT(DISTINCT s.student_id) as student_count
        FROM subject_assignments sa
        INNER JOIN subjects sub ON sa.subject_code = sub.subject_code
        INNER JOIN sections sec ON sa.section_id = sec.section_id
        LEFT JOIN students s ON sec.section_id = s.section_id AND s.status = 'active'
        WHERE sa.teacher_code = ? AND sa.is_active = 1
        GROUP BY sa.assignment_id, sa.section_id, sub.subject_code, sub.subject_name, 
                 sec.grade_level, sec.section_name
        ORDER BY sec.grade_level, sec.section_name, sub.subject_name
    ");
    $stmt->execute([$teacher_code]);
    $classes = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error fetching classes: " . $e->getMessage());
}

// Fetch advisory class
try {
    $stmt = $pdo->prepare("
        SELECT 
            sec.section_id,
            sec.grade_level,
            sec.section_name,
            COUNT(DISTINCT s.student_id) as student_count
        FROM sections sec
        LEFT JOIN students s ON sec.section_id = s.section_id AND s.status = 'active'
        WHERE sec.adviser_code = ? AND sec.is_active = 1
        GROUP BY sec.section_id, sec.grade_level, sec.section_name
    ");
    $stmt->execute([$teacher_code]);
    $advisory_class = $stmt->fetch();
} catch(PDOException $e) {
    die("Error fetching advisory class: " . $e->getMessage());
}

// STATIC DATA FOR DEMONSTRATION
// These will be replaced with real database queries later

// Overall Statistics
$overall_stats = [
    'total_students' => 125,
    'average_grade' => 86.5,
    'passing_rate' => 92.8,
    'at_risk_count' => 9
];

// Top Performers (Static)
$top_performers = [
    ['name' => 'Maria Santos', 'grade' => 98.5, 'section' => 'Grade 5 - Hidalgo', 'subject' => 'Mathematics'],
    ['name' => 'Juan Dela Cruz', 'grade' => 97.8, 'section' => 'Grade 4 - Charity', 'subject' => 'Science'],
    ['name' => 'Ana Reyes', 'grade' => 96.9, 'section' => 'Grade 5 - Amorsolo', 'subject' => 'English'],
    ['name' => 'Pedro Garcia', 'grade' => 96.5, 'section' => 'Grade 6 - Rizal', 'subject' => 'Mathematics'],
    ['name' => 'Sofia Martinez', 'grade' => 95.8, 'section' => 'Grade 4 - Serenity', 'subject' => 'Filipino']
];

// At-Risk Students (Static)
$at_risk_students = [
    ['name' => 'Mark Johnson', 'grade' => 68.5, 'section' => 'Grade 5 - Hidalgo', 'subject' => 'Mathematics', 'absences' => 12],
    ['name' => 'Lisa Wong', 'grade' => 70.2, 'section' => 'Grade 4 - Charity', 'subject' => 'Science', 'absences' => 8],
    ['name' => 'Carlos Ramos', 'grade' => 71.5, 'section' => 'Grade 6 - Rizal', 'subject' => 'English', 'absences' => 15],
    ['name' => 'Jenny Cruz', 'grade' => 69.8, 'section' => 'Grade 5 - Amorsolo', 'subject' => 'Filipino', 'absences' => 10],
    ['name' => 'Rico Santos', 'grade' => 72.3, 'section' => 'Grade 4 - Serenity', 'subject' => 'Mathematics', 'absences' => 7]
];

// Class Performance Summary (Static)
$class_performance = [
    [
        'class_name' => 'Mathematics - Grade 5 Hidalgo',
        'average' => 88.5,
        'passing' => 95,
        'excellent' => 12,
        'satisfactory' => 15,
        'needs_improvement' => 3,
        'total' => 30
    ],
    [
        'class_name' => 'Science - Grade 4 Charity',
        'average' => 85.2,
        'passing' => 90,
        'excellent' => 8,
        'satisfactory' => 18,
        'needs_improvement' => 4,
        'total' => 30
    ],
    [
        'class_name' => 'English - Grade 6 Rizal',
        'average' => 87.8,
        'passing' => 93,
        'excellent' => 15,
        'satisfactory' => 13,
        'needs_improvement' => 2,
        'total' => 30
    ]
];

// Grade Distribution Data (for charts)
$grade_distribution = [
    'excellent' => 35,      // 90-100
    'very_good' => 42,      // 85-89
    'good' => 28,           // 80-84
    'satisfactory' => 11,   // 75-79
    'needs_improvement' => 9 // Below 75
];

// Quarterly Performance Trend (Static)
$quarterly_trend = [
    'Q1' => 84.5,
    'Q2' => 86.2,
    'Q3' => 87.1,
    'Q4' => 86.5
];

// Advisory Class Performance (Static)
$advisory_performance = [
    'class_name' => 'Grade 5 - Hidalgo',
    'total_students' => 30,
    'overall_average' => 87.3,
    'top_subject' => 'Mathematics (90.5)',
    'needs_attention' => 'Filipino (82.1)',
    'attendance_rate' => 94.5,
    'honor_students' => 8
];

// Subject-wise comparison for advisory
$advisory_subjects = [
    ['subject' => 'Mathematics', 'average' => 90.5],
    ['subject' => 'Science', 'average' => 88.2],
    ['subject' => 'English', 'average' => 86.5],
    ['subject' => 'Filipino', 'average' => 82.1],
    ['subject' => 'AP', 'average' => 87.8],
    ['subject' => 'MAPEH', 'average' => 89.3],
    ['subject' => 'ESP', 'average' => 88.7]
];

$teacherName = htmlspecialchars($first_name . ' ' . $last_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .top-header {
            background: linear-gradient(135deg, #7cb342 0%, #689f38 100%);
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

        .teacher-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #7cb342, #689f38);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .teacher-avatar i {
            font-size: 40px;
            color: white;
        }

        .welcome-section h5 {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: #7cb342;
            font-weight: 600;
            font-size: 14px;
        }

        .faculty-id-section {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .faculty-id-section h6 {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .faculty-id-section .id-number {
            font-size: 24px;
            font-weight: bold;
            color: #7cb342;
            font-family: 'Courier New', monospace;
        }

        .faculty-id-section .subject {
            font-size: 14px;
            color: #666;
            margin-top: 8px;
            font-weight: 600;
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
            background: #e8f5e9;
            color: #7cb342;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #7cb342, #689f38);
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
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .stat-card.primary::before { background: linear-gradient(135deg, #2196f3, #1976d2); }
        .stat-card.success::before { background: linear-gradient(135deg, #4caf50, #388e3c); }
        .stat-card.warning::before { background: linear-gradient(135deg, #ff9800, #f57c00); }
        .stat-card.danger::before { background: linear-gradient(135deg, #f44336, #d32f2f); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 15px;
        }

        .stat-icon.primary { background: linear-gradient(135deg, #2196f3, #1976d2); }
        .stat-icon.success { background: linear-gradient(135deg, #4caf50, #388e3c); }
        .stat-icon.warning { background: linear-gradient(135deg, #ff9800, #f57c00); }
        .stat-icon.danger { background: linear-gradient(135deg, #f44336, #d32f2f); }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .chart-card h5 {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .student-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #7cb342;
            transition: all 0.3s;
            cursor: pointer;
        }

        .student-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .student-card.at-risk {
            border-left-color: #f44336;
        }

        .student-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #7cb342, #689f38);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            font-weight: bold;
        }

        .student-avatar.at-risk {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }

        .performance-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .performance-table h5 {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: #f8f9fa;
        }

        .badge-performance {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-excellent { background: #4caf50; color: white; }
        .badge-good { background: #8bc34a; color: white; }
        .badge-satisfactory { background: #ff9800; color: white; }
        .badge-needs-improvement { background: #f44336; color: white; }

        @media (max-width: 768px) {
            .sidebar {
                margin-bottom: 20px;
            }
            
            .brand-text h1 {
                font-size: 20px;
            }

            .page-title {
                font-size: 22px;
            }

            .stat-value {
                font-size: 28px;
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grade Distribution Pie Chart
        const gradeDistCtx = document.getElementById('gradeDistributionChart').getContext('2d');
        const gradeDistributionChart = new Chart(gradeDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent (90-100)', 'Very Good (85-89)', 'Good (80-84)', 'Satisfactory (75-79)', 'Needs Improvement (<75)'],
                datasets: [{
                    data: [
                        <?php echo $grade_distribution['excellent']; ?>,
                        <?php echo $grade_distribution['very_good']; ?>,
                        <?php echo $grade_distribution['good']; ?>,
                        <?php echo $grade_distribution['satisfactory']; ?>,
                        <?php echo $grade_distribution['needs_improvement']; ?>
                    ],
                    backgroundColor: [
                        '#4caf50',
                        '#8bc34a',
                        '#2196f3',
                        '#ff9800',
                        '#f44336'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' students (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Quarterly Trend Line Chart
        const quarterlyCtx = document.getElementById('quarterlyTrendChart').getContext('2d');
        const quarterlyTrendChart = new Chart(quarterlyCtx, {
            type: 'line',
            data: {
                labels: ['Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'],
                datasets: [{
                    label: 'Average Grade',
                    data: [
                        <?php echo $quarterly_trend['Q1']; ?>,
                        <?php echo $quarterly_trend['Q2']; ?>,
                        <?php echo $quarterly_trend['Q3']; ?>,
                        <?php echo $quarterly_trend['Q4']; ?>
                    ],
                    borderColor: '#7cb342',
                    backgroundColor: 'rgba(124, 179, 66, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: '#7cb342',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Average: ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 75,
                        max: 95,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        <?php if ($advisory_class): ?>
        // Advisory Subjects Bar Chart
        const advisoryCtx = document.getElementById('advisorySubjectsChart').getContext('2d');
        const advisorySubjectsChart = new Chart(advisoryCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($advisory_subjects as $subj): ?>
                        '<?php echo $subj['subject']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Average Grade',
                    data: [
                        <?php foreach ($advisory_subjects as $subj): ?>
                            <?php echo $subj['average']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4caf50',
                        '#2196f3',
                        '#ff9800',
                        '#9c27b0',
                        '#00bcd4',
                        '#ffeb3b',
                        '#8bc34a'
                    ],
                    borderRadius: 8,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Average: ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 75,
                        max: 95,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Add animation on scroll
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

        document.querySelectorAll('.stat-card, .chart-card, .performance-table, .student-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 mb-4">
                <div class="sidebar">
                    <div class="welcome-section">
                        <div class="teacher-avatar">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>

                    <!-- Class Performance Summary Table -->
                    <div class="performance-table mb-4">
                        <h5><i class="fas fa-chalkboard"></i> Class Performance Summary</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Average</th>
                                        <th>Passing Rate</th>
                                        <th>Excellent</th>
                                        <th>Satisfactory</th>
                                        <th>Needs Improvement</th>
                                        <th>Total Students</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($class_performance as $class): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo $class['average'] >= 85 ? 'badge-excellent' : ($class['average'] >= 80 ? 'badge-good' : 'badge-satisfactory'); ?>">
                                                    <?php echo number_format($class['average'], 1); ?>%
                                                </span>
                                            </td>
                                            <td><?php echo $class['passing']; ?>%</td>
                                            <td><span class="badge bg-success"><?php echo $class['excellent']; ?></span></td>
                                            <td><span class="badge bg-primary"><?php echo $class['satisfactory']; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $class['needs_improvement']; ?></span></td>
                                            <td><?php echo $class['total']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Advisory Class Performance -->
                    <?php if ($advisory_class): ?>
                        <div class="alert alert-warning mb-3">
                            <h5 class="mb-3">
                                <i class="fas fa-star"></i> Advisory Class Performance: 
                                <?php echo htmlspecialchars($advisory_performance['class_name']); ?>
                            </h5>
                        </div>

                        <div class="row mb-4">
                            <!-- Advisory Stats -->
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="stat-card primary">
                                    <div class="stat-icon primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $advisory_performance['total_students']; ?></div>
                                    <div class="stat-label">Total Students</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="stat-card success">
                                    <div class="stat-icon success">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-value"><?php echo number_format($advisory_performance['overall_average'], 1); ?>%</div>
                                    <div class="stat-label">Overall Average</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="stat-card warning">
                                    <div class="stat-icon warning">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="stat-value"><?php echo number_format($advisory_performance['attendance_rate'], 1); ?>%</div>
                                    <div class="stat-label">Attendance Rate</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="stat-card danger">
                                    <div class="stat-icon danger">
                                        <i class="fas fa-medal"></i>
                                    </div>
                                    <div class="stat-value"><?php echo $advisory_performance['honor_students']; ?></div>
                                    <div class="stat-label">Honor Students</div>
                                </div>
                            </div>
                        </div>

                        <!-- Advisory Subject Performance Chart -->
                        <div class="chart-card mb-4">
                            <h5><i class="fas fa-chart-bar"></i> Advisory Class - Subject Performance</h5>
                            <div class="chart-container">
                                <canvas id="advisorySubjectsChart"></canvas>
                            </div>
                        </div>

                        <!-- Advisory Insights -->
                        <div class="row mb-4">
                            <div class="col-lg-6 mb-3">
                                <div class="alert alert-success">
                                    <h6 class="alert-heading"><i class="fas fa-arrow-up"></i> Top Performing Subject</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($advisory_performance['top_subject']); ?></p>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-3">
                                <div class="alert alert-warning">
                                    <h6 class="alert-heading"><i class="fas fa-eye"></i> Needs Attention</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($advisory_performance['needs_attention']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
                        <h5>WELCOME!</h5>
                        <p style="font-size: 16px; color: #2c3e50; font-weight: bold;">
                            <?php echo strtoupper($teacherName); ?>
                        </p>
                        <p><i class="fas fa-check-circle"></i> Teacher Portal</p>
                    </div>

                    <div class="faculty-id-section">
                        <h6>Faculty ID</h6>
                        <div class="id-number"><?php echo htmlspecialchars($teacher_code); ?></div>
                        <div class="subject">
                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($subject_list); ?>
                        </div>
                    </div>

                    <nav>
                        <a href="teacher_dashboard.php" class="menu-item">
                            <i class="fas fa-chart-line"></i>
                            <span>DASHBOARD</span>
                        </a>
                        <a href="teacher_classes.php" class="menu-item">
                            <i class="fas fa-users"></i>
                            <span>MY CLASSES</span>
                        </a>
                        <a href="teacher_performance.php" class="menu-item active">
                            <i class="fas fa-chart-bar"></i>
                            <span>ANALYTICS</span>
                        </a>
                        <a href="grade_upload.php" class="menu-item">
                            <i class="fas fa-file-upload"></i>
                            <span>GRADE UPLOAD</span>
                        </a>
                        <a href="attendance.php" class="menu-item">
                            <i class="fas fa-clipboard-check"></i>
                            <span>ATTENDANCE</span>
                        </a>
                        <a href="teacher_reports.php" class="menu-item">
                            <i class="fas fa-file-alt"></i>
                            <span>ANALYTICS</span>
                        </a>
                        <a href="faculty_profile.php" class="menu-item">
                            <i class="fas fa-id-card"></i>
                            <span>MY PROFILE</span>
                        </a>
                        <a href="teacher_announcements.php" class="menu-item">
                            <i class="fas fa-bullhorn"></i>
                            <span>ANNOUNCEMENTS</span>
                        </a>
                    </nav>
                    <form action="logout.php" method="POST" style="margin-top: auto;">
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
                        <i class="fas fa-chart-bar"></i> Performance Analytics
                    </h2>

                    <!-- Overall Statistics -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card primary">
                                <div class="stat-icon primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-value"><?php echo $overall_stats['total_students']; ?></div>
                                <div class="stat-label">Total Students</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card success">
                                <div class="stat-icon success">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-value"><?php echo $overall_stats['average_grade']; ?>%</div>
                                <div class="stat-label">Average Grade</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card warning">
                                <div class="stat-icon warning">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="stat-value"><?php echo $overall_stats['passing_rate']; ?>%</div>
                                <div class="stat-label">Passing Rate</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stat-card danger">
                                <div class="stat-icon danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="stat-value"><?php echo $overall_stats['at_risk_count']; ?></div>
                                <div class="stat-label">At-Risk Students</div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <!-- Grade Distribution Chart -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-card">
                                <h5><i class="fas fa-chart-pie"></i> Grade Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="gradeDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Quarterly Trend Chart -->
                        <div class="col-lg-6 mb-4">
                            <div class="chart-card">
                                <h5><i class="fas fa-chart-line"></i> Quarterly Performance Trend</h5>
                                <div class="chart-container">
                                    <canvas id="quarterlyTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Performers and At-Risk Students -->
                    <div class="row mb-4">
                        <!-- Top Performers -->
                        <div class="col-lg-6 mb-4">
                            <div class="performance-table">
                                <h5><i class="fas fa-trophy" style="color: #ffd700;"></i> Top Performers</h5>
                                <div class="mb-3">
                                    <?php foreach ($top_performers as $student): 
                                        $initials = strtoupper(substr(explode(' ', $student['name'])[0], 0, 1) . substr(explode(' ', $student['name'])[count(explode(' ', $student['name']))-1], 0, 1));
                                    ?>
                                        <div class="student-card">
                                            <div class="d-flex align-items-center">
                                                <div class="student-avatar">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($student['name']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($student['subject']); ?> • 
                                                        <?php echo htmlspecialchars($student['section']); ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge badge-excellent" style="font-size: 16px;">
                                                        <?php echo number_format($student['grade'], 1); ?>%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- At-Risk Students -->
                        <div class="col-lg-6 mb-4">
                            <div class="performance-table">
                                <h5><i class="fas fa-exclamation-circle" style="color: #f44336;"></i> At-Risk Students</h5>
                                <div class="mb-3">
                                    <?php foreach ($at_risk_students as $student): 
                                        $initials = strtoupper(substr(explode(' ', $student['name'])[0], 0, 1) . substr(explode(' ', $student['name'])[count(explode(' ', $student['name']))-1], 0, 1));
                                    ?>
                                        <div class="student-card at-risk">
                                            <div class="d-flex align-items-center">
                                                <div class="student-avatar at-risk">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($student['name']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($student['subject']); ?> • 
                                                        <?php echo htmlspecialchars($student['section']); ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-danger">
                                                        <i class="fas fa-calendar-times"></i> <?php echo $student['absences']; ?> absences
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge badge-needs-improvement" style="font-size: 16px;">
                                                        <?php echo number_format($student['grade'], 1); ?>%
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>