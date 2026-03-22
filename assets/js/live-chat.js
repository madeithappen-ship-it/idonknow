/**
 * Live Chat System for Players
 * Direct messaging between players across the site
 */

class LiveChat {
    constructor() {
        this.currentConversation = null;
        this.conversations = [];
        this.lastMessageId = {};
        this.pollInterval = null;
        this.chatVisible = false;
    }

    init() {
        this.createChatUI();
        this.setupEventListeners();
        this.loadConversations();
        this.startPolling();
    }

    createChatUI() {
        const chatHTML = `
        <div id="live-chat-container" style="position: fixed; bottom: 20px; right: 20px; width: 350px; max-height: 600px; background: #262421; border: 2px solid #81b64c; border-radius: 10px; display: flex; flex-direction: column; z-index: 1500; box-shadow: 0 4px 15px rgba(0,0,0,0.5); font-family: Arial, sans-serif;">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #312e2b 0%, #1a1a2e 100%); padding: 15px; border-bottom: 2px solid #81b64c; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="color: #81b64c; font-weight: bold; font-size: 14px;">💬 Messages</span>
                    <span id="chat-unread-badge" style="background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 11px; margin-left: 8px; display: none;">0</span>
                </div>
                <button id="btn-close-chat" style="background: none; border: none; color: #81b64c; cursor: pointer; font-size: 18px; padding: 0;">✕</button>
            </div>
            
            <!-- Conversation List -->
            <div id="chat-conversation-list" style="flex: 1; overflow-y: auto; background: #1a1a2e; padding: 10px; min-height: 200px; border-bottom: 1px solid #403d39;">
                <p style="color: #888; text-align: center; font-size: 12px; margin: 30px 0;">Loading conversations...</p>
            </div>
            
            <!-- Message View (Hidden by default) -->
            <div id="chat-message-view" style="display: none; flex-direction: column; background: #1a1a2e; flex: 1; min-height: 300px;">
                <div style="background: #312e2b; padding: 12px; border-bottom: 1px solid #403d39; display: flex; justify-content: space-between; align-items: center;">
                    <button id="btn-back-conversations" style="background: none; border: none; color: #81b64c; cursor: pointer; font-size: 14px; padding: 0;">← Back</button>
                    <span id="chat-current-user" style="color: #fff; font-weight: bold; font-size: 13px;"></span>
                    <div style="width: 20px;"></div>
                </div>
                <div id="chat-messages" style="flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px;"></div>
                <div style="display: flex; gap: 5px; padding: 10px; border-top: 1px solid #403d39; background: #0a0a0e;">
                    <input type="text" id="chat-message-input" placeholder="Type a message..." style="flex: 1; background: #000; color: #fff; border: 1px solid #403d39; border-radius: 5px; padding: 8px; font-size: 12px; outline: none;">
                    <button id="btn-send-message" style="background: #81b64c; color: black; border: none; border-radius: 5px; padding: 8px 12px; cursor: pointer; font-weight: bold; font-size: 12px;">Send</button>
                </div>
            </div>
            
            <!-- Chat Toggle Button (when minimized) -->
            <div id="chat-minimized" style="display: none; padding: 15px; text-align: center; cursor: pointer; background: #1a1a2e;">
                <span style="color: #81b64c; font-weight: bold;">💬 Click to View Messages</span>
            </div>
        </div>
        `;
        document.body.insertAdjacentHTML('beforeend', chatHTML);
    }

