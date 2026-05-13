<?php
session_start();
require_once "db.php";

$userName = $_SESSION['user_name'] ?? "User";
$userId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$messageType = 'error';
$upcomingMeetings = [];
$endedMeetings = [];
$meetingHistory = [];
$myMeetingRequests = [];
$eventDays = [];
$calendarMeetings = [];
$requestedMonth = $_GET['month'] ?? '';
$calendarDate = preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
    ? DateTime::createFromFormat('!Y-m-d', $requestedMonth . '-01')
    : new DateTime('first day of this month');
$todayDate = new DateTime();
$calendarMonth = $calendarDate->format('F Y');
$calendarYear = (int)$calendarDate->format('Y');
$calendarMonthNumber = (int)$calendarDate->format('m');
$daysInMonth = (int)$calendarDate->format('t');
$todayDay = ($calendarDate->format('Y-m') === $todayDate->format('Y-m')) ? (int)$todayDate->format('j') : 0;
$firstDayOfMonth = new DateTime($calendarDate->format('Y-m-01'));
$leadingEmptyDays = ((int)$firstDayOfMonth->format('N')) - 1;
$prevMonth = (clone $calendarDate)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $calendarDate)->modify('+1 month')->format('Y-m');

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'mark_attendance') {
    header('Content-Type: application/json');

    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    $attendanceStatus = $_POST['status'] ?? '';

    if ($userId <= 0 || $meetingId <= 0 || !in_array($attendanceStatus, ['Attended', 'Not Attended'], true)) {
        echo json_encode(['success' => false]);
        exit();
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO meeting_attendance (meeting_id, user_id, attendance_status)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE attendance_status = VALUES(attendance_status)"
        );
        $stmt->bind_param("iis", $meetingId, $userId, $attendanceStatus);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['success' => false]);
    }

    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'request_room') {
    $title = trim($_POST['meeting_title'] ?? '');
    $pic = trim($_POST['meeting_pic'] ?? '');
    $attendees = (int)($_POST['meeting_attendees'] ?? 0);
    $date = trim($_POST['meeting_date'] ?? '');
    $startTime = trim($_POST['meeting_start_time'] ?? '');
    $endTime = trim($_POST['meeting_end_time'] ?? '');
    $room = trim($_POST['meeting_room'] ?? '');

    if ($userId <= 0) {
        $message = "Please log in before requesting a room.";
    } elseif ($title === '' || $pic === '' || $attendees <= 0 || $date === '' || $startTime === '' || $endTime === '' || $room === '') {
        $message = "Please fill in all request fields.";
    } elseif ($endTime <= $startTime) {
        $message = "End time must be after start time.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO meeting_requests (requester_id, title, pic, attendees, room, date, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississss", $userId, $title, $pic, $attendees, $room, $date, $startTime, $endTime);
            $stmt->execute();
            $stmt->close();

            header("Location: user.php?success=Room request submitted");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not submit the room request.";
        }
    }
}

if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $messageType = 'success';
}

try {
    $stmt = $conn->prepare(
        "SELECT m.id, m.status, m.title, m.pic, m.attendees, m.room, m.date, m.start_time, m.end_time,
                ma.attendance_status
         FROM meetings m
         LEFT JOIN meeting_attendance ma ON ma.meeting_id = m.id AND ma.user_id = ?
         ORDER BY m.date ASC, m.start_time ASC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dateObj = new DateTime($row['date']);
            $startObj = new DateTime($row['start_time']);
            $endObj = new DateTime($row['end_time']);

            $meeting = [
                'id' => $row['id'],
                'title' => $row['title'],
                'pic' => $row['pic'],
                'attendees' => $row['attendees'],
                'room' => $row['room'],
                'date' => $dateObj->format('d M Y'),
                'raw_date' => $dateObj->format('Y-m-d'),
                'time' => $startObj->format('g:ia') . ' - ' . $endObj->format('g:ia'),
                'attendance_status' => $row['attendance_status'] ?? ''
            ];

            if ((int)$dateObj->format('Y') === $calendarYear && (int)$dateObj->format('m') === $calendarMonthNumber) {
                $eventDay = (int)$dateObj->format('j');
                $eventDays[] = $eventDay;
                $calendarMeetings[$eventDay][] = $meeting;
            }

            $isPastMeeting = $row['status'] === 'Ended' || $dateObj->format('Y-m-d') < $todayDate->format('Y-m-d');

            if ($isPastMeeting && ($row['attendance_status'] ?? '') === 'Attended') {
                $meetingHistory[] = $meeting;
            }

            if ($isPastMeeting) {
                $endedMeetings[] = $meeting;
            } else {
                $upcomingMeetings[] = $meeting;
            }
        }
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $upcomingMeetings = [];
    $endedMeetings = [];
    $meetingHistory = [];
    $eventDays = [];
}

