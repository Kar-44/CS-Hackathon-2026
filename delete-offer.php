<?php
// delete-offer.php
session_start();
require_once 'user-functions.php';
require_once 'Database.php';

header('Content-Type: application/json');

// Debug function
function debug_log($message) {
    $debugFile = 'delete_debug.log';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

debug_log("=== Delete Request Started ===");

// Check if user is logged in
$user = get_logged_in_user();
if (!$user) {
    debug_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

debug_log("User: " . $user['username'] . " (ID: " . $user['id'] . ")");

// Get POST data
$input = file_get_contents('php://input');
debug_log("Raw input: " . $input);

$data = json_decode($input, true);
$offerId = $data['id'] ?? 0;

debug_log("Offer ID: " . $offerId);

if (!$offerId) {
    debug_log("No offer ID provided");
    echo json_encode(['success' => false, 'message' => 'No offer ID provided']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // First, get the offer to check ownership and get image path
    $stmt = $db->prepare("
        SELECT o.*, u.username as seller_username 
        FROM offers o
        JOIN users u ON o.seller_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$offerId]);
    $offer = $stmt->fetch();
    
    if (!$offer) {
        debug_log("Offer not found with ID: " . $offerId);
        echo json_encode(['success' => false, 'message' => 'Offer not found']);
        exit;
    }
    
    debug_log("Found offer: " . $offer['title'] . " (Seller: " . $offer['seller_username'] . ")");
    
    // Check if the current user is the seller
    if ($offer['seller_username'] !== $user['username']) {
        debug_log("Permission denied - user is not the seller");
        echo json_encode(['success' => false, 'message' => 'You can only delete your own offers']);
        exit;
    }
    
    // Delete the offer (this will cascade to favorites and chats due to foreign keys)
    $deleteStmt = $db->prepare("DELETE FROM offers WHERE id = ?");
    $deleteStmt->execute([$offerId]);
    
    // Delete the image file if it exists and is not a placeholder
    if (!empty($offer['image']) && strpos($offer['image'], 'uploads/') === 0) {
        $imagePath = $offer['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
            debug_log("Deleted image: " . $imagePath);
        }
    }
    
    debug_log("Offer deleted successfully from database");
    
    echo json_encode(['success' => true, 'message' => 'Offer deleted successfully']);
    
} catch (PDOException $e) {
    debug_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
