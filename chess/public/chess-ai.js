/**
 * Chess AI Engine Manager
 * Manages Stockfish communication, analysis, and AI opponent logic
 */

class ChessAI {
    constructor() {
        this.worker = null;
        this.isReady = false;
        this.isAnalyzing = false;
        this.bestMove = null;
        this.currentAnalysis = null;
        this.difficulty = 'medium'; // easy, medium, hard, expert
        this.callbacks = {
            onReady: null,
            onBestMove: null,
            onAnalysis: null,
            onError: null
        };
        
        // Difficulty settings
        this.difficultySettings = {
            easy: { depth: 5, movetime: 500 },
            medium: { depth: 10, movetime: 1500 },
            hard: { depth: 15, movetime: 2500 },
            expert: { depth: 20, movetime: 4000 }
        };
        
        this.moveHistory = [];
        this.lastEvaluation = 0;
    }
    
    /**
     * Initialize the AI engine
     */
    async initialize() {
        try {
            console.log('🚀 Starting Chess AI initialization...');
            
            // Create worker with fallback for older browsers
            if (typeof Worker !== 'undefined') {
                console.log('✓ Web Workers supported, creating Stockfish worker...');
                this.worker = new Worker('public/stockfish-worker.js');
                this.setupWorkerListeners();
                
                // Send init message
                this.worker.postMessage({ type: 'init' });
                console.log('✓ Init message sent to worker');
                
                // Wait for engine to be ready
                return new Promise((resolve) => {
                    let readyTimeout = setTimeout(() => {
                        console.warn('⚠️ Stockfish initialization timeout - engine may not be responding');
                        console.log('This can happen if Stockfish WASM CDN is slow or inaccessible');
                        resolve(true); // Still resolve so game can start
                    }, 8000);
                    
                    const originalCallback = this.callbacks.onReady;
                    this.callbacks.onReady = () => {
                        console.log('✅ AI Engine ready!');
                        clearTimeout(readyTimeout);
                        this.isReady = true;
                        if (originalCallback) originalCallback();
                        resolve(true);
                    };
                });
            } else {
                throw new Error('Web Workers not supported in this browser');
            }
        } catch (err) {
            console.error('❌ AI Engine initialization error:', err);
            if (this.callbacks.onError) {
                this.callbacks.onError('Failed to initialize Stockfish: ' + err.message);
            }
            return false;
        }
    }
    
    /**
     * Setup worker message listeners
     */
    setupWorkerListeners() {
        this.worker.onmessage = (e) => {
            const { type, data } = e.data;
            
            switch (type) {
                case 'engine-ready':
                    console.log('Stockfish engine is ready');
                    if (this.callbacks.onReady) this.callbacks.onReady();
                    break;
                    
                case 'bestmove':
                    this.isAnalyzing = false;
                    this.bestMove = e.data.move;
                    console.log('Best move:', this.bestMove);
                    if (this.callbacks.onBestMove) {
                        this.callbacks.onBestMove(this.bestMove, this.currentAnalysis);
                    }
                    break;
                    
                case 'analysis':
                    this.currentAnalysis = e.data.info;
                    if (this.callbacks.onAnalysis) {
                        this.callbacks.onAnalysis(this.currentAnalysis);
                    }
                    break;
                    
                case 'error':
                    console.error('Engine error:', e.data.message);
                    if (this.callbacks.onError) {
                        this.callbacks.onError(e.data.message);
                    }
                    break;
            }
        };
    }
    
    /**
     * Set AI difficulty level
     */
    setDifficulty(level) {
        if (this.difficultySettings[level]) {
            this.difficulty = level;
            console.log(`AI difficulty set to: ${level}`);
        }
    }
    
    /**
     * Set up position in Stockfish
     */
    setPosition(fen) {
        if (!this.worker) return;
        this.worker.postMessage({
            type: 'position',
            data: { fen }
        });
    }
    
