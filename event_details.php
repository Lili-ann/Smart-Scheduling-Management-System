<?php
session_start();
require_once "db.php";

$isAdmin = (($_SESSION['user_role'] ?? '') === 'Admin');
$isStaff = (($_SESSION['user_role'] ?? '') === 'Staff');
$isVisitor = !empty($_SESSION['visitor_access']);
$userId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$msgType = 'error';

// Staff/Admin can use login. Visitors can view after entering an invitation code.
if (!$isStaff && !$isAdmin && !$isVisitor) {
    header("Location: login.php?error=Please log in or enter a visitor code to view event details");
    exit();
}

// 1. Get the Event ID from the URL
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    die("Invalid Event ID.");
}

// 2. Fetch Event Details
$stmt = $conn->prepare(
    "SELECT e.*, u.fullname AS assigned_staff_name
     FROM events e
     LEFT JOIN users u ON u.id = e.assigned_staff_id
     WHERE e.id = ?"
);
$stmt->bind_param("i", $eventId);
$stmt->execute();
$eventResult = $stmt->get_result();

if ($eventResult->num_rows === 0) {
    die("Event not found.");
}
$event = $eventResult->fetch_assoc();
$stmt->close();

if ($isStaff && (int)($event['assigned_staff_id'] ?? 0) !== $userId) {
    header("Location: events.php?error=You can only view events assigned to you");
    exit();
}

// Catch GET messages (success/error redirects)
if (isset($_GET['error'])) {
    $message = trim($_GET['error']);
} elseif (isset($_GET['success'])) {
    $message = trim($_GET['success']);
    $msgType = 'success';
}

