// server.js - CampusCycle Backend
const express = require('express');
const Database = require('better-sqlite3');
const cors = require('cors');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

// --- Middleware ---
app.use(cors());
app.use(express.json({ limit: '50mb' })); // Large limit for base64 images
app.use(express.static(path.join(__dirname)));

// --- Database Setup ---
const db = new Database(path.join(__dirname, 'campus.db'));
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

// Create tables
db.exec(`
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        profile_name TEXT DEFAULT '',
        profile_email TEXT DEFAULT '',
        profile_phone TEXT DEFAULT '',
        profile_avatar TEXT DEFAULT 'https://via.placeholder.com/150'
    );

    CREATE TABLE IF NOT EXISTS offers (
        id INTEGER PRIMARY KEY,
        title TEXT NOT NULL,
        price TEXT NOT NULL,
        category TEXT NOT NULL,
        description TEXT DEFAULT '',
        image TEXT DEFAULT '',
        seller_username TEXT NOT NULL,
        seller_name TEXT DEFAULT '',
        time TEXT DEFAULT 'Just now'
    );

    CREATE TABLE IF NOT EXISTS favorites (
        user_id INTEGER NOT NULL,
        offer_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, offer_id)
    );

    CREATE TABLE IF NOT EXISTS chats (
        id TEXT PRIMARY KEY,
        offer_id TEXT NOT NULL,
        buyer TEXT NOT NULL,
        seller_username TEXT NOT NULL,
        seller_name TEXT DEFAULT '',
        seller_avatar TEXT DEFAULT '',
        offer_title TEXT DEFAULT '',
        offer_image TEXT DEFAULT '',
        last_message TEXT DEFAULT '',
        last_updated INTEGER DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id TEXT NOT NULL,
        sender TEXT NOT NULL,
        text TEXT NOT NULL,
        time INTEGER NOT NULL,
        FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS chat_read_status (
        chat_id TEXT NOT NULL,
        username TEXT NOT NULL,
        is_read INTEGER DEFAULT 1,
        PRIMARY KEY (chat_id, username)
    );
`);

// --- Auth Middleware ---
function authenticate(req, res, next) {
    const authHeader = req.headers.authorization;
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
        return res.status(401).json({ error: 'Not authenticated' });
    }

    const token = authHeader.slice(7);
    try {
        const decoded = Buffer.from(token, 'base64').toString('utf-8');
        const [username, password] = decoded.split(':');

        const user = db.prepare('SELECT * FROM users WHERE username = ? AND password = ?').get(username, password);
        if (!user) {
            return res.status(401).json({ error: 'Invalid token' });
        }

        req.user = user;
        next();
    } catch (e) {
        return res.status(401).json({ error: 'Invalid token' });
    }
}

// Helper to format user object for client
function formatUser(user, favorites) {
    return {
        username: user.username,
        email: user.email,
        password: user.password,
        profile: {
            name: user.profile_name || user.username,
            email: user.profile_email || user.email,
            phone: user.profile_phone || '',
            avatar: user.profile_avatar || 'https://via.placeholder.com/150'
        },
        favorites: favorites || []
    };
}

function getUserFavorites(userId) {
    const rows = db.prepare('SELECT offer_id FROM favorites WHERE user_id = ?').all(userId);
    return rows.map(r => r.offer_id);
}

// ===========================
// AUTH ENDPOINTS
// ===========================

// POST /api/register
app.post('/api/register', (req, res) => {
    const { username, email, password } = req.body;

    if (!username || !email || !password) {
        return res.status(400).json({ success: false, message: 'All fields are required' });
    }

    // Check email uniqueness
    const existingEmail = db.prepare('SELECT id FROM users WHERE email = ?').get(email);
    if (existingEmail) {
        return res.status(400).json({ success: false, message: 'Such account has already been used with this email' });
    }

    // Check username uniqueness
    const existingUser = db.prepare('SELECT id FROM users WHERE username = ?').get(username);
    if (existingUser) {
        return res.status(400).json({ success: false, message: 'Username taken' });
    }

    db.prepare(`
        INSERT INTO users (username, email, password, profile_name, profile_email, profile_avatar)
        VALUES (?, ?, ?, ?, ?, 'https://via.placeholder.com/150')
    `).run(username, email, password, username, email);

    return res.json({ success: true, message: 'Registered successfully' });
});

// POST /api/login
app.post('/api/login', (req, res) => {
    const { username, password } = req.body;

    const user = db.prepare('SELECT * FROM users WHERE username = ? AND password = ?').get(username, password);
    if (!user) {
        return res.status(401).json({ success: false, message: 'Invalid credentials' });
    }

    const favorites = getUserFavorites(user.id);
    const token = Buffer.from(`${username}:${password}`).toString('base64');

    return res.json({
        success: true,
        token: token,
        user: formatUser(user, favorites)
    });
});

