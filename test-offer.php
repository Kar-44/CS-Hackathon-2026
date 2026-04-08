<?php
// test-offer.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Offer System Test</h2>";

// Check if offers.txt exists and is writable
$file = 'offers.txt';
echo "<h3>File Check:</h3>";
if (file_exists($file)) {
    echo "✓ offers.txt exists<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($file)), -4) . "<br>";
    
    if (is_writable($file)) {
        echo "✓ offers.txt is writable<br>";
    } else {
        echo "✗ offers.txt is NOT writable<br>";
    }
} else {
    echo "✗ offers.txt does not exist<br>";
    // Try to create it
    if (file_put_contents($file, 'a:0:{}')) {
        echo "✓ Created offers.txt successfully<br>";
    } else {
        echo "✗ Failed to create offers.txt<br>";
    }
}

// Try to write a test offer
echo "<h3>Writing Test Offer:</h3>";
$testOffer = [
    'id' => time(),
    'title' => 'Test Offer',
    'price' => '10.00',
    'description' => 'This is a test offer',
    'category' => 'Other',
    'seller' => 'testuser',
    'sellerUsername' => 'testuser',
    'image' => 'https://via.placeholder.com/200x150',
    'date' => date('Y-m-d H:i:s')
];

// Read existing offers
$offers = [];
if (file_exists($file)) {
    $content = file_get_contents($file);
    if (!empty($content)) {
        $offers = unserialize($content);
        if ($offers === false) {
            echo "✗ Failed to unserialize offers.txt - file may be corrupted<br>";
            $offers = [];
        } else {
            echo "✓ Read " . count($offers) . " existing offers<br>";
        }
    }
}

// Add test offer
$offers[] = $testOffer;

// Save back
$result = file_put_contents($file, serialize($offers));
if ($result !== false) {
    echo "✓ Test offer saved successfully!<br>";
    echo "Wrote $result bytes to offers.txt<br>";
    
    // Verify by reading back
    $newContent = file_get_contents($file);
    $newOffers = unserialize($newContent);
    echo "✓ Now contains " . count($newOffers) . " offers<br>";
} else {
    echo "✗ Failed to save test offer<br>";
}

echo "<h3>Current offers.txt content:</h3>";
echo "<pre>";
print_r($offers);
echo "</pre>";

echo "<h3><a href='index.php'>Go to Homepage</a></h3>";
?>