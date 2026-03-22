const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

let WORLD_SIZE = 3000;
let player = { x: WORLD_SIZE/2, y: WORLD_SIZE/2, radius: 15, speed: 5, vx: 0, vy: 0, color: '#0ff' };
let camera = { x: 0, y: 0 };

let keys = { w:false, a:false, s:false, d:false };
let joystick = { active: false, dx: 0, dy: 0, base: null, knob: null };

let obstacles = [];
let treasures = [];
let foundTreasures = [];

let isLocked = false;
let mapSeed = 'cyber_01';

// Active UI references
const uiX = document.getElementById('hud-x');
const uiY = document.getElementById('hud-y');
const uiTreasure = document.getElementById('hud-treasures');
const uiWarning = document.getElementById('sonar-warning');

// Seeded Random Generator for deterministic Maps
function mulberry32(a) {
    return function() {
      var t = a += 0x6D2B79F5;
      t = Math.imul(t ^ t >>> 15, t | 1);
      t ^= t + Math.imul(t ^ t >>> 7, t | 61);
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    }
}
let seedRand;

function initGame(savedX, savedY, savedSeed, savedTreasures) {
    player.x = savedX;
    player.y = savedY;
    mapSeed = savedSeed;
    foundTreasures = savedTreasures || [];
    
    // Convert seed string to number for deterministic rand
    let seedNum = Array.from(mapSeed).reduce((acc, char) => acc + char.charCodeAt(0), 0);
    seedRand = mulberry32(seedNum);

    buildWorld();
    resizeCanvas();
    bindControls();
    
    requestAnimationFrame(gameLoop);
    
    // Background saves
    setInterval(saveState, 5000);
}

function buildWorld() {
    obstacles = [];
    treasures = [];
    
    // Generate Neon Buildings
    for(let i=0; i < 150; i++) {
        obstacles.push({
            x: seedRand() * WORLD_SIZE,
            y: seedRand() * WORLD_SIZE,
            w: 50 + seedRand() * 200,
            h: 50 + seedRand() * 200,
            color: `hsl(${seedRand() * 360}, 100%, 20%)`
        });
    }
    
    // Generate 40 Dynamic Treasures (Reward, Quest, Trap, Rare)
    for(let i=0; i < 40; i++) {
        let rand = seedRand();
        let tType = 'quest';
        if (rand > 0.9) tType = 'rare';       // 10%
        else if (rand > 0.7) tType = 'trap';  // 20%
        else if (rand > 0.4) tType = 'reward';// 30%
        else tType = 'quest';                 // 40%
        
        treasures.push({
            id: `tsr_${i}`,
            x: seedRand() * WORLD_SIZE,
            y: seedRand() * WORLD_SIZE,
            type: tType,
            radius: 10
        });
    }
}

function bindControls() {
    window.addEventListener('resize', resizeCanvas);
    
    // Keyboard WASD
    window.addEventListener('keydown', e => {
        let k = e.key.toLowerCase();
        if(keys.hasOwnProperty(k)) keys[k] = true;
    });
    window.addEventListener('keyup', e => {
        let k = e.key.toLowerCase();
        if(keys.hasOwnProperty(k)) keys[k] = false;
    });

    // Touch Joystick
    let zone = document.getElementById('virtual-joystick-container');
    let knob = document.getElementById('virtual-joystick-knob');
    let containerRect;
    
    zone.addEventListener('touchstart', e => {
        e.preventDefault();
        joystick.active = true;
        containerRect = zone.getBoundingClientRect();
        handleTouchMove(e.touches[0]);
    }, {passive: false});

    zone.addEventListener('touchmove', e => {
        e.preventDefault();
        handleTouchMove(e.touches[0]);
    }, {passive: false});

    function stopTouch() {
        joystick.active = false;
        joystick.dx = 0;
        joystick.dy = 0;
        knob.style.transform = `translate(0px, 0px)`;
    }
    zone.addEventListener('touchend', stopTouch);
    zone.addEventListener('touchcancel', stopTouch);

    function handleTouchMove(touch) {
        if(!joystick.active) return;
        let cx = containerRect.left + containerRect.width/2;
        let cy = containerRect.top + containerRect.height/2;
        let dx = touch.clientX - cx;
        let dy = touch.clientY - cy;
        
        let dist = Math.hypot(dx, dy);
        let maxDist = containerRect.width/2 - 25; // 25 is knob radius
        
        if (dist > maxDist) {
            dx = (dx / dist) * maxDist;
            dy = (dy / dist) * maxDist;
        }
        
        knob.style.transform = `translate(${dx}px, ${dy}px)`;
        
        // Normalize for velocity (-1 to 1)
        joystick.dx = dx / maxDist;
        joystick.dy = dy / maxDist;
    }
}

