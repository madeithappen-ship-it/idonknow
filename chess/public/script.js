/**
 * Enhanced Chess Game Script with AI
 * Integrates Stockfish WASM for AI opponent, analysis, and coaching
 */

const chess = new Chess();
let myColor = 'w';
let currentRoomId = null;
let isVsComputer = false;
let syncInterval = null;
let lastMoveId = 0;
let draggedPiece = null;
let selectedSquare = null;
let gameStatus = 'waiting';
let gameStartTime = null;
let timerInterval = null;
let gameTimeLimit = 1800; // 30 minutes in seconds

// Spectator Mode Variables
let spectatorMode = false;
let watchRoomId = null;
let watchInterval = null;
let lastWatchMoveId = 0;
let currentAnalysis = null;

// AI System Variables
let chessAI = null;
let aiEnabled = false;
let aiThinking = false;
let coachEnabled = true;
let previousEvaluation = 0;
let hintMove = null;
let hintsUsed = 0;
let maxHints = 3;

// ============================================================================
// API FUNCTIONS
// ============================================================================

function fetchAPI(action, payload = {}) {
    return fetch(`api.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(r => r.json()).catch(err => {
        console.error(err);
        return { success: false, error: 'Network Error' };
    });
}

// ============================================================================
// SPECTATOR MODE FUNCTIONS
// ============================================================================

async function loadLiveGames() {
    const res = await fetchAPI('get_live_games', {});
    if (!res.success) return;
    
    const list = document.getElementById('live-games-list');
    if (!list || !res.games || res.games.length === 0) {
        if (list) list.innerHTML = '<div style="color:#888; text-align:center; padding:20px;">No active games</div>';
        return;
    }
    
    let html = '';
    res.games.forEach(game => {
        const timeMin = Math.floor(game.remaining_seconds / 60);
        const timeSec = game.remaining_seconds % 60;
        const specsText = game.spectator_count > 0 ? `👁️ ${game.spectator_count}` : '';
        
        html += `
            <div style="padding:12px; border-bottom:1px solid #333; cursor:pointer; hover:background:#262641;" onclick="watchLiveGame('${game.room_code}')">
                <div style="font-weight:600; color:#fff; margin-bottom:4px;">
                    ${escapeHtml(game.player_w_name)} vs ${escapeHtml(game.player_b_name)}
                </div>
                <div style="font-size:12px; color:#aaa; display:flex; justify-content:space-between;">
                    <span>⏱️ ${String(timeMin).padStart(2,'0')}:${String(timeSec).padStart(2,'0')}</span>
                    <span>${specsText}</span>
                </div>
            </div>
        `;
    });
    
    list.innerHTML = html;
}

async function watchLiveGame(roomCode) {
    const res = await fetchAPI('join_spectate', { room_code: roomCode });
    if (!res.success) {
        alert('Error: ' + (res.error || 'Could not join spectate mode'));
        return;
    }
    
    // Enter spectator mode
    spectatorMode = true;
    watchRoomId = roomCode;
    lastWatchMoveId = 0;
    myColor = 'w'; // Default viewing angle
    
    // Reconstruct game from moves
    chess.reset();
    res.moves.forEach(m => {
        const move = chess.move({ from: m.move_from, to: m.move_to, promotion: m.promotion || undefined });
        lastWatchMoveId = m.id;
    });
    
    // Update UI
    document.getElementById('panel-new-game').style.display = 'none';
    document.getElementById('panel-live').style.display = 'none';
    document.getElementById('panel-games').style.display = 'none';
    document.getElementById('panel-players').style.display = 'none';
    document.getElementById('panel-spectate').style.display = 'block';
    document.getElementById('spectate-room-code').innerText = roomCode;
    document.getElementById('spectate-players').innerText = `${res.room.player_w_name} (White) vs ${res.room.player_b_name} (Black)`;
    
    // Start live watch polling
    if (watchInterval) clearInterval(watchInterval);
    watchInterval = setInterval(pollLiveGameUpdates, 1000);
    
    initBoard();
}

async function pollLiveGameUpdates() {
    if (!spectatorMode || !watchRoomId) return;
    
    const res = await fetchAPI('watch_live', {
        room_code: watchRoomId,
        last_move_id: lastWatchMoveId
    });
    
    if (!res.success) return;
    
    // Apply new moves
    if (res.moves && res.moves.length > 0) {
        res.moves.forEach(m => {
            chess.move({ from: m.move_from, to: m.move_to });
            lastWatchMoveId = m.id;
        });
        updateBoard();
    }
    
    // Update AI analysis display
    if (res.analysis) {
        currentAnalysis = res.analysis;
        updateSpectatorAnalysis();
    }
    
    // Update game status
    if (res.room.status === 'finished') {
        showGameFinished(res.room.result_reason);
    }
}

function updateSpectatorAnalysis() {
    if (!currentAnalysis) return;
    
    const panel = document.getElementById('spectate-analysis');
    if (!panel) return;
    
    const eval_text = currentAnalysis.evaluation || '?';
    const best = currentAnalysis.best_move || '-';
    const depth = currentAnalysis.depth || '?';
    
    panel.innerHTML = `
        <div style="padding:12px; background:#1a1a2e; border-radius:5px;">
            <div style="margin-bottom:8px;">
                <strong>♟️ AI Analysis</strong>
            </div>
            <div style="font-size:13px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div>
                    <div style="color:#888; font-size:11px;">Evaluation</div>
                    <div style="color:#4CAF50; font-weight:bold; font-size:16px;">${eval_text}</div>
                </div>
                <div>
                    <div style="color:#888; font-size:11px;">Best Move</div>
                    <div style="color:#64B5F6; font-weight:bold; font-size:16px;">${best}</div>
                </div>
                <div style="grid-column:1/3;">
                    <div style="color:#888; font-size:11px;">Depth: ${depth}</div>
                </div>
            </div>
        </div>
    `;
}

function showGameFinished(reason) {
    const msg = document.getElementById('spectate-analysis');
    if (msg) {
        msg.innerHTML = `
            <div style="padding:12px; background:#f44336; border-radius:5px; color:white; text-align:center; font-weight:bold;">
                ✓ Game Finished: ${reason}
            </div>
        `;
    }
    clearInterval(watchInterval);
}

function stopSpectating() {
    spectatorMode = false;
    watchRoomId = null;
    lastWatchMoveId = 0;
    currentAnalysis = null;
    if (watchInterval) clearInterval(watchInterval);
    
    document.getElementById('panel-new-game').style.display = 'block';
    document.getElementById('panel-live').style.display = 'block';
    document.getElementById('panel-spectate').style.display = 'none';
}

// ============================================================================
// BOARD RENDERING
// ============================================================================

function initBoard() {
    const boardEl = document.getElementById('chessboard');
    boardEl.innerHTML = '';
    const ranks = ['8','7','6','5','4','3','2','1'];
    const files = ['a','b','c','d','e','f','g','h'];
    
    // Invert board if we are playing as black
    const renderRanks = myColor === 'b' ? [...ranks].reverse() : ranks;
    const renderFiles = myColor === 'b' ? [...files].reverse() : files;
    
    renderRanks.forEach((r, rIdx) => {
        renderFiles.forEach((f, fIdx) => {
            const isLight = (rIdx + fIdx) % 2 === 0;
            const sq = document.createElement('div');
            sq.className = `square ${isLight ? 'light' : 'dark'}`;
            sq.dataset.square = f + r;
            sq.addEventListener('dragover', e => e.preventDefault());
            sq.addEventListener('drop', onDrop);
            sq.addEventListener('click', onSquareClick);
            boardEl.appendChild(sq);
        });
    });
    updateBoard();
    updateEvaluationBar();
}

function updateBoard() {
    document.querySelectorAll('.square').forEach(sq => sq.innerHTML = '');
    const position = chess.board();
    
    for (let r = 0; r < 8; r++) {
        for (let f = 0; f < 8; f++) {
            const piece = position[r][f];
            if (piece) {
                const sqName = ['a','b','c','d','e','f','g','h'][f] + (8 - r);
                const sqEl = document.querySelector(`[data-square="${sqName}"]`);
                if (sqEl) {
                    const pEl = document.createElement('div');
                    pEl.className = `piece ${piece.color}${piece.type}`;
                    pEl.draggable = true;
                    pEl.dataset.square = sqName;
                    pEl.addEventListener('dragstart', onDragStart);
                    sqEl.appendChild(pEl);
                }
            }
        }
    }
}

// ============================================================================
// MOVE HANDLING
// ============================================================================

function onDragStart(e) {
    if (gameStatus !== 'playing' || aiThinking) return e.preventDefault();
    if (chess.turn() !== myColor) return e.preventDefault();
    draggedPiece = e.target.dataset.square;
    setSelection(draggedPiece);
}

function onDrop(e) {
    e.preventDefault();
    if (!draggedPiece) return;
    let targetSquare = e.target.dataset.square || e.target.parentElement.dataset.square;
    if (!targetSquare) return;
    
    const move = chess.move({
        from: draggedPiece,
        to: targetSquare,
        promotion: 'q'
    });
    
    draggedPiece = null;
    clearSelection();
    if (move) finishMove(move);
}

function onSquareClick(e) {
    if (gameStatus !== 'playing' || aiThinking) return;
    if (chess.turn() !== myColor) return;
    
    let targetSquare = e.target.dataset.square || e.target.parentElement.dataset.square;
    if (!targetSquare) return;
    
    if (selectedSquare) {
        if (selectedSquare === targetSquare) {
            clearSelection();
            return;
        }
        
        const move = chess.move({
            from: selectedSquare,
            to: targetSquare,
            promotion: 'q'
        });
        
        if (move) {
            clearSelection();
            finishMove(move);
        } else {
            const piece = chess.get(targetSquare);
            if (piece && piece.color === myColor) {
                setSelection(targetSquare);
            } else {
                clearSelection();
            }
        }
    } else {
        const piece = chess.get(targetSquare);
        if (piece && piece.color === myColor) {
            setSelection(targetSquare);
        }
    }
}

function clearSelection() {
    selectedSquare = null;
    document.querySelectorAll('.square').forEach(s => s.classList.remove('selected'));
}

function setSelection(sqName) {
    clearSelection();
    selectedSquare = sqName;
    document.querySelector(`[data-square="${sqName}"]`)?.classList.add('selected');
}

function finishMove(move) {
    updateBoard();
    clearHighlights();
    document.querySelector(`[data-square="${move.from}"]`)?.classList.add('last-move');
    document.querySelector(`[data-square="${move.to}"]`)?.classList.add('last-move');
    appendMoveHistory(move);
    
    // Analyze the move if AI is enabled
    if (isVsComputer && chessAI && coachEnabled) {
        analyzeMoveQuality(move);
    }
    
    // Analyze position for evaluation bar
    if (chessAI) {
        analyzePosition();
    }
    
    let reason = '';
    if (chess.in_checkmate()) reason = 'Checkmate';
    else if (chess.in_draw()) reason = 'Draw';
    else if (chess.in_stalemate()) reason = 'Stalemate';
    
    if (isVsComputer) {
        checkLocalGameOver();
        if (!chess.game_over()) {
            makeAIMove();
        }
    } else {
        fetchAPI('move', {
            room_code: currentRoomId,
            fen: chess.fen(),
            from: move.from,
            to: move.to,
            san: move.san,
            game_over: chess.game_over(),
            reason: reason
        });
        checkLocalGameOver();
    }
}

// ============================================================================
// AI OPPONENT FUNCTIONS
// ============================================================================

async function makeAIMove() {
    if (!chessAI || chess.game_over()) return;
    
    aiThinking = true;
    document.getElementById('btn-hint').disabled = true;
    
    try {
        const { move, analysis } = await chessAI.getAIMove(chess.fen(), 800);
        
        if (move && move !== '(none)') {
            // Parse UCI move to algebraic
            const from = move.substring(0, 2);
            const to = move.substring(2, 4);
            const promotion = move.length > 4 ? move[4] : undefined;
            
            const moveObj = chess.move({
                from: from,
                to: to,
                promotion: promotion || 'q'
            });
            
            if (moveObj) {
                updateBoard();
                clearHighlights();
                document.querySelector(`[data-square="${moveObj.from}"]`)?.classList.add('last-move');
                document.querySelector(`[data-square="${moveObj.to}"]`)?.classList.add('last-move');
                appendMoveHistory(moveObj);
                
                // Update previous evaluation for next move analysis
                if (analysis && analysis.score !== null) {
                    previousEvaluation = analysis.score;
                }
                
                updateEvaluationBar();
                checkLocalGameOver();
            }
        }
    } catch (err) {
        console.error('AI move error:', err);
    } finally {
        aiThinking = false;
        document.getElementById('btn-hint').disabled = false;
    }
}

async function analyzePosition() {
    if (!chessAI || gameStatus !== 'playing') return;
    
    try {
        // Get quick analysis without waiting for full depth
        const fen = chess.fen();
        
        // Quick evaluation callback
        const originalCallback = chessAI.callbacks.onAnalysis;
        let bestEval = previousEvaluation;
        
        chessAI.callbacks.onAnalysis = (info) => {
            if (info.score !== null) {
                bestEval = info.score;
                updateEvaluationDisplay(bestEval, info.depth);
            }
        };
        
        chessAI.callbacks.onBestMove = (move, analysis) => {
            chessAI.callbacks.onAnalysis = originalCallback;
            if (analysis && analysis.score !== null) {
                previousEvaluation = analysis.score;
            }
        };
        
        chessAI.analyzePosition(fen);
    } catch (err) {
        console.error('Analysis error:', err);
    }
}

async function analyzeMoveQuality(move) {
    if (!chessAI) return;
    
    try {
        // Store the FEN before the move
        const fenBeforeMove = chess.fen();
        chess.undo();
        const fenBeforeActual = chess.fen();
        chess.redo();
        
        // Already made the move, so analyze new position
        const evaluation = await chessAI.getHint(chess.fen());
        
        const difference = Math.abs((evaluation.analysis?.score || 0) - previousEvaluation);
        const quality = chessAI.assessMoveQuality(difference, evaluation.analysis?.score || 0);
        
        // Display move quality feedback
        showMoveQualityFeedback(quality, difference);
        
    } catch (err) {
        console.error('Move analysis error:', err);
    }
}

async function getHint() {
    if (!chessAI || chess.game_over() || hintsUsed >= maxHints) return;
    
    hintsUsed++;
    document.getElementById('btn-hint').disabled = true;
    clearHighlights();
    
    try {
        const { move, analysis } = await chessAI.getHint(chess.fen());
        
        if (move && move !== '(none)') {
            hintMove = move;
            const from = move.substring(0, 2);
            const to = move.substring(2, 4);
            
            // Highlight hint squares
            document.querySelector(`[data-square="${from}"]`)?.classList.add('hint-from');
            document.querySelector(`[data-square="${to}"]`)?.classList.add('hint-to');
            
            // Show in coach panel
            showHintFeedback(from, to, analysis);
            
            // Remove highlights after 5 seconds
            setTimeout(() => {
                clearHighlights('hint');
            }, 5000);
        }
    } catch (err) {
        console.error('Hint error:', err);
    } finally {
        document.getElementById('btn-hint').disabled = false;
    }
}

// ============================================================================
// EVALUATION & FEEDBACK
// ============================================================================

function updateEvaluationBar() {
    if (!chessAI || chess.game_over()) return;
    
    const evalBar = document.getElementById('eval-bar-fill');
    const evalText = document.getElementById('eval-text');
    
    if (!evalBar || !evalText) return;
    
    const evaluation = previousEvaluation || 0;
    const whiteScore = evaluation;
    
    // Calculate percentage (50% = equal)
    // Values range from -500 (black winning) to +500 (white winning)
    let percentage = 50 + (Math.min(Math.max(whiteScore, -500), 500) / 500) * 50;
    percentage = Math.max(0, Math.min(100, percentage));
    
    // Update bar
    evalBar.style.height = percentage + '%';
    
    // Update color
    if (percentage > 52) {
        evalBar.classList.remove('black');
    } else if (percentage < 48) {
        evalBar.classList.add('black');
    }
    
    // Update text
    let displayEval = Math.abs(whiteScore) / 100;
    if (whiteScore < 0) {
        evalText.innerText = displayEval.toFixed(1);
        evalText.style.color = '#666';
    } else {
        evalText.innerText = '+' + displayEval.toFixed(1);
        evalText.style.color = '#aaa';
    }
}

function updateEvaluationDisplay(evaluation, depth) {
    updateEvaluationBar();
    
    const depthEl = document.getElementById('engine-depth');
    const nodesEl = document.getElementById('engine-nodes');
    
    if (depthEl) depthEl.innerText = depth || '-';
    if (nodesEl) nodesEl.innerText = '-'; // We don't track nodes in browser version
}

function showMoveQualityFeedback(quality, difference) {
    const badge = document.getElementById('move-quality-badge');
    const badgeText = document.getElementById('move-quality-text');
    
    if (!badge || !badgeText) return;
    
    const qualityLabels = {
        'excellent': '✓ Excellent Move',
        'good': '✓ Good Move',
        'inaccuracy': '○ Inaccuracy',
        'mistake': '✗ Mistake',
        'blunder': '✗✗ Blunder'
    };
    
    badgeText.innerText = qualityLabels[quality] || 'Analyzed';
    badgeText.className = 'quality-' + quality;
    badge.classList.remove('hidden');
    
    // Update coach message
    const coachMsg = document.getElementById('coach-message');
    if (coachMsg) {
        const messages = {
            'excellent': '🔥 Excellent! That was a very strong move.',
            'good': '✓ Good move! Keep it up.',
            'inaccuracy': '⚠️ This loses a bit of advantage. Watch for better options.',
            'mistake': 'Oops! This move loses significant material or position.',
            'blunder': '⚠️ BLUNDER! Major mistake. Try to recover now.'
        };
        coachMsg.innerText = messages[quality] || 'Move analyzed.';
    }
    
    // Auto-hide after 4 seconds
    setTimeout(() => {
        badge.classList.add('hidden');
    }, 4000);
}

function showHintFeedback(fromSquare, toSquare, analysis) {
    const coachMsg = document.getElementById('coach-message');
    const evalText = document.getElementById('eval-text');
    
    if (coachMsg) {
        coachMsg.innerText = `💡 Hint: ${fromSquare.toUpperCase()} → ${toSquare.toUpperCase()}`;
    }
    
    console.log('Hint:', fromSquare, toSquare, analysis);
}

function showCoachFeedback(evaluation, gamePhase = 'middle') {
    if (!coachEnabled) return;
    
    const panel = document.getElementById('coach-feedback-panel');
    const msg = document.getElementById('coach-message');
    
    if (!panel || !msg) return;
    
    panel.classList.remove('hidden');
    
    const absEval = Math.abs(evaluation);
    const isWhiteWinning = evaluation > 0;
    
    let feedback = '';
    
    if (absEval > 500) {
        feedback = isWhiteWinning 
            ? '♔ You are winning! Consolidate your advantage.' 
            : '♚ You are losing material. Look for counterattack opportunities.';
    } else if (absEval > 200) {
        feedback = isWhiteWinning 
            ? '♔ You have an advantage. Convert it carefully.' 
            : '♚ You are behind. Create complications.';
    } else if (absEval > 50) {
        feedback = isWhiteWinning 
            ? '♔ Slightly better position. Keep the pressure.' 
            : '♚ Slightly worse. Don\'t make hasty moves.';
    } else {
        feedback = '⚖️ Equal position. Both sides have chances.';
    }
    
    msg.innerText = feedback;
}

function clearHighlights(type = 'all') {
    if (type === 'all' || type === 'last-move') {
        document.querySelectorAll('.square').forEach(s => s.classList.remove('last-move'));
    }
    if (type === 'all' || type === 'hint') {
        document.querySelectorAll('.square').forEach(s => {
            s.classList.remove('hint-from');
            s.classList.remove('hint-to');
        });
    }
}

// ============================================================================
// GAME STATE
// ============================================================================

let moveCounter = 1;
function appendMoveHistory(move) {
    const list = document.getElementById('move-history');
    if (move.color === 'w') {
        const row = document.createElement('div');
        row.className = 'move-row';
        row.innerHTML = `<div class="move-num">${moveCounter}.</div><div class="move-w">${move.san}</div><div class="move-b"></div>`;
        list.appendChild(row);
    } else {
        const row = list.lastElementChild;
        if (row) row.querySelector('.move-b').innerText = move.san;
        moveCounter++;
    }
    list.scrollTop = list.scrollHeight;
}

function checkLocalGameOver() {
    if (chess.game_over()) {
        gameStatus = 'completed';
        let reason = 'Game Over';
        if (chess.in_checkmate()) reason = 'Checkmate';
        else if (chess.in_draw()) reason = 'Draw';
        else if (chess.in_stalemate()) reason = 'Stalemate';
        
        document.getElementById('game-status-text').innerText = 'Game Finished';
        
        setTimeout(() => {
            document.getElementById('game-over-modal').classList.remove('hidden');
            document.getElementById('game-over-desc').innerText = reason;
        }, 500);
    }
}

function startGameUI(isComputer, opponentName = 'Computer') {
    document.getElementById('panel-new-game').classList.add('hidden');
    document.getElementById('panel-playing').classList.remove('hidden');
    document.getElementById('opponent-name').innerText = opponentName;
    gameStatus = 'playing';
    initBoard();
    
    // Setup AI if playing computer
    if (isComputer) {
        document.getElementById('ai-difficulty-section').classList.remove('hidden');
        document.getElementById('ai-coach-toggle').classList.remove('hidden');
        document.getElementById('btn-hint').style.display = 'block';
        setupAIDifficulty();
        hintsUsed = 0;
    }
}

// ============================================================================
// AI INITIALIZATION & SETUP
// ============================================================================

async function initializeAI() {
    try {
        console.log('Initializing Chess AI...');
        chessAI = new ChessAI();
        
        chessAI.callbacks.onReady = () => {
            console.log('✓ Stockfish engine ready!');
            aiEnabled = true;
        };
        
        chessAI.callbacks.onBestMove = (move, analysis) => {
            console.log('Best move:', move);
        };
        
        chessAI.callbacks.onAnalysis = (analysis) => {
            // Silently update analysis
        };
        
        chessAI.callbacks.onError = (error) => {
            console.error('AI Error:', error);
            showNotification('AI engine error: ' + error, 'error');
        };
        
        await chessAI.initialize();
        console.log('✓ AI initialization complete');
        return true;
    } catch (err) {
        console.error('Failed to initialize AI:', err);
        return false;
    }
}

function setupAIDifficulty() {
    document.querySelectorAll('.difficulty-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.difficulty-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const difficulty = btn.getAttribute('data-difficulty');
            if (chessAI) {
                chessAI.setDifficulty(difficulty);
                console.log(`AI difficulty set to: ${difficulty}`);
            }
        });
    });
}

// ============================================================================
// SYNC POLLING FOR MULTIPLAYER
// ============================================================================

function startSyncPolling() {
    if (syncInterval) clearInterval(syncInterval);
    syncInterval = setInterval(() => {
        if (gameStatus === 'completed') {
            clearInterval(syncInterval);
            return;
        }
        fetchAPI('sync', { room_code: currentRoomId, last_move_id: lastMoveId }).then(data => {
            if (!data.success) return;
            
            // Handle opponent joining
            if (gameStatus === 'waiting' && data.room.status === 'playing') {
                const myName = document.getElementById('username')?.value || '';
                const oppName = data.room.player_w_name === myName ? data.room.player_b_name : data.room.player_w_name;
                startGameUI(false, oppName);
            }
            
            // Handle opponent leaving or game over
            if (data.room.status === 'abandoned' || data.room.status === 'completed') {
                gameStatus = 'completed';
                document.getElementById('game-over-modal').classList.remove('hidden');
                document.getElementById('game-over-title').innerText = 'Game Finished';
                document.getElementById('game-over-desc').innerText = data.room.result_reason || 'Opponent Left';
            }
            
            // Apply new moves
            if (data.moves && data.moves.length > 0) {
                data.moves.forEach(m => {
                    const moveObj = chess.move({from: m.move_from, to: m.move_to, promotion: 'q'});
                    if (moveObj) {
                        appendMoveHistory(moveObj);
                        clearHighlights();
                        document.querySelector(`[data-square="${moveObj.from}"]`)?.classList.add('last-move');
                        document.querySelector(`[data-square="${moveObj.to}"]`)?.classList.add('last-move');
                    }
                    lastMoveId = m.id;
                });
                updateBoard();
                checkLocalGameOver();
            }
        });
    }, 2500);
}

// ============================================================================
// UTILITIES & NOTIFICATIONS
// ============================================================================

function showNotification(message, type = 'info') {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'error' ? '#ef4444' : '#4CAF50'};
        color: white;
        padding: 15px 20px;
        border-radius: 4px;
        z-index: 10000;
        animation: slideInDown 0.3s;
    `;
    toast.innerText = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// ============================================================================
// INITIALIZATION & EVENT LISTENERS
// ============================================================================

document.addEventListener('DOMContentLoaded', async () => {
    console.log('Initializing Chess application...');
    
    // Initialize AI engine
    await initializeAI();
    
    // Initialize board
    initBoard();
    
    // Setup Tabs
    const tabs = document.querySelectorAll('.sidebar-tabs .tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            if (gameStatus === 'playing') return;
            
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            document.querySelectorAll('.sidebar-content').forEach(p => p.classList.add('hidden'));
            const targetId = tab.getAttribute('data-target');
            if (targetId) {
                document.getElementById(targetId).classList.remove('hidden');
                if (targetId === 'panel-players') {
                    loadPlayers();
                }
            }
        });
    });
    
    // Setup AI Coach Toggle
    const coachToggle = document.getElementById('coach-enabled');
    if (coachToggle) {
        coachToggle.addEventListener('change', (e) => {
            coachEnabled = e.target.checked;
            const panel = document.getElementById('coach-feedback-panel');
            if (panel) {
                if (!coachEnabled) {
                    panel.classList.add('hidden');
                }
            }
        });
    }
    
    // Setup Hint Button
    const hintBtn = document.getElementById('btn-hint');
    if (hintBtn) {
        hintBtn.addEventListener('click', () => {
            if (hintsUsed >= maxHints) {
                alert(`You've used all ${maxHints} hints!`);
                return;
            }
            getHint();
        });
    }
    
    // Setup Challenge Polling
    setInterval(pollChallenges, 5000);
    
    // Auto Join
    const urlParams = new URLSearchParams(window.location.search);
    const roomParam = urlParams.get('room');
    const myName = document.getElementById('username').value;
    
    if (roomParam) {
        fetchAPI('join_room', { room_code: roomParam }).then(data => {
            if (data.success) {
                currentRoomId = roomParam;
                myColor = data.color;
                startGameUI(false, data.opponent);
                startSyncPolling();
            } else {
                alert(data.error || 'Failed to join room');
            }
        });
    }
});

