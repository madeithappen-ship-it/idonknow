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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    
    <div id="chessboard" class="chessboard">
        <!-- Board squares will be injected by script.js -->
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
        <h2 class="section-title">Live Games</h2>
        <p style="color: #888; font-size: 13px;">No active games to spectate.</p>
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
<script src="public/script.js"></script>

</body>
</html>
