<!-- Professional Chess Game Interface with ELO, Modes, Analysis -->
<?php
session_start();
require_once(__DIR__ . '/../config.php');

if (!is_logged_in()) {
    header("Location: ../login.php");
    exit;
}

$user = get_user();
$username = $user['username'];
$user_id = $user['id'];

// Get user's ratings
$ratings_query = "SELECT * FROM chess_ratings WHERE user_id = {$user_id}";
$ratings_result = $GLOBALS['pdo']->query($ratings_query);
$ratings = $ratings_result ? $ratings_result->fetch() : ['bullet_rating' => 1200, 'blitz_rating' => 1200, 'rapid_rating' => 1200];
?>
<!DOCTYPE html>
<html lang="en" data-color-mode="dark" class="dark-mode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="true">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Professional Chess - Side Quest</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon-16.png">
    <link rel="apple-touch-icon" href="../assets/images/icon-192.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/style.css">
    <style>
        .rating-badge {
            display: inline-block;
            padding: 6px 12px;
            background: rgba(76,175,80,0.2);
            border: 1px solid #4CAF50;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            color: #4CAF50;
            margin: 5px 2px;
        }

        .mode-button {
            flex: 1;
            padding: 15px;
            margin: 5px 0;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(76,175,80,0.3);
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .mode-button:hover {
            background: rgba(76,175,80,0.1);
            border-color: #4CAF50;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }

        .stat-card {
            background: rgba(255,255,255,0.05);
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid rgba(76,175,80,0.2);
        }

        .stat-label {
            color: #aaa;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
        }

        .top-toolbar {
            display: flex;
            gap: 10px;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
        }

        .top-toolbar button {
            padding: 8px 15px;
            background: rgba(76,175,80,0.2);
            border: 1px solid #4CAF50;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }

        .top-toolbar button:hover {
            background: rgba(76,175,80,0.4);
        }
    </style>
</head>
<body class="theme-background">

<!-- Header with Quick Stats -->
<div style="background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%); padding: 15px; border-bottom: 2px solid #4CAF50;">
    <div style="max-width: 1400px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div>
                <h1 style="margin: 0; color: #4CAF50;">♔ Chess.io</h1>
                <p style="margin: 5px 0; color: #aaa; font-size: 13px;">Professional Online Chess</p>
            </div>
            <div class="top-toolbar">
                <button onclick="professionalChess.showLeaderboard()">🏆 Leaderboard</button>
                <button onclick="dailyPuzzle.showPuzzle()">🧩 Daily Puzzle</button>
                <button onclick="professionalChess.showPreferences()">⚙️ Settings</button>
                <button onclick="professionalChess.showAchievements(<?php echo $user_id; ?>)">🏅 Achievements</button>
            </div>
        </div>

        <!-- Rating Badges -->
        <div>
            <span class="rating-badge">⚡ Bullet: <?php echo $ratings['bullet_rating'] ?? 1200; ?></span>
            <span class="rating-badge">🔥 Blitz: <?php echo $ratings['blitz_rating'] ?? 1200; ?></span>
            <span class="rating-badge">⚙️ Rapid: <?php echo $ratings['rapid_rating'] ?? 1200; ?></span>
        </div>
    </div>
</div>

<!-- Main Game Area -->
<div style="max-width: 1400px; margin: 20px auto; display: grid; grid-template-columns: 1fr 350px; gap: 20px; padding: 20px;">
    
    <!-- Left: Chess Board -->
    <div>
        <!-- Game Info -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(76,175,80,0.2);">
                <div style="font-size: 12px; color: #aaa; margin-bottom: 8px;">WHITE</div>
                <div style="font-size: 16px; font-weight: bold;" id="white-player">Waiting...</div>
                <div style="font-size: 14px; color: #4CAF50;" id="white-elo">1200</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(76,175,80,0.2);">
                <div style="font-size: 12px; color: #aaa; margin-bottom: 8px;">BLACK</div>
                <div style="font-size: 16px; font-weight: bold;" id="black-player">Waiting...</div>
                <div style="font-size: 14px; color: #4CAF50;" id="black-elo">1200</div>
            </div>
        </div>

        <!-- Chess Board -->
        <div id="chessboard" class="chessboard" style="
            width: 100%;
            max-width: 600px;
            aspect-ratio: 1;
            background: repeating-conic-gradient(#F0D9B5 0% 25%, #B58863 0% 50%);
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 20px;
        "></div>

        <!-- Move History -->
        <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(76,175,80,0.2);">
            <h4 style="margin: 0 0 10px 0; color: #4CAF50;">📋 Moves</h4>
            <div id="move-history" style="
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
                gap: 5px;
                max-height: 200px;
                overflow-y: auto;
            "></div>
        </div>
    </div>

    <!-- Right: Sidebar -->
    <div style="display: flex; flex-direction: column; gap: 15px;">
        
        <!-- Game Mode Selector -->
        <div style="background: linear-gradient(135deg, rgba(76,175,80,0.2) 0%, rgba(76,175,80,0.05) 100%); padding: 15px; border-radius: 8px; border: 1px solid #4CAF50;">
            <h3 style="margin: 0 0 15px 0; colors: #fff;">🎮 Game Modes</h3>
            
            <button class="mode-button" onclick="professionalChess.createGameMode('bullet')">
                <span>⚡</span>
                <div style="text-align: left; font-size: 12px;">
                    <div style="font-weight: bold;">Bullet</div>
                    <div style="color: #aaa; font-size: 10px;">1 min • Rated</div>
                </div>
            </button>

            <button class="mode-button" onclick="professionalChess.createGameMode('blitz')">
                <span>🔥</span>
                <div style="text-align: left; font-size: 12px;">
                    <div style="font-weight: bold;">Blitz</div>
                    <div style="color: #aaa; font-size: 10px;">5 min • Rated</div>
                </div>
            </button>

            <button class="mode-button" onclick="professionalChess.createGameMode('rapid')">
                <span>⚙️</span>
                <div style="text-align: left; font-size: 12px;">
                    <div style="font-weight: bold;">Rapid</div>
                    <div style="color: #aaa; font-size: 10px;">15 min • Rated</div>
                </div>
            </button>

            <button class="mode-button" onclick="professionalChess.createGameMode('casual')">
                <span>🎮</span>
                <div style="text-align: left; font-size: 12px;">
                    <div style="font-weight: bold;">Casual</div>
                    <div style="color: #aaa; font-size: 10px;">5 min • Unrated</div>
                </div>
            </button>
        </div>

        <!-- Statistics -->
        <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(76,175,80,0.2);">
            <h4 style="margin: 0 0 12px 0; color: #4CAF50;">📊 Stats</h4>
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-label">Games Played</div>
                    <div class="stat-value" id="stat-games">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Win Rate</div>
                    <div class="stat-value" id="stat-winrate">0%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Wins</div>
                    <div class="stat-value" id="stat-wins">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Best Rating</div>
                    <div class="stat-value" id="stat-peak"><?php echo $ratings['peak_rating'] ?? 1200; ?></div>
                </div>
            </div>
        </div>

        <!-- Recent Achievements -->
        <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(76,175,80,0.2);">
            <h4 style="margin: 0 0 12px 0; color: #4CAF50;">🏅 Recent Achievements</h4>
            <div id="recent-achievements" style="display: flex; gap: 8px; flex-wrap: wrap;"></div>
            <button onclick="professionalChess.showAchievements()" style="width: 100%; padding: 8px; margin-top: 10px; background: rgba(76,175,80,0.2); border: 1px solid #4CAF50; color: #fff; border-radius: 4px; cursor: pointer;">View All</button>
        </div>

        <!-- Live Games -->
        <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(76,175,80,0.2);">
            <h4 style="margin: 0 0 12px 0; color: #4CAF50;">🔴 Live Games</h4>
            <div id="live-games-list" style="max-height: 200px; overflow-y: auto;">
                <p style="color: #aaa; font-size: 12px; margin: 0;">No games happening now</p>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://www.unpkg.com/chess.js@1.0.0-beta.6"></script>
<script src="https://cdn.jsdelivr.net/npm/stockfish@14.0.0/src/stockfish.js"></script>
<script src="public/script.js"></script>
<script src="public/professional-chess-ui.js"></script>
<script src="public/daily-puzzle.js"></script>
<script src="public/chess-sound.js"></script>

<script>
    // Initialize professional chess features
    document.addEventListener('DOMContentLoaded', async () => {
        // Load user stats
        try {
            const response = await fetch('/boringlife/chess/elo_system.php?action=get_stats&user_id=<?php echo $user_id; ?>');
            const stats = await response.json();
            
            if (stats.stats) {
                document.getElementById('stat-games').textContent = stats.stats.total_games;
                document.getElementById('stat-winrate').textContent = stats.stats.win_rate + '%';
                document.getElementById('stat-wins').textContent = stats.stats.wins;
            }

            // Load recent achievements
            if (stats.achievements && stats.achievements.length > 0) {
                const achievementsContainer = document.getElementById('recent-achievements');
                stats.achievements.slice(0, 5).forEach(ach => {
                    const div = document.createElement('div');
                    div.title = ach.title;
                    div.style.cssText = 'font-size: 24px; cursor: pointer;';
                    div.textContent = ach.icon_emoji;
                    achievementsContainer.appendChild(div);
                });
            }
        } catch (e) {
            console.error('Error loading stats:', e);
        }

        // Load live games
        fetchLiveGames();
        setInterval(fetchLiveGames, 10000);
    });

    async function fetchLiveGames() {
        try {
            const response = await fetch('/boringlife/chess/api.php?action=get_live_games');
            const games = await response.json();
            
            if (games && games.length > 0) {
                const liveList = document.getElementById('live-games-list');
                liveList.innerHTML = games.map(game => `
                    <div style="padding: 8px; background: rgba(76,175,80,0.1); border-radius: 4px; margin-bottom: 8px; cursor: pointer;" onclick="window.location.href='?room=${game.id}'">
                        <div style="font-size: 12px; font-weight: bold;">${game.white_player} vs ${game.black_player}</div>
                        <div style="font-size: 11px; color: #aaa;">👀 ${game.spectators || 0} watching</div>
                    </div>
                `).join('');
            }
        } catch (e) {
            console.error('Error fetching live games:', e);
        }
    }
</script>

<!-- Progressive Web App Helper -->
<script src="../assets/js/pwa-helper.js"></script>

</body>
</html>