// GET /api/users - get all users (public profiles for avatar/name lookups)
app.get('/api/users', (req, res) => {
    const users = db.prepare('SELECT username, profile_name, profile_email, profile_phone, profile_avatar FROM users').all();
    const formatted = users.map(u => ({
        username: u.username,
        profile: {
            name: u.profile_name || u.username,
            email: u.profile_email || '',
            phone: u.profile_phone || '',
            avatar: u.profile_avatar || 'https://via.placeholder.com/150'
        }
    }));
    return res.json(formatted);
});

// ===========================
// PROFILE ENDPOINTS
// ===========================

// PUT /api/profile
app.put('/api/profile', authenticate, (req, res) => {
    const { name, email, phone, avatar } = req.body;

    db.prepare(`
        UPDATE users SET profile_name = ?, profile_email = ?, profile_phone = ?, profile_avatar = ?, email = ?
        WHERE id = ?
    `).run(name || '', email || '', phone || '', avatar || '', email || req.user.email, req.user.id);

    const updated = db.prepare('SELECT * FROM users WHERE id = ?').get(req.user.id);
    const favorites = getUserFavorites(req.user.id);
    return res.json({ success: true, user: formatUser(updated, favorites) });
});

// PUT /api/password
app.put('/api/password', authenticate, (req, res) => {
    const { oldPassword, newPassword } = req.body;

    if (req.user.password !== oldPassword) {
        return res.status(400).json({ success: false, message: 'Incorrect old password' });
    }

    db.prepare('UPDATE users SET password = ? WHERE id = ?').run(newPassword, req.user.id);

    // Return new token since password changed
    const newToken = Buffer.from(`${req.user.username}:${newPassword}`).toString('base64');
    return res.json({ success: true, token: newToken });
});

// ===========================
// OFFERS ENDPOINTS
// ===========================

// GET /api/offers
app.get('/api/offers', (req, res) => {
    const offers = db.prepare('SELECT * FROM offers ORDER BY id DESC').all();
    const formatted = offers.map(o => ({
        id: o.id,
        title: o.title,
        price: o.price,
        category: o.category,
        desc: o.description,
        image: o.image,
        sellerUsername: o.seller_username,
        seller: o.seller_name,
        time: o.time
    }));
    return res.json(formatted);
});

