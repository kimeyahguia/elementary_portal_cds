<?php
require_once 'student_header.php';
require_once 'student_layout.php';

// Check if AJAX request
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';



// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// AJAX Handler - Process AJAX requests before any HTML output
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'load_attendance') {
    header('Content-Type: application/json');

    $student_code = $_POST['student_code'] ?? '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $subject = $_POST['subject'] ?? '';
    $month = $_POST['month'] ?? '';
    $status = $_POST['status'] ?? '';
    $sort = isset($_POST['sort']) && in_array($_POST['sort'], ['ASC', 'DESC']) ? $_POST['sort'] : 'DESC';

    $records_per_page = 20;
    $offset = ($page - 1) * $records_per_page;

    // Build WHERE clause
    $where_conditions = ["a.student_code = :student_code"];
    $params = ['student_code' => $student_code];

    if (!empty($subject)) {
        $where_conditions[] = "s.subject_name = :subject";
        $params['subject'] = $subject;
    }

    if (!empty($month)) {
        $where_conditions[] = "MONTH(a.date) = :month";
        $params['month'] = $month;
    }

    if (!empty($status)) {
        $where_conditions[] = "a.status = :status";
        $params['status'] = $status;
    }

    $where_clause = implode(' AND ', $where_conditions);

    try {
        // Get total count
        $count_sql = "SELECT COUNT(*) as total 
                      FROM attendance a
                      INNER JOIN subjects s ON a.subject_code = s.subject_code
                      WHERE $where_clause";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $total_pages = ceil($total_records / $records_per_page);

        // Get attendance records
        $sql = "SELECT 
                    a.date,
                    a.status,
                    a.remarks,
                    s.subject_name,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                FROM attendance a
                INNER JOIN subjects s ON a.subject_code = s.subject_code
                INNER JOIN teachers t ON a.teacher_code = t.teacher_code
                WHERE $where_clause
                ORDER BY a.date $sort
                LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate table HTML
        $table_html = '<table class="table table-hover align-middle">';
        $table_html .= '<thead class="table-light">';
        $table_html .= '<tr><th>Date</th><th>Day</th><th>Subject</th><th>Teacher</th><th>Status</th><th>Remarks</th></tr>';
        $table_html .= '</thead><tbody>';

        if (count($records) > 0) {
            foreach ($records as $row) {
                $date = date('M d, Y', strtotime($row['date']));
                $day = date('l', strtotime($row['date']));
                $subject_name = htmlspecialchars($row['subject_name']);
                $teacher_name = htmlspecialchars($row['teacher_name']);
                $remarks = $row['remarks'] ? htmlspecialchars($row['remarks']) : '-';

                $statusColors = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];
                $color = $statusColors[$row['status']] ?? 'secondary';
                $status_display = ucfirst($row['status']);

                $table_html .= "<tr>";
                $table_html .= "<td><strong>$date</strong></td>";
                $table_html .= "<td>$day</td>";
                $table_html .= "<td>$subject_name</td>";
                $table_html .= "<td>$teacher_name</td>";
                $table_html .= "<td><span class='badge bg-$color'>$status_display</span></td>";
                $table_html .= "<td><small class='text-muted'>$remarks</small></td>";
                $table_html .= "</tr>";
            }
        } else {
            $table_html .= '<tr><td colspan="6" class="text-center py-4">';
            $table_html .= '<i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>';
            $table_html .= '<p class="text-muted mt-2">No attendance records found</p></td></tr>';
        }

        $table_html .= '</tbody></table>';

        // Generate pagination
        $pagination_html = '';
        if ($total_pages > 1) {
            $pagination_html .= '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';

            if ($page > 1) {
                $pagination_html .= '<li class="page-item"><a class="page-link pagination-link" data-page="' . ($page - 1) . '" href="#"><i class="bi bi-chevron-left"></i> Previous</a></li>';
            }

            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1) {
                $pagination_html .= '<li class="page-item"><a class="page-link pagination-link" data-page="1" href="#">1</a></li>';
                if ($start_page > 2) $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = $i == $page ? 'active' : '';
                $pagination_html .= '<li class="page-item ' . $active . '"><a class="page-link pagination-link" data-page="' . $i . '" href="#">' . $i . '</a></li>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                $pagination_html .= '<li class="page-item"><a class="page-link pagination-link" data-page="' . $total_pages . '" href="#">' . $total_pages . '</a></li>';
            }

            if ($page < $total_pages) {
                $pagination_html .= '<li class="page-item"><a class="page-link pagination-link" data-page="' . ($page + 1) . '" href="#">Next <i class="bi bi-chevron-right"></i></a></li>';
            }

            $pagination_html .= '</ul></nav>';
            $start_record = $offset + 1;
            $end_record = min($offset + $records_per_page, $total_records);
            $pagination_html .= '<div class="text-center mt-2"><small class="text-muted">Showing ' . $start_record . ' to ' . $end_record . ' of ' . $total_records . ' records</small></div>';
        }

        // Get stats and chart data (only on first load)
        $stats_html = '';
        $chart_data = null;
        $subjects_list = [];

        if ($page == 1 && empty($subject) && empty($month) && empty($status)) {
            // Get statistics
            $stats_sql = "SELECT 
                            COUNT(*) as total_days,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
                          FROM attendance WHERE student_code = :student_code";

            $stats_stmt = $conn->prepare($stats_sql);
            $stats_stmt->execute(['student_code' => $student_code]);
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

            $attendance_rate = $stats['total_days'] > 0 ? round(($stats['present_days'] / $stats['total_days']) * 100, 1) : 0;

            $rating = 'Needs Improvement';
            if ($attendance_rate >= 95) $rating = 'Excellent';
            elseif ($attendance_rate >= 90) $rating = 'Very Good';
            elseif ($attendance_rate >= 85) $rating = 'Good';

            $stats_html = '<div class="col-md-3 mb-3"><div class="info-card"><div class="icon text-success"><i class="bi bi-check-circle-fill"></i></div><h3>PRESENT</h3><div class="value text-success">' . $stats['present_days'] . '</div><div class="label">Total days present</div></div></div>';
            $stats_html .= '<div class="col-md-3 mb-3"><div class="info-card"><div class="icon text-danger"><i class="bi bi-x-circle-fill"></i></div><h3>ABSENT</h3><div class="value text-danger">' . $stats['absent_days'] . '</div><div class="label">Total days absent</div></div></div>';
            $stats_html .= '<div class="col-md-3 mb-3"><div class="info-card"><div class="icon text-warning"><i class="bi bi-clock-fill"></i></div><h3>LATE</h3><div class="value text-warning">' . $stats['late_days'] . '</div><div class="label">Times tardy</div></div></div>';
            $stats_html .= '<div class="col-md-3 mb-3"><div class="info-card"><div class="icon text-primary"><i class="bi bi-percent"></i></div><h3>ATTENDANCE RATE</h3><div class="value text-primary">' . $attendance_rate . '%</div><div class="label">' . $rating . '</div></div></div>';

            // Get chart data
            $chart_sql = "SELECT DATE_FORMAT(date, '%Y-%m') as month,
                          SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                          SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                          SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                          SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
                          FROM attendance WHERE student_code = :student_code
                          GROUP BY DATE_FORMAT(date, '%Y-%m') ORDER BY month ASC LIMIT 12";

            $chart_stmt = $conn->prepare($chart_sql);
            $chart_stmt->execute(['student_code' => $student_code]);
            $chart_records = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

            $months = [];
            $present_data = [];
            $absent_data = [];
            $late_data = [];

            foreach ($chart_records as $rec) {
                $date = new DateTime($rec['month'] . '-01');
                $months[] = $date->format('M Y');
                $present_data[] = (int)$rec['present'];
                $absent_data[] = (int)$rec['absent'];
                $late_data[] = (int)$rec['late'];
            }

            $chart_data = [
                'months' => $months,
                'present' => $present_data,
                'absent' => $absent_data,
                'late' => $late_data,
                'totals' => [
                    'present' => (int)$stats['present_days'],
                    'absent' => (int)$stats['absent_days'],
                    'late' => (int)$stats['late_days'],
                    'excused' => (int)$stats['excused_days']
                ]
            ];

            // Get subjects for filter
            $subjects_sql = "SELECT DISTINCT s.subject_name FROM attendance a
                            INNER JOIN subjects s ON a.subject_code = s.subject_code
                            WHERE a.student_code = :student_code ORDER BY s.subject_name";
            $subjects_stmt = $conn->prepare($subjects_sql);
            $subjects_stmt->execute(['student_code' => $student_code]);
            $subjects_list = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode([
            'success' => true,
            'table' => $table_html,
            'pagination' => $pagination_html,
            'stats' => $stats_html,
            'chart_data' => $chart_data,
            'subjects' => $subjects_list,
            'total_records' => $total_records,
            'current_page' => $page,
            'total_pages' => $total_pages
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
}

// Regular page load continues below...

// Fetch student information
$stmt = $conn->prepare("
    SELECT 
        s.*,
        CONCAT(s.first_name, ' ', s.last_name) as full_name,
        sec.grade_level,
        sec.section_name as section,
        CONCAT(t.first_name, ' ', t.last_name) as adviser,
        u.username as lrn
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN sections sec ON s.section_id = sec.section_id
    LEFT JOIN teachers t ON sec.adviser_code = t.teacher_code
    WHERE s.user_id = :user_id
");
$stmt->execute(['user_id' => $user_id]);
$student_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student_data) {
    die("Student record not found");
}

$student_code = $student_data['student_code'];

// Get current school year and quarter
$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_school_year'");
$current_school_year = $stmt->fetchColumn() ?: '2025-2026';

$stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'current_quarter'");
$current_quarter = $stmt->fetchColumn() ?: '2nd';

// Prepare student info array
$student_info = [
    'first_name' => $student_data['first_name'],
    'full_name' => $student_data['full_name'],
    'lrn' => $student_data['lrn'],
    'grade_level' => $student_data['grade_level'] ?? 'N/A',
    'section' => $student_data['section'] ?? 'N/A',
    'adviser' => $student_data['adviser'] ?? 'N/A',
    'school_year' => $current_school_year,
    'current_quarter' => $current_quarter
];

// Get initials
$names = explode(' ', $student_data['full_name']);
$initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));

