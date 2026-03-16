<?php
header('Content-Type: application/json');
session_start();
require_once dirname(__FILE__) . '/../user-functions.php';
require_once dirname(__FILE__) . '/../Database.php';

$user = get_logged_in_user();
if (!$user) { echo json_encode(['success' => false]); exit; }

$data     = json_decode(file_get_contents('php://input'), true);
$chatId   = (int)($data['chatId']   ?? 0);
$isTyping = (bool)($data['isTyping'] ?? false);

if (!$chatId) { echo json_encode(['success' => false]); exit; }

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS typing_status (
            chat_id    INT NOT NULL,
            username   VARCHAR(100) NOT NULL,
            is_typing  TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (chat_id, username)
        )
    ");
    $stmt = $db->prepare("
        INSERT INTO typing_status (chat_id, username, is_typing, updated_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), updated_at = NOW()
    ");
    $stmt->execute([$chatId, $user['username'], $isTyping ? 1 : 0]);
    $db->exec("DELETE FROM typing_status WHERE updated_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
