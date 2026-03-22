/**
 * Progressive Web App Helper
 * Manages app installation, service worker registration, and offline features
 */

// == SERVICE WORKER REGISTRATION ==
function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker
      .register('./service-worker.js', { scope: './' })
      .then(registration => {
        console.log('✅ Service Worker registered successfully');
        
        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60000); // Check every 60 seconds
      })
      .catch(error => {
        console.error('❌ Service Worker registration failed:', error);
      });

    // Listen for service worker controller change (app update)
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (!refreshing) {
        window.location.reload();
        refreshing = true;
      }
    });
  }
}

// == INSTALL PROMPT HANDLING ==
class PWAInstaller {
  constructor() {
    this.deferredPrompt = null;
    this.installBtn = null;
    this.isInstallable = false;
    this.init();
  }

  init() {
    // Create install button if not exists
    this.createInstallButton();
    
    // Listen for before install prompt (browser indicates app is installable)
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.isInstallable = true;
      this.showInstallButton();
      console.log('✅ App is installable - showing install button');
    });

    // Handle successful installation
    window.addEventListener('appinstalled', () => {
      console.log('✅ App installed successfully');
      this.handleInstalled();
      this.deferredPrompt = null;
      this.isInstallable = false;
    });

    // Check if app is already installed
    if (window.matchMedia('(display-mode: standalone)').matches) {
      console.log('App is running in standalone mode');
      this.hideInstallButton();
    }

    // Show button after short delay even if beforeinstallprompt doesn't fire
    setTimeout(() => {
      if (!this.isInstallable && this.installBtn) {
        this.showInstallButton();
        this.installBtn.classList.add('always-show');
      }
    }, 2000);
  }

  createInstallButton() {
    // Check if button already exists
    if (document.getElementById('pwa-install-btn')) {
      this.installBtn = document.getElementById('pwa-install-btn');
      return;
    }

    // Create button
    const button = document.createElement('button');
    button.id = 'pwa-install-btn';
    button.className = 'pwa-install-btn';
    button.innerHTML = `
      <span class="pwa-icon">⬇️</span>
      <span class="pwa-text">Install App</span>
    `;
    button.style.display = 'flex';
    
    // Add to nav or create container
    const nav = document.querySelector('.navbar');
    if (nav) {
      nav.appendChild(button);
    } else {
      document.body.appendChild(button);
    }

    this.installBtn = button;

    // Add event listener
    button.addEventListener('click', (e) => {
      e.preventDefault();
      this.promptInstall();
    });

    console.log('Install button created');
  }

  showInstallButton() {
    if (this.installBtn) {
      this.installBtn.style.display = 'flex';
      this.installBtn.classList.add('pwa-show');
    }
  }

  hideInstallButton() {
    if (this.installBtn) {
      this.installBtn.style.display = 'none';
      this.installBtn.classList.remove('pwa-show');
    }
  }

  async promptInstall() {
    if (this.deferredPrompt && this.isInstallable) {
      // Show install prompt if available
      this.deferredPrompt.prompt();
      
      // Get user choice
      const { outcome } = await this.deferredPrompt.userChoice;
      console.log(`User response to install prompt: ${outcome}`);

      this.deferredPrompt = null;
      this.isInstallable = false;
      this.hideInstallButton();

      // Show success message
      if (outcome === 'accepted') {
        this.showNotification('App installing! 🎉', 'Side Quest will appear on your home screen');
      }
    } else {
      // Fallback: Show installation instructions
      this.showInstallationGuide();
    }
  }

  showInstallationGuide() {
    const modal = document.createElement('div');
    modal.className = 'pwa-install-modal';
    modal.innerHTML = `
      <div class="pwa-modal-content">
        <button class="pwa-close-btn" onclick="this.parentElement.parentElement.remove()">&times;</button>
        
        <h2>📱 Install Side Quest App</h2>
        
        <div class="install-guide">
          <div class="guide-section">
            <h3>💙 Chrome / Edge Desktop</h3>
            <ol>
              <li>Click the install icon <strong>(⬇️)</strong> in the address bar</li>
              <li>Click <strong>"Install"</strong> in the popup</li>
              <li>App appears on your desktop!</li>
            </ol>
          </div>

          <div class="guide-section">
            <h3>📱 Android (Chrome / Edge)</h3>
            <ol>
              <li>Tap the menu <strong>(⋮)</strong></li>
              <li>Select <strong>"Install app"</strong> or <strong>"Add to Home Screen"</strong></li>
              <li>Confirm in the popup</li>
              <li>App added to your home screen!</li>
            </ol>
          </div>

          <div class="guide-section">
            <h3>🍎 iPhone / iPad (Safari)</h3>
            <ol>
              <li>Tap the Share button <strong>(↑)</strong> at the bottom</li>
              <li>Scroll down and tap <strong>"Add to Home Screen"</strong></li>
              <li>Tap <strong>"Add"</strong></li>
              <li>App appears on your home screen!</li>
            </ol>
          </div>

          <div class="guide-section">
            <h3>🔗 Desktop (Any Browser)</h3>
            <ol>
              <li>Right-click or use browser menu</li>
              <li>Select <strong>"Create shortcut"</strong> or <strong>"Create app"</strong></li>
              <li>App opens in fullscreen window</li>
            </ol>
          </div>

          <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
            <p style="font-size: 13px; color: #999;">
              <strong>💡 Tip:</strong> After installation, the app works offline and loads super fast!
            </p>
          </div>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('pwa-show'), 10);
  }

  handleInstalled() {
    this.hideInstallButton();
    this.showNotification('Welcome! 🚀', 'Side Quest is now on your device. Works offline too!');
  }

  showNotification(title, message) {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = 'pwa-toast';
    toast.innerHTML = `
      <div class="pwa-toast-content">
        <strong>${title}</strong>
        <p>${message}</p>
      </div>
    `;
    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => toast.classList.add('pwa-show'), 10);

    // Remove after 4 seconds
    setTimeout(() => {
      toast.classList.remove('pwa-show');
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }
}

// == OFFLINE DETECTION ==
class OfflineManager {
  constructor() {
    this.isOnline = navigator.onLine;
    this.init();
  }

  init() {
    window.addEventListener('online', () => this.handleOnline());
    window.addEventListener('offline', () => this.handleOffline());
  }

  handleOnline() {
    console.log('🌐 Back online!');
    this.isOnline = true;
    
    // Show notification
    const notification = document.createElement('div');
    notification.className = 'pwa-toast pwa-online';
    notification.innerHTML = `
      <span>✅ Back online</span>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.classList.add('pwa-show');
    }, 10);

    setTimeout(() => {
      notification.classList.remove('pwa-show');
      setTimeout(() => notification.remove(), 300);
    }, 3000);

    // Sync data
    this.syncData();
  }

  handleOffline() {
    console.log('📡 You are offline');
    this.isOnline = false;
  }

  async syncData() {
    // Trigger background sync if available
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
      try {
        const registration = await navigator.serviceWorker.ready;
        await registration.sync.register('sync-games');
        console.log('Background sync scheduled');
      } catch (error) {
        console.log('Sync registration failed:', error);
      }
    }
  }

  static isOnline() {
    return navigator.onLine;
  }
}

