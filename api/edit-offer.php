<?php
session_start();
header('Content-Type: application/json');
ob_start();

require_once dirname(__DIR__) . '/Database.php';

try {
    if (!isset($_SESSION['user'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $currentUser = $_SESSION['user'];

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $id          = intval($data['id'] ?? 0);
    $title       = trim($data['title'] ?? '');
    $price       = $data['price'] ?? '';
    $description = trim($data['description'] ?? '');

    if (!$id || !$title || $price === '') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Title and price are required']);
        exit;
    }

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT o.id, o.price, u.username
        FROM offers o
        JOIN users u ON o.seller_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$id]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$offer) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Offer not found']);
        exit;
    }

    $isAdmin = !empty($currentUser['isAdmin']);
    if ($offer['username'] !== $currentUser['username'] && !$isAdmin) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'You can only edit your own offers']);
        exit;
    }

    $update = $db->prepare("UPDATE offers SET title = ?, price = ?, description = ? WHERE id = ?");
    $update->execute([$title, floatval($price), $description, $id]);

    $oldPrice = floatval($offer['price']);
    $newPrice = floatval($price);
    if ($oldPrice != $newPrice) {
        $alertsFile = dirname(__DIR__) . '/price_alerts.json';
        if (file_exists($alertsFile)) {
            $alerts = json_decode(file_get_contents($alertsFile), true) ?? [];
            foreach ($alerts as &$alert) {
                if ($alert['offerId'] == $id) {
                    $alert['currentPrice'] = $newPrice;
                    if ($newPrice <= floatval($alert['targetPrice']) && empty($alert['notified'])) {
                        $alert['notified'] = true;
                    }
                }
            }
            file_put_contents($alertsFile, json_encode($alerts, JSON_PRETTY_PRINT));
        }
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Offer updated successfully']);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
