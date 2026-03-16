<?php
// Start session
session_start();
require_once 'user-functions.php'; // This now uses the database version

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['regUser'] ?? '');
    $password = $_POST['regPass'] ?? '';
    $confirmPass = $_POST['regPassConfirm'] ?? '';
    $email = trim($_POST['regEmail'] ?? '');
    $privacyAgree = isset($_POST['privacyAgree']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirmPass) {
        $errors[] = "Passwords do not match";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (!$privacyAgree) {
        $errors[] = "You must agree to the Privacy Notice";
    }
    
    if (empty($errors)) {
        $result = save_user([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'name' => ''
        ]);
        
        if ($result['success']) {
            // Auto-login the user
            $login_result = authenticate_user($username, $password);
            if ($login_result['success']) {
                header('Location: account.php?registered=1');
                exit;
            } else {
                $register_error = "Registration successful but auto-login failed. Please login manually.";
            }
        } else {
            $register_error = $result['message'];
        }
    } else {
        $register_error = implode("<br>", $errors);
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['loginUser'] ?? '';
    $password = $_POST['loginPass'] ?? '';
    
    $result = authenticate_user($username, $password);
    
    if ($result['success']) {
        // After successful login, redirect to account page
        header('Location: account.php');
        exit;
    } else {
        $login_error = $result['message'];
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $user = get_logged_in_user();
    if ($user) {
        $profileData = [
            'name' => $_POST['profName'] ?? '',
            'email' => $_POST['profEmail'] ?? '',
            'phone' => $_POST['profPhone'] ?? ''
        ];
        
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
            $uploadDir = 'uploads/avatars/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = $user['username'] . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                $profileData['avatar'] = $targetPath;
            }
        }
        
        $result = update_user_profile($user['username'], $profileData);
        if ($result['success']) {
            $profile_success = "Profile updated successfully!";
            // Update the user variable with new data
            $user = get_logged_in_user();
        } else {
            $profile_error = $result['message'];
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $user = get_logged_in_user();
    if ($user) {
        $oldPassword = $_POST['oldPass'] ?? '';
        $newPassword = $_POST['newPass'] ?? '';
        $confirmPassword = $_POST['newPassConf'] ?? '';
        
        if (strlen($newPassword) < 6) {
            $password_error = "New password must be at least 6 characters";
        } elseif ($newPassword !== $confirmPassword) {
            $password_error = "New passwords do not match";
        } else {
            $result = change_user_password($user['username'], $oldPassword, $newPassword);
            if ($result['success']) {
                $password_success = "Password changed successfully!";
            } else {
                $password_error = $result['message'];
            }
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    logout_user();
    header('Location: account.php');
    exit;
}

// Get current user
$currentUser = get_logged_in_user();

// Check if user is admin
$isAdmin = is_admin();

// Check for redirect parameter
$redirect = $_GET['redirect'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Account - CampusCycle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">
    <style>
        .success-msg {
            color: #4CAF50;
            background: #e8f5e8;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .error-msg {
            color: #ff4757;
            background: #ffebee;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .info-msg {
            background-color: #e3f2fd;
            color: #0d47a1;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>

    <style>
        /* ===== CRITICAL OVERRIDES ===== */
        .theme-toggle {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
        }
        .theme-btn {
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
            min-height: 36px !important;
            max-width: 36px !important;
            max-height: 36px !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }
        .offer-card {
            border: 2px solid var(--primary) !important;
        }
    </style>
</head>
<body>

    <header>
        <nav>
            <a href="index.php" class="logo"><img src="uploads/campus-logo.png" style="height:48px;width:48px;object-fit:contain;vertical-align:middle;margin-right:8px;"> CampusCycle</a>
            
            <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </div>
            
            <div class="nav-links">
                <a href="favorites.html" class="nav-item"><i class="far fa-heart"></i></a>
                <a href="chats.html" class="nav-item"><i class="far fa-comment-dots"></i></a>
                <a href="account.php" class="nav-item" style="color: var(--primary);"><i class="fas fa-user-circle"></i></a>
                <?php if ($isAdmin): ?>
                    <a href="admin.php" class="nav-item" title="Admin Panel"><i class="fas fa-cog"></i> Admin</a>
                <?php endif; ?>
                <a href="contact.php" class="nav-item" title="Contact Us"><i class="far fa-envelope"></i></a>
                <a href="add-offer.php" class="nav-item btn-add-offer"><i class="fas fa-plus"></i> Add Offer</a>
            </div>
        </nav>
    </header>

    <div class="container">
        
        <?php if (!$currentUser): ?>
        <!-- Auth Section (Login/Register) -->
        <div id="authSection" class="auth-wrapper">
            <?php if ($redirect): ?>
                <div class="info-msg">
                    Please login to continue to <?php echo htmlspecialchars($redirect); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <div class="success-msg">
                    Registration successful! You are now logged in.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['timeout'])): ?>
                <div class="info-msg">
                    <i class="fas fa-clock"></i> Your session has expired. Please login again.
                </div>
            <?php endif; ?>
            
            <div class="auth-toggle">
                <button class="toggle-btn <?php echo (!isset($_GET['register']) && !isset($register_error)) ? 'active' : ''; ?>" id="tabLogin" onclick="toggleAuth('login')">Login</button>
                <button class="toggle-btn <?php echo (isset($_GET['register']) || isset($register_error)) ? 'active' : ''; ?>" id="tabRegister" onclick="toggleAuth('register')">Register</button>
            </div>

            <!-- Login Form -->
            <form id="loginForm" method="POST" action="" style="<?php echo isset($_GET['register']) ? 'display: none;' : ''; ?>">
                <input type="hidden" name="login" value="1">
                <?php if (isset($login_error)): ?>
                    <div class="error-msg"><?php echo $login_error; ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="loginUser" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="loginPass" class="form-control" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePass(this)"></i>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Login</button>
            </form>

            <!-- Register Form -->
            <div id="registerWelcome" style="<?php echo (!isset($_GET['register']) && !isset($register_error)) ? 'display: none;' : ''; ?> text-align:center; margin-bottom:20px; padding:18px 16px; background:linear-gradient(135deg, #ff2d7a 0%, #c0004e 100%); border-radius:12px; color:white;">
                <div style="font-size:1.6rem; margin-bottom:6px;">&#127891;</div>
                <div style="font-weight:700; font-size:1.05rem; margin-bottom:4px;">Welcome to CampusCycle</div>
                <div style="font-size:0.88rem; opacity:0.92; line-height:1.5;">Your local student marketplace. Trade easily, save money, and keep it on campus.</div>
            </div>
            <form id="registerForm" method="POST" action="<?php echo isset($_GET['register']) ? '?register' : ''; ?>" style="<?php echo (!isset($_GET['register']) && !isset($register_error)) ? 'display: none;' : ''; ?>">
                <input type="hidden" name="register" value="1">
                <?php if (isset($register_error)): ?>
                    <div class="error-msg"><?php echo $register_error; ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="regUser" class="form-control" required minlength="3">
                    <small style="color: #999;">Minimum 3 characters</small>
                </div>
                
                <div class="form-group">
                    <label>Email (optional)</label>
                    <input type="email" name="regEmail" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <div class="password-wrapper">
                        <input type="password" name="regPass" class="form-control" required minlength="6">
                        <i class="fas fa-eye toggle-password" onclick="togglePass(this)"></i>
                    </div>
                    <small style="color: #999;">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <div class="password-wrapper">
                        <input type="password" name="regPassConfirm" class="form-control" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePass(this)"></i>
                    </div>
                </div>
                
                <!-- Privacy Notice Agreement -->
                <div style="margin: 15px 0; font-size: 0.9rem; color: #666; text-align: center;">
                    <input type="checkbox" id="privacyAgree" name="privacyAgree" required style="margin-right: 5px;">
                    <label for="privacyAgree">I agree to the <a href="privacy.php" target="_blank" style="color: var(--primary); text-decoration: none;">Privacy Notice</a></label>
                </div>
                
                <button type="submit" class="submit-btn">Create Account</button>
            </form>
        </div>
        <?php else: ?>

        <!-- Profile Section (Logged In) -->
        <div id="profileSection" class="account-layout">
            
            <aside class="account-sidebar">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="<?php echo $currentUser['profile']['avatar'] ?? 'https://via.placeholder.com/150'; ?>" id="sidebarAvatar" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                    <h4 id="sidebarName" style="margin-top: 10px;"><?php echo htmlspecialchars($currentUser['profile']['name'] ?: $currentUser['username']); ?></h4>
                </div>
                <button class="sidebar-btn active" onclick="showSection('details', this)">
                    <i class="fas fa-user-cog"></i> Account Details
                </button>
                <button class="sidebar-btn" onclick="showSection('posts', this)">
                    <i class="fas fa-layer-group"></i> My Posts
                </button>
                <?php if ($isAdmin): ?>
                <button class="sidebar-btn" onclick="window.location.href='admin.php'">
                    <i class="fas fa-cog"></i> Admin Panel
                </button>
                <?php endif; ?>
                <div class="divider"></div>
                <button class="sidebar-btn" onclick="logout()" style="color: #ff4757;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </aside>

            <main class="account-content">
                
                <div id="detailsView">
                    <h2>Edit Profile</h2>
                    <br>
                    <?php if (isset($profile_success)): ?>
                        <div class="success-msg"><?php echo $profile_success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($profile_error)): ?>
                        <div class="error-msg"><?php echo $profile_error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="profile-pic-container" onclick="document.getElementById('avatarInput').click()">
                            <img src="<?php echo $currentUser['profile']['avatar'] ?? 'https://via.placeholder.com/150'; ?>" id="mainAvatar" class="profile-pic">
                            <div class="profile-pic-overlay"><i class="fas fa-camera"></i></div>
                            <input type="file" id="avatarInput" name="avatar" hidden accept="image/png, image/jpeg, image/jpg" onchange="previewAvatar(this)">
                        </div>
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="profName" id="profName" class="form-control" value="<?php echo htmlspecialchars($currentUser['profile']['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="profEmail" id="profEmail" class="form-control" value="<?php echo htmlspecialchars($currentUser['email'] ?? $currentUser['profile']['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="profPhone" id="profPhone" class="form-control" value="<?php echo htmlspecialchars($currentUser['profile']['phone'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="submit-btn">Save Info</button>
                    </form>

                    <div class="divider"></div>

                    <h3>Change Password</h3>
                    <br>
                    <?php if (isset($password_success)): ?>
                        <div class="success-msg"><?php echo $password_success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($password_error)): ?>
                        <div class="error-msg"><?php echo $password_error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label>Old Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="oldPass" id="oldPass" class="form-control" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePass(this)"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="newPass" id="newPass" class="form-control" required minlength="6">
                                <i class="fas fa-eye toggle-password" onclick="togglePass(this)"></i>
                            </div>
                            <small style="color: #999;">Minimum 6 characters</small>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="newPassConf" id="newPassConf" class="form-control" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePass(this)"></i>
                            </div>
                        </div>
                        <button type="submit" class="submit-btn" style="background-color: #333;">Update Password</button>
                    </form>
                </div>

                <div id="postsView" class="hidden">
                    <h2>My Active Offers</h2>
                    <br>
                    <div id="myPostsList">
                        <!-- Posts will be loaded here -->
                        <p style="text-align:center; color:#666;"><i class="fas fa-spinner fa-spin"></i> Loading your offers...</p>
                    </div>
                </div>

            </main>
        </div>
        <?php endif; ?>
    </div>

    <!-- Alert Modal -->
    <div class="modal-overlay" id="alertModal">
        <div class="modal-box">
            <h3 id="modalTitle">Alert</h3>
            <p id="modalMsg">Message</p>
            <button class="submit-btn" onclick="document.getElementById('alertModal').style.display='none'">OK</button>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box" style="text-align: left;">
            <h3>Edit Offer</h3>
            <br>
            <input type="hidden" id="editId">
            <div class="form-group">
                <label>Title</label>
                <input type="text" id="editTitle" class="form-control">
            </div>
            <div class="form-group">
                <label>Price (€)</label>
                <input type="number" id="editPrice" class="form-control" step="0.01">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="editDesc" class="form-control" rows="3" maxlength="300" style="resize:vertical; width:100%; padding:10px; border:1.5px solid var(--border-color); border-radius:8px; background:var(--bg); color:var(--text-dark); font-family:inherit; font-size:.95rem;"></textarea>
                <small style="color:var(--text-light);">Max 300 characters</small>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="submit-btn" onclick="saveEdit()">Save</button>
                <button class="submit-btn" style="background: #ccc;" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this offer?</p>
            <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: center;">
                <button class="submit-btn" onclick="confirmDelete()" id="confirmDeleteBtn">Yes, Delete</button>
                <button class="submit-btn" style="background: #ccc;" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            document.querySelector('.nav-links').classList.toggle('show');
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const nav = document.querySelector('.nav-links');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (nav && toggle && !nav.contains(event.target) && !toggle.contains(event.target)) {
                nav.classList.remove('show');
            }
        });

        // --- AUTH TOGGLE ---
        function toggleAuth(view) {
            if(view === 'login') {
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('registerForm').style.display = 'none';
                document.getElementById('registerWelcome').style.display = 'none';
                document.getElementById('tabLogin').classList.add('active');
                document.getElementById('tabRegister').classList.remove('active');
            } else {
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
                document.getElementById('registerWelcome').style.display = 'block';
                document.getElementById('tabLogin').classList.remove('active');
                document.getElementById('tabRegister').classList.add('active');
            }
        }

        // --- PASSWORD VISIBILITY ---
        function togglePass(icon) {
            const input = icon.parentElement.querySelector('input');
            if(input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.add('fa-eye');
                icon.classList.remove('fa-eye-slash');
            }
        }

        // --- GLOBAL DATA HELPERS ---
        function getCurrent() { 
            return JSON.parse(localStorage.getItem('currentUser'));
        }
        
        function getOffers() { return JSON.parse(localStorage.getItem('campusOffers')) || []; }
        function saveOffers(offers) { localStorage.setItem('campusOffers', JSON.stringify(offers)); }

        // Pass admin status to JavaScript
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

        // --- SYNC PHP SESSION TO LOCALSTORAGE ---
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($currentUser): ?>
                // User is logged in via PHP, save to localStorage
                const user = <?php echo json_encode($currentUser); ?>;
                localStorage.setItem('currentUser', JSON.stringify(user));
                console.log('User synced to localStorage:', user.username);
                
                // Check if we need to redirect after login
                <?php if ($redirect): ?>
                    window.location.href = '<?php echo $redirect; ?>';
                <?php endif; ?>
            <?php else: ?>
                // No user in session, clear localStorage
                localStorage.removeItem('currentUser');
                console.log('No user in session, cleared localStorage');
            <?php endif; ?>
            
            // Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('register') === '1') {
                toggleAuth('register');
            }
        });

        // --- LOGOUT ---
        function logout() {
            // Clear localStorage before redirecting
            localStorage.removeItem('currentUser');
            window.location.href = 'account.php?logout=1';
        }

        // --- PROFILE TABS ---
        function showSection(section, btn) {
            document.querySelectorAll('.sidebar-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            if(section === 'details') {
                document.getElementById('detailsView').classList.remove('hidden');
                document.getElementById('postsView').classList.add('hidden');
            } else {
                document.getElementById('detailsView').classList.add('hidden');
                document.getElementById('postsView').classList.remove('hidden');
                loadMyPosts();
            }
        }

        function previewAvatar(input) {
            if(input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('mainAvatar').src = e.target.result;
                    document.getElementById('sidebarAvatar').src = e.target.result;
                    
                    // Also update in localStorage
                    const user = getCurrent();
                    if (user) {
                        user.profile.avatar = e.target.result;
                        localStorage.setItem('currentUser', JSON.stringify(user));
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // --- MY POSTS LOGIC ---
        let offerToDelete = null;

        function loadMyPosts() {
            const user = getCurrent();
            if (!user) return;
            
            const container = document.getElementById('myPostsList');
            container.innerHTML = '<p style="text-align:center; color:#666;"><i class="fas fa-spinner fa-spin"></i> Loading your offers...</p>';
            
            // Fetch offers from the server
            fetch('get-offers.php')
                .then(response => response.json())
                .then(data => {
                    displayMyPosts(data.offers || [], user);
                })
                .catch(error => {
                    console.error('Error loading from server:', error);
                    container.innerHTML = '<p style="text-align:center; color:#666;">Error loading offers. Please refresh.</p>';
                });
        }

        function displayMyPosts(offers, user) {
            const container = document.getElementById('myPostsList');
            container.innerHTML = '';
            
            // Filter offers where sellerUsername matches current user
            const myOffers = offers.filter(o => o.sellerUsername === user.username);
            
            if (myOffers.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding: 40px; color:#666;">
                        <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>You have not posted any offers yet.</p>
                        <a href="add-offer.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px;">Add Your First Offer</a>
                    </div>`;
                return;
            }
            
            myOffers.forEach(offer => {
                const div = document.createElement('div');
                div.className = 'my-post-item';
                div.id = `offer-${offer.id}`;
                
                // Format date
                let dateText = 'Recently';
                if (offer.date) {
                    const date = new Date(offer.date);
                    dateText = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                }
                
                div.innerHTML = `
                    <img src="${offer.image || 'https://via.placeholder.com/80x80'}" class="my-post-img" onerror="this.src='https://via.placeholder.com/80x80'">
                    <div class="my-post-info">
                        <h4>${offer.title}</h4>
                        <p style="color: var(--primary); font-weight:bold;">€${offer.price}</p>
                        <small style="color: #999;">Posted: ${dateText}</small>
                    </div>
                    <div class="my-post-actions">
                        <button class="action-btn btn-edit" onclick="openEdit(${offer.id})"><i class="fas fa-edit"></i> Edit</button>
                        <button class="action-btn btn-delete" onclick="promptDelete(${offer.id})"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                `;
                container.appendChild(div);
            });
        }

        // --- DELETE POST FUNCTIONS ---
        function promptDelete(id) {
            offerToDelete = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            offerToDelete = null;
        }

        function confirmDelete() {
            if (!offerToDelete) return;
            
            // Show loading state
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            const originalText = deleteBtn.textContent;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            deleteBtn.disabled = true;
            
            // Send delete request to server
            fetch('delete-offer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: offerToDelete })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeDeleteModal();
                    showModal('Deleted!', 'Your offer has been deleted.');
                    loadMyPosts(); // Reload the posts
                } else {
                    showModal('Error!', data.message || 'Failed to delete offer');
                    closeDeleteModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModal('Error!', 'Failed to connect to server');
                closeDeleteModal();
            })
            .finally(() => {
                // Reset button
                deleteBtn.innerHTML = originalText;
                deleteBtn.disabled = false;
            });
        }

        // --- EDIT POST FUNCTIONS ---
        function openEdit(id) {
            // Get from server
            fetch('get-offers.php')
                .then(response => response.json())
                .then(data => {
                    const offers = data.offers || [];
                    const offer = offers.find(o => o.id == id);
                    if(offer) {
                        document.getElementById('editId').value = offer.id;
                        document.getElementById('editTitle').value = offer.title;
                        document.getElementById('editPrice').value = offer.price;
                        document.getElementById('editDesc').value = offer.description || '';
                        document.getElementById('editModal').style.display = 'flex';
                    } else {
                        showModal('Error!', 'Offer not found');
                    }
                })
                .catch(error => {
                    console.error('Error loading offer:', error);
                    showModal('Error!', 'Failed to load offer details');
                });
        }

        function saveEdit() {
            const id = parseInt(document.getElementById('editId').value);
            const newTitle = document.getElementById('editTitle').value.trim();
            const newPrice = document.getElementById('editPrice').value.trim();
            const newDesc = document.getElementById('editDesc').value.trim();
            
            if (!newTitle || !newPrice) {
                showModal('Error!', 'Title and price are required');
                return;
            }
            
            // Show loading state
            const saveBtn = document.querySelector('#editModal .submit-btn');
            const originalText = saveBtn.textContent;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;
            
            // Send update to server
            fetch('api/edit-offer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, title: newTitle, price: newPrice, description: newDesc })
            })
            .then(response => {
                const ct = response.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    return response.text().then(t => { throw new Error('Server error: ' + t.substring(0,200)); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('editModal').style.display = 'none';
                    showModal('Success!', 'Offer updated successfully!');
                    loadMyPosts();
                } else {
                    showModal('Error!', data.message || 'Failed to update offer');
                }
            })
            .catch(error => {
                console.error('Edit error:', error);
                showModal('Error!', error.message || 'Failed to connect to server');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }

        // --- UTILITY FUNCTIONS ---
        function showModal(title, message) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalMsg').innerText = message;
            document.getElementById('alertModal').style.display = 'flex';
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                document.getElementById('alertModal').style.display = 'none';
            }, 3000);
        }

        // Check for success message in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('edited') === '1') {
            showModal('Success!', 'Your offer has been updated.');
            window.history.replaceState({}, document.title, 'account.php');
        }
    </script>

    <!-- Theme Toggle JavaScript -->
    <script>
        (function() {
            // Theme keys
            const THEME_KEY = 'campuscycle_theme';
            const DEFAULT_THEME = 'light';
            
            // Available themes
            const themes = {
                light: { icon: '☀️', label: 'Light Mode' },
                dark: { icon: '🌙', label: 'Dark Mode' },
                'high-contrast': { icon: '◑', label: 'High Contrast' }
            };
            
            // Get current theme from localStorage or default
            function getCurrentTheme() {
                return localStorage.getItem(THEME_KEY) || DEFAULT_THEME;
            }
            
            // Set theme
            function setTheme(theme) {
                if (!themes[theme]) return;
                
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem(THEME_KEY, theme);
                
                // Update active button states
                document.querySelectorAll('.theme-btn').forEach(btn => {
                    if (btn.dataset.theme === theme) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
                
                console.log(`Theme changed to: ${theme}`);
            }
            
            // Create theme toggle buttons
            function createThemeToggle() {
                // Check if toggle already exists
                if (document.querySelector('.theme-toggle')) return;
                
                const toggleContainer = document.createElement('div');
                toggleContainer.className = 'theme-toggle';
                
                Object.keys(themes).forEach(theme => {
                    const btn = document.createElement('button');
                    btn.className = `theme-btn ${theme}`;
                    btn.dataset.theme = theme;
                    btn.setAttribute('title', themes[theme].label);
                    btn.innerHTML = themes[theme].icon;
                    
                    btn.addEventListener('click', () => setTheme(theme));
                    
                    toggleContainer.appendChild(btn);
                });
                
                document.body.appendChild(toggleContainer);
                
                // Set initial active state
                const currentTheme = getCurrentTheme();
                setTheme(currentTheme);
            }
            
            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', createThemeToggle);
            } else {
                createThemeToggle();
            }
        })();
    </script>
    
    <!-- Simple Footer -->
    <footer style="text-align: center; padding: 20px; margin-top: 40px; border-top: 1px solid #eee; color: #999;">
        <p>&copy; <?php echo date('Y'); ?> CampusCycle. <a href="privacy.php" style="color: var(--primary); text-decoration: none;">Privacy Notice</a> · <a href="contact.php" style="color: var(--primary); text-decoration: none;">Contact Us</a></p>
    </footer>
</body>
</html>