function resizeCanvas() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}

// Circle against AABB Rectangle collision
function checkCollision(px, py) {
    for (let obs of obstacles) {
        // Find closest point on rectangle to circle
        let testX = px;
        let testY = py;
        
        if (px < obs.x) testX = obs.x;
        else if (px > obs.x + obs.w) testX = obs.x + obs.w;
        
        if (py < obs.y) testY = obs.y;
        else if (py > obs.y + obs.h) testY = obs.y + obs.h;
        
        let distX = px - testX;
        let distY = py - testY;
        let distance = Math.hypot(distX, distY);
        
        if (distance <= player.radius) return true;
    }
    return false;
}

function updatePhysics() {
    if (isLocked) return;
    
    // Input mapping
    if (joystick.active) {
        player.vx = joystick.dx * player.speed;
        player.vy = joystick.dy * player.speed;
    } else {
        player.vx = (keys.d - keys.a) * player.speed;
        player.vy = (keys.s - keys.w) * player.speed;
    }
    
    // Diagonal normalization
    if (!joystick.active && player.vx !== 0 && player.vy !== 0) {
        let hyp = Math.SQRT2; // 1.414
        player.vx /= hyp;
        player.vy /= hyp;
    }
    
    // Attempt X movement
    if (player.vx !== 0) {
        let nextX = player.x + player.vx;
        if (nextX - player.radius > 0 && nextX + player.radius < WORLD_SIZE) {
            if (!checkCollision(nextX, player.y)) {
                player.x = nextX;
            }
        }
    }
    
    // Attempt Y movement
    if (player.vy !== 0) {
        let nextY = player.y + player.vy;
        if (nextY - player.radius > 0 && nextY + player.radius < WORLD_SIZE) {
            if (!checkCollision(player.x, nextY)) {
                player.y = nextY;
            }
        }
    }
    
    // Update Camera
    camera.x = player.x - canvas.width / 2;
    camera.y = player.y - canvas.height / 2;
    
    // HUD Update
    uiX.innerText = Math.round(player.x);
    uiY.innerText = Math.round(player.y);
    uiTreasure.innerText = foundTreasures.length;
    
    checkProximity();
}

function checkProximity() {
    let closestDist = Infinity;
    
    for (let t of treasures) {
        if (foundTreasures.includes(t.id)) continue;
        
        let dist = Math.hypot(player.x - t.x, player.y - t.y);
        if (dist < closestDist) closestDist = dist;
        
        // Pickup range!
        if (dist < player.radius + t.radius + 15) {
            triggerQuestSequence(t);
        }
    }
    
    // Sonar Warning
    if (closestDist < 200) {
        uiWarning.style.display = 'block';
        // Pulse faster if closer
        let speed = Math.max(0.2, (closestDist / 200));
        uiWarning.style.animationDuration = speed + 's';
    } else {
        uiWarning.style.display = 'none';
    }
}

