<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];


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

// Check cache for performance data
$cache_key = 'perf_analytics_' . $student_code;
$use_cache = false;

if (isset($_SESSION[$cache_key]) && !isset($_GET['refresh'])) {
    $cache_data = $_SESSION[$cache_key];
    if ((time() - $cache_data['timestamp']) < 300) { // 5 minute cache
        $use_cache = true;
        $all_grades = $cache_data['all_grades'];
        $attendance_data = $cache_data['attendance_data'];
        $attendance_rate = $cache_data['attendance_rate'];
        $grades_by_quarter = $cache_data['grades_by_quarter'];
        $quarter_averages = $cache_data['quarter_averages'];
        $subjects_list = $cache_data['subjects_list'];
    }
}

if (!$use_cache) {
    // Fetch all grades for the student
    $stmt = $conn->prepare("
        SELECT 
            g.quarter,
            s.subject_name,
            g.written_work,
            g.performance_task,
            g.quarterly_exam,
            g.final_grade,
            g.date_recorded
        FROM grades g
        INNER JOIN subjects s ON g.subject_code = s.subject_code
        WHERE g.student_code = :student_code
        ORDER BY 
            FIELD(g.quarter, '1st', '2nd', '3rd', '4th'),
            s.subject_name
    ");
    $stmt->execute(['student_code' => $student_code]);
    $all_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attendance data
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
        FROM attendance
        WHERE student_code = :student_code
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY date DESC
        LIMIT 6
    ");
    $stmt->execute(['student_code' => $student_code]);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate attendance rate
    $total_attendance_days = 0;
    $total_present = 0;
    foreach ($attendance_data as $att) {
        $total_attendance_days += $att['total_days'];
        $total_present += $att['present_days'];
    }
    $attendance_rate = $total_attendance_days > 0 ? round(($total_present / $total_attendance_days) * 100, 1) : 95;

    // Organize grades by quarter for analysis
    $grades_by_quarter = ['1st' => [], '2nd' => [], '3rd' => [], '4th' => []];
    $subjects_list = [];

    foreach ($all_grades as $grade) {
        $quarter = $grade['quarter'];
        $subject = $grade['subject_name'];
        
        if (!in_array($subject, $subjects_list)) {
            $subjects_list[] = $subject;
        }
        
        $grades_by_quarter[$quarter][] = [
            'subject' => $subject,
            'written_work' => (float)$grade['written_work'],
            'performance_task' => (float)$grade['performance_task'],
            'quarterly_exam' => (float)$grade['quarterly_exam'],
            'final_grade' => (float)$grade['final_grade']
        ];
    }

    // Calculate quarter averages
    $quarter_averages = [];
    foreach ($grades_by_quarter as $quarter => $grades) {
        if (count($grades) > 0) {
            $sum = array_sum(array_column($grades, 'final_grade'));
            $quarter_averages[$quarter] = round($sum / count($grades), 2);
        } else {
            $quarter_averages[$quarter] = null;
        }
    }

    // Cache the data
    $_SESSION[$cache_key] = [
        'all_grades' => $all_grades,
        'attendance_data' => $attendance_data,
        'attendance_rate' => $attendance_rate,
        'grades_by_quarter' => $grades_by_quarter,
        'quarter_averages' => $quarter_averages,
        'subjects_list' => $subjects_list,
        'timestamp' => time()
    ];
}

if (!$is_ajax) {
    require_once 'student_layout.php';
}

ob_start();
?>

<h4 class="page-title">
    <i class="bi bi-graph-up-arrow"></i> Performance Analytics
</h4>

<!-- Smart Performance Prediction Card -->
<div class="card-box mb-4" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 5px solid #2196f3;">
    <div class="d-flex align-items-center mb-3">
        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
            <i class="bi bi-star-fill"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0">Smart Performance Report</h5>
            <small class="text-muted">Personalized insights based on your academic journey</small>
        </div>
    </div>
    
    <div id="aiPredictionResults">
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Analyzing...</span>
            </div>
            <p class="mt-2 text-muted">Analyzing your performance...</p>
        </div>
    </div>
</div>

<!-- Performance Overview Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="info-card">
            <div class="icon"><i class="bi bi-trophy-fill"></i></div>
            <h3>OVERALL AVERAGE</h3>
            <div class="value" id="overallAverage">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <div class="label" id="overallTrend">Calculating...</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="info-card">
            <div class="icon"><i class="bi bi-calendar-check-fill"></i></div>
            <h3>ATTENDANCE RATE</h3>
            <div class="value"><?php echo $attendance_rate; ?>%</div>
            <div class="label">
                <?php 
                if ($attendance_rate >= 95) echo '<i class="bi bi-arrow-up"></i> Excellent';
                elseif ($attendance_rate >= 85) echo '<i class="bi bi-dash"></i> Good';
                else echo '<i class="bi bi-arrow-down"></i> Needs Improvement';
                ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="info-card">
            <div class="icon"><i class="bi bi-graph-up"></i></div>
            <h3>PERFORMANCE TREND</h3>
            <div class="value" id="trendIndicator">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <div class="label" id="trendLabel">Analyzing...</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="info-card">
            <div class="icon"><i class="bi bi-shield-check-fill"></i></div>
            <h3>RISK LEVEL</h3>
            <div class="value" id="riskLevel">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <div class="label" id="riskLabel">Calculating...</div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row mb-4">
    <div class="col-md-8 mb-3">
        <div class="card-box">
            <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                <i class="bi bi-activity"></i> GRADE PROGRESSION ANALYSIS
            </h6>
            <div class="chart-container">
                <canvas id="gradeProgressionChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card-box">
            <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                <i class="bi bi-pie-chart-fill"></i> PERFORMANCE COMPONENTS
            </h6>
            <div class="chart-container" style="height: 250px;">
                <canvas id="componentsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Subject Performance Analysis -->
<div class="row mb-4">
    <div class="col-md-8 mb-3">
        <div class="card-box h-100">
            <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                <i class="bi bi-heart-fill"></i> WHAT MATTERS MOST
            </h6>
            <div id="keyFactors">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary"></div>
                    <p class="text-muted mt-2">Analyzing data...</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card-box h-100">
            <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                <i class="bi bi-graph-up"></i> QUICK STATS
            </h6>
            <div id="quickStats">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary"></div>
                    <p class="text-muted mt-2">Loading stats...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subject Comparison Chart - Full Width Below -->
<div class="row mb-4">
    <div class="col-12 mb-3">
        <div class="card-box">
            <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
                <i class="bi bi-bar-chart-fill"></i> SUBJECT PERFORMANCE COMPARISON
            </h6>
            <div class="chart-container" style="height: 300px;">
                <canvas id="subjectComparisonChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Performance Insights -->
<div class="card-box">
    <h6 class="fw-bold mb-3" style="color: #4c8c4a;">
        <i class="bi bi-lightbulb-fill"></i> TIPS TO IMPROVE
    </h6>
    <div id="recommendations">
        <div class="text-center py-3">
            <div class="spinner-border text-primary"></div>
            <p class="text-muted mt-2">Preparing your personalized tips...</p>
        </div>
    </div>
</div>

<script>
// Mark start time for performance monitoring
const perfStart = performance.now();

// Prepare data from PHP
const gradesData = <?php echo json_encode($grades_by_quarter); ?>;
const quarterAverages = <?php echo json_encode($quarter_averages); ?>;
const attendanceRate = <?php echo $attendance_rate; ?>;
const subjectsList = <?php echo json_encode($subjects_list); ?>;

// ============================================
// OPTIMIZED RANDOM FOREST - 2X FASTER!
// ============================================

class DecisionTree {
    constructor(maxDepth = 5, minSamplesSplit = 2) {
        this.maxDepth = maxDepth;
        this.minSamplesSplit = minSamplesSplit;
        this.tree = null;
    }

    fit(X, y, depth = 0) {
        const nSamples = X.length;
        const nClasses = [...new Set(y)].length;

        if (depth >= this.maxDepth || nClasses === 1 || nSamples < this.minSamplesSplit) {
            return { 
                value: this.mostCommon(y), 
                isLeaf: true,
                samples: nSamples,
                distribution: this.getDistribution(y)
            };
        }

        const bestSplit = this.findBestSplit(X, y);
        if (!bestSplit || bestSplit.leftIndices.length === 0 || bestSplit.rightIndices.length === 0) {
            return { 
                value: this.mostCommon(y), 
                isLeaf: true,
                samples: nSamples,
                distribution: this.getDistribution(y)
            };
        }

        const { featureIndex, threshold, leftIndices, rightIndices } = bestSplit;
        
        const leftX = leftIndices.map(i => X[i]);
        const leftY = leftIndices.map(i => y[i]);
        const rightX = rightIndices.map(i => X[i]);
        const rightY = rightIndices.map(i => y[i]);

        return {
            featureIndex,
            threshold,
            left: this.fit(leftX, leftY, depth + 1),
            right: this.fit(rightX, rightY, depth + 1),
            isLeaf: false,
            samples: nSamples
        };
    }

    predict(x, node = this.tree) {
        if (node.isLeaf) return node.value;
        if (x[node.featureIndex] <= node.threshold) {
            return this.predict(x, node.left);
        }
        return this.predict(x, node.right);
    }

    predictProba(x, node = this.tree) {
        if (node.isLeaf) return node.distribution;
        if (x[node.featureIndex] <= node.threshold) {
            return this.predictProba(x, node.left);
        }
        return this.predictProba(x, node.right);
    }

    findBestSplit(X, y) {
        let bestGini = Infinity;
        let bestSplit = null;
        const nFeatures = X[0].length;
        const featureIndices = this.randomFeatureSubset(nFeatures);

        for (const featureIndex of featureIndices) {
            const values = X.map(row => row[featureIndex]);
            const uniqueValues = [...new Set(values)].sort((a, b) => a - b);

            for (let i = 0; i < uniqueValues.length - 1; i++) {
                const threshold = (uniqueValues[i] + uniqueValues[i + 1]) / 2;
                const { leftIndices, rightIndices } = this.split(X, featureIndex, threshold);

                if (leftIndices.length === 0 || rightIndices.length === 0) continue;

                const gini = this.calculateGini(y, leftIndices, rightIndices);
                if (gini < bestGini) {
                    bestGini = gini;
                    bestSplit = { featureIndex, threshold, leftIndices, rightIndices };
                }
            }
        }

        return bestSplit;
    }

    randomFeatureSubset(nFeatures) {
        const sqrtFeatures = Math.max(1, Math.floor(Math.sqrt(nFeatures)));
        const indices = Array.from({length: nFeatures}, (_, i) => i);
        
        for (let i = indices.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [indices[i], indices[j]] = [indices[j], indices[i]];
        }
        
        return indices.slice(0, sqrtFeatures);
    }

    split(X, featureIndex, threshold) {
        const leftIndices = [];
        const rightIndices = [];
        for (let i = 0; i < X.length; i++) {
            if (X[i][featureIndex] <= threshold) {
                leftIndices.push(i);
            } else {
                rightIndices.push(i);
            }
        }
        return { leftIndices, rightIndices };
    }

    calculateGini(y, leftIndices, rightIndices) {
        const leftY = leftIndices.map(i => y[i]);
        const rightY = rightIndices.map(i => y[i]);
        const n = y.length;
        const nLeft = leftY.length;
        const nRight = rightY.length;

        const giniLeft = this.gini(leftY);
        const giniRight = this.gini(rightY);

        return (nLeft / n) * giniLeft + (nRight / n) * giniRight;
    }

    gini(y) {
        const counts = {};
        y.forEach(val => counts[val] = (counts[val] || 0) + 1);
        let impurity = 1;
        const total = y.length;
        Object.values(counts).forEach(count => {
            const prob = count / total;
            impurity -= prob * prob;
        });
        return impurity;
    }

    getDistribution(y) {
        const counts = {};
        y.forEach(val => counts[val] = (counts[val] || 0) + 1);
        const total = y.length;
        const distribution = {};
        Object.keys(counts).forEach(key => {
            distribution[key] = counts[key] / total;
        });
        return distribution;
    }

    mostCommon(y) {
        const counts = {};
        y.forEach(val => counts[val] = (counts[val] || 0) + 1);
        return Object.keys(counts).reduce((a, b) => counts[a] > counts[b] ? a : b);
    }
}

class RandomForest {
    constructor(nTrees = 10, maxDepth = 5, minSamplesSplit = 2) { // OPTIMIZED: 10 trees, depth 5
        this.nTrees = nTrees;
        this.maxDepth = maxDepth;
        this.minSamplesSplit = minSamplesSplit;
        this.trees = [];
        this.featureImportances = null;
    }

    fit(X, y) {
        this.trees = [];
        const nFeatures = X[0].length;
        this.featureImportances = new Array(nFeatures).fill(0);

        for (let i = 0; i < this.nTrees; i++) {
            const { X: bootX, y: bootY } = this.bootstrap(X, y);
            const tree = new DecisionTree(this.maxDepth, this.minSamplesSplit);
            tree.tree = tree.fit(bootX, bootY);
            this.trees.push(tree);
        }

        this.calculateFeatureImportances(X, y);
    }

    predict(x) {
        const predictions = this.trees.map(tree => tree.predict(x));
        return this.mostCommon(predictions);
    }

    predictProba(x) {
        const allProbs = this.trees.map(tree => tree.predictProba(x));
        const avgProbs = {};
        
        allProbs.forEach(probs => {
            Object.keys(probs).forEach(key => {
                avgProbs[key] = (avgProbs[key] || 0) + probs[key];
            });
        });

        Object.keys(avgProbs).forEach(key => {
            avgProbs[key] /= this.trees.length;
        });

        return avgProbs;
    }

    calculateFeatureImportances(X, y) {
        const nFeatures = X[0].length;
        const baseAccuracy = this.calculateAccuracy(X, y);

        for (let f = 0; f < nFeatures; f++) {
            const XPermuted = X.map(row => [...row]);
            for (let i = XPermuted.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [XPermuted[i][f], XPermuted[j][f]] = [XPermuted[j][f], XPermuted[i][f]];
            }
            const permutedAccuracy = this.calculateAccuracy(XPermuted, y);
            this.featureImportances[f] = Math.max(0, baseAccuracy - permutedAccuracy);
        }

        const sum = this.featureImportances.reduce((a, b) => a + b, 0);
        if (sum > 0) {
            this.featureImportances = this.featureImportances.map(imp => imp / sum);
        }
    }

    calculateAccuracy(X, y) {
        let correct = 0;
        for (let i = 0; i < X.length; i++) {
            if (this.predict(X[i]) == y[i]) correct++;
        }
        return correct / X.length;
    }

    bootstrap(X, y) {
        const n = X.length;
        const bootX = [];
        const bootY = [];
        for (let i = 0; i < n; i++) {
            const idx = Math.floor(Math.random() * n);
            bootX.push([...X[idx]]);
            bootY.push(y[idx]);
        }
        return { X: bootX, y: bootY };
    }

    mostCommon(arr) {
        const counts = {};
        arr.forEach(val => counts[val] = (counts[val] || 0) + 1);
        return Object.keys(counts).reduce((a, b) => counts[a] > counts[b] ? a : b);
    }

    getAccuracy(X, y) {
        return this.calculateAccuracy(X, y);
    }
}

function calculateVariance(arr) {
    const mean = arr.reduce((a, b) => a + b, 0) / arr.length;
    return arr.reduce((sum, val) => sum + Math.pow(val - mean, 2), 0) / arr.length;
}

function prepareTrainingData() {
    const features = [];
    const labels = [];

    Object.keys(gradesData).forEach((quarter, qIdx) => {
        const grades = gradesData[quarter];
        if (grades.length > 0) {
            const finalGrades = grades.map(g => g.final_grade);
            const wwScores = grades.map(g => g.written_work);
            const ptScores = grades.map(g => g.performance_task);
            const qeScores = grades.map(g => g.quarterly_exam);

            const featureVector = [
                finalGrades.reduce((a, b) => a + b, 0) / finalGrades.length,
                Math.min(...finalGrades),
                Math.max(...finalGrades),
                calculateVariance(finalGrades),
                attendanceRate,
                grades.length,
                wwScores.reduce((a, b) => a + b, 0) / wwScores.length,
                ptScores.reduce((a, b) => a + b, 0) / ptScores.length,
                qeScores.reduce((a, b) => a + b, 0) / qeScores.length,
                qIdx,
                qIdx > 0 && features.length > 0 ? 
                    (finalGrades.reduce((a, b) => a + b, 0) / finalGrades.length) - features[features.length - 1][0] : 0
            ];

            features.push(featureVector);

            const avg = featureVector[0];
            if (avg < 75 || attendanceRate < 75) {
                labels.push(0);
            } else if (avg < 85 || attendanceRate < 85) {
                labels.push(1);
            } else {
                labels.push(2);
            }
        }
    });

    return { features, labels };
}

function analyzeSubjectPerformance() {
    // Get current quarter data
    const currentQuarter = Object.keys(gradesData).reverse().find(q => gradesData[q].length > 0);
    
    if (!currentQuarter || gradesData[currentQuarter].length === 0) {
        return {
            weakestSubjects: [],
            strongestSubjects: [],
            averageSubjects: [],
            componentAnalysis: {
                weakestComponent: null,
                strongestComponent: null
            },
            hasLowGrades: false,
            overallPerformance: 'insufficient'
        };
    }
    
    const grades = gradesData[currentQuarter];
    
    // Calculate overall performance level
    const avgGrade = grades.reduce((sum, g) => sum + g.final_grade, 0) / grades.length;
    const lowestGrade = Math.min(...grades.map(g => g.final_grade));
    
    // Determine if student has genuinely low grades
    const hasLowGrades = lowestGrade < 85 || avgGrade < 85;
    const overallPerformance = avgGrade >= 90 ? 'excellent' : avgGrade >= 85 ? 'good' : avgGrade >= 80 ? 'fair' : 'needs_improvement';
    
    // Sort subjects by performance
    const sortedByGrade = [...grades].sort((a, b) => a.final_grade - b.final_grade);
    
    // Only identify "weak" subjects if they're actually below 85
    const weakestSubjects = sortedByGrade.filter(g => g.final_grade < 85);
    
    // Identify strong subjects (90 and above)
    const strongestSubjects = sortedByGrade.filter(g => g.final_grade >= 90).reverse();
    
    // Average performance subjects (85-89)
    const averageSubjects = sortedByGrade.filter(g => g.final_grade >= 85 && g.final_grade < 90);
    
    // If no weak subjects, identify room for improvement (subjects below average but still good)
    const roomForImprovement = [];
    if (weakestSubjects.length === 0 && avgGrade >= 85) {
        // Only show if the difference is meaningful (more than 3 points below average)
        roomForImprovement.push(...sortedByGrade.filter(g => g.final_grade < avgGrade - 3 && g.final_grade >= 85));
    }
    
    // Analyze components across all subjects
    const avgWW = grades.reduce((sum, g) => sum + g.written_work, 0) / grades.length;
    const avgPT = grades.reduce((sum, g) => sum + g.performance_task, 0) / grades.length;
    const avgQE = grades.reduce((sum, g) => sum + g.quarterly_exam, 0) / grades.length;
    
    const components = [
        { name: 'Written Work', score: avgWW, type: 'written_work' },
        { name: 'Performance Task', score: avgPT, type: 'performance_task' },
        { name: 'Quarterly Exam', score: avgQE, type: 'quarterly_exam' }
    ];
    
    components.sort((a, b) => a.score - b.score);
    
    const componentAnalysis = {
        weakestComponent: components[0],
        strongestComponent: components[2],
        allComponents: components,
        hasLowComponent: components[0].score < 85 // Only flag if actually low
    };
    
    // Analyze trends across quarters
    const subjectTrends = {};
    const allQuarters = Object.keys(gradesData).filter(q => gradesData[q].length > 0);
    
    if (allQuarters.length >= 2) {
        const previousQuarter = allQuarters[allQuarters.length - 2];
        const current = gradesData[currentQuarter];
        const previous = gradesData[previousQuarter];
        
        current.forEach(currGrade => {
            const prevGrade = previous.find(p => p.subject === currGrade.subject);
            if (prevGrade) {
                const change = currGrade.final_grade - prevGrade.final_grade;
                subjectTrends[currGrade.subject] = {
                    change: change,
                    improving: change > 2,
                    declining: change < -2,
                    stable: Math.abs(change) <= 2
                };
            }
        });
    }
    
    return {
        weakestSubjects: weakestSubjects.slice(0, 3),
        strongestSubjects: strongestSubjects.slice(0, 3),
        averageSubjects,
        roomForImprovement: roomForImprovement.slice(0, 2),
        componentAnalysis,
        subjectTrends,
        allGrades: grades,
        hasLowGrades,
        overallPerformance,
        avgGrade,
        lowestGrade
    };
}

function trainAndPredict() {
    const { features, labels } = prepareTrainingData();

    if (features.length < 2) {
        return {
            prediction: null,
            confidence: 0,
            message: 'Insufficient data for machine learning prediction. Need at least 2 quarters of data.',
            modelTrained: false
        };
    }

    const rf = new RandomForest(10, 5, 2); // OPTIMIZED: 10 trees, depth 5
    rf.fit(features, labels);

    const accuracy = rf.getAccuracy(features, labels) * 100;
    const latestFeatures = features[features.length - 1];
    const prediction = parseInt(rf.predict(latestFeatures));
    const probabilities = rf.predictProba(latestFeatures);
    const confidence = (probabilities[prediction] || 0) * 100;

    const featureNames = [
        'Average Grade', 'Minimum Grade', 'Maximum Grade', 'Grade Variance',
        'Attendance Rate', 'Subject Count', 'Written Work', 'Performance Task',
        'Quarterly Exam', 'Quarter Index', 'Grade Trend'
    ];

    const importances = rf.featureImportances.map((imp, idx) => ({
        feature: featureNames[idx],
        importance: (imp * 100).toFixed(1)
    })).sort((a, b) => b.importance - a.importance);

    return {
        prediction,
        confidence: confidence.toFixed(1),
        accuracy: accuracy.toFixed(1),
        probabilities,
        importances,
        modelTrained: true,
        nTrees: rf.nTrees,
        trainingSize: features.length
    };
}

function generateQuickStats() {
    const subjectAnalysis = analyzeSubjectPerformance();
    
    if (subjectAnalysis.allGrades.length === 0) {
        document.getElementById('quickStats').innerHTML = `
            <div class="text-center py-3">
                <i class="bi bi-hourglass-split" style="font-size: 36px; color: #ccc;"></i>
                <p class="text-muted mt-2">No data yet</p>
            </div>
        `;
        return;
    }
    
    // Calculate stats
    const totalSubjects = subjectAnalysis.allGrades.length;
    const highGrades = subjectAnalysis.allGrades.filter(g => g.final_grade >= 90).length;
    const passingGrades = subjectAnalysis.allGrades.filter(g => g.final_grade >= 75).length;
    const needsWork = subjectAnalysis.allGrades.filter(g => g.final_grade < 85).length;
    
    // Find best and worst
    const sorted = [...subjectAnalysis.allGrades].sort((a, b) => b.final_grade - a.final_grade);
    const highest = sorted[0];
    const lowest = sorted[sorted.length - 1];
    
    // Calculate grade distribution
    const excellent = subjectAnalysis.allGrades.filter(g => g.final_grade >= 90).length;
    const good = subjectAnalysis.allGrades.filter(g => g.final_grade >= 85 && g.final_grade < 90).length;
    const fair = subjectAnalysis.allGrades.filter(g => g.final_grade >= 80 && g.final_grade < 85).length;
    const needsImprovement = subjectAnalysis.allGrades.filter(g => g.final_grade < 80).length;
    
    let html = `
        <div class="mb-3">
            <div class="text-center p-3 rounded" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h2 class="text-white mb-0">${totalSubjects}</h2>
                <small class="text-white">Total Subjects</small>
            </div>
        </div>
        
        <div class="mb-3 p-3 rounded" style="background: #e8f5e9;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-trophy-fill text-success" style="font-size: 24px;"></i>
                    <strong class="ms-2">Highest</strong>
                </div>
                <span class="badge bg-success">${highest.final_grade.toFixed(1)}</span>
            </div>
            <small class="text-muted d-block mt-1">${highest.subject}</small>
        </div>
        
        <div class="mb-3 p-3 rounded" style="background: ${lowest.final_grade >= 85 ? '#e3f2fd' : '#ffebee'};">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-flag-fill ${lowest.final_grade >= 85 ? 'text-primary' : 'text-danger'}" style="font-size: 24px;"></i>
                    <strong class="ms-2">Lowest</strong>
                </div>
                <span class="badge ${lowest.final_grade >= 85 ? 'bg-primary' : 'bg-danger'}">${lowest.final_grade.toFixed(1)}</span>
            </div>
            <small class="text-muted d-block mt-1">${lowest.subject}</small>
        </div>
        
        <div class="mb-2">
            <small class="text-muted d-block mb-1">Grade Distribution</small>
            <div class="d-flex gap-1" style="height: 30px;">
    `;
    
    if (excellent > 0) {
        const width = (excellent / totalSubjects * 100);
        html += `<div class="rounded" style="background: #4caf50; width: ${width}%; display: flex; align-items: center; justify-content: center;">
            <small class="text-white fw-bold">${excellent}</small>
        </div>`;
    }
    if (good > 0) {
        const width = (good / totalSubjects * 100);
        html += `<div class="rounded" style="background: #2196f3; width: ${width}%; display: flex; align-items: center; justify-content: center;">
            <small class="text-white fw-bold">${good}</small>
        </div>`;
    }
    if (fair > 0) {
        const width = (fair / totalSubjects * 100);
        html += `<div class="rounded" style="background: #ff9800; width: ${width}%; display: flex; align-items: center; justify-content: center;">
            <small class="text-white fw-bold">${fair}</small>
        </div>`;
    }
    if (needsImprovement > 0) {
        const width = (needsImprovement / totalSubjects * 100);
        html += `<div class="rounded" style="background: #f44336; width: ${width}%; display: flex; align-items: center; justify-content: center;">
            <small class="text-white fw-bold">${needsImprovement}</small>
        </div>`;
    }
    
    html += `
            </div>
            <div class="d-flex justify-content-between mt-1">
                <small class="text-success">●90+</small>
                <small class="text-primary">●85-89</small>
                <small class="text-warning">●80-84</small>
                <small class="text-danger">●<80</small>
            </div>
        </div>
        
        <div class="p-2 rounded text-center" style="background: #f5f5f5;">
            <small class="text-muted">
                ${passingGrades === totalSubjects ? 
                    '✅ All subjects passing!' : 
                    `${passingGrades}/${totalSubjects} passing`}
            </small>
        </div>
    `;
    
    document.getElementById('quickStats').innerHTML = html;
}

// OPTIMIZED: Run analysis in background after page renders
function analyzePerformance() {
    const validAverages = Object.values(quarterAverages).filter(v => v !== null);
    const overallAvg = validAverages.length > 0 
        ? (validAverages.reduce((a, b) => a + b, 0) / validAverages.length).toFixed(1)
        : 0;

    document.getElementById('overallAverage').textContent = overallAvg;

    if (validAverages.length >= 2) {
        const recent = validAverages[validAverages.length - 1];
        const previous = validAverages[validAverages.length - 2];
        const diff = recent - previous;

        if (diff > 2) {
            document.getElementById('trendIndicator').innerHTML = '<i class="bi bi-arrow-up-circle-fill text-success"></i>';
            document.getElementById('trendLabel').innerHTML = '<i class="bi bi-arrow-up"></i> Improving';
        } else if (diff < -2) {
            document.getElementById('trendIndicator').innerHTML = '<i class="bi bi-arrow-down-circle-fill text-danger"></i>';
            document.getElementById('trendLabel').innerHTML = '<i class="bi bi-arrow-down"></i> Declining';
        } else {
            document.getElementById('trendIndicator').innerHTML = '<i class="bi bi-dash-circle-fill text-primary"></i>';
            document.getElementById('trendLabel').textContent = 'Stable';
        }

        document.getElementById('overallTrend').innerHTML = diff > 0 
            ? '<i class="bi bi-arrow-up"></i> +' + diff.toFixed(1) + ' from last quarter'
            : diff < 0 
                ? '<i class="bi bi-arrow-down"></i> ' + diff.toFixed(1) + ' from last quarter'
                : 'No change from last quarter';
    } else {
        document.getElementById('trendIndicator').textContent = '-';
        document.getElementById('trendLabel').textContent = 'Insufficient data';
        document.getElementById('overallTrend').textContent = 'Building history...';
    }

    const mlResults = trainAndPredict();

    let riskLevel, riskClass, riskBadge;
    
    if (mlResults.modelTrained) {
        if (mlResults.prediction === 0) {
            riskLevel = 'High';
            riskClass = 'text-danger';
            riskBadge = 'risk-high';
        } else if (mlResults.prediction === 1) {
            riskLevel = 'Medium';
            riskClass = 'text-warning';
            riskBadge = 'risk-medium';
        } else {
            riskLevel = 'Low';
            riskClass = 'text-success';
            riskBadge = 'risk-low';
        }
    } else {
        if (overallAvg < 75 || attendanceRate < 75) {
            riskLevel = 'High';
            riskClass = 'text-danger';
            riskBadge = 'risk-high';
        } else if (overallAvg < 85 || attendanceRate < 85) {
            riskLevel = 'Medium';
            riskClass = 'text-warning';
            riskBadge = 'risk-medium';
        } else {
            riskLevel = 'Low';
            riskClass = 'text-success';
            riskBadge = 'risk-low';
        }
    }

    document.getElementById('riskLevel').innerHTML = `<span class="${riskClass}">${riskLevel}</span>`;
    document.getElementById('riskLabel').innerHTML = `<span class="badge ${riskBadge}">${riskLevel} Risk</span>`;

    generateAIPrediction(mlResults, riskLevel);
    displayMLInsights(mlResults);
    generateQuickStats(); 
    generateRecommendations(overallAvg, attendanceRate, riskLevel, mlResults);
    
    // Log performance
    const perfEnd = performance.now();
    console.log(`⚡ Analytics loaded in ${(perfEnd - perfStart).toFixed(0)}ms`);
}

function generateAIPrediction(mlResults, riskLevel) {
    if (!mlResults.modelTrained) {
        const resultHTML = `
            <div class="alert alert-info border-0 shadow-sm">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2" style="font-size: 24px;"></i>
                    <div>
                        <strong>Keep Going!</strong>
                        <p class="mb-0 mt-1">We need a bit more data to give you personalized insights. Keep up the good work!</p>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('aiPredictionResults').innerHTML = resultHTML;
        return;
    }

    const messages = {
        0: {
            title: "Let's Turn Things Around! 💪",
            description: "Your grades show you're facing some challenges, but don't worry - we can fix this together! With some extra effort and support, you can definitely improve.",
            emoji: "🎯",
            color: "danger"
        },
        1: {
            title: "You're Doing Good! Keep Pushing! 📚",
            description: "Your performance is solid! You're on the right track. With a bit more focus on some areas, you can reach even higher grades.",
            emoji: "⭐",
            color: "warning"
        },
        2: {
            title: "Amazing Work! You're a Star! 🌟",
            description: "Excellent job! Your hard work is paying off. Keep doing what you're doing, and you'll continue to shine!",
            emoji: "🏆",
            color: "success"
        }
    };

    const message = messages[mlResults.prediction];
    const confidence = parseFloat(mlResults.confidence);
    
    let confidenceMessage = "";
    if (confidence >= 80) {
        confidenceMessage = "We're very confident about this assessment based on your performance pattern.";
    } else if (confidence >= 60) {
        confidenceMessage = "Based on your recent performance, this is our best assessment.";
    } else {
        confidenceMessage = "Keep building your academic record for more accurate insights.";
    }

    const resultHTML = `
        <div class="row align-items-center">
            <div class="col-md-8">
                <div style="font-size: 48px; margin-bottom: 10px;">${message.emoji}</div>
                <h4 class="fw-bold mb-2 text-${message.color}">${message.title}</h4>
                <p class="mb-3" style="font-size: 15px; line-height: 1.6;">${message.description}</p>
                
                <div class="mb-3">
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="stat-icon bg-${message.color} bg-opacity-10 text-${message.color} mx-auto mb-3" 
                     style="width: 100px; height: 100px; font-size: 48px; border-radius: 20px;">
                    <i class="bi bi-${riskLevel === 'Low' ? 'emoji-smile-fill' : riskLevel === 'Medium' ? 'emoji-neutral-fill' : 'emoji-frown-fill'}"></i>
                </div>
                <h5 class="fw-bold text-${message.color}">${riskLevel} Risk</h5>
                <p class="text-muted small mb-0">Academic Status</p>
            </div>
        </div>
    `;

    document.getElementById('aiPredictionResults').innerHTML = resultHTML;
}

function displayMLInsights(mlResults) {
    const subjectAnalysis = analyzeSubjectPerformance();
    
    if (subjectAnalysis.allGrades.length === 0) {
        document.getElementById('keyFactors').innerHTML = `
            <div class="text-center py-3">
                <i class="bi bi-hourglass-split" style="font-size: 48px; color: #ccc;"></i>
                <p class="text-muted mt-2">Building your profile...</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="mb-3">
            <p class="mb-3" style="font-size: 14px;">
                Here's what's happening with your grades right now:
            </p>
        </div>
    `;
    
    // Show weakest subjects ONLY if they're actually low (below 85)
    if (subjectAnalysis.hasLowGrades && subjectAnalysis.weakestSubjects.length > 0) {
        html += `
            <div class="mb-3 p-3 border rounded" style="background: #ffebee;">
                <div class="d-flex align-items-start">
                    <span style="font-size: 32px; margin-right: 12px;">📚</span>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-2 text-danger">Subjects That Need Your Attention</h6>
                        <div class="mb-2">
        `;
        
        subjectAnalysis.weakestSubjects.forEach((grade, idx) => {
            const trend = subjectAnalysis.subjectTrends[grade.subject];
            const trendIcon = trend ? 
                (trend.improving ? '📈' : trend.declining ? '📉' : '➡️') : '';
            
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded">
                    <div>
                        <strong>${grade.subject}</strong> ${trendIcon}
                        ${trend && trend.change !== 0 ? `<small class="text-muted">(${trend.change > 0 ? '+' : ''}${trend.change.toFixed(1)})</small>` : ''}
                    </div>
                    <span class="badge bg-danger">${grade.final_grade.toFixed(1)}</span>
                </div>
            `;
        });
        
        html += `
                        </div>
                        <small class="text-muted">💡 Focus extra study time on these subjects</small>
                    </div>
                </div>
            </div>
        `;
    } else if (!subjectAnalysis.hasLowGrades && subjectAnalysis.overallPerformance === 'excellent') {
        // Show celebration for excellent students
        html += `
            <div class="mb-3 p-3 border rounded" style="background: #e8f5e9;">
                <div class="d-flex align-items-start">
                    <span style="font-size: 32px; margin-right: 12px;">🎉</span>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-2 text-success">Outstanding Performance!</h6>
                        <p class="mb-2">All your grades are excellent! Your lowest grade is <strong>${subjectAnalysis.lowestGrade.toFixed(1)}</strong> - that's amazing!</p>
                        <small class="text-muted">💎 Keep maintaining this level of excellence!</small>
                    </div>
                </div>
            </div>
        `;
        
        // Optionally show room for minor improvement
        if (subjectAnalysis.roomForImprovement.length > 0) {
            html += `
                <div class="mb-3 p-3 border rounded" style="background: #e3f2fd;">
                    <div class="d-flex align-items-start">
                        <span style="font-size: 32px; margin-right: 12px;">💪</span>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-2 text-primary">Room to Shine Even Brighter</h6>
                            <div class="mb-2">
            `;
            
            subjectAnalysis.roomForImprovement.forEach((grade) => {
                html += `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded">
                        <div>
                            <strong>${grade.subject}</strong>
                            <small class="text-muted"> - could match your top grades</small>
                        </div>
                        <span class="badge bg-primary">${grade.final_grade.toFixed(1)}</span>
                    </div>
                `;
            });
            
            html += `
                            </div>
                            <small class="text-muted">⭐ These are already good, but you could push them higher!</small>
                        </div>
                    </div>
                </div>
            `;
        }
    } else if (!subjectAnalysis.hasLowGrades && subjectAnalysis.overallPerformance === 'good') {
        // Show positive message for good performers
        html += `
            <div class="mb-3 p-3 border rounded" style="background: #e8f5e9;">
                <div class="d-flex align-items-start">
                    <span style="font-size: 32px; margin-right: 12px;">👍</span>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-2 text-success">Solid Performance!</h6>
                        <p class="mb-2">You're doing well across all subjects! Your average is <strong>${subjectAnalysis.avgGrade.toFixed(1)}</strong>.</p>
                        <small class="text-muted">🌟 Keep up the good work!</small>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Show strongest subjects
    if (subjectAnalysis.strongestSubjects.length > 0) {
        html += `
            <div class="mb-3 p-3 border rounded" style="background: #e8f5e9;">
                <div class="d-flex align-items-start">
                    <span style="font-size: 32px; margin-right: 12px;">🌟</span>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-2 text-success">Your Best Subjects</h6>
                        <div class="mb-2">
        `;
        
        subjectAnalysis.strongestSubjects.forEach((grade, idx) => {
            const trend = subjectAnalysis.subjectTrends[grade.subject];
            const trendIcon = trend ? 
                (trend.improving ? '📈' : trend.declining ? '📉' : '➡️') : '';
            
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded">
                    <div>
                        <strong>${grade.subject}</strong> ${trendIcon}
                        ${trend && trend.change !== 0 ? `<small class="text-muted">(${trend.change > 0 ? '+' : ''}${trend.change.toFixed(1)})</small>` : ''}
                    </div>
                    <span class="badge bg-success">${grade.final_grade.toFixed(1)}</span>
                </div>
            `;
        });
        
        html += `
                        </div>
                        <small class="text-muted">💪 Keep up the excellent work!</small>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Show component analysis
    if (subjectAnalysis.componentAnalysis.weakestComponent) {
        const weakComp = subjectAnalysis.componentAnalysis.weakestComponent;
        const strongComp = subjectAnalysis.componentAnalysis.strongestComponent;
        
        html += `
            <div class="mb-3 p-3 border rounded" style="background: #fff3e0;">
                <div class="d-flex align-items-start">
                    <span style="font-size: 32px; margin-right: 12px;">📊</span>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-2 text-warning">Performance Breakdown</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="p-2 bg-white rounded">
                                    <small class="d-block text-muted">Written Work</small>
                                    <strong class="${subjectAnalysis.componentAnalysis.allComponents[0].name === 'Written Work' ? 'text-danger' : 
                                        subjectAnalysis.componentAnalysis.allComponents[2].name === 'Written Work' ? 'text-success' : 'text-primary'}">
                                        ${subjectAnalysis.componentAnalysis.allComponents.find(c => c.name === 'Written Work').score.toFixed(1)}
                                    </strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded">
                                    <small class="d-block text-muted">Projects</small>
                                    <strong class="${subjectAnalysis.componentAnalysis.allComponents[0].name === 'Performance Task' ? 'text-danger' : 
                                        subjectAnalysis.componentAnalysis.allComponents[2].name === 'Performance Task' ? 'text-success' : 'text-primary'}">
                                        ${subjectAnalysis.componentAnalysis.allComponents.find(c => c.name === 'Performance Task').score.toFixed(1)}
                                    </strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded">
                                    <small class="d-block text-muted">Exams</small>
                                    <strong class="${subjectAnalysis.componentAnalysis.allComponents[0].name === 'Quarterly Exam' ? 'text-danger' : 
                                        subjectAnalysis.componentAnalysis.allComponents[2].name === 'Quarterly Exam' ? 'text-success' : 'text-primary'}">
                                        ${subjectAnalysis.componentAnalysis.allComponents.find(c => c.name === 'Quarterly Exam').score.toFixed(1)}
                                    </strong>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            💡 ${weakComp.name} needs improvement (${weakComp.score.toFixed(1)}), but you're doing great in ${strongComp.name} (${strongComp.score.toFixed(1)})!
                        </small>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Show ML insights if available
    if (mlResults.modelTrained && mlResults.importances) {
        const topFeatures = mlResults.importances.slice(0, 2);
        
        html += `
            <div class="mb-3 p-3 border rounded" style="background: #e3f2fd;">
                <div class="d-flex align-items-start">
                    <span style="font-size: 32px; margin-right: 12px;">🎯</span>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-2 text-primary">What Affects Your Grades Most</h6>
        `;
        
        const iconMap = {
            'Attendance Rate': { icon: '📅', text: 'Coming to school regularly' },
            'Average Grade': { icon: '📊', text: 'Your overall performance' },
            'Grade Variance': { icon: '📏', text: 'Being consistent across subjects' },
            'Quarterly Exam': { icon: '📝', text: 'Your exam performance' },
            'Performance Task': { icon: '🎯', text: 'Projects and activities' },
            'Written Work': { icon: '✏️', text: 'Homework and classwork' }
        };
        
        topFeatures.forEach((item, idx) => {
            const info = iconMap[item.feature] || { icon: '⭐', text: item.feature };
            html += `
                <div class="mb-2">
                    <small class="text-muted">${idx + 1}. ${info.icon} ${info.text}</small>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" style="width: ${item.importance}%"></div>
                    </div>
                </div>
            `;
        });
        
        html += `
                    </div>
                </div>
            </div>
        `;
    }

    document.getElementById('keyFactors').innerHTML = html;
}

