<?php
// check-offers.php
echo "<h2>Offers Check</h2>";

$offersFile = 'offers.txt';
if (file_exists($offersFile)) {
    echo "✅ offers.txt exists<br>";
    $content = file_get_contents($offersFile);
    echo "Content: " . htmlspecialchars($content) . "<br><br>";
    
    if (!empty($content) && $content !== 'a:0:{}') {
        $offers = unserialize($content);
        if ($offers !== false) {
            echo "<h3>Offers in system:</h3>";
            echo "<pre>";
            print_r($offers);
            echo "</pre>";
            
            echo "<h3>Offer IDs:</h3>";
            foreach ($offers as $offer) {
                echo "Offer ID: " . $offer['id'] . " - " . $offer['title'] . " (Seller: " . $offer['seller'] . ")<br>";
            }
        } else {
            echo "❌ Failed to unserialize offers.txt<br>";
        }
    } else {
        echo "❌ offers.txt is empty<br>";
    }
} else {
    echo "❌ offers.txt not found<br>";
}

echo "<h3><a href='index.php'>Back to Home</a></h3>";
?>