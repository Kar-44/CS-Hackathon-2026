<?php
// fix-favorites.php
require_once 'user-functions.php';

$users = load_users();
$fixed = 0;

foreach ($users['users'] as &$user) {
    if (!isset($user['favorites'])) {
        $user['favorites'] = [];
        $fixed++;
        echo "Added favorites array for user: " . $user['username'] . "<br>";
    }
}

if ($fixed > 0) {
    save_users($users);
    echo "<p style='color:green;'>✅ Fixed $fixed users</p>";
} else {
    echo "<p>All users already have favorites array</p>";
}

echo "<p><a href='index.php'>Back to Home</a></p>";
?>