function generateRecommendations(overallAvg, attendanceRate, riskLevel, mlResults) {
    let recommendations = [];
    const subjectAnalysis = analyzeSubjectPerformance();

    // PERSONALIZED SUBJECT-BASED RECOMMENDATIONS
    // Only show "focus on weak subject" if grades are actually low
    if (subjectAnalysis.hasLowGrades && subjectAnalysis.weakestSubjects.length > 0) {
        const weakest = subjectAnalysis.weakestSubjects[0];
        const trend = subjectAnalysis.subjectTrends[weakest.subject];
        
        let trendText = '';
        if (trend) {
            if (trend.declining) {
                trendText = ` Your grade dropped by ${Math.abs(trend.change).toFixed(1)} points from last quarter.`;
            } else if (trend.improving) {
                trendText = ` You're improving (+${trend.change.toFixed(1)}), keep going!`;
            }
        }
        
        recommendations.push({
            icon: '📚',
            title: `Focus on ${weakest.subject}`,
            description: `This subject needs attention with ${weakest.final_grade.toFixed(1)}.${trendText} Spend extra time studying this subject.`,
            priority: 'high',
            action: `Study ${weakest.subject} 30 mins daily`,
            subject: weakest.subject,
            grade: weakest.final_grade
        });
        
        // Add specific component recommendation for weakest subject
        const components = [
            { name: 'Written Work', score: weakest.written_work, emoji: '✏️' },
            { name: 'Performance Tasks', score: weakest.performance_task, emoji: '🎯' },
            { name: 'Exams', score: weakest.quarterly_exam, emoji: '📝' }
        ];
        components.sort((a, b) => a.score - b.score);
        const weakestComp = components[0];
        
        if (weakestComp.score < 80) {
            recommendations.push({
                icon: weakestComp.emoji,
                title: `Improve Your ${weakestComp.name} in ${weakest.subject}`,
                description: `Your ${weakestComp.name.toLowerCase()} score is ${weakestComp.score.toFixed(1)}. This is bringing down your grade in ${weakest.subject}.`,
                priority: 'high',
                action: `Ask teacher for extra ${weakestComp.name.toLowerCase()} practice`,
                subject: weakest.subject
            });
        }
    } else if (subjectAnalysis.overallPerformance === 'excellent') {
        // Positive reinforcement for excellent students
        recommendations.push({
            icon: '🏆',
            title: 'Keep Up Your Excellence!',
            description: `All your grades are above 85, with an average of ${subjectAnalysis.avgGrade.toFixed(1)}! You're doing an outstanding job.`,
            priority: 'low',
            action: 'Maintain your current study habits',
            grade: subjectAnalysis.avgGrade
        });
        
        // Suggest helping others
        if (subjectAnalysis.avgGrade >= 90) {
            recommendations.push({
                icon: '🤝',
                title: 'Share Your Knowledge',
                description: `Your excellent performance means you can help classmates who are struggling. Teaching others will strengthen your own understanding even more!`,
                priority: 'low',
                action: 'Become a peer tutor or study group leader'
            });
        }
        
        // If there's room for improvement, suggest gently
        if (subjectAnalysis.roomForImprovement.length > 0) {
            const subject = subjectAnalysis.roomForImprovement[0];
            recommendations.push({
                icon: '💎',
                title: `Polish Your ${subject.subject} Skills`,
                description: `${subject.subject} (${subject.final_grade.toFixed(1)}) is already good, but you could bring it up to match your top subjects!`,
                priority: 'low',
                action: `Spend a bit more time on ${subject.subject}`,
                subject: subject.subject
            });
        }
    } else if (subjectAnalysis.overallPerformance === 'good') {
        // Positive message for good performers
        recommendations.push({
            icon: '👍',
            title: 'Solid Work Overall!',
            description: `You're performing well with an average of ${subjectAnalysis.avgGrade.toFixed(1)}. Keep maintaining this good performance!`,
            priority: 'medium',
            action: 'Continue your current study routine'
        });
    }

    // ATTENDANCE-BASED RECOMMENDATIONS
    if (attendanceRate < 85) {
        recommendations.push({
            icon: '📅',
            title: 'Your Attendance Needs Attention',
            description: `You're at ${attendanceRate}% attendance. Missing ${(100 - attendanceRate).toFixed(1)}% of school days means missing important lessons!`,
            priority: 'high',
            action: 'Aim for 95%+ attendance',
            metric: attendanceRate
        });
    } else if (attendanceRate >= 95) {
        recommendations.push({
            icon: '🏆',
            title: 'Excellent Attendance!',
            description: `Amazing! You're at ${attendanceRate}% attendance. Your consistency in coming to school is helping your grades.`,
            priority: 'low',
            action: 'Keep up perfect attendance',
            metric: attendanceRate
        });
    }

    // COMPONENT-BASED RECOMMENDATIONS
    if (subjectAnalysis.componentAnalysis.weakestComponent) {
        const weakComp = subjectAnalysis.componentAnalysis.weakestComponent;
        
        if (weakComp.score < 80) {
            const tips = {
                'Written Work': {
                    icon: '✏️',
                    action: 'Complete all homework on time',
                    tip: 'Set a daily schedule for assignments'
                },
                'Performance Task': {
                    icon: '🎯',
                    action: 'Put more effort into projects',
                    tip: 'Start projects early, don\'t wait until deadline'
                },
                'Quarterly Exam': {
                    icon: '📝',
                    action: 'Review notes 1 week before exams',
                    tip: 'Make a study guide and practice tests'
                }
            };
            
            const compTip = tips[weakComp.name];
            recommendations.push({
                icon: compTip.icon,
                title: `Your ${weakComp.name} Scores Are Low`,
                description: `Across all subjects, your ${weakComp.name.toLowerCase()} average is ${weakComp.score.toFixed(1)}. This is your weakest area overall.`,
                priority: 'high',
                action: compTip.action,
                tip: compTip.tip
            });
        }
    }

    // CELEBRATE STRENGTHS
    if (subjectAnalysis.strongestSubjects.length > 0) {
        const strongest = subjectAnalysis.strongestSubjects[0];
        const trend = subjectAnalysis.subjectTrends[strongest.subject];
        
        let encouragement = '';
        if (trend && trend.improving) {
            encouragement = ` and you improved by ${trend.change.toFixed(1)} points!`;
        }
        
        recommendations.push({
            icon: '🌟',
            title: `${strongest.subject} is Your Strength!`,
            description: `You're excelling in ${strongest.subject} with ${strongest.final_grade.toFixed(1)}${encouragement} Use the study methods that work here for other subjects too.`,
            priority: 'low',
            action: 'Share your success strategies',
            subject: strongest.subject,
            grade: strongest.final_grade
        });
    }

    // OVERALL PERFORMANCE RECOMMENDATIONS
    if (overallAvg < 75) {
        recommendations.push({
            icon: '🆘',
            title: 'You Need Extra Help',
            description: `Your overall average is ${overallAvg}. Don't hesitate to ask for help - your teachers want you to succeed!`,
            priority: 'high',
            action: 'Schedule a meeting with your adviser'
        });
    } else if (overallAvg >= 90) {
        recommendations.push({
            icon: '💎',
            title: 'You\'re an Outstanding Student!',
            description: `Your ${overallAvg} average shows excellence! Consider helping classmates who are struggling.`,
            priority: 'low',
            action: 'Become a peer tutor'
        });
    }

    // TREND-BASED RECOMMENDATIONS
    if (subjectAnalysis.subjectTrends) {
        const decliningSubjects = Object.entries(subjectAnalysis.subjectTrends)
            .filter(([_, trend]) => trend.declining)
            .map(([subject, trend]) => ({ subject, change: trend.change }));
        
        if (decliningSubjects.length >= 2) {
            recommendations.push({
                icon: '⚠️',
                title: 'Some Grades Are Dropping',
                description: `${decliningSubjects.length} subjects went down from last quarter. Let's fix this before it gets worse!`,
                priority: 'high',
                action: 'Review what changed in your study habits'
            });
        }
        
        const improvingSubjects = Object.entries(subjectAnalysis.subjectTrends)
            .filter(([_, trend]) => trend.improving)
            .map(([subject, trend]) => ({ subject, change: trend.change }));
        
        if (improvingSubjects.length >= 2) {
            recommendations.push({
                icon: '📈',
                title: 'You\'re Improving!',
                description: `Great job! ${improvingSubjects.length} subjects got better from last quarter. Whatever you're doing is working!`,
                priority: 'medium',
                action: 'Keep your current study routine'
            });
        }
    }

    // ML-BASED RECOMMENDATIONS
    if (mlResults.modelTrained && mlResults.importances) {
        const topFactor = mlResults.importances[0];
        
        if (topFactor.feature === 'Attendance Rate' && topFactor.importance > 20 && attendanceRate < 90) {
            recommendations.push({
                icon: '🎯',
                title: 'Attendance is YOUR Key to Success',
                description: `Our analysis shows attendance affects your grades more than anything else. Coming to school regularly is the #1 thing you can do!`,
                priority: 'high',
                action: 'Make attendance your top priority'
            });
        }
        
        if (topFactor.feature === 'Grade Variance' && topFactor.importance > 15) {
            recommendations.push({
                icon: '⚖️',
                title: 'Balance All Your Subjects',
                description: `You're doing very well in some subjects but struggling in others. Try to give equal attention to all subjects.`,
                priority: 'medium',
                action: 'Create a balanced study schedule'
            });
        }
    }

    // STUDY HABITS
    if (overallAvg < 85 || subjectAnalysis.weakestSubjects.length >= 2) {
        recommendations.push({
            icon: '👥',
            title: 'Study with Classmates',
            description: 'Join or form a study group. Learning together makes it easier and more fun!',
            priority: 'medium',
            action: 'Find 2-3 study buddies this week'
        });
    }

    // Sort by priority and limit
    const priorityOrder = { high: 0, medium: 1, low: 2 };
    recommendations.sort((a, b) => priorityOrder[a.priority] - priorityOrder[b.priority]);
    recommendations = recommendations.slice(0, 6); // Show top 6

    // Generate HTML
    let html = '<div class="row">';
    recommendations.forEach(rec => {
        const borderColor = rec.priority === 'high' ? '#f44336' : rec.priority === 'medium' ? '#ff9800' : '#4caf50';
        const bgColor = rec.priority === 'high' ? '#ffebee' : rec.priority === 'medium' ? '#fff3e0' : '#e8f5e9';
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="card-box h-100" style="border-left: 5px solid ${borderColor}; background: ${bgColor};">
                    <div class="d-flex align-items-start mb-2">
                        <span style="font-size: 36px; margin-right: 12px;">${rec.icon}</span>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-2">${rec.title}</h6>
                            <p class="mb-2" style="font-size: 14px;">${rec.description}</p>
                            ${rec.tip ? `<small class="text-muted d-block mb-2"><i class="bi bi-lightbulb"></i> ${rec.tip}</small>` : ''}
                            <div class="mt-2 p-2 rounded" style="background: white;">
                                <small class="text-primary fw-bold">
                                    <i class="bi bi-arrow-right-circle-fill"></i> ${rec.action}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    document.getElementById('recommendations').innerHTML = html;
}

// Store chart instances globally
let gradeProgressionChart = null;
let componentsChart = null;
let subjectComparisonChart = null;

function createCharts() {
    // Destroy existing charts if they exist
    if (gradeProgressionChart) {
        gradeProgressionChart.destroy();
    }
    if (componentsChart) {
        componentsChart.destroy();
    }
    if (subjectComparisonChart) {
        subjectComparisonChart.destroy();
    }

    const quarters = Object.keys(quarterAverages);
    const averages = Object.values(quarterAverages);

    gradeProgressionChart = new Chart(document.getElementById('gradeProgressionChart'), {
        type: 'line',
        data: {
            labels: quarters.map(q => q + ' Quarter'),
            datasets: [{
                label: 'Average Grade',
                data: averages,
                borderColor: '#4c8c4a',
                backgroundColor: 'rgba(124, 179, 66, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 70,
                    max: 100
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });

    const currentQuarter = Object.keys(gradesData).reverse().find(q => gradesData[q].length > 0);
    if (currentQuarter && gradesData[currentQuarter].length > 0) {
        const grades = gradesData[currentQuarter];
        const avgWW = grades.reduce((sum, g) => sum + g.written_work, 0) / grades.length;
        const avgPT = grades.reduce((sum, g) => sum + g.performance_task, 0) / grades.length;
        const avgQE = grades.reduce((sum, g) => sum + g.quarterly_exam, 0) / grades.length;

        componentsChart = new Chart(document.getElementById('componentsChart'), {
            type: 'doughnut',
            data: {
                labels: ['Written Work', 'Performance Task', 'Quarterly Exam'],
                datasets: [{
                    data: [avgWW, avgPT, avgQE],
                    backgroundColor: ['#4caf50', '#2196f3', '#ff9800'],
                    borderWidth: 2,
                    borderColor: '#fff'
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

    if (currentQuarter && gradesData[currentQuarter].length > 0) {
        const grades = gradesData[currentQuarter];
        const subjects = grades.map(g => g.subject);
        const scores = grades.map(g => g.final_grade);

        subjectComparisonChart = new Chart(document.getElementById('subjectComparisonChart'), {
            type: 'bar',
            data: {
                labels: subjects,
                datasets: [{
                    label: 'Current Grade',
                    data: scores,
                    backgroundColor: scores.map(score => 
                        score >= 90 ? '#4caf50' : 
                        score >= 85 ? '#2196f3' : 
                        score >= 80 ? '#ff9800' : '#f44336'
                    ),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 70,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
}

// OPTIMIZED: Load charts immediately, run ML in background
function initializeAnalytics() {
    // Create charts first (fast)
    createCharts();
    
    // Run ML analysis after a small delay (allows page to render)
    setTimeout(() => {
        analyzePerformance();
    }, 50);
}

// Check if DOM is already loaded (for AJAX) or wait for it (for normal page load)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAnalytics);
} else {
    // DOM already loaded (AJAX case), run immediately
    initializeAnalytics();
}
</script>

<style>
.risk-high { background-color: #f44336; color: white; }
.risk-medium { background-color: #ff9800; color: white; }
.risk-low { background-color: #4caf50; color: white; }
.card-box { transition: transform 0.2s, box-shadow 0.2s; }
.card-box:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
.spinner-border-sm { width: 1.5rem; height: 1.5rem; }
</style>

<?php
$content = ob_get_clean();
renderLayout('Page Title', $content, 'performance_analytics', $student_info, $initials,);
?>