<?php

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = htmlspecialchars($_POST['email'] ?? '');
    $password = htmlspecialchars($_POST['password'] ?? '');

    //authentication logic: do not let the user in if the fields are empty and the captcha is not verified
    if (empty($email) || empty($password)) {
        header("Location: login.php?error=Please fill in all fields");
        exit();
    } elseif ($_POST['captcha_verified'] !== '1') {
        header("Location: login.php?error=Please complete the CAPTCHA verification");
        exit();
    } else {
        // let's pretend a database exists
        header("Location: admin.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            
            <form id="loginForm" action="admin.php" method="post" class="form-box" style="box-shadow:none; border:none; padding:0;">
                <?php if (isset($_GET['error'])) { echo "<p class='error'>".$_GET['error']."</p>"; } ?>
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
</body>
</html>