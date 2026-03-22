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
    <link rel="icon" type="image/x-icon" href="./assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/images/favicon-16.png">
    <link rel="apple-touch-icon" href="./assets/images/icon-192.png">
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
        
        /* Download App Section */
        .download-app-section {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.15) 0%, rgba(33, 150, 243, 0.15) 100%);
            border: 2px solid rgba(76, 175, 80, 0.3);
            border-radius: 16px;
            padding: 60px 20px;
            margin: 60px 0;
            backdrop-filter: blur(10px);
        }

        .download-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .download-content h2 {
            font-size: 36px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #4CAF50, #2196F3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .download-content > p {
            font-size: 16px;
            color: #ccc;
            margin-bottom: 30px;
        }

        .download-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 40px;
            box-shadow: 0 8px 24px rgba(76, 175, 80, 0.3);
        }

        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(76, 175, 80, 0.4);
        }

        .download-icon-btn {
            font-size: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .platform-guide {
            margin-bottom: 40px;
        }

        .platform-guide h3 {
            font-size: 18px;
            color: #4CAF50;
            margin-bottom: 20px;
        }

        .platform-steps {
            display: grid;
            gap: 15px;
        }

        .step {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .step-number {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }

        .step-content h4 {
            font-size: 14px;
            color: #fff;
            margin-bottom: 4px;
        }

        .step-content p {
            font-size: 13px;
            color: #aaa;
            margin: 0;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .benefit {
            text-align: center;
        }

        .benefit-icon {
            font-size: 32px;
            display: block;
            margin-bottom: 10px;
        }

        .benefit strong {
            display: block;
            color: #4CAF50;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .benefit p {
            font-size: 12px;
            color: #999;
            margin: 0;
        }

        /* Phone Mockup */
        .download-mockup {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .phone-frame {
            width: 280px;
            height: 560px;
            background: #000;
            border-radius: 40px;
            padding: 14px;
            border: 8px solid #000;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 50px rgba(76, 175, 80, 0.2);
            position: relative;
        }

        .phone-frame::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 28px;
            background: #000;
            border-radius: 0 0 30px 30px;
            z-index: 10;
        }

        .notch {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 25px;
            background: #000;
            border-radius: 0 0 20px 20px;
            z-index: 5;
        }

        .screen {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
            border-radius: 36px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .screen-content {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* APK Download Section */
        .apk-download-section {
            background: linear-gradient(135deg, rgba(255, 152, 0, 0.1) 0%, rgba(244, 67, 54, 0.1) 100%);
            border: 2px solid rgba(255, 152, 0, 0.3);
            border-radius: 16px;
            padding: 60px 20px;
            margin: 60px 0;
            backdrop-filter: blur(10px);
        }

        .apk-download-section h2 {
            font-size: 36px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #FF9800, #FF5722);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
        }

        .apk-download-section > p {
            text-align: center;
            font-size: 16px;
            color: #ccc;
            margin-bottom: 40px;
        }

        .apk-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }

        .apk-option {
            background: rgba(255, 152, 0, 0.1);
            border: 1px solid rgba(255, 152, 0, 0.3);
            border-radius: 12px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .apk-option:hover {
            border-color: rgba(255, 152, 0, 0.6);
            background: rgba(255, 152, 0, 0.15);
            transform: translateY(-5px);
        }

        .apk-option-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .apk-option h3 {
            font-size: 20px;
            color: #FF9800;
            margin-bottom: 8px;
        }

        .apk-option > p {
            color: #ccc;
            margin-bottom: 20px;
        }

        .apk-steps {
            background: rgba(0, 0, 0, 0.3);
            border-left: 3px solid #FF9800;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .apk-steps ol {
            margin: 0;
            padding-left: 20px;
        }

        .apk-steps li {
            margin-bottom: 8px;
            color: #ddd;
            font-size: 14px;
        }

        .btn-apk {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            width: 100%;
            text-align: center;
            margin-bottom: 15px;
        }

        .btn-apk-primary {
            background: linear-gradient(135deg, #FF9800, #FF5722);
            color: #fff;
            box-shadow: 0 8px 24px rgba(255, 152, 0, 0.3);
        }

        .btn-apk-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(255, 152, 0, 0.4);
        }

        .btn-apk-secondary {
            background: rgba(255, 152, 0, 0.2);
            color: #FF9800;
            border: 2px solid #FF9800;
        }

        .btn-apk-secondary:hover {
            background: #FF9800;
            color: #000;
            transform: translateY(-2px);
        }

        .apk-note {
            font-size: 13px;
            color: #aaa;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 152, 0, 0.2);
        }

        .apk-note strong {
            color: #FF9800;
        }

        .apk-comparison table tr:first-child th {
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .download-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .download-mockup {
                margin-top: 30px;
            }

            .benefits-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .download-content h2 {
                font-size: 28px;
            }

            .btn-download {
                width: 100%;
                justify-content: center;
            }

            .apk-options {
                grid-template-columns: 1fr;
            }

            .apk-download-section h2 {
                font-size: 28px;
            }

            .apk-option {
                padding: 20px;
            }

            .btn-apk {
                width: 100%;
            }
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

        <!-- Download App Section -->
        <section class="download-app-section">
            <div class="download-container">
                <div class="download-content">
                    <div class="download-icon">📱</div>
                    <h2>Get Side Quest as an App</h2>
                    <p>Install Side Quest directly on your phone, tablet, or computer. Works offline too!</p>
                    
                    <button class="btn-download" id="downloadAppBtn" onclick="if(window.pwaInstaller) window.pwaInstaller.promptInstall(); else alert('App installation not available yet');">
                        <span class="download-icon-btn">⬇️</span>Download Free App
                    </button>
                    
                    <div class="platform-guide">
                        <h3>How to Install</h3>
                        
                        <div class="platform-steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h4>💙 Chrome/Edge (Desktop)</h4>
                                    <p>Look for <strong>📥 Install</strong> button in address bar</p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h4>📱 Android (Chrome)</h4>
                                    <p>Tap menu <strong>(⋮)</strong> → <strong>Install app</strong></p>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h4>🍎 iPhone/iPad (Safari)</h4>
                                    <p>Tap Share <strong>(↑)</strong> → <strong>Add to Home Screen</strong></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="benefits-grid">
                        <div class="benefit">
                            <span class="benefit-icon">⚡</span>
                            <strong>Lightning Fast</strong>
                            <p>Cached content loads instantly</p>
                        </div>
                        <div class="benefit">
                            <span class="benefit-icon">📡</span>
                            <strong>Works Offline</strong>
                            <p>Play even without internet</p>
                        </div>
                        <div class="benefit">
                            <span class="benefit-icon">🔔</span>
                            <strong>Notifications</strong>
                            <p>Get push notifications</p>
                        </div>
                        <div class="benefit">
                            <span class="benefit-icon">📲</span>
                            <strong>Native Feel</strong>
                            <p>Fullscreen app experience</p>
                        </div>
                    </div>
                </div>

                <div class="download-mockup">
                    <div class="phone-frame">
                        <div class="notch"></div>
                        <div class="screen">
                            <div class="screen-content">
                                <div style="text-align: center; padding: 20px;">
                                    <div style="font-size: 40px; margin-bottom: 10px;">🎮</div>
                                    <div style="font-weight: bold; color: #333;">Side Quest</div>
                                    <div style="font-size: 12px; color: #999; margin-top: 5px;">App Installed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- APK Download Section -->
        <section class="apk-download-section">
            <div class="container">
                <h2>📥 Download Android APK</h2>
                <p>Install Side Quest directly as an Android app file</p>

                <div class="apk-options">
                    <!-- Option 1: Direct Download -->
                    <div class="apk-option">
                        <div class="apk-option-icon">⚡</div>
                        <h3>Quick Download (Recommended)</h3>
                        <p>Get the APK file instantly, no waiting</p>
                        
                        <div class="apk-steps">
                            <ol>
                                <li>Click "Download APK" below</li>
                                <li>File downloads to your phone</li>
                                <li>Open file and tap "Install"</li>
                                <li>Done! App on home screen</li>
                            </ol>
                        </div>

                        <button class="btn-apk btn-apk-primary" onclick="downloadAPK()">
                            ⬇️ Download APK Now
                        </button>

                        <div class="apk-note">
                            <strong>Fastest method:</strong> Works instantly on any Android phone
                        </div>
                    </div>

                    <!-- Option 2: PWA Builder -->
                    <div class="apk-option">
                        <div class="apk-option-icon">🚀</div>
                        <h3>Generate APK (Alternative)</h3>
                        <p>Create a native Android installation file</p>
                        
                        <div class="apk-steps">
                            <ol>
                                <li>Click the button below</li>
                                <li>Wait for APK to generate (2-3 minutes)</li>
                                <li>Download the .apk file</li>
                                <li>Open on Android & tap to install</li>
                            </ol>
                        </div>

                        <a href="https://www.pwabuilder.com/generate" target="_blank" class="btn-apk btn-apk-secondary">
                            🌐 Open PWA Builder
                        </a>

                        <div class="apk-note">
                            <strong>Note:</strong> Paste our URL: <code style="background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px;">https://sidequest.app</code>
                        </div>
                    </div>
                </div>

                <div class="apk-comparison" style="margin-top: 40px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <h3>Which Should I Use?</h3>
                    <table style="width: 100%; text-align: left; margin-top: 15px;">
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <th style="padding: 10px; color: #4CAF50;">Method</th>
                            <th style="padding: 10px; color: #4CAF50;">Installation</th>
                            <th style="padding: 10px; color: #4CAF50;">Updates</th>
                            <th style="padding: 10px; color: #4CAF50;">Best For</th>
                        </tr>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 10px;">📥 Generated APK</td>
                            <td style="padding: 10px;">Via Google Play</td>
                            <td style="padding: 10px;">Manual</td>
                            <td style="padding: 10px;">Distribution</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;">⚡ Quick Install</td>
                            <td style="padding: 10px;">Instant</td>
                            <td style="padding: 10px;">Automatic</td>
                            <td style="padding: 10px;">Users</td>
                        </tr>
                    </table>
                </div>
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
    
    <script>
        // APK Download Function
        function downloadAPK() {
            // Create a download link and trigger it
            const link = document.createElement('a');
            link.href = './download_apk.php';
            link.download = 'BoringLife.apk';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
