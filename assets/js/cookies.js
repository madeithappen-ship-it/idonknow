/**
 * Cookie Consent Banner
 * Manages cookie acceptance and displays consent banner
 */

class CookieConsent {
    constructor() {
        this.cookieName = 'side_quest_cookies_accepted';
        this.cookieExpireDays = 365;
        this.init();
    }

    init() {
        // Check if cookies already accepted
        if (!this.isCookiesAccepted()) {
            this.showBanner();
        }
        this.injectStyles();
    }

    isCookiesAccepted() {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${this.cookieName}=`);
        if (parts.length === 2) return parts.pop().split(';').shift() === 'true';
        return false;
    }

    acceptCookies() {
        // Set cookie for 1 year
        const date = new Date();
        date.setTime(date.getTime() + (this.cookieExpireDays * 24 * 60 * 60 * 1000));
        const expires = `expires=${date.toUTCString()}`;
        document.cookie = `${this.cookieName}=true; ${expires}; path=/; SameSite=Lax`;
        
        // Hide banner
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.style.animation = 'slideDown 0.3s ease-out reverse';
            setTimeout(() => banner.remove(), 300);
        }
    }

    rejectCookies() {
        // Don't set tracking cookies, but still hide banner
        document.cookie = `${this.cookieName}=rejected; expires=${new Date(Date.now() + 86400000).toUTCString()}; path=/; SameSite=Lax`;
        
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.style.animation = 'slideDown 0.3s ease-out reverse';
            setTimeout(() => banner.remove(), 300);
        }
    }

    showBanner() {
        const banner = document.createElement('div');
        banner.id = 'cookie-consent-banner';
        banner.innerHTML = `
            <div class="cookie-content">
                <div class="cookie-text">
                    <h4>🍪 Cookie Consent</h4>
                    <p>We use cookies to provide you with the best experience. Cookies help us remember your preferences and understand how you use our site. By clicking "Accept", you consent to our use of cookies. <a href="privacy.php" target="_blank">Learn more</a></p>
                </div>
                <div class="cookie-buttons">
                    <button class="cookie-btn cookie-reject" id="cookie-reject">Reject</button>
                    <button class="cookie-btn cookie-accept" id="cookie-accept">Accept</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(banner);
        
        // Add event listeners
        document.getElementById('cookie-accept').addEventListener('click', () => this.acceptCookies());
        document.getElementById('cookie-reject').addEventListener('click', () => this.rejectCookies());
    }

    injectStyles() {
        if (document.getElementById('cookie-consent-styles')) return; // Already injected
        
        const style = document.createElement('style');
        style.id = 'cookie-consent-styles';
        style.textContent = `
            #cookie-consent-banner {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(31, 41, 55, 0.95);
                backdrop-filter: blur(10px);
                border-top: 2px solid #4CAF50;
                padding: 20px 40px;
                z-index: 999999;
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
                animation: slideUp 0.3s ease-out;
            }

            @keyframes slideUp {
                from {
                    transform: translateY(100%);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            @keyframes slideDown {
                from {
                    transform: translateY(0);
                    opacity: 1;
                }
                to {
                    transform: translateY(100%);
                    opacity: 0;
                }
            }

            .cookie-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 20px;
                flex-wrap: wrap;
            }

            .cookie-text {
                flex: 1;
                min-width: 250px;
            }

            .cookie-text h4 {
                color: #4CAF50;
                margin-bottom: 8px;
                font-size: 16px;
            }

            .cookie-text p {
                color: #ddd;
                font-size: 14px;
                line-height: 1.6;
                margin: 0;
            }

            .cookie-text a {
                color: #4CAF50;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
            }

            .cookie-text a:hover {
                color: #45a049;
                text-decoration: underline;
            }

            .cookie-buttons {
                display: flex;
                gap: 12px;
                flex-shrink: 0;
            }

            .cookie-btn {
                padding: 10px 24px;
                border: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                min-width: 100px;
            }

            .cookie-reject {
                background: rgba(255, 255, 255, 0.1);
                color: #fff;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .cookie-reject:hover {
                background: rgba(255, 255, 255, 0.15);
            }

            .cookie-accept {
                background: #4CAF50;
                color: #fff;
            }

            .cookie-accept:hover {
                background: #45a049;
            }

            @media (max-width: 768px) {
                #cookie-consent-banner {
                    padding: 15px 20px;
                }

                .cookie-content {
                    flex-direction: column;
                    align-items: stretch;
                }

                .cookie-buttons {
                    width: 100%;
                }

                .cookie-btn {
                    flex: 1;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
}

// Initialize cookie consent when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new CookieConsent();
    });
} else {
    new CookieConsent();
}
