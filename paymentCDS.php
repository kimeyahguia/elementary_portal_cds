<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Creative Dreams School - Student Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary-green: #2d5f3f;
    --light-green: #d4f1d4;
    --white: #ffffff;
    --text-dark: #2c3e50;
    --text-light: #6c757d;
    --accent-blue: #49a86d;
    --border-color: #e0e0e0;
    --shadow-light: rgba(0, 0, 0, 0.08);
    --secondary-green: #81C784;
    --accent-yellow: #FBC02D;
    --light-gray: #F5F5F5;
    --card-bg: #ffffff;
    --red: #E53935;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f5f3e8;
    color: var(--text-dark);
}

/* HEADER */
.header {
    background: linear-gradient(135deg, #63a571, #3b8c5a);
    padding: 20px 40px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border-bottom-left-radius: 20px;
    border-bottom-right-radius: 20px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo {
    width: 50px;
    height: 50px;
    background: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px var(--shadow-light);
}

.school-name h1 {
    font-size: 24px;
    font-weight: 700;
    color: var(--white);
}

.tagline {
    font-size: 13px;
    color: #d4f1d4;
    font-style: italic;
}

.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-box {
    position: relative;
}

.search-box input {
    padding: 8px 35px;
    border: none;
    border-radius: 25px;
    width: 250px;
    background: rgba(255,255,255,0.9);
    transition: all 0.3s;
}

.search-box input:focus {
    outline: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
}

.header-icon {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.header-icon:hover {
    background: rgba(255,255,255,0.5);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* MAIN CONTAINER */
.main-container {
    display: flex;
    min-height: calc(100vh - 100px);
    margin-top: 20px;
    gap: 20px;
    padding: 0 20px;
}

/* SIDEBAR */
.sidebar {
    width: 220px;
    background: #f4fff7;
    padding: 20px;
    border-radius: 20px;
    box-shadow: 2px 0 20px var(--shadow-light);
    flex-shrink: 0;
    margin-bottom: 30px;
}

.sidebar a.menu-item {
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar a.menu-item:hover {
    text-decoration: none;
}

.user-profile {
    text-align: center;
    padding: 20px 10px;
    border-bottom: 2px solid #e0dfd0;
    margin-bottom: 25px;
}

.profile-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 10px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px var(--shadow-light);
}

.user-profile h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-green);
    text-transform: uppercase;
    margin: 5px 0;
}

.logged-status {
    font-size: 12px;
    color: #666;
}

.menu-item {
    padding: 12px 15px;
    margin: 8px 0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.menu-item:hover, .menu-item.active {
    background: var(--accent-blue);
    color: white;
}

.menu-item i {
    font-size: 18px;
    width: 20px;
    text-align: center;
}

/* CONTENT AREA */
.content-area {
    flex: 1;
    padding: 30px;
}

/* DASHBOARD VIEW */
.dashboard-view {
    display: block;
}

.dashboard-view.hidden {
    display: none;
}

/* WELCOME BANNER */
.welcome-banner {
    background: linear-gradient(135deg, #90d593, #2e7d32);
    color: white;
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: 0 8px 25px rgba(74,158,255,0.25);
}

.welcome-banner h2 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}

.welcome-banner p {
    font-size: 16px;
    opacity: 0.95;
}

/* DASHBOARD GRID */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.dashboard-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 8px 24px var(--shadow-light);
    transition: transform 0.3s, box-shadow 0.3s;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
}

.card-header {
    font-size: 13px;
    font-weight: 700;
    color: #666;
    margin-bottom: 20px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header i {
    font-size: 18px;
    color: var(--accent-blue);
}

/* PROGRAMS & INFO ITEMS */
.program-item, .info-item {
    background: #fdfdfd;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
}

.program-item:hover, .info-item:hover {
    background: #f0f4ff;
    transform: translateX(6px);
    box-shadow: 0 4px 12px var(--shadow-light);
}

.program-icon {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-size: 24px;
    border: 2px solid #e9ecef;
}

.program-name {
    font-weight: 700;
    font-size: 13px;
}

/* PERFORMANCE BADGES */
.performance-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}

.performance-badge {
    border-radius: 30px;
    padding: 10px 22px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
}

.performance-badge:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px var(--shadow-light);
}

.badge-high { background: #28a745; color: white; }
.badge-average { background: #ffc107; color: #333; }
.badge-support { background: #dc3545; color: white; }

/* STATS ROW */
.stats-row {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

.stat-item { text-align: center; }
.stat-number { font-size: 32px; font-weight: 700; color: var(--accent-blue); }
.stat-label { font-size: 12px; color: #666; text-transform: uppercase; margin-top: 5px; }

/* FEES VIEW */
.fees-view {
    display: none;
}

.fees-view.active {
    display: block;
}

/* Summary Cards */
.summary-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: var(--card-bg);
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.summary-card h6 {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 0.8rem;
    color: var(--text-light);
}

.summary-card .amount {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--text-dark);
}

.summary-card .icon {
    font-size: 2rem;
    color: var(--secondary-green);
}

/* Toolbar */
.action-toolbar {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}

.toolbar-search {
    position: relative;
    flex: 1;
    max-width: 300px;
}

.toolbar-search input {
    width: 100%;
    border-radius: 25px;
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    border: 1px solid #ccc;
}

.toolbar-search .fas.fa-search {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
}

.btn-filter {
    background: var(--card-bg);
    border: 1px solid #ccc;
    border-radius: 10px;
    padding: 0.5rem 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s;
}

.btn-filter:hover {
    background: var(--secondary-green);
    color: #fff;
    border-color: var(--secondary-green);
}

.btn-export, .btn-record {
    border-radius: 10px;
    padding: 0.5rem 1.2rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-export {
    background: var(--accent-blue);
    color: #fff;
}

.btn-export:hover {
    background: var(--secondary-green);
}

.btn-record {
    background: #1E88E5;
    color: #fff;
}

.btn-record:hover {
    background: #1565c0;
}

/* Table */
.payment-table-container {
    background: var(--card-bg);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.table-header {
    padding: 1rem 1.5rem;
    font-weight: 700;
    text-transform: uppercase;
    background: var(--light-gray);
    font-size: 0.9rem;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    text-align: left;
    font-size: 0.85rem;
    color: var(--text-dark);
    padding: 1rem 1.5rem;
    border-bottom: 2px solid var(--light-gray);
}

tbody td {
    padding: 1rem 1.5rem;
    color: var(--text-dark);
}

tbody tr:hover {
    background: var(--light-gray);
}

.status-paid {
    background: #4CAF50;
    color: #fff;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending {
    background: var(--accent-yellow);
    color: #333;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-overdue {
    background: var(--red);
    color: #fff;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 0.3rem;
}

.btn-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.btn-view { background: var(--light-gray); color: var(--text-dark); }
.btn-view:hover { background: var(--secondary-green); color: #fff; }

.btn-edit { background: var(--secondary-green); color: #fff; }
.btn-edit:hover { background: var(--accent-blue); }

/* RESPONSIVE */
@media (max-width: 992px) {
    .dashboard-grid { grid-template-columns: 1fr; }
    .performance-card { grid-column: span 1; }
}

@media (max-width: 768px) {
    .sidebar { width: 100%; border-radius: 20px; }
    .main-container { flex-direction: column; }
    .header-content { flex-direction: column; gap: 15px; }
    .search-box input { width: 200px; }
    .summary-row { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>
<!-- Header -->
<div class="header">
    <div class="header-content">
        <div class="logo-section">
            <div class="logo">
                <i class="fas fa-graduation-cap" style="color: var(--accent-blue); font-size: 24px;"></i>
            </div>
            <div class="school-name">
                <h1>Creative Dreams School, Inc.</h1>
                <div class="tagline">Inspire. Learn. Achieve.</div>
            </div>
        </div>
        <div class="header-actions">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search..." class="form-control">
            </div>
            <div class="header-icon" onclick="showNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">3</span>
            </div>
            <div class="header-icon" onclick="showProfile()">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </div>
</div>

<div class="main-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="user-profile">
            <div class="profile-icon">
                <i class="fas fa-user-circle" style="font-size: 40px; color: var(--accent-blue);"></i>
            </div>
            <h3>WELCOME STUDENT!</h3>
            <div class="logged-status">Logged in <i class="fas fa-check-circle" style="color: #28a745;"></i></div>
        </div>

        <!-- Navigation Links -->
        <a href="studentParent_dashboard.html" class="menu-item" id="nav-dashboard" onclick="showDashboard(event)">
            <i class="fas fa-tachometer-alt"></i> CDS Dashboard
        </a>
        <a href="studGradesManage.html" class="menu-item" id="nav-grades" onclick="navigateToGrades(event)">
            <i class="fas fa-user-graduate"></i> Grades Management
        </a>
        <a href="paymentCDS.php" class="menu-item active" id="nav-fees" onclick="showFees(event)">
            <i class="fas fa-dollar-sign"></i> Fees and Payment Management
        </a>
        <a href="studSettings.html" class="menu-item" id="nav-settings" onclick="navigateToSettings(event)">
            <i class="fas fa-cog"></i> Settings
        </a>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Dashboard View -->
        <div class="dashboard-view hidden" id="dashboardView">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <h2><i class="fas fa-home"></i> Welcome to Your Dashboard!</h2>
                <p>Here's an overview of your school management system</p>
            </div>

            <div class="dashboard-grid">
                <!-- School Information Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-book"></i> CREATIVE DREAMS SCHOOL, INC.
                    </div>
                    <div class="accordion" id="schoolInfoAccordion">
                        <!-- Mission -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingMission">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMission" aria-expanded="false">
                                    <i class="fas fa-bullseye me-2"></i> MISSION
                                </button>
                            </h2>
                            <div id="collapseMission" class="accordion-collapse collapse" data-bs-parent="#schoolInfoAccordion">
                                <div class="accordion-body">To provide quality education that nurtures creativity and dreams.</div>
                            </div>
                        </div>
                        <!-- Vision -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingVision">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVision" aria-expanded="false">
                                    <i class="fas fa-eye me-2"></i> VISION
                                </button>
                            </h2>
                            <div id="collapseVision" class="accordion-collapse collapse" data-bs-parent="#schoolInfoAccordion">
                                <div class="accordion-body">To be a leading institution in holistic child development.</div>
                            </div>
                        </div>
                    </div>
                    <div class="stats-row mt-3">
                        <div class="stat-item"><div class="stat-number">450</div><div class="stat-label">Students</div></div>
                        <div class="stat-item"><div class="stat-number">98%</div><div class="stat-label">Success Rate</div></div>
                    </div>
                </div>

                <!-- Programs Offered Card -->
                <div class="dashboard-card">
                    <div class="card-header"><i class="fas fa-graduation-cap"></i> PROGRAMS OFFERED</div>
                    <div class="program-item" onclick="showProgram('kindergarten')">
                        <div class="program-icon"><i class="fas fa-child"></i></div>
                        <span class="program-name">KINDERGARTEN PROGRAM</span>
                    </div>
                    <div class="program-item" onclick="showProgram('elementary')">
                        <div class="program-icon"><i class="fas fa-book-reader"></i></div>
                        <span class="program-name">ELEMENTARY PROGRAM</span>
                    </div>
                </div>

                <!-- Quick Access -->
                <div class="dashboard-card">
                    <div class="card-header"><i class="fas fa-chart-bar"></i> QUICK ACCESS</div>
                    <div class="info-item" onclick="showSection('students')"><i class="fas fa-users"></i> TOTAL STUDENTS</div>
                    <div class="info-item" onclick="showSection('programs')"><i class="fas fa-book"></i> ACADEMIC PROGRAMS</div>
                    <div class="info-item" onclick="showSection('success')"><i class="fas fa-star"></i> SUCCESS RATE</div>
                </div>

                <!-- Student Performance -->
                <div class="dashboard-card performance-card">
                    <div class="card-header"><i class="fas fa-chart-line"></i> STUDENT PERFORMANCE PREDICTION</div>
                    <div class="performance-badges">
                        <span class="performance-badge badge-high" onclick="showPerformance('high')">HIGH ACHIEVERS</span>
                        <span class="performance-badge badge-average" onclick="showPerformance('average')">AVERAGE PERFORMERS</span>
                        <span class="performance-badge badge-support" onclick="showPerformance('support')">NEED SUPPORT</span>
                    </div>
                    <div class="stats-row">
                        <div class="stat-item"><div class="stat-number">125</div><div class="stat-label">High Achievers</div></div>
                        <div class="stat-item"><div class="stat-number">280</div><div class="stat-label">Average</div></div>
                        <div class="stat-item"><div class="stat-number">45</div><div class="stat-label">Need Support</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fees View -->
        <div class="fees-view active" id="feesView">
            <!-- Summary Cards -->
            <div class="summary-row">
                <div class="summary-card">   
                    <h6>Total Collected</h6>
                    <div class="summary-card-content d-flex justify-content-between align-items-center">
                        <div class="amount">₱0.00</div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <h6>Pending Payments</h6>
                    <div class="summary-card-content d-flex justify-content-between align-items-center">
                        <div class="amount">₱0.00</div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <h6>Overdue Payments</h6>
                    <div class="summary-card-content d-flex justify-content-between align-items-center">
                        <div class="amount">₱0.00</div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <h6>Monthly Target</h6>
                    <div class="summary-card-content d-flex justify-content-between align-items-center">
                        <div class="amount">₱0.00</div>
                        <div class="icon"><i class="fas fa-chart-line"></i></div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="action-toolbar">
                <div class="d-flex gap-2 flex-wrap flex-grow-1">
                    <div class="toolbar-search">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search by guest name or booking ID...">
                    </div>
                    <button class="btn-filter"><i class="fas fa-sliders-h"></i> Add Filter</button>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn-export" onclick="exportData()">EXPORT</button>
                    <button class="btn-record" onclick="recordPayment()">RECORD PAYMENT</button>
                </div>
            </div>

            <!-- Payment Table -->
            <div class="payment-table-container">
                <div class="table-header">Recent Payments</div>
                <table>
                    <thead>
                        <tr>
                            <th>GUEST NAME</th>
                            <th>AMOUNT</th>
                            <th>PAYMENT TYPE</th>
                            <th>STATUS</th>
                            <th>DATE</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Juan Dela Cruz</td>
                            <td>₱5,000.00</td>
                            <td>Room Booking</td>
                            <td><span class="status-paid">PAID</span></td>
                            <td>Nov 05, 2025</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-view"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon btn-edit"><i class="fas fa-edit"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Maria Santos</td>
                            <td>₱3,500.00</td>
                            <td>Advance Payment</td>
                            <td><span class="status-pending">PENDING</span></td>
                            <td>Nov 06, 2025</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-view"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon btn-edit"><i class="fas fa-edit"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Pedro Reyes</td>
                            <td>₱8,000.00</td>
                            <td>Full Payment</td>
                            <td><span class="status-overdue">OVERDUE</span></td>
                            <td>Nov 01, 2025</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-view"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon btn-edit"><i class="fas fa-edit"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Navigation Functions
function showDashboard(event) {
    event.preventDefault();
    document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
    document.getElementById('nav-dashboard').classList.add('active');
    document.getElementById('dashboardView').style.display = 'block';
    document.getElementById('feesView').classList.remove('active');
}

function showFees(event) {
    event.preventDefault();
    document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
    document.getElementById('nav-fees').classList.add('active');
    document.getElementById('dashboardView').style.display = 'none';
    document.getElementById('feesView').classList.add('active');
    updateSummary();
}

function navigateToGrades(event) {
    event.preventDefault();
    alert('Navigating to Grades Management...');
}

function navigateToSettings(event) {
    event.preventDefault();
    alert('Navigating to Settings...');
}

// Fees Functions
function recordPayment() {
    alert('Opening payment recording form...');
}

function exportData() {
    alert('Exporting payment data...');
}

function updateSummary() {
    const summary = {total: 16500, pending: 3500, overdue: 8000, target: 50000};
    const amounts = document.querySelectorAll('.fees-view .amount');
    if (amounts.length > 0) {
        amounts[0].textContent = '₱' + summary.total.toLocaleString() + '.00';
        amounts[1].textContent = '₱' + summary.pending.toLocaleString() + '.00';
        amounts[2].textContent = '₱' + summary.overdue.toLocaleString() + '.00';
        amounts[3].textContent = '₱' + summary.target.toLocaleString() + '.00';
    }
}

// Dashboard Functions
function showSection(section) {
    const messages = {
        mission: 'MISSION: To provide quality education that nurtures creativity and dreams.',
        vision: 'VISION: To be a leading institution in holistic child development.',
        students: 'Total Students: 450\nKindergarten: 180\nElementary: 270',
        programs: 'Academic Programs:\n- Kindergarten\n- Elementary',
        success: 'Overall Success Rate: 98%'
    };
    alert(messages[section] || 'Section: ' + section);
}

function showProgram(program) {
    alert('Opening program: ' + program);
}

function showPerformance(type) {
    alert('Showing performance: ' + type);
}

function showNotifications() {
    alert('You have 3 new notifications!');
}

function showProfile() {
    alert('Opening profile settings');
}

// Initialize on page load
window.onload = function() {
    updateSummary();
};