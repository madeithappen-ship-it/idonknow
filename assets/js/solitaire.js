const SUITS = ['Hearts', 'Diamonds', 'Clubs', 'Spades'];
const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
const RED_SUITS = ['Hearts', 'Diamonds'];

let deck = [];
let piles = {
    draw: [],
    waste: [],
    f0: [], f1: [], f2: [], f3: [],
    t0: [], t1: [], t2: [], t3: [], t4: [], t5: [], t6: []
};

let moves = 0;
let score = 0;
let isLocked = false; 

// Drag State
let dragData = {
    active: false,
    cards: [], // DOM elements being dragged
    sourcePile: null,
    startX: 0,
    startY: 0
};

function initGame() {
    buildDeck();
    shuffle(deck);
    
    // Assign 5 random cards as Quest Cards
    let questIndices = [];
    while(questIndices.length < 5) {
        let r = Math.floor(Math.random() * 52);
        if(questIndices.indexOf(r) === -1) questIndices.push(r);
    }
    questIndices.forEach(idx => deck[idx].isQuest = true);

    dealBoard();
}

function buildDeck() {
    deck = [];
    for (let suit of SUITS) {
        for (let j = 0; j < RANKS.length; j++) {
            deck.push({
                suit: suit,
                rank: RANKS[j],
                color: RED_SUITS.includes(suit) ? 'red' : 'black',
                value: j + 1,
                faceUp: false,
                isQuest: false,
                id: `card-${suit}-${RANKS[j]}`
            });
        }
    }
}

function shuffle(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}

function createCardDOM(cardObj) {
    let el = document.createElement('div');
    el.className = `card ${cardObj.color}`;
    el.id = cardObj.id;
    el.dataset.value = cardObj.value;
    el.dataset.suit = cardObj.suit;
    el.dataset.color = cardObj.color;
    
    let front = document.createElement('div');
    front.className = 'card-face card-front';
    // Use unicode symbols
    const symbols = {'Hearts':'♥', 'Diamonds':'♦', 'Clubs':'♣', 'Spades':'♠'};
    front.innerHTML = `<div class="rank">${cardObj.rank}</div><div class="suit">${symbols[cardObj.suit]}</div>`;
    
    let back = document.createElement('div');
    back.className = 'card-face card-back';
    
    el.appendChild(front);
    el.appendChild(back);
    
    // Interaction bindings
    el.addEventListener('pointerdown', handlePointerDown);
    return el;
}

function dealBoard() {
    // Clear DOM
    document.querySelectorAll('.card').forEach(e => e.remove());
    
    let deckCopy = [...deck];
    
    // Deal Tableau
    for (let col = 0; col < 7; col++) {
        piles[`t${col}`] = [];
        for (let row = 0; row <= col; row++) {
            let card = deckCopy.pop();
            card.faceUp = (row === col); // Top card face up
            piles[`t${col}`].push(card);
            
            let dom = createCardDOM(card);
            if (!card.faceUp) dom.classList.add('facedown');
            
            let slot = document.querySelector(`[data-pile="t${col}"]`);
            slot.appendChild(dom);
            dom.style.top = (row * 25) + 'px';
        }
    }
    
    // Rest goes to draw
    piles.draw = deckCopy;
    let drawSlot = document.getElementById('draw-pile');
    piles.draw.forEach((card, idx) => {
        let dom = createCardDOM(card);
        dom.classList.add('facedown');
        drawSlot.appendChild(dom);
        // Stack visual
        dom.style.top = -(idx * 0.2) + 'px';
        dom.style.left = -(idx * 0.2) + 'px';
    });
}

