<?php 
session_start();

// NO LOGIN CHECK - Index is now public
// Users can browse freely and click "Login" when ready

$host = "localhost";
$user = "u545996239_cdsportal";
$pass = "B@nana2025";
$db   = "u545996239_cdsportal";
$conn = new mysqli($host, $user, $pass, $db);

if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

// CHECK ENROLLMENT STATUS
$enrollment_status = 'open';
$current_sy = '2025-2026';
try {
    $status_query = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('enrollment_status', 'current_school_year')";
    $status_result = $conn->query($status_query);
    if ($status_result && $status_result->num_rows > 0) {
        while ($row = $status_result->fetch_assoc()) {
            if ($row['setting_key'] === 'enrollment_status') {
                $enrollment_status = $row['setting_value'];
            } elseif ($row['setting_key'] === 'current_school_year') {
                $current_sy = $row['setting_value'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching enrollment status: " . $e->getMessage());
}

$enrollment_closed = ($enrollment_status === 'closed');

// FUNCTION: GET AVAILABLE DATES WITH SLOT COUNT
function getAvailableDates($conn) {
    $sql = "SELECT ad.*, 
                   (SELECT COUNT(*) FROM enrollment 
                    WHERE preferred_date = ad.available_date 
                    AND status != 'cancelled') as booked_slots
            FROM available_dates ad 
            WHERE ad.status = 'active' 
            AND ad.available_date >= CURDATE()
            ORDER BY ad.available_date ASC";
    
    $result = $conn->query($sql);
    $dates = [];
    
    while($row = $result->fetch_assoc()){
        $available_slots = $row['max_slots'] - $row['booked_slots'];
        if ($available_slots > 0) {
            $dates[] = [
                'date' => $row['available_date'],
                'available_slots' => $available_slots,
                'max_slots' => $row['max_slots'],
                'booked_slots' => $row['booked_slots']
            ];
        }
    }
    return $dates;
}

// FUNCTION: CHECK IF DATE AND TIME SLOT IS AVAILABLE
function isTimeSlotAvailable($conn, $date, $time) {
    // Check if date is in available_dates and active
    $date_check = "SELECT * FROM available_dates 
                   WHERE available_date = ? 
                   AND status = 'active' 
                   AND available_date >= CURDATE()";
    $stmt = $conn->prepare($date_check);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $date_result = $stmt->get_result();
    
    if ($date_result->num_rows === 0) {
        return false; // Date not available
    }
    
    $date_data = $date_result->fetch_assoc();
    
    // Count existing appointments for this date and time
    $time_check = "SELECT COUNT(*) as count FROM enrollment 
                   WHERE preferred_date = ? 
                   AND preferred_time = ? 
                   AND status != 'cancelled'";
    $stmt = $conn->prepare($time_check);
    $stmt->bind_param("ss", $date, $time);
    $stmt->execute();
    $time_result = $stmt->get_result();
    $time_data = $time_result->fetch_assoc();
    
    // Allow maximum 10 appointments per date
    $total_appointments = "SELECT COUNT(*) as total FROM enrollment 
                          WHERE preferred_date = ? 
                          AND status != 'cancelled'";
    $stmt = $conn->prepare($total_appointments);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_data = $total_result->fetch_assoc();
    
    return ($time_data['count'] < 2 && $total_data['total'] < $date_data['max_slots']);
}

// FUNCTION: GENERATE APPOINTMENT ID
function generateAppointmentId($conn) {
    $prefix = "CDS";
    $unique = false;
    $appointment_id = '';
    
    while (!$unique) {
        $random = mt_rand(1000, 9999);
        $appointment_id = $prefix . $random;
        
        // Check if it exists in database
        $check_sql = "SELECT id FROM enrollment WHERE appointment_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $appointment_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            $unique = true;
        }
        $stmt->close();
    }
    return $appointment_id;
}

// FUNCTION: SAVE ENROLLMENT DATA
function saveEnrollment($conn, $data) {
    // First check if the selected date and time is still available
    if (!isTimeSlotAvailable($conn, $data['date'], $data['time'])) {
        return ['status' => 'error', 'message' => 'Sorry, the selected date and time is no longer available. Please choose another slot.'];
    }
    
    $appointment_id = generateAppointmentId($conn);
    
    $sql = "INSERT INTO enrollment (
        appointment_id, student_name, parent_name, email, phone, 
        preferred_date, preferred_time, grade_level, message, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", 
        $appointment_id,
        $data['studentName'],
        $data['parentName'],
        $data['email'],
        $data['phone'],
        $data['date'],
        $data['time'],
        $data['gradeLevel'],
        $data['message']
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    if($result) {
        return ['status' => 'success', 'appointment_id' => $appointment_id];
    } else {
        return ['status' => 'error', 'message' => $conn->error];
    }
}

// FUNCTION: GET ONLY 3 TESTIMONIALS
function getTestimonials($conn) {
    $sql = "SELECT * FROM testimonials ORDER BY date_posted DESC LIMIT 3";
    $result = $conn->query($sql);

    $data = [];
    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }
    return $data;
}

// FETCH 3 TESTIMONIALS ONLY
$testimonials = getTestimonials($conn);

// FUNCTION: GET ALL PROGRAMS
function getPrograms($conn) {
    $sql = "SELECT * FROM programs";
    $result = $conn->query($sql);

    $data = [];
    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }
    return $data;
}
$programs = getPrograms($conn);

function getHighlights($conn) {
    $sql = "SELECT * FROM highlights ORDER BY highlight_id DESC LIMIT 3";
    $result = $conn->query($sql);

    $data = [];
    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }
    return $data;
}

