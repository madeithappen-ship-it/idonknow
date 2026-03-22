document.addEventListener('DOMContentLoaded', () => {
    const showAllBtn = document.getElementById('show-all-users-btn');
    const searchResults = document.getElementById('friend-search-results');
    const pendingList = document.getElementById('pending-friends-list');
    const friendsList = document.getElementById('my-friends-list');

    function fetchFriendsData() {
        fetch('api_friends.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list_friends' })
        })
        .then(r => r.json())
        .then(data => {
            if(!data.success) return;

            // Render Pending
            if(data.pending.length === 0) {
                pendingList.innerHTML = '<div style="color: #888; font-style: italic; font-size: 12px;">No pending requests.</div>';
            } else {
                let html = '';
                data.pending.forEach(p => {
                    let avatar = p.avatar_url ? `<img src="${p.avatar_url}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">` : `<div style="width:24px;height:24px;border-radius:50%;background:#0f172a;display:flex;align-items:center;justify-content:center;font-size:10px;border:1px solid #4ade80;">👤</div>`;
                    html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid #334155;">
                        <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" onclick="location.href='profile.php?id=${p.request_id}'">
                            ${avatar} <span style="font-size:13px;color:#fff;">${p.name} (Lv ${p.level})</span>
                        </div>
                        <div style="display:flex;gap:5px;">
                            <button onclick="respondFriend(${p.request_id}, 'accept')" style="background:#10b981;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;padding:3px 6px;">✓</button>
                            <button onclick="respondFriend(${p.request_id}, 'decline')" style="background:#ef4444;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;padding:3px 6px;">✗</button>
                        </div>
                    </div>`;
                });
                pendingList.innerHTML = html;
            }

            // Render Friends
            if(data.friends.length === 0) {
                friendsList.innerHTML = '<div style="color: #888; font-style: italic; font-size: 12px;">No friends added yet.</div>';
            } else {
                let html = '';
                data.friends.forEach(f => {
                    let avatar = f.avatar_url ? `<img src="${f.avatar_url}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">` : `<div style="width:24px;height:24px;border-radius:50%;background:#0f172a;display:flex;align-items:center;justify-content:center;font-size:10px;border:1px solid #4ade80;">👤</div>`;
                    let statusDot = f.online ? '<span style="color:#4ade80;font-size:10px;">●</span>' : '<span style="color:#64748b;font-size:10px;">●</span>';
                    html += `<div style="display:flex;align-items:center;padding:8px;border-bottom:1px solid #334155;gap:8px;cursor:pointer;" onclick="location.href='profile.php?id=${f.id}'">
                        ${statusDot} ${avatar} 
                        <span style="font-size:13px;color:#fff;">${f.name} <span style="color:#94a3b8;font-size:11px;">(Lv ${f.level})</span></span>
                    </div>`;
                });
                friendsList.innerHTML = html;
            }
        }).catch(e=>console.error(e));
    }

    window.respondFriend = function(reqId, responseTarget) {
        fetch('api_friends.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'respond_friend', request_id: reqId, response: responseTarget })
        }).then(() => fetchFriendsData());
    };

    window.addFriend = function(targetId) {
        fetch('api_friends.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add_friend', target_id: targetId })
        }).then(r=>r.json()).then(d => {
            if(d.success) {
                alert("Friend request sent!");
                showAllBtn.click(); // refresh search results
            } else {
                alert(d.error);
            }
        });
    };

    showAllBtn.addEventListener('click', () => {
        searchResults.innerHTML = '<div style="padding:10px;font-size:12px;color:#888;">Loading players...</div>';
        
        fetch('api_friends.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'search_user', query: '' })
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                if(data.results.length === 0) {
                    searchResults.innerHTML = '<div style="padding:10px;font-size:12px;color:#888;">No users found.</div>';
                    return;
                }
                let html = '';
                data.results.forEach(u => {
                    let avatar = u.avatar_url ? `<img src="${u.avatar_url}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">` : `<div style="width:24px;height:24px;border-radius:50%;background:#0f172a;display:flex;align-items:center;justify-content:center;font-size:10px;border:1px solid #4ade80;">👤</div>`;
                    html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid #334155;">
                        <div style="display:flex;align-items:center;gap:8px;cursor:pointer;" onclick="location.href='profile.php?id=${u.id}'">
                            ${avatar} <span style="font-size:13px;color:#fff;">${u.name} (Lv ${u.level})</span>
                        </div>
                        <button onclick="addFriend(${u.id})" style="background:#3b82f6;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;padding:3px 8px;">Add</button>
                    </div>`;
                });
                searchResults.innerHTML = html;
            }
        }).catch(e=>console.error(e));
    });

    // Run initial fetch
    fetchFriendsData();
    setInterval(fetchFriendsData, 5000); // Polling for live friend requests
});
