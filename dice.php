<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    redirect('login.php');
}

$user = get_user();

if ($user['level'] < 20 && !is_admin()) {
    $_SESSION['message'] = "🎲 Dice Game unlocks at Level 20!";
    $_SESSION['message_type'] = "error";
    redirect('dashboard.php');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dice Game - Side Quest</title>
    <style>
        body, html {
            margin: 0; padding: 0; width: 100%; height: 100%;
            background: #050505; color: #fff; font-family: 'Courier New', Courier, monospace;
            overflow: hidden;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .navbar {
            position: absolute; top: 0; left: 0; width: 100%; padding: 15px;
            background: rgba(0,0,0,0.8); border-bottom: 2px solid #ef4444;
            display: flex; justify-content: space-between; box-sizing: border-box;
            z-index: 100;
        }
        .navbar a { color: #fca5a5; text-decoration: none; font-weight: bold; border: 1px solid #fca5a5; padding: 5px 15px; border-radius: 4px; }
        
        .dice-container {
            perspective: 1000px;
            margin-top: 50px;
            margin-bottom: 40px;
        }
        
        .dice {
            width: 120px;
            height: 120px;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 1.5s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            transform: rotateX(-20deg) rotateY(-20deg);
        }
        
        .face {
            position: absolute;
            width: 120px; height: 120px;
            background: rgba(239, 68, 68, 0.9);
            border: 2px solid #fca5a5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            font-weight: bold;
            color: #fff;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
            text-shadow: 2px 2px 5px rgba(0,0,0,0.8);
        }
        
        .front  { transform: translateZ(60px); }
        .back   { transform: rotateY(180deg) translateZ(60px); }
        .right  { transform: rotateY(90deg) translateZ(60px); }
        .left   { transform: rotateY(-90deg) translateZ(60px); }
        .top    { transform: rotateX(90deg) translateZ(60px); }
        .bottom { transform: rotateX(-90deg) translateZ(60px); }
        
        #roll-btn {
            padding: 15px 40px; font-size: 24px; font-weight: bold;
            background: #ef4444; color: #fff; border: none; border-radius: 30px;
            cursor: pointer; box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
            transition: transform 0.2s, background 0.2s;
            text-transform: uppercase; letter-spacing: 2px;
        }
        #roll-btn:active { transform: scale(0.95); }
        #roll-btn:disabled { background: #555; color: #888; cursor: not-allowed; box-shadow: none; }
        
        #reroll-text {
            margin-top: 15px; font-size: 14px; color: #aaa;
        }
        
        #quest-card {
            margin-top: 30px;
            width: 300px;
            background: #111;
            border: 2px solid #333;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            display: none;
            opacity: 0;
            transition: opacity 0.5s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }
        
    </style>
</head>
<body>
    <div class="navbar">
        <a href="dashboard.php">◄ Exit Quest Roll</a>
        <div style="font-weight: bold; color: #fca5a5; text-shadow: 0 0 10px #fca5a5;">DICE_ROLLER_v1</div>
    </div>
    
    <div class="dice-container">
        <div class="dice" id="dice">
            <div class="face front">1</div>
            <div class="face back">6</div>
            <div class="face right">3</div>
            <div class="face left">4</div>
            <div class="face top">2</div>
            <div class="face bottom">5</div>
        </div>
    </div>
    
    <button id="roll-btn">ROLL DICE</button>
    <div id="reroll-text">Checking rerolls...</div>
    
    <div id="quest-card">
        <div id="quest-difficulty" style="color: #0ff; font-weight: bold; text-transform: uppercase; margin-bottom: 5px;">DIFFICULTY</div>
        <div id="quest-title" style="font-size: 20px; margin-bottom: 10px;">Quest Title</div>
        <div id="quest-desc" style="color: #aaa; font-size: 14px; margin-bottom: 15px;">Description...</div>
        <div id="quest-xp" style="color: #fbbf24; font-weight: bold;">+XP</div>
        <br>
        <button onclick="window.location.href='dashboard.php'" style="background: #333; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Head to Dashboard to Upload</button>
    </div>
    
    <script src="assets/js/dice.js"></script>
    <!-- Cookie Consent Banner -->
    <script src="assets/js/cookies.js"></script>
</body>
</html>