// ============================================================================
// GAME SETUP BUTTONS
// ============================================================================

document.getElementById('btn-create-link').addEventListener('click', () => {
    const btn = document.getElementById('btn-create-link');
    btn.querySelector('.title').innerText = 'Creating...';
    btn.disabled = true;
    
    fetchAPI('create_room', { target_opponent: null }).then(data => {
        btn.querySelector('.title').innerText = 'Create Challenge Link';
        btn.disabled = false;
        if (data.success) {
            currentRoomId = data.room_code;
            myColor = 'w';
            const fullLink = window.location.origin + window.location.pathname + '?room=' + currentRoomId;
            document.getElementById('share-link-input').value = fullLink;
            document.getElementById('share-link-container').classList.remove('hidden');
            gameStatus = 'waiting';
            startSyncPolling();
        }
    });
});

document.getElementById('btn-copy-link').addEventListener('click', () => {
    const input = document.getElementById('share-link-input');
    input.select();
    document.execCommand('copy');
    const icon = document.querySelector('#btn-copy-link i');
    icon.className = 'fa-solid fa-check';
    setTimeout(() => icon.className = 'fa-regular fa-copy', 2000);
});

document.getElementById('btn-comp-match').addEventListener('click', () => {
    isVsComputer = true;
    currentRoomId = 'local';
    myColor = 'w';
    startGameUI(true, 'Computer (Medium)');
});

