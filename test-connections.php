<?php
// test-connection.php
require_once 'Database.php';

echo "<h2>Testing Database Connection Class</h2>";

try {
    $db = Database::getInstance()->getConnection();
    echo "<p style='color:green;'> Database connection class working!</p>";

    // Test a simple query
    $result = $db->query("SELECT DATABASE() as db")->fetch();
    echo "<p>Connected to database: <strong>" . $result['db'] . "</strong></p>";

} catch (Exception $e) {
    echo "<p style='color:red;'> Error: " . $e->getMessage() . "</p>";
}
?>