function draw() {
    // Fill background
    ctx.fillStyle = '#050510';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    ctx.save();
    ctx.translate(-camera.x, -camera.y);
    
    // Draw Grid Lines (Neon theme)
    ctx.strokeStyle = 'rgba(0, 255, 255, 0.05)';
    ctx.lineWidth = 1;
    let gridSize = 100;
    
    let startX = Math.floor(camera.x / gridSize) * gridSize;
    let endX = startX + canvas.width + gridSize;
    let startY = Math.floor(camera.y / gridSize) * gridSize;
    let endY = startY + canvas.height + gridSize;
    
    ctx.beginPath();
    for(let x = startX; x < endX; x += gridSize) {
        ctx.moveTo(x, startY);
        ctx.lineTo(x, endY);
    }
    for(let y = startY; y < endY; y += gridSize) {
        ctx.moveTo(startX, y);
        ctx.lineTo(endX, y);
    }
    ctx.stroke();

    // Draw Obstacles
    for (let o of obstacles) {
        // Only draw if within camera bounds
        if (o.x + o.w > camera.x && o.x < camera.x + canvas.width &&
            o.y + o.h > camera.y && o.y < camera.y + canvas.height) {
            ctx.fillStyle = o.color;
            ctx.fillRect(o.x, o.y, o.w, o.h);
            ctx.strokeStyle = '#0ff';
            ctx.strokeRect(o.x, o.y, o.w, o.h);
        }
    }
    
    // Draw Active Nearby Treasures (Revealed within 150px)
    for (let t of treasures) {
        if(foundTreasures.includes(t.id)) continue;
        
        let dist = Math.hypot(player.x - t.x, player.y - t.y);
        if (dist < 150) {
            ctx.beginPath();
            ctx.arc(t.x, t.y, t.radius, 0, Math.PI * 2);
            
            let tColor = '#f0f'; // Default Quest
            if (t.type === 'rare') tColor = '#f59e0b';
            else if (t.type === 'trap') tColor = '#ef4444';
            else if (t.type === 'reward') tColor = '#10b981';
            
            ctx.fillStyle = tColor;
            ctx.fill();
            // Glow
            ctx.shadowBlur = 20;
            ctx.shadowColor = ctx.fillStyle;
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.shadowBlur = 0;
        }
    }

    // Draw Player
    ctx.beginPath();
    ctx.arc(player.x, player.y, player.radius, 0, Math.PI * 2);
    ctx.fillStyle = player.color;
    ctx.fill();
    ctx.shadowBlur = 15;
    ctx.shadowColor = '#0ff';
    ctx.stroke();
    ctx.shadowBlur = 0;
    
    ctx.restore();
    
    // Draw Fog of War Mask on UI Layer (Overlayed above translated context!)
    drawFogOfWar();
}

function drawFogOfWar() {
    // Creates a radial gradient hole in a black overlay
    ctx.save();
    ctx.globalCompositeOperation = 'source-over';
    
    let grd = ctx.createRadialGradient(canvas.width/2, canvas.height/2, 100, canvas.width/2, canvas.height/2, 250);
    grd.addColorStop(0, 'rgba(0,0,0,0)');
    grd.addColorStop(1, 'rgba(0,0,0,0.95)');
    
    ctx.fillStyle = grd;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Hard cutoff
    ctx.fillStyle = 'rgba(0,0,0,0.95)';
    ctx.beginPath();
    ctx.rect(0, 0, canvas.width, canvas.height);
    ctx.arc(canvas.width/2, canvas.height/2, 250, 0, Math.PI * 2, true);
    ctx.fill();
    
    ctx.restore();
}

function gameLoop() {
    updatePhysics();
    draw();
    requestAnimationFrame(gameLoop);
}

// ----------------------------------------------------
// SAVE STATE & API ROUTING
// ----------------------------------------------------
fetch('api_hunt.php', {
    method: 'POST',
    body: JSON.stringify({action: 'load'})
}).then(r => r.json()).then(data => {
    if(data.success) {
        initGame(data.x, data.y, data.seed, data.treasures);
    } else {
        initGame(WORLD_SIZE/2, WORLD_SIZE/2, 'cyber_' + Math.floor(Math.random()*1000), []);
    }
});

function saveState() {
    fetch('api_hunt.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'save',
            x: player.x,
            y: player.y,
            seed: mapSeed,
            found_treasures: foundTreasures
        })
    });
}

// ----------------------------------------------------
// QUEST SYSTEM INTERCEPTS
// ----------------------------------------------------
let activeUserQuestId = null;
let activeTreasureId = null;

