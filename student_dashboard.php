<?php
require_once 'student_header.php';
require_once 'student_layout.php';

// Fetch actual performance analytics for current quarter
$stmt = $conn->prepare("
    SELECT 
        g.quarter,
        s.subject_name,
        g.written_work,
        g.performance_task,
        g.quarterly_exam,
        g.final_grade
    FROM grades g
    INNER JOIN subjects s ON g.subject_code = s.subject_code
    WHERE g.student_code = :student_code AND g.quarter = :current_quarter
");
$stmt->execute([
    'student_code' => $student_code,
    'current_quarter' => $current_quarter
]);
$current_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate analytics
$total_subjects = count($current_grades);
$passing_subjects = 0;
$total_average = 0;
$highest_grade = 0;
$lowest_grade = 100;

if ($total_subjects > 0) {
    foreach ($current_grades as $grade) {
        $final = (float)$grade['final_grade'];
        $total_average += $final;

        if ($final >= 75) {
            $passing_subjects++;
        }

        if ($final > $highest_grade) {
            $highest_grade = $final;
        }

        if ($final < $lowest_grade) {
            $lowest_grade = $final;
        }
    }

    $total_average = round($total_average / $total_subjects, 1);
} else {
    $total_average = 0;
    $lowest_grade = 0;
}

// Get previous quarter for trend analysis
$previous_quarters = ['1st' => null, '2nd' => '1st', '3rd' => '2nd', '4th' => '3rd'];
$previous_quarter = $previous_quarters[$current_quarter] ?? null;

$trend = 'No data';
$trend_icon = 'bi-dash';
$trend_class = 'text-muted';

if ($previous_quarter) {
    $stmt = $conn->prepare("
        SELECT AVG(final_grade) as prev_avg
        FROM grades
        WHERE student_code = :student_code AND quarter = :previous_quarter
    ");
    $stmt->execute([
        'student_code' => $student_code,
        'previous_quarter' => $previous_quarter
    ]);
    $prev_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($prev_data && $prev_data['prev_avg']) {
        $prev_avg = (float)$prev_data['prev_avg'];
        $difference = $total_average - $prev_avg;

        if ($difference > 2) {
            $trend = 'Improving';
            $trend_icon = 'bi-arrow-up';
            $trend_class = 'text-success';
        } elseif ($difference < -2) {
            $trend = 'Declining';
            $trend_icon = 'bi-arrow-down';
            $trend_class = 'text-danger';
        } else {
            $trend = 'Stable';
            $trend_icon = 'bi-dash';
            $trend_class = 'text-primary';
        }
    }
}

// Fetch attendance for current school year
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
    FROM attendance
    WHERE student_code = :student_code
    AND YEAR(date) = YEAR(CURDATE())
");
$stmt->execute(['student_code' => $student_code]);
$attendance_data = $stmt->fetch(PDO::FETCH_ASSOC);

$attendance_rate = 0;
$attendance_label = 'No records';
$attendance_class = 'text-muted';

if ($attendance_data && $attendance_data['total_days'] > 0) {
    $attendance_rate = round(($attendance_data['present_days'] / $attendance_data['total_days']) * 100, 1);

    if ($attendance_rate >= 95) {
        $attendance_label = 'Excellent';
        $attendance_class = 'text-success';
    } elseif ($attendance_rate >= 85) {
        $attendance_label = 'Good';
        $attendance_class = 'text-primary';
    } elseif ($attendance_rate >= 75) {
        $attendance_label = 'Fair';
        $attendance_class = 'text-warning';
    } else {
        $attendance_label = 'Needs Improvement';
        $attendance_class = 'text-danger';
    }
}

// Determine performance level
$performance_level = 'No Data';
$performance_message = 'Start building your record';
$performance_class = 'text-muted';

if ($total_subjects > 0) {
    if ($total_average >= 90) {
        $performance_level = 'Excellent';
        $performance_message = 'Outstanding work!';
        $performance_class = 'text-success';
    } elseif ($total_average >= 85) {
        $performance_level = 'Very Good';
        $performance_message = 'Keep it up!';
        $performance_class = 'text-success';
    } elseif ($total_average >= 80) {
        $performance_level = 'Good';
        $performance_message = 'Doing well!';
        $performance_class = 'text-primary';
    } elseif ($total_average >= 75) {
        $performance_level = 'Fair';
        $performance_message = 'Room for improvement';
        $performance_class = 'text-warning';
    } else {
        $performance_level = 'Needs Work';
        $performance_message = 'Seek help from teachers';
        $performance_class = 'text-danger';
    }
}

ob_start();


ob_start();
?>

<style>
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4c8c4a 0%, #5fa85d 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: 700;
    color: white;
    margin: 0 auto;
    box-shadow: 0 4px 12px rgba(76, 140, 74, 0.3);
    border: 4px solid white;
}
</style>

<h4 class="page-title">
    <i class="bi bi-speedometer2"></i> Student Dashboard
</h4>

<!-- Student Information Card -->
<div class="card-box mb-4">
    <h6 class="fw-bold mb-4" style="color: #4c8c4a; font-size: 16px;">
        <i class="bi bi-person-circle"></i> STUDENT INFORMATION
    </h6>
    <div class="row">
        <div class="col-md-3 text-center border-end">
            <?php if ($profile_picture_url): ?>
                <img src="<?php echo htmlspecialchars($profile_picture_url); ?>"
                    alt="Profile Picture"
                    class="dashboard-profile-image">
            <?php else: ?>
                <div class="profile-avatar"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div class="mt-3">
                <span class="badge bg-success">Enrolled</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">FULL NAME</small>
                <strong style="color: #2c3e2b;"><?php echo $student_info['full_name']; ?></strong>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">GRADE AND SECTION</small>
                <strong style="color: #2c3e2b;"><?php echo $student_info['grade_level'] . ' - ' . $student_info['section']; ?></strong>
            </div>
            <div>
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">CLASS ADVISER</small>
                <strong style="color: #2c3e2b;"><?php echo $student_info['adviser']; ?></strong>
            </div>
        </div>
        <div class="col-md-5">
            <div class="mb-3">
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">STUDENT CODE (LRN)</small>
                <strong style="color: #2c3e2b;"><?php echo $student_info['lrn']; ?></strong>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">SCHOOL YEAR</small>
                <strong style="color: #2c3e2b;"><?php echo $student_info['school_year']; ?></strong>
            </div>
            <div>
                <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">CURRENT QUARTER</small>
                <strong style="color: #2c3e2b;"><?php echo $student_info['current_quarter']; ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- Status Cards -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="info-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="text-start">
                    <h3>ENROLLMENT STATUS</h3>
                    <div class="value" style="font-size: 24px;"><?php echo $student_info['enrollment_status']; ?></div>
                    <div class="label">Academic Year: <?php echo $student_info['school_year']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="info-card">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-credit-card-fill"></i>
                </div>
                <div class="text-start">
                    <h3>PAYMENT STATUS</h3>
                    <div class="value" style="font-size: 24px;"><?php echo $student_info['payment_status']; ?></div>
                    <div class="label">Balance: ₱<?php echo number_format($student_info['balance'], 2); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Parent Information Card -->
<div class="card-box mb-4">
    <h6 class="fw-bold mb-4" style="color: #4c8c4a; font-size: 16px;">
        <i class="bi bi-people-fill"></i> PARENT/GUARDIAN INFORMATION
    </h6>
    <div class="row">
        <div class="col-md-6">
            <div class="d-flex align-items-start mb-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3" style="width: 45px; height: 45px; font-size: 20px;">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div>
                    <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">PARENT/GUARDIAN NAME</small>
                    <strong style="color: #2c3e2b;"><?php echo $parent_info['name']; ?></strong>
                </div>
            </div>
            <div class="d-flex align-items-start mb-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3" style="width: 45px; height: 45px; font-size: 20px;">
                    <i class="bi bi-telephone-fill"></i>
                </div>
                <div>
                    <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">CONTACT NUMBER</small>
                    <strong style="color: #2c3e2b;"><?php echo $parent_info['contact']; ?></strong>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="d-flex align-items-start mb-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3" style="width: 45px; height: 45px; font-size: 20px;">
                    <i class="bi bi-envelope-fill"></i>
                </div>
                <div>
                    <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">EMAIL ADDRESS</small>
                    <strong style="color: #2c3e2b;"><?php echo $parent_info['email']; ?></strong>
                </div>
            </div>
            <div class="d-flex align-items-start mb-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3" style="width: 45px; height: 45px; font-size: 20px;">
                    <i class="bi bi-house-fill"></i>
                </div>
                <div>
                    <small class="text-muted d-block" style="font-size: 12px; font-weight: 600;">HOME ADDRESS</small>
                    <strong style="color: #2c3e2b;"><?php echo $parent_info['address']; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<h5 class="fw-bold mb-3" style="color: #2c3e2b; font-size: 22px;">
    <i class="bi bi-graph-up-arrow"></i> Academic Performance Analytics
    <span class="badge bg-primary ms-2" style="font-size: 12px;"><?php echo $current_quarter; ?> Quarter</span>
</h5>

<?php if ($total_subjects > 0): ?>
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-bar-chart-fill"></i></div>
                <h3>CURRENT AVERAGE</h3>
                <div class="value"><?php echo number_format($total_average, 1); ?></div>
                <div class="label <?php echo $trend_class; ?>">
                    <i class="<?php echo $trend_icon; ?>"></i> <?php echo $trend; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-trophy-fill"></i></div>
                <h3>PASSING SUBJECTS</h3>
                <div class="value"><?php echo $passing_subjects; ?>/<?php echo $total_subjects; ?></div>
                <div class="label">
                    <?php if ($passing_subjects === $total_subjects): ?>
                        <span class="text-success">All subjects passed!</span>
                    <?php elseif ($passing_subjects > 0): ?>
                        <span class="text-warning"><?php echo ($total_subjects - $passing_subjects); ?> need attention</span>
                    <?php else: ?>
                        <span class="text-danger">Seek academic help</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-calendar-check-fill"></i></div>
                <h3>ATTENDANCE RATE</h3>
                <div class="value"><?php echo $attendance_rate > 0 ? $attendance_rate . '%' : 'N/A'; ?></div>
                <div class="label <?php echo $attendance_class; ?>"><?php echo $attendance_label; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-graph-up"></i></div>
                <h3>PERFORMANCE LEVEL</h3>
                <div class="value <?php echo $performance_class; ?>" style="font-size: 22px;"><?php echo $performance_level; ?></div>
                <div class="label"><?php echo $performance_message; ?></div>
            </div>
        </div>
    </div>

    <!-- Additional Quick Insights -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card-box">
                <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                    <i class="bi bi-star-fill"></i> GRADE RANGE
                </h6>
                <div class="d-flex justify-content-around align-items-center">
                    <div class="text-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success mx-auto mb-2" style="width: 60px; height: 60px; font-size: 24px;">
                            <i class="bi bi-arrow-up-circle-fill"></i>
                        </div>
                        <small class="text-muted d-block">Highest</small>
                        <strong class="text-success" style="font-size: 24px;"><?php echo number_format($highest_grade, 1); ?></strong>
                    </div>
                    <div class="text-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mb-2" style="width: 60px; height: 60px; font-size: 24px;">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <small class="text-muted d-block">Average</small>
                        <strong class="text-primary" style="font-size: 24px;"><?php echo number_format($total_average, 1); ?></strong>
                    </div>
                    <div class="text-center">
                        <div class="stat-icon <?php echo $lowest_grade < 85 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-warning bg-opacity-10 text-warning'; ?> mx-auto mb-2" style="width: 60px; height: 60px; font-size: 24px;">
                            <i class="bi bi-arrow-down-circle-fill"></i>
                        </div>
                        <small class="text-muted d-block">Lowest</small>
                        <strong class="<?php echo $lowest_grade < 85 ? 'text-danger' : 'text-warning'; ?>" style="font-size: 24px;"><?php echo number_format($lowest_grade, 1); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card-box">
                <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                    <i class="bi bi-clipboard-check-fill"></i> COMPONENT AVERAGES
                </h6>
                <?php
                $avg_ww = 0;
                $avg_pt = 0;
                $avg_qe = 0;

                foreach ($current_grades as $grade) {
                    $avg_ww += (float)$grade['written_work'];
                    $avg_pt += (float)$grade['performance_task'];
                    $avg_qe += (float)$grade['quarterly_exam'];
                }

                $avg_ww = round($avg_ww / $total_subjects, 1);
                $avg_pt = round($avg_pt / $total_subjects, 1);
                $avg_qe = round($avg_qe / $total_subjects, 1);
                ?>

                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted"><i class="bi bi-pencil-fill"></i> Written Work</small>
                        <strong><?php echo $avg_ww; ?></strong>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $avg_ww; ?>%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted"><i class="bi bi-clipboard-data-fill"></i> Performance Tasks</small>
                        <strong><?php echo $avg_pt; ?></strong>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-primary" style="width: <?php echo $avg_pt; ?>%"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted"><i class="bi bi-file-text-fill"></i> Quarterly Exam</small>
                        <strong><?php echo $avg_qe; ?></strong>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-warning" style="width: <?php echo $avg_qe; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- No grades yet message -->
    <div class="alert alert-info border-0 shadow-sm">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle-fill me-3" style="font-size: 32px;"></i>
            <div>
                <h6 class="fw-bold mb-1">No Grades Available Yet</h6>
                <p class="mb-0">Your grades for the <?php echo $current_quarter; ?> Quarter haven't been posted yet. Check back soon!</p>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-bar-chart-fill"></i></div>
                <h3>CURRENT AVERAGE</h3>
                <div class="value">-</div>
                <div class="label text-muted">No data yet</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-trophy-fill"></i></div>
                <h3>PASSING SUBJECTS</h3>
                <div class="value">-</div>
                <div class="label text-muted">No data yet</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-calendar-check-fill"></i></div>
                <h3>ATTENDANCE RATE</h3>
                <div class="value"><?php echo $attendance_rate > 0 ? $attendance_rate . '%' : '-'; ?></div>
                <div class="label <?php echo $attendance_class; ?>"><?php echo $attendance_label; ?></div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-card">
                <div class="icon"><i class="bi bi-graph-up"></i></div>
                <h3>PERFORMANCE LEVEL</h3>
                <div class="value text-muted" style="font-size: 22px;">-</div>
                <div class="label text-muted">Building record...</div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
renderLayout('Page Title', $content, 'dashboard', $student_info, $initials, $profile_picture_url);
?>