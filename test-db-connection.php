<?php
// test-db-connection.php
require_once 'Database.php';
require_once 'user-functions.php';

echo "<h2>🔍 Testing Database Connection</h2>";

try {
    $db = Database::getInstance()->getConnection();
    echo "<p style='color:green;'>✅ Database connected</p>";
    
    // Check tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "<li>$table: $count rows</li>";
    }
    echo "</ul>";
    
    // Test user functions
    echo "<h3>Testing User Functions:</h3>";
    $user = get_logged_in_user();
    if ($user) {
        echo "<p>✅ Logged in as: " . $user['username'] . "</p>";
    } else {
        echo "<p>ℹ️ Not logged in</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
