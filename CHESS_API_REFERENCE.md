# 🚀 AI Chess System - Quick Reference & API

## Installation

### 1. Files Created
```
✅ chess/public/stockfish-worker.js    - Web Worker for Stockfish engine
✅ chess/public/chess-ai.js            - AI engine manager class
✅ chess/public/script.js              - Enhanced game logic (REPLACED)
✅ chess/index.php                     - Updated HTML with AI UI (MODIFIED)
✅ chess/public/style.css              - AI UI styles (UPDATED)
```

### 2. Key Dependencies
- `chess.js` - Already included
- **Stockfish WASM** - Loaded from CDN (automatic)
- `chess-ai.js` - Must be loaded before `script.js`

### 3. Script Loading Order
```html
<script src="public/chess.min.js"></script>
<script src="public/chess-ai.js"></script>    <!-- AI engine class -->
<script src="public/script.js"></script>      <!-- Game logic -->
```

---

## Core Classes & Methods

### ChessAI Class

```javascript
// INITIALIZATION
chessAI = new ChessAI()
await chessAI.initialize()

// DIFFICULTY
chessAI.setDifficulty('easy')      // 'easy', 'medium', 'hard', 'expert'
chessAI.difficulty                  // Gets current difficulty

// ANALYSIS
chessAI.analyzePosition(fen)        // Start analysis of position
await chessAI.getAIMove(fen)        // Gets best move with delay
await chessAI.getHint(fen)          // Gets best move without delay
await chessAI.evaluateMove(fen)     // Analyzes move quality

// FEEDBACK
chessAI.generateCoachFeedback(eval) // Creates coaching message
chessAI.assessMoveQuality(diff, eval) // Returns quality rating

// UTILITIES
chessAI.recordMove(move, fen, eval) // Records move in history
chessAI.getMoveStats()              // Gets game statistics
chessAI.reset()                     // Clears game state
chessAI.shutdown()                  // Closes engine

// CALLBACKS
chessAI.callbacks.onReady           // Engine initialized
chessAI.callbacks.onBestMove        // Best move received
chessAI.callbacks.onAnalysis        // Analysis update
chessAI.callbacks.onError           // Error occurred
```

---

## Game Functions

### Starting a Game

```javascript
// Play vs Computer
isVsComputer = true
currentRoomId = 'local'
myColor = 'w'
startGameUI(true, 'Computer (Medium)')

// This automatically:
// 1. Initializes AI engine in Web Worker
// 2. Shows difficulty selector
// 3. Shows AI coach panel
// 4. Enables hint button
```

### Making Moves

```javascript
// Player move (automatic via UI click)
finishMove(move)  // Called when move is complete
  ├─ Updates board
  ├─ Analyzes move quality (if vs computer)
  ├─ Updates evaluation bar
  └─ Calls AI to move

// AI move
await makeAIMove()
  ├─ Gets best move from Stockfish
  ├─ Applies move to board
  ├─ Updates evaluation bar
  ├─ Appends to move history
  └─ Checks if game over
```

### Evaluation & Feedback

```javascript
// Update evaluation bar
updateEvaluationBar()
  ├─ Gets current evaluation
  ├─ Converts to percentage (0-100%)
  ├─ Updates bar height and color
  └─ Shows centipawn display

// Analyze move quality
analyzeMoveQuality(move)
  ├─ Gets evaluation before/after move
  ├─ Calculates quality
  ├─ Shows feedback badge
  └─ Updates coach message

// Get hint
getHint()
  ├─ Checks hints remaining
  ├─ Gets best move
  ├─ Highlights hint squares
  ├─ Shows in coach panel
  └─ Auto-clears after 5s
```

---

## Stockfish Communication Protocol

### Position Setup

```javascript
// Send position to engine
chessAI.setPosition(fen)
// Internally sends: {type: 'position', data: {fen: '...'}}
```

### Analysis Commands

```javascript
// Analyze to specific depth
chessAI.analyzePosition(fen)
// Sends: {type: 'go', data: {depth: 15, movetime: 2500}}
// Difficulty sets depth automatically

// Get hint (quick analysis)
await chessAI.getHint(fen)
// Uses medium difficulty settings regardless of current

// Get AI move (with delay for UX)
await chessAI.getAIMove(fen, 800)  // 800ms artificial delay
```

### Engine Responses

```javascript
// Best move callback
(move, analysis) => {
  move: "e2e4",           // UCI format
  analysis: {
    score: 25,            // Centipawns (positive = white)
    scoreType: 'cp'       // 'cp' or 'mate'
  }
}

// Analysis callback (during thinking)
(info) => {
  depth: 15,              // Search depth
  score: 25,              // Current best score
  scoreType: 'cp',
  nodes: 1000000,         // Nodes evaluated
  time: 2500              // Milliseconds
}
```

