let isSpinning = false;
let currentRotation = 0;

document.getElementById('spin-btn').addEventListener('click', () => {
    if (isSpinning) return;
    
    isSpinning = true;
    const btn = document.getElementById('spin-btn');
    const resultText = document.getElementById('result-text');
    const wheel = document.getElementById('wheel-container');
    
    btn.disabled = true;
    btn.innerText = "SPINNING...";
    resultText.innerText = "";
    resultText.style.color = "#fff";
    
    fetch('api_wheel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'spin' })
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            // Determine result slice
            const type = data.result.type;
            const amount = data.result.amount;
            let targetSlice = 0;
            
            // Map types to slices
            // Slice 1 (0deg): +100 XP
            // Slice 2 (90deg): -50 XP
            // Slice 3 (180deg): +25 XP
            // Slice 4 (270deg): QUEST
            
            if (type === 'xp' && amount === 100) targetSlice = 1;
            else if (type === 'trap') targetSlice = 2;
            else if (type === 'xp' && amount === 25) targetSlice = 3;
            else if (type === 'quest') targetSlice = 4;
            else targetSlice = 1; // Fallback
            
            // Calculate final rotation
            // A wheel rotation of 0 puts Slice 1 on top right (0-90 bound).
            // We want the slice to land under the top pointer.
            // If target is 1: Spin 360-45 = 315 deg to land
            // If target is 2: Spin 360-135 = 225 deg to land 
            // If target is 3: Spin 360-225 = 135 deg to land
            // If target is 4: Spin 360-315 = 45 deg to land
            const spins = 5 * 360; // 5 full rotations
            
            let sliceOffset = 0;
            if (targetSlice === 1) sliceOffset = 315; 
            if (targetSlice === 2) sliceOffset = 225; 
            if (targetSlice === 3) sliceOffset = 135; 
            if (targetSlice === 4) sliceOffset = 45; 
            
            // Add a random variance within the slice (approx +-20 deg)
            const variance = Math.floor(Math.random() * 40) - 20;
            
            const totalRotation = currentRotation + spins + sliceOffset + variance - (currentRotation % 360);
            
            wheel.style.transform = `rotate(${totalRotation}deg)`;
            currentRotation = totalRotation;
            
            setTimeout(() => {
                isSpinning = false;
                btn.innerText = "ALREADY SPUN TODAY";
                resultText.innerText = data.result.label;
                resultText.style.color = data.result.color;
            }, 4000);
            
        } else {
            alert(data.error);
            isSpinning = false;
            btn.innerText = "SPIN WHEEL";
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alert("Server error connecting to wheel");
        isSpinning = false;
        btn.innerText = "SPIN WHEEL";
        btn.disabled = false;
    });
});