function handlePointerDown(e) {
    if (isLocked) return;
    
    let domCard = e.currentTarget;
    let pileId = domCard.parentElement.dataset.pile || domCard.parentElement.id;
    
    if (pileId === 'draw-pile') pileId = 'draw';
    if (pileId === 'waste-pile') pileId = 'waste';
    
    // Handle Draw Pile Click
    if (pileId === 'draw') {
        drawCard();
        return;
    }
    
    // Handle Face Down cards in Tableau
    if (domCard.classList.contains('facedown')) {
        let pileArr = piles[pileId];
        let cardObj = pileArr[pileArr.length - 1]; // Only top card can be flipped
        if (cardObj.id === domCard.id) {
            flipCardUp(cardObj, domCard);
        }
        return;
    }

    // Prepare Drag
    e.preventDefault();
    domCard.setPointerCapture(e.pointerId);
    
    dragData.active = true;
    dragData.sourcePile = pileId;
    dragData.startX = e.clientX;
    dragData.startY = e.clientY;
    
    // Find all cards below this one in the pile to drag together
    let pileArr = piles[pileId];
    let idx = pileArr.findIndex(c => c.id === domCard.id);
    dragData.cards = [];
    
    for (let i = idx; i < pileArr.length; i++) {
        let childDom = document.getElementById(pileArr[i].id);
        childDom.classList.add('dragging');
        // Store original inline top/left
        childDom.dataset.origTop = childDom.style.top;
        childDom.dataset.origLeft = childDom.style.left;
        dragData.cards.push(childDom);
    }
    
    domCard.addEventListener('pointermove', handlePointerMove);
    domCard.addEventListener('pointerup', handlePointerUp);
}

function handlePointerMove(e) {
    if (!dragData.active) return;
    
    let dx = e.clientX - dragData.startX;
    let dy = e.clientY - dragData.startY;
    
    dragData.cards.forEach(card => {
        card.style.transform = `translate(${dx}px, ${dy}px)`;
    });
}

function handlePointerUp(e) {
    if (!dragData.active) return;
    
    dragData.active = false;
    let targetCard = e.currentTarget;
    targetCard.releasePointerCapture(e.pointerId);
    targetCard.removeEventListener('pointermove', handlePointerMove);
    targetCard.removeEventListener('pointerup', handlePointerUp);
    
    dragData.cards.forEach(card => {
        card.classList.remove('dragging');
        card.style.transform = '';
    });
    
    // Calculate Drop Targeting (Collision Detection)
    let dropSlot = getValidDropSlot(e.clientX, e.clientY);
    
    if (dropSlot) {
        executeMove(dragData.sourcePile, dropSlot.pileId);
    }
}

function getValidDropSlot(x, y) {
    // Basic bounding rect collision
    const slots = document.querySelectorAll('.card-slot');
    for (let slot of slots) {
        let rect = slot.getBoundingClientRect();
        // Allow dropping on the slot area (which contains all its children)
        if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom + 500) {
            let pileId = slot.dataset.pile || slot.id;
            if (pileId !== dragData.sourcePile && pileId !== 'draw-pile' && pileId !== 'waste-pile') {
                if (validateMove(dragData.sourcePile, pileId)) {
                    return { pileId: pileId };
                }
            }
        }
    }
    return null;
}

function validateMove(sourceId, targetId) {
    let sourceArr = piles[sourceId];
    let targetArr = piles[targetId];
    
    // The top dragging card object
    let dragDom = dragData.cards[0];
    let dragCard = sourceArr.find(c => c.id === dragDom.id);
    
    // Foundation Rules
    if (targetId.startsWith('f')) {
        // Can only drop ONE card into foundation
        if (dragData.cards.length > 1) return false;
        
        if (targetArr.length === 0) {
            return dragCard.value === 1; // Must be Ace
        } else {
            let topTarget = targetArr[targetArr.length - 1];
            return (dragCard.suit === topTarget.suit && dragCard.value === topTarget.value + 1);
        }
    }
    
    // Tableau Rules
    if (targetId.startsWith('t')) {
        if (targetArr.length === 0) {
            return dragCard.value === 13; // Must be King
        } else {
            let topTarget = targetArr[targetArr.length - 1];
            return (dragCard.color !== topTarget.color && dragCard.value === topTarget.value - 1);
        }
    }
    
    return false;
}

