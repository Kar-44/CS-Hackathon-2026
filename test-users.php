<?php
// test-users.php
require_once 'user-functions.php';

echo "<h2>User Database Test</h2>";

// Check if users.json exists
if (file_exists('users.json')) {
    echo "✅ users.json exists<br>";
    echo "File size: " . filesize('users.json') . " bytes<br>";
    
    // Read raw content
    $content = file_get_contents('users.json');
    echo "<h3>Raw content:</h3>";
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
    
    // Try to load users
    $users = load_users();
    echo "<h3>Loaded users:</h3>";
    echo "<pre>";
    print_r($users);
    echo "</pre>";
    echo "Total users: " . count($users['users']) . "<br>";
    
} else {
    echo "❌ users.json does not exist!<br>";
    
    // Try to create it
    $initialData = ['users' => []];
    if (file_put_contents('users.json', json_encode($initialData, JSON_PRETTY_PRINT))) {
        echo "✅ Created users.json successfully<br>";
        chmod('users.json', 0666);
    } else {
        echo "❌ Failed to create users.json<br>";
    }
}

echo "<h3>Current working directory:</h3>";
echo getcwd() . "<br>";

echo "<h3>File permissions:</h3>";
if (file_exists('users.json')) {
    echo "users.json permissions: " . substr(sprintf('%o', fileperms('users.json')), -4) . "<br>";
}

echo "<p><a href='index.php'>Back to Home</a></p>";
?>