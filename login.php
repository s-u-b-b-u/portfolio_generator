<?php
require_once 'auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['signup'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        $signup_result = signup($name, $email, $password);
        if ($signup_result === true) {
            $success = "Account created successfully! You can now log in.";
        } else {
            $error = $signup_result;
        }
    } elseif (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (login($email, $password)) {
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Alumni Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="landing-page">
    <nav class="dashboard-nav" style="background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); position: absolute; width: 100%; top: 0;">
        <h2 style="font-family: 'Playfair Display', serif; color: var(--primary); cursor: pointer;" onclick="window.location.href='index.php'">Portfolio Builder</h2>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="login.php">Login</a>
            <a href="login.php?signup=1" class="btn btn-primary" style="padding: 0.5rem 1.2rem; color: white;">Get Started</a>
        </div>
    </nav>

    <div class="container" style="margin-top: 5rem;">
        <header style="text-align: center; margin-bottom: 2rem;">
            <h1>Dynamic Portfolio Generator</h1>
            <p>Welcome back! Please login to your account.</p>
        </header>

        <main class="auth-container" style="margin-top: 0;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="auth-wrapper">
                <!-- Login Form -->
                <section id="login-section" class="auth-card">
                    <h2>Login</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <input type="email" id="login-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password" id="login-password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary" style="width: 100%;">Login</button>
                        <p class="toggle-text" style="margin-top: 1rem; text-align: center;">Don't have an account? <a href="#" onclick="toggleAuth()">Sign Up</a></p>
                    </form>
                </section>

                <!-- Signup Form -->
                <section id="signup-section" class="auth-card" style="display: none;">
                    <h2>Sign Up</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="signup-name">Full Name</label>
                            <input type="text" id="signup-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-email">Email</label>
                            <input type="email" id="signup-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-password">Password</label>
                            <input type="password" id="signup-password" name="password" required>
                        </div>
                        <button type="submit" name="signup" class="btn btn-secondary" style="width: 100%;">Create Account</button>
                        <p class="toggle-text" style="margin-top: 1rem; text-align: center;">Already have an account? <a href="#" onclick="toggleAuth()">Login</a></p>
                    </form>
                </section>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">← Back to Home Page</a>
            </div>
        </main>
    </div>

    <script>
        // Check URL for signup parameter to toggle initial state
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('signup') === '1') {
                toggleAuth();
            }
        }

        function toggleAuth() {
            const loginSection = document.getElementById('login-section');
            const signupSection = document.getElementById('signup-section');
            if (loginSection.style.display === 'none') {
                loginSection.style.display = 'block';
                signupSection.style.display = 'none';
            } else {
                loginSection.style.display = 'none';
                signupSection.style.display = 'block';
            }
        }
    </script>
</body>
</html>
