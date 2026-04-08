<?php
// api/price-alerts.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
require_once dirname(__DIR__) . '/user-functions.php';
require_once dirname(__DIR__) . '/Database.php';

function debug_log($message) {
    $debugFile = dirname(__DIR__) . '/price_alerts_debug.log';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - price-alerts.php: " . $message . "\n", FILE_APPEND);
}

debug_log("=== New Request ===");

$user = get_logged_in_user();
if (!$user) {
    debug_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

debug_log("User: " . $user['username'] . " (ID: " . $user['id'] . ")");

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($method) {
        case 'GET':
            // Get user's price alerts
            $stmt = $db->prepare("
                SELECT pa.*, o.title as offer_title, o.price as current_price, o.image as offer_image
                FROM price_alerts pa
                JOIN offers o ON pa.offer_id = o.id
                WHERE pa.user_id = ?
                ORDER BY pa.created DESC
            ");
            $stmt->execute([$user['id']]);
            $alerts = $stmt->fetchAll();
            
            debug_log("Found " . count($alerts) . " alerts for user");
            echo json_encode(['success' => true, 'alerts' => $alerts]);
            break;
            
        case 'POST':
            // Create a price alert
            $data = json_decode(file_get_contents('php://input'), true);
            $offerId = $data['offerId'] ?? 0;
            $targetPrice = $data['targetPrice'] ?? 0;
            $currentPrice = $data['currentPrice'] ?? 0;
            
            debug_log("Creating alert - Offer: $offerId, Target: $targetPrice, Current: $currentPrice");
            
            if (!$offerId || !$targetPrice) {
                debug_log("Missing required fields");
                echo json_encode(['success' => false, 'message' => 'Missing offer ID or target price']);
                exit;
            }
            
            // Check if alert already exists
            $checkStmt = $db->prepare("SELECT id FROM price_alerts WHERE user_id = ? AND offer_id = ?");
            $checkStmt->execute([$user['id'], $offerId]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                // Update existing alert
                $updateStmt = $db->prepare("
                    UPDATE price_alerts 
                    SET target_price = ?, current_price = ?, is_notified = 0, created = NOW()
                    WHERE user_id = ? AND offer_id = ?
                ");
                $updateStmt->execute([$targetPrice, $currentPrice, $user['id'], $offerId]);
                debug_log("Updated existing alert");
            } else {
                // Insert new alert
                $insertStmt = $db->prepare("
                    INSERT INTO price_alerts (user_id, offer_id, target_price, current_price, created)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insertStmt->execute([$user['id'], $offerId, $targetPrice, $currentPrice]);
                debug_log("Inserted new alert");
            }
            
            echo json_encode(['success' => true, 'message' => 'Price alert saved successfully']);
            break;
            
        case 'DELETE':
            // Delete a price alert
            $data = json_decode(file_get_contents('php://input'), true);
            $alertId = $data['alertId'] ?? '';
            $offerId = $data['offerId'] ?? 0;
            
            debug_log("Deleting alert - ID: $alertId, Offer: $offerId");
            
            if ($alertId) {
                $deleteStmt = $db->prepare("DELETE FROM price_alerts WHERE id = ? AND user_id = ?");
                $deleteStmt->execute([$alertId, $user['id']]);
            } else if ($offerId) {
                $deleteStmt = $db->prepare("DELETE FROM price_alerts WHERE offer_id = ? AND user_id = ?");
                $deleteStmt->execute([$offerId, $user['id']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No alert ID or offer ID provided']);
                exit;
            }
            
            debug_log("Alert deleted successfully");
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    debug_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
