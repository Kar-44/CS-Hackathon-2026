<?php
header('Content-Type: application/json');
session_start();
require_once dirname(__FILE__) . '/../user-functions.php';
require_once dirname(__FILE__) . '/../chat-functions.php';
require_once dirname(__FILE__) . '/../Database.php';

$user = get_logged_in_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in', 'messages' => []]);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$chatId = $data['chatId'] ?? 0;

if (!$chatId) {
    echo json_encode(['success' => false, 'message' => 'No chat ID', 'messages' => []]);
    exit;
}

$messages = get_chat_messages($chatId, $user['username']);
echo json_encode(['success' => true, 'messages' => $messages]);