document.getElementById('btn-join-link').addEventListener('click', () => {
    const val = document.getElementById('join-link-input').value.trim();
    if (!val) return;
    let code = val;
    if (val.includes('room=')) {
        code = val.split('room=')[1].split('&')[0];
    }
    if (code) {
        window.location.href = '?room=' + code;
    }
});

document.getElementById('btn-resign').addEventListener('click', () => {
    if (!isVsComputer && currentRoomId && gameStatus === 'playing') {
        fetchAPI('abandon', { room_code: currentRoomId });
    }
    window.location.reload();
});

document.getElementById('btn-draw').addEventListener('click', () => {
    alert('Draw offers are not supported in training/casual play right now.');
});

// ============================================================================
// PLAYER MANAGEMENT & CHALLENGES
// ============================================================================

function loadPlayers() {
    const listEl = document.getElementById('players-list');
    listEl.innerHTML = '<div style="color:#888; text-align:center; padding: 20px;">Loading players...</div>';
    
    fetchAPI('get_players').then(data => {
        if (!data.success) {
            listEl.innerHTML = '<div style="color:#ef4444; text-align:center;">Failed to load players.</div>';
            return;
        }
        
        listEl.innerHTML = '';
        if (data.players.length === 0) {
            listEl.innerHTML = '<div style="color:#888; text-align:center; padding: 20px;">No other players found.</div>';
            return;
        }
        
        data.players.forEach(p => {
            const row = document.createElement('div');
            row.style.cssText = `
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: linear-gradient(135deg, #262641 0%, #1a1a2e 100%);
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #333;
                margin-bottom: 10px;
                transition: all 0.3s ease;
                cursor: pointer;
            `;
            row.onmouseover = () => {
                row.style.background = 'linear-gradient(135deg, #312e2b 0%, #262641 100%)';
                row.style.borderColor = '#4CAF50';
                row.style.transform = 'translateY(-2px)';
                row.style.boxShadow = '0 4px 12px rgba(76, 175, 80, 0.2)';
            };
            row.onmouseout = () => {
                row.style.background = 'linear-gradient(135deg, #262641 0%, #1a1a2e 100%)';
                row.style.borderColor = '#333';
                row.style.transform = 'translateY(0)';
                row.style.boxShadow = 'none';
            };
            
            const avatarPath = p.avatar_url ? `../uploads/avatars/${p.avatar_url}` : '';
            const avatarHtml = p.avatar_url 
                ? `<img src="${avatarPath}" style="width: 56px; height: 56px; border-radius: 8px; object-fit: cover; border: 3px solid #4CAF50; box-shadow: 0 0 15px rgba(76, 175, 80, 0.3);">`
                : `<div style="width: 56px; height: 56px; background: linear-gradient(135deg, #4CAF50, #45a049); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; font-weight: bold; box-shadow: 0 0 15px rgba(76, 175, 80, 0.3); flex-shrink: 0;"><i class="fa-solid fa-user"></i></div>`;
            
            row.innerHTML = `
                <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                    <div style="position: relative;">
                        ${avatarHtml}
                        <div style="position: absolute; bottom: 0; right: 0; width: 16px; height: 16px; background: #4CAF50; border-radius: 50%; border: 2px solid #1a1a2e; box-shadow: 0 0 8px rgba(76, 175, 80, 0.6);"></div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <span style="font-weight: 700; font-size: 15px; color: #fff; letter-spacing: 0.3px;">${p.display_name || p.username}</span>
                        <span style="font-size: 13px; color: #aaa;">
                            <i class="fa-solid fa-crown" style="color: #fbbf24; margin-right: 4px;"></i>Level ${p.level || 1}
                            <span style="margin-left: 8px; color: #888;">•</span>
                            <i class="fa-solid fa-bolt" style="color: #fbbf24; margin: 0 4px;"></i>${p.xp || 0} XP
                        </span>
                    </div>
                </div>
                <button class="btn btn-primary challenge-btn" data-opponent="${p.username}" style="padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 6px; transition: all 0.2s; white-space: nowrap;">Challenge</button>
            `;
            listEl.appendChild(row);
        });
        
        document.querySelectorAll('.challenge-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const opp = e.target.getAttribute('data-opponent');
                challengePlayer(opp, e.target);
            });
            btn.addEventListener('mouseover', () => {
                btn.style.transform = 'scale(1.05)';
                btn.style.boxShadow = '0 0 12px rgba(76, 175, 80, 0.4)';
            });
            btn.addEventListener('mouseout', () => {
                btn.style.transform = 'scale(1)';
                btn.style.boxShadow = 'none';
            });
        });
    });
}

