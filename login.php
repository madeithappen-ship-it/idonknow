<?php
/**
 * User Login Page
 */

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required';
        } else {
            $result = $auth->login($username, $password);

            if ($result['success']) {
                if (!empty($result['is_admin'])) {
                    $adminToken = config('admin_url_secret');
                    redirect('admin.php?token=' . urlencode($adminToken), 'Welcome back, Admin!', 'success');
                }
                redirect('dashboard.php', 'Welcome! Let\'s find you a quest.', 'success');
            } else {
                $error = $result['error'];
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
    <title>Login - <?php echo escape(config('app_name')); ?></title>
    <link rel="icon" type="image/png" href="/boringlife/assets/images/favicon.png">
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
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            color: #ff9999;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        <p class="subtitle">Welcome back, quest taker!</p>
        
        <?php if ($error): ?>
            <div class="alert"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
            
            <button type="submit">Login</button>
        </form>
        
        <div class="form-footer">
            Don't have an account? <a href="register.php">Create one here</a>
        </div>
    </div>
    
    <!-- Cookie Consent Banner -->
    <script src="assets/js/cookies.js"></script>
</body>
</html>
