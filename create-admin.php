<?php
// user-functions.php

/**
 * Load users from JSON file
 */
function load_users() {
    $file = 'users.json';
    if (!file_exists($file)) {
        return ['users' => []];
    }
    
    $json = file_get_contents($file);
    if ($json === false) {
        return ['users' => []];
    }
    
    $data = json_decode($json, true);
    return $data ?? ['users' => []];
}

/**
 * Save users to JSON file with backup
 */
function save_users($users) {
    $file = 'users.json';
    $json = json_encode($users, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    
    // Create backup before saving
    if (file_exists($file)) {
        copy($file, $file . '.backup');
    }
    
    $result = file_put_contents($file, $json);
    if ($result === false) {
        // Try to restore from backup
        if (file_exists($file . '.backup')) {
            copy($file . '.backup', $file);
        }
        return false;
    }
    
    // Set permissions
    chmod($file, 0666);
    
    return $result;
}

/**
 * Save a new user to the JSON file
 */
function save_user($userData) {
    $file = 'users.json';
    $users = load_users();
    
    // Check if username already exists (case insensitive)
    foreach ($users['users'] as $user) {
        if (strtolower($user['username']) === strtolower($userData['username'])) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
    }
    
    // Hash the password before storing
    $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Add timestamp and default profile
    $userData['registered'] = date('Y-m-d H:i:s');
    $userData['profile'] = [
        'avatar' => 'https://via.placeholder.com/150',
        'name' => $userData['name'] ?? '',
        'email' => $userData['email'] ?? '',
        'phone' => ''
    ];
    $userData['favorites'] = [];
    
    $users['users'][] = $userData;
    
    // Save back to file
    if (!save_users($users)) {
        return ['success' => false, 'message' => 'Failed to save user data'];
    }
    
    // Remove password before returning
    unset($userData['password']);
    return ['success' => true, 'message' => 'User registered successfully', 'user' => $userData];
}

/**
 * Authenticate a user with username and password
 */
function authenticate_user($username, $password) {
    $users = load_users();
    
    foreach ($users['users'] as $user) {
        // Case insensitive username comparison
        if (strtolower($user['username']) === strtolower($username)) {
            // Verify the password against the hash
            if (password_verify($password, $user['password'])) {
                // Remove password before returning user data
                unset($user['password']);
                
                // Ensure favorites array exists
                if (!isset($user['favorites'])) {
                    $user['favorites'] = [];
                }
                
                // Start session and store user
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user'] = $user;
                
                return ['success' => true, 'user' => $user];
            }
        }
    }
    
    return ['success' => false, 'message' => 'Invalid username or password'];
}

/**
 * Get the currently logged in user from session
 */
function get_logged_in_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $user = $_SESSION['user'] ?? null;
    
    // If we have a user in session, ensure favorites array exists
    if ($user && !isset($user['favorites'])) {
        $user['favorites'] = [];
        $_SESSION['user'] = $user;
    }
    
    return $user;
}

/**
 * Update user profile information
 */
