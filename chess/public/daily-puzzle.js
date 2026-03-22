/**
 * Daily Chess Puzzle Mode
 * Tactical training with difficulty levels and solutions
 */

class DailyPuzzleMode {
    constructor() {
        this.currentPuzzle = null;
        this.puzzleHistory = [];
        this.solving = false;
        this.init();
    }

    async init() {
        console.log('🧩 Daily Puzzle Mode Initialized');
    }

    /**
     * Load or create today's puzzle
     */
    async loadDailyPuzzle() {
        try {
            const response = await fetch('/boringlife/chess/api.php?action=get_daily_puzzle');
            this.currentPuzzle = await response.json();
            return this.currentPuzzle;
        } catch (e) {
            console.error('Error loading daily puzzle:', e);
            return null;
        }
    }

    /**
     * Create puzzle UI modal
     */
    createPuzzleUI() {
        const html = `
            <div id="puzzle-modal" class="modal-overlay" style="display: none;">
                <div class="modal-content" style="max-width: 800px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3>🧩 Daily Puzzle</h3>
                        <button onclick="dailyPuzzle.closePuzzle()" style="background: none; border: none; color: #fff; font-size: 24px; cursor: pointer;">×</button>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <!-- Chess Board -->
                        <div style="background: rgba(255,255,255,0.05); border-radius: 8px; padding: 15px;">
                            <div id="puzzle-board" style="
                                width: 100%;
                                aspect-ratio: 1;
                                background: repeating-conic-gradient(#F0D9B5 0% 25%, #B58863 0% 50%);
                                border-radius: 8px;
                            "></div>
                        </div>

                        <!-- Info Panel -->
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="background: rgba(76,175,80,0.1); border: 1px solid #4CAF50; border-radius: 8px; padding: 15px;">
                                <div style="color: #4CAF50; font-size: 12px; margin-bottom: 5px;">DIFFICULTY</div>
                                <div style="font-size: 20px; font-weight: bold;" id="puzzle-difficulty">--</div>
                            </div>

                            <div style="background: rgba(255,255,255,0.05); border-radius: 8px; padding: 15px;">
                                <div style="color: #aaa; font-size: 12px; margin-bottom: 10px;">THEME</div>
                                <div id="puzzle-theme" style="font-size: 16px; font-weight: bold;"></div>
                            </div>

                            <div style="background: rgba(255,255,255,0.05); border-radius: 8px; padding: 15px;">
                                <div style="color: #aaa; font-size: 12px; margin-bottom: 10px;">STATS</div>
                                <div style="display: flex; justify-content: space-between;">
                                    <div>
                                        <div style="color: #4CAF50; font-size: 12px;">Attempts</div>
                                        <div style="font-size: 18px; font-weight: bold;" id="puzzle-attempts">0</div>
                                    </div>
                                    <div>
                                        <div style="color: #4CAF50; font-size: 12px;">Solved</div>
                                        <div style="font-size: 18px; font-weight: bold;" id="puzzle-solved">0</div>
                                    </div>
                                </div>
                            </div>

                            <div id="puzzle-hint" style="background: rgba(0,150,200,0.1); border: 1px solid rgba(0,150,200,0.3); border-radius: 8px; padding: 15px; display: none;">
                                <div style="color: #00A8FF; font-size: 12px; margin-bottom: 5px;">💡 HINT</div>
                                <div id="hint-text" style="font-size: 14px;"></div>
                            </div>

                            <button onclick="dailyPuzzle.showHint()" class="btn" style="width: 100%; background: #FF9500;">Show Hint</button>
                            <button onclick="dailyPuzzle.solvePuzzle()" class="btn" style="width: 100%; background: #4CAF50;">Submit Solution</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (!document.getElementById('puzzle-modal')) {
            document.body.insertAdjacentHTML('beforeend', html);
        }
    }

    showPuzzle() {
        this.createPuzzleUI();
        document.getElementById('puzzle-modal').style.display = 'flex';
        this.displayPuzzle();
    }

    closePuzzle() {
        document.getElementById('puzzle-modal').style.display = 'none';
    }

    displayPuzzle() {
        if (!this.currentPuzzle) return;

        const puzzle = this.currentPuzzle;
        document.getElementById('puzzle-difficulty').textContent = 
            ['🟢 Easy', '🟡 Medium', '🔴 Hard'][puzzle.difficulty - 1] || '--';
        document.getElementById('puzzle-theme').textContent = puzzle.theme || 'Tactical';
        document.getElementById('puzzle-attempts').textContent = puzzle.attempt_count || 0;
        document.getElementById('puzzle-solved').textContent = puzzle.successful_count || 0;

        // Render board from FEN
        this.renderPuzzleBoard(puzzle.fen_position);
    }

    renderPuzzleBoard(fen) {
        // TODO: Implement FEN rendering with chess.js
        const board = document.getElementById('puzzle-board');
        // board.innerHTML = /* pieces from FEN */;
    }

    showHint() {
        const hintEl = document.getElementById('puzzle-hint');
        hintEl.style.display = 'block';
        if (this.currentPuzzle && this.currentPuzzle.solution_moves) {
            const firstMove = JSON.parse(this.currentPuzzle.solution_moves)[0];
            document.getElementById('hint-text').textContent = `Play: ${firstMove}`;
        }
    }

    async solvePuzzle() {
        try {
            const response = await fetch('/boringlife/chess/api.php?action=submit_puzzle_attempt', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    puzzle_id: this.currentPuzzle.id,
                    solved: true,
                    time_taken: 300
                }).toString()
            });

            const data = await response.json();
            if (data.success) {
                alert('✅ Puzzle solved! +50 XP');
                this.closePuzzle();
            }
        } catch (e) {
            console.error('Error submitting puzzle:', e);
        }
    }
}

// Initialize
let dailyPuzzle;
document.addEventListener('DOMContentLoaded', async () => {
    dailyPuzzle = new DailyPuzzleMode();
    await dailyPuzzle.loadDailyPuzzle();
});