function executeMove(sourceId, targetId) {
    let sourceArr = piles[sourceId];
    let targetArr = piles[targetId];
    
    // Find index of dragged card in source array
    let dragDom = dragData.cards[0];
    let idx = sourceArr.findIndex(c => c.id === dragDom.id);
    
    // Extract moving cards from state
    let movingCards = sourceArr.splice(idx);
    
    // Append moving cards to target array
    for (let card of movingCards) {
        targetArr.push(card);
        
        // Update DOM
        let dom = document.getElementById(card.id);
        let slot = document.querySelector(`[data-pile="${targetId}"]`);
        slot.appendChild(dom);
        
        // Reset positioning logic based on pile
        if (targetId.startsWith('f')) {
            dom.style.top = '0px';
            dom.style.left = '0px';
            score += 10; // Foundational move points
        } else if (targetId.startsWith('t')) {
            // Recalculate offset for Tableau stack
            let newIdx = targetArr.length - 1;
            dom.style.top = (newIdx * 25) + 'px';
            dom.style.left = '0px';
            score += 5; // Correct rank tracking
        }
    }

    moves++;
    document.getElementById('score-readout').innerText = `Score: ${score} | Moves: ${moves}`;
    saveState();
    checkWinCondition();
}

function drawCard() {
    let drawArr = piles.draw;
    let wasteArr = piles.waste;
    let wasteSlot = document.getElementById('waste-pile');
    let drawSlot = document.getElementById('draw-pile');
    
    if (drawArr.length === 0) {
        // Recycle waste to draw
        while(wasteArr.length > 0) {
            let cardObj = wasteArr.pop();
            cardObj.faceUp = false;
            drawArr.push(cardObj);
            
            let dom = document.getElementById(cardObj.id);
            dom.classList.add('facedown');
            drawSlot.prepend(dom); // push to bottom of DOM stack
            dom.style.top = '0px'; 
            dom.style.left = '0px';
        }
        return;
    }
    
    let cardObj = drawArr.pop();
    cardObj.faceUp = true;
    wasteArr.push(cardObj);
    
    let dom = document.getElementById(cardObj.id);
    dom.classList.remove('facedown');
    wasteSlot.appendChild(dom);
    dom.style.top = '0px';
    dom.style.left = '0px';
}

function flipCardUp(cardObj, domNode) {
    cardObj.faceUp = true;
    domNode.classList.remove('facedown');
    
    // QUEST INTERCEPT LOGIC
    if (cardObj.isQuest) {
        triggerQuest(cardObj);
    }
}

function checkWinCondition() {
    if (piles.f0.length === 13 && piles.f1.length === 13 && piles.f2.length === 13 && piles.f3.length === 13) {
        setTimeout(() => alert("WINNER! You've completely cleared the board!"), 500);
    }
}

// ----------------------------------------------------
// QUEST LOGIC & API SYNC (API Hook-ins)
// ----------------------------------------------------
let activeUserQuestId = null;

function saveState() {
    let payload = {
        deck: deck,
        piles: piles,
        moves: moves,
        score: score
    };
    fetch('api_solitaire.php', {
        method: 'POST',
        body: JSON.stringify({action: 'save', state_json: JSON.stringify(payload), score: score})
    });
}

function loadState() {
    fetch('api_solitaire.php', {
        method: 'POST',
        body: JSON.stringify({action: 'load'})
    }).then(r => r.json()).then(data => {
        if(data.success && data.state) {
            let s = JSON.parse(data.state);
            deck = s.deck;
            piles = s.piles;
            moves = s.moves;
            score = s.score;
            document.getElementById('score-readout').innerText = `Score: ${score} | Moves: ${moves}`;
            restoreBoard();
        } else {
            initGame();
        }
    });
}

function restoreBoard() {
    document.querySelectorAll('.card').forEach(e => e.remove());
    
    // Draw pile
    let drawSlot = document.getElementById('draw-pile');
    piles.draw.forEach((c, idx) => {
        let dom = createCardDOM(c);
        if(!c.faceUp) dom.classList.add('facedown');
        drawSlot.appendChild(dom);
        dom.style.top = -(idx * 0.2) + 'px';
        dom.style.left = -(idx * 0.2) + 'px';
    });
    
    // Waste pile
    let wasteSlot = document.getElementById('waste-pile');
    piles.waste.forEach((c, idx) => {
        let dom = createCardDOM(c);
        if(!c.faceUp) dom.classList.add('facedown');
        wasteSlot.appendChild(dom);
    });

    // Foundations & Tableau
    for (let p in piles) {
        if(p.startsWith('f') || p.startsWith('t')) {
            let slot = document.querySelector(`[data-pile="${p}"]`);
            piles[p].forEach((c, idx) => {
                let dom = createCardDOM(c);
                if(!c.faceUp) dom.classList.add('facedown');
                slot.appendChild(dom);
                dom.style.top = p.startsWith('t') ? (idx * 25) + 'px' : '0px';
            });
        }
    }
}

