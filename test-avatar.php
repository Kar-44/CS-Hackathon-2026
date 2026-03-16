<?php
// test-avatar.php
require_once 'user-functions.php';

echo "<h2>Avatar Function Test</h2>";

// Test the avatar generator
echo "<h3>Testing avatar generation:</h3>";

$testUsers = ['john', 'mary', 'alex', 'sarah', 'mike'];
foreach ($testUsers as $user) {
    $avatar = generate_ui_avatar($user);
    echo "<p><strong>$user</strong>: <br>";
    echo "<img src='$avatar' style='width:50px; height:50px; border-radius:50%; margin-right:10px;'>";
    echo "<br><small>$avatar</small></p>";
}

// Check current users
echo "<h3>Current users in database:</h3>";
$users = load_users();
foreach ($users['users'] as $user) {
    echo "<p><strong>" . $user['username'] . "</strong>: ";
    echo "<img src='" . $user['profile']['avatar'] . "' style='width:30px; height:30px; border-radius:50%; margin-right:10px;'>";
    echo "<br><small>" . $user['profile']['avatar'] . "</small></p>";
}

echo "<p><a href='fix-avatars.php'>Run fix-avatars.php</a></p>";
?>