<?php
// fix-user-sync.php
session_start();
require_once 'user-functions.php';

echo "<h2>User Sync Fix Tool</h2>";

// Check session
$sessionUser = get_logged_in_user();
if (!$sessionUser) {
    echo "<p style='color:red;'>❌ You are not logged in. Please login first.</p>";
    echo "<p><a href='account.php'>Go to Login</a></p>";
    exit;
}

echo "<p>✅ Logged in as: <strong>" . $sessionUser['username'] . "</strong></p>";

// Load database
$users = load_users();
$found = false;
$userData = null;

foreach ($users['users'] as $user) {
    if (strtolower($user['username']) === strtolower($sessionUser['username'])) {
        $found = true;
        $userData = $user;
        break;
    }
}

if (!$found) {
    echo "<p style='color:red;'>❌ User '{$sessionUser['username']}' not found in database!</p>";
    
    // Offer to create the user entry
    echo "<h3>Options:</h3>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='action' value='create'>";
    echo "<button type='submit' style='padding:10px; background:green; color:white; border:none; border-radius:5px; cursor:pointer;'>Create User in Database</button>";
    echo "</form>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create') {
        // Create user entry
        $newUser = [
            'username' => $sessionUser['username'],
            'password' => 'temp_password', // This will be overwritten
            'registered' => date('Y-m-d H:i:s'),
            'profile' => [
                'avatar' => 'https://via.placeholder.com/150',
                'name' => '',
                'email' => $sessionUser['email'] ?? '',
                'phone' => ''
            ],
            'favorites' => []
        ];
        
        $users['users'][] = $newUser;
        if (save_users($users)) {
            echo "<p style='color:green;'>✅ User created successfully!</p>";
            echo "<p><a href='index.php'>Go to Homepage</a></p>";
        }
    }
} else {
    echo "<p style='color:green;'>✅ User found in database!</p>";
    echo "<pre>";
    print_r($userData);
    echo "</pre>";
    
    // Check if favorites array exists
    if (!isset($userData['favorites'])) {
        echo "<p style='color:orange;'>⚠️ User has no favorites array. Fixing...</p>";
        
        // Update user in database
        foreach ($users['users'] as &$u) {
            if ($u['username'] === $sessionUser['username']) {
                $u['favorites'] = [];
                break;
            }
        }
        save_users($users);
        echo "<p style='color:green;'>✅ Favorites array added!</p>";
    }
    
    // Sync session with database
    $_SESSION['user'] = $userData;
    echo "<p style='color:green;'>✅ Session synced with database!</p>";
}

echo "<p><a href='index.php'>Back to Home</a></p>";
?>