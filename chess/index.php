<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}
$username = get_user()['username'];
?>
<!DOCTYPE html>
<html lang="en" data-color-mode="dark" class="dark-mode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="true">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Play Chess Online</title>
    <!-- Use FontAwesome for icons like Chess.com -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/style.css">
</head>
<body class="theme-background">

<!-- Left: Main Board Area -->
<div class="layout-board">
    <div class="player-tag player-top">
        <div class="user-avatar"><i class="fa-solid fa-robot"></i></div>
        <div class="user-info">
            <span class="username" id="opponent-name">Opponent</span>
            <span class="rating">(1200)</span>
        </div>
    </div>
    
    <!-- Game Timer Display -->
    <div id="game-timer" style="
        display: none;
        text-align: center;
        padding: 10px;
        margin: 10px 0;
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        border: 2px solid #4CAF50;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        color: #4CAF50;
    ">
        ⏱️ Time: <span id="timer-display">30:00</span>
    </div>
    
    <!-- Board with Evaluation Bar -->
    <div class="board-container">
        <!-- Evaluation Bar -->
        <div class="eval-bar-container">
            <div class="eval-bar" id="eval-bar">
                <div class="eval-bar-fill white" id="eval-bar-fill"></div>
                <div class="eval-text" id="eval-text">0.0</div>
            </div>
        </div>
        
        <div id="chessboard" class="chessboard">
            <!-- Board squares will be injected by script.js -->
        </div>
    </div>
    
    <div class="player-tag player-bottom">
        <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
        <div class="user-info">
            <span class="username" id="my-username"><?php echo htmlspecialchars($username); ?></span>
            <span class="rating">(1500)</span>
        </div>
    </div>
</div>

