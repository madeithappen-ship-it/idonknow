<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    redirect('login.php');
}

$user = get_user();
$user_id = $user['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cyber Hunt - Side Quest</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #050510;
            color: #fff;
            font-family: 'Courier New', Courier, monospace;
            overflow: hidden;
            touch-action: none; /* Block native gestures */
        }
        
        .navbar {
            background: rgba(0, 0, 0, 0.8);
            border-bottom: 2px solid #0ff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar a {
            color: #0ff;
            text-decoration: none;
            padding: 5px 15px;
            border: 1px solid #0ff;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 0 10px rgba(0,255,255,0.2);
            text-transform: uppercase;
        }

        #game-container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        canvas {
            display: block;
            width: 100%;
            height: 100%;
        }

        #virtual-joystick-container {
            position: absolute;
            bottom: 40px;
            left: 40px;
            width: 120px;
            height: 120px;
            background: rgba(0, 255, 255, 0.1);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 50%;
            z-index: 900;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 20px rgba(0,255,255,0.1);
        }

        @media (min-width: 1024px) {
            #virtual-joystick-container {
                display: none; /* Hide on desktop */
            }
        }

        #virtual-joystick-knob {
            width: 50px;
            height: 50px;
            background: rgba(0, 255, 255, 0.5);
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(0,255,255,0.5);
            position: absolute;
            pointer-events: none;
        }

        #hud {
            position: absolute;
            top: 70px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #f0f;
            padding: 15px;
            border-radius: 8px;
            z-index: 900;
            box-shadow: 0 0 15px rgba(255, 0, 255, 0.2);
            pointer-events: none;
        }

        .hud-line {
            margin: 5px 0;
            font-size: 14px;
            color: #0ff;
            text-shadow: 0 0 5px #0ff;
        }

        #sonar-warning {
            color: #f0f;
            text-shadow: 0 0 10px #f0f;
            font-weight: bold;
            display: none;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            50% { opacity: 0; }
        }

        /* Quest Modal (reused similarly from Solitaire) */
        .quest-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.9);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .quest-box {
            background: #000;
            padding: 30px;
            border-radius: 12px;
            border: 2px solid #0ff;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
        }

    </style>
</head>
<body>

    <div class="navbar">
        <a href="dashboard.php">◄ Exit Simulation</a>
        <div style="font-weight: bold; color: #f0f; text-shadow: 0 0 10px #f0f;">CYBER_HUNT_v1</div>
    </div>

    <div id="game-container">
        <!-- Main Rendering Context -->
        <canvas id="gameCanvas"></canvas>
        
        <!-- Mobile Joystick -->
        <div id="virtual-joystick-container">
            <div id="virtual-joystick-knob"></div>
        </div>

        <!-- HUD -->
        <div id="hud">
            <div class="hud-line">COORD: <span id="hud-x">1000</span>, <span id="hud-y">1000</span></div>
            <div class="hud-line">TREASURES: <span id="hud-treasures">0</span>/20</div>
            <div class="hud-line" id="sonar-warning">⚠ ANOMALY DETECTED NEARBY ⚠</div>
        </div>
    </div>

    <!-- Quest Delivery Terminal -->
    <div class="quest-modal" id="quest-modal">
        <div class="quest-box">
            <h2 style="color: #0ff; margin-bottom: 10px; text-shadow: 0 0 10px #0ff;">◆ ANOMALY INTERCEPTED ◆</h2>
            <p style="color: #ccc; font-size: 14px; margin-bottom: 20px;">You have uncovered isolated system cache. Complete this real-world diagnostic challenge to permanently extract XP and Rare data!</p>
            
            <div id="quest-text" style="font-size: 18px; font-weight: bold; padding: 20px; background: rgba(0,255,255,0.1); border-radius: 8px; margin-bottom: 20px; border: 1px dashed #0ff;">
                Establishing secure connection to mainframe...
            </div>
            
            <div id="quest-upload-area" style="display: none; margin-bottom: 15px;">
                <input type="file" id="hunt-proof" accept="image/*,video/*" style="margin-bottom: 15px; color: #0ff; width: 100%; border: 1px solid #0ff; padding: 10px; border-radius: 6px; background: #000;">
                <button onclick="submitHuntProof()" style="background: #0ff; color: #000; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; box-shadow: 0 0 15px rgba(0,255,255,0.5);">UPLOAD PAYLOAD & RESUME</button>
            </div>
            
            <div id="quest-actions">
                <button onclick="acceptQuest()" style="background: #0ff; color: #000; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; margin-bottom: 10px; box-shadow: 0 0 15px rgba(0,255,255,0.5);">ACCEPT DIAGNOSTIC</button>
                <button onclick="skipQuest()" style="background: transparent; border: 1px solid #f0f; color: #f0f; padding: 10px 20px; border-radius: 6px; cursor: pointer; width: 100%;">BYPASS ANOMALY (Ignore)</button>
            </div>
        </div>
    </div>

    <script src="assets/js/hunt.js"></script>

</body>
</html>
