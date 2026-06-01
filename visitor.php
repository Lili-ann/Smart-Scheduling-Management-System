<?php
session_start();
require_once "db.php";

$message = '';
$msgType = 'error'; // Used to determine if the alert should be red (error) or green (success)
$code = '';

// Catch GET messages (like success redirects or error redirects)
if (isset($_GET['error'])) {
    $message = trim($_GET['error']);
} elseif (isset($_GET['success'])) {
    $message = trim($_GET['success']);
    $msgType = 'success';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? 'verify_code'; // Identify which form was submitted

    // --- ACTION 1: SEND MESSAGE TO ADMIN ---
    if ($action === 'send_admin_message') {
        $msgName = trim($_POST['msg_name'] ?? '');
        $msgEmail = trim($_POST['msg_email'] ?? '');
        $msgContent = trim($_POST['msg_content'] ?? '');

        if ($msgName === '' || $msgEmail === '' || $msgContent === '') {
            $message = "Please fill in all message fields.";
        } elseif (!filter_var($msgEmail, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
        } else {
            try {
                $senderId = null; // Visitors don't have an ID
                $subject = "Visitor message";
                $stmt = $conn->prepare("INSERT INTO admin_messages (sender_id, sender_name, sender_email, subject, content) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $senderId, $msgName, $msgEmail, $subject, $msgContent);
                $stmt->execute();
                $stmt->close();

                // Redirect to avoid form resubmission on refresh
                header("Location: visitor.php?success=Message sent to admin successfully!");
                exit();
            } catch (mysqli_sql_exception $e) {
                $message = "Could not send the message to admin. Please try again later.";
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
                    // Clear previous staff sessions and set visitor session
                    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
                    $_SESSION['visitor_access'] = true;
                    $_SESSION['visitor_code_id'] = (int)$row['id'];
                    $_SESSION['visitor_code'] = $row['code'];

                    // Update last used timestamp
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
        /* CSS for the Alerts */
        .alert-success { background-color: #d1e7dd; color: #0f5132; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; border: 1px solid #badbcc; font-weight: bold; }
        .alert-error { background-color: #f8d7da; color: #842029; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; border: 1px solid #f5c2c7; font-weight: bold; }

        /* Floating Message Button */
        .message-admin-floating {
            width: 60px;
            height: 60px;
            position: fixed;
            right: 2rem;
            bottom: 2rem;
            z-index: 1000;
            border: none;
            border-radius: 50%;
            background: #11062b;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: transform 0.2s;
        }
        .message-admin-floating:hover { transform: scale(1.05); }

        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(2px);
        }

        /* Send Message Modal Content */
        .send-message-content {
            background: #11062b;
            color: white;
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 450px;
            position: relative;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        .send-message-content h2 { margin-bottom: 5px; color: white; text-transform: uppercase; font-size: 1.5rem; letter-spacing: 0.5px; }
        .send-message-divider { height: 4px; background: white; width: 100%; margin-bottom: 25px; border-radius: 2px; }
        
        .send-message-content .close-modal {
            color: white;
            position: absolute;
            top: 20px; right: 20px;
            cursor: pointer;
            font-size: 1.8rem;
            line-height: 1;
        }
        .send-message-content .close-modal:hover { color: #ccc; }

        .send-message-form { display: flex; flex-direction: column; gap: 15px; }
        .send-message-group { display: flex; align-items: center; gap: 10px; }
        .send-message-group label { width: 60px; font-size: 0.9rem; font-weight: bold; color: white; }
        .send-message-group input, .send-message-form textarea {
            flex: 1; padding: 10px 15px; border-radius: 8px; border: none; outline: none; color: #333; font-family: inherit;
        }
        .send-message-form textarea {
            width: 100%; height: 120px; resize: none; margin-top: 5px;
        }
        .send-message-submit-wrapper { display: flex; justify-content: center; margin-top: 15px; }
        .send-message-submit-wrapper button {
            background: white; color: #11062b; border: none; padding: 12px 30px;
            border-radius: 25px; font-size: 0.9rem; font-weight: bold; cursor: pointer; transition: 0.3s;
        }
        .send-message-submit-wrapper button:hover { background: #eee; transform: translateY(-2px); }
    </style>
</head>
<body class="visitor-page">
    <div class="form-section">
        <div class="form-wrapper visitor-wrapper">
            
            <?php if(!empty($message)): ?>
                <div class="<?php echo $msgType === 'success' ? 'alert-success' : 'alert-error'; ?>">
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
                Don't have a Code? <a href="#" id="openMessageLink">CONTACT ADMIN</a>
            </p>
        </div>
    </div>

    <div class="image-section visitor-image-section"></div>

    <button class="message-admin-floating" id="messageAdminBtn" type="button" aria-label="Send Message">
        <img src="smsicon.svg" width="30" height="30" alt="✉️">
    </button>

    <div id="sendMessageModal" class="modal-overlay">
        <div class="send-message-content">
            <span class="close-modal" id="closeSendMessageModal">&times;</span>
            <h2>SEND MESSAGE</h2>
            <div class="send-message-divider"></div>
            
            <form class="send-message-form" action="visitor.php" method="POST">
                <input type="hidden" name="action" value="send_admin_message">
                <div class="send-message-group">
                    <label for="msg_name">Name:</label>
                    <input type="text" id="msg_name" name="msg_name" placeholder="John Doe" required>
                </div>
                <div class="send-message-group">
                    <label for="msg_email">Email:</label>
                    <input type="email" id="msg_email" name="msg_email" placeholder="john@example.com" required>
                </div>
                <textarea name="msg_content" placeholder="How can we help you?" required></textarea>
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
        const openMessageLink = document.getElementById('openMessageLink');
        const closeSendMessageModalBtn = document.getElementById('closeSendMessageModal');

        // Open Modal (From floating button or text link)
        messageAdminBtn.addEventListener('click', () => sendMessageModal.style.display = 'flex');
        openMessageLink.addEventListener('click', (e) => {
            e.preventDefault(); // Stop link from refreshing page
            sendMessageModal.style.display = 'flex';
        });

        // Close Modal
        closeSendMessageModalBtn.addEventListener('click', () => sendMessageModal.style.display = 'none');
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