<!-- Right: Sidebar Area -->
<div class="layout-sidebar">
    <div class="sidebar-tabs">
        <div class="tab active" data-target="panel-new-game">New Game</div>
        <div class="tab" data-target="panel-games">Games</div>
        <div class="tab" data-target="panel-live">🔴 Live</div>
        <div class="tab" data-target="panel-players">Players</div>
    </div>
    
    <div class="sidebar-content" id="panel-new-game">
        <div class="play-options">
            <div class="header-image">
                <i class="fa-solid fa-chess-knight"></i>
            </div>
            <h2 class="section-title">Play Chess</h2>
            
            <button id="btn-create-link" class="btn btn-primary" style="margin-bottom: 20px;">
                <i class="fa-solid fa-link"></i> <span class="title">Create Challenge Link</span>
            </button>
            
            <div id="share-link-container" class="hidden" style="margin-top: 15px; background: #312e2b; padding: 10px; border-radius: 5px;">
                <p style="color: #bfdbfe; font-size: 13px; margin: 0 0 5px 0;">Share this link to play:</p>
                <div style="display: flex; gap: 5px;">
                    <input type="text" id="share-link-input" readonly style="flex:1; background: #000; color:#fff; border: 1px solid #403d39; padding: 8px; border-radius: 3px; font-size: 12px;">
                    <button id="btn-copy-link" class="btn btn-secondary" style="padding: 0 15px;"><i class="fa-regular fa-copy"></i></button>
                </div>
            </div>

            <div style="margin-bottom: 20px; text-align: left; background: #2f2d29; padding: 15px; border-radius: 8px;">
                <label style="color: #888; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Join a Game</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="join-link-input" placeholder="Paste link or code..." class="form-control" style="flex: 1; background: #262421; color: #fff; border: 1px solid #403d39; border-radius: 5px; padding: 8px 12px; font-size: 14px; min-width: 0;">
                    <button id="btn-join-link" class="btn btn-primary" style="padding: 8px 15px;"><i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>

            <h2 class="section-title" style="margin-top: 25px;">Training</h2>
            
            <button id="btn-comp-match" class="btn btn-secondary btn-large">
                <i class="fa-solid fa-robot"></i>
                <div class="btn-text">
                    <span class="title">Play vs Computer</span>
                    <span class="subtitle">Practice against AI bots</span>
                </div>
            </button>
            <input type="hidden" id="username" value="<?php echo htmlspecialchars($username); ?>">
        </div>
    </div>

    <!-- Active Game Panel (Hidden initially) -->
    <div class="sidebar-content hidden" id="panel-playing">
        <div class="game-controls">
            <div class="status-indicator">
                <i class="fa-solid fa-circle-play"></i>
                <span id="game-status-text">Game in Progress</span>
            </div>
            
            <!-- AI Difficulty Selector (shown only vs computer) -->
            <div id="ai-difficulty-section" class="hidden" style="margin-bottom: 15px; background: #312e2b; padding: 12px; border-radius: 8px;">
                <label style="color: #888; font-size: 12px; font-weight: 600; display: block; margin-bottom: 8px;">AI Difficulty</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button class="difficulty-btn" data-difficulty="easy" style="padding: 8px; background: #1a5c1a; border: 2px solid #4CAF50; border-radius: 4px; color: #4CAF50; cursor: pointer; font-size: 12px; font-weight: 600;">Easy</button>
                    <button class="difficulty-btn active" data-difficulty="medium" style="padding: 8px; background: #4CAF50; border: 2px solid #4CAF50; border-radius: 4px; color: #000; cursor: pointer; font-size: 12px; font-weight: 600;">Medium</button>
                    <button class="difficulty-btn" data-difficulty="hard" style="padding: 8px; background: #ff9800; border: 2px solid #ff9800; border-radius: 4px; color: #000; cursor: pointer; font-size: 12px; font-weight: 600;">Hard</button>
                    <button class="difficulty-btn" data-difficulty="expert" style="padding: 8px; background: #f44336; border: 2px solid #f44336; border-radius: 4px; color: #fff; cursor: pointer; font-size: 12px; font-weight: 600;">Expert</button>
                </div>
            </div>
            
            <!-- AI Coach Toggle -->
            <div id="ai-coach-toggle" class="hidden" style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" id="coach-enabled" checked style="cursor: pointer;">
                <label for="coach-enabled" style="color: #c3c3c0; font-size: 13px; cursor: pointer; flex: 1;">
                    <i class="fa-solid fa-graduation-cap"></i> AI Coach
                </label>
            </div>
            
            <!-- Move Hint Button -->
            <button id="btn-hint" class="btn btn-secondary" style="width: 100%; margin-bottom: 12px; display: none;">
                <i class="fa-solid fa-lightbulb"></i> Get Hint
            </button>
            
            <!-- AI Coach Feedback Panel -->
            <div id="coach-feedback-panel" class="hidden" style="background: linear-gradient(135deg, #1a3a3a 0%, #0d2626 100%); padding: 12px; border-radius: 8px; border-left: 4px solid #4CAF50; margin-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <i class="fa-solid fa-brain" style="color: #4CAF50; font-size: 16px;"></i>
                    <span style="color: #81b64c; font-size: 12px; font-weight: 600;">Coach's Tip</span>
                </div>
                <p id="coach-message" style="color: #c3c3c0; font-size: 12px; line-height: 1.4; margin: 0;">Position analysis will appear here...</p>
                <div id="move-quality-badge" style="margin-top: 8px; display: none;">
                    <span id="move-quality-text" style="display: inline-block; padding: 4px 8px; border-radius: 4px; background: #312e2b; color: #81b64c; font-size: 11px; font-weight: 600;"></span>
                </div>
            </div>
            
            <!-- Engine Analysis -->
            <div id="engine-analysis-panel" style="background: #262421; padding: 12px; border-radius: 8px; margin-bottom: 12px; font-size: 11px; color: #888;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div>
                        <span style="color: #666; display: block; font-size: 10px; margin-bottom: 2px;">Depth</span>
                        <span id="engine-depth" style="color: #c3c3c0; font-weight: 600;">-</span>
                    </div>
                    <div>
                        <span style="color: #666; display: block; font-size: 10px; margin-bottom: 2px;">Nodes</span>
                        <span id="engine-nodes" style="color: #c3c3c0; font-weight: 600;">-</span>
                    </div>
                </div>
            </div>
            
            <div class="move-history" id="move-history">
                <!-- Move history logs go here -->
            </div>
            
            <div class="btn-group-row">
                <button id="btn-resign" class="btn btn-outline" title="Resign"><i class="fa-solid fa-flag"></i> Resign</button>
                <button id="btn-draw" class="btn btn-outline" title="Offer Draw"><i class="fa-solid fa-handshake"></i> Draw</button>
            </div>
        </div>
    </div>

    <!-- Players Panel (Hidden initially) -->
    <div class="sidebar-content hidden" id="panel-players">
        <h2 class="section-title">Online Players</h2>
        <div id="players-list" style="display: flex; flex-direction: column; gap: 10px;">
            <!-- Players will be injected here via JS -->
        </div>
    </div>

    <!-- Games Panel (Placeholder) -->
    <div class="sidebar-content hidden" id="panel-games">
        <h2 class="section-title">My Games</h2>
        <div id="games-list" style="display: flex; flex-direction: column; gap: 10px;">
            <p style="color: #888; font-size: 13px;">No saved games yet.</p>
        </div>
    </div>

    <!-- Live Games Panel -->
    <div class="sidebar-content hidden" id="panel-live">
        <h2 class="section-title">🔴 Live Games</h2>
        <p style="color: #aaa; font-size: 12px; margin-bottom: 15px;">Watch ongoing games in real-time</p>
        <div id="live-games-list" style="display: flex; flex-direction: column; gap: 10px;">
            <p style="color: #888; font-size: 13px;">Loading live games...</p>
        </div>
    </div>