try {
    $stmt = $conn->prepare(
        "SELECT id, status, title, room, date, start_time, end_time
         FROM meeting_requests
         WHERE requester_id = ?
         ORDER BY created_at DESC
         LIMIT 5"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dateObj = new DateTime($row['date']);
            $startObj = new DateTime($row['start_time']);
            $endObj = new DateTime($row['end_time']);

            $row['formatted_date'] = $dateObj->format('d M Y');
            $row['formatted_time'] = $startObj->format('g:ia') . ' - ' . $endObj->format('g:ia');
            $myMeetingRequests[] = $row;
        }
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    $myMeetingRequests = [];
}

$eventDays = array_unique($eventDays);
$calendarMeetingsJson = json_encode($calendarMeetings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <style>
        /* Base Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #ffffff; color: #333; overflow-x: hidden; }

        /* --- Header & Wave Design --- */
        .header-container {
            position: relative;
            width: 100%;
            height: 120px; /* Height of the dark blue area */
            background-color: #11072b;
            color: white;
            padding: 40px 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        /* The smooth bottom wave using SVG */
        .header-wave {
            position: absolute;
            top: 80%; /* Positions the wave exactly below the header */
            left: 0;
            width: 100%;
            height: 100px;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L1000,0 L1000,30 C750,10 250,120 0,80 Z' fill='%2311072b'/%3E%3C/svg%3E");
            background-size: 100% 100%;
        }

        .header-container h1 {
            font-size: 2.5rem;
            font-weight: 400;
            z-index: 2;
        }
        
        .logout-btn {
            background-color: white;
            color: #11062b;
            padding: 10px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1rem;
            z-index: 2;
        }

        /* --- Main Layout --- */
        .main-content {
            display: flex;
            padding: 50px 60px;
            gap: 40px;
            margin-top: 50px;
        }

        .left-panel { flex: 2; }
        
        .divider { width: 1px; background-color: #d1d1d1; margin: 0 10px; }
        
        .right-panel { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 24px; }

        .request-toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-bottom: 24px;
        }

        .btn-request-room {
            background-color: #11072b;
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
        }

        /* --- Meeting Cards --- */
        .section-title { font-size: 1.4rem; margin-bottom: 20px; margin-top: 10px; color: #000; }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .meeting-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            gap: 5px;
            border-top: 5px solid #11062b;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .meeting-card:hover {
            transform: translateY(-5px);
        }

        .meeting-card h3 { font-size: 1.2rem; margin-bottom: 8px; color: #000; }
        .meeting-card p { font-size: 0.8rem; color: #444; line-height: 1.4; }

        /* --- Calendar --- */
        .calendar-container {
            background-color: #3f517e;
            width: 100%;
            max-width: 380px;
            border-radius: 5px;
            padding: 25px 30px;
            color: white;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            margin: -20px auto 0;
        }

        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 1.6rem;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .calendar-header a {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
        }

        .calendar-header a:hover {
            background-color: rgba(255, 255, 255, 0.18);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 15px 5px;
            text-align: center;
        }

        .day-name { color: #8fa2c9; font-size: 0.8rem; font-weight: bold; margin-bottom: 10px; }

        .calendar-day {
            position: relative;
            font-size: 1rem;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
            cursor: pointer;
        }

        .calendar-day:not(.has-event) {
            cursor: default;
        }

        .calendar-day.has-event:hover {
            background-color: rgba(255, 255, 255, 0.16);
            border-radius: 50%;
        }

        .calendar-day.has-event {
            border: none;
            color: white;
            background: transparent;
            font: inherit;
        }

        .calendar-day.dimmed { color: rgba(255, 255, 255, 0.4); }

        .calendar-day.active {
            background-color: #627bff;
            border-radius: 50%;
            font-weight: bold;
        }

        /* The small dot indicator under dates */
        .calendar-day.has-event::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            background-color: #a4b5d6;
            border-radius: 50%;
        }

        /* Active day has a white dot */
        .calendar-day.active.has-event::after { background-color: white; }

        .request-status-panel {
            width: 100%;
            max-width: 380px;
        }

        .request-status-panel h2 {
            color: #11072b;
            font-size: 1.25rem;
            margin-bottom: 14px;
        }

        .request-status-card {
            background: white;
            border-top: 5px solid #11062b;
            border-radius: 8px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.16);
            padding: 14px;
            margin-bottom: 12px;
        }

        .request-status-card h3 {
            font-size: 1rem;
            color: #11072b;
            margin-bottom: 6px;
        }

        .request-status-card p {
            color: #444;
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .request-status {
            display: inline-block;
            border-radius: 12px;
            padding: 4px 9px;
            font-size: 0.68rem;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .request-status.Pending { background: #fff3cd; color: #664d03; }
        .request-status.Approved { background: #d1e7dd; color: #0f5132; }
        .request-status.Rejected { background: #f8d7da; color: #842029; }

        /* --- Footer --- */
        .footer { padding: 0 60px 40px 60px; }
        .footer a { color: #11062b; font-size: 1rem; text-decoration: underline; }

        /* --- Modal Styles --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            position: relative;
        }

        .close-modal { position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 1.5rem; color: #333; }
        .close-modal:hover { color: #d9534f; }

        .modal-content h2 { margin-bottom: 20px; color: #11072b; }
        .modal-content form { display: flex; flex-direction: column; gap: 15px; }
        .modal-content input { padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        .modal-content form button { background-color: #11072b; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        
        .modal-meeting-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .modal-actions { display: flex; gap: 15px; justify-content: space-between; }
        .modal-actions button { flex: 1; padding: 12px; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; color: white; transition: 0.3s; }
        .btn-attended { background-color: #5cf25c; color: #000 !important; font-weight: bold; }
        .btn-attended:hover { background-color: #4ade4a; }
        .btn-not-attended { background-color: #d9534f; }
        .btn-not-attended:hover { background-color: #c9302c; }
        .calendar-meeting-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .calendar-meeting-item {
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            padding: 12px;
            text-align: left;
            cursor: pointer;
            color: #333;
        }
        .calendar-meeting-item:hover {
            border-color: #11072b;
            background: #f7f5fb;
        }
        .calendar-meeting-item strong {
            display: block;
            color: #11072b;
            margin-bottom: 4px;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .history-item {
            border: 1px solid #ddd;
            border-left: 5px solid #11072b;
            border-radius: 8px;
            padding: 14px;
        }

        .history-item h3 {
            color: #11072b;
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .history-item p {
            color: #444;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        /* --- Badges & Empty States --- */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-bottom: 8px;
            width: fit-content;
        }
        .badge-attended { background-color: #d1e7dd; color: #0f5132; }
        .badge-not-attended { background-color: #f8d7da; color: #842029; }
        .empty-state { color: #666; font-style: italic; grid-column: 1 / -1; }

    </style>
</head>
<body>

    <header class="header-container">
        <h1>Hello, {<?php echo $userName; ?>}</h1>
        <a href="login.php" class="logout-btn">Logout</a>
        <div class="header-wave"></div>
    </header>

    <main class="main-content">
        
        <section class="left-panel">
            <div class="request-toolbar">
                <button type="button" id="requestRoomBtn" class="btn-request-room">Request Room</button>
                <button type="button" id="meetingHistoryBtn" class="btn-request-room">Meeting History</button>
            </div>
            
            <h2 class="section-title">Upcoming</h2>
            <div class="cards-grid">
                <?php if (empty($upcomingMeetings)): ?>
                    <p class="empty-state">No upcoming meetings at this time.</p>
                <?php else: ?>
                    <?php foreach ($upcomingMeetings as $meeting): ?>
                        <div class="meeting-card" onclick='openMeetingModal(<?php echo htmlspecialchars(json_encode($meeting), ENT_QUOTES); ?>)'>
                            <?php if ($meeting['attendance_status'] === 'Attended'): ?>
                                <span class="badge badge-attended">Attended</span>
                            <?php elseif ($meeting['attendance_status'] === 'Not Attended'): ?>
                                <span class="badge badge-not-attended">Not Attended</span>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($meeting['title']); ?></h3>
                            <p><strong>PIC:</strong> <?php echo htmlspecialchars($meeting['pic']); ?></p>
                            <p><strong>Expected Attendees:</strong> <?php echo htmlspecialchars($meeting['attendees']); ?></p>
                            <p><strong>Room:</strong> <?php echo htmlspecialchars($meeting['room']); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                            <p><strong>Time:</strong> <?php echo htmlspecialchars($meeting['time']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2 class="section-title">Ended</h2>
            <div class="cards-grid">
                <?php if (empty($endedMeetings)): ?>
                    <p class="empty-state">No ended meetings to display.</p>
                <?php else: ?>
                    <?php foreach ($endedMeetings as $meeting): ?>
                        <div class="meeting-card" onclick='openMeetingModal(<?php echo htmlspecialchars(json_encode($meeting), ENT_QUOTES); ?>)'>
                            <?php if ($meeting['attendance_status'] === 'Attended'): ?>
                                <span class="badge badge-attended">Attended</span>
                            <?php elseif ($meeting['attendance_status'] === 'Not Attended'): ?>
                                <span class="badge badge-not-attended">Not Attended</span>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($meeting['title']); ?></h3>
                            <p><strong>PIC:</strong> <?php echo htmlspecialchars($meeting['pic']); ?></p>
                            <p><strong>Expected Attendees:</strong> <?php echo htmlspecialchars($meeting['attendees']); ?></p>
                            <p><strong>Room:</strong> <?php echo htmlspecialchars($meeting['room']); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                            <p><strong>Time:</strong> <?php echo htmlspecialchars($meeting['time']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </section>

        <div class="divider"></div>

        <section class="right-panel">
            <div class="calendar-container">
                <div class="calendar-header">
                    <a href="user.php?month=<?php echo htmlspecialchars($prevMonth); ?>" aria-label="Previous month">&lt;</a>
                    <span><?php echo htmlspecialchars($calendarMonth); ?></span>
                    <a href="user.php?month=<?php echo htmlspecialchars($nextMonth); ?>" aria-label="Next month">&gt;</a>
                </div>
                <div class="calendar-grid">
                    <div class="day-name">M</div><div class="day-name">T</div>
                    <div class="day-name">W</div><div class="day-name">T</div>
                    <div class="day-name">F</div><div class="day-name">S</div><div class="day-name">S</div>

                    <?php for ($i = 0; $i < $leadingEmptyDays; $i++): ?>
                        <div class="calendar-day dimmed"></div>
                    <?php endfor; ?>

                    <?php for ($i = 1; $i <= $daysInMonth; $i++): 
                        $classes = "calendar-day";
                        if ($i == $todayDay) $classes .= " active"; // Highlight today
                        if (in_array($i, $eventDays)) $classes .= " has-event"; // Add dot
                        $eventCount = isset($calendarMeetings[$i]) ? count($calendarMeetings[$i]) : 0;
                    ?>
                        <?php if ($eventCount > 0): ?>
                            <button
                                type="button"
                                class="<?php echo $classes; ?>"
                                data-event-day="<?php echo $i; ?>"
                                title="<?php echo $eventCount; ?> meeting<?php echo $eventCount > 1 ? 's' : ''; ?>"
                            >
                                <?php echo $i; ?>
                            </button>
                        <?php else: ?>
                            <div class="<?php echo $classes; ?>"><?php echo $i; ?></div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="request-status-panel">
                <h2>My Requests</h2>
                <?php if (empty($myMeetingRequests)): ?>
                    <p class="empty-state">No room requests yet.</p>
                <?php else: ?>
                    <?php foreach ($myMeetingRequests as $request): ?>
                        <div class="request-status-card">
                            <span class="request-status <?php echo htmlspecialchars($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                            <h3><?php echo htmlspecialchars($request['title']); ?></h3>
                            <p><strong>Room:</strong> <?php echo htmlspecialchars($request['room']); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($request['formatted_date']); ?></p>
                            <p><strong>Time:</strong> <?php echo htmlspecialchars($request['formatted_time']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <footer class="footer">
    </footer>

    <?php if (!empty($message)): ?>
        <div id="messageModal" class="modal-overlay" style="display: flex;">
            <div class="modal-content">
                <span class="close-modal" id="closeMessageModal">&times;</span>
                <h2><?php echo $messageType === 'success' ? 'Success' : 'Notice'; ?></h2>
                <p style="line-height: 1.5; margin-bottom: 20px;"><?php echo htmlspecialchars($message); ?></p>
                <button type="button" id="messageModalOkBtn" class="btn-request-room" style="width: 100%;">OK</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Request Room Modal -->
    <div id="requestRoomModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeRequestRoomModal">&times;</span>
            <h2>Request Room</h2>
            <form action="user.php" method="POST">
                <input type="hidden" name="action" value="request_room">
                <input type="text" name="meeting_title" placeholder="Meeting Title" required>
                <input type="text" name="meeting_pic" placeholder="Person In Charge (PIC)" value="<?php echo htmlspecialchars($userName); ?>" required>
                <input type="number" name="meeting_attendees" placeholder="Expected Attendees" min="1" required>
                <input type="text" name="meeting_room" placeholder="Requested Room Number" required>
                <input type="date" name="meeting_date" required>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="request_start_time" style="flex-shrink: 0;">From:</label>
                    <input id="request_start_time" type="time" name="meeting_start_time" required style="width: 100%;">
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label for="request_end_time" style="flex-shrink: 0;">To:</label>
                    <input id="request_end_time" type="time" name="meeting_end_time" required style="width: 100%;">
                </div>
                <button type="submit">Submit Request</button>
            </form>
        </div>
    </div>

    <!-- Calendar Day Meetings Modal -->
    <div id="calendarMeetingsModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeCalendarMeetingsModal">&times;</span>
            <h2 id="calendarMeetingsTitle">Meetings</h2>
            <div id="calendarMeetingsList" class="calendar-meeting-list"></div>
        </div>
    </div>

    <!-- Meeting History Modal -->
    <div id="meetingHistoryModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeMeetingHistoryModal">&times;</span>
            <h2>Meeting History</h2>
            <div class="history-list">
                <?php if (empty($meetingHistory)): ?>
                    <p class="empty-state">No attended past meetings yet.</p>
                <?php else: ?>
                    <?php foreach ($meetingHistory as $meeting): ?>
                        <div class="history-item">
                            <span class="badge badge-attended">Attended</span>
                            <h3><?php echo htmlspecialchars($meeting['title']); ?></h3>
                            <p><?php echo htmlspecialchars($meeting['date']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Meeting Details Modal -->
    <div id="meetingDetailsModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="closeMeetingDetailsModal">&times;</span>
            <h2 id="modalMeetingTitle">Meeting Details</h2>
            <div id="modalMeetingInfo" class="modal-meeting-info">
                <p><strong>PIC:</strong> <span id="modalPic"></span></p>
                <p><strong>Expected Attendees:</strong> <span id="modalAttendees"></span></p>
                <p><strong>Room:</strong> <span id="modalRoom"></span></p>
                <p><strong>Date:</strong> <span id="modalDate"></span></p>
                <p><strong>Time:</strong> <span id="modalTime"></span></p>
            </div>
            <div class="modal-actions">
                <button class="btn-attended">Attended</button>
                <button class="btn-not-attended">Not Attended</button>
            </div>
        </div>
    </div>

    <script>
        const meetingModal = document.getElementById('meetingDetailsModal');
        const closeModalBtn = document.getElementById('closeMeetingDetailsModal');
        const calendarMeetings = <?php echo $calendarMeetingsJson ?: '{}'; ?>;
        const calendarMeetingsModal = document.getElementById('calendarMeetingsModal');
        const closeCalendarMeetingsModalBtn = document.getElementById('closeCalendarMeetingsModal');
        const calendarMeetingsTitle = document.getElementById('calendarMeetingsTitle');
        const calendarMeetingsList = document.getElementById('calendarMeetingsList');
        const requestRoomModal = document.getElementById('requestRoomModal');
        const requestRoomBtn = document.getElementById('requestRoomBtn');
        const closeRequestRoomModalBtn = document.getElementById('closeRequestRoomModal');
        const meetingHistoryModal = document.getElementById('meetingHistoryModal');
        const meetingHistoryBtn = document.getElementById('meetingHistoryBtn');
        const closeMeetingHistoryModalBtn = document.getElementById('closeMeetingHistoryModal');
        const messageModal = document.getElementById('messageModal');
        const closeMessageModalBtn = document.getElementById('closeMessageModal');
        const messageModalOkBtn = document.getElementById('messageModalOkBtn');
        let currentMeetingId = null;

        requestRoomBtn.addEventListener('click', () => {
            requestRoomModal.style.display = 'flex';
        });

        closeRequestRoomModalBtn.addEventListener('click', () => {
            requestRoomModal.style.display = 'none';
        });

        meetingHistoryBtn.addEventListener('click', () => {
            meetingHistoryModal.style.display = 'flex';
        });

        closeMeetingHistoryModalBtn.addEventListener('click', () => {
            meetingHistoryModal.style.display = 'none';
        });

        if (messageModal && window.history.replaceState) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('success')) {
                currentUrl.searchParams.delete('success');
                window.history.replaceState({}, '', currentUrl.pathname + currentUrl.search + currentUrl.hash);
            }
        }

        function closeMessageModal() {
            if (messageModal) {
                messageModal.style.display = 'none';
            }
        }

        if (closeMessageModalBtn) {
            closeMessageModalBtn.addEventListener('click', closeMessageModal);
        }
        if (messageModalOkBtn) {
            messageModalOkBtn.addEventListener('click', closeMessageModal);
        }

        document.querySelectorAll('[data-event-day]').forEach((dayButton) => {
            dayButton.addEventListener('click', () => {
                const meetings = calendarMeetings[dayButton.dataset.eventDay] || [];

                if (meetings.length === 1) {
                    openMeetingModal(meetings[0]);
                    return;
                }

                calendarMeetingsTitle.innerText = meetings.length && meetings[0].date
                    ? `Meetings on ${meetings[0].date}`
                    : 'Meetings';
                calendarMeetingsList.innerHTML = '';

                meetings.forEach((meeting) => {
                    const button = document.createElement('button');
                    const title = document.createElement('strong');
                    const meta = document.createElement('span');

                    button.type = 'button';
                    button.className = 'calendar-meeting-item';
                    title.textContent = meeting.title;
                    meta.textContent = `${meeting.time} - Room ${meeting.room}`;
                    button.appendChild(title);
                    button.appendChild(meta);
                    button.addEventListener('click', () => {
                        calendarMeetingsModal.style.display = 'none';
                        openMeetingModal(meeting);
                    });
                    calendarMeetingsList.appendChild(button);
                });

                calendarMeetingsModal.style.display = 'flex';
            });
        });

        function openMeetingModal(meeting) {
            currentMeetingId = meeting.id;
            document.getElementById('modalMeetingTitle').innerText = meeting.title;
            document.getElementById('modalPic').innerText = meeting.pic;
            document.getElementById('modalAttendees').innerText = meeting.attendees;
            document.getElementById('modalRoom').innerText = meeting.room;
            document.getElementById('modalDate').innerText = meeting.date;
            document.getElementById('modalTime').innerText = meeting.time;

            // Reset button styles
            document.querySelector('.btn-attended').style.opacity = '1';
            document.querySelector('.btn-not-attended').style.opacity = '1';

            if (meeting.attendance_status === 'Attended') {
                document.querySelector('.btn-not-attended').style.opacity = '0.5';
            } else if (meeting.attendance_status === 'Not Attended') {
                document.querySelector('.btn-attended').style.opacity = '0.5';
            }

            meetingModal.style.display = 'flex';
        }

        function markAttendance(status) {
            if (!currentMeetingId) return;

            const formData = new FormData();
            formData.append('action', 'mark_attendance');
            formData.append('meeting_id', currentMeetingId);
            formData.append('status', status);

            fetch('user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Reload to reflect changes
                } else {
                    alert('Error updating attendance.');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        document.querySelector('.btn-attended').addEventListener('click', () => markAttendance('Attended'));
        document.querySelector('.btn-not-attended').addEventListener('click', () => markAttendance('Not Attended'));

        closeModalBtn.addEventListener('click', () => meetingModal.style.display = 'none');
        closeCalendarMeetingsModalBtn.addEventListener('click', () => calendarMeetingsModal.style.display = 'none');
        window.addEventListener('click', (e) => {
            if (e.target === messageModal) closeMessageModal();
            if (e.target === requestRoomModal) requestRoomModal.style.display = 'none';
            if (e.target === meetingHistoryModal) meetingHistoryModal.style.display = 'none';
            if (e.target === calendarMeetingsModal) calendarMeetingsModal.style.display = 'none';
            if (e.target === meetingModal) meetingModal.style.display = 'none';
        });
    </script>
</body>
</html>
