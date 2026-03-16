<?php
// debug-avatar-upload.php
session_start();
require_once 'user-functions.php';
require_once 'Database.php';

$user = get_logged_in_user();
if (!$user) {
    die("Please login first");
}

echo "<h1>🔍 Avatar Upload Debugger</h1>";
echo "<p>User: <strong>" . $user['username'] . "</strong> (ID: " . $user['id'] . ")</p>";

// Test 1: Check upload directory
echo "<h2>Test 1: Upload Directory</h2>";
$uploadDir = 'uploads/avatars/';
if (!file_exists($uploadDir)) {
    echo "❌ Directory doesn't exist. Creating...<br>";
    if (mkdir($uploadDir, 0777, true)) {
        echo "✅ Created directory: " . realpath($uploadDir) . "<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
} else {
    echo "✅ Directory exists: " . realpath($uploadDir) . "<br>";
}
echo "Permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";
echo "Writable: " . (is_writable($uploadDir) ? '✅ Yes' : '❌ No') . "<br>";

// Test 2: Check current avatar in database
echo "<h2>Test 2: Current Avatar in Database</h2>";
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT avatar FROM users WHERE username = ?");
    $stmt->execute([$user['username']]);
    $dbAvatar = $stmt->fetchColumn();
    echo "Database avatar: " . ($dbAvatar ?: 'NULL/empty') . "<br>";
    
    if ($dbAvatar && file_exists($dbAvatar)) {
        echo "✅ File exists: " . realpath($dbAvatar) . "<br>";
        echo "<img src='$dbAvatar' style='max-width:100px; border-radius:50%;'><br>";
    } else if ($dbAvatar) {
        echo "❌ File does not exist: $dbAvatar<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 3: Manual upload form
echo "<h2>Test 3: Manual Upload Test</h2>";
echo '<form method="POST" enctype="multipart/form-data">';
echo '<input type="file" name="test_avatar" accept="image/*">';
echo '<button type="submit" name="upload_test">Test Upload</button>';
echo '</form>';

if (isset($_POST['upload_test']) && isset($_FILES['test_avatar'])) {
    echo "<h3>Upload Result:</h3>";
    
    if ($_FILES['test_avatar']['error'] !== UPLOAD_ERR_OK) {
        echo "❌ Upload error: " . $_FILES['test_avatar']['error'] . "<br>";
    } else {
        $tmpFile = $_FILES['test_avatar']['tmp_name'];
        $origName = $_FILES['test_avatar']['name'];
        $fileSize = $_FILES['test_avatar']['size'];
        $fileType = $_FILES['test_avatar']['type'];
        
        echo "Original name: $origName<br>";
        echo "Temporary file: $tmpFile<br>";
        echo "File size: $fileSize bytes<br>";
        echo "File type: $fileType<br>";
        
        // Try to move the file
        $extension = pathinfo($origName, PATHINFO_EXTENSION);
        $newFilename = $user['username'] . '_test_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $newFilename;
        
        if (move_uploaded_file($tmpFile, $targetPath)) {
            echo "✅ File moved to: $targetPath<br>";
            echo "File exists: " . (file_exists($targetPath) ? '✅ Yes' : '❌ No') . "<br>";
            echo "<img src='$targetPath' style='max-width:100px; border-radius:50%;'><br>";
            
            // Try to update database
            try {
                $updateStmt = $db->prepare("UPDATE users SET avatar = ? WHERE username = ?");
                $updateStmt->execute([$targetPath, $user['username']]);
                echo "✅ Database updated with: $targetPath<br>";
                
                // Verify update
                $checkStmt = $db->prepare("SELECT avatar FROM users WHERE username = ?");
                $checkStmt->execute([$user['username']]);
                $newAvatar = $checkStmt->fetchColumn();
                echo "Database now has: " . ($newAvatar ?: 'NULL') . "<br>";
                
                // Update session
                $user['profile']['avatar'] = $targetPath;
                $_SESSION['user'] = $user;
                echo "✅ Session updated<br>";
                
            } catch (Exception $e) {
                echo "❌ Database update failed: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "❌ Failed to move uploaded file<br>";
            echo "Target path: $targetPath<br>";
            echo "Upload dir writable: " . (is_writable($uploadDir) ? 'Yes' : 'No') . "<br>";
        }
    }
}

// Test 4: Check session after update
echo "<h2>Test 4: Current Session Data</h2>";
echo "<pre>";
print_r($_SESSION['user']['profile']);
echo "</pre>";

echo "<p><a href='account.php'>Back to Account</a></p>";
?>