// Background Reward Polling
setInterval(() => {
    fetch('api_solitaire.php', {
        method: 'POST',
        body: JSON.stringify({action: 'check_rewards'})
    }).then(r => r.json()).then(data => {
        if(data.success && data.reward) {
            alert("🔥 ADMIN APPROVED YOUR INSANE MOD QUEST!! 🔥\\n\\nYou've been granted +5000 Rank Score as your exclusive reward!");
            score += 5000;
            isLocked = false;
            saveState();
            document.getElementById('score-readout').innerText = `Score: ${score} | Moves: ${moves}`;
        }
    });
}, 15000);

// Override DOM load explicitly
document.addEventListener("DOMContentLoaded", () => {
    loadState();
});

function triggerQuest(cardObj) {
    cardObj.isQuest = false; // Prevent re-triggering if drag-dropped again
    isLocked = true; // Freeze the board
    
    document.getElementById('quest-modal').style.display = 'flex';
    document.getElementById('quest-actions').style.display = 'block';
    document.getElementById('quest-upload-area').style.display = 'none';
    
    document.getElementById('quest-text').innerText = "Summoning an extreme challenge magically from the server...";
    
    // Fetch random quest via our API hook
    fetch('get_quest.php?force_insane=true')
        .then(r => r.json())
        .then(data => {
            if(data.success && data.quest) {
                activeUserQuestId = data.quest.id;
                document.getElementById('quest-text').innerHTML = `
                    <div style="color: #64B5F6; font-size: 14px; text-transform: uppercase; font-weight: 900; letter-spacing: 2px;">[ ${data.quest.difficulty} ]</div>
                    <div style="margin: 10px 0; font-size: 22px;">${data.quest.title}</div>
                    <div style="font-size: 15px; color: #aaa; font-weight: normal; line-height: 1.4;">${data.quest.description}</div>
                `;
            } else {
                document.getElementById('quest-text').innerText = "Error: Could not fetch quest. The server is rejecting extreme generations right now.";
            }
        });
}

function acceptQuest() {
    document.getElementById('quest-actions').style.display = 'none';
    document.getElementById('quest-upload-area').style.display = 'block';
}

function submitSolitaireProof() {
    let fileInput = document.getElementById('solitaire-proof');
    if (!fileInput.files.length) {
        alert("You cannot proceed without a photo or video proof of completion!");
        return;
    }
    
    let formData = new FormData();
    formData.append("user_quest_id", activeUserQuestId);
    formData.append("proof", fileInput.files[0]);
    
    document.getElementById('quest-upload-area').innerHTML = "<p style='color: #10b981; font-weight: bold;'>Uploading your payload to Admin review queue...</p>";
    
    fetch('submit_proof.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert("✅ Proof logged successfully! Status = PENDING.\n\nThe game is now unfrozen. Check back later—when an Admin officially approves this proof, you'll receive massive rewards here!");
            isLocked = false;
            document.getElementById('quest-modal').style.display = 'none';
        } else {
            alert("Upload failed: " + data.error);
            isLocked = false;
            document.getElementById('quest-modal').style.display = 'none';
        }
    })
    .catch(e => {
        alert("Network error.");
        isLocked = false;
        document.getElementById('quest-modal').style.display = 'none';
    });
}

function skipQuest() {
    moves += 50;
    document.getElementById('score-readout').innerText = `Score: ${score} | Moves: ${moves}`;
    isLocked = false;
    document.getElementById('quest-modal').style.display = 'none';
}
