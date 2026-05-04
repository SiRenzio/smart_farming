<?php
session_start();
require_once '../db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username_email || !$password) {
        $error = 'All fields are required.';
    } else {
        $stmt = $conn->prepare('SELECT userID, username, password_hash FROM users WHERE username = ? OR email = ?');
        $stmt->bind_param('ss', $username_email, $username_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($userID, $username, $password_hash);
            $stmt->fetch();
            if (password_verify($password, $password_hash)) {
                // Success: set session and redirect
                $_SESSION['userID'] = $userID;
                $_SESSION['username'] = $username;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials.';
            }
        } else {
            $error = 'Invalid credentials.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/login.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-seedling"></i>
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to your Smart Farming account</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <div class="form-group">
                    <label for="username_email">Username or Email</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               id="username_email"
                               name="username_email" 
                               class="form-input"
                               placeholder="Enter your username or email" 
                               required 
                               value="<?php echo htmlspecialchars($_POST['username_email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password"
                               name="password" 
                               class="form-input"
                               placeholder="Enter your password" 
                               required>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="register-link">
                Don't have an account? <a href="register.php">Create one now</a>
            </div>
        </div>
    </div>
</body>
</html> 