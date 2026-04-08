<?php
// save-offer.php
session_start();
require_once 'user-functions.php';
require_once 'Database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug function
function debug_log($message) {
    $debugFile = 'debug_log.txt';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - save-offer: " . $message . "\n", FILE_APPEND);
}

debug_log("=== New Submission ===");
debug_log("POST: " . print_r($_POST, true));
debug_log("FILES: " . print_r($_FILES, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add-offer.php?error=Invalid request method');
    exit;
}

// Check if user is logged in
$user = get_logged_in_user();
if (!$user) {
    debug_log("User not logged in");
    header('Location: account.php?redirect=add-offer.php');
    exit;
}

debug_log("User: " . $user['username'] . " (ID: " . $user['id'] . ")");

// Validate required fields
$title = trim($_POST['title'] ?? '');
$price = trim($_POST['price'] ?? '');
$description = trim($_POST['description'] ?? '');
$category = $_POST['category'] ?? 'Other';

$errors = [];
if (empty($title)) $errors[] = 'Title is required';
if (empty($price)) $errors[] = 'Price is required';
if (empty($description)) $errors[] = 'Description is required';
if (!is_numeric($price) || $price <= 0) $errors[] = 'Price must be a positive number';

// Check if image was uploaded
$hasImage = isset($_FILES['image']) && $_FILES['image']['error'] === 0 && $_FILES['image']['size'] > 0;
if (!$hasImage) {
    $errors[] = 'An image is required';
    debug_log("No image uploaded");
}

if (!empty($errors)) {
    debug_log("Validation errors: " . implode(', ', $errors));
    header('Location: add-offer.php?error=' . urlencode(implode(', ', $errors)));
    exit;
}

// Handle image upload
$imagePath = '';

if ($hasImage) {
    $uploadDir = 'uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            debug_log("Failed to create uploads directory");
            header('Location: add-offer.php?error=' . urlencode('Failed to create upload directory'));
            exit;
        }
        debug_log("Created uploads directory");
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        debug_log("Uploads directory is not writable");
        chmod($uploadDir, 0777);
    }
    
    // Check file size (2MB max)
    if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        debug_log("File too large: " . $_FILES['image']['size']);
        header('Location: add-offer.php?error=' . urlencode('Image file is too large. Max size is 2MB.'));
        exit;
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $fileType = $_FILES['image']['type'];
    if (!in_array($fileType, $allowedTypes)) {
        debug_log("Invalid file type: " . $fileType);
        header('Location: add-offer.php?error=' . urlencode('Only JPG, PNG, and GIF images are allowed.'));
        exit;
    }
    
    // Generate unique filename
    $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    debug_log("Saving image to: $targetPath");
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        $imagePath = $targetPath;
        debug_log("Image saved successfully");
        chmod($targetPath, 0644);
    } else {
        debug_log("Failed to save image. Error: " . $_FILES['image']['error']);
        header('Location: add-offer.php?error=' . urlencode('Failed to upload image. Please try again.'));
        exit;
    }
}

// Save to database
try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        INSERT INTO offers (title, description, price, category, seller_id, image, created)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $title,
        $description,
        $price,
        $category,
        $user['id'],
        $imagePath
    ]);
    
    $offerId = $db->lastInsertId();
    debug_log("Offer saved to database with ID: $offerId");
    
    header('Location: index.php?success=1');
    exit;
    
} catch (PDOException $e) {
    debug_log("Database error: " . $e->getMessage());
    
    // If image was uploaded but database failed, delete the image
    if (!empty($imagePath) && file_exists($imagePath)) {
        unlink($imagePath);
        debug_log("Deleted orphaned image: $imagePath");
    }
    
    header('Location: add-offer.php?error=' . urlencode('Failed to save offer. Please try again.'));
    exit;
}
?>
