<?php
require_once 'student_header.php';
require_once 'student_layout.php';



// Fetch grades for all quarters
$stmt = $conn->prepare("
    SELECT 
        g.quarter,
        s.subject_name,
        g.final_grade as grade,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name
    FROM grades g
    INNER JOIN subjects s ON g.subject_code = s.subject_code
    LEFT JOIN teachers t ON g.teacher_code = t.teacher_code
    WHERE g.student_code = :student_code
    ORDER BY 
        FIELD(g.quarter, '1st', '2nd', '3rd', '4th'),
        s.subject_name
");
$stmt->execute(['student_code' => $student_code]);
$all_grades = $stmt->fetchAll();

// Get all subjects assigned to the student's section
if ($student_data['section_id']) {
    // First get section grade level
    $stmt = $conn->prepare("SELECT grade_level FROM sections WHERE section_id = :section_id");
    $stmt->execute(['section_id' => $student_data['section_id']]);
    $section_grade = $stmt->fetchColumn();

    if ($section_grade) {
        // Get all subjects for this grade level with their assigned teachers
        $stmt = $conn->prepare("
            SELECT DISTINCT
                subj.subject_name,
                subj.subject_code,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM grade_schedule_template gst
            INNER JOIN subjects subj ON gst.subject_code = subj.subject_code
            LEFT JOIN section_schedules ss ON gst.template_id = ss.template_id 
                AND ss.section_id = :section_id
                AND ss.is_active = TRUE
            LEFT JOIN teachers t ON ss.teacher_code = t.teacher_code
            WHERE gst.grade_level = :grade_level
            ORDER BY subj.subject_name
        ");
        $stmt->execute([
            'section_id' => $student_data['section_id'],
            'grade_level' => $section_grade
        ]);
        $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $all_subjects = [];
    }
} else {
    $all_subjects = [];
}

// Default subjects if none found
if (empty($all_subjects)) {
    $all_subjects = [
        ['subject_name' => 'Mathematics', 'teacher_name' => 'N/A'],
        ['subject_name' => 'Science', 'teacher_name' => 'N/A'],
        ['subject_name' => 'English', 'teacher_name' => 'N/A'],
        ['subject_name' => 'Filipino', 'teacher_name' => 'N/A'],
        ['subject_name' => 'Araling Panlipunan', 'teacher_name' => 'N/A'],
        ['subject_name' => 'MAPEH', 'teacher_name' => 'N/A'],
        ['subject_name' => 'GMRC', 'teacher_name' => 'N/A'],
    ];
}

// Organize grades by quarter
$grades_data = ['1st' => [], '2nd' => [], '3rd' => [], '4th' => []];

foreach (['1st', '2nd', '3rd', '4th'] as $quarter) {
    foreach ($all_subjects as $subject) {
        $grade_found = false;
        foreach ($all_grades as $grade_row) {
            if ($grade_row['quarter'] == $quarter && $grade_row['subject_name'] == $subject['subject_name']) {
                $grade_value = (float)$grade_row['grade'];
                $grades_data[$quarter][] = [
                    'subject' => $subject['subject_name'],
                    'teacher' => $grade_row['teacher_name'] ?? $subject['teacher_name'] ?? 'N/A',
                    'grade' => $grade_value,
                    'remarks' => $grade_value >= 75 ? 'Passed' : ($grade_value > 0 ? 'Failed' : 'Not Available')
                ];
                $grade_found = true;
                break;
            }
        }
        if (!$grade_found) {
            $grades_data[$quarter][] = [
                'subject' => $subject['subject_name'],
                'teacher' => $subject['teacher_name'] ?? 'N/A',
                'grade' => 0,
                'remarks' => 'Not Available'
            ];
        }
    }
}

ob_start();
?>

<h4 class="fw-bold mb-3">Academic Grades</h4>

<div class="row">
    <div class="col-md-9">
        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold mb-1">Grade Report</h5>
                    <p class="text-muted mb-0">Academic Year <?php echo $student_info['school_year']; ?></p>
                </div>
                <div>
                    <label class="me-2 fw-semibold">Select Quarter:</label>
                    <select id="quarterSelect" class="form-select d-inline-block w-auto">
                        <option value="1st">1st Quarter</option>
                        <option value="2nd" selected>2nd Quarter</option>
                        <option value="3rd">3rd Quarter</option>
                        <option value="4th">4th Quarter</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Subject</th>
                            <th class="text-center">Grade</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="gradesTableBody">
                        <?php foreach ($grades_data['2nd'] as $grade): ?>
                            <tr>
                                <td><strong><?php echo $grade['subject']; ?></strong></td>
                                <td class="text-center">
                                    <h5 class="mb-0 fw-bold <?php echo $grade['grade'] >= 90 ? 'text-success' : ($grade['grade'] >= 80 ? 'text-primary' : ($grade['grade'] > 0 ? 'text-warning' : 'text-muted')); ?>">
                                        <?php echo $grade['grade'] > 0 ? $grade['grade'] : '-'; ?>
                                    </h5>
                                </td>
                                <td>
                                    <?php
                                    if ($grade['grade'] >= 90) {
                                        echo '<span class="badge bg-success">Outstanding</span>';
                                    } elseif ($grade['grade'] >= 85) {
                                        echo '<span class="badge bg-primary">Very Satisfactory</span>';
                                    } elseif ($grade['grade'] >= 80) {
                                        echo '<span class="badge bg-info">Satisfactory</span>';
                                    } elseif ($grade['grade'] >= 75) {
                                        echo '<span class="badge bg-warning">Fairly Satisfactory</span>';
                                    } elseif ($grade['grade'] > 0) {
                                        echo '<span class="badge bg-danger">Did Not Meet</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Not Available</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mt-4 pt-3 border-top">
                <div class="col-md-4 text-center">
                    <h4 class="fw-bold mb-0" id="quarterAverage">87.0</h4>
                    <p class="text-muted mb-0">General Average</p>
                </div>
                <div class="col-md-4 text-center">
                    <h4 class="fw-bold mb-0" id="passingSubjects">7/7</h4>
                    <p class="text-muted mb-0">Passed Subjects</p>
                </div>
                <div class="col-md-4 text-center">
                    <h4 class="fw-bold mb-0 text-success" id="quarterStatus">Passed</h4>
                    <p class="text-muted mb-0">Quarter Status</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Grading Scale -->
    <div class="col-md-3">
        <div class="card-box">
            <h6 class="fw-bold mb-3">Grading Scale</h6>
            <table class="table table-sm grading-table">
                <thead>
                    <tr>
                        <th>Grade</th>
                        <th>Descriptor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>90-100</strong></td>
                        <td>Outstanding</td>
                    </tr>
                    <tr>
                        <td><strong>85-89</strong></td>
                        <td>Very Satisfactory</td>
                    </tr>
                    <tr>
                        <td><strong>80-84</strong></td>
                        <td>Satisfactory</td>
                    </tr>
                    <tr>
                        <td><strong>75-79</strong></td>
                        <td>Fairly Satisfactory</td>
                    </tr>
                    <tr>
                        <td><strong>Below 75</strong></td>
                        <td>Did Not Meet</td>
                    </tr>
                </tbody>
            </table>

            <div class="alert alert-info mt-3" role="alert">
                <small><strong>Note:</strong> Passing grade is 75 and above for all subjects.</small>
            </div>
        </div>
    </div>
</div>

<script>
    const gradesData = <?php echo json_encode($grades_data); ?>;

    document.getElementById('quarterSelect').addEventListener('change', function() {
        const quarter = this.value;
        const grades = gradesData[quarter];

        let tableHTML = '';
        let total = 0;
        let count = 0;
        let passing = 0;

        grades.forEach(grade => {
            let badge = '';
            let colorClass = '';

            if (grade.grade > 0) {
                if (grade.grade >= 90) {
                    badge = '<span class="badge bg-success">Outstanding</span>';
                    colorClass = 'text-success';
                    passing++;
                } else if (grade.grade >= 85) {
                    badge = '<span class="badge bg-primary">Very Satisfactory</span>';
                    colorClass = 'text-primary';
                    passing++;
                } else if (grade.grade >= 80) {
                    badge = '<span class="badge bg-info">Satisfactory</span>';
                    colorClass = 'text-primary';
                    passing++;
                } else if (grade.grade >= 75) {
                    badge = '<span class="badge bg-warning">Fairly Satisfactory</span>';
                    colorClass = 'text-warning';
                    passing++;
                } else {
                    badge = '<span class="badge bg-danger">Did Not Meet</span>';
                    colorClass = 'text-danger';
                }
                total += grade.grade;
                count++;
            } else {
                badge = '<span class="badge bg-secondary">Not Available</span>';
                colorClass = 'text-muted';
            }

            tableHTML += `
            <tr>
                <td><strong>${grade.subject}</strong></td>
                <td class="text-center"><h5 class="mb-0 fw-bold ${colorClass}">${grade.grade > 0 ? grade.grade : '-'}</h5></td>
                <td>${badge}</td>
            </tr>
        `;
        });

        document.getElementById('gradesTableBody').innerHTML = tableHTML;

        const average = count > 0 ? (total / count).toFixed(1) : '0.0';
        document.getElementById('quarterAverage').textContent = average;
        document.getElementById('passingSubjects').textContent = `${passing}/${grades.length}`;

        if (count === 0) {
            document.getElementById('quarterStatus').textContent = 'Not Available';
            document.getElementById('quarterStatus').className = 'fw-bold mb-0 text-secondary';
        } else if (passing === grades.length) {
            document.getElementById('quarterStatus').textContent = 'Passed';
            document.getElementById('quarterStatus').className = 'fw-bold mb-0 text-success';
        } else {
            document.getElementById('quarterStatus').textContent = 'Needs Improvement';
            document.getElementById('quarterStatus').className = 'fw-bold mb-0 text-warning';
        }
    });
</script>

<?php
$content = ob_get_clean();
renderLayout('Page Title', $content, 'grades', $student_info, $initials, $profile_picture_url);
?>