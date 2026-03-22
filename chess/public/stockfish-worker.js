/**
 * Stockfish Web Worker
 * Handles all communication with Stockfish WASM engine
 * This worker runs in a separate thread to avoid blocking the UI
 */

let engineReady = false;
let engineInitialized = false;

// Load Stockfish WASM
importScripts('https://cdn.jsdelivr.net/npm/stockfish@14.1.0');

// Initialize Stockfish
function initEngine() {
    if (engineInitialized) return;
    
    try {
        // Initialize Stockfish WASM
        if (typeof Stockfish !== 'undefined') {
            Stockfish().then(sf => {
                engine = sf;
                engine.onmessage = onEngineMessage;
                
                // Initial setup
                engine.postMessage('uci');
                engineInitialized = true;
                
                postMessage({
                    type: 'engine-ready',
                    status: true
                });
            }).catch(err => {
                console.error('Failed to initialize Stockfish:', err);
                postMessage({
                    type: 'error',
                    message: 'Failed to initialize Stockfish WASM engine'
                });
            });
        }
    } catch (err) {
        console.error('Stockfish initialization error:', err);
        postMessage({
            type: 'error',
            message: err.message
        });
    }
}

let engine = null;
let analysisCallback = null;

function onEngineMessage(msg) {
    const line = msg;
    
    // Handle bestmove response
    if (line.startsWith('bestmove')) {
        const parts = line.split(' ');
        const bestMove = parts[1];
        const ponder = parts[3] || null;
        
        postMessage({
            type: 'bestmove',
            move: bestMove,
            ponder: ponder
        });
    }
    
    // Handle info response (evaluation, depth, etc)
    if (line.startsWith('info')) {
        const info = parseInfo(line);
        postMessage({
            type: 'analysis',
            info: info
        });
    }
}

function parseInfo(infoLine) {
    const info = {
        depth: null,
        seldepth: null,
        multipv: 1,
        score: null,
        scoreType: null, // 'cp' for centipawns, 'mate' for moves to mate
        nodes: null,
        nps: null,
        time: null,
        pv: null
    };
    
    const parts = infoLine.split(' ');
    
    for (let i = 0; i < parts.length; i++) {
        if (parts[i] === 'depth') info.depth = parseInt(parts[i + 1]);
        if (parts[i] === 'seldepth') info.seldepth = parseInt(parts[i + 1]);
        if (parts[i] === 'multipv') info.multipv = parseInt(parts[i + 1]);
        if (parts[i] === 'cp') {
            info.score = parseInt(parts[i + 1]);
            info.scoreType = 'cp';
        }
        if (parts[i] === 'mate') {
            info.score = parseInt(parts[i + 1]);
            info.scoreType = 'mate';
        }
        if (parts[i] === 'nodes') info.nodes = parseInt(parts[i + 1]);
        if (parts[i] === 'nps') info.nps = parseInt(parts[i + 1]);
        if (parts[i] === 'time') info.time = parseInt(parts[i + 1]);
        if (parts[i] === 'pv') {
            info.pv = parts.slice(i + 1).join(' ');
        }
    }
    
    return info;
}

// Listen for messages from main thread
self.onmessage = function(e) {
    const { type, data } = e.data;
    
    switch (type) {
        case 'init':
            initEngine();
            break;
            
        case 'position':
            if (engine && engineInitialized) {
                const { fen } = data;
                engine.postMessage(`position fen ${fen}`);
            }
            break;
            
        case 'go':
            if (engine && engineInitialized) {
                const { depth, movetime, nodes } = data;
                let command = 'go';
                
                if (depth) command += ` depth ${depth}`;
                if (movetime) command += ` movetime ${movetime}`;
                if (nodes) command += ` nodes ${nodes}`;
                
                engine.postMessage(command);
            }
            break;
            
        case 'stop':
            if (engine && engineInitialized) {
                engine.postMessage('stop');
            }
            break;
            
        case 'quit':
            if (engine && engineInitialized) {
                engine.postMessage('quit');
            }
            break;
            
        case 'isready':
            if (engine && engineInitialized) {
                engine.postMessage('isready');
                postMessage({
                    type: 'readyok'
                });
            }
            break;
            
        default:
            console.warn('Unknown message type:', type);
    }
};

// Start initialization when worker loads
initEngine();
