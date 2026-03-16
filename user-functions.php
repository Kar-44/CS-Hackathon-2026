<?php
// user-functions.php - Database Version
require_once dirname(__FILE__) . '/Database.php';

// Set session timeout to 15 minutes (900 seconds)
ini_set('session.gc_maxlifetime', 900);
ini_set('session.cookie_lifetime', 900);

// Set session garbage collection probability
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if session has timed out (15 minutes of inactivity)
 */
function check_session_timeout() {
    $timeout = 900; // 15 minutes in seconds
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];
        
        if ($elapsed_time > $timeout) {
            // Session expired - destroy it
            $_SESSION = array();
            
            // Delete the session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Get logged in user from session with timeout check
 */
function get_logged_in_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check session timeout
    if (!check_session_timeout()) {
        return null;
    }
    
    return $_SESSION['user'] ?? null;
}

/**
 * Save a new user to database
 */
function save_user($userData) {
    $db = Database::getInstance()->getConnection();
    
    try {
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$userData['username']]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, full_name, avatar, registered)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $avatar = generate_ui_avatar($userData['username']);
        
        $stmt->execute([
            $userData['username'],
            $userData['email'] ?? '',
            $hashedPassword,
            $userData['name'] ?? '',
            $avatar
        ]);
        
        return ['success' => true, 'message' => 'User registered successfully'];
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

/**
 * Authenticate user
 */
function authenticate_user($username, $password) {
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if user is banned (column may not exist on older installs)
            if (isset($user['is_banned']) && $user['is_banned'] == 1) {
                return ['success' => false, 'message' => 'Your account has been banned. Please contact support.'];
            }
            // Update last login
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Get favorites
            $favStmt = $db->prepare("SELECT offer_id FROM favorites WHERE user_id = ?");
            $favStmt->execute([$user['id']]);
            $favorites = $favStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Prepare user array for session
            $sessionUser = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'profile' => [
                    'name' => $user['full_name'],
                    'avatar' => $user['avatar'],
                    'phone' => $user['phone'] ?? ''
                ],
                'favorites' => $favorites,
                'isAdmin' => $user['is_admin'] == 1
            ];
            
            $_SESSION['user'] = $sessionUser;
            $_SESSION['last_activity'] = time(); // Set last activity time on login
            
            return ['success' => true, 'user' => $sessionUser];
        }
        
        return ['success' => false, 'message' => 'Invalid username or password'];
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Login failed. Please try again.'];
    }
}

/**
 * Update user profile information - FIXED VERSION
 */
function update_user_profile($username, $profileData) {
    $db = Database::getInstance()->getConnection();
    
    try {
        // Build the SQL dynamically based on what's being updated
        $fields = [];
        $params = [];
        
        if (isset($profileData['name'])) {
            $fields[] = "full_name = ?";
            $params[] = $profileData['name'];
        }
        
        if (isset($profileData['email'])) {
            $fields[] = "email = ?";
            $params[] = $profileData['email'];
        }
        
        if (isset($profileData['phone'])) {
            $fields[] = "phone = ?";
            $params[] = $profileData['phone'];
        }
        
        // Handle avatar separately - only update if a new one was uploaded
        if (isset($profileData['avatar']) && !empty($profileData['avatar'])) {
            $fields[] = "avatar = ?";
            $params[] = $profileData['avatar'];
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => 'No fields to update'];
        }
        
        // Add username to params
        $params[] = $username;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE username = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Get the updated user data
        $selectStmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $selectStmt->execute([$username]);
        $updatedUser = $selectStmt->fetch();
        
        if ($updatedUser) {
            // Get updated favorites
            $favStmt = $db->prepare("SELECT offer_id FROM favorites WHERE user_id = ?");
            $favStmt->execute([$updatedUser['id']]);
            $favorites = $favStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Update session with latest data
            $sessionUser = [
                'id' => $updatedUser['id'],
                'username' => $updatedUser['username'],
                'email' => $updatedUser['email'],
                'profile' => [
                    'name' => $updatedUser['full_name'],
                    'avatar' => $updatedUser['avatar'],
                    'phone' => $updatedUser['phone'] ?? ''
                ],
                'favorites' => $favorites,
                'isAdmin' => $updatedUser['is_admin'] == 1
            ];
            
            $_SESSION['user'] = $sessionUser;
            $_SESSION['last_activity'] = time();
        }
        
        return ['success' => true, 'message' => 'Profile updated successfully'];
        
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()];
    }
}

/**
 * Change user password
 */
