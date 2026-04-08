<?php
// test-api.php
echo "<h2>API Test</h2>";

$url = 'http://' . $_SERVER['HTTP_HOST'] . '/get-offers.php?page=1';
echo "Fetching from: $url<br><br>";

$json = file_get_contents($url);
$data = json_decode($json, true);

echo "<h3>Response:</h3>";
echo "<pre>";
print_r($data);
echo "</pre>";

if (isset($data['offers']) && count($data['offers']) > 0) {
    echo "<p style='color:green;'>✅ API returned " . count($data['offers']) . " offers</p>";
} else {
    echo "<p style='color:red;'>❌ API returned no offers</p>";
    if (isset($data['pagination'])) {
        echo "<p>Pagination: " . print_r($data['pagination'], true) . "</p>";
    }
}
?>
