<?php
session_start();
require_once "db.php";

// Only staff users are allowed to open this page.
if (($_SESSION['user_role'] ?? '') !== 'Staff') {
    header("Location: login.php?error=Please log in as staff to access that page");
    exit();
}

$userName = $_SESSION['user_name'] ?? "Staff";
$userId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$messageType = 'error';

$eventDays = [];

// Handle Reply to Message
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'reply_message') {
    $msgId = (int)($_POST['message_id'] ?? 0);
    $replyText = trim($_POST['reply_text'] ?? '');
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    
    // Here is where you would normally write mail() logic to send the email to the visitor.
    // For now, we mark it as handled (Read) in the database.
    if ($msgId > 0 && $replyText !== '') {
        $stmt = $conn->prepare("UPDATE admin_messages SET status = 'Read' WHERE id = ?");
        $stmt->bind_param("i", $msgId);
        $stmt->execute();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Reply successfully sent to the visitor!', 'message_id' => $msgId]);
            exit();
        }
        header("Location: user.php?success=Reply successfully sent to the visitor!");
        exit();
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please type a reply before sending.']);
        exit();
    }
}

// Handle Forward Message to Admin
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'forward_message') {
    $msgId = (int)($_POST['message_id'] ?? 0);
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if ($msgId > 0) {
        // We inject the staff's name into the subject line!
        $stmt = $conn->prepare("UPDATE admin_messages SET subject = CONCAT('[FORWARDED by ', ?, '] ', subject) WHERE id = ?");
        $stmt->bind_param("si", $userName, $msgId);
        $stmt->execute();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Message successfully forwarded to the Admin!', 'message_id' => $msgId]);
            exit();
        }
        header("Location: user.php?success=Message successfully forwarded to the Admin!");
        exit();
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Could not forward this message.']);
        exit();
    }
}

// Calendar Month Logic
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

if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $messageType = 'success';
}
// ----------------------------------------------------
// 1. LOAD EVENTS (ONLY FOR THIS SPECIFIC USER)
// ----------------------------------------------------
$events = [];
try {
    $stmt = $conn->prepare(
        "SELECT e.id, e.title, e.description, e.image_path, e.room, e.date, e.start_time, e.end_time, e.assigned_staff_id,
                u.fullname AS assigned_staff_name
         FROM events e
         LEFT JOIN users u ON e.assigned_staff_id = u.id
         WHERE e.assigned_staff_id = ?
         ORDER BY e.date ASC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
            // Record event days for the calendar
            if (strpos($row['date'], $calendarDate->format('Y-m')) === 0) {
                $eventDays[] = (int)(new DateTime($row['date']))->format('j');
            }
        }
    }
    $stmt->close();
} catch (Exception $e) {}

