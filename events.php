<?php
session_start();
require_once "db.php";

/** @var mysqli $conn */

$isVisitor = !empty($_SESSION['visitor_access']);
$isStaff = (($_SESSION['user_role'] ?? '') === 'Staff');
$isAdmin = (($_SESSION['user_role'] ?? '') === 'Admin');

// Staff use this page for assigned events. Visitors use it after entering an invitation code.
if (!$isStaff && !$isVisitor) {
    header("Location: login.php?error=Please log in as staff or enter a visitor code to access events");
    exit();
}

// Basic user information and arrays used to build the dashboard.
$userName = $isVisitor ? 'Visitor' : ($_SESSION['user_name'] ?? "User");
$userId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$messageType = 'error';


if (isset($_GET['error'])) {
    $message = trim($_GET['error']);
}

// Check if user is already registered for an event
function isUserJoined($conn, $userId, $eventId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_events WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $userId, $eventId);
    $stmt->execute();
        
    $count = 0; 
        
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

// Handler for staff editing assigned event details.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'edit_event') {
    if ($isVisitor) {
        header("Location: events.php?error=Visitors can view events only");
        exit();
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $room = trim($_POST['room'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');

    if ($userId <= 0 || $eventId <= 0 || $title === '' || $desc === '' || $room === '' || $date === '' || $start === '' || $end === '') {
        header("Location: events.php?error=Please fill in all event fields");
        exit();
    }

    if ($end <= $start) {
        header("Location: events.php?error=End time must be after start time");
        exit();
    }

    try {
        $ownershipStmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND assigned_staff_id = ?");
        $ownershipStmt->bind_param("ii", $eventId, $userId);
        $ownershipStmt->execute();
        $ownershipResult = $ownershipStmt->get_result();
        $canEditEvent = $ownershipResult && $ownershipResult->num_rows > 0;
        $ownershipStmt->close();

        if (!$canEditEvent) {
            header("Location: events.php?error=You can only edit events assigned to you");
            exit();
        }

        $stmt = $conn->prepare(
            "UPDATE events
             SET title = ?, description = ?, room = ?, date = ?, start_time = ?, end_time = ?
             WHERE id = ? AND assigned_staff_id = ?"
        );
        $stmt->bind_param("ssssssii", $title, $desc, $room, $date, $start, $end, $eventId, $userId);
        $stmt->execute();
        $stmt->close();

        header("Location: events.php?success=Event updated successfully");
        exit();
    } catch (mysqli_sql_exception $e) {
        header("Location: events.php?error=Could not update the event");
        exit();
    }
}

// Handler for user joining an event
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'join_event') {
    if ($isVisitor) {
        header("Location: events.php?error=Visitors can view events only");
        exit();
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($userId > 0 && $eventId > 0 && !isUserJoined($conn, $userId, $eventId)) {
        try {
            $stmt = $conn->prepare("INSERT INTO user_events (user_id, event_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $eventId);
            $stmt->execute();
            $stmt->close();
            // Redirect to prevent form resubmission
            header("Location: events.php?success=Successfully joined event!");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not join the event. " . $e->getMessage();
        }
    }
}

// Handler for marking attendance from modal
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'mark_event_attendance') {
    header('Content-Type: application/json');
    if ($isVisitor) {
        echo json_encode(['success' => false]);
        exit();
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    $attendanceStatus = $_POST['status'] ?? '';
    // Here we'll just acknowledge the request for this example.
    echo json_encode(['success' => true]);
    exit();
}

// Load events. Staff only see assigned events; visitors can view all events.
$upcomingEvents = [];
try {
    if ($isVisitor) {
        $stmt = $conn->prepare(
            "SELECT e.id, e.title, e.image_path, e.description, e.room, e.date, e.start_time, e.end_time,
                    u.fullname AS assigned_staff_name
             FROM events e
             LEFT JOIN users u ON u.id = e.assigned_staff_id
             ORDER BY e.date ASC, e.start_time ASC"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT e.id, e.title, e.image_path, e.description, e.room, e.date, e.start_time, e.end_time,
                    u.fullname AS assigned_staff_name
             FROM events e
             LEFT JOIN users u ON u.id = e.assigned_staff_id
             WHERE e.assigned_staff_id = ?
             ORDER BY e.date ASC, e.start_time ASC"
        );
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dateObj = new DateTime($row['date']);
            $startObj = new DateTime($row['start_time']);
            $endObj = new DateTime($row['end_time']);
            
            $row['formatted_date'] = $dateObj->format('M d, Y');
            $row['formatted_time'] = $startObj->format('g:ia') . ' - ' . $endObj->format('g:ia');
            $row['user_joined'] = $isVisitor ? false : isUserJoined($conn, $userId, $row['id']);
            $upcomingEvents[] = $row;
        }
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $upcomingEvents = [];
}

// Function to fetch gallery images for a specific event
function getEventGalleryImages($conn, $eventId) {
    $images = [];
    try {
        $stmt = $conn->prepare("SELECT image_path FROM event_gallery WHERE event_id = ? ORDER BY id ASC LIMIT 25");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $images[] = $row['image_path'];
            }
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $images = [];
    }
    return $images;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#11072b">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="captcha.png">
    <title>Events Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Reset and Layout */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #ffffff; color: #333; overflow-x: hidden; min-height: 100vh; display: flex; flex-direction: column; }

        /* --- Header & Wave Design --- */
        .header-container {
            position: relative;
            width: 100%;
            height: 120px;
            background-color: #11072b;
            color: white;
            padding: 40px 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            z-index: 2;
        }

        .header-wave {
            position: absolute;
            top: 80%;
            left: 0;
            width: 100%;
            height: 100px;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L1000,0 L1000,30 C750,10 250,120 0,80 Z' fill='%2311072b'/%3E%3C/svg%3E");
            background-size: 100% 100%;
        }

        .header-left { display: flex; align-items: baseline; gap: 20px; z-index: 2; }
        .header-container h1 { font-size: 2.5rem; font-weight: 400; }
        
        .logout-btn, .back-btn {
            background-color: white; color: #11062b; padding: 10px 30px; border-radius: 25px;
            text-decoration: none; font-weight: bold; font-size: 1rem; z-index: 2;
        }
        .back-btn { background: transparent; color: white; text-decoration: underline; padding: 10px 0; font-weight: 400; }

        /* --- Main Layout --- */
        .main-content { flex: 1; padding: 50px 60px 100px; margin-top: 50px; z-index: 2; position: relative; }

        /* --- Events Section --- */
        .events-title { font-size: 1.8rem; font-weight: bold; margin-bottom: 30px; color: #000; }

        .cards-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }

        .event-card {
            background-color: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            overflow: hidden; display: flex; flex-direction: column; border: 1px solid #14043d; transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2); }
        .event-card-image-wrapper { position: relative; width: 100%; padding-top: 75%; }
        .event-card-image-wrapper img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .event-edit-icon {
            position: absolute; top: 12px; right: 12px; z-index: 3; width: 38px; height: 38px;
            border: none; border-radius: 50%; background: rgba(255, 255, 255, 0.95); color: #11062b;
            display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
            box-shadow: 0 6px 14px rgba(0,0,0,0.22); transition: 0.2s ease;
        }
        .event-edit-icon:hover { background: #f6d365; transform: translateY(-1px); }
        .event-edit-icon i { font-size: 1rem; }

        .event-card-body {
            position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; color: white;
            background: linear-gradient(to top, rgba(17, 6, 43, 0.95) 0%, rgba(17, 6, 43, 0.8) 50%, rgba(17, 6, 43, 0) 100%);
            display: flex; flex-direction: column; gap: 5px;
        }
        .event-card-body h3 { font-size: 1rem; font-weight: bold; margin-bottom: 5px; }
        .event-card-body p { font-size: 0.6rem; line-height: 1.4; }
        .event-assigned-staff { font-size: 0.75rem; color: #e8ddff; font-weight: 600; }

        .event-card-footer { position: relative; margin-top: auto; display: flex; justify-content: flex-end; padding: 10px 20px 20px; }
        .join-btn, .details-btn {
            background-color: white; color: #11062b; padding: 8px 24px; border-radius: 20px; text-decoration: none;
            font-weight: bold; font-size: 0.85rem; cursor: pointer; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.2s;
        }
        .join-btn:hover, .details-btn:hover { background-color: #f0f0f0; box-shadow: 0 6px 10px rgba(0,0,0,0.15); }
        .join-btn:disabled { background-color: #ccc; color: #666; cursor: not-allowed; box-shadow: none; }
        .details-btn { background-color: #11062b; color: white; margin-right: 10px; }
        .details-btn:hover { background-color: #1f0b4d; }

        .empty-state { color: #666; font-style: italic; grid-column: 1 / -1; }
        .footer { padding: 0 60px 40px 60px; margin-top: auto; z-index: 2; position: relative; }

        /* --- Modal Styles --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 450px; position: relative; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2); max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 1.5rem; color: #333; transition: color 0.2s; }
        .close-modal:hover { color: #d9534f; }

        /* Generic Modal Content */
        .modal-content h2 { margin-bottom: 20px; color: #11072b; font-size: 1.5rem; }
        .modal-content p { line-height: 1.5; margin-bottom: 20px; color: #444; }
        .modal-content form { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        .modal-content form input, .modal-content form textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d4d4d4; border-radius: 6px;
            font: inherit; color: #333; background: #fff;
        }
        .modal-content form textarea { resize: vertical; min-height: 110px; }
        .modal-content form button[type="submit"] {
            background-color: #11072b; color: white; border: none; padding: 12px;
            border-radius: 5px; cursor: pointer; font-size: 1rem; font-weight: bold;
        }
        .modal-content form button[type="submit"]:hover { background-color: #2b1154; }
        .btn-request-room { background-color: #11072b; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-size: 1rem; width: 100%; }

        /* Event Details Modal */
        .event-details-content { max-width: 800px; width: min(800px, calc(100vw - 32px)); display: flex; flex-direction: column; gap: 20px; padding: 0; overflow: hidden; }
        .event-details-header { background: #11062b; color: white; padding: 20px 30px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .event-details-header h2 { margin: 0; color: white; font-size: 1.25rem; font-weight: bold; }
        .event-details-header .close-modal { color: rgba(255,255,255,0.7); top: 18px; right: 20px; }
        .event-details-header .close-modal:hover { color: white; }
        
        .event-details-body { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 30px; padding: 30px; background: white; }
        .details-left { display: flex; flex-direction: column; gap: 15px; }
        .modal-event-poster { width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 10px; }
        .event-info-title { color: #11062b; font-size: 1.2rem; font-weight: bold; margin-bottom: 10px; display: block; }
        .event-section-label { color: #11062b; font-weight: bold; font-size: 0.95rem; display: block; margin-bottom: 5px; }
        .modal-event-description { color: #444; font-size: 0.85rem; line-height: 1.5; margin-bottom: 15px; }
        
        .event-meta-table { font-size: 0.85rem; width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .event-meta-table td { padding: 5px 0; vertical-align: middle; color: #444; }
        .event-meta-table td:first-child { width: 80px; font-weight: bold; color: #11062b; }
        .form-input-inline { padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 0.85rem; width: 100%; max-width: 250px; background: #fdfdfd; }
        .time-range { display: flex; gap: 10px; align-items: center; }
        .time-range .form-input-inline { max-width: 110px; }

        .details-right { display: flex; flex-direction: column; gap: 15px; }
        .recap-header-wrapper { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .event-section-label-recap { margin: 0; color: #11062b; font-weight: bold; font-size: 1rem; }
        .gallery-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 15px; }
        .gallery-thumb { width: 100%; aspect-ratio: 1; border-radius: 5px; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; cursor: pointer; }
        .gallery-thumb:hover { transform: scale(1.05); }

        .attendance-actions { display: flex; gap: 15px; margin-top: auto; padding-top: 15px; }
        .btn-attended, .btn-not-attended { flex: 1; padding: 12px; border: none; border-radius: 5px; font-size: 0.9rem; cursor: pointer; font-weight: bold; color: white; transition: 0.3s; }
        .btn-attended { background-color: #5cf25c; color: #000; }
        .btn-attended:hover { background-color: #4ade4a; }
        .btn-not-attended { background-color: #d9534f; }
        .btn-not-attended:hover { background-color: #c9302c; }

        /* --- Interative FAQ Chat on Bottom Right --- */
        .faq {
            background: transparent; color: white; padding: 0; text-decoration: none; position: fixed;
            bottom: 1rem; right: 1rem; z-index: 1000; border: none; cursor: pointer; transition: transform 0.2s;
            border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .faq:hover { transform: scale(1.05); }
        
        /* FAQ Chat Window Styles */
        .faq-modal-content {
            width: min(430px, calc(100vw - 32px)); max-width: 430px; height: min(700px, calc(100vh - 32px));
            padding: 0; overflow: hidden; display: flex; flex-direction: column;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3); border-radius: 12px; background: white;
        }
        .faq-modal-header { background: #11062b; color: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .faq-modal-header h2 { margin: 0; color: white; font-size: 1.1rem; font-weight: bold; }
        .faq-modal-header .close-modal { top: 12px; right: 15px; color: rgba(255,255,255,0.7); }
        .faq-modal-header .close-modal:hover { color: white; }

        /* Internal Chat Styles replacing the iframe */
        .chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background-color: #f9f9fb; scroll-behavior: smooth; }
        .message { max-width: 85%; padding: 12px 16px; border-radius: 15px; font-size: 0.95rem; line-height: 1.4; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .bot-message { background-color: #e2e8f0; color: #333; align-self: flex-start; border-bottom-left-radius: 2px; }
        .user-message { background-color: #11062b; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        .options-area { background-color: #ffffff; padding: 10px 15px; border-top: 1px solid #eee; display: flex; gap: 10px; overflow-x: auto; white-space: nowrap; scrollbar-width: none; }
        .options-area::-webkit-scrollbar { display: none; }
        .option-btn { background-color: #f0ecf9; border: 1px solid #d4c5f0; color: #11062b; padding: 8px 15px; border-radius: 20px; font-size: 0.85rem; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0; }
        .option-btn:hover { background-color: #11062b; color: white; }
        .chat-input-area { display: flex; padding: 15px; background-color: white; border-top: 1px solid #eee; }
        .chat-input-area input { flex: 1; padding: 12px 15px; border: 1px solid #ccc; border-radius: 25px; outline: none; font-size: 0.95rem; }
        .chat-input-area button { background-color: #11062b; color: white; border: none; width: 45px; height: 45px; border-radius: 50%; margin-left: 10px; cursor: pointer; font-size: 1.1rem; transition: background 0.3s; display: flex; justify-content: center; align-items: center; }
        .chat-input-area button:hover { background-color: #2b1154; }

        /* --- Send Message Admin Modal --- */
        .message-admin-btn { background-color: transparent; border: none; padding: 0; margin: 0; cursor: pointer; transition: transform 0.2s; }
        .message-admin-btn:hover { transform: scale(1.1); }
        .send-message-content { background: #11062b; color: white; padding: 40px; border-radius: 15px; width: 100%; max-width: 450px; position: relative; }
        .send-message-content h2 { margin-bottom: 5px; color: white; text-transform: uppercase; font-size: 1.5rem; letter-spacing: 0.5px; }
        .send-message-divider { height: 4px; background: white; width: 100%; margin-bottom: 25px; border-radius: 2px; }
        .send-message-content .close-modal { color: white; }
        .send-message-content .close-modal:hover { color: #ccc; }
        .send-message-form { display: flex; flex-direction: column; gap: 15px; }
        .send-message-group { display: flex; align-items: center; gap: 10px; }
        .send-message-group label { width: 60px; font-size: 0.9rem; font-weight: bold; color: white; }
        .send-message-group input, .send-message-form textarea { flex: 1; padding: 10px 15px; border-radius: 8px; border: none; outline: none; color: #333; }
        .send-message-form textarea { width: 100%; height: 120px; padding: 15px; border-radius: 12px; border: none; outline: none; resize: none; color: #333; font-family: inherit; margin-top: 5px; }
        .send-message-form textarea::placeholder { color: #999; }
        .send-message-submit-wrapper { display: flex; justify-content: center; margin-top: 15px; }
        .send-message-submit-wrapper button { background: white !important; color: #11062b !important; border: none; padding: 10px 24px !important; border-radius: 20px !important; font-size: 0.85rem !important; font-weight: bold; cursor: pointer; transition: 0.3s; width: auto !important; }
        .send-message-submit-wrapper button:hover { background: #eee !important; }

    </style>
</head>
<body>

    <header class="header-container">
            <a href="<?php echo $isVisitor ? 'visitor.php' : 'admin.php'; ?>" class="back-btn">
            <?php echo $isVisitor ? 'Exit' : 'Back'; ?>
        </a>        <div class="header-wave"></div>


    </header>

    <main class="main-content">
        <h2 class="events-title">Events</h2>
        
        <div class="cards-grid">
            <?php if (empty($upcomingEvents)): ?>
                <p class="empty-state"><?php echo $isVisitor ? 'No events listed yet.' : 'No events assigned to you yet.'; ?></p>
            <?php else: ?>
                <?php foreach ($upcomingEvents as $event): ?>
                    <div class="event-card">
                        <div class="event-card-image-wrapper">
                            <img src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?> Poster">
                            <?php if (!$isVisitor): ?>
                                <button
                                    type="button"
                                    class="event-edit-icon edit-event-btn"
                                    title="Edit event"
                                    aria-label="Edit <?php echo htmlspecialchars($event['title']); ?>"
                                    data-id="<?php echo (int)$event['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($event['title'], ENT_QUOTES); ?>"
                                    data-desc="<?php echo htmlspecialchars($event['description'], ENT_QUOTES); ?>"
                                    data-room="<?php echo htmlspecialchars($event['room'], ENT_QUOTES); ?>"
                                    data-date="<?php echo htmlspecialchars($event['date'], ENT_QUOTES); ?>"
                                    data-start="<?php echo htmlspecialchars($event['start_time'], ENT_QUOTES); ?>"
                                    data-end="<?php echo htmlspecialchars($event['end_time'], ENT_QUOTES); ?>"
                                >
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                            <?php endif; ?>
                            <div class="event-card-body">
                                <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p><?php echo htmlspecialchars($event['description']); ?></p>
                                <span class="event-assigned-staff">Staff: <?php echo htmlspecialchars($event['assigned_staff_name'] ?? 'Unassigned'); ?></span>
                            </div>
                        </div>
                        <div class="event-card-footer">
                                <a href="event_details.php?id=<?php echo $event['id']; ?>" class="details-btn" style="text-decoration: none;"><?php echo $isVisitor ? 'View Details' : 'View / Edit Details'; ?></a>
                                <?php if (!$isVisitor): ?>
                                    <a href="admin_event.php?id=<?php echo (int)$event['id']; ?>" class="btn btn-outline" style="margin-right: 10px; text-decoration: none;">
                                        Edit &amp; Manage Event
                                    </a>
                                <?php endif; ?>
                                <?php if (!$isVisitor): ?>
                                <form action="events.php" method="POST" style="display:inline;">

                                <input type="hidden" name="action" value="join_event">

                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                <button type="submit" class="join-btn" <?php echo $event['user_joined'] ? 'disabled' : ''; ?>>
                                    <?php echo $event['user_joined'] ? 'Joined' : 'JOIN'; ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="footer"></footer>

    <button class="faq" id="faqBtn" type="button" style="background: #11062b; width: 65px; height: 65px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="white" viewBox="0 0 16 16">
            <path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.01.388-1.01.94z"/>
            <path d="M14 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H4.414A2 2 0 0 0 3 11.586l-2 2V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12.793a.5.5 0 0 0 .854.353l2.853-2.853A1 1 0 0 1 4.414 12H14a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
        </svg>
    </button>
    
    <?php if (!empty($message) || isset($_GET['success'])): 
        $displayMsg = isset($_GET['success']) ? $_GET['success'] : $message;
        $displayType = isset($_GET['success']) ? 'success' : 'Notice';
    ?>
        <div id="messageModal" class="modal-overlay" style="display: flex;">
            <div class="modal-content">
                <span class="close-modal" id="closeMessageModal">&times;</span>
                <h2><?php echo $displayType; ?></h2>
                <p><?php echo htmlspecialchars($displayMsg); ?></p>
                <button type="button" id="messageModalOkBtn" class="btn-request-room">OK</button>
            </div>
        </div>
    <?php endif; ?>

    <div id="faqModal" class="modal-overlay">
        <div class="faq-modal-content">
            <div class="faq-modal-header">
                <h2>FAQ Assistant</h2>
                <span class="close-modal" id="closeFaqModal">&times;</span>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="message bot-message">Hello! Choose an option below or type your question.</div>
            </div>
            
            <div class="options-area" id="optionsArea">
                <button type="button" class="option-btn" data-id="q_invite">Get Invitation Code</button>
                <button type="button" class="option-btn" data-id="q_contact">Contact Staff</button>
                <button type="button" class="option-btn" data-id="q_password">Reset Password</button>
                <button type="button" class="option-btn" data-id="q_events">Events</button>
            </div>

            <form class="chat-input-area" id="chatForm">
                <input type="text" id="userInput" placeholder="Type your question..." autocomplete="off">
                <button type="submit"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </div>
    </div>

    <div id="sendMessageModal" class="modal-overlay">
        <div class="send-message-content">
            <span class="close-modal" id="closeSendMessageModal">&times;</span>
            <h2>SEND MESSAGE</h2>
            <div class="send-message-divider"></div>
            
            <form class="send-message-form" action="user.php" method="POST">
                <input type="hidden" name="action" value="send_admin_message">
                <div class="send-message-group">
                    <label for="msg_name">Name:</label>
                    <input type="text" id="msg_name" name="msg_name" value="<?php echo htmlspecialchars($userName); ?>" required>
                </div>
                <div class="send-message-group">
                    <label for="msg_email">Email:</label>
                    <input type="email" id="msg_email" name="msg_email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>" required>
                </div>
                <textarea name="msg_content" placeholder="Enter Message..." required></textarea>
                <div class="send-message-submit-wrapper">
                    <button type="submit">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$isVisitor): ?>
    <div id="editEventModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeEditEventModal">&times;</span>
            <h2>Edit Event Details</h2>
            <form action="events.php" method="POST">
                <input type="hidden" name="action" value="edit_event">
                <input type="hidden" name="event_id" id="editEventId">
                <input type="text" name="title" id="editEventTitle" placeholder="Event Title" required>
                <textarea name="description" id="editEventDesc" rows="4" placeholder="Event Description" required></textarea>
                <input type="text" name="room" id="editEventRoom" placeholder="Room/Location" required>
                <input type="date" name="date" id="editEventDate" required>
                <div style="display:flex; gap:10px;">
                    <input type="time" name="start_time" id="editEventStart" style="flex:1;" required>
                    <input type="time" name="end_time" id="editEventEnd" style="flex:1;" required>
                </div>
                <button type="submit">Update Event</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="eventDetailsModal" class="modal-overlay">
        <div class="modal-content event-details-content">
            <div class="event-details-header">
                <h2>Event Details</h2>
                <span class="close-modal" id="closeEventDetailsModal">&times;</span>
            </div>
            <div class="event-details-body">
                <div class="details-left">
                    <img id="modalEventPoster" src="" alt="Event Poster" class="modal-event-poster">
                    <strong id="modalEventTitle" class="event-info-title"></strong>
                    <strong class="event-section-label">Description</strong>
                    <p id="modalEventDescription" class="modal-event-description"></p>
                    
                    <table class="event-meta-table">
                        <tr>
                            <td>Room:</td>
                            <td><input type="text" id="modalRoom" readonly class="form-input-inline"></td>
                        </tr>
                        <tr>
                            <td>Date:</td>
                            <td><input type="text" id="modalDate" readonly class="form-input-inline" placeholder="MM / DD / YYYY"></td>
                        </tr>
                        <tr>
                            <td>Time:</td>
                            <td class="time-range">
                                <input type="text" id="modalStartTime" readonly class="form-input-inline" placeholder="Start Time">
                                <span>-</span>
                                <input type="text" id="modalEndTime" readonly class="form-input-inline" placeholder="End Time">
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="details-right">
                    <div class="recap-header-wrapper">
                        <strong class="event-section-label-recap">Event Recap</strong>
                        <button class="message-admin-btn" id="messageAdminBtnModal" type="button">
                            <img src="smsicon.svg" width="40" height="40" alt="Send Message">
                        </button>
                    </div>
                    
                    <div id="galleryGrid" class="gallery-grid">
                        </div>

                    <div id="modalAttendanceActions" class="attendance-actions" style="display:none;">
                        <strong class="event-section-label">Mark Attendance</strong>
                        <div style="display: flex; gap: 15px; width: 100%;">
                            <button class="btn-attended" onclick="markEventAttendance('Attended')">Attended</button>
                            <button class="btn-not-attended" onclick="markEventAttendance('Not Attended')">Not Attended</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- 1. General Modals Setup ---
        const messageModal = document.getElementById('messageModal');
        const closeMessageModalBtn = document.getElementById('closeMessageModal');
        const messageModalOkBtn = document.getElementById('messageModalOkBtn');
        const faqModal = document.getElementById('faqModal');
        const faqBtn = document.getElementById('faqBtn');
        const closeFaqModalBtn = document.getElementById('closeFaqModal');
        const sendMessageModal = document.getElementById('sendMessageModal');
        const messageAdminBtnModal = document.getElementById('messageAdminBtnModal');
        const closeSendMessageModalBtn = document.getElementById('closeSendMessageModal');
        const eventDetailsModal = document.getElementById('eventDetailsModal');
        const closeEventDetailsModalBtn = document.getElementById('closeEventDetailsModal');
        const editEventModal = document.getElementById('editEventModal');
        const closeEditEventModalBtn = document.getElementById('closeEditEventModal');
        const galleryGrid = document.getElementById('galleryGrid');

        let currentEventId = null;

        function closeGenericMessageModal() {
            if (messageModal) {
                messageModal.style.display = 'none';
                if (window.history.replaceState) {
                    const currentUrl = new URL(window.location.href);
                    if (currentUrl.searchParams.has('success')) {
                        currentUrl.searchParams.delete('success');
                        window.history.replaceState({}, '', currentUrl.pathname);
                    }
                }
            }
        }
        if (closeMessageModalBtn) closeMessageModalBtn.addEventListener('click', closeGenericMessageModal);
        if (messageModalOkBtn) messageModalOkBtn.addEventListener('click', closeGenericMessageModal);

        faqBtn.addEventListener('click', () => faqModal.style.display = 'flex');
        closeFaqModalBtn.addEventListener('click', () => faqModal.style.display = 'none');

        messageAdminBtnModal.addEventListener('click', () => {
            eventDetailsModal.style.display = 'none'; 
            sendMessageModal.style.display = 'flex';
        });
        closeSendMessageModalBtn.addEventListener('click', () => sendMessageModal.style.display = 'none');

        closeEventDetailsModalBtn.addEventListener('click', () => eventDetailsModal.style.display = 'none');

        if (closeEditEventModalBtn) {
            closeEditEventModalBtn.addEventListener('click', () => editEventModal.style.display = 'none');
        }

        document.querySelectorAll('.edit-event-btn').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('editEventId').value = button.dataset.id;
                document.getElementById('editEventTitle').value = button.dataset.title;
                document.getElementById('editEventDesc').value = button.dataset.desc;
                document.getElementById('editEventRoom').value = button.dataset.room;
                document.getElementById('editEventDate').value = button.dataset.date;
                document.getElementById('editEventStart').value = button.dataset.start;
                document.getElementById('editEventEnd').value = button.dataset.end;
                editEventModal.style.display = 'flex';
            });
        });

        function generatePlaceholderGallery() {
            galleryGrid.innerHTML = ''; 
            for (let i = 1; i <= 25; i++) {
                const img = document.createElement('img');
                img.src = `https://via.placeholder.com/100x100.png?text=Photo+${i}`;
                img.alt = `Recap Photo ${i}`;
                img.className = 'gallery-thumb';
                img.loading = 'lazy';
                img.onclick = () => window.open(img.src, '_blank');
                galleryGrid.appendChild(img);
            }
        }

        function openEventModal(event) {
            currentEventId = event.id;
            document.getElementById('modalEventPoster').src = event.image_path;
            document.getElementById('modalEventPoster').alt = `${event.title} Poster`;
            document.getElementById('modalEventTitle').innerText = event.title;
            document.getElementById('modalEventDescription').innerText = event.description;
            document.getElementById('modalRoom').value = event.room;
            document.getElementById('modalDate').value = event.date; 
            document.getElementById('modalStartTime').value = event.start_time; 
            document.getElementById('modalEndTime').value = event.end_time;

            generatePlaceholderGallery();

            const attendanceActions = document.getElementById('modalAttendanceActions');
            if (event.user_joined) {
                attendanceActions.style.display = 'flex';
                document.querySelector('.btn-attended').style.opacity = '1';
                document.querySelector('.btn-not-attended').style.opacity = '1';
            } else {
                attendanceActions.style.display = 'none';
            }

            eventDetailsModal.style.display = 'flex';
        }

        function markEventAttendance(status) {
            if (!currentEventId) return;
            
            const formData = new FormData();
            formData.append('action', 'mark_event_attendance');
            formData.append('event_id', currentEventId);
            formData.append('status', status);

            fetch('events.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (status === 'Attended') {
                        document.querySelector('.btn-attended').style.opacity = '1';
                        document.querySelector('.btn-not-attended').style.opacity = '0.5';
                    } else if (status === 'Not Attended') {
                        document.querySelector('.btn-attended').style.opacity = '0.5';
                        document.querySelector('.btn-not-attended').style.opacity = '1';
                    }
                } else {
                    alert('Error updating event attendance.');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Close when clicking outside of Modals
        window.addEventListener('click', (e) => {
            if (e.target === messageModal) closeGenericMessageModal();
            if (e.target === faqModal) faqModal.style.display = 'none';
            if (e.target === sendMessageModal) sendMessageModal.style.display = 'none';
            if (e.target === eventDetailsModal) eventDetailsModal.style.display = 'none';
            if (e.target === editEventModal) editEventModal.style.display = 'none';
        });

        // --- 2. FAQ Chat Script ---
        const chatMessages = document.getElementById('chatMessages');
        const optionButtons = document.querySelectorAll('.option-btn');
        const chatForm = document.getElementById('chatForm');
        const userInput = document.getElementById('userInput');

        function appendMessage(text, sender) {
            const msgDiv = document.createElement('div');
            msgDiv.classList.add('message', sender === 'user' ? 'user-message' : 'bot-message');
            msgDiv.textContent = text;
            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        async function sendMessageToServer(payload) {
            const typingIndicator = document.createElement('div');
            typingIndicator.classList.add('message', 'bot-message');
            typingIndicator.textContent = "Typing...";
            chatMessages.appendChild(typingIndicator);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                // Fetch to backend endpoint
                const response = await fetch('faq-chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                chatMessages.removeChild(typingIndicator);
                appendMessage(data.reply, 'bot');
            } catch (error) {
                chatMessages.removeChild(typingIndicator);
                appendMessage("Sorry, I'm having trouble connecting to the server.", 'bot');
            }
        }

        optionButtons.forEach(button => {
            button.addEventListener('click', function() {
                const questionText = this.textContent;
                const questionId = this.getAttribute('data-id');

                appendMessage(questionText, 'user');
                sendMessageToServer({ id: questionId }); 
            });
        });

        chatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const message = userInput.value.trim();
            if (!message) return;

            appendMessage(message, 'user'); 
            userInput.value = ''; 
            
            sendMessageToServer({ message: message }); 
        });
    </script>
</body>
</html>