function change_user_password($username, $oldPassword, $newPassword) {
    $db = Database::getInstance()->getConnection();
    
    try {
        // Get current password
        $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
        $updateStmt->execute([$hashedPassword, $username]);
        
        // Update activity time on password change
        $_SESSION['last_activity'] = time();
        
        return ['success' => true, 'message' => 'Password changed successfully'];
        
    } catch (PDOException $e) {
        error_log("Password change error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to change password'];
    }
}

/**
 * Toggle favorite
 */
function toggle_favorite($username, $offerId) {
    $db = Database::getInstance()->getConnection();
    
    try {
        // Get user ID
        $userStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $userStmt->execute([$username]);
        $userId = $userStmt->fetchColumn();
        
        if (!$userId) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Check if favorite exists
        $checkStmt = $db->prepare("SELECT * FROM favorites WHERE user_id = ? AND offer_id = ?");
        $checkStmt->execute([$userId, $offerId]);
        
        if ($checkStmt->fetch()) {
            // Remove
            $deleteStmt = $db->prepare("DELETE FROM favorites WHERE user_id = ? AND offer_id = ?");
            $deleteStmt->execute([$userId, $offerId]);
            $isFavorite = false;
        } else {
            // Add
            $insertStmt = $db->prepare("INSERT INTO favorites (user_id, offer_id, created) VALUES (?, ?, NOW())");
            $insertStmt->execute([$userId, $offerId]);
            $isFavorite = true;
        }
        
        // Get updated favorites
        $favStmt = $db->prepare("SELECT offer_id FROM favorites WHERE user_id = ?");
        $favStmt->execute([$userId]);
        $favorites = $favStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Update session
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['favorites'] = $favorites;
            $_SESSION['last_activity'] = time(); // Update activity time on favorite toggle
        }
        
        return [
            'success' => true,
            'isFavorite' => $isFavorite,
            'favorites' => $favorites
        ];
        
    } catch (PDOException $e) {
        error_log("Toggle favorite error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update favorites'];
    }
}

/**
 * Get user favorites
 */
function get_user_favorites($username) {
    $db = Database::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            SELECT f.offer_id 
            FROM favorites f
            JOIN users u ON u.id = f.user_id
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);
        
        // Update activity time on read operation
        $_SESSION['last_activity'] = time();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        error_log("Get favorites error: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate UI Avatar
 */
function generate_ui_avatar($username, $fullName = '') {
    $hash = md5($username);
    $color = substr($hash, 0, 6);
    $initials = strtoupper(substr($username, 0, 2));
    
    return 'https://ui-avatars.com/api/?name=' . urlencode($initials) . 
           '&background=' . $color . '&color=fff&size=150&bold=true&length=2';
}

function is_admin() {
    $user = get_logged_in_user();
    if (!$user) return false;
    if (!empty($user['isAdmin'])) return true;
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT is_admin FROM users WHERE username = ?");
        $stmt->execute([$user['username']]);
        $row = $stmt->fetch();
        if ($row && $row['is_admin'] == 1) {
            $_SESSION['user']['isAdmin'] = true;
            return true;
        }
    } catch (Exception $e) {
        error_log("is_admin() error: " . $e->getMessage());
    }
    return false;
}

/**
 * Logout user - destroy session properly
 */
function logout_user() {
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    return ['success' => true];
}

/**
 * Check if user is logged in (with timeout check)
 */
function is_user_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check timeout
    if (!check_session_timeout()) {
        return false;
    }
    
    return isset($_SESSION['user']);
}

/**
 * Sync session user with database (optional)
 */
function sync_session_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!check_session_timeout()) {
        return null;
    }
    
    $sessionUser = $_SESSION['user'] ?? null;
    if (!$sessionUser) {
        return null;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$sessionUser['id']]);
    $dbUser = $stmt->fetch();
    
    if ($dbUser) {
        // Get updated favorites
        $favStmt = $db->prepare("SELECT offer_id FROM favorites WHERE user_id = ?");
        $favStmt->execute([$dbUser['id']]);
        $favorites = $favStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Update session with latest data
        $sessionUser = [
            'id' => $dbUser['id'],
            'username' => $dbUser['username'],
            'email' => $dbUser['email'],
            'profile' => [
                'name' => $dbUser['full_name'],
                'avatar' => $dbUser['avatar'],
                'phone' => $dbUser['phone'] ?? ''
            ],
            'favorites' => $favorites,
            'isAdmin' => $dbUser['is_admin'] == 1
        ];
        
        $_SESSION['user'] = $sessionUser;
        $_SESSION['last_activity'] = time();
    }
    
    return $sessionUser;
}
?>