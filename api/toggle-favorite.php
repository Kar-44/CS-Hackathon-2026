<?php
// api/toggle-favorite.php
header('Content-Type: application/json');
require_once '../user-functions.php';

$user = get_logged_in_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$offerId = $data['offerId'] ?? 0;

if (!$offerId) {
    echo json_encode(['success' => false, 'message' => 'No offer ID provided']);
    exit;
}

$result = toggle_favorite($user['username'], $offerId);
echo json_encode($result);
?>
