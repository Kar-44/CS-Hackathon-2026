<?php
// check-login.php
session_start();
require_once 'user-functions.php';

echo "<h2>Login Status Check</h2>";

// Check PHP session
$user = get_logged_in_user();
echo "<h3>PHP Session:</h3>";
if ($user) {
    echo "✅ Logged in as: " . $user['username'] . "<br>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
} else {
    echo "❌ Not logged in via PHP session<br>";
}

// Check localStorage (via JavaScript)
echo "<h3>localStorage (check browser console):</h3>";
echo "<script>
    const user = JSON.parse(localStorage.getItem('currentUser'));
    console.log('localStorage user:', user);
    if (user) {
        document.write('✅ localStorage has user: ' + user.username + '<br>');
    } else {
        document.write('❌ No user in localStorage<br>');
    }
</script>";

echo "<h3>Session ID:</h3>";
echo "Session ID: " . session_id() . "<br>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3><a href='add-offer.php'>Try adding an offer</a></h3>";
?>