ob_start();
?>

<h4 class="page-title">
    <i class="bi bi-calendar-check"></i> Attendance Records
</h4>

<!-- Attendance Statistics -->
<div class="row mb-4" id="attendanceStats">
    <div class="col-12 text-center py-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading attendance statistics...</p>
    </div>
</div>

<!-- Attendance Chart -->
<div class="row mb-4" id="attendanceCharts">
    <div class="col-12 text-center py-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading charts...</p>
    </div>
</div>

<!-- Attendance Status Guide -->
<div class="card-box mb-4">
    <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
        <i class="bi bi-info-circle-fill"></i> ATTENDANCE STATUS GUIDE
    </h6>
    <div class="row">
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-success me-2">Present</span>
                <small>Student was present for the entire class</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-danger me-2">Absent</span>
                <small>Student was absent without excuse</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-warning me-2">Late</span>
                <small>Student arrived after class started</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center mb-2">
                <span class="badge bg-info me-2">Excused</span>
                <small>Absence with valid excuse/documentation</small>
            </div>
        </div>
    </div>
</div>

<!-- Recent Attendance Records -->
<div class="card-box">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="fw-bold mb-0" style="color: #4c8c4a;">
            <i class="bi bi-list-ul"></i> RECENT ATTENDANCE RECORDS
        </h6>
        <div class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label mb-1 small">Subject</label>
                <select id="subjectFilter" class="form-select form-select-sm" style="width: 180px;">
                    <option value="">All Subjects</option>
                </select>
            </div>
            <div>
                <label class="form-label mb-1 small">Month</label>
                <select id="monthFilter" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Months</option>
                    <option value="01">January</option>
                    <option value="02">February</option>
                    <option value="03">March</option>
                    <option value="04">April</option>
                    <option value="05">May</option>
                    <option value="06">June</option>
                    <option value="07">July</option>
                    <option value="08">August</option>
                    <option value="09">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div>
                <label class="form-label mb-1 small">Status</label>
                <select id="statusFilter" class="form-select form-select-sm" style="width: 130px;">
                    <option value="">All Status</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="excused">Excused</option>
                </select>
            </div>
            <div>
                <label class="form-label mb-1 small">Sort By</label>
                <select id="sortOrder" class="form-select form-select-sm" style="width: 150px;">
                    <option value="DESC">Newest First</option>
                    <option value="ASC">Oldest First</option>
                </select>
            </div>
            <div>
                <label class="form-label mb-1 small">&nbsp;</label>
                <button id="applyFilters" class="btn btn-primary btn-sm d-block" style="width: 100px;">
                    <i class="bi bi-funnel"></i> Apply
                </button>
            </div>
            <div>
                <label class="form-label mb-1 small">&nbsp;</label>
                <button id="resetFilters" class="btn btn-outline-secondary btn-sm d-block" style="width: 80px;">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive" id="attendanceTable">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading attendance records...</p>
        </div>
    </div>

    <div id="paginationContainer" class="mt-3"></div>
