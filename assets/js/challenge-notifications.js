/**
 * Global Challenge Notification System
 * Monitors for incoming chess challenges and displays notifications anywhere on the site
 * Automatically redirects to chess game when accepted
 */

(function() {
    let activeChallengeRoom = null;
    let pollInterval = null;
    let isOnChessPage = false;
    
    // Detect if we're on the chess page
    function detectChessPage() {
        return window.location.pathname.includes('/chess/') || 
               document.getElementById('chess-board') !== null;
    }
    
    // Create global challenge toast HTML if it doesn't exist
    function createChallengeToast() {
        // Don't create if we're on chess page (it has its own)
        if (detectChessPage()) {
            isOnChessPage = true;
            return;
        }
        
        // Check if toast already exists
        if (document.getElementById('global-challenge-toast')) {
            return;
        }
        
        const toastHTML = `
        <div id="global-challenge-toast" class="hidden" style="position: fixed; bottom: 20px; right: 20px; background: #262421; border-left: 4px solid #81b64c; box-shadow: 0 4px 15px rgba(0,0,0,0.5); padding: 15px; border-radius: 5px; z-index: 10001; width: 300px; display: flex; flex-direction: column; gap: 10px; transition: opacity 0.3s; opacity: 1; font-family: Arial, sans-serif;">
            <div style="color: #fff; font-weight: bold; font-size: 14px;">
                <i class="fa-solid fa-chess-knight" style="color: #81b64c; margin-right: 5px;"></i> Game Challenge!
            </div>
            <div id="global-challenge-toast-msg" style="color: #c3c3c0; font-size: 13px;">
                Someone has challenged you to a game.
            </div>
            <div id="global-challenge-opponent" style="color: #a0a0a0; font-size: 12px; font-style: italic;"></div>
            <div style="display: flex; gap: 10px; margin-top: 5px;">
                <button id="btn-global-challenge-accept" class="btn" title="Accept Challenge" style="flex: 1; padding: 8px; background: #81b64c; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; transition: opacity 0.2s; font-weight: bold;">
                    <i class="fa-solid fa-check" style="margin-right: 5px;"></i> Accept
                </button>
                <button id="btn-global-challenge-decline" class="btn" title="Decline Challenge" style="flex: 1; padding: 8px; background: #ef4444; color: white; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; transition: opacity 0.2s; font-weight: bold;">
                    <i class="fa-solid fa-xmark" style="margin-right: 5px;"></i> Decline
                </button>
            </div>
        </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', toastHTML);
        setupToastEventListeners();
    }
    
    // Setup event listeners for buttons
    function setupToastEventListeners() {
        const acceptBtn = document.getElementById('btn-global-challenge-accept');
        const declineBtn = document.getElementById('btn-global-challenge-decline');
        
        if (acceptBtn) {
            acceptBtn.addEventListener('click', handleAcceptChallenge);
        }
        
        if (declineBtn) {
            declineBtn.addEventListener('click', handleDeclineChallenge);
        }
    }
    
    // Handle accepting a challenge
    function handleAcceptChallenge() {
        if (activeChallengeRoom) {
            // Redirect to chess page with room code
            window.location.href = '/boringlife/chess/index.php?room=' + activeChallengeRoom;
        }
    }
    
    // Handle declining a challenge
    function handleDeclineChallenge() {
        if (activeChallengeRoom) {
            // Call API to decline
            fetch('../chess/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'decline_challenge',
                    room_code: activeChallengeRoom
                })
            }).catch(err => console.log('Challenge declined'));
            
            hideChallenge();
        }
    }
    
    // Hide the challenge toast
    function hideChallenge() {
        const toast = document.getElementById('global-challenge-toast');
        if (toast) {
            toast.classList.add('hidden');
        }
        activeChallengeRoom = null;
    }
    
    // Poll for challenges
    function pollChallenges() {
        // Skip if on chess page (chess page handles its own)
        if (isOnChessPage) {
            return;
        }
        
        // Skip if a challenge is already showing
        const toast = document.getElementById('global-challenge-toast');
        if (toast && !toast.classList.contains('hidden')) {
            return;
        }
        
        // Fetch challenges from API
        fetch('../chess/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'check_challenges'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.challenges && data.challenges.length > 0) {
                const challenge = data.challenges[0];
                activeChallengeRoom = challenge.room_code;
                
                // Update toast content
                const msgEl = document.getElementById('global-challenge-toast-msg');
                const opponentEl = document.getElementById('global-challenge-opponent');
                
                if (msgEl) {
                    msgEl.innerText = challenge.player_w_name + ' has challenged you to a chess game!';
                }
                
                if (opponentEl && challenge.player_w_rating) {
                    opponentEl.innerText = 'Rating: ' + challenge.player_w_rating;
                }
                
                // Show toast
                if (toast) {
                    toast.classList.remove('hidden');
                }
                
                // Play notification sound if available
                playNotificationSound();
            }
        })
        .catch(err => {
            console.log('Error polling challenges:', err);
        });
    }
    
    // Play notification sound
    function playNotificationSound() {
        try {
            // Check if notifications.js has playSound function
            if (typeof playSound === 'function') {
                playSound();
            } else {
                // Fallback: create and play a simple beep
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = 800;
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            }
        } catch (err) {
            // Sound not available, silently continue
        }
    }
    
    // Initialize the system
    function init() {
        // Check if we're on chess page
        isOnChessPage = detectChessPage();
        
        // Create toast if not on chess page
        if (!isOnChessPage) {
            createChallengeToast();
            
            // Start polling for challenges
            if (!pollInterval) {
                pollInterval = setInterval(pollChallenges, 5000);
                // Do an immediate check
                pollChallenges();
            }
        }
    }
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
    });
})();