$highlights = getHighlights($conn);

// GET AVAILABLE DATES
$available_dates = getAvailableDates($conn);

$query = "SELECT * FROM school_settings WHERE id = 1 LIMIT 1";
$result = mysqli_query($conn, $query);
$school = mysqli_fetch_assoc($result);

// AUTO-SPLIT mission & vision (they are stored together)
$mission = '';
$vision = '';

if (!empty($school['mission_vision'])) {
    $parts = preg_split('/\r\n|\r|\n/', $school['mission_vision'], 2);
    $mission = $parts[0] ?? '';
    $vision  = $parts[1] ?? '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creative Dreams School, INC.</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Sleek Enrollment Modal with Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap');

        :root {
            --primary-purple:#a5d6a7 100%;
            --primary-green: #688A65;
            --light-green: #82b37dff;
            --dark-green: #4a6a48;
            --text-dark: #2c3e2b;
            --yellow: #ffd700;
            --white: #ffffff;
            --light-sage: #c4d6b7;
            --sage-green: #4c8c4a;
            --accent-green: #66a65c;
            --pale-green: #e0f7fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f9fff9 0%, #e6f3e6 100%);
            overflow-x: hidden;
        }

        /* NAVIGATION */
        .navbar {
            background: var(--dark-green);
            padding: 1rem 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: white !important;
            font-size: 1.3rem;
        }

        .nav-link {
            color: white !important;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: var(--yellow) !important;
        }

        .enroll-btn {
            background: var(--yellow);
            color: var(--primary-purple);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .enroll-btn:hover {
            background: #ffeb52;
            transform: translateY(-2px);
        }

        .enroll-btn.closed {
            background: #6c757d;
            cursor: not-allowed;
        }

        .enroll-btn.closed:hover {
            transform: none;
            background: #6c757d;
        }

        .hero-section {
            height: 600px;
            background: linear-gradient(rgba(74,124,89,.85), rgba(45,90,61,.9)),
                        url('cdspic.jpg') center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: white;
            text-align: center;
        }

        /* WRAPPER FIX */
        .hero-content {
            display: flex;
            flex-direction: column;
            align-items: center;     /* ← ito talaga nagpapagitna */
            justify-content: center;
            width: 100%;             /* ← prevents lean to left */
        }

        .hero-logo {
            width: 200px;          /* smaller size */
            height: 200px;         /* same height for perfect circle */
            border-radius: 50%;    /* makes it a perfect circle */
            background: white;
            padding: 10px;         /* small padding for breathing room */
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
            margin-bottom: 1.5rem;
        }

        .hero-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;    /* keeps image round too */
        }

        @keyframes float {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .tagline {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .tagline .highlight {
            color: var(--light-sage);
        }

        .cta-btn {
            background: var(--sage-green);
            border-radius: 50px;
            border: none;
            padding: 1rem 2rem;
            font-weight: 700;
            transition: 0.3s;
            color: white;
            cursor: pointer;
            font-size: 1.1rem;
        }

        .cta-btn:hover {
            transform: translateY(-5px);
            background: var(--accent-green);
            box-shadow: 0 10px 30px rgba(74, 124, 89, 0.4);
        }

        .cta-btn.closed {
            background: #6c757d;
            cursor: not-allowed;
        }

        .cta-btn.closed:hover {
            transform: none;
            background: #6c757d;
            box-shadow: none;
        }

        /* Enrollment Status Styles */
        .enrollment-status-closed {
            animation: pulse 2s infinite;
            background: rgba(255,255,255,0.1); 
            padding: 15px 25px; 
            border-radius: 50px; 
            margin: 1rem 0;
            border: 2px solid #ff6b6b;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        /* SECTION BACKGROUND - solid soft sage */
.about-section {
    padding: 4rem 0;
    background: #f1f4f2; /* soft sage-neutral */
}

/* MAIN CARD */
.about-card {
    max-width: 1100px;
    border-radius: 16px;
    padding: 40px;
    background: #ffffff;
    border-left: 6px solid #A3B18A; /* sage accent */
    box-shadow: 0 4px 20px rgba(88, 129, 87, 0.10);
}

/* SCHOOL NAME */
.about-card h1 {
    font-size: 2.5rem;
    color: #344e41; /* deep forest sage */
    margin-bottom: 1.5rem;
}

/* FOREWORD */
.lead {
    max-width: 850px;
    margin: 0 auto 3rem;
    line-height: 1.8;
    font-size: 1.1rem;
    color: #4f5f4d; /* soft sage-gray */
}

/* INNER CARDS */
.inner-card {
    border-radius: 12px;
    background: #f6f8f6; /* very light sage */
    padding: 2rem;
    height: 100%;
    border: 1px solid #d7e0d6;
    transition: 0.3s ease;
}

.inner-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(88, 129, 87, 0.15);
    background: #ffffff;
    border-color: #A3B18A;
}

/* TITLES */
.inner-card h3 {
    font-size: 1.5rem;
    color: #588157; /* deep sage */
    margin-bottom: 1rem;
    font-weight: 600;
}

/* TEXT */
.inner-card p {
    color: #495f4b;
    line-height: 1.7;
    margin: 0;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .about-card {
        padding: 25px;
    }
    
    .about-card h1 {
        font-size: 1.8rem;
    }
    
    .lead {
        font-size: 1rem;
    }
}

        .program-card {
            background: var(--pale-green);
            padding: 2rem;
            border-radius: 20px;
            border: 2px solid var(--sage-green);
            transition: 0.3s;
        }

        .program-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary-green);
            box-shadow: 0 10px 30px rgba(74, 124, 89, 0.2);
        }

        .program-icon {
            width: 64px;
            height: 64px;
            background: var(--sage-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: white;
            font-size: 2rem;
        }

        /* WHY SECTION */
        .why-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            transition: 0.3s;
            border: 3px solid transparent;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .why-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary-green);
            box-shadow: 0 15px 35px rgba(74, 124, 89, 0.15);
        }

        .why-icon {
            width: 64px;
            height: 64px;
            background: var(--sage-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }

        /* TESTIMONIALS */
        .testimonial-card {
            background: white;
            padding: 2rem;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: 0.3s;
            position: relative;
            overflow: hidden;
            border: 2px solid var(--pale-green);
        }

        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(74, 124, 89, 0.15);
            border-color: var(--sage-green);
        }

        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 100px;
            color: rgba(156, 175, 136, 0.1);
            font-family: Georgia, serif;
        }

        .testimonial-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--pale-green);
        }

        .stars {
            color: var(--accent-green);
        }

        /* ACHIEVEMENTS */
        .achievement-section {
            background: linear-gradient(135deg, var(--pale-green) 0%, rgba(156, 175, 136, 0.1) 100%);
            padding: 5rem 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: 0.3s;
            border: 2px solid var(--pale-green);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            border-color: var(--sage-green);
            box-shadow: 0 15px 35px rgba(74, 124, 89, 0.15);
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--text-dark);
            margin: 0.5rem 0;
        }

        .achievement-highlight {
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            border-left: 4px solid;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: 0.3s;
        }

        .achievement-highlight:hover {
            box-shadow: 0 10px 30px rgba(74, 124, 89, 0.15);
            transform: translateX(5px);
        }

        /* FOOTER */
        footer {
            background: var(--dark-green);
            color: white;
            padding: 4rem 2rem;
            margin-top: 5rem;
        }

        .footer-logo {
            width: 120px;
            height: 120px;
            padding: 15px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            margin: 0 auto;
        }

        .footer-section h4 {
            color: var(--light-sage);
            margin-bottom: 1rem;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: color 0.3s;
            color: white;
        }

        .footer-section ul li:hover {
            color: var(--sage-green);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 1rem;
            margin-top: 2rem;
            text-align: center;
            color: white;
        }

        .footer-tagline {
            color: var(--light-sage);
        }

        .footer-item {
    position: relative;
    padding: 5px 0;
    cursor: pointer;
    color: white;
    transition: color 0.3s;
}