// POST /api/offers
app.post('/api/offers', authenticate, (req, res) => {
    const { id, title, price, category, desc, image, seller, time } = req.body;

    const offerId = id || Date.now();

    db.prepare(`
        INSERT INTO offers (id, title, price, category, description, image, seller_username, seller_name, time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(offerId, title, price, category, desc || '', image || '', req.user.username, seller || req.user.profile_name || req.user.username, time || 'Just now');

    return res.json({ success: true, id: offerId });
});

// PUT /api/offers/:id
app.put('/api/offers/:id', authenticate, (req, res) => {
    const { title, price } = req.body;
    const offerId = parseInt(req.params.id);

    // Verify ownership
    const offer = db.prepare('SELECT * FROM offers WHERE id = ? AND seller_username = ?').get(offerId, req.user.username);
    if (!offer) {
        return res.status(404).json({ success: false, message: 'Offer not found or not yours' });
    }

    db.prepare('UPDATE offers SET title = ?, price = ? WHERE id = ?').run(title, price, offerId);
    return res.json({ success: true });
});

// DELETE /api/offers/:id
app.delete('/api/offers/:id', authenticate, (req, res) => {
    const offerId = parseInt(req.params.id);

    // Verify ownership
    const offer = db.prepare('SELECT * FROM offers WHERE id = ? AND seller_username = ?').get(offerId, req.user.username);
    if (!offer) {
        return res.status(404).json({ success: false, message: 'Offer not found or not yours' });
    }

    db.prepare('DELETE FROM offers WHERE id = ?').run(offerId);
    return res.json({ success: true });
});

// ===========================
// FAVORITES ENDPOINTS
// ===========================

// GET /api/favorites
app.get('/api/favorites', authenticate, (req, res) => {
    const favIds = getUserFavorites(req.user.id);
    return res.json(favIds);
});

// POST /api/favorites/:offerId
app.post('/api/favorites/:offerId', authenticate, (req, res) => {
    const offerId = parseInt(req.params.offerId);
    try {
        db.prepare('INSERT OR IGNORE INTO favorites (user_id, offer_id) VALUES (?, ?)').run(req.user.id, offerId);
    } catch (e) { /* ignore duplicate */ }
    const favIds = getUserFavorites(req.user.id);
    return res.json({ success: true, favorites: favIds });
});

// DELETE /api/favorites/:offerId
app.delete('/api/favorites/:offerId', authenticate, (req, res) => {
    const offerId = parseInt(req.params.offerId);
    db.prepare('DELETE FROM favorites WHERE user_id = ? AND offer_id = ?').run(req.user.id, offerId);
    const favIds = getUserFavorites(req.user.id);
    return res.json({ success: true, favorites: favIds });
});

// ===========================
// CHATS ENDPOINTS
// ===========================

// GET /api/chats
app.get('/api/chats', authenticate, (req, res) => {
    const chats = db.prepare(`
        SELECT * FROM chats WHERE buyer = ? OR seller_username = ?
        ORDER BY last_updated DESC
    `).all(req.user.username, req.user.username);

    const result = chats.map(chat => {
        const readRow = db.prepare('SELECT is_read FROM chat_read_status WHERE chat_id = ? AND username = ?')
            .get(chat.id, req.user.username);

        return {
            id: chat.id,
            offerId: chat.offer_id,
            buyer: chat.buyer,
            sellerUsername: chat.seller_username,
            sellerName: chat.seller_name,
            sellerAvatar: chat.seller_avatar,
            offerTitle: chat.offer_title,
            offerImage: chat.offer_image,
            lastMessage: chat.last_message,
            lastUpdated: chat.last_updated,
            readByMe: readRow ? !!readRow.is_read : true
        };
    });

    return res.json(result);
});

// POST /api/chats
app.post('/api/chats', authenticate, (req, res) => {
    const { offerId, sellerUsername, sellerName, sellerAvatar, offerTitle, offerImage } = req.body;

    // Check if chat already exists
    const existing = db.prepare('SELECT * FROM chats WHERE offer_id = ? AND buyer = ?')
        .get(String(offerId), req.user.username);

    if (existing) {
        return res.json({ success: true, chatId: existing.id, existing: true });
    }

    const chatId = 'chat_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    const now = Date.now();

    db.prepare(`
        INSERT INTO chats (id, offer_id, buyer, seller_username, seller_name, seller_avatar, offer_title, offer_image, last_message, last_updated)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Chat started', ?)
    `).run(chatId, String(offerId), req.user.username, sellerUsername || 'Unknown', sellerName || 'Unknown', sellerAvatar || '', offerTitle || '', offerImage || '', now);

    // Set read status
    db.prepare('INSERT OR REPLACE INTO chat_read_status (chat_id, username, is_read) VALUES (?, ?, 1)')
        .run(chatId, req.user.username);
    db.prepare('INSERT OR REPLACE INTO chat_read_status (chat_id, username, is_read) VALUES (?, ?, 0)')
        .run(chatId, sellerUsername);

    return res.json({ success: true, chatId: chatId, existing: false });
});

// GET /api/chats/:id/messages
app.get('/api/chats/:id/messages', authenticate, (req, res) => {
    const chatId = req.params.id;

    // Verify user is part of this chat
    const chat = db.prepare('SELECT * FROM chats WHERE id = ? AND (buyer = ? OR seller_username = ?)')
        .get(chatId, req.user.username, req.user.username);

    if (!chat) {
        return res.status(404).json({ error: 'Chat not found' });
    }

    const messages = db.prepare('SELECT sender, text, time FROM messages WHERE chat_id = ? ORDER BY time ASC')
        .all(chatId);

    return res.json({
        chat: {
            id: chat.id,
            offerId: chat.offer_id,
            buyer: chat.buyer,
            sellerUsername: chat.seller_username,
            sellerName: chat.seller_name,
            sellerAvatar: chat.seller_avatar,
            offerTitle: chat.offer_title,
            offerImage: chat.offer_image
        },
        messages: messages
    });
});

// POST /api/chats/:id/messages
app.post('/api/chats/:id/messages', authenticate, (req, res) => {
    const chatId = req.params.id;
    const { text } = req.body;

    // Verify user is part of this chat
    const chat = db.prepare('SELECT * FROM chats WHERE id = ? AND (buyer = ? OR seller_username = ?)')
        .get(chatId, req.user.username, req.user.username);

    if (!chat) {
        return res.status(404).json({ error: 'Chat not found' });
    }

    const now = Date.now();
    db.prepare('INSERT INTO messages (chat_id, sender, text, time) VALUES (?, ?, ?, ?)')
        .run(chatId, req.user.username, text, now);

    // Update chat metadata
    db.prepare('UPDATE chats SET last_message = ?, last_updated = ? WHERE id = ?')
        .run(text, now, chatId);

    // Update read status
    const otherPerson = (req.user.username === chat.buyer) ? chat.seller_username : chat.buyer;
    db.prepare('INSERT OR REPLACE INTO chat_read_status (chat_id, username, is_read) VALUES (?, ?, 1)')
        .run(chatId, req.user.username);
    db.prepare('INSERT OR REPLACE INTO chat_read_status (chat_id, username, is_read) VALUES (?, ?, 0)')
        .run(chatId, otherPerson);

    return res.json({ success: true, time: now });
});

// PUT /api/chats/:id/read
app.put('/api/chats/:id/read', authenticate, (req, res) => {
    const chatId = req.params.id;
    db.prepare('INSERT OR REPLACE INTO chat_read_status (chat_id, username, is_read) VALUES (?, ?, 1)')
        .run(chatId, req.user.username);
    return res.json({ success: true });
});

// ===========================
// START SERVER
// ===========================
app.listen(PORT, () => {
    console.log(`CampusCycle server running at http://localhost:${PORT}`);
});
