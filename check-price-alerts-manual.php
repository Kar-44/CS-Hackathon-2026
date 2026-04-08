<?php
// api/check-price-alerts-manual.php
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/user-functions.php';
require_once dirname(__DIR__) . '/Database.php';

function debug_log($message) {
    $debugFile = dirname(__DIR__) . '/price_alerts_debug.log';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - manual-check: " . $message . "\n", FILE_APPEND);
}

debug_log("=== Manual Price Alert Check ===");

try {
    $db = Database::getInstance()->getConnection();
    
    // Get all active alerts with current offer prices
    $stmt = $db->query("
        SELECT pa.*, o.price as offer_price, o.title as offer_title, u.username
        FROM price_alerts pa
        JOIN offers o ON pa.offer_id = o.id
        JOIN users u ON pa.user_id = u.id
    ");
    
    $alerts = $stmt->fetchAll();
    $triggeredAlerts = [];
    
    foreach ($alerts as $alert) {
        $currentPrice = floatval($alert['offer_price']);
        $targetPrice = floatval($alert['target_price']);
        
        // Check if price dropped below target
        if ($currentPrice <= $targetPrice && !$alert['is_notified']) {
            // Mark as notified
            $updateStmt = $db->prepare("UPDATE price_alerts SET is_notified = 1, current_price = ? WHERE id = ?");
            $updateStmt->execute([$currentPrice, $alert['id']]);
            
            $triggeredAlerts[] = [
                'id' => $alert['id'],
                'username' => $alert['username'],
                'offerId' => $alert['offer_id'],
                'offerTitle' => $alert['offer_title'],
                'targetPrice' => $targetPrice,
                'currentPrice' => $currentPrice
            ];
            
            debug_log("Price alert triggered for user {$alert['username']} on {$alert['offer_title']}");
        }
    }
    
    debug_log("Triggered " . count($triggeredAlerts) . " alerts");
    echo json_encode(['success' => true, 'triggered' => $triggeredAlerts]);
    
} catch (PDOException $e) {
    debug_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>

