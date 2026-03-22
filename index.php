<?php
/**
 * Main Entry Point
 * Routes users to dashboard if logged in, or login page if not
 */

require_once(__DIR__ . '/config.php');

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';

// If admin is logged in, redirect to admin panel
if ($is_admin && isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

// If regular user is logged in, redirect to dashboard
if ($is_logged_in && isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Not logged in - show landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Side Quest - Gamified Real-Life Challenges</title>
    <link rel="icon" type="image/png" href="./assets/images/favicon.png">
    <link rel="manifest" href="./manifest.json">
    <meta name="theme-color" content="#4CAF50">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
            color: #fff;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .navbar {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }

        .navbar-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
        }

        .navbar h1 {
            font-size: 24px;
            color: #4CAF50;
            font-weight: 700;
            margin: 0;
        }

        @media (max-width: 600px) {
            .navbar-logo {
                height: 32px;
            }
            .navbar h1 {
                font-size: 18px;
            }
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #fff;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
        }
        
        .nav-links .btn-login {
            background: #4CAF50;
            color: #fff;
        }
        
        .nav-links .btn-login:hover {
            background: #45a049;
        }
        
        .nav-links .btn-register {
            background: #2196F3;
            color: #fff;
        }
        
        .nav-links .btn-register:hover {
            background: #0b7dda;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .hero {
            text-align: center;
            padding: 60px 20px;
        }
        
        .hero h2 {
            font-size: 48px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #4CAF50, #2196F3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            color: #ccc;
        }
        
        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            font-size: 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #2196F3;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #0b7dda;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(33, 150, 243, 0.3);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin: 60px 0;
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
            border-color: #4CAF50;
        }
        
        .feature-card h3 {
            color: #4CAF50;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .feature-card p {
            color: #aaa;
            line-height: 1.8;
        }
        
        .emoji {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        footer {
            text-align: center;
            padding: 30px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #aaa;
            margin-top: 60px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="./" class="navbar-brand">
            <img src="./assets/images/logo.png" alt="Side Quest Logo" class="navbar-logo">
            <h1>🎮 Side Quest</h1>
        </a>
        <div class="nav-links">
            <a href="login.php" class="btn-login">Login</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="hero">
            <h2>My Life is Boring and I Want to Do a Side Quest But Don't Know What To Do</h2>
            <p>Accept challenges, complete quests, and level up your real life!</p>
            <div class="cta-buttons">
                <a href="login.php" class="btn btn-primary">Login to Play</a>
                <a href="register.php" class="btn btn-secondary">Create Account</a>
            </div>
        </div>

        <!-- Features Section -->
        <section class="features">
            <div class="feature-card">
                <div class="emoji">⚡</div>
                <h3>Daily Challenges</h3>
                <p>Get assigned random quests every day. From truth challenges to dares, social experiments to physical feats.</p>
            </div>
            
            <div class="feature-card">
                <div class="emoji">📸</div>
                <h3>Proof & Verification</h3>
                <p>Submit photos or videos as proof of completed quests. Our admin team verifies authentic completions.</p>
            </div>
            
            <div class="feature-card">
                <div class="emoji">⭐</div>
                <h3>Level Up & Rewards</h3>
                <p>Earn XP points with each completed quest. Level up, increase your streak, and unlock achievements.</p>
            </div>
            
            <div class="feature-card">
                <div class="emoji">🏆</div>
                <h3>Leaderboard</h3>
                <p>Compete with other players globally. Climb the leaderboard and become a legend in the Side Quest community.</p>
            </div>
            
            <div class="feature-card">
                <div class="emoji">🎲</div>
                <h3>Diverse Quest Types</h3>
                <p>Truth, Dare, Social, Dark Humor, Physical, and Challenge quests. Something for everyone!</p>
            </div>
            
            <div class="feature-card">
                <div class="emoji">🚀</div>
                <h3>Never Boring Again</h3>
                <p>Step outside your comfort zone. Do things you've always wanted to do in a fun, supported community.</p>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer>
        <div style="margin-bottom: 15px;">
            <p>&copy; 2026 Side Quest. Break the boredom. Start your adventure now.</p>
        </div>
        <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-bottom: 10px; color: #aaa; font-size: 14px;">
            <a href="privacy.php" style="color: #4CAF50; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='#45a049'" onmouseout="this.style.color='#4CAF50'">Privacy Policy</a>
            <span>•</span>
            <a href="mailto:boringlifesuck3@gmail.com" style="color: #4CAF50; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='#45a049'" onmouseout="this.style.color='#4CAF50'">📧 boringlifesuck3@gmail.com</a>
            <span>•</span>
            <a href="tel:+254702060628" style="color: #4CAF50; text-decoration: none; transition: all 0.3s ease;" onmouseover="this.style.color='#45a049'" onmouseout="this.style.color='#4CAF50'">📞 +254 702 060 628</a>
        </div>
    </footer>

    <!-- Live Chat System -->
    <script src="assets/js/live-chat.js"></script>
    
    <!-- Push Notifications -->
    <script src="assets/js/push-notifications.js"></script>
    
    <!-- Cookie Consent Banner -->
    <script src="assets/js/cookies.js"></script>

    <!-- Progressive Web App Helper -->
    <script src="assets/js/pwa-helper.js"></script>
</body>
</html>
