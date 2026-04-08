<?php
// check-users.php
session_start();
require_once 'user-functions.php';

echo "<h2>User Database Check</h2>";

// Check session
$sessionUser = get_logged_in_user();
echo "<h3>Current Session:</h3>";
if ($sessionUser) {
    echo "✅ Logged in as: " . $sessionUser['username'] . "<br>";
    echo "<pre>";
    print_r($sessionUser);
    echo "</pre>";
} else {
    echo "❌ Not logged in<br>";
}

// Check users.json
echo "<h3>Users in database:</h3>";
$users = load_users();
echo "<pre>";
print_r($users);
echo "</pre>";

// Check if session user exists in database
if ($sessionUser) {
    $found = false;
    foreach ($users['users'] as $user) {
        if ($user['username'] === $sessionUser['username']) {
            $found = true;
            echo "<p style='color:green;'>✅ User '{$sessionUser['username']}' found in database</p>";
            
            // Check if favorites array exists
            if (isset($user['favorites'])) {
                echo "<p>Favorites: " . implode(', ', $user['favorites']) . "</p>";
            } else {
                echo "<p style='color:orange;'>⚠️ User has no favorites array</p>";
            }
            break;
        }
    }
    if (!$found) {
        echo "<p style='color:red;'>❌ User '{$sessionUser['username']}' NOT found in database!</p>";
    }
}

echo "<h3><a href='index.php'>Back to Home</a></h3>";
?>