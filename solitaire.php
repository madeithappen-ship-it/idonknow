<?php
require_once(__DIR__ . '/config.php');

if (!is_logged_in()) {
    redirect('login.php');
}

$user = get_user();
$user_id = $user['id'];

// Lock to Level 5 or Admins
if ($user['level'] < 5 && !is_admin()) {
    $_SESSION['message'] = "🃏 Solitaire Quests unlock at Level 5!";
    $_SESSION['message_type'] = "error";
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Solitaire Quests - Side Quest</title>
    <style>
        :root {
            --card-width: 60px;
            --card-height: 84px;
            --card-spacing: 15px;
            --card-overlap: 25px;
        }

        @media (min-width: 768px) {
            :root {
                --card-width: 90px;
                --card-height: 126px;
                --card-spacing: 20px;
                --card-overlap: 30px;
            }
        }

        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #0f172a 0%, #064e3b 100%);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden; /* Prevent scrolling while dragging natively */
            touch-action: none; /* Disable pull-to-refresh on mobile */
        }

        .navbar {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar a {
            color: #fff;
            text-decoration: none;
            padding: 5px 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            font-size: 14px;
        }

        #game-board {
            width: 100%;
            height: 100vh;
            padding-top: 60px; /* offset for navbar */
            position: relative;
            user-select: none;
        }

        .row {
            display: flex;
            justify-content: center;
            gap: var(--card-spacing);
            margin-top: 20px;
        }

        .deck-zone, .foundation-zone, .tableau-zone {
            display: flex;
            gap: var(--card-spacing);
        }

        /* The physical slot placeholder where cards can be dropped */
        .card-slot {
            width: var(--card-width);
            height: var(--card-height);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            position: relative;
        }

        .card {
            width: var(--card-width);
            height: var(--card-height);
            border-radius: 6px;
            background: #fff;
            position: absolute;
            box-shadow: 0 4px 6px rgba(0,0,0,0.5);
            cursor: grab;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 4px 6px;
            box-sizing: border-box;
            transform-style: preserve-3d;
            transition: transform 0.2s ease, top 0.2s ease, left 0.2s ease;
        }

        .card.dragging {
            z-index: 100 !important;
            cursor: grabbing;
            transition: none; /* Instant tracking on mouse */
        }

        /* Front / Back flip logic */
        .card-face {
            backface-visibility: hidden;
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 6px;
        }

        .card-front {
            background: #fff;
            color: #000;
        }

        .card-back {
            background: repeating-linear-gradient(45deg, #0f172a, #0f172a 10px, #1e293b 10px, #1e293b 20px);
            border: 2px solid #fff;
            transform: rotateY(180deg);
        }

        .card.facedown {
            transform: rotateY(180deg);
        }

        .card.red .suit, .card.red .rank { color: #e11d48; }
        .card.black .suit, .card.black .rank { color: #0f172a; }

        .rank { font-size: 16px; font-weight: bold; line-height: 1; }
        .suit { font-size: 20px; line-height: 1; text-align: center; margin-top: 4px; }
        
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
            background: #1e293b;
            padding: 30px;
            border-radius: 12px;
            border: 2px solid #f59e0b;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

    </style>
</head>
<body>

    <div class="navbar">
        <a href="dashboard.php">← Back to Dashboard</a>
        <div style="font-weight: bold; color: #f59e0b;">Solitaire Quests</div>
        <div>
            <button onclick="location.reload()" style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 15px; font-weight: bold; transition: 0.2s;">➕ New Game</button>
            <button onclick="reshuffle()" style="background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #f59e0b; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 15px; font-weight: bold; transition: 0.2s;">↻ Reshuffle (+50 Moves)</button>
            <span id="score-readout" style="font-size: 14px; color: #aaa;">Score: 0 | Moves: 0</span>
        </div>
    </div>

    <div id="game-board">
        <!-- Top Row: Draw, Waste, Foundations -->
        <div class="row" style="margin-bottom: 40px;">
            <div class="deck-zone">
                <div class="card-slot" id="draw-pile" style="background: rgba(0,0,0,0.4); border-style: solid;"></div>
                <div class="card-slot" id="waste-pile" style="border: none;"></div>
            </div>
            
            <div style="width: 40px;"></div> <!-- Spacer -->

            <div class="foundation-zone">
                <div class="card-slot" data-pile="f0"></div>
                <div class="card-slot" data-pile="f1"></div>
                <div class="card-slot" data-pile="f2"></div>
                <div class="card-slot" data-pile="f3"></div>
            </div>
        </div>

        <!-- Bottom Row: Tableau -->
        <div class="row">
            <div class="tableau-zone">
                <div class="card-slot" data-pile="t0"></div>
                <div class="card-slot" data-pile="t1"></div>
                <div class="card-slot" data-pile="t2"></div>
                <div class="card-slot" data-pile="t3"></div>
                <div class="card-slot" data-pile="t4"></div>
                <div class="card-slot" data-pile="t5"></div>
                <div class="card-slot" data-pile="t6"></div>
            </div>
        </div>
    </div>

    <!-- Quest Popup Trigger -->
    <div class="quest-modal" id="quest-modal">
        <div class="quest-box">
            <h2 style="color: #f59e0b; margin-bottom: 10px;">🌟 QUEST CARD FLIPPED!</h2>
            <p style="color: #ccc; font-size: 14px; margin-bottom: 20px;">You've uncovered a real-life challenge. Complete it to unlock massive Undo boosts and XP!</p>
            <div id="quest-text" style="font-size: 18px; font-weight: bold; padding: 20px; background: rgba(0,0,0,0.5); border-radius: 8px; margin-bottom: 20px;">
                Loading quest...
            </div>
            
            <div id="quest-upload-area" style="display: none; margin-bottom: 15px;">
                <textarea id="solitaire-text-proof" placeholder="Type out your proof here... (Optional if uploading file)" rows="3" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #0f172a; color: #fff; margin-bottom: 15px; font-family: inherit; font-size: 14px; box-sizing: border-box;"></textarea>
                <input type="file" id="solitaire-proof" accept="image/*,video/*" style="margin-bottom: 15px; color: #fff; width: 100%; border: 1px solid #333; padding: 10px; border-radius: 6px; box-sizing: border-box;">
                <button onclick="submitSolitaireProof()" style="background: #10b981; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; box-shadow: 0 4px 10px rgba(16,185,129,0.3);">Submit Proof & Resume Game</button>
            </div>
            
            <div id="quest-actions">
                <button onclick="acceptQuest()" style="background: #10b981; color: #fff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; margin-bottom: 10px; box-shadow: 0 4px 10px rgba(16,185,129,0.3);">Accept Challenge</button>
                <button onclick="skipQuest()" style="background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 10px 20px; border-radius: 6px; cursor: pointer; width: 100%;">Skip (Penalty: 50 Moves)</button>
            </div>
        </div>
    </div>

    <script src="assets/js/solitaire.js"></script>

</body>
</html>
