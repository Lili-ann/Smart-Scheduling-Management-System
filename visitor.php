<?php
session_start();
require_once "db.php";

$message = '';
$code = '';

if (isset($_GET['error'])) {
    $message = trim($_GET['error']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
</head>
<body class="visitor-page">
    <div class="form-section">
        <div class="form-wrapper visitor-wrapper">
            <?php if(!empty($message)): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="access-toggle" aria-label="Choose access type">
                <a href="signup.php" class="access-toggle-option">PIC / STAFF</a>
                <a href="visitor.php" class="access-toggle-option active">VISITOR</a>
            </div>

            <h1>WELCOME</h1>

            <form action="visitor.php" method="POST" class="visitor-entry-form">
                <input type="text" name="invitation_code" placeholder="Enter code" value="<?php echo htmlspecialchars($code); ?>" autocomplete="one-time-code" required>
                <button type="submit">Enter</button>
            </form>

            <p class="signup-link visitor-code-link">
                Don't have Code? <a href="mailto:admin@gmail.com?subject=Visitor%20Invitation%20Code">GET CODE</a>
            </p>
        </div>
    </div>

    <div class="image-section visitor-image-section"></div>

    <script>
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
