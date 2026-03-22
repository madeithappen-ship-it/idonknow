/**
 * Professional Chess Features Frontend
 * ELO ratings, game modes, analysis, leaderboards, achievements
 */

class ProfessionalChessUI {
    constructor() {
        this.currentGameMode = 'blitz';
        this.isRated = true;
        this.userPreferences = {};
        this.currentTheme = 'classic';
        this.sessionToken = null;
        this.matchId = null;
        this.init();
    }

    async init() {
        await this.loadPreferences();
        this.setupUI();
        this.setupEventListeners();
        console.log('✅ Professional Chess UI Initialized');
    }

    async loadPreferences() {
        try {
            const response = await fetch('/boringlife/chess/api_professional.php?action=get_preferences');
            this.userPreferences = await response.json();
            this.currentTheme = this.userPreferences.theme_id || 'classic';
            this.applyTheme(this.currentTheme);
        } catch (e) {
            console.log('Using default preferences:', e);
        }
    }

    setupUI() {
        this.createGameModeSelector();
        this.createThemeSwitcher();
        this.createLeaderboardPanel();
        this.createAnalysisPanel();
        this.createAchievementsPanel();
        this.createPreferencesPanel();
    }

    setupEventListeners() {
        document.addEventListener('gameFinished', (e) => this.onGameFinished(e));
        document.addEventListener('moveAnalyzed', (e) => this.recordMoveAnalysis(e));
    }

    // ============= GAME MODE SELECTOR =============
    createGameModeSelector() {
        const html = `
            <div id="game-mode-selector" class="modal-overlay" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <h3>🎮 Select Game Mode</h3>
                    
                    <div class="mode-grid">
                        <div class="mode-card" data-mode="bullet">
                            <div class="mode-icon">⚡</div>
                            <div class="mode-name">Bullet</div>
                            <div class="mode-time">1 min</div>
                            <div class="mode-desc">Ultra fast • Rated</div>
                        </div>
                        
                        <div class="mode-card" data-mode="blitz">
                            <div class="mode-icon">🔥</div>
                            <div class="mode-name">Blitz</div>
                            <div class="mode-time">5 min</div>
                            <div class="mode-desc">Fast • Rated</div>
                        </div>
                        
                        <div class="mode-card" data-mode="rapid">
                            <div class="mode-icon">⚙️</div>
                            <div class="mode-name">Rapid</div>
                            <div class="mode-time">15 min</div>
                            <div class="mode-desc">Standard • Rated</div>
                        </div>
                        
                        <div class="mode-card" data-mode="casual">
                            <div class="mode-icon">🎮</div>
                            <div class="mode-name">Casual</div>
                            <div class="mode-time">5 min</div>
                            <div class="mode-desc">Unrated • Practice</div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="rated-games" checked>
                            <span>Ranked Game (affects ELO rating)</span>
                        </label>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button onclick="professionalChess.closeGameModeSelector()" class="btn" style="flex: 1; background: #555;">Cancel</button>
                        <button onclick="professionalChess.startGameWithMode()" class="btn" style="flex: 1; background: #4CAF50;">Play</button>
                    </div>
                </div>
            </div>

            <style>
                .mode-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                    gap: 15px;
                    margin: 20px 0;
                }

                .mode-card {
                    background: rgba(255,255,255,0.05);
                    border: 2px solid rgba(255,255,255,0.1);
                    border-radius: 12px;
                    padding: 15px;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.3s;
                }

                .mode-card:hover, .mode-card.selected {
                    background: rgba(76,175,80,0.2);
                    border-color: #4CAF50;
                }

                .mode-icon {
                    font-size: 32px;
                    margin-bottom: 10px;
                }

                .mode-name {
                    font-weight: bold;
                    font-size: 16px;
                    margin-bottom: 5px;
                }

                .mode-time {
                    font-size: 14px;
                    color: #4CAF50;
                    margin-bottom: 5px;
                }

                .mode-desc {
                    font-size: 12px;
                    color: #aaa;
                }
            </style>
        `;

        if (!document.getElementById('game-mode-selector')) {
            document.body.insertAdjacentHTML('beforeend', html);
            this.attachModeSelectorEvents();
        }
    }

