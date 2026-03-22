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

function onDragStart(e) {
    if (gameStatus !== 'playing') return e.preventDefault();
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
    if (gameStatus !== 'playing') return;
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
    
    let reason = '';
    if (chess.in_checkmate()) reason = 'Checkmate';
    else if (chess.in_draw()) reason = 'Draw';
    else if (chess.in_stalemate()) reason = 'Stalemate';
    
    if (isVsComputer) {
        checkLocalGameOver();
        if (!chess.game_over()) setTimeout(makeComputerMove, 500);
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

function makeComputerMove() {
    if (chess.game_over()) return;
    const moves = chess.moves({ verbose: true });
    if (moves.length === 0) return;
    const move = moves[Math.floor(Math.random() * moves.length)];
    chess.move(move);
    updateBoard();
    clearHighlights();
    document.querySelector(`[data-square="${move.from}"]`)?.classList.add('last-move');
    document.querySelector(`[data-square="${move.to}"]`)?.classList.add('last-move');
    appendMoveHistory(move);
    checkLocalGameOver();
}

function clearHighlights() {
    document.querySelectorAll('.square').forEach(s => s.classList.remove('last-move'));
}

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
}

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

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
    initBoard();
    
    // Setup Tabs
    const tabs = document.querySelectorAll('.sidebar-tabs .tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            if (gameStatus === 'playing') return; // Disable tabs during a game
            
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
    
    // Setup Challenge Polling (reduced frequency for performance)
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
    startGameUI(true, 'Computer (Level 1)');
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
            row.style.cssText = 'display: flex; align-items: center; justify-content: space-between; background: #312e2b; padding: 10px; border-radius: 5px;';
            
            const avatarPath = p.avatar_url ? `../uploads/avatars/${p.avatar_url}` : ''; // Fallback to placeholder if none
            const avatarHtml = p.avatar_url 
                ? `<img src="${avatarPath}" style="width: 32px; height: 32px; border-radius: 4px; object-fit: cover;">`
                : `<div style="width: 32px; height: 32px; background: #403d39; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 14px;"><i class="fa-solid fa-user"></i></div>`;
            
            row.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    ${avatarHtml}
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-weight: 600; font-size: 14px; color: #fff;">${p.display_name || p.username}</span>
                        <span style="font-size: 12px; color: #888;">Level ${p.level || 1} • <i class="fa-solid fa-bolt" style="color: #fbbf24;"></i> ${p.xp || 0} XP</span>
                    </div>
                </div>
                <button class="btn btn-primary challenge-btn" data-opponent="${p.username}" style="padding: 6px 12px; font-size: 12px;">Challenge</button>
            `;
            listEl.appendChild(row);
        });
        
        // Add click listeners to challenge buttons
        document.querySelectorAll('.challenge-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const opp = e.target.getAttribute('data-opponent');
                challengePlayer(opp, e.target);
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
            
            // For now, "challenging" just generates a link directly to them if we had a notification system.
            // Since there's no real-time notification system outside of chess, we'll just show the link and prompt them to share it.
            const fullLink = window.location.origin + window.location.pathname + '?room=' + currentRoomId;
            
            // Switch to New Game tab to show the link
            document.querySelector('[data-target="panel-new-game"]').click();
            document.getElementById('share-link-input').value = fullLink;
            document.getElementById('share-link-container').classList.remove('hidden');
            
            // Add a small hint text
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
    // Skip if game is active or already showing a challenge toast
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
