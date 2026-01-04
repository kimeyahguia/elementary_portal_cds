<?php
require_once 'student_header.php';
require_once 'student_layout.php';

// Check if this is an AJAX request for filtering only (not SPA navigation)
$is_filter_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['filter']);

// Check if this is an SPA navigation request
$is_spa_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1' && !isset($_GET['filter']);

// Get filter parameters
$filter_category = isset($_GET['category']) ? $_GET['category'] : 'all';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$filter_date = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';

// Build the WHERE clause based on filters
$where_conditions = ["target_audience IN ('all', 'students')", "is_active = TRUE"];
$params = [];

if ($filter_category != 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $filter_category;
}

if ($filter_priority != 'all') {
    $where_conditions[] = "priority = ?";
    $params[] = $filter_priority;
}

// Date range filter
if ($filter_date != 'all') {
    switch ($filter_date) {
        case 'today':
            $where_conditions[] = "DATE(date_posted) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "date_posted >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "date_posted >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch announcements from database
$stmt = $conn->prepare("
    SELECT 
        title,
        content,
        DATE_FORMAT(date_posted, '%M %d, %Y') as date_posted,
        category,
        priority
    FROM announcements
    WHERE $where_clause
    ORDER BY 
        FIELD(priority, 'high', 'medium', 'low'),
        date_posted DESC
    LIMIT 10
");
$stmt->execute($params);
$db_announcements = $stmt->fetchAll();

// Prepare announcements data
$announcements = [];
foreach ($db_announcements as $announcement) {
    $announcements[] = [
        'title' => $announcement['title'],
        'date' => $announcement['date_posted'],
        'category' => $announcement['category'] ?? 'Information',
        'content' => $announcement['content'],
        'priority' => $announcement['priority'] ?? 'medium'
    ];
}

// Fallback to static announcements if database is empty (only when no filters applied)
if (empty($announcements) && $filter_category == 'all' && $filter_priority == 'all' && $filter_date == 'all') {
    $announcements = [
        [
            'title' => 'Christmas Party Celebration',
            'date' => 'November 10, 2025',
            'category' => 'Event',
            'content' => 'Join us for our annual Christmas Party on December 20, 2025. Students are encouraged to wear their costumes and participate in various activities.',
            'priority' => 'high'
        ],
        [
            'title' => 'Report Card Distribution',
            'date' => 'November 08, 2025',
            'category' => 'Academic',
            'content' => 'Report cards for the 2nd Quarter will be distributed on November 25, 2025. Parents are requested to check the grades and sign the cards.',
            'priority' => 'medium'
        ],
    ];
}

// Fetch available categories for filter dropdown
$cat_stmt = $conn->prepare("
    SELECT DISTINCT category 
    FROM announcements 
    WHERE target_audience IN ('all', 'students') 
    AND is_active = TRUE 
    AND category IS NOT NULL
    ORDER BY category
");
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate announcements HTML
function generateAnnouncementsHTML($announcements) {
    ob_start();
    ?>
    <?php if (empty($announcements)): ?>
        <div class="no-results">
            <i class="bi bi-inbox" style="font-size: 3rem; color: #dee2e6;"></i>
            <h5 class="mt-3">No announcements found</h5>
            <p>Try adjusting your filters to see more results.</p>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
            <div class="card-box announcement-card mb-3 priority-<?php echo $announcement['priority']; ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                        <div>
                            <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($announcement['category']); ?></span>
                            <?php if ($announcement['priority'] == 'high'): ?>
                                <span class="badge bg-danger">Important</span>
                            <?php elseif ($announcement['priority'] == 'medium'): ?>
                                <span class="badge bg-warning text-dark">Medium</span>
                            <?php else: ?>
                                <span class="badge bg-success">Low</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="bi bi-calendar3"></i> <?php echo $announcement['date']; ?>
                    </small>
                </div>
                <p class="mb-0"><?php echo htmlspecialchars($announcement['content']); ?></p>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($announcements) >= 10): ?>
            <div class="text-center mt-4">
                <button class="btn btn-outline-success" onclick="loadMoreAnnouncements()">
                    <i class="bi bi-arrow-clockwise"></i> Load More Announcements
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// If filter AJAX request, return only the announcements list
if ($is_filter_ajax) {
    echo generateAnnouncementsHTML($announcements);
    exit;
}

// Continue with full page render for normal requests and SPA navigation
ob_start();
?>

<style>
.filter-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-group {
    margin-bottom: 15px;
}

.filter-group label {
    font-weight: 600;
    margin-bottom: 5px;
    display: block;
    color: #495057;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.announcement-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.announcement-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.priority-high {
    border-left: 4px solid #dc3545;
}

.priority-medium {
    border-left: 4px solid #ffc107;
}

.priority-low {
    border-left: 4px solid #28a745;
}

.no-results {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.active-filter {
    background-color: #198754 !important;
    color: white !important;
    border-color: #198754 !important;
}

.loading-spinner {
    display: none;
    text-align: center;
    padding: 20px;
}

.loading-spinner.show {
    display: block;
}

#announcementsList {
    min-height: 200px;
    position: relative;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">School Announcements</h4>
    <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
        <i class="bi bi-arrow-clockwise"></i> Reset Filters
    </button>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="row">
        <div class="col-md-4">
            <div class="filter-group">
                <label><i class="bi bi-tag"></i> Category</label>
                <select class="form-select" id="categoryFilter">
                    <option value="all" <?php echo $filter_category == 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php 
                    $default_categories = ['Academic', 'Event', 'Information', 'Administrative', 'Holiday'];
                    $all_categories = array_unique(array_merge($categories, $default_categories));
                    foreach ($all_categories as $cat): 
                    ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $filter_category == $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="filter-group">
                <label><i class="bi bi-exclamation-circle"></i> Priority</label>
                <select class="form-select" id="priorityFilter">
                    <option value="all" <?php echo $filter_priority == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                    <option value="high" <?php echo $filter_priority == 'high' ? 'selected' : ''; ?>>High Priority</option>
                    <option value="medium" <?php echo $filter_priority == 'medium' ? 'selected' : ''; ?>>Medium Priority</option>
                    <option value="low" <?php echo $filter_priority == 'low' ? 'selected' : ''; ?>>Low Priority</option>
                </select>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="filter-group">
                <label><i class="bi bi-calendar-range"></i> Date Range</label>
                <select class="form-select" id="dateFilter">
                    <option value="all" <?php echo $filter_date == 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $filter_date == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $filter_date == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $filter_date == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Filter Action Buttons -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex gap-2">
                <button class="btn btn-success" onclick="applyFilters()">
                    <i class="bi bi-funnel-fill"></i> Apply Filters
                </button>
                <button class="btn btn-outline-danger" onclick="clearFilters()" id="clearFiltersBtn">
                    <i class="bi bi-x-circle"></i> Clear Filters
                </button>
            </div>
        </div>
    </div>
    
    <!-- Active Filters Display -->
    <div id="activeFiltersDisplay" class="mt-3" style="<?php echo ($filter_category == 'all' && $filter_priority == 'all' && $filter_date == 'all') ? 'display:none;' : ''; ?>">
        <small class="text-muted">Active filters:</small>
        <div class="d-inline-flex gap-2 ms-2 flex-wrap" id="activeFilterBadges">
            <?php if ($filter_category != 'all'): ?>
                <span class="badge bg-success" data-filter="category">
                    Category: <?php echo htmlspecialchars($filter_category); ?>
                    <i class="bi bi-x-circle ms-1" style="cursor: pointer;" onclick="removeFilter('category')"></i>
                </span>
            <?php endif; ?>
            <?php if ($filter_priority != 'all'): ?>
                <span class="badge bg-success" data-filter="priority">
                    Priority: <?php echo ucfirst($filter_priority); ?>
                    <i class="bi bi-x-circle ms-1" style="cursor: pointer;" onclick="removeFilter('priority')"></i>
                </span>
            <?php endif; ?>
            <?php if ($filter_date != 'all'): ?>
                <span class="badge bg-success" data-filter="date">
                    Date: <?php echo ucfirst($filter_date); ?>
                    <i class="bi bi-x-circle ms-1" style="cursor: pointer;" onclick="removeFilter('date')"></i>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="loading-spinner" id="loadingSpinner">
    <div class="spinner-border text-success" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2">Loading announcements...</p>
</div>

<!-- Announcements List -->
<div id="announcementsList">
    <?php echo generateAnnouncementsHTML($announcements); ?>
</div>

<script>
function applyFilters() {
    const category = document.getElementById('categoryFilter').value;
    const priority = document.getElementById('priorityFilter').value;
    const dateRange = document.getElementById('dateFilter').value;
    
    // Build URL with filter parameters - add 'filter' parameter to distinguish from SPA navigation
    const params = new URLSearchParams();
    params.append('ajax', '1');
    params.append('filter', '1'); // This tells the PHP it's a filter request, not SPA navigation
    if (category !== 'all') params.append('category', category);
    if (priority !== 'all') params.append('priority', priority);
    if (dateRange !== 'all') params.append('date_range', dateRange);
    
    // Show loading spinner
    document.getElementById('loadingSpinner').classList.add('show');
    document.getElementById('announcementsList').style.opacity = '0.5';
    
    // Make AJAX request
    fetch(window.location.pathname + '?' + params.toString())
        .then(response => response.text())
        .then(html => {
            // Update announcements list directly with the returned HTML
            document.getElementById('announcementsList').innerHTML = html;
            
            // Update active filters display
            updateActiveFiltersDisplay(category, priority, dateRange);
            
            // Update URL without reloading (without the ajax and filter params)
            const urlParams = new URLSearchParams();
            if (category !== 'all') urlParams.append('category', category);
            if (priority !== 'all') urlParams.append('priority', priority);
            if (dateRange !== 'all') urlParams.append('date_range', dateRange);
            
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.pushState({}, '', newUrl);
            
            // Hide loading spinner
            document.getElementById('loadingSpinner').classList.remove('show');
            document.getElementById('announcementsList').style.opacity = '1';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while filtering announcements. Please try again.');
            document.getElementById('loadingSpinner').classList.remove('show');
            document.getElementById('announcementsList').style.opacity = '1';
        });
}

function clearFilters() {
    // Reset all dropdowns
    document.getElementById('categoryFilter').value = 'all';
    document.getElementById('priorityFilter').value = 'all';
    document.getElementById('dateFilter').value = 'all';
    
    // Apply the reset filters
    applyFilters();
}

function removeFilter(filterType) {
    // Reset specific filter
    switch(filterType) {
        case 'category':
            document.getElementById('categoryFilter').value = 'all';
            break;
        case 'priority':
            document.getElementById('priorityFilter').value = 'all';
            break;
        case 'date':
            document.getElementById('dateFilter').value = 'all';
            break;
    }
    
    // Apply updated filters
    applyFilters();
}

function updateActiveFiltersDisplay(category, priority, dateRange) {
    const activeFiltersDisplay = document.getElementById('activeFiltersDisplay');
    const activeFilterBadges = document.getElementById('activeFilterBadges');
    
    let badges = '';
    let hasFilters = false;
    
    if (category !== 'all') {
        hasFilters = true;
        badges += `
            <span class="badge bg-success" data-filter="category">
                Category: ${escapeHtml(category)}
                <i class="bi bi-x-circle ms-1" style="cursor: pointer;" onclick="removeFilter('category')"></i>
            </span>
        `;
    }
    
    if (priority !== 'all') {
        hasFilters = true;
        badges += `
            <span class="badge bg-success" data-filter="priority">
                Priority: ${capitalize(priority)}
                <i class="bi bi-x-circle ms-1" style="cursor: pointer;" onclick="removeFilter('priority')"></i>
            </span>
        `;
    }
    
    if (dateRange !== 'all') {
        hasFilters = true;
        badges += `
            <span class="badge bg-success" data-filter="date">
                Date: ${capitalize(dateRange)}
                <i class="bi bi-x-circle ms-1" style="cursor: pointer;" onclick="removeFilter('date')"></i>
            </span>
        `;
    }
    
    activeFilterBadges.innerHTML = badges;
    activeFiltersDisplay.style.display = hasFilters ? 'block' : 'none';
}

function resetFilters() {
    clearFilters();
}

function loadMoreAnnouncements() {
    alert('Loading more announcements...\n\nThis feature will load additional announcements from the database.');
    // In actual implementation, this would load more announcements via AJAX
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<?php
$content = ob_get_clean();

// If SPA AJAX request, wrap content in main-content div
if ($is_spa_ajax) {
    echo '<div class="main-content">' . $content . '</div>';
} else {
    // Normal page load - use full layout
    renderLayout('Announcements', $content, 'announcements', $student_info, $initials, $profile_picture_url ?? null);
}
?>