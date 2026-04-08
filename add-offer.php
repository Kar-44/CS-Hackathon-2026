<?php
// Start session and check login at the server level
session_start();
require_once 'user-functions.php';

// Check if user is logged in via PHP session
$user = get_logged_in_user();

// If not logged in, redirect to login page with redirect parameter
if (!$user) {
    header('Location: account.php?redirect=add-offer.php');
    exit;
}

// Pass user data to JavaScript
$currentUser = $user;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Add Offer - CampusCycle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">

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
            <a href="index.php" class="logo">
                <img src="uploads/campus-logo.png" style="height:48px;width:48px;object-fit:contain;vertical-align:middle;margin-right:8px;"> CampusCycle
            </a>
            
            <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </div>
            
            <div class="nav-links">
                <a href="favorites.html" class="nav-item"><i class="far fa-heart"></i></a>
                <a href="chats.html" class="nav-item"><i class="far fa-comment-dots"></i></a>
                <a href="account.php" class="nav-item"><i class="far fa-user-circle"></i></a>
                <a href="contact.php" class="nav-item" title="Contact Us"><i class="far fa-envelope"></i></a>
                <a href="add-offer.php" class="nav-item btn-add-offer"><i class="fas fa-plus"></i> Add Offer</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="form-container">
            <h2 style="margin-bottom: 30px; color: var(--primary);">Add New Offer</h2>
            
            <?php if (isset($_GET['error'])): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <form action="save-offer.php" method="POST" enctype="multipart/form-data" id="offerForm">
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" class="form-control" required>
                        <option value="">Select a category</option>
                        <option value="Textbooks">Textbooks</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Furniture">Dorm Furniture</option>
                        <option value="Transport">Transport</option>
                        <option value="Clothing">Clothing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="price">Price (€) *</label>
                    <div class="price-wrapper">
                        <span class="price-symbol">€</span>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required maxlength="300" oninput="updateCharCount(this)" style="resize:vertical; max-height:200px;"></textarea>
                    <small id="descCharCount" style="color:var(--text-light); float:right;">0 / 300</small>
                </div>

                <div class="form-group">
                    <label for="image">Upload Image</label>
                    <div class="image-upload-box" id="imageUploadBox">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary);"></i>
                        <p>Click to upload an image</p>
                        <small style="color: #999;">Max size: 2MB (JPG, PNG, GIF)</small>
                        <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                    </div>
                    <div id="imagePreview" style="display: none; margin-top: 10px;">
                        <img src="#" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                        <button type="button" onclick="removeImage()" style="display: block; margin-top: 5px; padding: 5px; background: #ff4757; color: white; border: none; border-radius: 4px; cursor: pointer;">Remove</button>
                    </div>
                </div>

                <!-- Hidden username field -->
                <input type="hidden" name="username" id="usernameField" value="<?php echo htmlspecialchars($currentUser['username']); ?>">

                <button type="submit" class="submit-btn">Post Offer</button>
            </form>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-box">
            <div class="modal-icon"><i class="fas fa-check-circle"></i></div>
            <h3>Success!</h3>
            <p>Your offer has been posted.</p>
            <button class="submit-btn" onclick="redirectToHome()" style="margin-top: 15px;">OK</button>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal-overlay" id="errorModal">
        <div class="modal-box">
            <div class="modal-icon" style="color: #ff4757;"><i class="fas fa-exclamation-circle"></i></div>
            <h3>Error!</h3>
            <p id="errorMessage">Something went wrong.</p>
            <button class="submit-btn" onclick="document.getElementById('errorModal').style.display='none'" style="margin-top: 15px; background: #ff4757;">OK</button>
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

        // Get user from PHP (most reliable)
        const currentUser = <?php echo json_encode($currentUser); ?>;
        
        // Sync to localStorage to keep everything consistent
        localStorage.setItem('currentUser', JSON.stringify(currentUser));
        
        console.log('Current user from PHP:', currentUser);

        // Handle form submission
        document.getElementById('offerForm').addEventListener('submit', function(e) {
            // Double-check that username field has value
            const usernameField = document.getElementById('usernameField');
            
            if (!usernameField.value || usernameField.value === '') {
                console.log('Username field empty, setting from currentUser');
                usernameField.value = currentUser.username;
            }
            
            console.log('Submitting with username:', usernameField.value);
            
            // Show loading state
            const submitBtn = this.querySelector('.submit-btn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
            submitBtn.disabled = true;
            
            // Don't prevent default - let the form submit normally
        });

        // Handle image upload box click
        document.getElementById('imageUploadBox').addEventListener('click', function() {
            document.getElementById('image').click();
        });

        // Preview image when selected
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showError('File is too large. Max size is 2MB.');
                    this.value = '';
                    return;
                }
                
                // Check file type
                if (!file.type.match('image.*')) {
                    showError('Please select an image file (JPG, PNG, GIF)');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').style.display = 'block';
                    document.getElementById('imagePreview').querySelector('img').src = e.target.result;
                    document.getElementById('imageUploadBox').style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });

        function removeImage() {
            document.getElementById('image').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('imageUploadBox').style.display = 'block';
        }

        function redirectToHome() {
            window.location.href = 'index.php';
        }
        
        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').style.display = 'flex';
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                document.getElementById('errorModal').style.display = 'none';
            }, 3000);
        }

        // Check for success message in URL (if redirected back)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            document.getElementById('successModal').style.display = 'flex';
            // Remove the success parameter from URL without refreshing
            window.history.replaceState({}, document.title, 'add-offer.php');
        }
        
        // Check for error message in URL
        if (urlParams.get('error')) {
            showError(decodeURIComponent(urlParams.get('error')));
            window.history.replaceState({}, document.title, 'add-offer.php');
        }

        function updateCharCount(el) {
            const count = el.value.length;
            const counter = document.getElementById('descCharCount');
            if (counter) {
                counter.textContent = count + ' / 300';
                counter.style.color = count > 270 ? 'var(--primary)' : 'var(--text-light)';
            }
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
</body>
</html>