// == APP SHORTCUTS ==
class AppShortcuts {
  static registerShortcuts() {
    if ('shortcuts' in navigator) {
      navigator.shortcuts.add([
        {
          name: 'Play Chess',
          url: './chess/index.php?mode=quick',
          description: 'Start a new chess game'
        },
        {
          name: 'View Leaderboard',
          url: './chess/professional-index.php?tab=leaderboard',
          description: 'Check global rankings'
        }
      ]).catch(err => console.log('Shortcuts not available:', err));
    }
  }
}

// == INITIALIZATION ==
document.addEventListener('DOMContentLoaded', () => {
  // Register service worker
  registerServiceWorker();
  
  // Initialize PWA installer
  window.pwaInstaller = new PWAInstaller();
  
  // Initialize offline manager
  window.offlineManager = new OfflineManager();
  
  // Add PWA styles if not already present
  if (!document.getElementById('pwa-styles')) {
    const style = document.createElement('style');
    style.id = 'pwa-styles';
    style.textContent = `
      /* PWA Install Button */
      .pwa-install-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        margin-left: 10px;
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
      }

      .pwa-install-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
      }

      .pwa-install-btn.pwa-show {
        animation: slideDown 0.3s ease;
      }

      .pwa-install-btn.always-show {
        animation: pulse 2s infinite;
      }

      .pwa-icon {
        font-size: 16px;
        animation: bounce 2s infinite;
      }

      /* PWA Install Modal */
      .pwa-install-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
        padding: 20px;
      }

      .pwa-install-modal.pwa-show {
        opacity: 1;
      }

      .pwa-modal-content {
        background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
        border: 1px solid rgba(76, 175, 80, 0.3);
        border-radius: 16px;
        padding: 40px;
        max-width: 600px;
        width: 100%;
        color: #fff;
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
      }

      .pwa-close-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        color: #aaa;
        font-size: 28px;
        cursor: pointer;
        transition: color 0.3s;
      }

      .pwa-close-btn:hover {
        color: #fff;
      }

      .pwa-modal-content h2 {
        margin: 0 0 30px 0;
        font-size: 28px;
        background: linear-gradient(135deg, #4CAF50, #2196F3);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }

      .install-guide {
        display: grid;
        gap: 20px;
      }

      .guide-section {
        background: rgba(255, 255, 255, 0.05);
        border-left: 4px solid #4CAF50;
        padding: 15px;
        border-radius: 8px;
      }

      .guide-section h3 {
        margin: 0 0 10px 0;
        color: #4CAF50;
        font-size: 16px;
      }

      .guide-section ol {
        margin: 0;
        padding-left: 20px;
      }

      .guide-section li {
        margin-bottom: 8px;
        color: #ddd;
      }

      /* PWA Toast Notifications */
      .pwa-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.9);
        color: #fff;
        padding: 16px 20px;
        border-radius: 8px;
        border-left: 4px solid #4CAF50;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
        max-width: 90%;
        font-size: 14px;
      }

      .pwa-toast.pwa-show {
        opacity: 1;
        transform: translateY(0);
      }

      .pwa-toast.pwa-online {
        border-left-color: #4CAF50;
        background: rgba(76, 175, 80, 0.1);
        color: #4CAF50;
      }

      .pwa-toast-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
      }

      .pwa-toast-content strong {
        font-size: 15px;
        font-weight: 600;
      }

      .pwa-toast-content p {
        font-size: 13px;
        opacity: 0.8;
        margin: 0;
      }

      /* Mobile adjustments */
      @media (max-width: 600px) {
        .pwa-install-btn {
          padding: 8px 12px;
          font-size: 12px;
          margin-left: 5px;
        }

        .pwa-icon {
          font-size: 14px;
        }

        .pwa-toast {
          bottom: 15px;
          right: 15px;
          left: 15px;
          max-width: calc(100% - 30px);
        }

        .pwa-modal-content {
          padding: 30px 20px;
        }
      }

      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-3px); }
      }

      @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
      }
    `;
    document.head.appendChild(style);
  }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { PWAInstaller, OfflineManager, AppShortcuts };
}
