<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    redirect('login.php');
}

$user = get_user();

if ($user['level'] < 20 && !is_admin()) {
    $_SESSION['message'] = "🎡 Chaos Wheel unlocks at Level 20!";
    $_SESSION['message_type'] = "error";
    redirect('dashboard.php');
}

$has_spun_today = ($user['last_spin_date'] === date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chaos Wheel - Side Quest</title>
    <style>
        body, html {
            margin: 0; padding: 0; width: 100%; height: 100%;
            background: #000; color: #fff; font-family: 'Courier New', Courier, monospace;
            overflow: hidden;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .navbar {
            position: absolute; top: 0; left: 0; width: 100%; padding: 15px;
            background: rgba(0,0,0,0.8); border-bottom: 2px solid #8b5cf6;
            display: flex; justify-content: space-between; box-sizing: border-box;
            z-index: 100;
        }
        .navbar a { color: #c4b5fd; text-decoration: none; font-weight: bold; border: 1px solid #c4b5fd; padding: 5px 15px; border-radius: 4px; }
        
        #wheel-container {
            position: relative; width: 300px; height: 300px;
            margin-top: 50px;
            border-radius: 50%;
            border: 10px solid #4c1d95;
            box-shadow: 0 0 50px rgba(139,92,246,0.6);
            overflow: hidden;
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
        }
        
        .slice {
            position: absolute; width: 50%; height: 50%;
            transform-origin: 100% 100%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 14px; text-shadow: 1px 1px 2px #000;
            left: 50%; top: 0;
        }
        
        /* Hacky CSS pure square slices */
        #slice1 { background: #fbbf24; transform: rotate(0deg) skewY(0deg); width: 150px; height: 150px; }
        #slice2 { background: #ef4444; transform: rotate(90deg) skewY(0deg); width: 150px; height: 150px; }
        #slice3 { background: #34d399; transform: rotate(180deg) skewY(0deg); width: 150px; height: 150px; }
        #slice4 { background: #c084fc; transform: rotate(270deg) skewY(0deg); width: 150px; height: 150px; }
        
        .slice-text {
            position: absolute; right: 20px; bottom: 20px;
            transform: rotate(45deg); text-transform: uppercase; text-align: center;
        }
        
        #pointer {
            position: absolute; top: -20px; left: 50%;
            transform: translateX(-50%); width: 0; height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 40px solid #fff;
            filter: drop-shadow(0 0 10px #fff);
            z-index: 10;
        }
        
        #spin-btn {
            margin-top: 50px; padding: 15px 40px; font-size: 24px; font-weight: bold;
            background: #eab308; color: #000; border: none; border-radius: 30px;
            cursor: pointer; box-shadow: 0 0 20px rgba(234, 179, 8, 0.5);
            transition: transform 0.2s, background 0.2s;
            text-transform: uppercase; letter-spacing: 2px;
        }
        
        #spin-btn:active { transform: scale(0.95); }
        #spin-btn:disabled { background: #555; color: #888; cursor: not-allowed; box-shadow: none; }
        
        #result-text {
            margin-top: 30px; font-size: 28px; font-weight: bold; height: 40px;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="dashboard.php">◄ Exit Casino</a>
        <div style="font-weight: bold; color: #c4b5fd; text-shadow: 0 0 10px #c4b5fd;">CHAOS_WHEEL_v1</div>
    </div>
    
    <div style="position: relative;">
        <div id="pointer"></div>
        <div id="wheel-container">
            <div class="slice" id="slice1"><div class="slice-text">+100 XP</div></div>
            <div class="slice" id="slice2"><div class="slice-text">-50 XP</div></div>
            <div class="slice" id="slice3"><div class="slice-text">+25 XP</div></div>
            <div class="slice" id="slice4"><div class="slice-text">QUEST</div></div>
        </div>
    </div>
    
    <button id="spin-btn" <?php echo $has_spun_today ? 'disabled' : ''; ?>>
        <?php echo $has_spun_today ? 'ALREADY SPUN TODAY' : 'SPIN WHEEL'; ?>
    </button>
    <div id="result-text"></div>
    
    <script src="assets/js/wheel.js"></script>
    
    <!-- Cookie Consent Banner -->
    <script src="assets/js/cookies.js"></script>
</body>
</html>