    /**
     * Analyze position and get best move
     */
    analyzePosition(fen) {
        if (!this.worker) {
            console.error('❌ Worker not available');
            return;
        }
        
        if (this.isAnalyzing) {
            console.warn('⚠️ Already analyzing a position');
            return;
        }
        
        this.isAnalyzing = true;
        this.bestMove = null;
        this.currentAnalysis = null;
        
        try {
            this.setPosition(fen);
            
            const settings = this.difficultySettings[this.difficulty];
            console.log(`📊 Analyzing position with ${this.difficulty} difficulty (depth: ${settings.depth})`);
            
            this.worker.postMessage({
                type: 'go',
                data: settings
            });
        } catch (err) {
            console.error('❌ Analysis error:', err);
            this.isAnalyzing = false;
        }
    }
    
    /**
     * Get hint move for current position
     */
    getHint(fen) {
        return new Promise((resolve) => {
            const originalCallback = this.callbacks.onBestMove;
            
            this.callbacks.onBestMove = (move, analysis) => {
                this.callbacks.onBestMove = originalCallback;
                resolve({
                    move,
                    analysis
                });
            };
            
            this.analyzePosition(fen);
        });
    }
    
    /**
     * Stop current analysis
     */
    stopAnalysis() {
        if (!this.worker || !this.isAnalyzing) return;
        this.worker.postMessage({ type: 'stop' });
        this.isAnalyzing = false;
    }
    
    /**
     * Get AI move with artificial delay (for better UX)
     */
    async getAIMove(fen, delayMs = 800) {
        return new Promise((resolve) => {
            // Fallback if engine isn't working
            if (!this.worker || !this.isReady) {
                console.warn('⚠️ AI Engine not ready, using random move fallback');
                setTimeout(() => {
                    resolve({ 
                        move: '(none)',
                        analysis: { score: 0, depth: 0 }
                    });
                }, delayMs);
                return;
            }
            
            const startTime = Date.now();
            let moveResolved = false;
            
            this.callbacks.onBestMove = (move, analysis) => {
                if (moveResolved) return;
                moveResolved = true;
                
                // Apply artificial delay if needed
                const elapsed = Date.now() - startTime;
                const remaining = Math.max(0, delayMs - elapsed);
                
                setTimeout(() => {
                    resolve({ move: move || '(none)', analysis });
                }, remaining);
            };
            
            // Add timeout fallback in case bestmove never comes
            setTimeout(() => {
                if (!moveResolved) {
                    moveResolved = true;
                    console.warn('⚠️ AI move timeout, using fallback');
                    resolve({ 
                        move: '(none)',
                        analysis: { score: 0, depth: 0 }
                    });
                }
            }, delayMs + 2000);
            
            this.analyzePosition(fen);
        });
    }
    
    /**
     * Evaluate position change (for move analysis)
     */
    evaluateMove(fen, previousEvaluation) {
        return new Promise((resolve) => {
            const originalCallback = this.callbacks.onAnalysis;
            let bestEval = previousEvaluation;
            
            this.callbacks.onAnalysis = (info) => {
                if (info.score !== null) {
                    bestEval = info.score;
                }
            };
            
            this.callbacks.onBestMove = (move, analysis) => {
                this.callbacks.onAnalysis = originalCallback;
                
                const evaluation = bestEval;
                const difference = Math.abs(evaluation - previousEvaluation);
                
                resolve({
                    move,
                    evaluation,
                    difference,
                    quality: this.assessMoveQuality(difference, evaluation)
                });
            };
            
            this.analyzePosition(fen);
        });
    }
    
    /**
     * Assess move quality based on evaluation change
     */
    assessMoveQuality(evalDifference, newEvaluation) {
        // Thresholds are in centipawns
        if (evalDifference > 300) return 'blunder';
        if (evalDifference > 150) return 'mistake';
        if (evalDifference > 50) return 'inaccuracy';
        if (evalDifference < -50) return 'excellent';
        return 'good';
    }
    
