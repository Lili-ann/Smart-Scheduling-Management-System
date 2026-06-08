// this is the UI for the visitors since they have a different thing with the codes and all.

<?php
session_start();
require_once "db.php";

$message = '';
$msgType = 'error'; 
$code = '';

if (isset($_GET['error'])) {
    $message = trim($_GET['error']);
} elseif (isset($_GET['success'])) {
    $message = trim($_GET['success']);
    $msgType = 'success';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? 'verify_code'; 

    // --- ACTION 1: REQUEST CODE ---
    if ($action === 'request_invite_code') {
        $msgName = trim($_POST['msg_name'] ?? '');
        $msgEmail = trim($_POST['msg_email'] ?? '');

        if ($msgName === '' || $msgEmail === '') {
            $message = "Please fill in your name and email.";
        } elseif (!filter_var($msgEmail, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
        } else {
            try {
                $senderId = null; 
                // Format the subject exactly as requested
                $subject = "[CODE REQUEST] " . $msgName . " (Visitor)";
                $msgContent = "Requested an invitation code to access the platform.";
                
                $stmt = $conn->prepare("INSERT INTO admin_messages (sender_id, sender_name, sender_email, subject, content) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $senderId, $msgName, $msgEmail, $subject, $msgContent);
                $stmt->execute();
                $stmt->close();

                header("Location: visitor.php?success=Code request sent successfully! Please check your email later.");
                exit();
            } catch (mysqli_sql_exception $e) {
                $message = "Could not send the request. Please try again later.";
            }
        }
    } 
    // --- ACTION 2: VERIFY VISITOR CODE ---
    else {
        $code = strtoupper(trim($_POST['invitation_code'] ?? ''));

        if ($code === '') {
            $message = "Please enter your invitation code.";
        } else {
            $stmt = $conn->prepare("SELECT id, code FROM visitor_invitation_codes WHERE code = ? AND is_active = 1 LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $code);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
                    $_SESSION['visitor_access'] = true;
                    $_SESSION['visitor_code_id'] = (int)$row['id'];
                    $_SESSION['visitor_code'] = $row['code'];

                    $updateStmt = $conn->prepare("UPDATE visitor_invitation_codes SET last_used_at = NOW() WHERE id = ?");
                    if ($updateStmt) {
                        $codeId = (int)$row['id'];
                        $updateStmt->bind_param("i", $codeId);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }

                    header("Location: events.php");
                    exit();
                }
                $message = "Invalid invitation code.";
                $stmt->close();
            } else {
                $message = "Visitor access is not ready yet.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#11072b">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="captcha.png">
    <title>Visitor Access - Scheduler</title>
    <link rel="stylesheet" href="styles.css">

    <style>
            .modal-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                z-index: 1000;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(2px);
            }
        </style>

</head>
<body class="visitor-page">
    <div class="form-section">
        <div class="form-wrapper visitor-wrapper">
            
            <?php if(!empty($message)): ?>
                <div class="alert <?php echo $msgType === 'success' ? 'success' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="access-toggle" aria-label="Choose access type">
                <a href="signup.php" class="access-toggle-option">PIC / STAFF</a>
                <a href="visitor.php" class="access-toggle-option active">VISITOR</a>
            </div>

            <h1>WELCOME</h1>

            <form action="visitor.php" method="POST" class="visitor-entry-form">
                <input type="hidden" name="action" value="verify_code">
                <input type="text" name="invitation_code" placeholder="Enter code" value="<?php echo htmlspecialchars($code); ?>" autocomplete="one-time-code" required>
                <button type="submit">Enter</button>
            </form>

<p class="signup-link visitor-code-link">
                Don't have a Code? <button type="button" class="invite-request-link" id="openMessageLink" style="background:none;border:none;padding:0;color:#2b1154;text-decoration:underline;font-weight:bold;cursor:pointer;">REQUEST CODE</button>
            </p>
        </div>
    </div>

    <div class="image-section visitor-image-section"></div>

    <div id="sendMessageModal" class="modal-overlay">
        <div class="send-message-content">
            <span class="close-modal" id="closeSendMessageModal">&times;</span>
            <h2>REQUEST CODE</h2>
            <div class="send-message-divider"></div>
            
            <form class="send-message-form" action="visitor.php" method="POST">
                <input type="hidden" name="action" value="request_invite_code">
                <div class="send-message-group">
                    <label for="msg_name">Name:</label>
                    <input type="text" id="msg_name" name="msg_name" placeholder="John Doe" required>
                </div>
                <div class="send-message-group">
                    <label for="msg_email">Email:</label>
                    <input type="email" id="msg_email" name="msg_email" placeholder="john@example.com" required>
                </div>
                <div class="send-message-submit-wrapper">
                    <button type="submit">Request Code</button>
                </div>
            </form>
        </div>
    </div>

<script>
        // Modal logic
        const sendMessageModal = document.getElementById('sendMessageModal');
        const openMessageLink = document.getElementById('openMessageLink');
        const closeSendMessageModalBtn = document.getElementById('closeSendMessageModal');

        // Safely open Modal (From text link)
        if (openMessageLink && sendMessageModal) {
            openMessageLink.addEventListener('click', (e) => {
                e.preventDefault(); // Stop link from refreshing page
                sendMessageModal.style.display = 'flex';
            });
        }

        // Safely close Modal
        if (closeSendMessageModalBtn) {
            closeSendMessageModalBtn.addEventListener('click', () => {
                sendMessageModal.style.display = 'none';
            });
        }

        // Close when clicking the dark background
        window.addEventListener('click', (e) => {
            if (e.target === sendMessageModal) {
                sendMessageModal.style.display = 'none';
            }
        });

        // PWA Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch((error) => {
                    console.error('Service worker registration failed:', error);
                });
            });
        }
    </script>
</body>
</html>