.footer-item:hover {
    color: var(--light-sage);
}

.footer-details {
    max-height: 0;
    opacity: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, opacity 0.3s ease;
    font-size: 0.9em;
    color: #ddd;
    margin-top: 2px;
}

.footer-item:hover .footer-details {
    max-height: 200px;
    opacity: 1;
}

        /* MODAL */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-green) 100%);
            color: white;
            padding: 2rem;
            border-radius: 30px 30px 0 0;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            transition: 0.3s;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.3);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-family: 'Poppins', sans-serif;
            transition: 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-purple) 0%, var(--primary-green) 100%);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(107,91,140,0.3);
        }

        .success-message {
            text-align: center;
            padding: 3rem 2rem;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 3rem;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .tagline {
                font-size: 2rem;
            }

            .hero-logo {
                width: 140px;
                height: 140px;
                font-size: 60px;
            }
        }

            .cards-container {
            display: flex;
            gap: 40px;
            flex-wrap: wrap; /* responsive stacking */
            justify-content: center;
            }

            .mission-box {
            background: #ffffffdd;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(199, 239, 207, 0.1);
            padding: 35px;
            width: 320px;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            font-style: italic;
            position: relative;
            overflow: hidden;
            }

            .mission-box::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            transform: rotate(25deg);
            transition: all 0.5s;
            }

            .mission-box:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            }

            .mission-box:hover::before {
            top: -60%;
            left: -60%;
            }

            .mission-box h3 {
            margin-bottom: 20px;
            font-size: 1.5rem;
            color: #333;
            font-style: normal; /* keep title not italic for readability */
            }

            .mission-box p {
            color: #555;
            line-height: 1.7;
            }

            .footer-logo {
                width: 120px;           /* footer size — slightly smaller than hero */
                height: 120px;
                border-radius: 50%;     /* make it a perfect circle */
                background: white;
                padding: 10px;
                box-shadow: 0 6px 25px rgba(0,0,0,0.20);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem auto; /* center + spacing */
            }

            .footer-logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                border-radius: 50%;
            }

            .custom-alert {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.custom-alert.hidden {
    display: none;
}

.custom-alert-box {
    width: 350px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    animation: fadeIn 0.25s ease-out;
}

.custom-alert-header {
    background: #2E7D32;
    padding: 20px;
    text-align: center;
    color: white;
    font-size: 2rem;
}

.custom-alert-body {
    padding: 20px;
    text-align: center;
}

.custom-alert-body p {
    margin-bottom: 15px;
    font-size: 1rem;
    color: #333;
}

.custom-alert-body button {
    background: #2E7D32;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    transition: 0.2s;
}

.custom-alert-body button:hover {
    background: #256428;
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

/* Date slot styles */
.date-slot-info {
    font-size: 0.85rem;
    color: #666;
    margin-top: 5px;
}

.date-slot-available {
    color: #28a745;
    font-weight: 600;
}

.date-slot-full {
    color: #dc3545;
    font-weight: 600;
}

.time-slot-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Improved readability for section headings */
.section-heading {
    color: #2c3e2b !important;
    font-weight: 700;
}

/* Improved readability for section text */
.section-text {
    color: #4a6a48 !important;
}

/* White text for all footer content */
.footer-section * {
    color: white !important;
}

.footer-section h4 {
    color: var(--light-sage) !important;
}

/* Google Maps Modal */
.map-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.map-modal.active {
    display: flex;
}

.map-container {
    width: 90%;
    height: 80%;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.map-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(0,0,0,0.7);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-size: 1.5rem;
    cursor: pointer;
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
}

.map-close:hover {
    background: rgba(0,0,0,0.9);
}

.map-iframe {
    width: 100%;
    height: 100%;
    border: none;
}

/* Dark green school name in header */
.header-school-name {
    color: var(--dark-green) !important;
    font-weight: 700;
}

    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="cdslogo.jpg" alt="cdslogo" style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
            Creative Dreams School, INC.
        </a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="#about">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#programs">Programs</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#why">Why Us?</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#testimonials">Testimonials</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#contact">Contact</a>
                </li>

                <!-- LOGIN BUTTON -->
                <li class="nav-item ms-lg-3">
                    <a href="login.php" 
                        class="btn btn-light"
                        style="
                            padding: 6px 18px;
                            border-radius: 8px;
                            font-size: 0.9rem;
                            color: #333 !important;
                            font-weight: 500;
                        ">
                        Login
                    </a>
                </li>

                <!-- ENROLL BUTTON -->
                <li class="nav-item ms-lg-2">
                    <button class="enroll-btn <?php echo $enrollment_closed ? 'closed' : ''; ?>" 
                            onclick="openEnrollModal()">
                        <?php echo $enrollment_closed ? 'Enrollment Closed' : 'Enroll Now'; ?>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>


<!-- HERO -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-logo" data-aos="zoom-in"> 
            <img src="cdslogo.jpg" alt="cdslogo">
        </div>

        <h1 class="tagline" data-aos="fade-up">
            Shaping Young Minds,<br>
            <span class="highlight">Building Bright Futures</span>
        </h1>

        <!-- ENROLLMENT STATUS INDICATOR -->
        <?php if ($enrollment_closed): ?>
        <div class="enrollment-status-closed" data-aos="fade-up" data-aos-delay="100">
            <i class="bi bi-exclamation-triangle-fill" style="color: #ff6b6b; margin-right: 8px;"></i>
            <strong style="color: white;">Enrollment for SY <?php echo htmlspecialchars($current_sy); ?> is currently closed</strong>
        </div>
        <?php endif; ?>

        <button class="cta-btn mt-4 <?php echo $enrollment_closed ? 'closed' : ''; ?>" 
                onclick="openEnrollModal()" 
                data-aos="fade-up" 
                data-aos-delay="200">
            <?php echo $enrollment_closed ? 'Enrollment Closed' : 'ENROLL NOW'; ?> 
            <i class="bi bi-arrow-right"></i>
        </button>
    </div>
</section>


<!-- ABOUT -->
<section class="about-section" id="about" data-aos="fade-up">
    <div class="container">
        <div class="card about-card mx-auto border-0">
            <div class="card-body text-center">

                <!-- SCHOOL NAME -->
                <h1 class="fw-bold header-school-name">
                    <?= htmlspecialchars($school['school_name']); ?>
                </h1>

                <!-- FOREWORD -->
                <p class="lead section-text">
                    <?= nl2br(htmlspecialchars($school['foreword'])); ?>
                </p>

                <!-- MISSION & VISION -->
                <div class="row g-4">

                    <!-- MISSION -->
                    <div class="col-md-6">
                        <div class="inner-card">
                            <h3 class="section-heading">Our Mission</h3>
                            <p class="section-text">
                                <?= nl2br(htmlspecialchars($mission)); ?>
                            </p>
                        </div>
                    </div>

                    <!-- VISION -->
                    <div class="col-md-6">
                        <div class="inner-card">
                            <h3 class="section-heading">Our Vision</h3>
                            <p class="section-text">
                                <?= nl2br(htmlspecialchars($vision)); ?>
                            </p>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</section>




<!-- PROGRAMS -->
<section class="container my-5" id="programs">
    <h1 class="text-center mb-5 section-heading" data-aos="fade-down">Programs & Academics</h1>

    <div class="row g-4">

        <?php foreach ($programs as $p): ?>
        <div class="col-md-4" data-aos="fade-up">
            <div class="program-card">

                <div class="program-icon">
                    <i class="<?= htmlspecialchars($p['icon']) ?>"></i>
                </div>

                <h3 class="section-heading"><?= htmlspecialchars($p['title']) ?></h3>
                <p class="section-text"><?= htmlspecialchars($p['description']) ?></p>

            </div>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- WHY SECTION -->
<section class="container text-center my-5 py-5" id="why">
    <h1 class="mb-5 section-heading" data-aos="fade-down">Why Creative Dreams School, INC.?</h1>

    <div class="row g-4">
        <div class="col-md-3" data-aos="zoom-in">
            <div class="why-card">
                <div class="why-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3 class="section-heading">Experienced Teachers</h3>
            </div>
        </div>

        <div class="col-md-3" data-aos="zoom-in" data-aos-delay="100">
            <div class="why-card">
                <div class="why-icon">
                    <i class="bi bi-book-fill"></i>
                </div>
                <h3 class="section-heading">Strong Academic Foundation</h3>
            </div>
        </div>

        <div class="col-md-3" data-aos="zoom-in" data-aos-delay="200">
            <div class="why-card">
                <div class="why-icon">
                    <i class="bi bi-laptop-fill"></i>
                </div>
                <h3 class="section-heading">Modern Facilities & Tech</h3>
            </div>
        </div>

        <div class="col-md-3" data-aos="zoom-in" data-aos-delay="300">
            <div class="why-card">
                <div class="why-icon">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <h3 class="section-heading">Holistic Student Development</h3>
            </div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="container my-5 py-5" id="testimonials">
    <div class="text-center mb-5">
        <h1 class="section-heading" data-aos="fade-down">Our School in Their Words</h1>
        <p class="lead text-muted section-text" data-aos="fade-up">Hear from our amazing school community</p>
    </div>

    <div class="row g-4">

        <?php foreach ($testimonials as $t): ?>
        <div class="col-md-4" data-aos="fade-up">
            <div class="testimonial-card">

                <div class="d-flex align-items-center mb-3">
                    <!-- Auto-generated avatar -->
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($t['name']) ?>&background=random" 
                         alt="<?= htmlspecialchars($t['name']) ?>" 
                         class="testimonial-avatar me-3">

                    <div>
                        <h5 class="mb-0 section-heading"><?= htmlspecialchars($t['name']) ?></h5>
                        <small class="text-muted section-text"><?= htmlspecialchars($t['role']) ?></small>
                    </div>
                </div>

                <div class="stars mb-2">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                </div>

                <p class="fst-italic section-text">"<?= htmlspecialchars($t['message']) ?>"</p>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</section>

        <!-- Recent Highlights -->
<h3 class="text-center mb-4 section-heading" data-aos="fade-up">Recent Highlights</h3>

<div class="row g-4">

    <?php foreach ($highlights as $h): ?>
    <div class="col-md-4" data-aos="fade-up">
        <div class="achievement-highlight" style="border-color: <?= htmlspecialchars($h['border_color']) ?>;">
            <div class="d-flex gap-3">

                <div class="stat-icon" 
                     style="background: var(--pale-green); 
                            color: <?= htmlspecialchars($h['icon_color']) ?>; 
                            width: 48px; height: 48px; font-size: 1.5rem;">
                    <i class="<?= htmlspecialchars($h['icon']) ?>"></i>
                </div>

                <div>
                    <h5 class="section-heading"><?= htmlspecialchars($h['title']) ?></h5>
                    <p class="text-muted small mb-0 section-text">
                        <?= htmlspecialchars($h['description']) ?>
                    </p>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>
<!-- FOOTER -->
<footer id="contact">
    <div class="container">
        <!-- Footer Header / Logo -->
        <div class="text-center mb-4">
            <div class="footer-logo">
                <img src="cdslogo.jpg" alt="cdslogo" style="width:80px; height:auto;">
            </div>
            <h2 class="mt-3">Creative Dreams School, INC.</h2>
            <p style="color: var(--yellow); font-size: 1.1rem;">Nurturing Hearts • Inspiring Minds • Creating Dreams</p>
        </div>

        <!-- Footer Columns -->
        <div class="row text-center text-md-start g-4">

            <!-- Column 1: Quick Links -->
            <div class="col-md-4 footer-section">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li class="footer-item">
                        About Us
                        <div class="footer-details">
                            <p>Learn about our mission, vision, and team.</p>
                        </div>
                    </li>
                    <li class="footer-item">
                        Programs
                        <div class="footer-details">
                            <p>Preschool, Kindergarten, Elementary, and Summer Programs.</p>
                        </div>
                    </li>
                    <li class="footer-item">
                        Admissions
                        <div class="footer-details">
                            <p>Application process, tuition fees, and scholarships.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Column 2: Programs -->
            <div class="col-md-4 footer-section">
                <h4>Programs</h4>
                <ul>
                    <li class="footer-item">Preschool</li>
                    <li class="footer-item">Elementary</li>
                    <li class="footer-item">Clubs & Activities</li>
                    <li class="footer-item">Libraries</li>
                </ul>
            </div>

            <!-- Column 3: Contact -->
            <div class="col-md-4 footer-section">
                <h4>Contact</h4>
                <ul>
                    <li><i class="bi bi-envelope"></i> cds@gmail.com</li>
                    <li><i class="bi bi-telephone"></i> 0912-345-6789</li>
                    <li class="footer-item" onclick="openMapModal()" style="cursor: pointer;">
                        <i class="bi bi-geo-alt"></i> Brias Street, Barangay 1, Nasugbu, Batangas 4231 
                        <div class="footer-details">
                            <p>Click to view location on Google Maps</p>
                        </div>
                    </li>
                </ul>
            </div>

            

        </div> <!-- End row -->

        <!-- Footer Bottom -->
        <div class="footer-bottom text-center mt-4">
            © 2025 Creative Dreams School, INC. All Rights Reserved.
        </div>
    </div>
</footer>

<!-- Google Maps Modal -->
<div class="map-modal" id="mapModal">
    <div class="map-container">
        <button class="map-close" onclick="closeMapModal()">&times;</button>
        <iframe 
            src="https://www.google.com/maps/embed?pb=!4v1733029123456!6m8!1m7!1sCAoSLEFGMVFpcE5fN3BkRzBqWUVBR1R5U0c4cGpYX0I5LUZrT1VJcFBmRGpYT1pf!2m2!1d14.076674!2d120.6308194!3f0!4f0!5f0.7820865974627469" 
            class="map-iframe" 
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>
</div>

<!-- ENROLLMENT MODAL -->
<div class="modal-overlay" id="enrollModal">
    <div class="modal-content">
        <div class="modal-header">
            <button class="modal-close" onclick="closeEnrollModal()">&times;</button>
            <h2 class="mb-2">Schedule Your Visit</h2>
            <p>Book an appointment to tour our campus and meet our team</p>
        </div>

        <div class="modal-body" id="modalBody">
            <form id="enrollForm">
                <div class="form-group">
                    <label><i class="bi bi-person"></i> Student Name</label>
                    <input type="text" class="form-control" name="studentName" required placeholder="Enter student's full name">
                </div>

                <div class="form-group">
                    <label><i class="bi bi-person"></i> Parent/Guardian Name</label>
                    <input type="text" class="form-control" name="parentName" required placeholder="Enter your full name">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="bi bi-envelope"></i> Email</label>
                            <input type="email" class="form-control" name="email" required placeholder="your@email.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="bi bi-phone"></i> Phone</label>
                            <input type="tel" class="form-control" name="phone" required placeholder="0912-345-6789">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="bi bi-calendar"></i> Preferred Date</label>
                            <select class="form-control" name="date" id="dateSelect" required>
                                <option value="">Loading available dates...</option>
                            </select>
                            <div id="dateSlotInfo" class="date-slot-info"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="bi bi-clock"></i> Preferred Time</label>
                            <select class="form-control" name="time" id="timeSelect" required>
                                <option value="">Select time</option>
                                <option value="9:00 AM">9:00 AM</option>
                                <option value="10:00 AM">10:00 AM</option>
                                <option value="11:00 AM">11:00 AM</option>
                                <option value="1:00 PM">1:00 PM</option>
                                <option value="2:00 PM">2:00 PM</option>
                                <option value="3:00 PM">3:00 PM</option>
                                <option value="4:00 PM">4:00 PM</option>
                            </select>
                            <div id="timeSlotInfo" class="date-slot-info"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Grade Level Interested In</label>
                    <select class="form-control" name="gradeLevel" required>
                        <option value="">Select grade level</option>
                        <option value="Preschool">Preschool</option>
                        <option value="Kindergarten">Kindergarten</option>
                        <option value="Grade 1">Grade 1</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                        <option value="Grade 4">Grade 4</option>
                        <option value="Grade 5">Grade 5</option>
                        <option value="Grade 6">Grade 6</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="bi bi-chat-left-text"></i> Additional Notes (Optional)</label>
                    <textarea class="form-control" name="message" rows="4" placeholder="Any questions or special requirements?"></textarea>
                </div>

                <button type="submit" class="submit-btn">Schedule Appointment</button>
            </form>
        </div>
    </div>
</div>

<!-- Custom Alert Modal -->
<div id="customAlert" class="custom-alert hidden">
    <div class="custom-alert-box">
        <div class="custom-alert-header">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="custom-alert-body">
            <p id="customAlertMessage"></p>
            <button onclick="closeCustomAlert()">OK</button>
        </div>
    </div>
</div>


<!-- SCRIPTS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
const { jsPDF } = window.jspdf;

// Available dates from PHP
const availableDates = <?php echo json_encode($available_dates); ?>;

// Google Maps Modal Functions
function openMapModal() {
    document.getElementById('mapModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMapModal() {
    document.getElementById('mapModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close map modal when clicking outside
document.getElementById('mapModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMapModal();
    }
});

// Enrollment status check
function openEnrollModal() {
    <?php if ($enrollment_closed): ?>
        showAlert("Enrollment is currently closed for School Year <?php echo htmlspecialchars($current_sy); ?>. Please check back later for the next enrollment period.");
        return;
    <?php endif; ?>
    
    document.getElementById('enrollModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Populate available dates
    populateAvailableDates();
}

function closeEnrollModal() {
    document.getElementById('enrollModal').classList.remove('active');
    document.body.style.overflow = 'auto';
    // Reset form when closing
    document.getElementById('enrollForm').reset();
    document.getElementById('modalBody').innerHTML = document.getElementById('modalBody').innerHTML;
}

// Populate available dates in dropdown
function populateAvailableDates() {
    const dateSelect = document.getElementById('dateSelect');
    const dateSlotInfo = document.getElementById('dateSlotInfo');
    
    dateSelect.innerHTML = '<option value="">Select a date</option>';
    
    if (availableDates.length === 0) {
        dateSelect.innerHTML = '<option value="">No available dates</option>';
        dateSlotInfo.innerHTML = '<span class="date-slot-full">No appointment slots available at the moment.</span>';
        return;
    }
    
    availableDates.forEach(date => {
        const option = document.createElement('option');
        option.value = date.date;
        option.textContent = formatDate(date.date) + ` (${date.available_slots} slots available)`;
        option.dataset.slots = date.available_slots;
        dateSelect.appendChild(option);
    });
    
    dateSlotInfo.innerHTML = `<span class="date-slot-available">${availableDates.length} date(s) available for booking</span>`;
}

// Format date for display
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

// Check time slot availability when date changes
document.getElementById('dateSelect').addEventListener('change', function() {
    const selectedDate = this.value;
    const timeSelect = document.getElementById('timeSelect');
    const timeSlotInfo = document.getElementById('timeSlotInfo');
    
    // Reset time options
    Array.from(timeSelect.options).forEach(option => {
        if (option.value !== '') {
            option.disabled = false;
            option.classList.remove('time-slot-disabled');
        }
    });
    
    timeSlotInfo.innerHTML = '';
    
    if (!selectedDate) return;
    
    // Check availability for each time slot
    const timeSlots = ['9:00 AM', '10:00 AM', '11:00 AM', '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM'];
    
    // In a real implementation, you would fetch this from the server
    // For now, we'll simulate by allowing all time slots if date has available slots
    const selectedOption = this.options[this.selectedIndex];
    const availableSlots = parseInt(selectedOption.dataset.slots);
    
    if (availableSlots <= 0) {
        timeSlotInfo.innerHTML = '<span class="date-slot-full">No slots available for this date</span>';
        Array.from(timeSelect.options).forEach(option => {
            if (option.value !== '') {
                option.disabled = true;
                option.classList.add('time-slot-disabled');
            }
        });
    }
});

// Form Submission
document.getElementById('enrollForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const selectedDate = formData.get('date');
    const selectedTime = formData.get('time');

    // Validate date selection
    if (!selectedDate) {
        showAlert("Please select an available date.");
        return;
    }

    // Validate time selection
    if (!selectedTime) {
        showAlert("Please select a preferred time.");
        return;
    }

    try {
        // Send data to PHP for database storage
        const response = await fetch('submit_enrollment.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            // Ask user if they want PDF Slip
            const printConfirm = confirm("Appointment successfully scheduled! Do you want to download the summary slip?");

            if (printConfirm) {
                generatePDF(result.appointment_id, formData);
            }

            // Success Message
            document.getElementById('modalBody').innerHTML = `
                <div class="success-message text-center">
                    <div class="success-icon mb-3">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h3>Appointment Scheduled!</h3>
                    <p class="text-muted mb-3">We'll contact you shortly to confirm your visit.</p>
                    ${result.appointment_id ? `<p><strong>Appointment ID: ${result.appointment_id}</strong></p>` : ''}
                    <p class="text-muted small">Please bring the required documents during your visit.</p>
                </div>
            `;

        } else {
            showAlert(result.message || "Error scheduling appointment. Please try again.");
        }

    } catch (error) {
        console.error(error);
        showAlert("Network error. Please check your connection and try again.");
    }
});

// Generate PDF function
function generatePDF(appointmentId, formData) {
    const doc = new jsPDF();

    // --- COLORS ---
    const primaryColor = "#2E7D32";

    // --- HEADER ---
    doc.setFillColor(primaryColor);
    doc.rect(0, 0, 210, 25, 'F');

    doc.setFontSize(16);
    doc.setTextColor(255, 255, 255);
    doc.setFont("helvetica", "bold");
    doc.text("Creative Dreams School", 105, 15, null, null, "center");

    doc.setFontSize(12);
    doc.setFont("helvetica", "normal");
    doc.text("Online Enrollment Schedule Slip", 105, 22, null, null, "center");

    // --- BODY ---
    doc.setFontSize(12);
    doc.setTextColor(0);
    let y = 35;
    const labelX = 20;
    const valueX = 100;
    const lineHeight = 10;

    const fields = [
        ["Student Name", formData.get("studentName")],
        ["Parent/Guardian Name", formData.get("parentName")],
        ["Email", formData.get("email")],
        ["Phone", formData.get("phone")],
        ["Preferred Date", formData.get("date")],
        ["Preferred Time", formData.get("time")],
        ["Grade Level", formData.get("gradeLevel")],
        ["Additional Notes", formData.get("message") || "N/A"],
    ];

    fields.forEach(([label, value]) => {
        doc.setFont("helvetica", "bold");
        doc.text(`${label}:`, labelX, y);
        doc.setFont("helvetica", "normal");
        doc.text(String(value), valueX, y);
        y += lineHeight;
    });

    // --- APPOINTMENT ID ---
    if (appointmentId) {
        y += 5;
        doc.setFont("helvetica", "bold");
        doc.text("Appointment ID:", labelX, y);
        doc.setFont("helvetica", "normal");
        doc.text(String(appointmentId), valueX, y);
        y += 15;
    }

    // --- REQUIREMENTS TO BRING ---
    doc.setFont("helvetica", "bold");
    doc.setFontSize(12);
    doc.text("Requirements to Bring:", labelX, y);
    y += 8;

    doc.setFont("helvetica", "italic");
    doc.setFontSize(11);

    const requirements = [
        "Student's PSA Birth Certificate (Photocopy)",
        "Report Card / Form 138 from Previous school",
        "2 pcs 1x1 ID Picture",
        "Parent/Guardian Valid ID",
    ];

    requirements.forEach(req => {
        doc.text(`•  ${req}`, labelX + 5, y);
        y += 8;
    });

    // --- FOOTER ---
    y += 10;
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(
        "Thank you for scheduling your campus visit. We will contact you to confirm.",
        20,
        y
    );

    // --- BORDER ---
    doc.setDrawColor(primaryColor);
    doc.setLineWidth(0.8);
    doc.rect(15, 30, 180, y - 25);

    // --- SAVE PDF ---
    doc.save("Creative_Dreams_Enrollment_Slip.pdf");
}

function showAlert(message) {
    document.getElementById("customAlertMessage").textContent = message;
    document.getElementById("customAlert").classList.remove("hidden");
}

function closeCustomAlert() {
    document.getElementById("customAlert").classList.add("hidden");
}

// Toggle hidden text
const buttons = document.querySelectorAll('.toggle-btn');
buttons.forEach(btn => {
    btn.addEventListener('click', () => {
        const hiddenText = btn.nextElementSibling;
        hiddenText.classList.toggle('hidden');
        btn.textContent = hiddenText.classList.contains('hidden') ? 'Read More' : 'Read Less';
    });
});

// AOS
AOS.init({
    duration: 1000,
    once: true
});

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Close modal on overlay
document.getElementById('enrollModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEnrollModal();
    }
});

// Close on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEnrollModal();
        closeMapModal();
    }
});
</script>

</body>
</html>