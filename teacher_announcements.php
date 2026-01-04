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
} catch (PDOException $e) {
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
    $teacher_id = $teacher['teacher_id'];

    // Get subjects taught
    $stmt = $pdo->prepare("
    SELECT DISTINCT sub.subject_name 
    FROM section_schedules ss
    INNER JOIN grade_schedule_template gst ON ss.template_id = gst.template_id
    INNER JOIN subjects sub ON gst.subject_code = sub.subject_code
    WHERE ss.teacher_code = ? AND ss.is_active = 1
");
    $stmt->execute([$teacher_code]);
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $subject_list = !empty($subjects) ? implode(', ', $subjects) : 'No subjects assigned';
} catch (PDOException $e) {
    die("Error fetching teacher data: " . $e->getMessage());
}

// Fetch announcements
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.announcement_id,
            a.title,
            a.content,
            a.category,
            a.priority,
            a.target_audience,
            DATE_FORMAT(a.date_posted, '%M %d, %Y') as date,
            DATE_FORMAT(a.date_posted, '%h:%i %p') as time,
            CONCAT(t.first_name, ' ', t.last_name) as posted_by_name
        FROM announcements a
        LEFT JOIN users u ON a.posted_by = u.user_id
        LEFT JOIN teachers t ON u.user_id = t.user_id
        WHERE a.is_active = 1
        AND (a.target_audience = 'all' OR a.target_audience = 'teachers')
        ORDER BY a.date_posted DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching announcements: " . $e->getMessage());
}

$teacherName = htmlspecialchars($first_name . ' ' . $last_name);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #5a9c4e;
            --light-green: #6db560;
            --dark-green: #4a8240;
            --text-dark: #2d5a24;
            --sage-green: #52a347;
            --accent-green: #68b85d;
            --pale-green: #e0f7fa;
            --light-sage: #7ec274;
            --forest-green: #3d6e35;
        }

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
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
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
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        /* Sidebar */
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

        .teacher-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
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
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--sage-green);
            font-weight: 600;
            font-size: 14px;
        }

        .faculty-id-section {
            background: var(--pale-green);
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
            color: var(--sage-green);
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
            color: var(--text-dark);
            font-weight: 500;
        }

        .menu-item:hover {
            background: var(--pale-green);
            color: var(--sage-green);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, var(--sage-green), var(--forest-green));
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

        /* Main Content */
        .main-content {
            padding: 20px;
        }

        .page-title {
            color: var(--text-dark);
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }

        .announcement-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border-left: 5px solid var(--primary-green);
            transition: all 0.3s;
        }

        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .announcement-card.priority-high {
            border-left-color: #f44336;
        }

        .announcement-card.priority-medium {
            border-left-color: #ff9800;
        }

        .announcement-card.priority-low {
            border-left-color: var(--primary-green);
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid var(--primary-green);
            background: white;
            color: var(--primary-green);
            border-radius: 25px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary-green);
            color: white;
        }

        .no-announcements {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .no-announcements i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 20px;
        }

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
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 mb-4">
                <div class="sidebar">
                    <div class="welcome-section">
                        <div class="teacher-avatar">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h5>WELCOME!</h5>
                        <p style="font-size: 16px; color: #2c3e50; font-weight: bold;">
                            <?php echo strtoupper($teacherName); ?>
                        </p>
                        <p><i class="fas fa-check-circle"></i> Teacher Portal</p>
                    </div>

                    <!-- Faculty ID Section -->
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
                        <a href="teacher_announcements.php" class="menu-item active">
                            <i class="fas fa-bullhorn"></i>
                            <span>ANNOUNCEMENTS</span>
                        </a>
                    </nav>
                    <form action="../logout.php" method="POST" style="margin-top: auto;">
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
                        <i class="fas fa-bullhorn"></i> School Announcements
                    </h2>

                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-2" style="color: #666; font-weight: 600;">
                                    <i class="fas fa-filter"></i> Filter by Category:
                                </h6>
                                <button class="filter-btn active" data-filter="all">All</button>
                                <button class="filter-btn" data-filter="Academic">Academic</button>
                                <button class="filter-btn" data-filter="Event">Event</button>
                                <button class="filter-btn" data-filter="Information">Information</button>
                                <button class="filter-btn" data-filter="Urgent">Urgent</button>
                            </div>
                            <div class="mt-3 mt-md-0">
                                <span class="badge bg-success" style="font-size: 14px; padding: 10px 15px;">
                                    <i class="fas fa-list"></i> <?php echo count($announcements); ?> Announcements
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Announcements List -->
                    <?php if (!empty($announcements)): ?>
                        <div id="announcements-container">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-card priority-<?php echo $announcement['priority']; ?>"
                                    data-category="<?php echo $announcement['category']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h4 class="fw-bold mb-2" style="color: #2c3e50;">
                                                <?php echo htmlspecialchars($announcement['title']); ?>
                                            </h4>
                                            <div class="d-flex flex-wrap gap-2 mb-2">
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-tag"></i> <?php echo $announcement['category']; ?>
                                                </span>
                                                <?php if ($announcement['priority'] == 'high'): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-exclamation-circle"></i> High Priority
                                                    </span>
                                                <?php elseif ($announcement['priority'] == 'medium'): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-info-circle"></i> Medium Priority
                                                    </span>
                                                <?php endif; ?>
                                                <span class="badge" style="background: #7cb342;">
                                                    <i class="fas fa-users"></i>
                                                    <?php echo ucfirst($announcement['target_audience']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-end ms-3" style="min-width: 150px;">
                                            <small class="text-muted d-block">
                                                <i class="fas fa-calendar-alt"></i> <?php echo $announcement['date']; ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-clock"></i> <?php echo $announcement['time']; ?>
                                            </small>
                                            <?php if ($announcement['posted_by_name']): ?>
                                                <small class="text-muted d-block mt-1">
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['posted_by_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <p class="mb-0" style="color: #555; line-height: 1.7; font-size: 15px;">
                                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Load More Button -->
                        <div class="text-center mt-4">
                            <button class="btn btn-success btn-lg" style="border-radius: 25px; padding: 12px 40px;">
                                <i class="fas fa-sync-alt"></i> Load More Announcements
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="no-announcements">
                            <i class="fas fa-bullhorn"></i>
                            <h4 style="color: #666;">No Announcements Available</h4>
                            <p class="text-muted">There are currently no announcements to display.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        const filterButtons = document.querySelectorAll('.filter-btn');
        const announcementCards = document.querySelectorAll('.announcement-card');

        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');

                const filterValue = this.getAttribute('data-filter');

                announcementCards.forEach(card => {
                    if (filterValue === 'all') {
                        card.style.display = 'block';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, 10);
                    } else {
                        const category = card.getAttribute('data-category');
                        if (category === filterValue) {
                            card.style.display = 'block';
                            setTimeout(() => {
                                card.style.opacity = '1';
                                card.style.transform = 'translateY(0)';
                            }, 10);
                        } else {
                            card.style.opacity = '0';
                            card.style.transform = 'translateY(20px)';
                            setTimeout(() => {
                                card.style.display = 'none';
                            }, 300);
                        }
                    }
                });
            });
        });

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

        document.querySelectorAll('.announcement-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>

</html>