// 3. Handle Send Message Request
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'send_admin_message') {
    $msgName = trim($_POST['msg_name'] ?? '');
    $msgEmail = trim($_POST['msg_email'] ?? '');
    $msgContent = trim($_POST['msg_content'] ?? '');
    $eventContext = trim($_POST['event_context'] ?? '');

    if ($msgName === '' || $msgEmail === '' || $msgContent === '') {
        $message = "Please fill in all message fields.";
    } elseif (!filter_var($msgEmail, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        try {
            $senderId = null; // Visitors don't have an ID
            // Automatically append the Event Title so the admin knows what they are asking about
            $subject = "Visitor message regarding: " . $eventContext;
            
            $insertStmt = $conn->prepare("INSERT INTO admin_messages (sender_id, sender_name, sender_email, subject, content) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->bind_param("issss", $senderId, $msgName, $msgEmail, $subject, $msgContent);
            $insertStmt->execute();
            $insertStmt->close();

            // Redirect to avoid form resubmission on refresh
            header("Location: event_details.php?id=" . $eventId . "&success=Message sent to admin successfully!");
            exit();
        } catch (mysqli_sql_exception $e) {
            $message = "Could not send the message to admin. Please try again later.";
        }
    }
}

// 4. Fetch Gallery Images for this event
$galleryStmt = $conn->prepare("SELECT image_path FROM event_gallery WHERE event_id = ? ORDER BY id ASC");
$galleryStmt->bind_param("i", $eventId);
$galleryStmt->execute();
$galleryResult = $galleryStmt->get_result();

$galleryImages = [];
while ($row = $galleryResult->fetch_assoc()) {
    $galleryImages[] = $row['image_path'];
}
$galleryStmt->close();

// Format the date for display
$dateObj = new DateTime($event['date']);
$formattedDate = $dateObj->format('M d, Y');
$startObj = new DateTime($event['start_time']);
$endObj = new DateTime($event['end_time']);
$formattedTime = $startObj->format('h:i A') . ' - ' . $endObj->format('h:i A');
$backUrl = $isVisitor ? 'events.php' : ($isAdmin ? 'admin.php' : 'user.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Recap</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern Light UI styling */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8f9fa; color: #333; min-height: 100vh; display: flex; flex-direction: column; }

        /* Top Header Area matching main app */
        .header-container { position: relative; width: 100%; height: 140px; background-color: #11072b; color: white; padding: 30px 60px; display: flex; align-items: center; z-index: 2; }
        .header-wave { position: absolute; top: 99%; left: 0; width: 100%; height: 60px; z-index: 1; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L1000,0 L1000,30 C750,10 250,120 0,80 Z' fill='%2311072b'/%3E%3C/svg%3E"); background-size: 100% 100%; }
        .header-title-wrapper { z-index: 2; }
        .header-container h1 { font-size: 2.2rem; font-weight: 600; margin: 0; }

        /* Main Content Container */
        .main-content { width: 100%; max-width: 1200px; margin: 40px auto; padding: 0 40px; position: relative; z-index: 5; }
        .container-card { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); border: 1px solid rgba(0, 0, 0, 0.05); padding: 35px; }
        
        /* Navigation / Back Button styling */
        /* Navigation / Back Button styling */
        .back-btn-wrapper { 
            margin-top: 40px; /* <--- This pushes it down */
            margin-bottom: 25px; 
        }
        .back-btn { display: inline-flex; align-items: center; gap: 8px; background-color: #11062b; color: white; padding: 10px 24px; border-radius: 20px; text-decoration: none; font-weight: bold; font-size: 0.85rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: background-color 0.2s, transform 0.2s; }
        .back-btn:hover { background-color: #1f0b4d; transform: translateY(-1px); }

        /* Alerts */
        .alert-success { background-color: #d1e7dd; color: #0f5132; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #badbcc; font-weight: bold; }
        .alert-error { background-color: #f8d7da; color: #842029; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c2c7; font-weight: bold; }

        /* Two Column Grid layout */
        .details-layout { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 40px; margin-top: 15px; }
        @media (max-width: 768px) { .details-layout { grid-template-columns: 1fr; } }

        /* Left Side Panel */
        .details-left { display: flex; flex-direction: column; gap: 20px; }
        .details-poster { width: 100%; max-height: 420px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .event-info-title { color: #11062b; font-size: 1.6rem; font-weight: 700; margin-bottom: 5px; }
        .event-description { color: #555; font-size: 0.95rem; line-height: 1.6; margin-bottom: 10px; }

        /* Meta Table styling */
        .event-meta-table { font-size: 0.9rem; width: 100%; border-collapse: collapse; margin-top: 10px; background: #fdfdfd; border-radius: 6px; }
        .event-meta-table td { padding: 10px 0; vertical-align: middle; color: #444; border-bottom: 1px solid #f0f0f0; }
        .event-meta-table tr:last-child td { border-bottom: none; }
        .event-meta-table td:first-child { width: 110px; font-weight: bold; color: #11062b; }
        .event-meta-table i { color: #11062b; margin-right: 8px; width: 16px; text-align: center; }

        /* Right Side Gallery Panel */
        .details-right { display: flex; flex-direction: column; gap: 20px; }
        
        /* New Wrapper for Header and Message Button */
        .recap-header-wrapper { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #11062b; padding-bottom: 8px; margin-bottom: 5px; }
        .gallery-section-label { color: #11062b; font-weight: 700; font-size: 1.2rem; margin: 0; padding: 0; border: none; }
        .message-admin-btn { background: transparent; border: none; cursor: pointer; transition: transform 0.2s; display: flex; align-items: center; justify-content: center; padding: 0; }
        .message-admin-btn:hover { transform: scale(1.08); }

        .gallery-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        @media (max-width: 480px) { .gallery-grid { grid-template-columns: repeat(2, 1fr); } }
        .gallery-thumb { width: 100%; aspect-ratio: 1; border-radius: 6px; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.06); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border: 1px solid rgba(0,0,0,0.04); }
        .gallery-thumb:hover { transform: scale(1.04); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .no-highlights { color: #777; font-style: italic; font-size: 0.9rem; }

        /* Modal Base */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        
        /* Send Message Modal Content */
        .send-message-content { background: #11062b; color: white; padding: 40px; border-radius: 15px; width: 100%; max-width: 450px; position: relative; box-shadow: 0 15px 35px rgba(0,0,0,0.3); }
        .send-message-content h2 { margin-bottom: 5px; color: white; text-transform: uppercase; font-size: 1.5rem; letter-spacing: 0.5px; }
        .send-message-divider { height: 4px; background: white; width: 100%; margin-bottom: 25px; border-radius: 2px; }
        .send-message-content .close-modal { color: white; position: absolute; top: 20px; right: 20px; cursor: pointer; font-size: 1.8rem; line-height: 1; }
        .send-message-content .close-modal:hover { color: #ccc; }
        .send-message-form { display: flex; flex-direction: column; gap: 15px; }
        .send-message-group { display: flex; align-items: center; gap: 10px; }
        .send-message-group label { width: 60px; font-size: 0.9rem; font-weight: bold; color: white; }
        .send-message-group input, .send-message-form textarea { flex: 1; padding: 10px 15px; border-radius: 8px; border: none; outline: none; color: #333; font-family: inherit; }
        .send-message-form textarea { width: 100%; height: 120px; resize: none; margin-top: 5px; }
        .send-message-submit-wrapper { display: flex; justify-content: center; margin-top: 15px; }
        .send-message-submit-wrapper button { background: white; color: #11062b; border: none; padding: 12px 30px; border-radius: 25px; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .send-message-submit-wrapper button:hover { background: #eee; transform: translateY(-2px); }
    </style>
</head>
<body>

    <div class="header-container">
        <div class="header-title-wrapper">
            <h1>Event Recap & Highlights</h1>
        </div>
        <div class="header-wave"></div>
    </div>

    <main class="main-content">
        <div class="back-btn-wrapper">
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Events
            </a>
        </div>

        <?php if(!empty($message)): ?>
            <div class="<?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="container-card">
            <div class="details-layout">
                
                <div class="details-left">
                    <?php if (!empty($event['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($event['image_path']); ?>" alt="Event Poster" class="details-poster">
                    <?php endif; ?>
                    
                    <h2 class="event-info-title"><?php echo htmlspecialchars($event['title']); ?></h2>
                    <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    
                    <table class="event-meta-table">
                        <tr>
                            <td><i class="fas fa-calendar-alt"></i> Date</td>
                            <td><?php echo $formattedDate; ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-clock"></i> Time</td>
                            <td><?php echo $formattedTime; ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-map-marker-alt"></i> Location</td>
                            <td><?php echo htmlspecialchars($event['room']); ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-user-tie"></i> Staff</td>
                            <td><?php echo htmlspecialchars($event['assigned_staff_name'] ?? 'Unassigned'); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="details-right">
                    
                    <div class="recap-header-wrapper">
                        <h3 class="gallery-section-label">Event Highlights</h3>
                        
                        <?php if ($isVisitor): ?>
                            <button class="message-admin-btn" id="messageAdminBtn" type="button" title="Ask for help">
                                <img src="smsicon.svg" width="42" height="42" alt="Send Message">
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($galleryImages) > 0): ?>
                        <div class="gallery-grid">
                            <?php foreach ($galleryImages as $img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="Recap Photo" class="gallery-thumb" onclick="window.open(this.src, '_blank');">
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-highlights">No highlights photos available for this event yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <?php if ($isVisitor): ?>
    <div id="sendMessageModal" class="modal-overlay">
        <div class="send-message-content">
            <span class="close-modal" id="closeSendMessageModal">&times;</span>
            <h2>ASK FOR HELP</h2>
            <div class="send-message-divider"></div>
            
            <form class="send-message-form" action="event_details.php?id=<?php echo $eventId; ?>" method="POST">
                <input type="hidden" name="action" value="send_admin_message">
                <input type="hidden" name="event_context" value="<?php echo htmlspecialchars($event['title']); ?>">
                
                <div class="send-message-group">
                    <label for="msg_name">Name:</label>
                    <input type="text" id="msg_name" name="msg_name" placeholder="John Doe" required>
                </div>
                <div class="send-message-group">
                    <label for="msg_email">Email:</label>
                    <input type="email" id="msg_email" name="msg_email" placeholder="john@example.com" required>
                </div>
                <textarea name="msg_content" placeholder="What do you need help with regarding this event?" required></textarea>
                <div class="send-message-submit-wrapper">
                    <button type="submit">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal logic
        const sendMessageModal = document.getElementById('sendMessageModal');
        const messageAdminBtn = document.getElementById('messageAdminBtn');
        const closeSendMessageModalBtn = document.getElementById('closeSendMessageModal');

        if (messageAdminBtn && sendMessageModal) {
            messageAdminBtn.addEventListener('click', () => {
                sendMessageModal.style.display = 'flex';
            });
            
            closeSendMessageModalBtn.addEventListener('click', () => {
                sendMessageModal.style.display = 'none';
            });
            
            window.addEventListener('click', (e) => {
                if (e.target === sendMessageModal) {
                    sendMessageModal.style.display = 'none';
                }
            });
        }
    </script>
    <?php endif; ?>

</body>
</html>
