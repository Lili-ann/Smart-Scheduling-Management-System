<?php

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = htmlspecialchars($_POST['fullname'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $password = htmlspecialchars($_POST['password'] ?? '');
    $confirm_password = htmlspecialchars($_POST['confirm_password'] ?? '');
    $role = htmlspecialchars($_POST['role'] ?? '');

    // Placeholder for your signup logic
    if (!empty($fullname) && !empty($email) && !empty($password) && !empty($confirm_password) && !empty($role)) {
        if ($password !== $confirm_password) {
            $message = "Passwords do not match.";
        } else {
            $message = "Account created for: " . $email . " as " . $role;
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
    <style>
        /* Base Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            background-color: #1a0f2e; /* Fallback dark background */
            overflow: hidden;
        }

        /* Left Section: The Form */
        .form-section {
            width: 65%;
            height: 100%;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        /* The Wavy Edge Effect */
        .form-section::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            right: -120px;       /* wider to match the deeper curve */
            width: 120px;
            background-color: transparent;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' preserveAspectRatio='none'%3E%3Cpath d='M0,0 L50,0 C100,33 0,66 50,100 L0,100 Z' fill='%23ffffff'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-size: 100% 100%;
            z-index: 1;
        }

        /* Form Container */
        .form-wrapper {
            width: 100%;
            max-width: 400px;
            text-align: center;
            padding-right: 40px; 
        }

        h1 {
            color: #2b1154;
            font-size: 2.5rem;
            font-family: "Times New Roman", serif;
            font-weight: normal;
            letter-spacing: 2px;
            margin-bottom: 50px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
        }

        /* Inputs */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 25px;
            border-radius: 30px;
            border: none;
            background-color: #1a0f2e;
            color: #ffffff;
            font-size: 1rem;
            outline: none;
        }

        input::placeholder {
            color: #887a9e;
        }

        /* Submit Button */
        button {
            margin-top: 20px;
            padding: 15px 40px;
            width: 150px;
            border-radius: 30px;
            border: none;
            background-color: #1a0f2e;
            color: #ffffff;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #381b6b;
        }

        /* Footer Link */
        .signup-link {
            margin-top: 40px;
            color: #2b1154;
            font-size: 0.9rem;
        }

        .signup-link a {
            color: #2b1154;
            text-decoration: underline;
            font-weight: bold;
        }

        /* PHP Message Alert */
        .alert {
            margin-bottom: 20px;
            color: #d9534f;
            font-weight: bold;
        }

        /* Right Section: The Background Image */
        .image-section {
            width: 35%;
            height: 100%;
            position: absolute;
            right: 0;
            top: 0;
            z-index: 1;
            background: url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?q=80&w=1000&auto=format&fit=crop') center/cover no-repeat;
            box-shadow: inset 100vw 0 0 rgba(26, 15, 46, 0.4); /* Dark overlay */
        }

        /* Responsive Design for smaller screens */
        @media (max-width: 768px) {
            .form-section {
                width: 100%;
            }
            .form-section::after {
                display: none;
            }
            .form-wrapper {
                padding-right: 0;
            }
            .image-section {
                display: none; /* Hide image on mobile */
            }
        }
    </style>
</head>
<body>

<!-- SIGNUP FORM -->
    <div class="form-section">
        <div class="form-wrapper">
            
            <?php if(!empty($message)): ?>
                <div class="alert"><?php echo $message; ?></div>
            <?php endif; ?>

            <h1>SIGN UP</h1>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <input type="text" name="fullname" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Enter email" required>
                <select name="role" required>
                    <option value="" disabled selected hidden>Select Role</option>
                    <option value="Admin">Admin</option>
                    <option value="User">User</option>
                </select>
                <input type="password" name="password" placeholder="Create Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
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