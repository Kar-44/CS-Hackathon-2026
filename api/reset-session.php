<?php
// api/reset-session.php
session_start();
require_once '../user-functions.php';

if (get_logged_in_user()) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
