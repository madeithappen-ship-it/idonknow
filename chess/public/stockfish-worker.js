/**
 * Stockfish Web Worker
 * Handles all communication with Stockfish WASM engine
 * This worker runs in a separate thread to avoid blocking the UI
 */

let engine = null;
let engineReady = false;
let engineInitialized = false;

// Load Stockfish WASM from CDN
importScripts('https://cdn.jsdelivr.net/npm/stockfish@14.1.0/dist/stockfish.wasm.js');

// Prevent timing issues - wait for Stockfish to be available globally
let stockfishPromise = null;

function getStockfish() {
    if (!stockfishPromise) {
        stockfishPromise = new Promise((resolve, reject) => {
            let attempts = 0;
            const checkStockfish = () => {
                if (typeof Stockfish !== 'undefined') {
                    console.log('Stockfish found, initializing...');
                    Stockfish().then(sf => {
                        console.log('✓ Stockfish WASM loaded successfully');
                        resolve(sf);
                    }).catch(err => {
                        console.error('Stockfish initialization error:', err);
                        reject(err);
                    });
                } else if (attempts < 50) {
                    attempts++;
                    setTimeout(checkStockfish, 100);
                } else {
                    console.error('Stockfish not found after waiting');
                    reject(new Error('Stockfish WASM not available after timeout'));
                }
            };
            checkStockfish();
        });
    }
    return stockfishPromise;
}

// Initialize Stockfish when worker starts
function initEngine() {
    if (engineInitialized) {
        postMessage({
            type: 'engine-ready',
            status: true
        });
        return;
    }
    
    getStockfish().then(sf => {
        engine = sf;
        engine.onmessage = onEngineMessage;
        
        // Send initial UCI commands
        engine.postMessage('uci');
        engine.postMessage('setoption name Skill Level value 10');
        
        engineInitialized = true;
        engineReady = true;
        
        console.log('✓ Engine ready');
        postMessage({
            type: 'engine-ready',
            status: true
        });
    }).catch(err => {
        console.error('Engine initialization failed:', err);
        postMessage({
            type: 'error',
            message: 'Failed to initialize Stockfish WASM engine: ' + err.message
        });
    });
}

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
