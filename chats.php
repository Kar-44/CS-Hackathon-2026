<?php
session_start();
require_once 'user-functions.php';
require_once 'Database.php';
$currentUser = get_logged_in_user();
$isAdmin = is_admin();
require_once 'nav-notifications.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Messages - CampusCycle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">
    <script src="websocket-client.js"></script>
    <style>
        .typing-indicator {
            display: flex; gap: 3px; padding: 10px 15px;
            background: #f0f0f0; border-radius: 18px;
            width: fit-content; margin-bottom: 10px;
        }
        .typing-indicator span {
            width: 8px; height: 8px; background: #999;
            border-radius: 50%; animation: typing 1.4s infinite;
        }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.6; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
        .connection-status {
            position: absolute; top: 5px; right: 5px;
            width: 10px; height: 10px; border-radius: 50%;
            background: #4CAF50; box-shadow: 0 0 5px #4CAF50;
        }
        .connection-status.disconnected {
            background: #ff4757; box-shadow: 0 0 5px #ff4757;
        }
        .loading-spinner { text-align: center; padding: 40px; color: #999; }
        .loading-spinner i { font-size: 2rem; margin-bottom: 10px; }
        @keyframes messageAppear {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .message { animation: messageAppear 0.2s ease-out; }
    </style>

    <style>
        /* ===== CRITICAL OVERRIDES ===== */
        .theme-toggle {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
        }
        .theme-btn {
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
            min-height: 36px !important;
            max-width: 36px !important;
            max-height: 36px !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }
        .offer-card {
            border: 2px solid var(--primary) !important;
        }
    </style>
</head>
<body>

    <header>
        <nav>
            <a href="index.php" class="logo">
                <img src="uploads/campus-logo.png" style="height:48px;width:48px;object-fit:contain;vertical-align:middle;margin-right:8px;"> CampusCycle
            </a>
            <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </div>
            <div class="nav-links">
                <a href="favorites.html" class="nav-item"><i class="far fa-heart"></i></a>
                <a href="chats.php" id="chatNavLink" class="nav-item" style="color: var(--primary);"><i class="fas fa-comment-dots"></i><?php nav_badge(); ?></a>
                <a href="account.php" class="nav-item"><i class="far fa-user-circle"></i></a>
                <a href="contact.php" class="nav-item" title="Contact Us"><i class="far fa-envelope"></i></a>
                <a href="add-offer.php" class="nav-item btn-add-offer"><i class="fas fa-plus"></i> Add Offer</a>
                <?php if ($isAdmin): ?>
                    <a href="admin.php" class="nav-item" title="Admin Panel" style="color: var(--primary);"><i class="fas fa-cog"></i> Admin</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div class="favorites-header"><h2>Messages</h2></div>

    <div class="container" style="display: block; max-width: 800px;">
        <div class="chat-tabs" id="chatTabs">
            <button class="chat-tab-btn active" onclick="filterChats('all', this)">All</button>
            <button class="chat-tab-btn" onclick="filterChats('buying', this)">I'm Buying</button>
            <button class="chat-tab-btn" onclick="filterChats('selling', this)">I'm Selling</button>
        </div>
        <div id="chatList">
            <p style="text-align:center; padding:20px; color:#999;">
                <i class="fas fa-spinner fa-spin"></i> Loading conversations...
            </p>
        </div>
    </div>

    <div class="chat-overlay" id="chatOverlay">
        <div class="chat-window">
            <div class="chat-window-header">
                <img src="" id="chatOfferImg" class="header-offer-img">
                <div class="header-info">
                    <h4 id="chatOfferTitle" style="margin-bottom: 2px;">Title</h4>
                    <div style="display:flex; align-items:center; font-size:0.85rem; color:#666; position:relative;">
                        <img src="" id="chatOtherPic" class="header-user-pic">
                        <span id="chatOtherName">User</span>
                        <span id="typingStatus" style="margin-left:8px; font-style:italic; color:var(--primary); display:none;">typing...</span>
                        <span id="connectionStatus" class="connection-status disconnected"></span>
                    </div>
                </div>
                <button class="close-chat-btn" onclick="closeChat()">&times;</button>
            </div>
            <div class="messages-container" id="msgContainer"></div>
            <form class="chat-input-area" id="chatForm">
                <input type="text" class="chat-input" id="msgInput" placeholder="Type a message..." autocomplete="off">
                <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="errorModal">
        <div class="modal-box">
            <div class="modal-icon" style="color:#ff4757;"><i class="fas fa-exclamation-circle"></i></div>
            <h3>Error!</h3>
            <p id="errorMessage">Something went wrong.</p>
            <button class="submit-btn" onclick="document.getElementById('errorModal').style.display='none'" style="margin-top:15px; background:#ff4757;">OK</button>
        </div>
    </div>

    <script>
    // ─── Helpers ───────────────────────────────────────────────────────────────
    // Seed localStorage from PHP session - ensures getCurrent() always works
    // even when navigating directly to chats.php
    <?php if ($currentUser): ?>
    (function() {
        const phpUser = <?php echo json_encode($currentUser); ?>;
        if (phpUser) {
            localStorage.setItem('currentUser', JSON.stringify(phpUser));
        }
    })();
    <?php endif; ?>

    function getCurrent() {
        // Try localStorage first, fall back to PHP session data
        const stored = localStorage.getItem('currentUser');
        if (stored) return JSON.parse(stored);
        <?php if ($currentUser): ?>
        return <?php echo json_encode($currentUser); ?>;
        <?php else: ?>
        return null;
        <?php endif; ?>
    }
    function showError(msg) {
        document.getElementById('errorMessage').textContent = msg;
        document.getElementById('errorModal').style.display = 'flex';
        setTimeout(() => document.getElementById('errorModal').style.display = 'none', 3000);
    }
    function scrollToBottom() {
        const c = document.getElementById('msgContainer');
        c.scrollTop = c.scrollHeight;
    }
    function toggleMobileMenu() {
        document.querySelector('.nav-links').classList.toggle('show');
    }
    document.addEventListener('click', function(e) {
        const nav = document.querySelector('.nav-links');
        const tog = document.querySelector('.mobile-menu-toggle');
        if (nav && tog && !nav.contains(e.target) && !tog.contains(e.target))
            nav.classList.remove('show');
    });

    // ─── State ─────────────────────────────────────────────────────────────────
    let activeFilter        = 'all';
    let currentChatId       = null;
    let allChats            = [];
    let sseSource           = null;   // SSE fallback
    let lastMessageTime     = 0;      // unix timestamp of newest received message
    let refreshInterval     = null;
    let typingTimeout       = null;
    let typingClearTimeout  = null;
    let isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    // ─── WebSocket setup ───────────────────────────────────────────────────────
    const wsClient = new WebSocketClient();
    let wsReady = false;

    (function initWebSocket() {
        const sessionId = document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] || '';
        wsClient.connect(sessionId).then(() => {
            wsReady = true;
        }).catch(() => {
            wsReady = false;
            console.warn('WebSocket unavailable, using SSE fallback');
        });

        wsClient.onConnectionChange(connected => {
            wsReady = connected;
            setConnected(connected);
            if (!connected && currentChatId) openSSE(currentChatId);
        });

        wsClient.onMessage(msg => {
            if (currentChatId) handleIncomingMessages([msg], currentChatId);
        });

        wsClient.onTyping(data => {
            showTypingIndicator([data.username]);
        });
    })();

    // ─── Init ──────────────────────────────────────────────────────────────────
    window.onload = function () {
        const user = getCurrent();
        if (!user) {
            <?php if (!$currentUser): ?>
            document.getElementById('chatList').innerHTML = `
                <div style="text-align:center; padding:40px;">
                    <i class="fas fa-comment-dots" style="font-size:3rem; color:#ccc; margin-bottom:15px;"></i>
                    <h3>Please Login</h3>
                    <p style="color:#666; margin-bottom:20px;">You need to be logged in to view your messages.</p>
                    <a href="account.php" class="btn-add-offer" style="text-decoration:none; display:inline-block;">Go to Login</a>
                </div>`;
            return;
            <?php else: ?>
            // PHP session exists but localStorage empty - reload to resync
            window.location.reload();
            return;
            <?php endif; ?>
        }

        loadChatsFromServer();

        // Auto-open chat from URL param (e.g. chats.php?chat=5)
        const params = new URLSearchParams(window.location.search);
        const chatId = params.get('chat');
        if (chatId) setTimeout(() => openChat(parseInt(chatId)), 900);

        // Refresh chat list periodically so last-message previews stay fresh
        refreshInterval = setInterval(loadChatsFromServer, isMobile ? 20000 : 12000);
    };

    window.onunload = function () {
        if (refreshInterval) clearInterval(refreshInterval);
        closeSSE();
    };

    // ─── SSE connection ────────────────────────────────────────────────────────
    function openSSE(chatId) {
        closeSSE(); // close any previous connection

        const url = `api/sse.php?chatId=${chatId}&lastTimestamp=${lastMessageTime}`;
        sseSource = new EventSource(url);

        sseSource.addEventListener('connected', () => {
            setConnected(true);
        });

        sseSource.addEventListener('message', (e) => {
            const data = JSON.parse(e.data);
            handleIncomingMessages(data.messages, chatId);
        });

        sseSource.addEventListener('typing', (e) => {
            const data = JSON.parse(e.data);
            showTypingIndicator(data.users);
        });

        sseSource.addEventListener('ping', () => {
            // heartbeat — connection is alive
        });

        sseSource.addEventListener('reconnect', () => {
            // Server closed connection after timeout — reconnect immediately
            closeSSE();
            openSSE(chatId);
        });

        sseSource.addEventListener('error', (e) => {
            setConnected(false);
        });

        sseSource.onerror = () => {
            setConnected(false);
            // If SSE keeps failing, start polling fallback
            setTimeout(() => {
                if (currentChatId && sseSource && sseSource.readyState === EventSource.CLOSED) {
                    console.log('SSE failed, switching to polling');
                    pollForMessages();
                }
            }, 3000);
        };
    }

    function closeSSE() {
        if (sseSource) {
            sseSource.close();
            sseSource = null;
        }
        setConnected(false);
    }

    function setConnected(connected) {
        const el = document.getElementById('connectionStatus');
        if (!el) return;
        if (connected) {
            el.classList.remove('disconnected');
        } else {
            el.classList.add('disconnected');
        }
    }

    // ── Polling fallback (used when SSE is unavailable) ──────────────────────
    function pollForMessages() {
        if (!currentChatId) return;
        fetch('api/get-messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatId: currentChatId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.messages) {
                const user = getCurrent();
                const chatIndex = allChats.findIndex(c => c.id === currentChatId);
                if (chatIndex !== -1 && user) {
                    const currentCount = (allChats[chatIndex].messages || []).length;
                    if (data.messages.length > currentCount) {
                        allChats[chatIndex].messages = data.messages;
                        renderMessages(data.messages, user.username);
                    }
                }
            }
        })
        .catch(() => {})
        .finally(() => {
            if (currentChatId && (!sseSource || sseSource.readyState === 2)) {
                setTimeout(pollForMessages, 2000);
            }
        });
    }

    // ─── Incoming message handler ──────────────────────────────────────────────
    function handleIncomingMessages(messages, chatId) {
        if (!messages || messages.length === 0) return;

        const user = getCurrent();
        const chatIndex = allChats.findIndex(c => c.id === chatId);
        if (chatIndex === -1) return;

        messages.forEach(msg => {
            // Avoid duplicates (optimistic messages already rendered)
            if (msg.time > lastMessageTime || (msg.time === lastMessageTime && msg.sender !== user.username)) {
                allChats[chatIndex].messages = allChats[chatIndex].messages || [];
                // Only add if not already shown (optimistic update may have added sender's own)
                const isDuplicate = allChats[chatIndex].messages.some(
                    m => m.time === msg.time && m.sender === msg.sender && m.text === msg.text
                );
                if (!isDuplicate) {
                    allChats[chatIndex].messages.push(msg);
                }
                lastMessageTime = Math.max(lastMessageTime, msg.time);
            }
        });

        allChats[chatIndex].lastMessage  = allChats[chatIndex].messages.at(-1).text;
        allChats[chatIndex].lastUpdated  = lastMessageTime;

        renderMessages(allChats[chatIndex].messages, user.username);
        renderChatList();
    }

    // ─── Typing indicator ──────────────────────────────────────────────────────
    function showTypingIndicator(users) {
        const el = document.getElementById('typingStatus');
        if (!el) return;
        if (users && users.length > 0) {
            el.style.display = 'inline';
            el.textContent   = users[0] + ' is typing...';
            // Auto-clear if SSE stops reporting typing
            if (typingClearTimeout) clearTimeout(typingClearTimeout);
            typingClearTimeout = setTimeout(() => {
                el.style.display = 'none';
            }, 4500);
        } else {
            el.style.display = 'none';
        }
    }

    function sendTypingStatus(isTyping) {
        if (!currentChatId) return;
        if (wsReady) {
            wsClient.sendTyping(currentChatId, isTyping);
        } else {
            fetch('api/typing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ chatId: currentChatId, isTyping })
            }).catch(() => {});
        }
    }

    // ─── Load chat list ────────────────────────────────────────────────────────
    function loadChatsFromServer() {
        const user = getCurrent();
        if (!user) return;
        fetch('api/get-chats.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Preserve locally-loaded messages to avoid wiping them
                    data.chats.forEach(newChat => {
                        const existing = allChats.find(c => c.id === newChat.id);
                        if (existing && existing.messages && existing.messages.length > 0) {
                            newChat.messages = existing.messages;
                        }
                    });
                    allChats = data.chats;
                    filterChats(activeFilter, null);
                }
            })
            .catch(() => {});
    }

    function filterChats(filter, btn) {
        activeFilter = filter;
        document.querySelectorAll('.chat-tab-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        else {
            document.querySelectorAll('.chat-tab-btn').forEach(b => {
                if ((filter === 'all' && b.textContent === 'All') ||
                    b.textContent.toLowerCase().includes(filter)) b.classList.add('active');
            });
        }
        renderChatList();
    }

    function renderChatList() {
        const user = getCurrent();
        if (!user) return;
        const container = document.getElementById('chatList');

        let list = allChats;
        if (activeFilter === 'buying')  list = allChats.filter(c => c.buyer  === user.username);
        if (activeFilter === 'selling') list = allChats.filter(c => c.seller === user.username);

        if (list.length === 0) {
            container.innerHTML = `
                <div style="text-align:center; padding:40px;">
                    <i class="fas fa-comment-dots" style="font-size:3rem; color:#ccc; margin-bottom:15px;"></i>
                    <p style="color:#666;">No messages yet.</p>
                    <a href="index.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:var(--primary); color:white; text-decoration:none; border-radius:8px;">Browse Offers</a>
                </div>`;
            return;
        }

        list.sort((a, b) => b.lastUpdated - a.lastUpdated);
        container.innerHTML = '';

        list.forEach(chat => {
            const isUnread = chat.unreadCount > 0;
            const otherUser = user.username === chat.buyer ? chat.seller : chat.buyer;
            const timeStr = formatTime(chat.lastUpdated);

            const div = document.createElement('div');
            div.className = `chat-item ${isUnread ? 'unread' : 'read'}`;
            div.dataset.chatId = chat.id;
            div.onclick = () => openChat(chat.id);
            div.innerHTML = `
                <img src="${chat.offerImage || 'https://via.placeholder.com/60'}" class="chat-thumb"
                     onerror="this.src='https://via.placeholder.com/60'">
                <div class="chat-info">
                    <div class="chat-details">
                        <span class="chat-title-text">${chat.offerTitle}</span>
                        <span class="last-msg">${chat.lastMessage || 'No messages yet'}</span>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:0.8rem; color:#999;">${timeStr}</span>
                        ${isUnread ? `<span style="display:inline-block; width:8px; height:8px; background:var(--primary); border-radius:50%; margin-left:5px;"></span>` : ''}
                    </div>
                </div>`;
            container.appendChild(div);
        });
    }

    function formatTime(unixTs) {
        const diffMs   = Date.now() - unixTs * 1000;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHrs  = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHrs / 24);
        if (diffMins < 1)  return 'Just now';
        if (diffMins < 60) return diffMins + 'm ago';
        if (diffHrs  < 24) return diffHrs  + 'h ago';
        return diffDays + 'd ago';
    }

    // ─── Open chat ─────────────────────────────────────────────────────────────
    function openChat(chatId) {
        const user = getCurrent();
        if (!user) {
            showError('Please login to chat');
            setTimeout(() => window.location.href = 'account.php', 1500);
            return;
        }

        currentChatId = chatId;
        const chat = allChats.find(c => c.id === chatId);
        if (!chat) return;

        document.getElementById('chatOfferImg').src   = chat.offerImage || 'https://via.placeholder.com/45';
        document.getElementById('chatOfferTitle').innerText = chat.offerTitle || 'Item';
        const other = user.username === chat.buyer ? chat.seller : chat.buyer;
        const otherAvatar = user.username === chat.buyer ? chat.sellerAvatar : chat.buyerAvatar;
        document.getElementById('chatOtherName').innerText = other;
        document.getElementById('chatOtherPic').src = otherAvatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(other)}&background=ff2d7a&color=fff&size=30`;
        document.getElementById('chatOtherPic').onerror = function(){ this.src=`https://ui-avatars.com/api/?name=${encodeURIComponent(other)}&background=ff2d7a&color=fff&size=30`; };
        document.getElementById('typingStatus').style.display = 'none';
        document.getElementById('msgContainer').innerHTML =
            '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading messages...</p></div>';
        document.getElementById('chatOverlay').style.display = 'flex';

        // Load existing messages, then connect via WebSocket (or SSE fallback)
        loadMessages(chatId).then(() => {
            if (wsReady) {
                closeSSE();
                wsClient.subscribeToChat(chatId);
                setConnected(true);
            } else {
                openSSE(chatId);
            }
        });

        setTimeout(() => document.getElementById('msgInput').focus(), 400);

        // Mark as read in local state
        const idx = allChats.findIndex(c => c.id === chatId);
        if (idx !== -1) { allChats[idx].unreadCount = 0; renderChatList(); }
    }

    function loadMessages(chatId) {
        const user = getCurrent();
        if (!user) return Promise.resolve();

        return fetch('api/get-messages.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const idx = allChats.findIndex(c => c.id === chatId);
                if (idx !== -1) {
                    allChats[idx].messages = data.messages;
                    if (data.messages.length > 0) {
                        lastMessageTime = data.messages.at(-1).time;
                    }
                    renderMessages(data.messages, user.username);
                }
            } else {
                showError('Failed to load messages');
            }
        })
        .catch(() => showError('Failed to load messages'));
    }

    // ─── Render messages ────────────────────────────────────────────────────────
    function renderMessages(messages, myUsername) {
        const container = document.getElementById('msgContainer');
        container.innerHTML = '';

        if (!messages || messages.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#999; padding:20px;">No messages yet. Start the conversation!</p>';
            return;
        }

        messages.forEach(msg => {
            const div   = document.createElement('div');
            const isMe  = msg.sender === myUsername;
            div.className = `message ${isMe ? 'sent' : 'received'}`;
            const ts    = new Date(msg.time * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            div.innerHTML = `${msg.text}<span class="msg-time">${ts}</span>`;
            container.appendChild(div);
        });

        scrollToBottom();
    }

    // ─── Close chat ─────────────────────────────────────────────────────────────
    function closeChat() {
        closeSSE();
        document.getElementById('chatOverlay').style.display = 'none';
        sendTypingStatus(false);
        currentChatId   = null;
        lastMessageTime = 0;
    }

    document.querySelector('.chat-overlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeChat();
    });

    // ─── Send message ────────────────────────────────────────────────────────────
    document.getElementById('chatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!currentChatId) return;

        const user = getCurrent();
        if (!user) {
            showError('Please login to send messages');
            setTimeout(() => window.location.href = 'account.php', 1500);
            return;
        }

        const input = document.getElementById('msgInput');
        const text  = input.value.trim();
        if (!text) return;

        // Stop typing indicator immediately
        sendTypingStatus(false);
        if (typingTimeout) clearTimeout(typingTimeout);

        // Optimistic render — show message instantly without waiting for server
        const now      = Math.floor(Date.now() / 1000);
        const tempMsg  = { sender: user.username, text, time: now };
        const chatIdx  = allChats.findIndex(c => c.id === currentChatId);
        if (chatIdx !== -1) {
            allChats[chatIdx].messages = allChats[chatIdx].messages || [];
            allChats[chatIdx].messages.push(tempMsg);
            allChats[chatIdx].lastMessage = text;
            allChats[chatIdx].lastUpdated = now;
            renderMessages(allChats[chatIdx].messages, user.username);
            lastMessageTime = now;
        }
        input.value = '';

        // Send to server
        fetch('api/send-message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chatId: currentChatId, message: text })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                // Roll back the optimistic message
                if (chatIdx !== -1) {
                    allChats[chatIdx].messages.pop();
                    renderMessages(allChats[chatIdx].messages, user.username);
                }
                showError('Failed to send message. Please try again.');
            }
        })
        .catch(() => {
            if (chatIdx !== -1) {
                allChats[chatIdx].messages.pop();
                renderMessages(allChats[chatIdx].messages, user.username);
            }
            showError('Failed to send message. Please try again.');
        });
    });

    // ─── Typing detection ────────────────────────────────────────────────────────
    document.getElementById('msgInput').addEventListener('input', function() {
        if (!currentChatId) return;
        sendTypingStatus(true);
        if (typingTimeout) clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => sendTypingStatus(false), 3000);
    });

    // ─── URL error param ─────────────────────────────────────────────────────────
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error')) {
        showError(decodeURIComponent(urlParams.get('error')));
        window.history.replaceState({}, document.title, 'chats.php');
    }
    </script>

    <!-- Theme Toggle -->
    <script>
    (function() {
        const THEME_KEY = 'campuscycle_theme';
        const themes = {
            light: { icon: '☀️', label: 'Light Mode' },
            dark:  { icon: '🌙', label: 'Dark Mode' },
            'high-contrast': { icon: '◑', label: 'High Contrast' }
        };
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem(THEME_KEY, theme);
            document.querySelectorAll('.theme-btn').forEach(b =>
                b.classList.toggle('active', b.dataset.theme === theme));
        }
        function createThemeToggle() {
            if (document.querySelector('.theme-toggle')) return;
            const wrap = document.createElement('div');
            wrap.className = 'theme-toggle';
            Object.keys(themes).forEach(t => {
                const btn = document.createElement('button');
                btn.className = `theme-btn ${t}`;
                btn.dataset.theme = t;
                btn.title = themes[t].label;
                btn.innerHTML = `<i class="fas ${themes[t].icon}"></i>`;
                btn.addEventListener('click', () => setTheme(t));
                wrap.appendChild(btn);
            });
            document.body.appendChild(wrap);
            setTheme(localStorage.getItem(THEME_KEY) || 'light');
        }
        document.readyState === 'loading'
            ? document.addEventListener('DOMContentLoaded', createThemeToggle)
            : createThemeToggle();
    })();
    </script>
</body>
</html>