    attachModeSelectorEvents() {
        document.querySelectorAll('.mode-card').forEach(card => {
            card.addEventListener('click', () => {
                document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                this.currentGameMode = card.dataset.mode;
            });
        });
    }

    showGameModeSelector() {
        document.getElementById('game-mode-selector').style.display = 'flex';
        document.querySelector('.mode-card[data-mode="blitz"]').click();
    }

    closeGameModeSelector() {
        document.getElementById('game-mode-selector').style.display = 'none';
    }

    async startGameWithMode() {
        const isRated = document.getElementById('rated-games').checked;

        try {
            const response = await fetch('/boringlife/chess/api_professional.php?action=create_game_with_mode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `mode=${this.currentGameMode}&is_rated=${isRated}`
            });

            const data = await response.json();
            if (data.success) {
                this.sessionToken = data.session_token;
                window.location.href = `/boringlife/chess/index.php?room=${data.room_id}`;
            }
        } catch (e) {
            alert('Error creating game: ' + e.message);
        }
    }

    // ============= THEME SWITCHER =============
    createThemeSwitcher() {
        const html = `
            <div id="theme-switcher" style="
                position: fixed;
                top: 70px;
                right: 20px;
                z-index: 1000;
                background: rgba(0,0,0,0.8);
                border: 1px solid #4CAF50;
                border-radius: 8px;
                padding: 10px;
                display: none;
            ">
                <div style="font-size: 12px; color: #4CAF50; margin-bottom: 10px;">🎨 Board Themes</div>
                <div id="theme-list" style="display: flex; flex-direction: column; gap: 8px;"></div>
            </div>

            <style>
                .theme-option {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 8px;
                    cursor: pointer;
                    border-radius: 4px;
                    transition: all 0.2s;
                }

                .theme-option:hover {
                    background: rgba(76,175,80,0.2);
                }

                .theme-preview {
                    width: 30px;
                    height: 30px;
                    border-radius: 4px;
                    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1);
                }
            </style>
        `;

        if (!document.getElementById('theme-switcher')) {
            document.body.insertAdjacentHTML('beforeend', html);
            this.loadThemes();
        }
    }

    async loadThemes() {
        const themes = [
            { code: 'classic', name: 'Classic', light: '#F0D9B5', dark: '#B58863' },
            { code: 'dark', name: 'Dark', light: '#1A1A1A', dark: '#333333' },
            { code: 'ocean', name: 'Ocean', light: '#87CEEB', dark: '#4682B4' },
            { code: 'forest', name: 'Forest', light: '#90EE90', dark: '#228B22' },
            { code: 'neon', name: 'Neon', light: '#0F0F1E', dark: '#1A1A2E' },
        ];

        const themeList = document.getElementById('theme-list');
        themes.forEach(theme => {
            const html = `
                <div class="theme-option" onclick="professionalChess.switchTheme('${theme.code}')">
                    <div class="theme-preview" style="
                        background: linear-gradient(45deg, ${theme.light} 50%, ${theme.dark} 50%);
                    "></div>
                    <span>${theme.name}</span>
                </div>
            `;
            themeList.insertAdjacentHTML('beforeend', html);
        });
    }

    switchTheme(themeCode) {
        this.currentTheme = themeCode;
        this.applyTheme(themeCode);
        this.savePreferences();
    }

    applyTheme(themeCode) {
        const themes = {
            'classic': { light: '#F0D9B5', dark: '#B58863', highlight: '#BCE654' },
            'dark': { light: '#1A1A1A', dark: '#333333', highlight: '#4CAF50' },
            'ocean': { light: '#87CEEB', dark: '#4682B4', highlight: '#FFD700' },
            'forest': { light: '#90EE90', dark: '#228B22', highlight: '#FFD700' },
            'neon': { light: '#0F0F1E', dark: '#1A1A2E', highlight: '#00FF00' },
        };

        const theme = themes[themeCode] || themes['classic'];
        const board = document.querySelector('.chess-board');
        if (board) {
            board.style.setProperty('--light-square', theme.light);
            board.style.setProperty('--dark-square', theme.dark);
            board.style.setProperty('--highlight-color', theme.highlight);
        }
    }

    // ============= LEADERBOARD PANEL =============
    createLeaderboardPanel() {
        const html = `
            <div id="leaderboard-panel" class="modal-overlay" style="display: none;">
                <div class="modal-content" style="max-width: 600px; max-height: 80vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>🏆 Leaderboard</h3>
                        <button onclick="professionalChess.closeLeaderboard()" style="background: none; border: none; color: #fff; font-size: 24px; cursor: pointer;">×</button>
                    </div>

                    <div class="leaderboard-modes" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <button class="leaderboard-mode-btn" data-mode="blitz" onclick="professionalChess.loadLeaderboard('blitz')">🔥 Blitz</button>
                        <button class="leaderboard-mode-btn" data-mode="bullet" onclick="professionalChess.loadLeaderboard('bullet')">⚡ Bullet</button>
                        <button class="leaderboard-mode-btn" data-mode="rapid" onclick="professionalChess.loadLeaderboard('rapid')">⚙️ Rapid</button>
                    </div>

                    <table id="leaderboard-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #4CAF50;">
                                <th style="padding: 10px; text-align: left;">Rank</th>
                                <th style="padding: 10px; text-align: left;">Player</th>
                                <th style="padding: 10px; text-align: center;">Rating</th>
                                <th style="padding: 10px; text-align: center;">Games</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboard-body"></tbody>
                    </table>
                </div>
            </div>

            <style>
                .leaderboard-mode-btn {
                    flex: 1;
                    padding: 10px;
                    background: rgba(255,255,255,0.05);
                    border: 1px solid rgba(255,255,255,0.1);
                    color: #fff;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.3s;
                }

                .leaderboard-mode-btn.active {
                    background: rgba(76,175,80,0.3);
                    border-color: #4CAF50;
                }

                #leaderboard-table tbody tr:hover {
                    background: rgba(76,175,80,0.1);
                }
            </style>
        `;

        if (!document.getElementById('leaderboard-panel')) {
            document.body.insertAdjacentHTML('beforeend', html);
            this.loadLeaderboard('blitz');
        }
    }

    showLeaderboard() {
        document.getElementById('leaderboard-panel').style.display = 'flex';
    }

    closeLeaderboard() {
        document.getElementById('leaderboard-panel').style.display = 'none';
    }

    async loadLeaderboard(mode) {
        document.querySelectorAll('.leaderboard-mode-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`[data-mode="${mode}"]`).classList.add('active');

        try {
            const response = await fetch(`/boringlife/chess/api_professional.php?action=get_leaderboard&mode=${mode}&limit=50`);
            const data = await response.json();

            const tbody = document.getElementById('leaderboard-body');
            tbody.innerHTML = '';

            data.forEach((player, idx) => {
                const row = `
                    <tr>
                        <td style="padding: 10px;">#${player.rank}</td>
                        <td style="padding: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="${player.avatar_url || '/boringlife/assets/images/default-avatar.svg'}" 
                                     style="width: 28px; height: 28px; border-radius: 50%;" alt="">
                                <span>${player.username}</span>
                            </div>
                        </td>
                        <td style="padding: 10px; text-align: center; color: #4CAF50; font-weight: bold;">${player.rating}</td>
                        <td style="padding: 10px; text-align: center;">${player.games}</td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });
        } catch (e) {
            console.error('Error loading leaderboard:', e);
        }
    }

    // ============= ANALYSIS PANEL =============
    createAnalysisPanel() {
        const html = `
            <div id="analysis-panel" class="modal-overlay" style="display: none;">
                <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
                    <h3>📊 Game Analysis</h3>
                    
                    <div class="analysis-stats" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0;">
                        <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px;">
                            <div style="color: #4CAF50; font-size: 12px;">WHITE ACCURACY</div>
                            <div style="font-size: 24px; font-weight: bold;" id="white-accuracy">--</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px;">
                            <div style="color: #4CAF50; font-size: 12px;">BLACK ACCURACY</div>
                            <div style="font-size: 24px; font-weight: bold;" id="black-accuracy">--</div>
                        </div>
                    </div>

                    <div id="moves-analysis" style="margin-top: 20px;"></div>

                    <button onclick="professionalChess.closeAnalysis()" class="btn" style="width: 100%; margin-top: 20px;">Close</button>
                </div>
            </div>
        `;

        if (!document.getElementById('analysis-panel')) {
            document.body.insertAdjacentHTML('beforeend', html);
        }
    }

    showAnalysis(matchId) {
        this.matchId = matchId;
        document.getElementById('analysis-panel').style.display = 'flex';
        this.loadAnalysis(matchId);
    }

    closeAnalysis() {
        document.getElementById('analysis-panel').style.display = 'none';
    }

    async loadAnalysis(matchId) {
        try {
            const response = await fetch(`/boringlife/chess/api_professional.php?action=get_match_analysis&match_id=${matchId}`);
            const data = await response.json();

            document.getElementById('white-accuracy').textContent = Math.round(data.accuracy.white) + '%';
            document.getElementById('black-accuracy').textContent = Math.round(data.accuracy.black) + '%';

            const movesHtml = data.moves.map((move, idx) => {
                const quality = move.is_blunder ? '🔴 Blunder' : (move.is_mistake ? '🟡 Mistake' : '✅ Good');
                return `
                    <div style="padding: 10px; margin: 5px 0; background: rgba(255,255,255,0.03); border-radius: 4px; font-size: 12px;">
                        <strong>Move ${move.move_number}:</strong> ${move.move_notation} ${quality} (${move.centipawn_loss} cp loss)
                    </div>
                `;
            }).join('');

            document.getElementById('moves-analysis').innerHTML = movesHtml;
        } catch (e) {
            console.error('Error loading analysis:', e);
        }
    }

    // ============= ACHIEVEMENTS PANEL =============
    createAchievementsPanel() {
        const html = `
            <div id="achievements-panel" class="modal-overlay" style="display: none;">
                <div class="modal-content">
                    <h3>🏅 Achievements</h3>
                    <div id="achievements-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin: 20px 0;"></div>
                    <button onclick="professionalChess.closeAchievements()" class="btn" style="width: 100%; margin-top: 20px;">Close</button>
                </div>
            </div>
        `;

        if (!document.getElementById('achievements-panel')) {
            document.body.insertAdjacentHTML('beforeend', html);
        }
    }

    showAchievements(userId) {
        document.getElementById('achievements-panel').style.display = 'flex';
        this.loadAchievements(userId);
    }

    closeAchievements() {
        document.getElementById('achievements-panel').style.display = 'none';
    }

    async loadAchievements(userId) {
        try {
            const response = await fetch(`/boringlife/chess/api_professional.php?action=get_achievements&user_id=${userId}`);
            const achievements = await response.json();

            const grid = document.getElementById('achievements-grid');
            grid.innerHTML = '';

            achievements.forEach(ach => {
                const html = `
                    <div style="
                        background: linear-gradient(135deg, rgba(76,175,80,0.2), rgba(76,175,80,0.1));
                        border: 1px solid #4CAF50;
                        border-radius: 8px;
                        padding: 15px;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.3s;
                    " title="${ach.description}">
                        <div style="font-size: 32px; margin-bottom: 5px;">${ach.icon_emoji}</div>
                        <div style="font-weight: bold; font-size: 12px;">${ach.title}</div>
                        <div style="font-size: 10px; color: #aaa;">+${ach.reward_xp} XP</div>
                    </div>
                `;
                grid.insertAdjacentHTML('beforeend', html);
            });
        } catch (e) {
            console.error('Error loading achievements:', e);
        }
    }

    // ============= PREFERENCES PANEL =============
    createPreferencesPanel() {
        const html = `
            <div id="preferences-panel" class="modal-overlay" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <h3>⚙️ Chess Preferences</h3>
                    
                    <div style="margin: 20px 0;">
                        <label style="display: flex; align-items: center; gap: 10px; margin: 15px 0; cursor: pointer;">
                            <input type="checkbox" id="pref-sound" ${this.userPreferences.sound_enabled ? 'checked' : ''}>
                            <span>🔊 Sound Effects</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 10px; margin: 15px 0; cursor: pointer;">
                            <input type="checkbox" id="pref-animations" ${this.userPreferences.animations_enabled ? 'checked' : ''}>
                            <span>✨ Animations</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 10px; margin: 15px 0; cursor: pointer;">
                            <input type="checkbox" id="pref-legal-moves" ${this.userPreferences.show_legal_moves ? 'checked' : ''}>
                            <span>🟢 Show Legal Moves</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 10px; margin: 15px 0; cursor: pointer;">
                            <input type="checkbox" id="pref-last-move" ${this.userPreferences.show_last_move ? 'checked' : ''}>
                            <span>🟡 Highlight Last Move</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 10px; margin: 15px 0; cursor: pointer;">
                            <input type="checkbox" id="pref-hints" ${this.userPreferences.enable_hints ? 'checked' : ''}>
                            <span>💡 Enable Hints</span>
                        </label>

                        <label style="margin: 15px 0;">
                            <span style="display: block; margin-bottom: 5px;">Hints per game:</span>
                            <input type="number" id="pref-hints-count" value="${this.userPreferences.hints_per_game || 3}" min="1" max="10" style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;">
                        </label>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button onclick="professionalChess.closePreferences()" class="btn" style="flex: 1; background: #555;">Cancel</button>
                        <button onclick="professionalChess.savePreferencesPanel()" class="btn" style="flex: 1; background: #4CAF50;">Save</button>
                    </div>
                </div>
            </div>
        `;

        if (!document.getElementById('preferences-panel')) {
            document.body.insertAdjacentHTML('beforeend', html);
        }
    }

    showPreferences() {
        document.getElementById('preferences-panel').style.display = 'flex';
    }

    closePreferences() {
        document.getElementById('preferences-panel').style.display = 'none';
    }

    async savePreferencesPanel() {
        const prefs = {
            sound_enabled: document.getElementById('pref-sound').checked,
            animations_enabled: document.getElementById('pref-animations').checked,
            show_legal_moves: document.getElementById('pref-legal-moves').checked,
            show_last_move: document.getElementById('pref-last-move').checked,
            enable_hints: document.getElementById('pref-hints').checked,
            hints_per_game: parseInt(document.getElementById('pref-hints-count').value)
        };

        await this.savePreferences(prefs);
        this.closePreferences();
        alert('Preferences saved!');
    }

    async savePreferences(prefs = null) {
        const data = prefs || this.userPreferences;
        try {
            await fetch('/boringlife/chess/api_professional.php?action=save_preferences', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data).toString()
            });
        } catch (e) {
            console.error('Error saving preferences:', e);
        }
    }

    // ============= GAME FINISH HANDLER =============
    async onGameFinished(event) {
        const { white_id, black_id, result, pgn_moves } = event.detail;
        const roomId = new URLSearchParams(window.location.search).get('room');

        try {
            const response = await fetch('/boringlife/chess/api_professional.php?action=record_match', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    room_id: roomId,
                    white_id,
                    black_id,
                    result,
                    pgn_moves,
                    mode: this.currentGameMode,
                    game_duration: Math.floor(Date.now() / 1000) - this.gameStartTime
                }).toString()
            });

            const data = await response.json();
            if (data.success) {
                console.log('✅ Match recorded:', data);
                this.matchId = data.match_id;
                this.showAnalysis(data.match_id);
            }
        } catch (e) {
            console.error('Error recording match:', e);
        }
    }

    async recordMoveAnalysis(event) {
        const { move_uci, centipawn_loss, player_id } = event.detail;
        if (this.matchId) {
            await fetch('/boringlife/chess/api_professional.php?action=record_move_analysis', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    match_id: this.matchId,
                    move_number: Date.now() % 1000,
                    player_id,
                    move_uci,
                    centipawn_loss
                }).toString()
            });
        }
    }
}

// Initialize on page load
let professionalChess;
document.addEventListener('DOMContentLoaded', () => {
    professionalChess = new ProfessionalChessUI();
});
