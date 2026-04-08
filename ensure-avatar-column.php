<?php
// ensure-avatar-column.php
require_once 'Database.php';

echo "<h2>Ensuring avatar column exists and is configured correctly</h2>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if avatar column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'avatar'");
    $avatarColumn = $stmt->fetch();
    
    if (!$avatarColumn) {
        // Add avatar column if it doesn't exist
        $db->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(500) DEFAULT 'https://via.placeholder.com/150'");
        echo "<p style='color:green;'>✅ Added avatar column to users table</p>";
    } else {
        echo "<p style='color:green;'>✅ avatar column already exists</p>";
        
        // Modify column to ensure it can store full paths
        $db->exec("ALTER TABLE users MODIFY COLUMN avatar VARCHAR(500) DEFAULT 'https://via.placeholder.com/150'");
        echo "<p style='color:green;'>✅ Updated avatar column to VARCHAR(500)</p>";
    }
    
    // Check if phone column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
    $phoneColumn = $stmt->fetch();
    
    if (!$phoneColumn) {
        $db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT ''");
        echo "<p style='color:green;'>✅ Added phone column to users table</p>";
    }
    
    echo "<h3>Current users table structure:</h3>";
    $columns = $db->query("DESCRIBE users")->fetchAll();
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='account.php'>Go to Account Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
