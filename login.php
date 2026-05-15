<?php
session_start();
require_once "db.php";

$message = '';

// Checks if the user is already logged in or not. If they are, then we redirect them to the appropriate dashboard based on their role.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Authentication logic: do not let the user in if the fields are empty and the captcha is not verified
    if (empty($email) || empty($password)) {
        header("Location: login.php?error=Please fill in all fields");
        exit();
        // This is the specific part that checks for if the captcha is verified. er. Recaptcha? Probably.
    } elseif (($_POST['captcha_verified'] ?? '0') !== '1') {
        header("Location: login.php?error=Please complete the CAPTCHA verification");
        exit();
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, password_hash, role FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password_hash'])) {
                    // Set session variables for the dashboard
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['user_name'] = $row['fullname'];
                    $_SESSION['user_role'] = $row['role'];

                    // Takes them to the fitting dashboard depending on their role.
                    if ($row['role'] === 'Admin') {
                        header("Location: admin.php");
                    } else {
                        header("Location: user.php");
                    }
                    exit();
                } else {
                    header("Location: login.php?error=Invalid email or password");
                    exit();
                }
            } else {
                header("Location: login.php?error=Invalid email or password");
                exit();
            }
            $stmt->close();
        } else {
            header("Location: login.php?error=Database error");
            exit();
        }
    }
}
// the HTML is next. this one steals from styles.css, and I believe most of the captcha stuff is here too.
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
    <title>Welcome Back - Login</title>
</head>



<body>

<!-- LOGIN FORM -->
    <div class="form-section">
        <div class="form-wrapper">
            
            <?php if(!empty($message)): ?>
                <div class="alert"><?php echo $message; ?></div>
            <?php endif; ?>

            <h1>WELCOME BACK</h1>
            
            <form id="loginForm" action="login.php" method="post" class="form-box" style="box-shadow:none; border:none; padding:0;">
                <?php if (isset($_GET['error'])) { echo "<p class='error'>".htmlspecialchars($_GET['error'])."</p>"; } ?>
                <input type="email" name="email" placeholder="Enter email" required>
                <input type="password" name="password" placeholder="Enter Password" required>
                <button type="button" id="loginBtn" onclick="openCaptcha()">LOGIN</button>
                <input type="hidden" name="captcha_verified" id="captcha_verified" value="0">
            </form>
            
            <p class="signup-link">
                Create a new account? <a href="signup.php">Sign up</a>
            </p>
                    <!-- CAPTCHA Modal -->
            <div id="captchaModal" class="captcha-modal">
                <div class="captcha-content">
                    <div class="captcha-title">Security Verification</div>
                    <div class="captcha-instruction">Please select all the squares with a man.</div>
                    <div class="selected-count">Selected: <span id="selectedCount">0</span>/25</div>
                    
                    <div class="captcha-image-container">
                        <img src="captcha.png" alt="CAPTCHA Image">
                        <div class="captcha-grid" id="captchaGrid"></div>
                    </div>

                    <div class="captcha-error" id="captchaError">Please try again</div>
                    
                    <div class="captcha-button-group">
                        <button type="button" class="captcha-button submit" onclick="verifyCaptcha()">Verify</button>
                        <button type="button" class="captcha-button cancel" onclick="closeCaptcha()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="image-section"></div>

    <script>
        // The script for the captcha.
        const selectedSquares = new Set();

        function openCaptcha() {
            document.getElementById('captchaModal').style.display = 'block';
            generateGrid();
        }

        function closeCaptcha() {
            document.getElementById('captchaModal').style.display = 'none';
            selectedSquares.clear();
            updateSelectedCount();
            document.getElementById('captchaError').classList.remove('show');
        }

        // Handles the grid function of the captcha.
        function generateGrid() {
            const grid = document.getElementById('captchaGrid');
            grid.innerHTML = '';
            
            for (let i = 0; i < 25; i++) {
                const cell = document.createElement('div');
                cell.className = 'captcha-cell';
                cell.dataset.index = i;
                cell.onclick = () => toggleCell(i);
                grid.appendChild(cell);
            }
        }

        function toggleCell(index) {
            const cell = document.querySelector(`[data-index="${index}"]`);
            if (selectedSquares.has(index)) {
                selectedSquares.delete(index);
                cell.classList.remove('selected');
            } else {
                selectedSquares.add(index);
                cell.classList.add('selected');
            }
            updateSelectedCount();
        }

        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = selectedSquares.size;
        }

        function verifyCaptcha() {
            if (selectedSquares.size === 0) {
                document.getElementById('captchaError').textContent = 'Please select at least one square';
                document.getElementById('captchaError').classList.add('show');
                return;
            }

            const selectedArray = Array.from(selectedSquares).sort((a, b) => a - b);
            
            // Send to server for validation
            fetch('captcha_validate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    selected: selectedArray
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('captcha_verified').value = '1';
                    closeCaptcha();
                    document.getElementById('loginForm').submit();
                } else {
                    document.getElementById('captchaError').textContent = data.message || 'Incorrect selection. Please try again.';
                    document.getElementById('captchaError').classList.add('show');
                    selectedSquares.clear();
                    generateGrid();
                    updateSelectedCount();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('captchaError').textContent = 'An error occurred. Please try again.';
                document.getElementById('captchaError').classList.add('show');
            });
        }

        // Close modal if clicking outside the content
        window.onclick = function(event) {
            const modal = document.getElementById('captchaModal');
            if (event.target === modal) {
                closeCaptcha();
            }
        }
    </script>
    <script>
        // The service worker to turn it into a PWA.
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
