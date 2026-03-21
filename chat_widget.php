<?php
$is_chat_admin = is_admin();
?>
<style>
/* Chat Widget CSS */
#chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 10000;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
#chat-toggle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4CAF50, #264f36);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.4);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 2px solid rgba(255,255,255,0.1);
}
#chat-toggle:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
}
#chat-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #f44336;
    color: white;
    border-radius: 50%;
    padding: 3px 7px;
    font-size: 11px;
    font-weight: bold;
    display: none;
    border: 2px solid #0f0f1e;
}
#chat-window {
    display: none;
    width: 320px;
    height: 450px;
    background: #1a1a2e;
    border: 1px solid #333;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.6);
    flex-direction: column;
    overflow: hidden;
    position: absolute;
    bottom: 75px;
    right: 0;
    transform-origin: bottom right;
    animation: chatPop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes chatPop {
    0% { transform: scale(0.5); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
#chat-header {
    background: linear-gradient(135deg, #264f36, #1a3324);
    padding: 15px 20px;
    color: white;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
#chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: #0f0f1e;
}
#chat-body::-webkit-scrollbar { width: 6px; }
#chat-body::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
.chat-msg {
    max-width: 85%;
    padding: 10px 14px;
    border-radius: 18px;
    font-size: 13px;
    line-height: 1.4;
    word-wrap: break-word;
}
.msg-mine {
    background: #4CAF50;
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 4px;
    box-shadow: 0 2px 5px rgba(76, 175, 80, 0.2);
}
.msg-theirs {
    background: #262641;
    color: #e0e0e0;
    align-self: flex-start;
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
#chat-input-area {
    display: flex;
    padding: 12px;
    background: #1a1a2e;
    border-top: 1px solid #333;
    gap: 10px;
}
#chat-input {
    flex: 1;
    padding: 10px 15px;
    border-radius: 20px;
    border: 1px solid #444;
    background: #0f0f1e;
    color: white;
    outline: none;
    font-size: 13px;
    transition: border-color 0.2s;
}
#chat-input:focus { border-color: #4CAF50; }
#chat-send {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s, box-shadow 0.2s;
}
#chat-send:hover {
    transform: scale(1.05);
    box-shadow: 0 0 10px rgba(76,175,80,0.4);
}
/* Admin conv list */
.conv-item {
    padding: 12px 15px;
    border-bottom: 1px solid #222;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1a1a2e;
    transition: background 0.2s;
}
.conv-item:hover { background: #262641; }
.conv-name { font-weight: 600; color: #fff; font-size: 14px; }
.conv-preview { font-size: 11px; color: #888; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.conv-unread { background: #f44336; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold; box-shadow: 0 0 5px rgba(244,67,54,0.4); }

#chat-typing-indicator {
    padding: 5px 15px;
    font-size: 11px;
    color: #888;
    font-style: italic;
}
.typing-dots::after {
    content: '.';
    animation: dots 1.5s steps(5, end) infinite;
}
@keyframes dots {
    0%, 20% { color: rgba(0,0,0,0); text-shadow: .25em 0 0 rgba(0,0,0,0), .5em 0 0 rgba(0,0,0,0); }
    40% { color: #888; text-shadow: .25em 0 0 rgba(0,0,0,0), .5em 0 0 rgba(0,0,0,0); }
    60% { text-shadow: .25em 0 0 #888, .5em 0 0 rgba(0,0,0,0); }
    80%, 100% { text-shadow: .25em 0 0 #888, .5em 0 0 #888; }
}
</style>

<div id="chat-widget">
    <div id="chat-toggle" onclick="toggleChat()">
        💬<span id="chat-badge">0</span>
    </div>
    
    <div id="chat-window">
        <div id="chat-header">
            <span id="chat-title">Live Support</span>
            <span style="cursor:pointer; font-size:18px; padding: 0 5px;" onclick="toggleChat()">×</span>
        </div>
        <div id="chat-body"></div>
        <div id="chat-typing-indicator" style="display:none;"><span id="typing-text">Someone</span> is typing<span class="typing-dots"></span></div>
        <div id="chat-input-area" style="display:none;">
            <input type="text" id="chat-input" placeholder="Type a message..." oninput="handleTyping()" onkeypress="if(event.key === 'Enter') sendChat()">
            <button id="chat-send" onclick="sendChat()">➣</button>
        </div>
    </div>
</div>

<script>
const _isAdminChat = <?php echo $is_chat_admin ? 'true' : 'false'; ?>;
let _activeChatUser = 0;
let _lastChatMsgId = 0;
let _isChatOpen = false;

function escapeHtml(unsafe) {
    return (unsafe||'').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function linkifyText(text) {
    let safeText = escapeHtml(text);
    const urlRegex = /(https?:\/\/[^\s"']+)/g;
    return safeText.replace(urlRegex, function(url) {
        return `<a href="${url}" target="_blank" rel="noopener noreferrer" style="color: #64B5F6; text-decoration: underline; font-weight: 600;">${url}</a>`;
    });
}

let _lastTypingSent = 0;

function handleTyping() {
    const now = Date.now();
    if (now - _lastTypingSent > 2000) {
        _lastTypingSent = now;
        const fd = new FormData();
        fd.append('action', 'typing');
        if (_isAdminChat) fd.append('user_id', _activeChatUser);
        fetch('chat_api.php', {method: 'POST', body: fd});
    }
}

function deleteChatMsg(id) {
    if (!confirm('Permanently delete this message?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_msg');
    fd.append('msg_id', id);
    fetch('chat_api.php', {method: 'POST', body: fd}).then(() => loadChatMessages());
}

function toggleChat() {
    _isChatOpen = !_isChatOpen;
    document.getElementById('chat-window').style.display = _isChatOpen ? 'flex' : 'none';
    if (_isChatOpen) {
        document.getElementById('chat-badge').style.display = 'none';
        if (_isAdminChat && _activeChatUser === 0) {
            loadChatConversations();
        } else {
            document.getElementById('chat-input-area').style.display = 'flex';
            loadChatMessages();
        }
    }
}

function loadChatConversations() {
    document.getElementById('chat-title').innerHTML = 'Conversations';
    document.getElementById('chat-input-area').style.display = 'none';
    
    fetch('chat_api.php?action=fetch').then(r=>r.json()).then(d=>{
        const body = document.getElementById('chat-body');
        if (!d.success || !d.conversations.length) {
            body.innerHTML = '<div style="text-align:center; color:#666; margin-top:40px; font-size:13px;">No active conversations</div>';
            return;
        }
        let html = '';
        d.conversations.forEach(c => {
            let unread = c.unread > 0 ? `<div class="conv-unread">${c.unread}</div>` : '';
            html += `
                <div class="conv-item" onclick="openAdminChat(${c.id}, '${escapeHtml(c.username).replace(/'/g, "\\'")}')">
                    <div style="flex:1; overflow:hidden; padding-right:10px;">
                        <div class="conv-name">${escapeHtml(c.username)}</div>
                        <div class="conv-preview">${escapeHtml(c.last_text)}</div>
                    </div>
                    ${unread}
                </div>
            `;
        });
        body.innerHTML = html;
        body.scrollTop = 0;
    });
}

function openAdminChat(uid, uname) {
    if (!_isChatOpen) toggleChat();
    _activeChatUser = uid;
    _lastChatMsgId = 0;
    document.getElementById('chat-title').innerHTML = `<span style="cursor:pointer; margin-right:10px; font-size:16px;" onclick="backToConvs()">⬅</span> ${uname}`;
    document.getElementById('chat-body').innerHTML = '<div style="text-align:center; color:#666; margin-top:20px;">Loading...</div>';
    document.getElementById('chat-input-area').style.display = 'flex';
    loadChatMessages();
}

function startAdminChat(uid, uname) {
    if (!uname) uname = 'User ' + uid;
    openAdminChat(uid, uname);
}

function backToConvs() {
    _activeChatUser = 0;
    loadChatConversations();
}

function loadChatMessages() {
    if (!_isChatOpen) return;
    
    let url = 'chat_api.php?action=fetch&last_id=' + _lastChatMsgId;
    if (_isAdminChat) {
        if (_activeChatUser === 0) return;
        url += '&user_id=' + _activeChatUser;
    }
    
    fetch(url).then(r=>r.json()).then(d=>{
        if (!d.success) return;
        const body = document.getElementById('chat-body');
        
        // Render typing indicator seamlessly
        const ind = document.getElementById('chat-typing-indicator');
        if (d.is_typing) {
            document.getElementById('typing-text').innerText = _isAdminChat ? "User" : "Admin";
            ind.style.display = 'block';
        } else {
            ind.style.display = 'none';
        }
        
        if (!d.messages) return;
        
        let shouldScroll = (body.scrollTop + body.clientHeight) >= (body.scrollHeight - 20) || _lastChatMsgId === 0;
        if (_lastChatMsgId === 0) body.innerHTML = '';

        
        d.messages.forEach(m => {
            if (m.id > _lastChatMsgId) _lastChatMsgId = m.id;
            let isMine = (_isAdminChat && m.sender_type === 'admin') || (!_isAdminChat && m.sender_type === 'user');
            
            let delBtn = _isAdminChat ? `<span style="font-size:10px; cursor:pointer; color:#888; margin: 0 5px;" title="Delete message" onclick="deleteChatMsg(${m.id})">🗑️</span>` : '';
            
            let div = document.createElement('div');
            div.className = 'chat-msg ' + (isMine ? 'msg-mine' : 'msg-theirs');
            div.innerHTML = isMine ? delBtn + linkifyText(m.message) : linkifyText(m.message) + delBtn;
            body.appendChild(div);
        });
        
        if (shouldScroll && d.messages.length > 0) {
            body.scrollTop = body.scrollHeight; 
        }
    });
}

function sendChat() {
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text) return;
    
    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('message', text);
    if (_isAdminChat) fd.append('user_id', _activeChatUser);
    
    input.value = '';
    fetch('chat_api.php', {method: 'POST', body: fd}).then(() => loadChatMessages());
}

function pollChat() {
    if (_isChatOpen) {
        if (_isAdminChat && _activeChatUser === 0) {
            loadChatConversations();
        } else {
            loadChatMessages();
        }
    } else {
        fetch('chat_api.php?action=unread_count').then(r=>r.json()).then(d=>{
            if (d.success && d.count > 0) {
                let b = document.getElementById('chat-badge');
                b.innerText = d.count;
                b.style.display = 'block';
            } else {
                document.getElementById('chat-badge').style.display = 'none';
            }
        });
    }
    setTimeout(pollChat, _isChatOpen ? 1500 : 4000);
}
setTimeout(pollChat, 1500);

</script>
