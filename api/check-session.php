<?php
// api/check-session.php
header('Content-Type: application/json');
session_start();
require_once '../user-functions.php';

$user = get_logged_in_user();
$time_remaining = 900; // Default 15 minutes

if ($user && isset($_SESSION['last_activity'])) {
    $elapsed = time() - $_SESSION['last_activity'];
    $time_remaining = max(0, 900 - $elapsed);
}

echo json_encode([
    'logged_in' => $user ? true : false,
    'time_remaining' => $time_remaining
]);
?>