function update_user_profile($username, $profileData) {
    $users = load_users();
    $found = false;
    
    foreach ($users['users'] as &$user) {
        if (strtolower($user['username']) === strtolower($username)) {
            $found = true;
            // Update profile fields
            $user['profile'] = array_merge($user['profile'], $profileData);
            
            // Ensure favorites array exists
            if (!isset($user['favorites'])) {
                $user['favorites'] = [];
            }
            
            // Save changes
            if (!save_users($users)) {
                return ['success' => false, 'message' => 'Failed to save profile'];
            }
            
            // Update session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user'] = $user;
            
            return ['success' => true, 'user' => $user];
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    return ['success' => false, 'message' => 'Update failed'];
}

/**
 * Change user password
 */
function change_user_password($username, $oldPassword, $newPassword) {
    $users = load_users();
    
    foreach ($users['users'] as &$user) {
        if (strtolower($user['username']) === strtolower($username)) {
            // Verify old password
            if (!password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Update to new password
            $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Ensure favorites array exists
            if (!isset($user['favorites'])) {
                $user['favorites'] = [];
            }
            
            // Save changes
            if (!save_users($users)) {
                return ['success' => false, 'message' => 'Failed to save new password'];
            }
            
            return ['success' => true, 'message' => 'Password changed successfully'];
        }
    }
    
    return ['success' => false, 'message' => 'User not found'];
}

/**
 * Toggle favorite for a user (IMPROVED)
 */
function toggle_favorite($username, $offerId) {
    $users = load_users();
    
    foreach ($users['users'] as &$user) {
        if (strtolower($user['username']) === strtolower($username)) {
            // Initialize favorites array if it doesn't exist
            if (!isset($user['favorites'])) {
                $user['favorites'] = [];
            }
            
            // Check if already favorited (use loose comparison for integers)
            $found = false;
            foreach ($user['favorites'] as $key => $id) {
                if ($id == $offerId) {
                    // Remove from favorites
                    unset($user['favorites'][$key]);
                    $found = true;
                    $isFavorite = false;
                    break;
                }
            }
            
            if (!$found) {
                // Add to favorites
                $user['favorites'][] = $offerId;
                $isFavorite = true;
            }
            
            // Re-index array
            $user['favorites'] = array_values($user['favorites']);
            
            // Save changes
            if (save_users($users)) {
                // Update session
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user'] = $user;
                
                return [
                    'success' => true, 
                    'isFavorite' => $isFavorite, 
                    'favorites' => $user['favorites']
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to save favorites'];
            }
        }
    }
    
    return ['success' => false, 'message' => 'User not found'];
}

/**
 * Add an offer to user's favorites
 */
function add_favorite($username, $offerId) {
    $users = load_users();
    
    foreach ($users['users'] as &$user) {
        if (strtolower($user['username']) === strtolower($username)) {
            if (!isset($user['favorites'])) {
                $user['favorites'] = [];
            }
            
            // Check if already exists (loose comparison)
            $exists = false;
            foreach ($user['favorites'] as $id) {
                if ($id == $offerId) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $user['favorites'][] = $offerId;
                
                // Save changes
                if (!save_users($users)) {
                    return ['success' => false, 'message' => 'Failed to save favorites'];
                }
                
                // Update session
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user'] = $user;
            }
            
            return ['success' => true, 'favorites' => $user['favorites']];
        }
    }
    
    return ['success' => false, 'message' => 'User not found'];
}

/**
 * Remove an offer from user's favorites
 */
function remove_favorite($username, $offerId) {
    $users = load_users();
    
    foreach ($users['users'] as &$user) {
        if (strtolower($user['username']) === strtolower($username)) {
            if (isset($user['favorites'])) {
                // Filter out the offer (loose comparison)
                $user['favorites'] = array_values(array_filter($user['favorites'], function($id) use ($offerId) {
                    return $id != $offerId;
                }));
                
                // Save changes
                if (!save_users($users)) {
                    return ['success' => false, 'message' => 'Failed to save favorites'];
                }
                
                // Update session
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user'] = $user;
            }
            
            return ['success' => true, 'favorites' => $user['favorites']];
        }
    }
    
    return ['success' => false, 'message' => 'User not found'];
}

/**
 * Get user's favorite offers
 */
function get_user_favorites($username) {
    $users = load_users();
    
    foreach ($users['users'] as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            return $user['favorites'] ?? [];
        }
    }
    
    return [];
}

/**
 * Check if an offer is favorited by a user
 */
function is_favorite($username, $offerId) {
    $favorites = get_user_favorites($username);
    foreach ($favorites as $id) {
        if ($id == $offerId) {
            return true;
        }
    }
    return false;
}

/**
 * Sync session user with database
 */
function sync_session_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $sessionUser = $_SESSION['user'] ?? null;
    if (!$sessionUser) {
        return null;
    }
    
    $users = load_users();
    foreach ($users['users'] as $dbUser) {
        if (strtolower($dbUser['username']) === strtolower($sessionUser['username'])) {
            // Update session with database user
            unset($dbUser['password']);
            $_SESSION['user'] = $dbUser;
            return $dbUser;
        }
    }
    
    return $sessionUser;
}

/**
 * Logout user - destroy session
 */
function logout_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
    return ['success' => true, 'message' => 'Logged out successfully'];
}

/**
 * Check if user is logged in
 */
function is_user_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user']);
}

/**
 * Get all users (admin function)
 */
function get_all_users() {
    $users = load_users();
    // Remove passwords from all users
    foreach ($users['users'] as &$user) {
        unset($user['password']);
        if (!isset($user['favorites'])) {
            $user['favorites'] = [];
        }
    }
    return $users['users'];
}

/**
 * Fix user data - ensure all users have favorites array
 */
function fix_user_data() {
    $users = load_users();
    $fixed = 0;
    
    foreach ($users['users'] as &$user) {
        if (!isset($user['favorites'])) {
            $user['favorites'] = [];
            $fixed++;
        }
    }
    
    if ($fixed > 0) {
        save_users($users);
    }
    
    return $fixed;
}