---

## Move Quality Ratings

```javascript
// Blunder: >300 centipawn loss
'✗✗ BLUNDER! Major mistake.'

// Mistake: 150-300 centipawn loss
'✗ Mistake! This loses material.'

// Inaccuracy: 50-150 centipawn loss
'○ Inaccuracy. Better options exist.'

// Good: Small loss or gain (<50cp)
'✓ Good move!'

// Excellent: >50 centipawn gain
'🔥 Excellent! Strong move.'
```

---

## UI Components & Selectors

### Key Elements

```javascript
// Evaluation bar
#eval-bar              // Container
#eval-bar-fill         // Filling div (0-100% height)
#eval-text             // Score display

// AI difficulty buttons
.difficulty-btn        // Class for all buttons
[data-difficulty]      // Attribute with level

// Coaching elements
#coach-feedback-panel  // Main coach panel
#coach-message         // Feedback text
#coach-enabled         // Toggle checkbox
#move-quality-badge    // Quality badge
#move-quality-text     // Badge text

// Hint button
#btn-hint              // Get hint button

// Engine analysis
#engine-depth          // Depth display
#engine-nodes          // Nodes display

// Game status
#game-status-text      // "Game in Progress", etc
#game-over-modal       // Game result modal
```

---

## Styling Classes

```css
/* Evaluation bar colors */
.eval-bar-fill        /* White (winning) color */
.eval-bar-fill.black  /* Black (winning) color */

/* Move quality badges */
.quality-excellent    /* Green - excellent move */
.quality-good         /* Light green - good move */
.quality-inaccuracy   /* Yellow - slight mistake */
.quality-mistake      /* Orange - significant error */
.quality-blunder      /* Red - major error */

/* Hint highlights */
.hint-from            /* Yellow highlight (from square) */
.hint-to              /* Blue highlight (to square) */

/* Last move highlight */
.last-move            /* Greenish highlight */

/* Selected square */
.selected             /* Green tinted highlight */
```

---

## Config & Customization

### Difficulty Settings

```javascript
// In chess-ai.js:
this.difficultySettings = {
  easy:   { depth: 5,  movetime: 500 },
  medium: { depth: 10, movetime: 1500 },
  hard:   { depth: 15, movetime: 2500 },
  expert: { depth: 20, movetime: 4000 }
};

// Modify by editing depth and movetime values
// depth: How many plies to analyze
// movetime: Milliseconds to think
```

### Hint Limit

```javascript
// In script.js:
let maxHints = 3;  // Change this value

// Or modify on runtime:
maxHints = 5;  // Increase to 5 hints
```

### Coach Messages

```javascript
// In chess-ai.js, generateCoachFeedback():
const messages = {
  winning_large: '♔ You are winning! Consolidate your advantage.',
  winning_small: '♔ You have a small advantage.',
  equal: '⚖️ Equal position. Both have chances.',
  losing_small: '♚ Slightly worse. Play accurately.',
  losing_large: '♚ You are losing. Look for tactics!'
};
```

---

## Error Handling

```javascript
// Stockfish initialization fails
if (!chessAI) {
  console.error('AI engine not available')
  // Game still playable but no AI features
}

// AI move fails
try {
  const move = await makeAIMove()
} catch (err) {
  console.error('AI move error:', err)
  // Show error notification to user
}

// Worker communication error
chessAI.callbacks.onError = (error) => {
  console.error('AI Error:', error)
  showNotification('AI engine error', 'error')
}
```

---

## Performance Tips

### For Slow Devices
```javascript
// Use lower depths
chessAI.setDifficulty('easy')  // Depth 5 only

// Disable evaluations
const panel = document.getElementById('coach-feedback-panel')
panel.classList.add('hidden')  // Hide coach

// Use medium difficulty
chessAI.setDifficulty('medium')  // Balanced
```

### For Fast Devices
```javascript
// Use higher depths
chessAI.setDifficulty('expert')  // Depth 20

// Enable all features
document.getElementById('coach-enabled').checked = true
```

---

## Debugging Techniques

### Check Initialization

```javascript
// In browser console:
console.log('AI Ready:', aiEnabled)
console.log('AI Class:', chessAI)
console.log('Worker:', chessAI.worker)
```

### Monitor Engine Activity

```javascript
// Watch all communications (in stockfish-worker.js):
console.log('Received message:', msg)
console.log('Sending command:', command)
```

