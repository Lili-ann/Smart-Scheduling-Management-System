<?php
require_once "db.php";

$message = '';
$messageType = 'error';
$fullname = '';
$email = '';
$role = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $allowedRoles = ['Admin', 'User'];

    // Password strength validation (sign up only)
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

    if (!empty($fullname) && !empty($email) && !empty($password) && !empty($role)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
        } elseif (!in_array($role, $allowedRoles, true)) {
            $message = "Please select a valid role.";
        } elseif (!empty($passwordErrors)) {
            $message = "Password must include: " . implode(", ", $passwordErrors) . ".";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("ssss", $fullname, $email, $passwordHash, $role);

                $stmt->execute();
                $message = "Account created successfully. You can now login.";
                $messageType = 'success';
                $fullname = '';
                $email = '';
                $role = '';

                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $message = "An account with this email already exists.";
                } else {
                    $message = "Signup setup is incomplete. Please make sure the users table exists.";
                }
            }
        }
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Sign Up</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- SIGNUP FORM -->
    <div class="form-section">
        <div class="form-wrapper">
            
            <?php if(!empty($message)): ?>
                <div class="alert <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <h1>SIGN UP</h1>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <input type="text" name="fullname" placeholder="Full Name" value="<?php echo htmlspecialchars($fullname); ?>" required>
                <input type="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($email); ?>" required>
                <select name="role" required>
                    <option value="" disabled <?php echo $role === '' ? 'selected' : ''; ?> hidden>Select Role</option>
                    <option value="Admin" <?php echo $role === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="User" <?php echo $role === 'User' ? 'selected' : ''; ?>>User</option>
                </select>
                <input type="password" name="password" placeholder="Create Password" required>
                <button type="submit">SIGN UP</button>
            </form>
            
            <p class="signup-link">
                Already have an account? <a href="login.php">Login</a>
            </p>
        </div>
    </div>

    <div class="image-section"></div>

</body>
</html>