    setupEventListeners() {
        document.getElementById('btn-close-chat').addEventListener('click', () => this.toggleChat());
        document.getElementById('btn-back-conversations').addEventListener('click', () => this.showConversationList());
        document.getElementById('btn-send-message').addEventListener('click', () => this.sendMessage());
        
        const input = document.getElementById('chat-message-input');
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });
    }

    toggleChat() {
        const container = document.getElementById('live-chat-container');
        this.chatVisible = !this.chatVisible;
        
        if (this.chatVisible) {
            container.style.display = 'flex';
            this.showConversationList();
        } else {
            container.style.display = 'none';
        }
    }

    loadConversations() {
        fetch('/boringlife/chess/api.php?action=get_conversations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.conversations) {
                this.conversations = data.conversations;
                this.updateUnreadBadge();
                this.showConversationList();
            }
        })
        .catch(err => console.log('Error loading conversations:', err));
    }

    showConversationList() {
        const list = document.getElementById('chat-conversation-list');
        const messageView = document.getElementById('chat-message-view');
        
        messageView.style.display = 'none';
        list.style.display = 'block';
        
        if (this.conversations.length === 0) {
            list.innerHTML = '<p style="color: #888; text-align: center; font-size: 12px; margin: 30px 0;">No conversations yet</p>';
            return;
        }
        
        let html = '';
        this.conversations.forEach(conv => {
            const unread = conv.unread_count ? `<span style="background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; margin-left: 5px;">${conv.unread_count}</span>` : '';
            html += `
                <div onclick="liveChat.openConversation('${conv.other_user}')" style="padding: 12px; background: #262421; border-bottom: 1px solid #403d39; cursor: pointer; border-left: 3px solid ${conv.unread_count ? '#ef4444' : '#666'}; transition: background 0.2s;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: #fff; font-weight: bold; font-size: 13px;">${escapeHtml(conv.other_user)}</span>
                        ${unread}
                    </div>
                    <p style="color: #888; font-size: 11px; margin: 5px 0 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(conv.last_message || 'No messages')}</p>
                </div>
            `;
        });
        list.innerHTML = html;
    }

    openConversation(username) {
        this.currentConversation = username;
        this.lastMessageId[username] = 0;
        
        const list = document.getElementById('chat-conversation-list');
        const messageView = document.getElementById('chat-message-view');
        const input = document.getElementById('chat-message-input');
        
        list.style.display = 'none';
        messageView.style.display = 'flex';
        
        document.getElementById('chat-current-user').innerText = username;
        document.getElementById('chat-messages').innerHTML = '<p style="color: #888; text-align: center; font-size: 12px; margin: 30px 0;">Loading messages...</p>';
        input.value = '';
        input.focus();
        
        // Mark messages as read
        fetch('/boringlife/chess/api.php?action=mark_as_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ from_user: username })
        }).catch(() => {});
        
        // Load messages
        this.loadMessages(username);
    }

    loadMessages(username) {
        fetch('/boringlife/chess/api.php?action=get_direct_messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                with_user: username,
                last_id: this.lastMessageId[username] || 0
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.messages) {
                const messagesDiv = document.getElementById('chat-messages');
                
                if (!this.lastMessageId[username]) {
                    messagesDiv.innerHTML = '';
                }
                
                data.messages.forEach(msg => {
                    this.lastMessageId[username] = msg.id;
                    this.addMessageToView(msg);
                });
                
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        })
        .catch(err => console.log('Error loading messages:', err));
    }

    addMessageToView(msg) {
        const messagesDiv = document.getElementById('chat-messages');
        const placeholder = messagesDiv.querySelector('p');
        if (placeholder) placeholder.remove();
        
        // Remove duplicates
        if (document.querySelector(`[data-msg-id="${msg.id}"]`)) return;
        
        const isSent = msg.from_username === this.getCurrentUsername();
        const msgEl = document.createElement('div');
        msgEl.dataset.msgId = msg.id;
        msgEl.style.cssText = `
            margin-bottom: 8px;
            padding: 8px 12px;
            background: ${isSent ? '#1a3a1a' : '#3a1a1a'};
            border-radius: 8px;
            border-left: 3px solid ${isSent ? '#81b64c' : '#ff9800'};
            align-self: ${isSent ? 'flex-end' : 'flex-start'};
            max-width: 80%;
            word-wrap: break-word;
        `;
        msgEl.innerHTML = `
            <div style="color: ${isSent ? '#81b64c' : '#ff9800'}; font-weight: bold; font-size: 10px; margin-bottom: 3px;">${isSent ? 'You' : escapeHtml(msg.from_username)}</div>
            <div style="color: #fff; font-size: 12px;">${escapeHtml(msg.message)}</div>
            <div style="color: #666; font-size: 9px; margin-top: 3px;">${new Date(msg.created_at).toLocaleTimeString()}</div>
        `;
        
        document.getElementById('chat-messages').appendChild(msgEl);
        document.getElementById('chat-messages').scrollTop = document.getElementById('chat-messages').scrollHeight;
    }

    sendMessage() {
        if (!this.currentConversation) return;
        
        const input = document.getElementById('chat-message-input');
        const message = input.value.trim();
        
        if (!message) return;
        
        input.value = '';
        input.disabled = true;
        
        fetch('/boringlife/chess/api.php?action=send_direct_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                to_username: this.currentConversation,
                message: message
            })
        })
        .then(r => r.json())
        .then(data => {
            input.disabled = false;
            input.focus();
            if (data.success) {
                this.loadMessages(this.currentConversation);
            } else {
                alert('Error sending message: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Error sending message:', err);
            input.disabled = false;
            input.focus();
        });
    }

    startPolling() {
        this.pollInterval = setInterval(() => {
            if (this.currentConversation) {
                this.loadMessages(this.currentConversation);
            }
            this.loadConversations();
        }, 3000);
    }

    updateUnreadBadge() {
        const unreadCount = this.conversations.reduce((sum, conv) => sum + (conv.unread_count || 0), 0);
        const badge = document.getElementById('chat-unread-badge');
        
        if (unreadCount > 0) {
            badge.innerText = unreadCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }

    getCurrentUsername() {
        const usernameEl = document.getElementById('username');
        if (usernameEl) {
            return usernameEl.value;
        }
        return 'Unknown';
    }

    stop() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    }
}

// Helper function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize chat when page loads
let liveChat = null;
document.addEventListener('DOMContentLoaded', () => {
    // Wait a moment to ensure page is fully loaded
    setTimeout(() => {
        liveChat = new LiveChat();
        liveChat.init();
    }, 500);
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (liveChat) {
        liveChat.stop();
    }
});
