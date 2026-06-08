// in case someone forgets their password lol

<?php
session_start();
require_once "db.php";

$message = '';
$messageType = 'error';
$step = 1; // Step 1: Email input, Step 2: Code verification, Step 3: New password
$email = '';

// If email is already in session (verified), go to step 3
if (isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    $step = 3;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        
        // Step 1: Send reset code to email
        if ($_POST['action'] === 'send_code') {
            $email = trim($_POST['email'] ?? '');
            
            if (empty($email)) {
                $message = "Please enter your email address.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address.";
            } else {
                // Check if email exists in database
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Generate a reset code
                        $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        
                        // Store reset code in session (in production, use email sending)
                        $_SESSION['reset_code'] = $resetCode;
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_code_time'] = time();
                        
                        // In production, send this code via email
                        // For now, we'll display it for testing
                        $message = "Reset code sent to your email. Use code: " . $resetCode;
                        $messageType = 'success';
                        $step = 2;
                    } else {
                        $message = "No account found with this email address.";
                    }
                    $stmt->close();
                } else {
                    $message = "Database error. Please try again.";
                }
            }
        }
        
        // Step 2: Verify reset code
        elseif ($_POST['action'] === 'verify_code') {
            $email = $_SESSION['reset_email'] ?? '';
            $code = trim($_POST['reset_code'] ?? '');
            
            if (empty($code)) {
                $message = "Please enter the reset code.";
                $step = 2;
            } elseif ($code !== $_SESSION['reset_code']) {
                $message = "Invalid reset code. Please try again.";
                $step = 2;
            } else {
                $message = "Code verified! Please enter your new password.";
                $messageType = 'success';
                $step = 3;
            }
        }
        
        // Step 3: Reset password
        elseif ($_POST['action'] === 'reset_password') {
            $email = $_SESSION['reset_email'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            $passwordErrors = [];
            
            if (strlen($password) < 8) {
                $passwordErrors[] = "at least 8 characters";
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $passwordErrors[] = "one uppercase letter";
            }
            if (!preg_match('/[a-z]/', $password)) {
                $passwordErrors[] = "one lowercase letter";
            }
            if (!preg_match('/[0-9]/', $password)) {
                $passwordErrors[] = "one number";
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
                $passwordErrors[] = "one special character";
            }
            
            if (empty($password) || empty($confirmPassword)) {
                $message = "Please fill in all fields.";
                $step = 3;
            } elseif ($password !== $confirmPassword) {
                $message = "Passwords do not match.";
                $step = 3;
            } elseif (!empty($passwordErrors)) {
                $message = "Password must include: " . implode(", ", $passwordErrors) . ".";
                $step = 3;
            } else {
                // Update password in database
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $passwordHash, $email);
                    
                    if ($stmt->execute()) {
                        $message = "Password reset successfully! You can now login with your new password.";
                        $messageType = 'success';
                        
                        // Clear session variables
                        unset($_SESSION['reset_code']);
                        unset($_SESSION['reset_email']);
                        unset($_SESSION['reset_code_time']);
                        
                        // Redirect to login after 2 seconds
                        header("refresh:2;url=login.php");
                    } else {
                        $message = "An error occurred. Please try again.";
                        $step = 3;
                    }
                    $stmt->close();
                } else {
                    $message = "Database error. Please try again.";
                    $step = 3;
                }
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
    <link rel="stylesheet" href="styles.css">
    <title>Reset Password</title>
</head>
<body class="reset-password-page">

    <!-- RESET PASSWORD FORM -->
    <div class="form-section">
        <div class="form-wrapper">
            
            <?php if(!empty($message)): ?>
                <div class="alert <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <h1>RESET YOUR PASSWORD</h1>
            <p class="reset-subtitle">Enter your email associated with your account and we'll send your password reset instructions</p>
            
            <!-- Step 1: Email Input -->
            <?php if($step === 1): ?>
                <form action="reset_password.php" method="post" class="form-box">
                    <input type="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <input type="hidden" name="action" value="send_code">
                    <button type="submit">Send Code</button>
                </form>
            <?php endif; ?>
            
            <!-- Step 2: Code Verification -->
            <?php if($step === 2): ?>
                <form action="reset_password.php" method="post" class="form-box">
                    <p class="step-info">A reset code has been sent to your email</p>
                    <input type="text" name="reset_code" placeholder="Enter reset code" required>
                    <input type="hidden" name="action" value="verify_code">
                    <button type="submit">Verify Code</button>
                </form>
                <p class="reset-link" style="margin-top: 15px;">
                    <a href="reset_password.php">Back to email</a>
                </p>
            <?php endif; ?>
            
            <!-- Step 3: New Password -->
            <?php if($step === 3): ?>
                <form action="reset_password.php" method="post" class="form-box">
                    <input type="password" name="password" placeholder="Enter Password" required>
                    <input type="password" name="confirm_password" placeholder="Re-enter Password" required>
                    <input type="hidden" name="action" value="reset_password">
                    <button type="submit">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <p class="reset-link">
                <a href="login.php">Back to Sign In</a>
            </p>
        </div>
    </div>

    <div class="image-section"></div>

    <script>
        // Registers the service worker so the app can cache basic files for PWA support.
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
