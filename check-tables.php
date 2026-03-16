<?php
// check-tables.php
require_once 'Database.php';

echo "<h2> Database Tables Check</h2>";

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();
    echo "<p style='color:green;'> Database connection successful!</p>";

    // Check what database we're connected to
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "<p>Connected to database: <strong>" . $dbName . "</strong></p>";

    // Get all tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3> Tables found in database:</h3>";
    if (empty($tables)) {
        echo "<p style='color:orange;'>⚠️ No tables found! Your database is empty.</p>";
        echo "<p>You need to create the tables first.</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            // Get row count for each table
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<li><strong>$table</strong> - $count rows</li>";
        }
        echo "</ul>";
        echo "<p style='color:green;'>✅ Tables are ready!</p>";
    }

    // Show table structures if tables exis
    if (!empty($tables)) {
        echo "<h3>📊 Table Details:</h3>";
        foreach ($tables as $table) {
            echo "<h4>$table</h4>";
            $columns = $db->query("DESCRIBE $table")->fetchAll();
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 20px;'>";
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
        }
    }
    } catch (Exception $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your Database.php configuration.</p>";
}
?>
