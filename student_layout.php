<?php
// student_layout.php - Common layout wrapper with fixed sidebar
function renderLayout($page_title, $content, $active_page, $student_info, $initials, $profile_picture_url = null)
{
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $page_title; ?> - Creative Dreams School</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <link rel="stylesheet" href="student_stylesss.css">
        <style>
            /* Fixed Header */
            .top-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
            }

            /* Fixed Sidebar */
            .sidebar {
                position: fixed;
                top: 80px;
                /* Adjust based on your header height */
                left: 0;
                height: calc(100vh - 80px);
                overflow-y: auto;
                overflow-x: hidden;
            }

            /* Adjust main content to account for fixed sidebar */
            .content-wrapper {
                margin-top: 80px;
                /* Same as header height */
            }

            .main-content {
                padding: 20px;
            }

            /* Hide profile picture and avatar in sidebar */
            .sidebar .profile-avatar-image,
            .sidebar .profile-avatar {
                display: none;
            }

            /* Sidebar welcome section - simplified */
            .welcome-section {
                text-align: center;
                padding: 20px;
            }

            .welcome-section h5 {
                margin-bottom: 0;
            }

            /* Dashboard profile image - keep original size */
            .dashboard-profile-image {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid #4c8c4a;
                box-shadow: 0 4px 12px rgba(88, 129, 87, 0.2);
            }

            .dashboard-profile-avatar {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #5a9c4e, #4a8240);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
                color: white;
                font-weight: bold;
                border: 3px solid #4c8c4a;
                box-shadow: 0 4px 12px rgba(88, 129, 87, 0.2);
            }

            /* Header Logout Button */
            .header-logout-btn {
                background: linear-gradient(135deg, #f44336, #e53935) !important;
                border: none !important;
                color: white !important;
                padding: 10px 24px;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
                font-size: 14px;
                box-shadow: 0 3px 10px rgba(244, 67, 54, 0.4);
            }

            .header-logout-btn:hover {
                background: linear-gradient(135deg, #e53935, #d32f2f) !important;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(244, 67, 54, 0.5);
            }

            .header-logout-btn i {
                font-size: 18px;
            }

            /* Logout Confirmation Modal */
            .logout-modal-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                z-index: 10000;
                justify-content: center;
                align-items: center;
            }

            .logout-modal-overlay.show {
                display: flex;
            }

            .logout-modal {
                background: white;
                border-radius: 15px;
                padding: 30px;
                max-width: 400px;
                width: 90%;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                animation: modalSlideIn 0.3s ease;
            }

            @keyframes modalSlideIn {
                from {
                    transform: translateY(-50px);
                    opacity: 0;
                }

                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            .logout-modal-header {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 20px;
            }

            .logout-modal-icon {
                width: 50px;
                height: 50px;
                background: linear-gradient(135deg, #f44336, #e53935);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 24px;
            }

            .logout-modal-title {
                font-size: 22px;
                font-weight: bold;
                color: #333;
                margin: 0;
            }

            .logout-modal-body {
                color: #666;
                font-size: 15px;
                margin-bottom: 25px;
                line-height: 1.6;
            }

            .logout-modal-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }

            .logout-modal-btn {
                padding: 10px 24px;
                border-radius: 8px;
                border: none;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                font-size: 14px;
            }

            .logout-cancel-btn {
                background: #f5f5f5;
                color: #666;
            }

            .logout-cancel-btn:hover {
                background: #e0e0e0;
                color: #333;
            }

            .logout-confirm-btn {
                background: linear-gradient(135deg, #f44336, #e53935);
                color: white;
                box-shadow: 0 3px 10px rgba(244, 67, 54, 0.3);
            }

            .logout-confirm-btn:hover {
                background: linear-gradient(135deg, #e53935, #d32f2f);
                box-shadow: 0 5px 15px rgba(244, 67, 54, 0.4);
                transform: translateY(-2px);
            }

            /* Smooth scroll */
            html {
                scroll-behavior: smooth;
            }

            /* Sidebar scrollbar styling */
            .sidebar::-webkit-scrollbar {
                width: 6px;
            }

            .sidebar::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }

            .sidebar::-webkit-scrollbar-thumb {
                background: #5a9c4e;
                border-radius: 10px;
            }

            .sidebar::-webkit-scrollbar-thumb:hover {
                background: #4a8240;
            }
        </style>
    </head>

    <body>
        <!-- Logout Confirmation Modal -->
        <div class="logout-modal-overlay" id="logoutModal">
            <div class="logout-modal">
                <div class="logout-modal-header">
                    <div class="logout-modal-icon">
                        <i class="bi bi-box-arrow-right"></i>
                    </div>
                    <h3 class="logout-modal-title">Confirm Logout</h3>
                </div>
                <div class="logout-modal-body">
                    Are you sure you want to logout? You will need to sign in again to access your account.
                </div>
                <div class="logout-modal-actions">
                    <button type="button" class="logout-modal-btn logout-cancel-btn" onclick="cancelLogout()">
                        Cancel
                    </button>
                    <button type="button" class="logout-modal-btn logout-confirm-btn" onclick="confirmLogout()">
                        Yes, Logout
                    </button>
                </div>
            </div>
        </div>

        <!-- Fixed Header -->
        <div class="top-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="logo-section">
                    <div class="logo">
                        <img src="../images/cdslogo.png" alt="School Logo">
                    </div>
                    <div class="brand-text">
                        <h1>Creative Dreams</h1>
                        <p>Inspire. Learn. Achieve.</p>
                    </div>
                </div>
                <div class="header-actions">
                    <form action="../logout.php" method="POST" id="logoutForm" style="margin: 0;">
                        <button type="button" class="header-logout-btn" onclick="showLogoutModal()">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>LOGOUT</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="container-fluid content-wrapper">
            <div class="row">
                <!-- Fixed Sidebar -->
                <div class="col-lg-2 col-md-3">
                    <div class="sidebar">
                        <div class="welcome-section">
                            <h5><i class="bi bi-mortarboard-fill"></i> STUDENT PORTAL</h5>
                        </div>

                        <!-- Student ID Section -->
                        <div class="faculty-id-section">
                            <h6>Student Code (LRN)</h6>
                            <div class="id-number"><?php echo htmlspecialchars($student_info['lrn']); ?></div>
                            <div class="subject">
                                <i class="bi bi-book"></i> <?php echo htmlspecialchars($student_info['grade_level']); ?> - <?php echo htmlspecialchars($student_info['section']); ?>
                            </div>
                        </div>

                        <nav>
                            <a href="student_dashboard.php" class="nav-link <?php echo $active_page === 'dashboard' ? 'active' : ''; ?>">
                                <i class="bi bi-house-door-fill"></i>
                                <span>DASHBOARD</span>
                            </a>

                            <!-- Performance Dropdown -->
                            <div class="nav-dropdown">
                                <a href="#" class="nav-link <?php echo in_array($active_page, ['performance_analytics', 'grades', 'attendance']) ? 'active' : ''; ?>"
                                    data-bs-toggle="collapse" data-bs-target="#performanceMenu"
                                    aria-expanded="<?php echo in_array($active_page, ['performance_analytics', 'grades', 'attendance']) ? 'true' : 'false'; ?>">
                                    <i class="bi bi-graph-up-arrow"></i>
                                    <span>PERFORMANCE</span>
                                    <i class="bi bi-chevron-down ms-auto dropdown-arrow"></i>
                                </a>
                                <div class="collapse <?php echo in_array($active_page, ['performance_analytics', 'grades', 'attendance']) ? 'show' : ''; ?>" id="performanceMenu">
                                    <a href="student_performance_analytics.php" class="nav-link sub-link <?php echo $active_page === 'performance_analytics' ? 'active' : ''; ?>">
                                        <i class="bi bi-bar-chart-line-fill"></i>
                                        <span>Analytics</span>
                                    </a>
                                    <a href="student_grades.php" class="nav-link sub-link <?php echo $active_page === 'grades' ? 'active' : ''; ?>">
                                        <i class="bi bi-journal-text"></i>
                                        <span>Grades</span>
                                    </a>
                                    <a href="student_attendance.php" class="nav-link sub-link <?php echo $active_page === 'attendance' ? 'active' : ''; ?>">
                                        <i class="bi bi-calendar-check"></i>
                                        <span>Attendance</span>
                                    </a>
                                </div>
                            </div>

                            <a href="student_schedule.php" class="nav-link <?php echo $active_page === 'schedule' ? 'active' : ''; ?>">
                                <i class="bi bi-calendar-week"></i>
                                <span>SCHEDULE</span>
                            </a>
                            <a href="student_documents.php" class="nav-link <?php echo $active_page === 'documents' ? 'active' : ''; ?>">
                                <i class="bi bi-folder2-open"></i>
                                <span>DOCUMENTS</span>
                            </a>
                            <a href="student_appointments.php" class="nav-link <?php echo $active_page === 'appointments' ? 'active' : ''; ?>">
                                <i class="bi bi-calendar-check"></i>
                                <span>APPOINTMENTS</span>
                            </a>
                            <a href="student_enrollment.php" class="nav-link <?php echo $active_page === 'enrollment' ? 'active' : ''; ?>">
                                <i class="bi bi-clipboard-check"></i>
                                <span>ENROLLMENT</span>
                            </a>
                            <a href="student_payment.php" class="nav-link <?php echo $active_page === 'payment' ? 'active' : ''; ?>">
                                <i class="bi bi-credit-card"></i>
                                <span>PAYMENT</span>
                            </a>
                            <a href="student_announcements.php" class="nav-link <?php echo $active_page === 'announcements' ? 'active' : ''; ?>">
                                <i class="bi bi-megaphone"></i>
                                <span>ANNOUNCEMENTS</span>
                            </a>
                            <a href="student_profile.php" class="nav-link <?php echo $active_page === 'profile' ? 'active' : ''; ?>">
                                <i class="bi bi-person-circle"></i>
                                <span>PROFILE</span>
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-10 col-md-9">
                    <div class="main-content">
                        <?php echo $content; ?>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Logout Modal Functions
            function showLogoutModal() {
                document.getElementById('logoutModal').classList.add('show');
            }

            function cancelLogout() {
                document.getElementById('logoutModal').classList.remove('show');
            }

            function confirmLogout() {
                document.getElementById('logoutForm').submit();
            }

            // Close modal when clicking outside
            document.addEventListener('click', function(e) {
                const modal = document.getElementById('logoutModal');
                if (e.target === modal) {
                    cancelLogout();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    cancelLogout();
                }
            });

            // Smooth scroll to top when page loads
            window.addEventListener('load', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                // Ensure active nav link is properly styled
                const activeLinks = document.querySelectorAll('.nav-link.active, .sub-link.active');
                activeLinks.forEach(link => {
                    // Force reflow to ensure styles are applied
                    link.style.display = 'none';
                    link.offsetHeight; // Trigger reflow
                    link.style.display = 'flex';
                });
            });
        </script>
    </body>

    </html>
<?php
}
?>