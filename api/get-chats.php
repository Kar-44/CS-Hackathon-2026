<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__FILE__) . '/../user-functions.php';
require_once dirname(__FILE__) . '/../chat-functions.php';
require_once dirname(__FILE__) . '/../Database.php';

$user = get_logged_in_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in', 'chats' => []]);
    exit;
}

try {
    $chats = get_user_chats($user['username']);
    foreach ($chats as &$chat) {
        $chat['messages'] = [];
    }
    echo json_encode(['success' => true, 'chats' => $chats]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'chats' => []]);
}
