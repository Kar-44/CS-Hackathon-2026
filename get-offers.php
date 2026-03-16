<?php
// get-offers.php
header('Content-Type: application/json');
require_once 'Database.php';

// Get parameters from request
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$limit = 10; // Number of offers per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    
    // Build the WHERE clause
    $whereConditions = ["(o.status = 'active' OR o.status IS NULL)"];
    $params = [];
    
    if (!empty($category) && $category !== 'all') {
        $whereConditions[] = "o.category = ?";
        $params[] = $category;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(o.title LIKE ? OR o.description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get total count of offers with filters
    $countSql = "SELECT COUNT(*) as total FROM offers o WHERE $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalOffers = $countStmt->fetch()['total'];
    $totalPages = ceil($totalOffers / $limit);
    
    // Get paginated offers with filters
    $sql = "
        SELECT o.*, u.username as seller, u.avatar as seller_avatar
        FROM offers o
        JOIN users u ON o.seller_id = u.id
        WHERE $whereClause
        ORDER BY o.created DESC
        LIMIT ? OFFSET ?
    ";
    
    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $offers = $stmt->fetchAll();
    
    // Format for frontend compatibility
    foreach ($offers as &$offer) {
        $offer['sellerUsername'] = $offer['seller'];
        $offer['date'] = $offer['created'];
    }
    
    echo json_encode([
        'offers' => $offers,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalOffers' => $totalOffers,
            'hasMore' => $page < $totalPages
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get offers error: " . $e->getMessage());
    echo json_encode([
        'offers' => [],
        'pagination' => [
            'currentPage' => 1,
            'totalPages' => 1,
            'totalOffers' => 0,
            'hasMore' => false
        ]
    ]);
}
?>
