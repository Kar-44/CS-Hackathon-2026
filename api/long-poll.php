<?php
// api/long-poll.php
header('Content-Type: application/json');
session_start();
require_once '../user-functions.php';
require_once '../chat-functions.php';
require_once '../Database.php';

$user = get_logged_in_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$chatId = $data['chatId'] ?? 0;
$lastTimestamp = $data['lastTimestamp'] ?? 0;

if (!$chatId) {
    echo json_encode(['success' => false, 'message' => 'No chat ID']);
    exit;
}

// Convert timestamp to datetime
$lastDateTime = $lastTimestamp ? date('Y-m-d H:i:s', $lastTimestamp) : date('Y-m-d H:i:s', 0);

// Set timeout to 25 seconds
$timeout = 25;
$start = time();

while (time() - $start < $timeout) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check for new messages
        $stmt = $db->prepare("
            SELECT m.*, u.username as sender_username
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ? AND m.sent > ?
            ORDER BY m.sent ASC
        ");
        $stmt->execute([$chatId, $lastDateTime]);
        $newMessages = $stmt->fetchAll();
        
        if (!empty($newMessages)) {
            // Format messages
            $messages = [];
            $latestTimestamp = $lastTimestamp;
            
            foreach ($newMessages as $msg) {
                $messages[] = [
                    'sender' => $msg['sender_username'],
                    'text' => $msg['message'],
                    'time' => strtotime($msg['sent'])
                ];
                $latestTimestamp = strtotime($msg['sent']);
            }
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'lastTimestamp' => $latestTimestamp
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Long poll error: " . $e->getMessage());
    }
    
    sleep(1);
}

// Timeout - no new messages
echo json_encode(['success' => true, 'messages' => [], 'lastTimestamp' => $lastTimestamp]);
?>