### Test Analysis

```javascript
// Manually analyze a position:
const fen = chess.fen()
chessAI.analyzePosition(fen)
// Wait 2-3 seconds for analysis
// Check console for bestmove output
```

### Verify Callbacks

```javascript
// Test callback firing:
chessAI.callbacks.onBestMove = (move, analysis) => {
  console.log('Move received:', move)
  console.log('Analysis:', analysis)
}
```

---

## Integration with Multiplayer

```javascript
// When playing vs human (no AI):
isVsComputer = false

// Hide AI components
document.getElementById('ai-difficulty-section').classList.add('hidden')
document.getElementById('ai-coach-toggle').classList.add('hidden')
document.getElementById('btn-hint').style.display = 'none'

// Disable AI move generation
// (makeAIMove not called)

// Sync moves with server instead
fetchAPI('move', {room_code, fen, from, to})
```

---

## Event Lifecycle

```
DOMContentLoaded
  ↓
initializeAI() ← Starts Stockfish in Web Worker
  ↓
initBoard() ← Renders chessboard
  ↓
User starts vs Computer game
  ↓
setupAIDifficulty() ← Setup difficulty buttons
  ↓
User makes move
  ↓
finishMove()
  ├─ analyzeMoveQuality() ← Shows feedback
  ├─ analyzePosition() ← Updates eval bar
  └─ makeAIMove() ← AI responds
  ↓
checkLocalGameOver()
  ↓
If checkmate/stalemate: Show game over modal
Else: Continue loop
```

---

## Mobile Optimization

```javascript
// Responsive breakpoints handled in CSS
// Smaller board and eval bar on mobile

// Touch handling (automatic)
// - Tap square to select
// - Tap destination to move
// - No drag required (but drag works too)

// Performance on mobile
// - Lower default difficulty on mobile detect
// - Smaller analysis update frequency
// - Reduced animation complexity
```

---

## Security Notes

✅ **Safe**
- All code runs on user's machine
- No data transmitted
- No tracking
- Works offline

⚠️ **Best Practices**
- Validate all moves with chess.js
- Don't expose internal FEN to user
- Rate-limit analysis requests if needed
- Timeout AI analysis if needed

---

## Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| WASM | ✅ | ✅ | ✅ | ✅ |
| Web Workers | ✅ | ✅ | ✅ | ✅ |
| ES6 | ✅ | ✅ | ✅ | ✅ |
| CSS Grid | ✅ | ✅ | ✅ | ✅ |

**Minimum Versions**: Chrome 74+, Firefox 79+, Safari 14+, Edge 79+

---

## Code Examples

### Example 1: Start Game Programmatically

```javascript
// Start vs Computer on page load
document.addEventListener('DOMContentLoaded', () => {
  // After AI initialized
  setTimeout(() => {
    isVsComputer = true
    myColor = 'w'
    chessAI.setDifficulty('hard')
    startGameUI(true, 'Computer (Hard)')
  }, 1000)
})
```

### Example 2: Custom Coach Feedback

```javascript
// Override defaultcoach messages
chessAI.callbacks.onBestMove = (move, analysis) => {
  if (analysis.score > 300) {
    document.getElementById('coach-message').innerText = 
      '🚀 Winning position! Attack now!'
  }
}
```

### Example 3: Timed Analysis

```javascript
// Analyze with time limit instead of depth
const analyzeWithTime = async (fen, ms) => {
  chessAI.worker.postMessage({
    type: 'go',
    data: { movetime: ms }
  })
  
  return new Promise((resolve) => {
    chessAI.callbacks.onBestMove = (move, analysis) => {
      resolve({move, analysis})
    }
  })
}
```

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| AI very slow | Depth too high | Lower difficulty |
| UI freezes briefly | Main thread blocked | Check other JS |
| No evaluation bar | CSS not loaded | Check style.css |
| AI makes illegal move | Chess.js not loaded | Check script order |
| Worker not found | Path incorrect | Verify file location |
| Stockfish CDN fails | Network issue | Use local fallback |

---

## Support & Resources

- 📖 **chess.js docs**: https://github.com/jhlywa/chess.js
- 📖 **Stockfish WASM**: https://www.npmjs.com/package/stockfish
- 📖 **Web Workers**: https://developer.mozilla.org/en-US/docs/Web/API/Web_Workers_API
- 🐛 **Debug Console**: Press F12 in browser
- 💬 **Your codebase**: Check existing `/memories/` for notes

---

**Ready to play? Visit `/chess/index.php` and click "Play vs Computer"!** ♟️
