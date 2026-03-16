<?php
// Start session to get user favorites
session_start();
require_once 'user-functions.php';
require_once 'Database.php';

// Get current user for favorites
$currentUser = get_logged_in_user();
$isAdmin = is_admin();
require_once 'nav-notifications.php';
$userFavorites = $currentUser ? ($currentUser['favorites'] ?? []) : [];

// Load offers from database
$offers = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT o.*, u.username as seller, u.avatar as seller_avatar
        FROM offers o
        JOIN users u ON o.seller_id = u.id
        ORDER BY o.created DESC
    ");
    $offers = $stmt->fetchAll();
    
    // Format for frontend compatibility
    foreach ($offers as &$offer) {
        $offer['sellerUsername'] = $offer['seller'];
        $offer['date'] = $offer['created'];
    }
} catch (Exception $e) {
    error_log("Error loading offers: " . $e->getMessage());
    $offers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>CampusCycle - Student Marketplace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">
    <style>
        /* Additional avatar styles */
        .seller-avatar-img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
        }
        
        /* Load More button */
        .load-more-container {
            text-align: center;
            margin: 30px 0;
            width: 100%;
        }
        
        .load-more-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .load-more-btn:hover {
            background: var(--primary-dark);
        }
        
        .load-more-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .load-more-btn i {
            font-size: 1rem;
        }
        
        /* End of results message */
        .end-message {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
            width: 100%;
        }
    </style>

    <style>
        /* ===== CRITICAL OVERRIDES - DO NOT REMOVE ===== */
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
                <a href="favorites.html" class="nav-item" title="Favorites"><i class="far fa-heart"></i></a>
                <a href="chats.php" class="nav-item" title="Chats"><i class="far fa-comment-dots"></i></a>
                <a href="account.php" class="nav-item" title="My Account"><i class="far fa-user-circle"></i></a>
                <?php if ($isAdmin): ?>
                    <a href="admin.php" class="nav-item" title="Admin Panel" style="color: var(--primary);"><i class="fas fa-cog"></i> Admin</a>
                <?php endif; ?>
                <a href="contact.php" class="nav-item" title="Contact Us"><i class="far fa-envelope"></i></a>
                <a href="add-offer.php" class="nav-item btn-add-offer"><i class="fas fa-plus"></i> Add Offer</a>
            </div>
        </nav>
    </header>

    <section class="hero">
        <h1>Find what you need on campus.</h1>
        <br>
        <div class="search-container">
            <input type="text" class="search-input" id="searchInput" placeholder="Search for textbooks, furniture, electronics...">
            <button class="search-btn" onclick="searchOffers()"><i class="fas fa-search"></i></button>
        </div>
    </section>

    <div class="container">
        
        <aside>
            <div class="category-list">
                <h3>Categories</h3>
                <ul>
                    <li onclick="filterCategory('all', this)"><i class="fas fa-border-all"></i> All</li>
                    <li onclick="filterCategory('Textbooks', this)"><i class="fas fa-book"></i> Textbooks</li>
                    <li onclick="filterCategory('Electronics', this)"><i class="fas fa-laptop"></i> Electronics</li>
                    <li onclick="filterCategory('Furniture', this)"><i class="fas fa-couch"></i> Dorm Furniture</li>
                    <li onclick="filterCategory('Transport', this)"><i class="fas fa-bicycle"></i> Transport</li>
                    <li onclick="filterCategory('Clothing', this)"><i class="fas fa-tshirt"></i> Clothing</li>
                    <li onclick="filterCategory('Other', this)"><i class="fas fa-tag"></i> Other</li>
                </ul>
            </div>
        </aside>

        <div class="offers-feed" id="offersFeed">
            
            <?php if (empty($offers)): ?>
                <!-- Show empty state message when no offers exist -->
                <div style="text-align: center; padding: 60px 20px; width: 100%;">
                    <i class="fas fa-box-open" style="font-size: 4rem; color: #ccc; margin-bottom: 20px;"></i>
                    <h3 style="color: #666; margin-bottom: 10px;">No offers yet</h3>
                    <p style="color: #999; margin-bottom: 25px;">Be the first to post an item for sale!</p>
                    <a href="add-offer.php" style="display: inline-block; padding: 12px 30px; background: var(--primary); color: white; text-decoration: none; border-radius: 25px; font-weight: 600;">+ Post an Offer</a>
                </div>
            <?php else: ?>
                <!-- Show real offers from the database -->
                <?php 
                $offersPerPage = 10;
                $totalOffers = count($offers);
                $showedOffers = array_slice($offers, 0, $offersPerPage);
                $remainingOffers = $totalOffers - $offersPerPage;
                ?>
                
                <div id="initialOffers">
                    <?php foreach ($showedOffers as $offer): 
                        $isFavorited = in_array($offer['id'], $userFavorites);
                        $heartIcon = $isFavorited ? 'fa-solid' : 'fa-regular';
                        $seller = $offer['seller'] ?? 'Anonymous';
                    ?>
                        <div class="offer-card" data-category="<?php echo htmlspecialchars($offer['category'] ?? 'Other'); ?>" onclick="trackView(<?php echo $offer['id']; ?>)">
                            <button class="heart-btn <?php echo $isFavorited ? 'liked' : ''; ?>" onclick="toggleLike(this, <?php echo $offer['id']; ?>); event.stopPropagation();">
                                <i class="<?php echo $heartIcon; ?> fa-heart"></i>
                            </button>
                            <div class="card-image">
                                <img src="<?php 
                                    $imagePath = $offer['image'] ?? 'https://via.placeholder.com/200x150';
                                    echo htmlspecialchars($imagePath); 
                                ?>" alt="<?php echo htmlspecialchars($offer['title'] ?? 'Offer'); ?>">
                            </div>
                            <div class="card-details">
                                <div>
                                    <div class="card-header">
                                        <div class="title-price-wrapper">
                                            <h3 class="offer-title"><?php echo htmlspecialchars($offer['title'] ?? 'Untitled'); ?></h3>
                                            <span class="offer-price">€<?php echo htmlspecialchars($offer['price'] ?? '0'); ?></span>
                                        </div>
                                    </div>
                                    <p class="offer-desc"><?php echo htmlspecialchars($offer['description'] ?? 'No description'); ?></p>
                                </div>
                                <div class="card-footer">
                                    <div class="seller-info">
                                        <img src="<?php echo $offer['seller_avatar'] ?? 'https://via.placeholder.com/30'; ?>" class="seller-avatar-img" alt="<?php echo htmlspecialchars($seller); ?>">
                                        <span><?php echo htmlspecialchars($seller); ?> • 
                                        <?php 
                                            // Format the date nicely
                                            if (isset($offer['date'])) {
                                                $date = new DateTime($offer['date']);
                                                $now = new DateTime();
                                                $diff = $now->diff($date);
                                                
                                                if ($diff->d > 0) {
                                                    echo $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
                                                } elseif ($diff->h > 0) {
                                                    echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                                } elseif ($diff->i > 0) {
                                                    echo $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                                } else {
                                                    echo 'Just now';
                                                }
                                            } else {
                                                echo 'Recently';
                                            }
                                        ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="contact-btn" onclick="setPriceAlert(<?php echo $offer['id']; ?>, '<?php echo htmlspecialchars(addslashes($offer['title'])); ?>', '<?php echo htmlspecialchars($offer['image'] ?? 'https://via.placeholder.com/200x150'); ?>', <?php echo $offer['price']; ?>); event.stopPropagation();" style="padding: 6px 10px;">
                                            <i class="fas fa-bell"></i>
                                        </button>
                                        <button class="contact-btn" onclick="startChat(<?php echo $offer['id']; ?>, '<?php echo htmlspecialchars($offer['seller'] ?? ''); ?>', this); event.stopPropagation();">Message</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Container for more offers to be loaded -->
                <div id="moreOffers" style="display:flex; flex-direction:column; gap:40px; margin-top:40px;"></div>
                
                <!-- Load More Button -->
                <?php if ($remainingOffers > 0): ?>
                <div class="load-more-container" id="loadMoreContainer">
                    <button class="load-more-btn" id="loadMoreBtn" onclick="loadMoreOffers()">
                        <i class="fas fa-arrow-down"></i> Load More (<?php echo $remainingOffers; ?> remaining)
                    </button>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
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

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-box">
            <div class="modal-icon" style="color: #4CAF50;"><i class="fas fa-check-circle"></i></div>
            <h3>Success!</h3>
            <p id="successMessage">Price alert set successfully!</p>
            <button class="submit-btn" onclick="document.getElementById('successModal').style.display='none'" style="margin-top: 15px; background: #4CAF50;">OK</button>
        </div>
    </div>

    <!-- Price Alert Modal -->
    <div class="modal-overlay" id="priceAlertModal" style="display:none;">
        <div class="modal-box" style="text-align:left; max-width:360px;">
            <div class="modal-icon" style="color:var(--primary); text-align:center;"><i class="fas fa-bell"></i></div>
            <h3 style="text-align:center; margin-bottom:4px;">Set Price Alert</h3>
            <p id="priceAlertOfferName" style="text-align:center; color:var(--text-light); font-size:.9rem; margin-bottom:16px;"></p>
            <div class="form-group" style="margin-bottom:8px;">
                <label style="font-size:.85rem; font-weight:600; color:var(--text-dark);">Current price</label>
                <p id="priceAlertCurrent" style="font-size:1.1rem; font-weight:700; color:var(--primary); margin:4px 0 12px;"></p>
                <label style="font-size:.85rem; font-weight:600; color:var(--text-dark);">Alert me when price drops to (€)</label>
                <input type="number" id="priceAlertTarget" class="form-control" step="0.01" min="0.01"
                    style="margin-top:6px; padding:10px 14px; border:1.5px solid var(--border-color); border-radius:8px; background:var(--bg); color:var(--text-dark); font-size:1rem; width:100%; box-sizing:border-box;">
            </div>
            <div style="display:flex; gap:10px; margin-top:18px;">
                <button class="submit-btn" onclick="confirmPriceAlert()" style="flex:1;">Set Alert</button>
                <button class="submit-btn" onclick="document.getElementById('priceAlertModal').style.display='none'" style="flex:1; background:#ccc;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP variables to JavaScript
        const currentUser = <?php echo json_encode($currentUser); ?>;
        const userFavorites = <?php echo json_encode($userFavorites); ?>;
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        
        // Store all offers for pagination
        const allOffers = <?php echo json_encode($offers); ?>;
        let currentPage = 1;
        const offersPerPage = 10;
        let isLoading = false;

        // Recently Viewed constants
        const RECENT_VIEWS_KEY = 'recentlyViewed';
        const MAX_RECENT_ITEMS = 20;

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

        // --- GLOBAL HELPERS ---
        function getCurrent() { 
            return JSON.parse(localStorage.getItem('currentUser')); 
        }
        function saveCurrent(user) { 
            localStorage.setItem('currentUser', JSON.stringify(user)); 
        }

        // --- RECENTLY VIEWED FUNCTIONS ---
        function getRecentlyViewed() {
            return JSON.parse(localStorage.getItem(RECENT_VIEWS_KEY)) || [];
        }

        function trackView(offerId) {
            let recent = getRecentlyViewed();
            recent = recent.filter(id => id !== offerId);
            recent.unshift(offerId);
            if (recent.length > MAX_RECENT_ITEMS) {
                recent = recent.slice(0, MAX_RECENT_ITEMS);
            }
            localStorage.setItem(RECENT_VIEWS_KEY, JSON.stringify(recent));
        }

        function clearRecentlyViewed() {
            localStorage.removeItem(RECENT_VIEWS_KEY);
        }

        // --- SHOW ERROR FUNCTION ---
        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').style.display = 'flex';
            setTimeout(() => {
                document.getElementById('errorModal').style.display = 'none';
            }, 3000);
        }

        // --- SHOW SUCCESS FUNCTION ---
        function showSuccess(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').style.display = 'flex';
            setTimeout(() => {
                document.getElementById('successModal').style.display = 'none';
            }, 2000);
        }

        // --- LOAD MORE OFFERS FUNCTION ---
        function loadMoreOffers() {
            if (isLoading) return;
            isLoading = true;
            
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const originalText = loadMoreBtn.innerHTML;
            loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            loadMoreBtn.disabled = true;
            
            setTimeout(() => {
                currentPage++;
                const startIndex = (currentPage - 1) * offersPerPage;
                const endIndex = startIndex + offersPerPage;
                const nextOffers = allOffers.slice(startIndex, endIndex);
                
                if (nextOffers.length > 0) {
                    appendOffers(nextOffers);
                }
                
                const remainingOffers = allOffers.length - (currentPage * offersPerPage);
                const loadMoreContainer = document.getElementById('loadMoreContainer');
                
                if (remainingOffers <= 0) {
                    loadMoreContainer.style.display = 'none';
                    const feed = document.getElementById('offersFeed');
                    const endMsg = document.createElement('div');
                    endMsg.className = 'end-message';
                    endMsg.innerHTML = '<i class="fas fa-check-circle"></i> You\'ve seen all offers!';
                    feed.appendChild(endMsg);
                } else {
                    loadMoreBtn.innerHTML = `<i class="fas fa-arrow-down"></i> Load More (${remainingOffers} remaining)`;
                    loadMoreBtn.disabled = false;
                }
                
                isLoading = false;
            }, 500);
        }

        // --- APPEND OFFERS TO FEED ---
        function appendOffers(offers) {
            const moreOffersDiv = document.getElementById('moreOffers');
            
            offers.forEach(offer => {
                const isFavorited = userFavorites && userFavorites.includes(offer.id);
                const heartIcon = isFavorited ? 'fa-solid' : 'fa-regular';
                const seller = offer.seller || 'Anonymous';
                
                const offerHtml = `
                    <div class="offer-card" data-category="${offer.category || 'Other'}">
                        <button class="heart-btn ${isFavorited ? 'liked' : ''}" onclick="toggleLike(this, ${offer.id}); event.stopPropagation();">
                            <i class="${heartIcon} fa-heart"></i>
                        </button>
                        <div class="card-image">
                            <img src="${offer.image || 'https://via.placeholder.com/200x150'}" alt="${offer.title}">
                        </div>
                        <div class="card-details">
                            <div>
                                <div class="card-header">
                                    <div class="title-price-wrapper">
                                        <h3 class="offer-title">${offer.title}</h3>
                                        <span class="offer-price">€${offer.price}</span>
                                    </div>
                                </div>
                                <p class="offer-desc">${offer.description || 'No description'}</p>
                            </div>
                            <div class="card-footer">
                                <div class="seller-info">
                                    <img src="${offer.seller_avatar || 'https://via.placeholder.com/30'}" class="seller-avatar-img" alt="${seller}">
                                    <span>${seller} • Recently</span>
                                </div>
                                <div style="display: flex; gap: 5px;">
                                    <button class="contact-btn" onclick="setPriceAlert(${offer.id}, '${offer.title.replace(/'/g, "\\'")}', '${offer.image || 'https://via.placeholder.com/200x150'}', ${offer.price}); event.stopPropagation();">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                    <button class="contact-btn" onclick="startChat(${offer.id}, '${offer.seller || ''}', this); event.stopPropagation();">Message</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                moreOffersDiv.insertAdjacentHTML('beforeend', offerHtml);
            });
        }

        // --- SET PRICE ALERT FUNCTION ---
        let _priceAlertData = {};

        function setPriceAlert(offerId, offerTitle, offerImage, currentPrice) {
            let user = getCurrent();
            if (!user) {
                showError("Please login to set price alerts.");
                setTimeout(() => window.location.href = 'account.php', 1500);
                return;
            }
            _priceAlertData = { offerId, offerTitle, offerImage, currentPrice, btn: event.target };
            document.getElementById('priceAlertOfferName').textContent = offerTitle;
            document.getElementById('priceAlertCurrent').textContent = '€' + parseFloat(currentPrice).toFixed(2);
            document.getElementById('priceAlertTarget').value = (currentPrice * 0.8).toFixed(2);
            document.getElementById('priceAlertModal').style.display = 'flex';
            setTimeout(() => document.getElementById('priceAlertTarget').focus(), 100);
        }

        function confirmPriceAlert() {
            const { offerId, offerTitle, offerImage, currentPrice, btn } = _priceAlertData;
            const targetPriceNum = parseFloat(document.getElementById('priceAlertTarget').value);
            if (isNaN(targetPriceNum) || targetPriceNum <= 0) {
                showError("Please enter a valid price.");
                return;
            }
            document.getElementById('priceAlertModal').style.display = 'none';
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            fetch('api/price-alerts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ offerId, offerTitle, offerImage, targetPrice: targetPriceNum, currentPrice })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { showSuccess('🔔 Price alert set! We\'ll notify you when the price drops.'); }
                else { showError(data.message || 'Failed to set alert'); }
            })
            .catch(() => showError('Failed to connect to server'))
            .finally(() => { btn.innerHTML = originalHtml; btn.disabled = false; });
        }

        // --- TOGGLE LIKE FUNCTION ---
        function toggleLike(btn, offerId) {
            let user = getCurrent();
            
            if (!user) {
                showError("Please login to save favorites.");
                setTimeout(() => window.location.href = 'account.php', 1500);
                return;
            }

            const wasLiked = btn.classList.contains('liked');
            
            if (wasLiked) {
                btn.classList.remove('liked');
                btn.querySelector('i').className = 'fa-regular fa-heart';
            } else {
                btn.classList.add('liked');
                btn.querySelector('i').className = 'fa-solid fa-heart';
            }

            btn.disabled = true;

            fetch('api/toggle-favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ offerId: offerId, action: 'toggle' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (user) {
                        user.favorites = data.favorites;
                        localStorage.setItem('currentUser', JSON.stringify(user));
                    }
                } else {
                    if (wasLiked) {
                        btn.classList.add('liked');
                        btn.querySelector('i').className = 'fa-solid fa-heart';
                    } else {
                        btn.classList.remove('liked');
                        btn.querySelector('i').className = 'fa-regular fa-heart';
                    }
                    showError(data.message || 'Failed to update favorite');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (wasLiked) {
                    btn.classList.add('liked');
                    btn.querySelector('i').className = 'fa-solid fa-heart';
                } else {
                    btn.classList.remove('liked');
                    btn.querySelector('i').className = 'fa-regular fa-heart';
                }
                showError('Failed to connect to server');
            })
            .finally(() => {
                btn.disabled = false;
            });
        }

        // --- START CHAT FUNCTION ---
        function startChat(offerId, sellerUsername, btn) {
            let user = getCurrent();
            
            if (!user) {
                showError("Please login to message sellers.");
                setTimeout(() => window.location.href = 'account.php', 1500);
                return;
            }
            
            if (sellerUsername === user.username) {
                showError("This is your own offer!");
                return;
            }
            
            const originalText = btn.textContent;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            fetch('api/start-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ offerId: offerId, seller: sellerUsername })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'chats.php?chat=' + data.chatId;
                } else {
                    showError(data.message || 'Failed to start chat');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Failed to connect to server');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        // --- SEARCH FUNCTION ---
        function searchOffers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const offers = document.querySelectorAll('.offer-card');
            let visibleCount = 0;
            const isFiltering = searchTerm.length > 0;
            
            // Hide/show load more during search
            const loadMoreContainer = document.getElementById('loadMoreContainer');
            if (loadMoreContainer) loadMoreContainer.style.display = isFiltering ? 'none' : '';
            
            offers.forEach(offer => {
                const title = offer.querySelector('.offer-title')?.textContent.toLowerCase() || '';
                const desc = offer.querySelector('.offer-desc')?.textContent.toLowerCase() || '';
                
                if (title.includes(searchTerm) || desc.includes(searchTerm)) {
                    offer.style.display = 'flex';
                    visibleCount++;
                } else {
                    offer.style.display = 'none';
                }
            });
            
            const feed = document.getElementById('offersFeed');
            let noResultsMsg = document.getElementById('noResultsMsg');
            
            if (visibleCount === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.style.textAlign = 'center';
                    noResultsMsg.style.padding = '40px';
                    noResultsMsg.style.color = '#666';
                    noResultsMsg.style.width = '100%';
                    noResultsMsg.innerHTML = `
                        <i class="fas fa-search" style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i>
                        <p>No offers match your search.</p>
                    `;
                    feed.appendChild(noResultsMsg);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }

        // --- FILTER BY CATEGORY ---
        function filterCategory(category, element) {
            const offers = document.querySelectorAll('.offer-card');
            let visibleCount = 0;
            
            // Hide load more when filtering by category (not 'all')
            const loadMoreContainer = document.getElementById('loadMoreContainer');
            if (loadMoreContainer) loadMoreContainer.style.display = category === 'all' ? '' : 'none';
            
            document.querySelectorAll('.category-list li').forEach(li => {
                li.style.backgroundColor = '';
                li.style.color = '';
            });
            element.style.backgroundColor = 'var(--primary-light)';
            element.style.color = 'var(--primary-dark)';
            
            offers.forEach(offer => {
                const offerCategory = offer.dataset.category;
                
                if (category === 'all' || offerCategory === category) {
                    offer.style.display = 'flex';
                    visibleCount++;
                } else {
                    offer.style.display = 'none';
                }
            });
            
            const feed = document.getElementById('offersFeed');
            let noResultsMsg = document.getElementById('noResultsMsg');
            
            if (visibleCount === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.style.textAlign = 'center';
                    noResultsMsg.style.padding = '40px';
                    noResultsMsg.style.color = '#666';
                    noResultsMsg.style.width = '100%';
                    noResultsMsg.innerHTML = `
                        <i class="fas fa-filter" style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i>
                        <p>No offers in this category.</p>
                    `;
                    feed.appendChild(noResultsMsg);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }

        // Add enter key support for search
        document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchOffers();
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (currentUser) {
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
            }
        });

        // Check for success message in URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            console.log('Offer posted successfully!');
            window.history.replaceState({}, document.title, 'index.php');
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