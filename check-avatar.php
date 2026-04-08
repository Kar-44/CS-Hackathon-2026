<?php
// check-avatar.php
session_start();
require_once 'user-functions.php';

$user = get_logged_in_user();

echo "<h2>Avatar Debug Tool</h2>";

if (!$user) {
    echo "<p style='color:red;'>❌ Not logged in</p>";
    echo "<p><a href='account.php'>Login here</a></p>";
    exit;
}

echo "<h3>Current User:</h3>";
echo "<p>Username: " . $user['username'] . "</p>";
echo "<p>Avatar path from session: <strong>" . ($user['profile']['avatar'] ?? 'Not set') . "</strong></p>";

// Check database
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT avatar FROM users WHERE username = ?");
    $stmt->execute([$user['username']]);
    $dbAvatar = $stmt->fetchColumn();
    
    echo "<p>Avatar path from database: <strong>" . ($dbAvatar ?: 'Not set') . "</strong></p>";
    
    // Check if file exists
    if ($dbAvatar && file_exists($dbAvatar)) {
        echo "<p style='color:green;'>✅ File exists on server</p>";
        echo "<p>File path: " . realpath($dbAvatar) . "</p>";
        echo "<p>File size: " . filesize($dbAvatar) . " bytes</p>";
        echo "<p>Preview: <br><img src='$dbAvatar' style='max-width:200px; border:2px solid green; border-radius:10px;'></p>";
    } else if ($dbAvatar) {
        echo "<p style='color:red;'>❌ File does not exist on server: $dbAvatar</p>";
    }
    
    // Check uploads directory
    $uploadDir = 'uploads/avatars/';
    echo "<h3>Uploads Directory:</h3>";
    if (file_exists($uploadDir)) {
        echo "<p>✅ Uploads directory exists</p>";
        echo "<p>Directory path: " . realpath($uploadDir) . "</p>";
        echo "<p>Directory permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "</p>";
        
        // List files in directory
        $files = scandir($uploadDir);
        $avatarFiles = array_diff($files, ['.', '..']);
        
        if (empty($avatarFiles)) {
            echo "<p>No avatar files found</p>";
        } else {
            echo "<p>Avatar files found:</p><ul>";
            foreach ($avatarFiles as $file) {
                $filePath = $uploadDir . $file;
                echo "<li><img src='$filePath' style='width:50px; height:50px; border-radius:50%; margin-right:10px; vertical-align:middle;'> $file (" . round(filesize($filePath)/1024, 2) . " KB)</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color:red;'>❌ Uploads directory does not exist</p>";
        // Try to create it
        if (mkdir($uploadDir, 0777, true)) {
            echo "<p style='color:green;'>✅ Created uploads directory</p>";
        } else {
            echo "<p style='color:red;'>❌ Failed to create uploads directory</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='account.php'>Back to Account</a></p>";
?>
