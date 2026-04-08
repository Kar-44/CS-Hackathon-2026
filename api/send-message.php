<?php
header('Content-Type: application/json');
session_start();
require_once dirname(__FILE__) . '/../user-functions.php';
require_once dirname(__FILE__) . '/../chat-functions.php';
require_once dirname(__FILE__) . '/../Database.php';

$user = get_logged_in_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$chatId  = $data['chatId']  ?? 0;
$message = $data['message'] ?? '';

if (!$chatId || !$message) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$result = send_message($chatId, $user['username'], $message);
echo json_encode($result);
