<?php
// chat-functions.php - Database Version
require_once dirname(__FILE__) . '/Database.php';
require_once dirname(__FILE__) . '/user-functions.php';

// Debug function
function chat_log($message) {
    $debugFile = dirname(__FILE__) . '/chat_debug_log.txt';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

/**
 * Get all chats for a user
 */
function get_user_chats($username) {
    chat_log("Getting chats for user: $username");
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get user ID
        $userStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $userStmt->execute([$username]);
        $userId = $userStmt->fetchColumn();
        
        if (!$userId) {
            chat_log("User not found: $username");
            return [];
        }
        
        // Get all chats where user is buyer or seller
        $stmt = $db->prepare("
            SELECT 
                c.id,
                c.offer_id,
                c.buyer_id,
                c.seller_id,
                c.created,
                c.last_message_time,
                o.title as offer_title,
                o.image as offer_image,
                buyer.username as buyer_username,
                buyer.avatar as buyer_avatar,
                seller.username as seller_username,
                seller.avatar as seller_avatar,
                (SELECT message FROM messages WHERE chat_id = c.id ORDER BY sent DESC LIMIT 1) as last_message,
                (SELECT sent FROM messages WHERE chat_id = c.id ORDER BY sent DESC LIMIT 1) as last_sent,
                (SELECT COUNT(*) FROM messages WHERE chat_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
            FROM chats c
            JOIN offers o ON c.offer_id = o.id
            JOIN users buyer ON c.buyer_id = buyer.id
            JOIN users seller ON c.seller_id = seller.id
            WHERE c.buyer_id = ? OR c.seller_id = ?
            ORDER BY COALESCE(c.last_message_time, c.created) DESC
        ");
        
        $stmt->execute([$userId, $userId, $userId]);
        $chats = $stmt->fetchAll();
        
        // Format for frontend compatibility
        $formattedChats = [];
        foreach ($chats as $chat) {
            $lastUpdated = $chat['last_sent'] ? strtotime($chat['last_sent']) : ($chat['last_message_time'] ? strtotime($chat['last_message_time']) : strtotime($chat['created']));
            
            $formattedChat = [
                'id' => (int)$chat['id'],
                'offerId' => (int)$chat['offer_id'],
                'offerTitle' => $chat['offer_title'],
                'offerImage' => $chat['offer_image'] ?: 'https://via.placeholder.com/60',
                'buyer' => $chat['buyer_username'],
                'buyerAvatar' => $chat['buyer_avatar'] ?: '',
                'seller' => $chat['seller_username'],
                'sellerAvatar' => $chat['seller_avatar'] ?: '',
                'lastMessage' => $chat['last_message'] ?: '',
                'lastUpdated' => $lastUpdated,
                'unreadCount' => (int)$chat['unread_count'],
                'messages' => [], // Messages will be loaded separately
                'readBy' => [
                    $chat['buyer_username'] => ($chat['buyer_id'] == $userId) ? true : ((int)$chat['unread_count'] === 0),
                    $chat['seller_username'] => ($chat['seller_id'] == $userId) ? true : ((int)$chat['unread_count'] === 0)
                ]
            ];
            
            $formattedChats[] = $formattedChat;
        }
        
        chat_log("Found " . count($formattedChats) . " chats for user: $username");
        return $formattedChats;
        
    } catch (PDOException $e) {
        chat_log("Database error in get_user_chats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get messages for a specific chat
 */
function get_chat_messages($chatId, $username) {
    chat_log("Getting messages for chat: $chatId, user: $username");
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get user ID
        $userStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $userStmt->execute([$username]);
        $userId = $userStmt->fetchColumn();
        
        // Get messages
        $stmt = $db->prepare("
            SELECT m.*, u.username as sender_username
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ?
            ORDER BY m.sent ASC
        ");
        $stmt->execute([$chatId]);
        $messages = $stmt->fetchAll();
        
        // Mark messages as read
        $updateStmt = $db->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE chat_id = ? AND sender_id != ? AND is_read = 0
        ");
        $updateStmt->execute([$chatId, $userId]);
        
        // Format for frontend
        $formatted = [];
        foreach ($messages as $msg) {
            $formatted[] = [
                'sender' => $msg['sender_username'],
                'text' => $msg['message'],
                'time' => strtotime($msg['sent'])
            ];
        }
        
        chat_log("Found " . count($formatted) . " messages");
        return $formatted;
        
    } catch (PDOException $e) {
        chat_log("Database error in get_chat_messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Send a message
 */
function send_message($chatId, $senderUsername, $message) {
    chat_log("Sending message - Chat: $chatId, Sender: $senderUsername");
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get sender ID
        $userStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $userStmt->execute([$senderUsername]);
        $senderId = $userStmt->fetchColumn();
        
        if (!$senderId) {
            chat_log("Sender not found: $senderUsername");
            return ['success' => false, 'message' => 'Sender not found'];
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO messages (chat_id, sender_id, message, sent)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$chatId, $senderId, $message]);
        
        // Update chat's last_message_time
        $updateStmt = $db->prepare("
            UPDATE chats SET last_message_time = NOW() WHERE id = ?
        ");
        $updateStmt->execute([$chatId]);
        
        chat_log("Message sent successfully");
        return ['success' => true];
        
    } catch (PDOException $e) {
        chat_log("Database error in send_message: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Create a new chat
 */
function create_chat($offerId, $buyerUsername, $sellerUsername) {
    chat_log("Creating chat - Offer: $offerId, Buyer: $buyerUsername, Seller: $sellerUsername");
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get buyer ID
        $buyerStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $buyerStmt->execute([$buyerUsername]);
        $buyerId = $buyerStmt->fetchColumn();
        
        // Get seller ID
        $sellerStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $sellerStmt->execute([$sellerUsername]);
        $sellerId = $sellerStmt->fetchColumn();
        
        if (!$buyerId || !$sellerId) {
            chat_log("User not found - buyer: $buyerId, seller: $sellerId");
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Check if chat already exists
        $checkStmt = $db->prepare("
            SELECT id FROM chats 
            WHERE offer_id = ? AND buyer_id = ? AND seller_id = ?
        ");
        $checkStmt->execute([$offerId, $buyerId, $sellerId]);
        $existingChat = $checkStmt->fetch();
        
        if ($existingChat) {
            chat_log("Chat already exists with ID: " . $existingChat['id']);
            return ['success' => true, 'chatId' => (int)$existingChat['id']];
        }
        
        // Create new chat
        $createStmt = $db->prepare("
            INSERT INTO chats (offer_id, buyer_id, seller_id, created, last_message_time)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $createStmt->execute([$offerId, $buyerId, $sellerId]);
        
        $chatId = $db->lastInsertId();
        chat_log("Created new chat with ID: $chatId");
        
        return ['success' => true, 'chatId' => (int)$chatId];
        
    } catch (PDOException $e) {
        chat_log("Database error in create_chat: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
?>