/**
 * Find user by username (case insensitive)
 */
function find_user($username) {
    $users = load_users();
    
    foreach ($users['users'] as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            unset($user['password']);
            return $user;
        }
    }
    
    return null;
}

// ============= ADMIN FUNCTIONS =============

/**
 * Check if the current user is an admin
 */
function is_admin() {
    $user = get_logged_in_user();
    return $user && isset($user['isAdmin']) && $user['isAdmin'] === true;
}

/**
 * Get all users with full details (admin only)
 */
function get_all_users_admin() {
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    $users = load_users();
    // Remove passwords from all users
    foreach ($users['users'] as &$user) {
        unset($user['password']);
        if (!isset($user['favorites'])) {
            $user['favorites'] = [];
        }
    }
    return ['success' => true, 'users' => $users['users']];
}

/**
 * Delete a user (admin only)
 */
function admin_delete_user($username) {
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    $users = load_users();
    $found = false;
    
    foreach ($users['users'] as $key => $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            // Don't allow deleting yourself
            $currentUser = get_logged_in_user();
            if (strtolower($currentUser['username']) === strtolower($username)) {
                return ['success' => false, 'message' => 'Cannot delete your own account'];
            }
            unset($users['users'][$key]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $users['users'] = array_values($users['users']);
    
    if (save_users($users)) {
        return ['success' => true, 'message' => 'User deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to save changes'];
}

/**
 * Delete an offer (admin only)
 */
function admin_delete_offer($offerId) {
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    $offersFile = 'offers.txt';
    if (!file_exists($offersFile)) {
        return ['success' => false, 'message' => 'Offers file not found'];
    }
    
    $content = file_get_contents($offersFile);
    $offers = unserialize($content) ?: [];
    $found = false;
    
    foreach ($offers as $key => $offer) {
        if ($offer['id'] == $offerId) {
            // Delete image if it exists and is a local file
            if (isset($offer['image']) && strpos($offer['image'], 'uploads/') === 0) {
                $imagePath = $offer['image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            unset($offers[$key]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'Offer not found'];
    }
    
    $offers = array_values($offers);
    if (file_put_contents($offersFile, serialize($offers))) {
        return ['success' => true, 'message' => 'Offer deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to save changes'];
}

/**
 * Make a user an admin (admin only)
 */
function admin_make_admin($username) {
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    $users = load_users();
    $found = false;
    
    foreach ($users['users'] as &$user) {
        if (strtolower($user['username']) === strtolower($username)) {
            $user['isAdmin'] = true;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    if (save_users($users)) {
        return ['success' => true, 'message' => "$username is now an admin"];
    }
    
    return ['success' => false, 'message' => 'Failed to save changes'];
}

/**
 * Remove admin privileges from a user (admin only)
 */
function admin_remove_admin($username) {
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    // Don't allow removing your own admin status
    $currentUser = get_logged_in_user();
    if (strtolower($currentUser['username']) === strtolower($username)) {
        return ['success' => false, 'message' => 'Cannot remove your own admin status'];
    }
    
    $users = load_users();
    $found = false;
    
    foreach ($users['users'] as &$user) {
        if (strtolower($user['username']) === strtolower($username)) {
            unset($user['isAdmin']);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    if (save_users($users)) {
        return ['success' => true, 'message' => "Admin privileges removed from $username"];
    }
    
    return ['success' => false, 'message' => 'Failed to save changes'];
}

/**
 * Get system statistics (admin only)
 */
function get_system_stats() {
    if (!is_admin()) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    $stats = [
        'total_users' => 0,
        'total_offers' => 0,
        'total_chats' => 0,
        'total_images' => 0
    ];
    
    // Count users
    $users = load_users();
    $stats['total_users'] = count($users['users']);
    
    // Count offers
    if (file_exists('offers.txt')) {
        $content = file_get_contents('offers.txt');
        if (!empty($content)) {
            $offers = unserialize($content) ?: [];
            $stats['total_offers'] = count($offers);
            
            // Count images
            foreach ($offers as $offer) {
                if (isset($offer['image']) && strpos($offer['image'], 'uploads/') === 0) {
                    $stats['total_images']++;
                }
            }
        }
    }
    
    // Count chats
    if (file_exists('chats.json')) {
        $chats = json_decode(file_get_contents('chats.json'), true);
        $stats['total_chats'] = count($chats['chats'] ?? []);
    }
    
    return ['success' => true, 'stats' => $stats];
}
?>