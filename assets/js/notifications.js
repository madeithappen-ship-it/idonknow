/**
 * Real-time Notification System with Sounds
 * Shows popups for admin notifications, daily quests, and chess challenges
 */

class NotificationManager {
    constructor() {
        this.pollInterval = 30000; // Poll every 30 seconds
        this.lastPollId = 0;
        this.isPolling = false;
        this.sounds = {};
        this.maxNotifications = 5;
        this.shownNotifications = new Set(); // Track shown notifications
        
        // Load previously shown notifications from localStorage
        this.loadShownNotifications();
        
        // Initialize notification UI
        this.createNotificationContainer();
        this.loadSounds();
        
        // Start polling
        this.startPolling();
        
        // Handle visibility change (pause polling when tab is hidden)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pausePolling();
            } else {
                this.startPolling();
            }
        });
    }
    
    /**
     * Load shown notifications from localStorage
     */
    loadShownNotifications() {
        try {
            const stored = localStorage.getItem('notif_shown_' + this.getSessionKey());
            if (stored) {
                this.shownNotifications = new Set(JSON.parse(stored));
            }
        } catch (e) {
            console.error('Error loading shown notifications:', e);
        }
    }
    
    /**
     * Save shown notifications to localStorage
     */
    saveShownNotifications() {
        try {
            if (this.shownNotifications.size > 0) {
                localStorage.setItem('notif_shown_' + this.getSessionKey(), 
                    JSON.stringify([...this.shownNotifications]));
            }
        } catch (e) {
            console.error('Error saving shown notifications:', e);
        }
    }
    
    /**
     * Get unique session key for storing notifications
     */
    getSessionKey() {
        // Use user_id from session or create a session-based key
        if (window.__notif_session_key === undefined) {
            window.__notif_session_key = 'session_' + Math.random().toString(36).substr(2, 9);
        }
        return window.__notif_session_key;
    }
    
    /**
     * Mark notification as shown
     */
    markAsShown(notifId) {
        this.shownNotifications.add(notifId);
        this.saveShownNotifications();
    }
    
    /**
     * Check if notification has been shown
     */
    hasBeenShown(notifId) {
        return this.shownNotifications.has(notifId);
    }
    
    /**
     * Create the notification container
     */
    createNotificationContainer() {
        if (document.getElementById('notification-container')) return;
        
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }
    
    /**
     * Load sound effects using Web Audio API
     */
    loadSounds() {
        // Create simple beep sounds using Web Audio API (no external files needed)
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        // Notification sound
        this.sounds.notification = () => this.playBeep(audioContext, 800, 100);
        
        // Quest sound
        this.sounds.quest = () => this.playBeep(audioContext, 1000, 150, 1200, 100);
        
        // Challenge sound
        this.sounds.challenge = () => this.playBeep(audioContext, 1200, 100, 1000, 100, 800, 100);
    }
    
    /**
     * Play a beep sound using Web Audio API
     */
    playBeep(audioContext, ...freqs) {
        try {
            let time = audioContext.currentTime;
            for (let i = 0; i < freqs.length; i += 2) {
                const freq = freqs[i];
                const duration = (freqs[i + 1] || 100) / 1000;
                
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();
                
                osc.connect(gain);
                gain.connect(audioContext.destination);
                
                osc.frequency.value = freq;
                osc.type = 'sine';
                
                gain.gain.setValueAtTime(0.3, time);
                gain.gain.exponentialRampToValueAtTime(0.01, time + duration);
                
                osc.start(time);
                osc.stop(time + duration);
                
                time += duration + 50 / 1000;
            }
        } catch (e) {
            console.error('Error playing sound:', e);
        }
    }
    
    /**
     * Start polling for notifications
     */
    startPolling() {
        if (this.isPolling) return;
        this.isPolling = true;
        this.poll();
    }
    
    /**
     * Pause polling
     */
    pausePolling() {
        this.isPolling = false;
    }
    
    /**
     * Poll for new notifications
     */
    poll() {
        if (!this.isPolling) return;
        
        // Use simple root-relative path
        fetch(`../api_notifications.php?since_id=${this.lastPollId}`)
            .then(r => {
                if (!r.ok) throw new Error('API error');
                return r.json();
            })
            .then(data => {
                if (data.success && data.notifications && data.notifications.length > 0) {
                    // Only show notifications that haven't been shown before
                    data.notifications.forEach(notif => {
                        if (!this.hasBeenShown(notif.id)) {
                            this.showNotification(notif);
                        }
                    });
                    this.lastPollId = data.next_poll_id || this.lastPollId;
                }
            })
            .catch(e => console.error('Notification poll error:', e))
            .finally(() => {
                if (this.isPolling) {
                    setTimeout(() => this.poll(), this.pollInterval);
                }
            });
    }
    
    /**
     * Show a notification popup
     */
    showNotification(notif) {
        const container = document.getElementById('notification-container');
        if (!container) return;
        
        // Mark as shown immediately to prevent duplicate display
        this.markAsShown(notif.id);
        
        // Limit number of visible notifications
        if (container.children.length >= this.maxNotifications) {
            container.removeChild(container.firstChild);
        }
        
        // Create notification element
        const notifEl = document.createElement('div');
        notifEl.className = 'notification-popup';
        notifEl.style.cssText = `
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border: 2px solid #4CAF50;
            border-radius: 10px;
            padding: 16px;
            min-width: 300px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease-out;
            pointer-events: auto;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        `;
        
        // Set border color based on type
        const borderColors = {
            'admin': '#64B5F6',
            'daily_quest': '#FFD700',
            'challenge': '#FF6B6B'
        };
        notifEl.style.borderColor = borderColors[notif.type] || '#4CAF50';
        
        // Title and message
        let html = `
            <div style="font-weight: 700; font-size: 14px; margin-bottom: 6px; color: ${borderColors[notif.type] || '#4CAF50'};">
                ${notif.title}
            </div>
            <div style="font-size: 15px; margin-bottom: 8px; line-height: 1.4;">
                ${notif.message}
            </div>
        `;
        
        // Add description if exists
        if (notif.description) {
            html += `<div style="font-size: 13px; color: #aaa; margin-bottom: 8px;">${notif.description}</div>`;
        }
        
        // Add image if exists
        if (notif.image) {
            html += `<img src="${notif.image}" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 6px; margin-bottom: 8px;">`;
        }
        
        // Add action button for challenges
        if (notif.action_url) {
            html += `
                <button onclick="window.location.href='${notif.action_url}'" style="
                    width: 100%;
                    padding: 8px;
                    background: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 13px;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#45a049'" onmouseout="this.style.background='#4CAF50'">
                    Accept Challenge
                </button>
            `;
        }
        
        // Close button
        html += `
            <button onclick="this.closest('.notification-popup').remove()" style="
                position: absolute;
                top: 8px;
                right: 8px;
                background: transparent;
                border: none;
                color: #aaa;
                cursor: pointer;
                font-size: 18px;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: color 0.2s;
            " title="Close" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#aaa'">
                ✕
            </button>
        `;
        
        notifEl.innerHTML = html;
        notifEl.style.position = 'relative';
        
        // Add hover effect
        notifEl.addEventListener('mouseenter', () => {
            notifEl.style.transform = 'translateX(-4px)';
            notifEl.style.boxShadow = '0 12px 32px rgba(0,0,0,0.4)';
        });
        
        notifEl.addEventListener('mouseleave', () => {
            notifEl.style.transform = 'translateX(0)';
            notifEl.style.boxShadow = '0 8px 24px rgba(0,0,0,0.3)';
        });
        
        container.appendChild(notifEl);
        
        // Play sound
        if (this.sounds[notif.sound]) {
            this.sounds[notif.sound]();
        }
        
        // Auto-remove after 8 seconds
        setTimeout(() => {
            if (notifEl.parentNode) {
                notifEl.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(() => {
                    if (notifEl.parentNode) {
                        notifEl.parentNode.removeChild(notifEl);
                    }
                }, 300);
            }
        }, 8000);
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(450px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(450px);
            opacity: 0;
        }
    }
    
    .notification-popup {
        backdrop-filter: blur(10px);
    }
    
    @media (max-width: 768px) {
        #notification-container {
            top: 10px !important;
            right: 10px !important;
            left: 10px !important;
            max-width: 100% !important;
        }
        
        .notification-popup {
            min-width: auto !important;
            max-width: 100% !important;
        }
    }
`;
document.head.appendChild(style);

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new NotificationManager();
    });
} else {
    new NotificationManager();
}
