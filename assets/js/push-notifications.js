/**
 * Push Notification Manager
 * Registers service worker and manages push subscriptions
 */

class PushNotificationManager {
    constructor() {
        this.pushSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.isSubscribed = false;
        this.registration = null;
    }

    async init() {
        if (!this.pushSupported) {
            console.log('Push notifications not supported');
            return false;
        }

        try {
            // Register service worker
            this.registration = await navigator.serviceWorker.register('/boringlife/service-worker.js', {
                scope: '/boringlife/'
            });
            console.log('✅ Service Worker registered');

            // Check subscription status
            await this.checkSubscription();
            return true;
        } catch (err) {
            console.error('Service Worker registration failed:', err);
            return false;
        }
    }

    async requestPermission() {
        if (!this.pushSupported) {
            alert('Push notifications not supported on this browser');
            return false;
        }

        const permission = await Notification.requestPermission();
        
        if (permission !== 'granted') {
            console.log('Notification permission denied');
            return false;
        }

        await this.subscribe();
        return true;
    }

    async subscribe() {
        try {
            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(
                    'BJKh8r2vWzQhw4QMr5N5YjbJSYvEu6Q1V5bYGZvH38OvQhV8Rw8J5uBqVrPuGQWHDHVdXyHFfPNGEKrjBhGnUkc='
                )
            });

            console.log('✅ Push subscription successful');
            
            // Send subscription to server
            await this.sendSubscriptionToServer(subscription);
            this.isSubscribed = true;
            return true;
        } catch (err) {
            console.error('Failed to subscribe to push notifications:', err);
            return false;
        }
    }

    async checkSubscription() {
        try {
            const subscription = await this.registration.pushManager.getSubscription();
            this.isSubscribed = !!subscription;
            
            if (subscription) {
                console.log('✅ Already subscribed to push notifications');
            }
        } catch (err) {
            console.error('Failed to check subscription:', err);
        }
    }

    async unsubscribe() {
        try {
            const subscription = await this.registration.pushManager.getSubscription();
            
            if (subscription) {
                await subscription.unsubscribe();
                
                // Notify server
                await fetch('/boringlife/chess/api.php?action=unsubscribe_push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint
                    })
                });
                
                this.isSubscribed = false;
                console.log('✅ Unsubscribed from push notifications');
                return true;
            }
        } catch (err) {
            console.error('Failed to unsubscribe:', err);
        }
        return false;
    }

    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/boringlife/chess/api.php?action=subscribe_push', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subscription: subscription.toJSON()
                })
            });

            const data = await response.json();
            if (!data.success) {
                console.error('Failed to save subscription on server:', data.error);
                return false;
            }
            return true;
        } catch (err) {
            console.error('Error sending subscription to server:', err);
            return false;
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    async sendNotification(title, options = {}) {
        if (!this.registration) return false;

        try {
            await this.registration.showNotification(title, {
                icon: '/boringlife/assets/images/icon.svg',
                badge: '/boringlife/assets/images/badge.svg',
                ...options
            });
            return true;
        } catch (err) {
            console.error('Failed to show notification:', err);
            return false;
        }
    }
}

// Initialize push notifications
let pushManager = null;
document.addEventListener('DOMContentLoaded', async () => {
    pushManager = new PushNotificationManager();
    await pushManager.init();
});

// Function to request push notifications from user
async function requestPushNotifications() {
    if (!pushManager) {
        console.error('Push manager not initialized');
        return false;
    }
    return await pushManager.requestPermission();
}

// Function to disable push notifications
async function disablePushNotifications() {
    if (!pushManager) {
        console.error('Push manager not initialized');
        return false;
    }
    return await pushManager.unsubscribe();
}

// Function to check if notifications are enabled
function arePushNotificationsEnabled() {
    if (!pushManager) return false;
    return pushManager.isSubscribed;
}
