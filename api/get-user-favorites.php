<?php
// api/get-user-favorites.php
header('Content-Type: application/json');
require_once '../user-functions.php';

$user = get_logged_in_user();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in', 'favorites' => []]);
    exit;
}

$favorites = get_user_favorites($user['username']);
echo json_encode(['success' => true, 'favorites' => $favorites]);
?>
