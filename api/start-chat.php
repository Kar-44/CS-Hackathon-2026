<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__FILE__) . '/../user-functions.php';
require_once dirname(__FILE__) . '/../chat-functions.php';
require_once dirname(__FILE__) . '/../Database.php';

$user = get_logged_in_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data           = json_decode(file_get_contents('php://input'), true);
$offerId        = (int)($data['offerId'] ?? 0);
$sellerUsername = trim($data['seller'] ?? '');

if (!$offerId) {
    echo json_encode(['success' => false, 'message' => 'No offer ID provided']);
    exit;
}

if (!$sellerUsername) {
    echo json_encode(['success' => false, 'message' => 'No seller provided']);
    exit;
}

if ($sellerUsername === $user['username']) {
    echo json_encode(['success' => false, 'message' => 'Cannot chat with yourself']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Find the offer - accept any status
    $stmt = $db->prepare("
        SELECT o.id, o.title, u.username AS seller_username
        FROM offers o
        JOIN users u ON o.seller_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$offerId]);
    $offer = $stmt->fetch();

    if (!$offer) {
        echo json_encode(['success' => false, 'message' => 'Offer not found']);
        exit;
    }

    $result = create_chat($offerId, $user['username'], $offer['seller_username']);
    echo json_encode($result);

} catch (Exception $e) {
    error_log("start-chat error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
