<?php
require_once '../db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate
    if (!$username || !$email || !$password || !$confirm_password) {
        $errors[] = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Check for existing username/email
    if (!$errors) {
        $stmt = $conn->prepare('SELECT userID FROM users WHERE username = ? OR email = ?');
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Username or email already exists.';
        }
        $stmt->close();
    }

    // Register user
    if (!$errors) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $username, $email, $password_hash);
        if ($stmt->execute()) {
            $success = 'Registration successful! <a href="login.php">Login here</a>.';
        } else {
            $errors[] = 'Registration failed. Please try again.';
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
    <title>Register - Smart Farming</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .register-container {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .register-header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .register-header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: #666;
            font-size: 1rem;
        }

        .error-message {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .success-message {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .success-message a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            background: white;
        }

        .form-input::placeholder {
            color: #999;
        }

        .register-btn {
            width: 100%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            margin-bottom: 1.5rem;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        .login-link {
            text-align: center;
            color: #666;
            font-size: 0.95rem;
        }

        .login-link a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #45a049;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 1.1rem;
        }

        .input-icon .form-input {
            padding-left: 3rem;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 1rem;
            }
            
            .register-card {
                padding: 2rem;
            }
            
            .register-header h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Join Smart Farming</h1>
                <p>Create your account to get started</p>
            </div>

            <?php if ($errors): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               id="username"
                               name="username" 
                               class="form-input"
                               placeholder="Choose a username" 
                               required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" 
                               id="email"
                               name="email" 
                               class="form-input"
                               placeholder="Enter your email address" 
                               required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
                               placeholder="Create a strong password" 
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="confirm_password"
                               name="confirm_password" 
                               class="form-input"
                               placeholder="Confirm your password" 
                               required>
                    </div>
                </div>

                <button type="submit" class="register-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
</body>
</html> 