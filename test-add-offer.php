<?php
// test-add-offer.php
session_start();
require_once 'user-functions.php';

$user = get_logged_in_user();

if (!$user) {
    echo "You must be logged in to test. <a href='account.php'>Login here</a>";
    exit;
}

echo "<h2>Test Add Offer</h2>";
echo "Logged in as: " . $user['username'] . "<br><br>";

// Create a test offer
$testOffer = [
    'id' => time(),
    'title' => 'Test Offer from Script',
    'price' => '15.00',
    'description' => 'This is a test offer from the test script',
    'category' => 'Other',
    'seller' => $user['username'],
    'sellerUsername' => $user['username'],
    'image' => 'https://via.placeholder.com/200x150',
    'date' => date('Y-m-d H:i:s')
];

// Read existing offers
$offers = [];
if (file_exists('offers.txt')) {
    $content = file_get_contents('offers.txt');
    if (!empty($content) && $content !== 'a:0:{}') {
        $offers = unserialize($content);
        if ($offers === false) {
            $offers = [];
        }
    }
}

// Add test offer
$offers[] = $testOffer;

// Save back
if (file_put_contents('offers.txt', serialize($offers))) {
    echo "✅ Test offer added successfully!<br>";
    echo "<a href='index.php'>View on homepage</a>";
} else {
    echo "❌ Failed to add test offer";
}

echo "<h3>offers.txt now contains:</h3>";
echo "<pre>";
print_r($offers);
echo "</pre>";
?>