// ----------------------------------------------------
// 2. LOAD VISITOR MESSAGES (Inbox for Staff)
// ----------------------------------------------------
$visitorMessages = [];
try {
    // Show unread messages that haven't been forwarded to admin yet
    $sqlMessages = "SELECT id, sender_name, sender_email, subject, content, status, created_at
                    FROM admin_messages
                    WHERE status = 'Unread' AND subject NOT LIKE '[FORWARDED by %'
                    ORDER BY created_at DESC
                    LIMIT 10";
    $resMessages = $conn->query($sqlMessages);
    if ($resMessages && $resMessages->num_rows > 0) {
        while ($row = $resMessages->fetch_assoc()) {
            $dateObj = new DateTime($row['created_at']);
            $row['formatted_date'] = $dateObj->format('d M Y, g:ia');
            $visitorMessages[] = $row;
        }
    }
} catch (Exception $e) {}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#11072b">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #ffffff; color: #333; overflow-x: hidden; }

        /* Header */
        .header-container { position: relative; width: 100%; height: 120px; background-color: #11072b; color: white; padding: 40px 60px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-wave { position: absolute; top: 80%; left: 0; width: 100%; height: 100px; z-index: 1; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L1000,0 L1000,30 C750,10 250,120 0,80 Z' fill='%2311072b'/%3E%3C/svg%3E"); background-size: 100% 100%; }
        .header-container h1 { font-size: 2.5rem; font-weight: 400; z-index: 2; }
        .logout-btn { background-color: white; color: #11062b; padding: 10px 30px; border-radius: 25px; text-decoration: none; font-weight: bold; font-size: 1rem; z-index: 2; }

        /* Layout */
        .main-content { display: flex; padding: 50px 60px; gap: 40px; margin-top: 50px; }
        .left-panel { flex: 2; }
        .divider { width: 1px; background-color: #d1d1d1; margin: 0 10px; }
        .right-panel { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 24px; }

        /* Alerts */
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .alert-success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .alert-error { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }

        /* Toolbars & Buttons */
        .request-toolbar { display: flex; justify-content: flex-end; gap: 12px; margin-bottom: 24px; }
        .btn-request-room { background-color: #11072b; color: white; border: none; padding: 12px 28px; border-radius: 25px; font-size: 1rem; cursor: pointer; text-decoration: none; }

        /* Grid */
        .section-title { font-size: 1.4rem; margin-bottom: 20px; margin-top: 10px; color: #000; }
        .cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 40px; }
        
        /* Event Cards */
        .event-card { background-color: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); overflow: hidden; display: flex; flex-direction: column; border: 1px solid #eee; transition: 0.2s; cursor: pointer; }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        .event-card-image-wrapper { position: relative; width: 100%; padding-top: 70%; }
        .event-card-image-wrapper img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .event-card-body { padding: 20px; display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .event-card-body h3 { font-size: 1.25rem; color: #11062b; }
        .event-card-body p { font-size: 0.85rem; color: #555; }
        
        /* New Action Bar for Staff Event Cards */
        .event-actions { display: flex; border-top: 1px solid #eee; background: #fdfdfd; }
        .event-action-btn { flex: 1; padding: 12px; border: none; background: transparent; cursor: pointer; font-size: 0.9rem; font-weight: bold; color: #11062b; text-align: center; text-decoration: none; transition: 0.2s; display: inline-flex; justify-content: center; align-items: center; gap: 6px; }
        .event-action-btn:hover { background: #eee; }
        .event-action-btn:first-child { border-right: 1px solid #eee; }

        /* Calendar */
        .calendar-container { background-color: #3f517e; width: 100%; max-width: 380px; border-radius: 5px; padding: 25px 30px; color: white; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2); margin: -20px auto 0; }
        .calendar-header { display: flex; justify-content: space-between; font-size: 1.6rem; font-weight: bold; margin-bottom: 30px; }
        .calendar-header a { color: white; text-decoration: none; padding: 0 10px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px 5px; text-align: center; }
        .day-name { color: #8fa2c9; font-size: 0.8rem; font-weight: bold; margin-bottom: 10px; }
        .calendar-day { position: relative; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; margin: auto; }
        .calendar-day.dimmed { color: rgba(255, 255, 255, 0.4); }
        .calendar-day.active { background-color: #627bff; border-radius: 50%; font-weight: bold; }
        .calendar-day.has-event { cursor: pointer; color: white; background: transparent; border: none; font: inherit;}
        .calendar-day.has-event:hover { background-color: rgba(255, 255, 255, 0.16); border-radius: 50%; }
        .calendar-day.has-event::after { content: ''; position: absolute; bottom: 2px; left: 50%; transform: translateX(-50%); width: 4px; height: 4px; background-color: #a4b5d6; border-radius: 50%; }
        .calendar-day.active.has-event::after { background-color: white; }

        /* Message Requests Panel */
        .request-status-panel { width: 100%; max-width: 380px; }
        .request-status-panel h2 { color: #11072b; font-size: 1.25rem; margin-bottom: 14px; }
        .request-status-card { background: white; border-top: 5px solid #11062b; border-radius: 8px; box-shadow: 0 6px 15px rgba(0,0,0,0.16); padding: 14px; margin-bottom: 12px; cursor: pointer; transition: 0.2s; }
        .request-status-card:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,0.2); }
        .request-status-card h3 { font-size: 1rem; color: #11072b; margin-bottom: 6px; }
        .request-status-card p { color: #444; font-size: 0.8rem; line-height: 1.4; }
        .request-status { display: inline-block; border-radius: 12px; padding: 4px 9px; font-size: 0.68rem; font-weight: bold; margin-bottom: 8px; background: #fff3cd; color: #664d03; }

        .empty-state { color: #666; font-style: italic; }

        /* Modals */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 100%; max-width: 450px; position: relative; }
        .close-modal { position: absolute; top: 15px; right: 15px; cursor: pointer; font-size: 1.5rem; color: #333; }
        .close-modal:hover { color: #d9534f; }
        .modal-content h2 { margin-bottom: 20px; color: #11072b; }
        .modal-content form { display: flex; flex-direction: column; gap: 15px; }
        .modal-content input, .modal-content textarea { padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        .modal-content form button { background-color: #11072b; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>

    <header class="header-container">
        <h1>Hello, {<?php echo htmlspecialchars($userName); ?>}</h1>
        <a href="login.php" class="logout-btn">Logout</a>
        <div class="header-wave"></div>
    </header>

    <main class="main-content">
        
        <section class="left-panel">
        
            
            <?php if(!empty($message)): ?>
                <div class="alert-box <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <h2 class="section-title">Assigned Events</h2>
            <div class="cards-grid">
                <?php 
                $todayStr = $todayDate->format('Y-m-d');
                $upcoming = array_filter($events, fn($e) => $e['date'] >= $todayStr);
                if (empty($upcoming)): ?>
                    <p class="empty-state">No upcoming events assigned to you.</p>
                <?php else: ?>
                    <?php foreach ($upcoming as $evt): ?>
                        <div class="event-card">
                            <div class="event-card-image-wrapper" onclick="location.href='event_details.php?id=<?php echo $evt['id']; ?>'">
                                <img src="<?php echo htmlspecialchars($evt['image_path']); ?>" alt="Poster">
                            </div>
                            <div class="event-card-body" onclick="location.href='event_details.php?id=<?php echo $evt['id']; ?>'">
                                <h3><?php echo htmlspecialchars($evt['title']); ?></h3>
                                <p><i class="fa-regular fa-calendar"></i> <?php echo (new DateTime($evt['date']))->format('M d, Y'); ?> | <?php echo (new DateTime($evt['start_time']))->format('h:i A'); ?></p>
                                <p><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($evt['room']); ?></p>
                            </div>
                            <div class="event-actions">
                                <a href="admin_event.php?id=<?php echo $evt['id']; ?>" class="event-action-btn"><i class="fa-solid fa-pen"></i> Edit</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2 class="section-title">Past Events</h2>
            <div class="cards-grid">
                <?php 
                $past = array_filter($events, fn($e) => $e['date'] < $todayStr);
                if (empty($past)): ?>
                    <p class="empty-state">No past events assigned to you.</p>
                <?php else: ?>
                    <?php foreach (array_reverse($past) as $evt): ?>
                        <div class="event-card">
                            <div class="event-card-image-wrapper" onclick="location.href='event_details.php?id=<?php echo $evt['id']; ?>'">
                                <img src="<?php echo htmlspecialchars($evt['image_path']); ?>" alt="Poster">
                            </div>
                            <div class="event-card-body" onclick="location.href='event_details.php?id=<?php echo $evt['id']; ?>'">
                                <h3><?php echo htmlspecialchars($evt['title']); ?></h3>
                                <p><i class="fa-regular fa-calendar"></i> <?php echo (new DateTime($evt['date']))->format('M d, Y'); ?> | <?php echo (new DateTime($evt['start_time']))->format('h:i A'); ?></p>
                                <p><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($evt['room']); ?></p>
                            </div>
                            <div class="event-actions">
                                <a href="admin_event.php?id=<?php echo $evt['id']; ?>" class="event-action-btn"><i class="fa-solid fa-pen"></i> Edit</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <div class="divider"></div>

        <section class="right-panel">
            <div class="calendar-container">
                <div class="calendar-header">
                    <a href="user.php?month=<?php echo htmlspecialchars($prevMonth); ?>">&lt;</a>
                    <span><?php echo htmlspecialchars($calendarMonth); ?></span>
                    <a href="user.php?month=<?php echo htmlspecialchars($nextMonth); ?>">&gt;</a>
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
                        if ($i == $todayDay) $classes .= " active";
                        if (in_array($i, $eventDays)) $classes .= " has-event";
                    ?>
                        <div class="<?php echo $classes; ?>"><?php echo $i; ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="request-status-panel">
                <h2>Message Requests</h2>
                <?php if (empty($visitorMessages)): ?>
                    <p class="empty-state">No new messages from visitors.</p>
                <?php else: ?>
                    <?php foreach ($visitorMessages as $msg): ?>
                        <div class="request-status-card" data-message-id="<?php echo (int)$msg['id']; ?>" onclick='openReplyModal(<?php echo htmlspecialchars(json_encode($msg), ENT_QUOTES); ?>)'>
                            <span class="request-status Pending">New</span>
                            <h3><?php echo htmlspecialchars($msg['subject']); ?></h3>
                            <p><strong>From:</strong> <?php echo htmlspecialchars($msg['sender_name']); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($msg['formatted_date']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <div id="replyMessageModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="document.getElementById('replyMessageModal').style.display='none'">&times;</span>
            <h2>Reply to Visitor</h2>
            <div style="margin-bottom: 15px; font-size:0.9rem; color:#444;">
                <p><strong>From:</strong> <span id="replyMsgFrom"></span></p>
                <p><strong>Subject:</strong> <span id="replyMsgSubject"></span></p>
                <div id="replyMsgContent" style="background:#f0f0f0; padding:10px; border-radius:5px; margin-top:10px; font-style:italic;"></div>
            </div>
            <form action="user.php" method="POST" id="replyMessageForm">
                <input type="hidden" name="action" id="replyAction" value="reply_message">
                <input type="hidden" name="message_id" id="replyMsgId">
                <textarea name="reply_text" id="replyTextarea" rows="4" placeholder="Type your reply here..." required></textarea>
                
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button type="submit" data-action="reply_message" style="flex:1;">Send Reply</button>
                    <button type="submit" data-action="forward_message" formnovalidate style="flex:1; background:#b02a37;">Forward to Admin</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Clean URL after success messages and auto-hide alerts
        if (window.history.replaceState) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('success') || currentUrl.searchParams.has('error')) {
                currentUrl.searchParams.delete('success');
                currentUrl.searchParams.delete('error');
                window.history.replaceState({}, '', currentUrl.pathname);
            }
        }

        // Auto-hide the alert box after 4 seconds
        setTimeout(() => {
            const alertBox = document.querySelector('.alert-box');
            if (alertBox) {
                alertBox.style.transition = 'opacity 0.5s ease';
                alertBox.style.opacity = '0';
                setTimeout(() => alertBox.remove(), 500); 
            }
        }, 4000);

        // Open Reply to Message Modal
        function openReplyModal(msg) {
            document.getElementById('replyMsgId').value = msg.id;
            document.getElementById('replyMsgFrom').innerText = `${msg.sender_name} (${msg.sender_email})`;
            document.getElementById('replyMsgSubject').innerText = msg.subject;
            document.getElementById('replyMsgContent').innerText = `"${msg.content}"`;
            document.getElementById('replyTextarea').value = ''; 
            document.getElementById('replyMessageModal').style.display = 'flex';
        }

        const replyMessageForm = document.getElementById('replyMessageForm');
        replyMessageForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitter = event.submitter;
            const selectedAction = submitter?.dataset.action || 'reply_message';
            const replyTextarea = document.getElementById('replyTextarea');

            if (selectedAction === 'reply_message' && !replyTextarea.value.trim()) {
                alert('Please type a reply before sending.');
                return;
            }

            document.getElementById('replyAction').value = selectedAction;
            const formData = new FormData(replyMessageForm);

            try {
                const response = await fetch('user.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.redirected && response.url.includes('login.php')) {
                    alert('Your session expired. Please log in again.');
                    window.location.href = response.url;
                    return;
                }

                const data = await response.json();
                if (!data.success) {
                    alert(data.message || 'Could not update this request.');
                    return;
                }

                const card = document.querySelector(`[data-message-id="${data.message_id}"]`);
                if (card) {
                    card.remove();
                }

                document.getElementById('replyMessageModal').style.display = 'none';
                const remainingCards = document.querySelectorAll('.request-status-card').length;
                const panel = document.querySelector('.request-status-panel');
                if (remainingCards === 0 && panel && !panel.querySelector('.empty-state')) {
                    const empty = document.createElement('p');
                    empty.className = 'empty-state';
                    empty.textContent = 'No new messages from visitors.';
                    panel.appendChild(empty);
                }
            } catch (error) {
                alert('Could not update this request. Please try again.');
            }
        });

        // Close Modals on background click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>