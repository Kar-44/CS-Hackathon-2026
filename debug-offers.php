<?php
// debug-offers.php
require_once 'Database.php';
require_once 'user-functions.php';

echo "<h1>🔍 Comprehensive Offer Debug</h1>";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Check database connection
    echo "<h2>1. Database Connection</h2>";
    echo "<p style='color:green;'>✅ Connected successfully</p>";
    
    // 2. Check if offers table exists
    echo "<h2>2. Offers Table</h2>";
    $tables = $db->query("SHOW TABLES LIKE 'offers'")->fetch();
    if ($tables) {
        echo "<p style='color:green;'>✅ offers table exists</p>";
    } else {
        echo "<p style='color:red;'>❌ offers table does NOT exist!</p>";
        exit;
    }
    
    // 3. Count total offers
    echo "<h2>3. Offer Count</h2>";
    $count = $db->query("SELECT COUNT(*) FROM offers")->fetchColumn();
    echo "<p>Total offers in database: <strong>$count</strong></p>";
    
    if ($count == 0) {
        echo "<p style='color:orange;'>⚠️ No offers found in database!</p>";
        echo "<p>You need to add some offers first.</p>";
        echo "<p><a href='add-offer.php'>Add an offer</a></p>";
    } else {
        // 4. Show sample offers
        echo "<h2>4. Sample Offers (first 5)</h2>";
        $offers = $db->query("
            SELECT o.*, u.username as seller, u.id as seller_id
            FROM offers o
            LEFT JOIN users u ON o.seller_id = u.id
            ORDER BY o.id DESC
            LIMIT 5
        ")->fetchAll();
        
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>
                <th>ID</th>
                <th>Title</th>
                <th>Price</th>
                <th>Seller ID</th>
                <th>Seller Username</th>
                <th>Status</th>
                <th>Created</th>
              </tr>";
        
        foreach ($offers as $offer) {
            $status = $offer['status'] ?? 'NULL';
            echo "<tr>";
            echo "<td>" . $offer['id'] . "</td>";
            echo "<td>" . htmlspecialchars($offer['title']) . "</td>";
            echo "<td>€" . $offer['price'] . "</td>";
            echo "<td>" . ($offer['seller_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($offer['seller'] ?? 'NULL') . "</td>";
            echo "<td>" . $status . "</td>";
            echo "<td>" . $offer['created'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 5. Check for status column issues
        echo "<h2>5. Status Column Check</h2>";
        try {
            $statusCheck = $db->query("SELECT status FROM offers LIMIT 1")->fetch();
            echo "<p style='color:green;'>✅ status column exists and is accessible</p>";
        } catch (Exception $e) {
            echo "<p style='color:orange;'>⚠️ status column issue: " . $e->getMessage() . "</p>";
            echo "<p>This might be why offers aren't showing - the query filters by status</p>";
        }
    }
    
    // 6. Test the exact query used in get-offers.php
    echo "<h2>6. Testing get-offers.php Query</h2>";
    
    $testQuery = "
        SELECT o.*, u.username as seller, u.avatar as seller_avatar
        FROM offers o
        JOIN users u ON o.seller_id = u.id
        WHERE (o.status = 'active' OR o.status IS NULL)
        ORDER BY o.created DESC
        LIMIT 10
    ";
    
    try {
        $testStmt = $db->query($testQuery);
        $testResults = $testStmt->fetchAll();
        echo "<p>Query returned: " . count($testResults) . " offers</p>";
        
        if (count($testResults) > 0) {
            echo "<p style='color:green;'>✅ Query is working!</p>";
        } else {
            echo "<p style='color:orange;'>⚠️ Query returned 0 results</p>";
            
            // Try without status filter
            echo "<p>Trying without status filter:</p>";
            $testQuery2 = "
                SELECT o.*, u.username as seller, u.avatar as seller_avatar
                FROM offers o
                JOIN users u ON o.seller_id = u.id
                ORDER BY o.created DESC
                LIMIT 10
            ";
            $testStmt2 = $db->query($testQuery2);
            $testResults2 = $testStmt2->fetchAll();
            echo "<p>Query without status filter returned: " . count($testResults2) . " offers</p>";
            
            if (count($testResults2) > 0) {
                echo "<p style='color:orange;'>⚠️ The status filter is filtering out all offers!</p>";
                echo "<p>Your offers might have a status other than 'active' or NULL</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ Query error: " . $e->getMessage() . "</p>";
    }
    
    // 7. Check users table for seller references
    echo "<h2>7. Users Table Check</h2>";
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<p>Total users: $userCount</p>";
    
    if ($count > 0 && $userCount > 0) {
        // Check if any offers have invalid seller_id
        $invalidOffers = $db->query("
            SELECT COUNT(*) 
            FROM offers o 
            LEFT JOIN users u ON o.seller_id = u.id 
            WHERE u.id IS NULL
        ")->fetchColumn();
        
        if ($invalidOffers > 0) {
            echo "<p style='color:red;'>❌ $invalidOffers offers have invalid seller_id (user doesn't exist)</p>";
        } else {
            echo "<p style='color:green;'>✅ All offers have valid sellers</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
