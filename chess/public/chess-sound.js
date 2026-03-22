/**
 * Chess Sound Effects System
 * Generates and plays chess move sounds
 */

class ChessSoundSystem {
    constructor() {
        this.enabled = localStorage.getItem('chessSound') !== 'false';
        this.audioContext = null;
        this.masterGain = null;
        this.soundPresets = {
            move: { frequency: 600, duration: 0.15, type: 'sine' },
            capture: { frequency: 800, duration: 0.2, type: 'square' },
            check: { frequency: 900, duration: 0.25, type: 'triangle' },
            gameOver: { frequency: 400, duration: 0.5, type: 'sine' }
        };
    }

    init() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.masterGain = this.audioContext.createGain();
            this.masterGain.connect(this.audioContext.destination);
            this.masterGain.gain.value = 0.3; // 30% volume
        } catch (e) {
            console.log('Web Audio API not supported');
            this.enabled = false;
        }
    }

    play(soundType = 'move') {
        if (!this.enabled || !this.audioContext) return;

        const preset = this.soundPresets[soundType] || this.soundPresets.move;

        try {
            const now = this.audioContext.currentTime;
            const oscillator = this.audioContext.createOscillator();
            const envelope = this.audioContext.createGain();

            oscillator.type = preset.type;
            oscillator.frequency.value = preset.frequency;

            // Envelope (attack-decay)
            envelope.gain.setValueAtTime(0.5, now);
            envelope.gain.exponentialRampToValueAtTime(0.01, now + preset.duration);

            oscillator.connect(envelope);
            envelope.connect(this.masterGain);

            oscillator.start(now);
            oscillator.stop(now + preset.duration);
        } catch (e) {
            console.log('Error playing sound:', e);
        }
    }

    playMove(isCapture = false, isCheck = false) {
        if (isCheck) {
            this.play('check');
        } else if (isCapture) {
            this.play('capture');
        } else {
            this.play('move');
        }
    }

    playGameOver() {
        this.play('gameOver');
    }

    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('chessSound', this.enabled ? 'true' : 'false');
        return this.enabled;
    }

    setVolume(value) {
        if (this.masterGain) {
            this.masterGain.gain.value = Math.max(0, Math.min(1, value));
        }
    }
}

// Initialize sound system
let chessSound = new ChessSoundSystem();
document.addEventListener('DOMContentLoaded', () => {
    chessSound.init();
});