function triggerQuestSequence(treasureObj) {
    if (treasureObj.type === 'trap') {
        alert("💀 CRITICAL SYSTEM ERROR! TRAP TRIGGERED! You lost 50 XP!");
        foundTreasures.push(treasureObj.id);
        saveState();
        return;
    }
    if (treasureObj.type === 'reward') {
        alert("🎁 PURE CACHE LOCATED! You gained 50 XP instantly!");
        foundTreasures.push(treasureObj.id);
        saveState();
        return;
    }

    isLocked = true;
    activeTreasureId = treasureObj.id;
    
    // Instantly freeze physical momentum
    player.vx = 0; player.vy = 0;
    joystick.active = false;
    
    document.getElementById('quest-modal').style.display = 'flex';
    document.getElementById('quest-actions').style.display = 'block';
    document.getElementById('quest-upload-area').style.display = 'none';
    document.getElementById('quest-text').innerText = "Decrypting mainframe anomaly package...";
    
    fetch('get_quest.php')
        .then(r => r.json())
        .then(data => {
            if(data.success && data.quest) {
                if(data.quest.status === 'submitted') {
                    document.getElementById('quest-text').innerHTML = `
                        <div style="color: #FFC107; font-size: 16px; margin-bottom: 10px;">⏳ QUEST PENDING ADMIN REVIEW</div>
                        <div style="font-size: 14px; color: #aaa;">You already submitted an extraction payload for this sector.<br><br><b>${data.quest.title}</b><br><br>Please wait for an Admin to verify your offline trace before collecting new anomaly data!</div>
                    `;
                    document.getElementById('quest-actions').style.display = 'block';
                    document.getElementById('quest-upload-area').style.display = 'none';
                    document.getElementById('quest-actions').innerHTML = `<button onclick="skipQuest()" style="background: transparent; border: 1px solid #0ff; color: #0ff; padding: 10px 20px; border-radius: 6px; cursor: pointer; width: 100%;">Resume Scanning (Close Panel)</button>`;
                    return;
                }
                
                activeUserQuestId = data.quest.id;
                document.getElementById('quest-text').innerHTML = `
                    <div style="color: #f0f; font-size: 14px; text-transform: uppercase;">[ ${data.quest.difficulty} ]</div>
                    <div style="margin: 10px 0;">${data.quest.title}</div>
                    <div style="font-size: 14px; color: #aaa;">${data.quest.description}</div>
                `;
                document.getElementById('quest-actions').innerHTML = `
                    <button onclick="acceptQuest()" style="background: #0ff; color: #000; border: none; padding: 12px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; margin-bottom: 10px; box-shadow: 0 0 15px rgba(0,255,255,0.5);">ACCEPT DIAGNOSTIC</button>
                    <button onclick="skipQuest()" style="background: transparent; border: 1px solid #f0f; color: #f0f; padding: 10px 20px; border-radius: 6px; cursor: pointer; width: 100%;">BYPASS ANOMALY (Ignore)</button>
                `;
            } else {
                document.getElementById('quest-text').innerText = "Decryption failed. Connection lost.";
            }
        });
}

function acceptQuest() {
    document.getElementById('quest-actions').style.display = 'none';
    document.getElementById('quest-upload-area').style.display = 'block';
}

function skipQuest() {
    // Push player back so they don't immediately re-trigger it
    player.y += 30; 
    player.x += 30;
    
    isLocked = false;
    document.getElementById('quest-modal').style.display = 'none';
    saveState();
    
    if (activeUserQuestId) {
        let formData = new URLSearchParams();
        formData.append("user_quest_id", activeUserQuestId);
        formData.append("csrf_token", CSRF_TOKEN);
        
        fetch('skip_quest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        }).catch(err => console.error(err));
        
        activeUserQuestId = null;
    }
}

function submitHuntProof() {
    let fileInput = document.getElementById('hunt-proof');
    let textInput = document.getElementById('hunt-text-proof');
    let textContent = textInput ? textInput.value.trim() : '';
    
    if (!fileInput.files.length && !textContent) {
        alert("Payload requires a verified media block (Photo/Video Upload) OR a text proxy");
        return;
    }
    
    let formData = new FormData();
    formData.append("user_quest_id", activeUserQuestId);
    if (fileInput.files.length) {
        formData.append("proof", fileInput.files[0]);
    }
    if (textContent) {
        formData.append("text_proof", textContent);
    }
    
    document.getElementById('quest-upload-area').innerHTML = "<p style='color: #0ff'>Transmitting...</p>";
    
    fetch('submit_proof.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert("Data Transmitted. Waiting for SysAdmin approval! You can resume exploring.");
            foundTreasures.push(activeTreasureId);
            isLocked = false;
            document.getElementById('quest-modal').style.display = 'none';
            saveState();
        } else {
            alert("Upload failed: " + data.error);
            isLocked = false;
            document.getElementById('quest-modal').style.display = 'none';
        }
    });
}

// Background poller for Unlocking Rewards
setInterval(() => {
    fetch('api_hunt.php', {
        method: 'POST',
        body: JSON.stringify({action: 'check_rewards'})
    }).then(r => r.json()).then(data => {
        if(data.success && data.reward) {
            alert("SYSADMIN VERIFIED YOUR DATA BLOCK! +10,000 XP");
        }
    });
}, 15000);
