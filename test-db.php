<?php
// test-db.php
$host = 'localhost';
$dbname = 'campuscycle';
$username = 'campususer';
$password = 'shebang'; // Use the password you set above

echo "<h2>Testing Database Connection</h2>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p style='color:green;'> Connected to database successfully!</p>";

    // Check if tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>Tables in database:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

} catch(PDOException $e) {
    echo "<p style='color:red;'> Connection failed: " . $e->getMessage() . "</p>";
}
?>