</div>

<script>
    const studentCode = '<?php echo $student_code; ?>';
    let currentPage = 1;
    let isLoading = false;
    let chartsInitialized = false;
    let attendanceChart = null;
    let pieChart = null;

    function loadAttendance(page = 1) {
        if (isLoading) return;

        isLoading = true;
        currentPage = page;

        const subjectFilter = document.getElementById('subjectFilter').value;
        const monthFilter = document.getElementById('monthFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const sortOrder = document.getElementById('sortOrder').value;

        if (page > 1 || subjectFilter || monthFilter || statusFilter || sortOrder !== 'DESC') {
            document.getElementById('attendanceTable').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading records...</p></div>';
        }

        // Create form data
        const formData = new FormData();
        formData.append('ajax_action', 'load_attendance');
        formData.append('student_code', studentCode);
        formData.append('page', page);
        formData.append('subject', subjectFilter);
        formData.append('month', monthFilter);
        formData.append('status', statusFilter);
        formData.append('sort', sortOrder);

        fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('attendanceTable').innerHTML = data.table;
                    document.getElementById('paginationContainer').innerHTML = data.pagination || '';

                    if (data.stats) {
                        document.getElementById('attendanceStats').innerHTML = data.stats;
                    }

                    if (data.chart_data && !chartsInitialized) {
                        loadCharts(data.chart_data);
                        chartsInitialized = true;
                    }

                    if (data.subjects && document.getElementById('subjectFilter').options.length === 1) {
                        data.subjects.forEach(subject => {
                            const option = document.createElement('option');
                            option.value = subject;
                            option.textContent = subject;
                            document.getElementById('subjectFilter').appendChild(option);
                        });
                    }
                } else {
                    document.getElementById('attendanceTable').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + (data.message || 'Error loading data') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('attendanceTable').innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Failed to load attendance records. Please check console.</div>';
            })
            .finally(() => {
                isLoading = false;
            });
    }

    function loadCharts(data) {
        const chartHTML = '<div class="col-md-8 mb-3"><div class="card-box"><h6 class="fw-bold mb-3" style="color: #4c8c4a;"><i class="bi bi-graph-up"></i> MONTHLY ATTENDANCE TREND</h6><div class="chart-container"><canvas id="attendanceChart"></canvas></div></div></div><div class="col-md-4 mb-3"><div class="card-box"><h6 class="fw-bold mb-3" style="color: #4c8c4a;"><i class="bi bi-pie-chart-fill"></i> ATTENDANCE BREAKDOWN</h6><div class="chart-container" style="height: 250px;"><canvas id="attendancePieChart"></canvas></div></div></div>';
        document.getElementById('attendanceCharts').innerHTML = chartHTML;

        attendanceChart = new Chart(document.getElementById('attendanceChart'), {
            type: 'line',
            data: {
                labels: data.months,
                datasets: [{
                        label: 'Present',
                        data: data.present,
                        borderColor: '#4caf50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Absent',
                        data: data.absent,
                        borderColor: '#f44336',
                        backgroundColor: 'rgba(244, 67, 54, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Late',
                        data: data.late,
                        borderColor: '#ff9800',
                        backgroundColor: 'rgba(255, 152, 0, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        pieChart = new Chart(document.getElementById('attendancePieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Excused'],
                datasets: [{
                    data: [data.totals.present, data.totals.absent, data.totals.late, data.totals.excused],
                    backgroundColor: ['#4caf50', '#f44336', '#ff9800', '#2196f3']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Initial load
        loadAttendance(1);

        // Apply button click
        document.getElementById('applyFilters').addEventListener('click', function() {
            loadAttendance(1);
        });

        // Reset button click
        document.getElementById('resetFilters').addEventListener('click', function() {
            document.getElementById('subjectFilter').value = '';
            document.getElementById('monthFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('sortOrder').value = 'DESC';
            loadAttendance(1);
        });

        // Optional: Press Enter to apply filters
        ['subjectFilter', 'monthFilter', 'statusFilter', 'sortOrder'].forEach(id => {
            document.getElementById(id).addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadAttendance(1);
                }
            });
        });

        // Pagination click handler (using event delegation)
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('pagination-link') || e.target.closest('.pagination-link')) {
                e.preventDefault();
                const link = e.target.classList.contains('pagination-link') ? e.target : e.target.closest('.pagination-link');
                const page = parseInt(link.getAttribute('data-page'));
                loadAttendance(page);

                // Smooth scroll to table
                const tableElement = document.getElementById('attendanceTable');
                const offset = tableElement.offsetTop - 100;
                window.scrollTo({
                    top: offset,
                    behavior: 'smooth'
                });
            }
        });
    });
</script>

<style>
    .pagination-link {
        cursor: pointer;
    }

    .pagination-link:hover {
        background-color: #e9ecef;
    }

    .form-select-sm {
        padding: 0.25rem 2rem 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .form-label.small {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    @media (max-width: 768px) {
        .d-flex.gap-2.flex-wrap {
            width: 100%;
        }

        .form-select-sm,
        .btn-sm {
            width: 100% !important;
            margin-bottom: 0.5rem;
        }
    }

    #attendanceTable {
        max-height: 400px;
        overflow-y: auto;
        position: relative;
    }

    #attendanceTable table {
        margin-bottom: 0;
    }

    #attendanceTable thead {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8f9fa;
    }

    #attendanceTable thead th {
        border-top: none;
    }

    /* Custom scrollbar styling (optional) */
    #attendanceTable::-webkit-scrollbar {
        width: 8px;
    }

    #attendanceTable::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    #attendanceTable::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    #attendanceTable::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>

<?php
$content = ob_get_clean();
if ($is_ajax) {
    // For AJAX requests, output only the content wrapped in main-content div
    echo '<div class="main-content">' . $content . '</div>';
} else {
    // For normal requests, use full layout
    renderLayout('Page Title', $content, 'attendance', $student_info, $initials);
}
?>