<?php
/**
 * User Registration Page
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // Validate form
        if (empty($username) || empty($email) || empty($password)) {
            $error = 'All fields are required';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match';
        } else {
            // Attempt registration
            require_once(__DIR__ . '/auth.php');
            $result = $auth->register($username, $email, $password, $username);
            
            if ($result['success']) {
                $success = 'Registration successful! Please <a href="login.php">log in</a>.';
            } else {
                $error = $result['error'] ?? 'Registration failed';
            }
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo escape(config('app_name')); ?></title>
    <link rel="icon" type="image/x-icon" href="./assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/images/favicon-16.png">
    <link rel="apple-touch-icon" href="./assets/images/icon-192.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        .container {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        h1 {
            margin-bottom: 10px;
            font-size: 28px;
            text-align: center;
        }
        
        .subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #4CAF50;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.2);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            color: #ff9999;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.5);
            color: #99ff99;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .form-footer a {
            color: #4CAF50;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .form-footer a:hover {
            color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Account</h1>
        <p class="subtitle">Join the side quest adventure</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo ($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       pattern="[a-zA-Z0-9_]{3,20}" 
                       title="3-20 characters, letters, numbers, underscore only">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       minlength="8" 
                       title="At least 8 characters">
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
            
            <button type="submit">Create Account</button>
        </form>
        
        <div class="form-footer">
            Already have an account? <a href="login.php">Log in here</a>
        </div>
    </div>
    
    <!-- Cookie Consent Banner -->
    <script src="assets/js/cookies.js"></script>

    <!-- Progressive Web App Helper -->
    <script src="assets/js/pwa-helper.js"></script>
</body>
</html>