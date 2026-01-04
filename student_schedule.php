<?php
require_once 'student_header.php';
require_once 'student_layout.php';



// Fetch schedule for the student's section
$schedule_data = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => []
];

// COMPLETE REPLACEMENT CODE:
if ($student_data['section_id']) {
    // First, get the section details to know the grade level
    $stmt = $conn->prepare("SELECT grade_level, room_assignment FROM sections WHERE section_id = :section_id");
    $stmt->execute(['section_id' => $student_data['section_id']]);
    $section_info = $stmt->fetch();

    if ($section_info) {
        // Get complete schedule including breaks from grade template
        $stmt = $conn->prepare("
            SELECT 
                gst.day_of_week as day,
                mts.start_time,
                mts.end_time,
                mts.slot_type,
                mts.slot_name,
                subj.subject_name,
                CASE 
                    WHEN gst.subject_code IN ('MAPEH', 'COMP') THEN gst.room_type
                    ELSE :room_assignment
                END as room,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM grade_schedule_template gst
            INNER JOIN master_time_slots mts ON gst.slot_id = mts.slot_id
            INNER JOIN subjects subj ON gst.subject_code = subj.subject_code
            LEFT JOIN section_schedules ss ON gst.template_id = ss.template_id 
                AND ss.section_id = :section_id
                AND ss.is_active = TRUE
            LEFT JOIN teachers t ON ss.teacher_code = t.teacher_code
            WHERE gst.grade_level = :grade_level
            ORDER BY 
                FIELD(gst.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                mts.slot_order
        ");
        $stmt->execute([
            'section_id' => $student_data['section_id'],
            'grade_level' => $section_info['grade_level'],
            'room_assignment' => $section_info['room_assignment']
        ]);
        $schedule_results = $stmt->fetchAll();

        foreach ($schedule_results as $schedule) {
            $start = date('g:i A', strtotime($schedule['start_time']));
            $end = date('g:i A', strtotime($schedule['end_time']));

            // Determine what to display based on slot type
            if ($schedule['slot_type'] === 'RECESS') {
                $display_name = '☕ Morning Recess';
                $room = '-';
                $teacher = '-';
            } elseif ($schedule['slot_type'] === 'LUNCH') {
                $display_name = '🍽️ Lunch Break';
                $room = '-';
                $teacher = '-';
            } else {
                $display_name = $schedule['subject_name'];
                $room = $schedule['room'];
                $teacher = $schedule['teacher_name'] ?? 'TBA';
            }

            $schedule_data[$schedule['day']][] = [
                'time' => $start . ' - ' . $end,
                'start_time' => $schedule['start_time'],
                'subject' => $display_name,
                'room' => $room,
                'teacher' => $teacher,
                'slot_type' => $schedule['slot_type']
            ];
        }
    }
}

// Collect all unique time slots for table format
$all_times = [];
foreach ($schedule_data as $day => $classes) {
    foreach ($classes as $class) {
        if (isset($class['start_time'])) {
            $all_times[$class['start_time']] = $class['time'];
        }
    }
}
ksort($all_times);

// Add placeholder if no schedule
foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
    if (empty($schedule_data[$day])) {
        $schedule_data[$day][] = [
            'time' => '-',
            'subject' => 'No Schedule Available',
            'room' => '-',
            'teacher' => '-'
        ];
    }
}

ob_start();
?>

<style>
    .schedule-header {
        background: linear-gradient(135deg, #7cb342, #689f38);
        color: white;
        padding: 20px 25px;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .schedule-header h4 {
        margin: 0;
        font-weight: bold;
        font-size: 24px;
    }

    .schedule-header p {
        margin: 5px 0 0 0;
        opacity: 0.95;
        font-size: 14px;
    }

    .schedule-day-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
        border-left: 5px solid #7cb342;
    }

    .schedule-day-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }

    .day-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .day-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #7cb342, #689f38);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }

    .day-name {
        font-size: 20px;
        font-weight: bold;
        color: #2c3e50;
        margin: 0;
    }

    .schedule-item {
        background: #f8fef9;
        padding: 18px;
        border-radius: 12px;
        margin-bottom: 12px;
        border: 1px solid #e8f5e9;
        transition: all 0.3s;
    }

    .schedule-item:hover {
        background: #e8f5e9;
        border-color: #7cb342;
        transform: translateX(5px);
    }

    .schedule-item:last-child {
        margin-bottom: 0;
    }

    .subject-name {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .subject-name i {
        color: #7cb342;
        font-size: 18px;
    }

    .schedule-details {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #666;
    }

    .detail-item i {
        color: #7cb342;
        font-size: 14px;
    }

    .detail-item strong {
        color: #2c3e50;
    }

    .no-schedule {
        text-align: center;
        padding: 30px;
        color: #999;
        font-style: italic;
    }

    .download-btn {
        background: white;
        color: #7cb342;
        border: 2px solid #7cb342;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .download-btn:hover {
        background: #7cb342;
        color: white;
    }

    /* Print Styles */
    .print-table-view {
        display: none;
    }

    @media print {
        @page {
            size: A4;
            margin: 15mm;
        }

        body * {
            visibility: hidden;
        }

        .print-table-view,
        .print-table-view * {
            visibility: visible;
        }

        .print-table-view {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            display: block;
            padding: 0;
        }

        .schedule-header,
        .schedule-day-card,
        .card-view {
            display: none !important;
        }

        .print-table-view {
            page-break-after: avoid;
        }

        .schedule-table {
            font-size: 10px;
        }

        .schedule-table th {
            padding: 8px 4px;
            font-size: 11px;
        }

        .schedule-table td {
            padding: 6px 4px;
            font-size: 9px;
        }

        .print-header h2 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .print-header p {
            font-size: 10px;
            margin: 2px 0;
        }
    }

    /* Print Table Styles */
    .print-table-view {
        page-break-after: always;
    }

    .print-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .print-header h2 {
        color: #2c3e50;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .print-header p {
        color: #666;
        margin: 5px 0;
    }

    .schedule-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .schedule-table th {
        background: #7cb342;
        color: white;
        padding: 12px;
        text-align: left;
        font-weight: bold;
        border: 1px solid #689f38;
    }

    .schedule-table td {
        padding: 12px;
        border: 1px solid #ddd;
        vertical-align: top;
    }

    .schedule-table tr:nth-child(even) {
        background: #f8f9fa;
    }

    .schedule-table .time-col {
        font-weight: bold;
        background: #f0f0f0;
        white-space: nowrap;
    }

    .schedule-table .class-info {
        font-size: 11px;
    }

    .schedule-table .subject {
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 3px;
        font-size: 11px;
    }

    .schedule-table .room,
    .schedule-table .teacher {
        color: #666;
        font-size: 10px;
        line-height: 1.3;
    }

    .schedule-table .empty-cell {
        text-align: center;
        color: #999;
        font-style: italic;
    }

    @media (max-width: 768px) {
        .schedule-header {
            text-align: center;
        }

        .schedule-details {
            flex-direction: column;
            gap: 8px;
        }

        .day-header {
            justify-content: center;
        }
    }
</style>

<!-- Card View for Screen -->
<div class="card-view">
    <div class="schedule-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4><i class="bi bi-calendar-week"></i> Class Schedule</h4>
                <p>Academic Year <?php echo $student_info['school_year']; ?></p>
            </div>
            <button class="download-btn" onclick="downloadSchedulePDF()">
                <i class="bi bi-download"></i> Download PDF
            </button>
        </div>
    </div>

    <?php
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $day_icons = [
        'Monday' => 'calendar-day',
        'Tuesday' => 'calendar2-day',
        'Wednesday' => 'calendar3',
        'Thursday' => 'calendar2-week',
        'Friday' => 'calendar-check'
    ];

    for ($i = 0; $i < count($days); $i += 2):
    ?>
        <div class="row">
            <!-- First Day -->
            <div class="col-md-6">
                <div class="schedule-day-card">
                    <div class="day-header">
                        <div class="day-icon">
                            <i class="bi bi-<?php echo $day_icons[$days[$i]]; ?>"></i>
                        </div>
                        <h5 class="day-name"><?php echo $days[$i]; ?></h5>
                    </div>

                    <?php if ($schedule_data[$days[$i]][0]['subject'] === 'No Schedule Available'): ?>
                        <div class="no-schedule">
                            <i class="bi bi-inbox" style="font-size: 40px; color: #ddd;"></i>
                            <p style="margin-top: 10px;">No classes scheduled for this day</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($schedule_data[$days[$i]] as $class): ?>
                            <div class="schedule-item">
                                <div class="subject-name">
                                    <i class="bi bi-book"></i>
                                    <?php echo htmlspecialchars($class['subject']); ?>
                                </div>
                                <div class="schedule-details">
                                    <div class="detail-item">
                                        <i class="bi bi-clock"></i>
                                        <span><?php echo htmlspecialchars($class['time']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="bi bi-door-open"></i>
                                        <span><strong>Room:</strong> <?php echo htmlspecialchars($class['room']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="bi bi-person"></i>
                                        <span><strong>Teacher:</strong> <?php echo htmlspecialchars($class['teacher']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Second Day -->
            <?php if (isset($days[$i + 1])): ?>
                <div class="col-md-6">
                    <div class="schedule-day-card">
                        <div class="day-header">
                            <div class="day-icon">
                                <i class="bi bi-<?php echo $day_icons[$days[$i + 1]]; ?>"></i>
                            </div>
                            <h5 class="day-name"><?php echo $days[$i + 1]; ?></h5>
                        </div>

                        <?php if ($schedule_data[$days[$i + 1]][0]['subject'] === 'No Schedule Available'): ?>
                            <div class="no-schedule">
                                <i class="bi bi-inbox" style="font-size: 40px; color: #ddd;"></i>
                                <p style="margin-top: 10px;">No classes scheduled for this day</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($schedule_data[$days[$i + 1]] as $class): ?>
                                <div class="schedule-item">
                                    <div class="subject-name">
                                        <i class="bi bi-book"></i>
                                        <?php echo htmlspecialchars($class['subject']); ?>
                                    </div>
                                    <div class="schedule-details">
                                        <div class="detail-item">
                                            <i class="bi bi-clock"></i>
                                            <span><?php echo htmlspecialchars($class['time']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="bi bi-door-open"></i>
                                            <span><strong>Room:</strong> <?php echo htmlspecialchars($class['room']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="bi bi-person"></i>
                                            <span><strong>Teacher:</strong> <?php echo htmlspecialchars($class['teacher']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endfor; ?>
</div>

<!-- Table View for Print -->
<div class="print-table-view">
    <div class="print-header">
        <h2>Class Schedule</h2>
        <p><strong>Student:</strong> <?php echo htmlspecialchars($student_info['full_name']); ?></p>
        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_data['student_code']); ?></p>
        <p><strong>Section:</strong> Grade <?php echo htmlspecialchars($student_data['grade_level']); ?> - <?php echo htmlspecialchars($student_data['section_name'] ?? 'N/A'); ?></p>
        <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($student_info['school_year']); ?></p>
    </div>

    <table class="schedule-table">
        <thead>
            <tr>
                <th style="width: 150px;">Time</th>
                <th>Monday</th>
                <th>Tuesday</th>
                <th>Wednesday</th>
                <th>Thursday</th>
                <th>Friday</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($all_times)): ?>
                <tr>
                    <td class="time-col">-</td>
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                        <td class="empty-cell">No classes</td>
                    <?php endforeach; ?>
                </tr>
            <?php else: ?>
                <?php foreach ($all_times as $start_time => $time_display): ?>
                    <tr>
                        <td class="time-col"><?php echo htmlspecialchars($time_display); ?></td>
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day): ?>
                            <td>
                                <?php
                                $found = false;
                                foreach ($schedule_data[$day] as $class) {
                                    if (isset($class['start_time']) && $class['start_time'] === $start_time) {
                                        $found = true;
                                ?>
                                        <div class="class-info">
                                            <div class="subject"><?php echo htmlspecialchars($class['subject']); ?></div>
                                            <div class="room">Room: <?php echo htmlspecialchars($class['room']); ?></div>
                                            <div class="teacher"><?php echo htmlspecialchars($class['teacher']); ?></div>
                                        </div>
                                <?php
                                        break;
                                    }
                                }
                                if (!$found) {
                                    echo '<span class="empty-cell">-</span>';
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
    // Pass schedule data to JavaScript
    const scheduleData = <?php echo json_encode($schedule_data); ?>;
    const allTimes = <?php echo json_encode($all_times); ?>;
    const studentInfo = {
        name: <?php echo json_encode($student_info['full_name']); ?>,
        studentCode: <?php echo json_encode($student_data['student_code']); ?>,
        gradeLevel: <?php echo json_encode($student_data['grade_level']); ?>,
        section: <?php echo json_encode($student_data['section_name'] ?? 'N/A'); ?>,
        schoolYear: <?php echo json_encode($student_info['school_year']); ?>
    };

    function downloadSchedulePDF() {
        const {
            jsPDF
        } = window.jspdf;
        const doc = new jsPDF('landscape', 'mm', 'a4'); // Landscape for better table fit

        // Colors
        const primaryColor = "#7cb342";
        const headerColor = "#689f38";

        // Page dimensions (landscape A4)
        const pageWidth = 297; // A4 landscape width in mm
        const pageHeight = 210; // A4 landscape height in mm
        const margin = 10;
        const usableWidth = pageWidth - (margin * 2);
        const usableHeight = pageHeight - (margin * 2);

        let yPos = margin;

        // Header
        doc.setFillColor(headerColor);
        doc.rect(0, 0, pageWidth, 25, 'F');

        doc.setFontSize(18);
        doc.setTextColor(255, 255, 255);
        doc.setFont("helvetica", "bold");
        doc.text("Class Schedule", pageWidth / 2, 12, {
            align: "center"
        });

        doc.setFontSize(11);
        doc.setFont("helvetica", "normal");
        doc.text("Creative Dreams School", pageWidth / 2, 20, {
            align: "center"
        });

        yPos = 35;

        // Student Information
        doc.setFontSize(9);
        doc.setTextColor(0, 0, 0);
        doc.setFont("helvetica", "normal");
        doc.text(`Student: ${studentInfo.name}`, margin, yPos);
        doc.text(`Student ID: ${studentInfo.studentCode}`, margin + 75, yPos);
        const sectionText = `Grade ${studentInfo.gradeLevel} - ${studentInfo.section}`;
        doc.text(`Section: ${sectionText}`, margin + 150, yPos);
        yPos += 6;
        doc.text(`Academic Year: ${studentInfo.schoolYear}`, margin, yPos);
        yPos += 10;

        // Table setup
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        const timeColWidth = 35;
        const dayColWidth = (usableWidth - timeColWidth) / 5;
        const headerHeight = 10;

        // Get all unique time slots and sort them
        const sortedTimes = Object.entries(allTimes || {}).sort((a, b) => a[0].localeCompare(b[0]));

        // Calculate row height based on available space
        const availableHeight = pageHeight - yPos - margin - headerHeight;
        const numRows = sortedTimes.length;
        // Minimum row height to accommodate subject, room, and teacher
        let rowHeight = Math.max(10, Math.min(12, availableHeight / Math.max(numRows, 1)));

        // Adjust font sizes and spacing if needed to fit on one page
        let subjectFontSize = 7;
        let detailFontSize = 6;
        let lineSpacing = 3.5;
        if (numRows > 12) {
            rowHeight = Math.max(9, availableHeight / numRows);
            subjectFontSize = 6.5;
            detailFontSize = 5.5;
            lineSpacing = 3;
        }
        if (numRows > 18) {
            rowHeight = Math.max(7, availableHeight / numRows);
            subjectFontSize = 6;
            detailFontSize = 5;
            lineSpacing = 2.5;
        }

        // Table Header
        doc.setFillColor(primaryColor);
        doc.rect(margin, yPos, timeColWidth, headerHeight, 'F');
        doc.setFillColor(headerColor);
        doc.rect(margin + timeColWidth, yPos, dayColWidth * 5, headerHeight, 'F');

        doc.setFontSize(10);
        doc.setTextColor(255, 255, 255);
        doc.setFont("helvetica", "bold");
        doc.text("Time", margin + timeColWidth / 2, yPos + 7, {
            align: "center"
        });

        let xPos = margin + timeColWidth;
        days.forEach((day) => {
            doc.text(day.substring(0, 3), xPos + dayColWidth / 2, yPos + 7, {
                align: "center"
            });
            xPos += dayColWidth;
        });

        yPos += headerHeight;

        // Table rows
        doc.setFontSize(8);
        doc.setTextColor(0, 0, 0);
        doc.setFont("helvetica", "normal");

        sortedTimes.forEach(([startTime, timeDisplay], index) => {
            // Alternate row background
            if (index % 2 === 0) {
                doc.setFillColor(245, 245, 245);
                doc.rect(margin, yPos, usableWidth, rowHeight, 'F');
            }

            // Time column
            doc.setFillColor(240, 240, 240);
            doc.rect(margin, yPos, timeColWidth, rowHeight, 'F');
            doc.setFont("helvetica", "bold");
            doc.setFontSize(8);
            // Center time vertically in the cell
            const timeY = yPos + (rowHeight / 2) + 2.5;
            doc.text(timeDisplay, margin + timeColWidth / 2, timeY, {
                align: "center"
            });
            doc.setFont("helvetica", "normal");

            // Day columns
            xPos = margin + timeColWidth;
            days.forEach((day) => {
                const cellContent = getScheduleForTime(day, startTime);

                // Draw cell border
                doc.setDrawColor(200, 200, 200);
                doc.setLineWidth(0.1);
                doc.rect(xPos, yPos, dayColWidth, rowHeight, 'S');

                if (cellContent) {
                    // Calculate starting Y position with padding
                    const cellPadding = 1.5;
                    let textY = yPos + cellPadding + 2;

                    // Subject name (bold)
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(subjectFontSize);
                    const subjectLines = doc.splitTextToSize(cellContent.subject, dayColWidth - 4);
                    // Only show first line if subject is too long
                    const subjectText = subjectLines[0];
                    if (subjectText.length < cellContent.subject.length) {
                        doc.text(subjectText.substring(0, subjectText.length - 3) + '...', xPos + cellPadding, textY);
                    } else {
                        doc.text(subjectText, xPos + cellPadding, textY);
                    }
                    textY += lineSpacing;

                    // Room (smaller)
                    doc.setFont("helvetica", "normal");
                    doc.setFontSize(detailFontSize);
                    if (cellContent.room && cellContent.room !== '-' && textY < yPos + rowHeight - 2) {
                        const roomText = `R: ${cellContent.room}`;
                        const roomLines = doc.splitTextToSize(roomText, dayColWidth - 4);
                        doc.text(roomLines[0], xPos + cellPadding, textY);
                        textY += lineSpacing;
                    }

                    // Teacher (smaller)
                    if (cellContent.teacher && cellContent.teacher !== '-' && cellContent.teacher !== 'N/A' && textY < yPos + rowHeight - 2) {
                        let teacherName = cellContent.teacher;
                        const maxTeacherLength = Math.floor((dayColWidth - 4) / (detailFontSize * 0.35)); // Approximate chars per width
                        if (teacherName.length > maxTeacherLength) {
                            teacherName = teacherName.substring(0, maxTeacherLength - 3) + '...';
                        }
                        const teacherLines = doc.splitTextToSize(teacherName, dayColWidth - 4);
                        doc.text(teacherLines[0], xPos + cellPadding, textY);
                    }
                }

                xPos += dayColWidth;
            });

            yPos += rowHeight;
        });

        // Outer border
        doc.setDrawColor(primaryColor);
        doc.setLineWidth(0.5);
        doc.rect(margin, margin + 25, usableWidth, yPos - (margin + 25), 'S');

        // Save PDF
        const fileName = `Class_Schedule_${studentInfo.name.replace(/\s+/g, '_')}.pdf`;
        doc.save(fileName);
    }

    function getScheduleForTime(day, startTime) {
        if (!scheduleData[day]) return null;

        for (let i = 0; i < scheduleData[day].length; i++) {
            const classItem = scheduleData[day][i];
            // Check if this time slot matches and it's not a placeholder
            if (classItem.start_time === startTime &&
                classItem.subject !== 'No Schedule Available' &&
                classItem.subject !== '-') {
                return {
                    subject: classItem.subject,
                    room: classItem.room || '-',
                    teacher: classItem.teacher || '-'
                };
            }
        }
        return null;
    }
</script>

<?php
$content = ob_get_clean();
renderLayout('Page Title', $content, 'schedule', $student_info, $initials, $profile_picture_url);
?>