    /**
     * Generate coaching feedback based on position evaluation
     */
    generateCoachFeedback(evaluation, gamePhase = 'middle') {
        const absEval = Math.abs(evaluation);
        const isWhiteWinning = evaluation > 0;
        
        const feedback = {
            evaluation: evaluation,
            recommendation: '',
            warning: null
        };
        
        if (absEval > 500) {
            feedback.recommendation = isWhiteWinning 
                ? '♔ You are winning! Consolidate your advantage.' 
                : '♚ You are losing material. Look for counterattack opportunities.';
        } else if (absEval > 200) {
            feedback.recommendation = isWhiteWinning 
                ? '♔ You have an advantage. Convert it carefully.' 
                : '♚ You are behind. Create complications.';
        } else if (absEval > 50) {
            feedback.recommendation = isWhiteWinning 
                ? '♔ Slightly better position. Keep the pressure.' 
                : '♚ Slightly worse. Don\'t make hasty moves.';
        } else {
            feedback.recommendation = '⚖️ Equal position. Both sides have chances.';
        }
        
        return feedback;
    }
    
    /**
     * Record move in history
     */
    recordMove(move, fen, evaluation) {
        this.moveHistory.push({
            move,
            fen,
            evaluation,
            timestamp: Date.now()
        });
    }
    
    /**
     * Get move statistics
     */
    getMoveStats() {
        if (this.moveHistory.length === 0) return null;
        
        const evaluations = this.moveHistory.map(m => m.evaluation);
        const average = evaluations.reduce((a, b) => a + b, 0) / evaluations.length;
        const maxEval = Math.max(...evaluations);
        const minEval = Math.min(...evaluations);
        
        return {
            totalMoves: this.moveHistory.length,
            averageEvaluation: average,
            maxEvaluation: maxEval,
            minEvaluation: minEval
        };
    }
    
    /**
     * Reset AI state
     */
    reset() {
        this.stopAnalysis();
        this.moveHistory = [];
        this.bestMove = null;
        this.currentAnalysis = null;
        this.lastEvaluation = 0;
    }
    
    /**
     * Shutdown AI engine
     */
    shutdown() {
        if (this.worker) {
            this.stopAnalysis();
            this.worker.postMessage({ type: 'quit' });
            this.worker.terminate();
            this.worker = null;
        }
    }
    
    /**
     * Diagnostic method for debugging AI issues
     */
    diagnose() {
        const status = {
            workerAvailable: !!this.worker,
            engineReady: this.isReady,
            isAnalyzing: this.isAnalyzing,
            difficulty: this.difficulty,
            difficulty_settings: this.difficultySettings[this.difficulty],
            timestamp: new Date().toLocaleString()
        };
        
        console.log('🔍 ChessAI Diagnostic Report:');
        console.table(status);
        
        if (!this.worker) {
            console.error('❌ CRITICAL: No worker available');
            return;
        }
        
        // Send a quick test position to see if engine responds
        console.log('🧪 Running diagnostic test with starting position...');
        const testFen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
        
        let testTimeout = setTimeout(() => {
            console.error('❌ TIMEOUT: Engine did not respond to diagnostic test');
            console.log('💡 Try: 1) Refresh page, 2) Check browser console for Stockfish errors, 3) Check network connection');
        }, 5000);
        
        const originalCallback = this.callbacks.onBestMove;
        let responseReceived = false;
        
        this.callbacks.onBestMove = (move, analysis) => {
            if (responseReceived) return;
            responseReceived = true;
            
            clearTimeout(testTimeout);
            console.log('✅ Diagnostic test PASSED!');
            console.log('  - Engine responded with move:', move);
            console.log('  - Evaluation:', analysis?.score || 0, 'centipawns');
            console.log('  - Depth reached:', analysis?.depth || 0);
            
            this.callbacks.onBestMove = originalCallback;
        };
        
        this.analyzePosition(testFen);
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChessAI;
}
