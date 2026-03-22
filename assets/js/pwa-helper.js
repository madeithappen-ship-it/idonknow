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
    this.init();
  }

  init() {
    // Create install button if not exists
    this.createInstallButton();
    
    // Listen for before install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.showInstallButton();
    });

    // Handle successful installation
    window.addEventListener('appinstalled', () => {
      console.log('✅ App installed successfully');
      this.handleInstalled();
      this.deferredPrompt = null;
    });
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
      <span class="pwa-icon">📲</span>
      <span class="pwa-text">Install App</span>
    `;
    button.style.display = 'none';
    
    // Add to nav or create container
    const nav = document.querySelector('.navbar');
    if (nav) {
      nav.appendChild(button);
    } else {
      document.body.appendChild(button);
    }

    this.installBtn = button;

    // Add event listener
    button.addEventListener('click', () => this.promptInstall());
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
    if (!this.deferredPrompt) {
      return;
    }

    // Show install prompt
    this.deferredPrompt.prompt();
    
    // Get user choice
    const { outcome } = await this.deferredPrompt.userChoice;
    console.log(`User response to install prompt: ${outcome}`);

    this.deferredPrompt = null;
    this.hideInstallButton();

    // Show success message
    if (outcome === 'accepted') {
      this.showNotification('App installed! 🎉', 'Side Quest is now on your home screen');
    }
  }

  handleInstalled() {
    this.hideInstallButton();
    this.showNotification(
      'Welcome to Side Quest! 🚀',
      'Enjoy the app. It works offline too!'
    );
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
        display: none;
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

      .pwa-icon {
        font-size: 16px;
      }

      .pwa-text {
        font-size: 12px;
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
    `;
    document.head.appendChild(style);
  }
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { PWAInstaller, OfflineManager, AppShortcuts };
}