</div>

<!-- Overlays -->
<div id="game-over-modal" class="modal hidden">
    <div class="modal-content">
        <h2 id="game-over-title">Game Over</h2>
        <p id="game-over-desc">Checkmate</p>
        <button class="btn btn-primary" onclick="window.location.reload()" style="margin-top:20px; width: 100%;">New Game</button>
    </div>
</div>

<!-- Challenge Notification Toast -->
<div id="challenge-toast" class="hidden" style="position: fixed; bottom: 20px; right: 20px; background: #262421; border-left: 4px solid #81b64c; box-shadow: 0 4px 15px rgba(0,0,0,0.5); padding: 15px; border-radius: 5px; z-index: 1000; width: 300px; display: flex; flex-direction: column; gap: 10px; transition: opacity 0.3s; opacity: 1;">
    <div style="color: #fff; font-weight: bold; font-size: 14px;">
        <i class="fa-solid fa-chess-knight" style="color: #81b64c; margin-right: 5px;"></i> Game Challenge!
    </div>
    <div id="challenge-toast-msg" style="color: #c3c3c0; font-size: 13px;">
        UserX has challenged you to a game.
    </div>
    <div style="display: flex; gap: 10px; margin-top: 5px;">
        <button id="btn-challenge-accept" class="btn" title="Accept" style="flex: 1; padding: 8px; background: #81b64c; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; transition: opacity 0.2s;"><i class="fa-solid fa-check"></i></button>
        <button id="btn-challenge-decline" class="btn" title="Decline" style="flex: 1; padding: 8px; background: #ef4444; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; transition: opacity 0.2s;"><i class="fa-solid fa-xmark"></i></button>
    </div>
</div>

<script src="public/chess.min.js"></script>
<script src="public/chess-ai.js"></script>
<script src="public/script.js"></script>
<script src="../assets/js/notifications.js"></script>

<!-- Cookie Consent Banner -->
<script src="../assets/js/cookies.js"></script>

</body>
</html>
