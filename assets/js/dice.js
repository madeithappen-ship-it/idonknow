let isRolling = false;

// Check status on load
fetch('api_dice.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'status' })
})
.then(r => r.json())
.then(data => {
    if (data.success) {
        updateRerollText(data.rerolls_left);
    }
});

function updateRerollText(left) {
    const text = document.getElementById('reroll-text');
    const btn = document.getElementById('roll-btn');
    if (left <= 0) {
        text.innerText = "0 rerolls left today.";
        btn.disabled = true;
        btn.innerText = "OUT OF REROLLS";
    } else {
        text.innerText = `${left} reroll(s) available today.`;
    }
}

document.getElementById('roll-btn').addEventListener('click', () => {
    if (isRolling) return;
    isRolling = true;
    
    const btn = document.getElementById('roll-btn');
    const dice = document.getElementById('dice');
    const card = document.getElementById('quest-card');
    
    btn.disabled = true;
    btn.innerText = "ROLLING...";
    card.style.opacity = '0';
    setTimeout(() => card.style.display = 'none', 500);
    
    // Add random spinning quickly before fetching
    dice.style.transition = 'transform 0.5s linear';
    dice.style.transform = `rotateX(${Math.random()*720}deg) rotateY(${Math.random()*720}deg)`;
    
    fetch('api_dice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'roll' })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            // Map roll to face:
            // 1: front (0,0)
            // 2: top (-90,0)
            // 3: right (0,-90)
            // 4: left (0,90)
            // 5: bottom (90,0)
            // 6: back (180,0)
            
            let rx = 0, ry = 0;
            const target = data.roll;
            if (target === 1) { rx = 0; ry = 0; }
            if (target === 2) { rx = -90; ry = 0; }
            if (target === 3) { rx = 0; ry = -90; }
            if (target === 4) { rx = 0; ry = 90; }
            if (target === 5) { rx = 90; ry = 0; }
            if (target === 6) { rx = 180; ry = 0; }
            
            // Add extra spins (3 full rotations backwards to create a spinning effect)
            rx -= 360 * 3;
            ry -= 360 * 3;
            
            setTimeout(() => {
                dice.style.transition = 'transform 1.5s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
                dice.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`;
                
                setTimeout(() => {
                    // Show quest
                    document.getElementById('quest-difficulty').innerText = `[ ${data.difficulty} ]`;
                    let color = '#99ff99';
                    if(data.difficulty==='medium') color = '#ffeb99';
                    if(data.difficulty==='hard') color = '#ffb399';
                    if(data.difficulty==='insane') color = '#ff4444';
                    document.getElementById('quest-difficulty').style.color = color;
                    
                    document.getElementById('quest-title').innerText = data.quest.title;
                    document.getElementById('quest-desc').innerText = data.quest.description;
                    document.getElementById('quest-xp').innerText = `+${data.quest.xp_reward} XP`;
                    
                    card.style.display = 'block';
                    setTimeout(() => card.style.opacity = '1', 50);
                    
                    updateRerollText(data.rerolls_left);
                    isRolling = false;
                    if(data.rerolls_left > 0) {
                        btn.disabled = false;
                        btn.innerText = "ROLL AGAIN";
                    }
                }, 1600);
            }, 500); // giving time for the fetch quick spin to settle
            
        } else {
            alert(data.error);
            isRolling = false;
            btn.innerText = "ROLL DICE";
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alert("Server error connecting to dice engine.");
        isRolling = false;
        btn.innerText = "ROLL DICE";
        btn.disabled = false;
    });
});
