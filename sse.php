<?php
// api/sse.php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

session_start();
require_once dirname(__FILE__) . '/../user-functions.php';
require_once dirname(__FILE__) . '/../Database.php';

// Kill all output buffering
while (ob_get_level()) ob_end_clean();
set_time_limit(0);
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

$user = get_logged_in_user();
if (!$user) {
    echo "event: error\ndata: " . json_encode(['message' => 'Not logged in']) . "\n\n";
    flush(); exit;
}

$chatId        = isset($_GET['chatId']) ? (int)$_GET['chatId'] : 0;
$lastTimestamp = isset($_GET['lastTimestamp']) ? (int)$_GET['lastTimestamp'] : 0;

if (!$chatId) {
    echo "event: error\ndata: " . json_encode(['message' => 'No chat ID']) . "\n\n";
    flush(); exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Security: confirm user belongs to this chat
    $authStmt = $db->prepare("
        SELECT c.id FROM chats c
        JOIN users u ON (u.id = c.buyer_id OR u.id = c.seller_id)
        WHERE c.id = ? AND u.username = ? LIMIT 1
    ");
    $authStmt->execute([$chatId, $user['username']]);
    if (!$authStmt->fetch()) {
        echo "event: error\ndata: " . json_encode(['message' => 'Access denied']) . "\n\n";
        flush(); exit;
    }
} catch (Exception $e) {
    echo "event: error\ndata: " . json_encode(['message' => 'Server error']) . "\n\n";
    flush(); exit;
}

// Confirm connection
echo "event: connected\ndata: " . json_encode(['chatId' => $chatId]) . "\n\n";
flush();

$lastDateTime = $lastTimestamp
    ? date('Y-m-d H:i:s', $lastTimestamp)
    : date('Y-m-d H:i:s', 0);

$timeout  = 55;
$start    = time();
$lastPing = time();

try {
    $msgStmt = $db->prepare("
        SELECT m.message, m.sent, u.username AS sender_username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.chat_id = ? AND m.sent > ?
        ORDER BY m.sent ASC
    ");

    while (!connection_aborted() && (time() - $start) < $timeout) {

        // Check for new messages
        $msgStmt->execute([$chatId, $lastDateTime]);
        $rows = $msgStmt->fetchAll();

        if (!empty($rows)) {
            $messages = [];
            foreach ($rows as $row) {
                $messages[] = [
                    'sender' => $row['sender_username'],
                    'text'   => $row['message'],
                    'time'   => strtotime($row['sent']),
                ];
                $lastDateTime = $row['sent'];
            }
            echo "event: message\ndata: " . json_encode(['messages' => $messages]) . "\n\n";
            flush();
        }

        // Check typing status
        try {
            $typingStmt = $db->prepare("
                SELECT username FROM typing_status
                WHERE chat_id = ? AND username != ?
                AND updated_at > DATE_SUB(NOW(), INTERVAL 4 SECOND)
                AND is_typing = 1
            ");
            $typingStmt->execute([$chatId, $user['username']]);
            $typers = array_column($typingStmt->fetchAll(), 'username');
            echo "event: typing\ndata: " . json_encode(['users' => $typers]) . "\n\n";
            flush();
        } catch (Exception $e) { /* typing_status table may not exist yet */ }

        // Heartbeat every 5s
        if (time() - $lastPing >= 5) {
            echo "event: ping\ndata: " . time() . "\n\n";
            flush();
            $lastPing = time();
        }

        usleep(300000); // poll every 300ms
    }

    // Tell browser to reconnect
    echo "event: reconnect\ndata: {}\n\n";
    flush();

} catch (Exception $e) {
    error_log("SSE Error: " . $e->getMessage());
    echo "event: error\ndata: " . json_encode(['message' => 'Server error']) . "\n\n";
    flush();
}
