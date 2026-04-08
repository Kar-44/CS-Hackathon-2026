<?php
// migrate-data.php
require_once 'Database.php';
require_once 'user-functions-db.php'; // We'll create this next if needed

echo "<!DOCTYPE html>
<html>
<head>
    <title>Data Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
        .section { margin: 20px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>📊 CampusCycle Data Migration</h1>";

try {
    $db = Database::getInstance()->getConnection();
    echo "<p class='success'>✅ Database connection successful!</p>";
    
    // Start migration
    echo "<div class='section'>";
    echo "<h2>Starting Migration...</h2>";
    
    // ============================================
    // 1. Migrate Users
    // ============================================
    echo "<h3>📁 1. Migrating Users</h3>";
    
    if (file_exists('users.json')) {
        $usersData = json_decode(file_get_contents('users.json'), true);
        $userCount = 0;
        $userSkipped = 0;
        $userErrors = 0;
        
        echo "<p>Found " . count($usersData['users']) . " users in users.json</p>";
        
        foreach ($usersData['users'] as $user) {
            try {
                // Check if user already exists
                $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->execute([$user['username']]);
                
                if (!$checkStmt->fetch()) {
                    // Insert user
                    $stmt = $db->prepare("
                        INSERT INTO users (
                            username, email, password, full_name, avatar, registered, is_admin
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $isAdmin = isset($user['isAdmin']) && $user['isAdmin'] ? 1 : 0;
                    $fullName = $user['profile']['name'] ?? '';
                    $avatar = $user['profile']['avatar'] ?? generate_ui_avatar($user['username'], $fullName);
                    
                    $stmt->execute([
                        $user['username'],
                        $user['email'] ?? '',
                        $user['password'] ?? '', // Password is already hashed
                        $fullName,
                        $avatar,
                        $user['registered'] ?? date('Y-m-d H:i:s'),
                        $isAdmin
                    ]);
                    
                    $userId = $db->lastInsertId();
                    echo "<p class='success'>✅ Migrated user: {$user['username']} (ID: $userId)</p>";
                    $userCount++;
                    
                    // Migrate favorites for this user
                    if (!empty($user['favorites'])) {
                        $favCount = 0;
                        foreach ($user['favorites'] as $offerId) {
                            try {
                                $favStmt = $db->prepare("
                                    INSERT INTO favorites (user_id, offer_id, created)
                                    VALUES (?, ?, NOW())
                                ");
                                $favStmt->execute([$userId, $offerId]);
                                $favCount++;
                            } catch (Exception $e) {
                                // Skip if favorite already exists
                            }
                        }
                        if ($favCount > 0) {
                            echo "<p class='info'>   Added $favCount favorites for {$user['username']}</p>";
                        }
                    }
                } else {
                    echo "<p class='warning'>⏭️ User already exists: {$user['username']}</p>";
                    $userSkipped++;
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Failed to migrate user {$user['username']}: " . $e->getMessage() . "</p>";
                $userErrors++;
            }
        }
        
        echo "<p><strong>Users migration complete:</strong> $userCount added, $userSkipped skipped, $userErrors errors</p>";
    } else {
        echo "<p class='warning'>⚠️ No users.json file found</p>";
    }
    
    // ============================================
    // 2. Migrate Offers
    // ============================================
    echo "<h3>📁 2. Migrating Offers</h3>";
    
    if (file_exists('offers.txt')) {
        $content = file_get_contents('offers.txt');
        $offers = unserialize($content);
        $offerCount = 0;
        $offerSkipped = 0;
        $offerErrors = 0;
        
        echo "<p>Found " . count($offers) . " offers in offers.txt</p>";
        
        // First, create a map of usernames to user IDs
        $userMap = [];
        $userStmt = $db->query("SELECT id, username FROM users");
        while ($row = $userStmt->fetch()) {
            $userMap[$row['username']] = $row['id'];
        }
        
        foreach ($offers as $offer) {
            try {
                // Check if seller exists in database
                $sellerUsername = $offer['seller'] ?? $offer['sellerUsername'] ?? null;
                
                if (!$sellerUsername || !isset($userMap[$sellerUsername])) {
                    echo "<p class='warning'>⚠️ Seller '{$sellerUsername}' not found for offer: {$offer['title']}</p>";
                    $offerErrors++;
                    continue;
                }
                
                $sellerId = $userMap[$sellerUsername];
                
                // Check if offer already exists
                $checkStmt = $db->prepare("SELECT id FROM offers WHERE id = ?");
                $checkStmt->execute([$offer['id']]);
                
                if (!$checkStmt->fetch()) {
                    $stmt = $db->prepare("
                        INSERT INTO offers (
                            id, title, description, price, category, seller_id, image, created, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    
                    $stmt->execute([
                        $offer['id'],
                        $offer['title'],
                        $offer['description'] ?? '',
                        $offer['price'],
                        $offer['category'] ?? 'Other',
                        $sellerId,
                        $offer['image'] ?? 'https://via.placeholder.com/200x150',
                        $offer['date'] ?? date('Y-m-d H:i:s')
                    ]);
                    
                    echo "<p class='success'>✅ Migrated offer: {$offer['title']} (ID: {$offer['id']})</p>";
                    $offerCount++;
                } else {
                    echo "<p class='warning'>⏭️ Offer already exists: {$offer['title']}</p>";
                    $offerSkipped++;
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Failed to migrate offer {$offer['title']}: " . $e->getMessage() . "</p>";
                $offerErrors++;
            }
        }
        
        echo "<p><strong>Offers migration complete:</strong> $offerCount added, $offerSkipped skipped, $offerErrors errors</p>";
    } else {
        echo "<p class='warning'>⚠️ No offers.txt file found</p>";
    }
    
    // ============================================
    // 3. Migrate Chats
    // ============================================
    echo "<h3>📁 3. Migrating Chats</h3>";
    
    if (file_exists('chats.json')) {
        $chatsData = json_decode(file_get_contents('chats.json'), true);
        $chatCount = 0;
        $messageCount = 0;
        $chatErrors = 0;
        
        echo "<p>Found " . count($chatsData['chats'] ?? []) . " chats in chats.json</p>";
        
        // Create user map if not already done
        if (empty($userMap)) {
            $userStmt = $db->query("SELECT id, username FROM users");
            while ($row = $userStmt->fetch()) {
                $userMap[$row['username']] = $row['id'];
            }
        }
        
        foreach ($chatsData['chats'] as $chat) {
            try {
                // Get buyer ID
                $buyerId = $userMap[$chat['buyer']] ?? null;
                // Get seller ID
                $sellerId = $userMap[$chat['seller']] ?? null;
                
                if (!$buyerId || !$sellerId) {
                    echo "<p class='warning'>⚠️ Chat participants not found for chat ID: {$chat['id']}</p>";
                    $chatErrors++;
                    continue;
                }
                
                // Check if chat already exists
                $checkStmt = $db->prepare("SELECT id FROM chats WHERE id = ?");
                $checkStmt->execute([$chat['id']]);
                
                if (!$checkStmt->fetch()) {
                    // Insert chat
                    $chatStmt = $db->prepare("
                        INSERT INTO chats (id, offer_id, buyer_id, seller_id, created, last_message_time)
                        VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))
                    ");
                    
                    $lastUpdated = $chat['lastUpdated'] ?? time();
                    
                    $chatStmt->execute([
                        $chat['id'],
                        $chat['offerId'],
                        $buyerId,
                        $sellerId,
                        $lastUpdated,
                        $lastUpdated
                    ]);
                    
                    echo "<p class='success'>✅ Migrated chat ID: {$chat['id']}</p>";
                    $chatCount++;
                    
                    // Migrate messages
                    if (!empty($chat['messages'])) {
                        $msgCount = 0;
                        foreach ($chat['messages'] as $msg) {
                            $senderId = $userMap[$msg['sender']] ?? null;
                            
                            if ($senderId) {
                                $msgStmt = $db->prepare("
                                    INSERT INTO messages (chat_id, sender_id, message, sent)
                                    VALUES (?, ?, ?, FROM_UNIXTIME(?))
                                ");
                                $msgStmt->execute([
                                    $chat['id'],
                                    $senderId,
                                    $msg['text'],
                                    $msg['time']
                                ]);
                                $msgCount++;
                            }
                        }
                        $messageCount += $msgCount;
                        echo "<p class='info'>   Added $msgCount messages</p>";
                    }
                } else {
                    echo "<p class='warning'>⏭️ Chat already exists: {$chat['id']}</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Failed to migrate chat: " . $e->getMessage() . "</p>";
                $chatErrors++;
            }
        }
        
        echo "<p><strong>Chats migration complete:</strong> $chatCount chats added, $messageCount messages, $chatErrors errors</p>";
    } else {
        echo "<p class='warning'>⚠️ No chats.json file found</p>";
    }
    
    // ============================================
    // 4. Migrate Price Alerts
    // ============================================
    echo "<h3>📁 4. Migrating Price Alerts</h3>";
    
    if (file_exists('price_alerts.json')) {
        $alerts = json_decode(file_get_contents('price_alerts.json'), true);
        $alertCount = 0;
        $alertErrors = 0;
        
        echo "<p>Found " . count($alerts) . " price alerts in price_alerts.json</p>";
        
        foreach ($alerts as $alert) {
            try {
                // Get user ID
                $userId = $userMap[$alert['username']] ?? null;
                
                if (!$userId) {
                    echo "<p class='warning'>⚠️ User not found for price alert: {$alert['id']}</p>";
                    $alertErrors++;
                    continue;
                }
                
                // Check if alert already exists
                $checkStmt = $db->prepare("SELECT id FROM price_alerts WHERE id = ?");
                $checkStmt->execute([$alert['id']]);
                
                if (!$checkStmt->fetch()) {
                    $stmt = $db->prepare("
                        INSERT INTO price_alerts (
                            id, user_id, offer_id, target_price, current_price, is_notified, created
                        ) VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))
                    ");
                    
                    $stmt->execute([
                        $alert['id'],
                        $userId,
                        $alert['offerId'],
                        $alert['targetPrice'],
                        $alert['currentPrice'] ?? $alert['targetPrice'],
                        $alert['notified'] ? 1 : 0,
                        $alert['created'] ?? time()
                    ]);
                    
                    echo "<p class='success'>✅ Migrated price alert ID: {$alert['id']}</p>";
                    $alertCount++;
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Failed to migrate price alert: " . $e->getMessage() . "</p>";
                $alertErrors++;
            }
        }
        
        echo "<p><strong>Price alerts migration complete:</strong> $alertCount added, $alertErrors errors</p>";
    } else {
        echo "<p class='warning'>⚠️ No price_alerts.json file found</p>";
    }
    
    // ============================================
    // Migration Summary
    // ============================================
    echo "</div>";
    echo "<div class='section'>";
    echo "<h2>📊 Migration Summary</h2>";
    
    // Get final counts from database
    $userFinal = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $offerFinal = $db->query("SELECT COUNT(*) FROM offers")->fetchColumn();
    $chatFinal = $db->query("SELECT COUNT(*) FROM chats")->fetchColumn();
    $messageFinal = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $alertFinal = $db->query("SELECT COUNT(*) FROM price_alerts")->fetchColumn();
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr><th>Table</th><th>Records</th></tr>";
    echo "<tr><td>Users</td><td>$userFinal</td></tr>";
    echo "<tr><td>Offers</td><td>$offerFinal</td></tr>";
    echo "<tr><td>Chats</td><td>$chatFinal</td></tr>";
    echo "<tr><td>Messages</td><td>$messageFinal</td></tr>";
    echo "<tr><td>Price Alerts</td><td>$alertFinal</td></tr>";
    echo "</table>";
    
    echo "<p class='success' style='font-size: 1.2em; margin-top: 20px;'>✅ Migration completed successfully!</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2 class='error'>❌ Migration Failed</h2>";
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

// Helper function (in case user-functions-db.php isn't loaded)
if (!function_exists('generate_ui_avatar')) {
    function generate_ui_avatar($username, $fullName = '') {
        $hash = md5($username);
        $color = substr($hash, 0, 6);
        $initials = strtoupper(substr($username, 0, 2));
        return 'https://ui-avatars.com/api/?name=' . urlencode($initials) . 
               '&background=' . $color . '&color=fff&size=150&bold=true&length=2';
    }
}

echo "</body></html>";
?>
