<?php
// fix-avatars.php
require_once 'user-functions.php';

echo "<h2>Fixing User Avatars</h2>";

$fixed = fix_existing_avatars();
echo "<p>✅ Updated $fixed users with new UI Avatars!</p>";

echo "<p><a href='index.php'>Back to Home</a></p>";
?>