function challengePlayer(opponentUsername, btnEl) {
    btnEl.innerText = 'Creating...';
    btnEl.disabled = true;
    
    fetchAPI('create_room', { target_opponent: opponentUsername }).then(data => {
        if (data.success) {
            currentRoomId = data.room_code;
            myColor = 'w';
            const fullLink = window.location.origin + window.location.pathname + '?room=' + currentRoomId;
            
            document.querySelector('[data-target="panel-new-game"]').click();
            document.getElementById('share-link-input').value = fullLink;
            document.getElementById('share-link-container').classList.remove('hidden');
            
            const p = document.createElement('p');
            p.style.cssText = "color: #fbbf24; font-size: 12px; margin-top: 10px;";
            p.innerText = `You challenged ${opponentUsername}! Share this link with them.`;
            document.getElementById('share-link-container').appendChild(p);
            
            gameStatus = 'waiting';
            startSyncPolling();
        } else {
            btnEl.innerText = 'Challenge';
            btnEl.disabled = false;
            alert('Failed to start challenge');
        }
    });
}

let activeChallengeRoom = null;
function pollChallenges() {
    if (gameStatus === 'playing' || !document.getElementById('challenge-toast').classList.contains('hidden')) return;
    
    fetchAPI('check_challenges').then(data => {
        if (data.success && data.challenges && data.challenges.length > 0) {
            const ch = data.challenges[0];
            activeChallengeRoom = ch.room_code;
            document.getElementById('challenge-toast-msg').innerText = `${ch.player_w_name} has challenged you to a game!`;
            document.getElementById('challenge-toast').classList.remove('hidden');
        } else {
            document.getElementById('challenge-toast').classList.add('hidden');
            activeChallengeRoom = null;
        }
    });
}

document.getElementById('btn-challenge-accept').addEventListener('click', () => {
    if (activeChallengeRoom) {
        window.location.href = '?room=' + activeChallengeRoom;
    }
});

document.getElementById('btn-challenge-decline').addEventListener('click', () => {
    if (activeChallengeRoom) {
        fetchAPI('decline_challenge', { room_code: activeChallengeRoom });
        document.getElementById('challenge-toast').classList.add('hidden');
        activeChallengeRoom = null;
    }
});
