<?php
/**
 * Privacy Policy Page
 */
require_once(__DIR__ . '/config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Side Quest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            line-height: 1.8;
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
        
        .navbar h1 {
            font-size: 24px;
            color: #4CAF50;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        
        .navbar a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .navbar a:hover {
            color: #45a049;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 50px 20px;
        }
        
        h1 {
            color: #4CAF50;
            margin-bottom: 30px;
            font-size: 32px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 15px;
        }
        
        h2 {
            color: #2196F3;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        p {
            margin-bottom: 15px;
            color: #ddd;
        }
        
        ul, ol {
            margin-left: 30px;
            margin-bottom: 15px;
        }
        
        li {
            margin-bottom: 10px;
            color: #ddd;
        }
        
        .contact-info {
            background: rgba(76, 175, 80, 0.1);
            border-left: 4px solid #4CAF50;
            padding: 20px;
            margin: 30px 0;
            border-radius: 4px;
        }
        
        .contact-info p {
            margin-bottom: 10px;
        }
        
        .contact-info a {
            color: #4CAF50;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .contact-info a:hover {
            color: #45a049;
            text-decoration: underline;
        }
        
        footer {
            text-align: center;
            padding: 30px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #aaa;
            margin-top: 60px;
        }
        
        footer a {
            color: #4CAF50;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        footer a:hover {
            color: #45a049;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php"><h1>🎮 Side Quest</h1></a>
        <a href="index.php">← Back to Home</a>
    </nav>

    <div class="container">
        <h1>Privacy Policy</h1>
        <p><strong>Last Updated: March 2026</strong></p>

        <h2>1. Introduction</h2>
        <p>Welcome to Side Quest ("we," "us," "our," or "Company"). We are committed to protecting your privacy and ensuring you have a positive experience on our platform. This Privacy Policy explains how we collect, use, disclose, and safeguard your information.</p>

        <h2>2. Information We Collect</h2>
        <p>We collect information you provide directly and information collected automatically as you use our service.</p>
        
        <h3 style="color: #90CAF9; margin-top: 20px;">Information You Provide:</h3>
        <ul>
            <li>Account registration information (username, email, password)</li>
            <li>Profile information (display name, avatar, bio)</li>
            <li>Quest submission data (text, images, videos)</li>
            <li>Communication data (messages, notifications)</li>
            <li>Payment information (if applicable)</li>
        </ul>

        <h3 style="color: #90CAF9; margin-top: 20px;">Information Collected Automatically:</h3>
        <ul>
            <li>Browser type and IP address</li>
            <li>Pages visited and time spent</li>
            <li>Device information and operating system</li>
            <li>Cookies and similar tracking technologies</li>
        </ul>

        <h2>3. How We Use Your Information</h2>
        <p>We use collected information for:</p>
        <ul>
            <li>Providing and improving our service</li>
            <li>Personalizing your experience</li>
            <li>Communicating with you about updates and features</li>
            <li>Detecting and preventing fraud or abuse</li>
            <li>Analyzing usage patterns and trends</li>
            <li>Complying with legal obligations</li>
        </ul>

        <h2>4. How We Share Your Information</h2>
        <p>We do not sell your personal information. We may share information:</p>
        <ul>
            <li>With service providers who assist us (hosting, analytics)</li>
            <li>When required by law or legal process</li>
            <li>To protect our rights and prevent abuse</li>
            <li>In aggregated, anonymized form for analytics</li>
            <li>Public profile information is visible to other users</li>
        </ul>

        <h2>5. Cookies and Tracking</h2>
        <p>We use cookies to:</p>
        <ul>
            <li>Maintain your session and login status</li>
            <li>Remember your preferences</li>
            <li>Track usage patterns and improve our service</li>
            <li>Show you relevant content</li>
        </ul>
        <p>By using Side Quest, you consent to our cookie policy. You can disable cookies in your browser settings, but some features may not work properly.</p>

        <h2>6. Data Security</h2>
        <p>We implement industry-standard security measures to protect your data, including encryption, secure transmission protocols, and regular security audits. However, no method of transmission over the internet is 100% secure.</p>

        <h2>7. Your Rights</h2>
        <p>You have the right to:</p>
        <ul>
            <li>Access your personal data</li>
            <li>Request correction of inaccurate data</li>
            <li>Request deletion of your account and data</li>
            <li>Opt-out of marketing communications</li>
            <li>Export your data</li>
        </ul>

        <h2>8. Children's Privacy</h2>
        <p>Side Quest is not intended for users under 13 years of age. We do not knowingly collect personal information from children. If we learn we have collected personal information from a child, we will delete such information promptly.</p>

        <h2>9. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. We will notify you of significant changes by posting the updated policy on our website and updating the "Last Updated" date.</p>

        <h2>10. Contact Us</h2>
        <div class="contact-info">
            <p><strong>If you have questions about this Privacy Policy or our practices, please contact us:</strong></p>
            <p>
                📧 <strong>Email:</strong> <a href="mailto:boringlifesuck3@gmail.com">boringlifesuck3@gmail.com</a>
            </p>
            <p>
                📞 <strong>Phone:</strong> <a href="tel:+254702060628">+254 702 060 628</a>
            </p>
        </div>

        <h2>11. Acknowledgment</h2>
        <p>By using Side Quest, you acknowledge that you have read this Privacy Policy and agree to its terms.</p>
    </div>

    <footer>
        <p>&copy; 2026 Side Quest. <a href="privacy.php">Privacy Policy</a> | <strong>Contact:</strong> <a href="mailto:boringlifesuck3@gmail.com">boringlifesuck3@gmail.com</a> | <a href="tel:+254702060628">+254 702 060 628</a></p>
    </footer>
</body>
</html>
