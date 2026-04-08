<?php
session_start();
require_once 'user-functions.php';
require_once 'Database.php';
$currentUser = get_logged_in_user();
$isAdmin = is_admin();
require_once 'nav-notifications.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Privacy Notice - CampusCycle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">
    <style>
        .privacy-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .privacy-container h1 {
            color: var(--primary);
            margin-bottom: 10px;
        }

        .privacy-container h2 {
            color: var(--text-dark);
            margin: 28px 0 10px;
            font-size: 1.15rem;
            border-left: 3px solid var(--primary);
            padding-left: 12px;
        }

        .privacy-container p,
        .privacy-container ul {
            color: var(--text-light);
            line-height: 1.7;
            margin-bottom: 15px;
        }

        .privacy-container ul {
            padding-left: 20px;
        }

        .privacy-container li {
            color: var(--text-light);
            margin-bottom: 6px;
        }

        .privacy-container a {
            color: var(--primary);
            text-decoration: none;
        }

        .privacy-container a:hover {
            text-decoration: underline;
        }

        .last-updated {
            color: var(--text-light);
            font-style: italic;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .privacy-container {
                margin: 20px 15px;
                padding: 20px;
            }
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
        <a href="index.php" class="logo">
            <img src="uploads/campus-logo.png" style="height:48px;width:48px;object-fit:contain;vertical-align:middle;margin-right:8px;"> CampusCycle
        </a>

        <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </div>

        <div class="nav-links">
            <a href="favorites.html" class="nav-item" title="Favorites"><i class="far fa-heart"></i></a>
            <a href="chats.php" id="chatNavLink" class="nav-item" title="Chats"><i class="far fa-comment-dots"></i><?php nav_badge(); ?></a>
            <a href="account.php" class="nav-item" title="My Account"><i class="far fa-user-circle"></i></a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" class="nav-item" title="Admin Panel" style="color: var(--primary) !important;"><i class="fas fa-shield-alt"></i></a>
            <?php endif; ?>
            <a href="contact.php" class="nav-item" title="Contact Us"><i class="far fa-envelope"></i></a>
                <a href="add-offer.php" class="nav-item btn-add-offer"><i class="fas fa-plus"></i> Add Offer</a>
        </div>
    </nav>
</header>

<div class="privacy-container">
    <h1><i class="fas fa-shield-alt"></i> Privacy Notice</h1>
    <p><em>Last updated: <?php echo date('F j, Y'); ?></em></p>

    <h2>1. Introduction</h2>
    <p>Welcome to CampusCycle. We respect your privacy and are committed to protecting your personal data. This privacy notice will inform you about how we look after your personal data when you visit our website and tell you about your privacy rights.</p>

    <h2>2. What Information We Collect</h2>
    <p>We may collect, use, store and transfer different kinds of personal data about you which we have grouped together as follows:</p>
    <ul>
        <li><strong>Identity Data:</strong> username, and any name you choose to provide in your profile.</li>
        <li><strong>Contact Data:</strong> email address and any phone number you choose to provide.</li>
        <li><strong>Profile Data:</strong> your username, password, profile picture, preferences, feedback, and survey responses.</li>
        <li><strong>Content Data:</strong> offers you post, messages you send through our chat system, and any other content you create on our platform.</li>
        <li><strong>Technical Data:</strong> internet protocol (IP) address, browser type and version, time zone setting and location, operating system and platform.</li>
        <li><strong>Usage Data:</strong> information about how you use our website, such as items you view and favourite.</li>
    </ul>

    <h2>3. How We Collect Your Information</h2>
    <p>We collect information through:</p>
    <ul>
        <li><strong>Direct interactions:</strong> When you register an account, post an offer, send messages, or update your profile.</li>
        <li><strong>Automated technologies:</strong> As you navigate through our website, we may automatically collect Technical Data about your equipment and browsing actions through cookies and similar technologies.</li>
    </ul>

    <h2>4. How We Use Your Information</h2>
    <p>We will only use your personal data when the law allows us to. Most commonly, we will use your personal data in the following circumstances:</p>
    <ul>
        <li>To register you as a new user and manage your account.</li>
        <li>To enable you to post offers and communicate with other users through our chat system.</li>
        <li>To manage our relationship with you, including notifying you about changes to our terms or privacy policy.</li>
        <li>To administer and protect our business and this website (including troubleshooting, data analysis, and system testing).</li>
        <li>To deliver relevant website content to you.</li>
    </ul>

    <h2>5. Cookies</h2>
    <p>Our website uses cookies to distinguish you from other users. This helps us provide you with a good experience when you browse our website and also allows us to improve our site. The cookies we use include:</p>
    <ul>
        <li><strong>Strictly necessary cookies:</strong> Required for the operation of our website, such as cookies that enable you to log into secure areas.</li>
        <li><strong>Functional cookies:</strong> Used to recognise you when you return to our website, enabling us to personalise our content for you.</li>
    </ul>

    <h2>6. Who We Share Your Information With</h2>
    <p>We may share your personal data with the following parties:</p>
    <ul>
        <li><strong>Other users:</strong> Your username and offer details are visible to all visitors. Your messages are shared with the users you communicate with.</li>
        <li><strong>Service providers:</strong> We may engage third-party companies to facilitate our website (e.g., hosting providers).</li>
    </ul>
    <p>We do not sell your personal data to third parties.</p>

    <h2>7. Data Security</h2>
    <p>We have put in place appropriate security measures to prevent your personal data from being accidentally lost, used, or accessed in an unauthorised way. We limit access to your personal data to those who have a business need to know.</p>

    <h2>8. Your Legal Rights</h2>
    <p>Under certain circumstances, you have rights under data protection laws in relation to your personal data, including the right to:</p>
    <ul>
        <li>Request access to your personal data.</li>
        <li>Request correction of your personal data.</li>
        <li>Request erasure of your personal data.</li>
        <li>Object to processing of your personal data.</li>
        <li>Request restriction of processing your personal data.</li>
        <li>Request transfer of your personal data.</li>
        <li>Right to withdraw consent.</li>
    </ul>
    <p>You can exercise these rights by logging into your account and using the profile editing features, or by contacting us.</p>

    <h2>9. Data Retention</h2>
    <p>We will only retain your personal data for as long as necessary to fulfil the purposes we collected it for, including for the purposes of satisfying any legal, accounting, or reporting requirements.</p>

    <h2>10. Contact Us</h2>
    <p>If you have any questions about this privacy notice or our data practices, please contact us through our <a href="account.php">account page</a>.</p>

    <div class="last-updated">
        <p>This version was last updated on <?php echo date('F j, Y'); ?>.</p>
    </div>
</div>

<script>
    function toggleMobileMenu() {
        document.querySelector('.nav-links').classList.toggle('show');
    }
    document.addEventListener('click', function(e) {
        const nav = document.querySelector('.nav-links');
        const tog = document.querySelector('.mobile-menu-toggle');
        if (nav && tog && !nav.contains(e.target) && !tog.contains(e.target)) {
            nav.classList.remove('show');
        }
    });
</script>

<!-- Floating theme toggle (matches all other pages) -->
<script>
(function() {
    const THEME_KEY = 'campuscycle_theme';
    const themes = {
        light: { icon: '☀️', label: 'Light Mode' },
        dark:  { icon: '🌙', label: 'Dark Mode' },
        'high-contrast': { icon: '◑', label: 'High Contrast' }
    };
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        document.querySelectorAll('.theme-btn').forEach(b =>
            b.classList.toggle('active', b.dataset.theme === theme));
    }
    function createThemeToggle() {
        if (document.querySelector('.theme-toggle')) return;
        const wrap = document.createElement('div');
        wrap.className = 'theme-toggle';
        Object.keys(themes).forEach(t => {
            const btn = document.createElement('button');
            btn.className = `theme-btn ${t}`;
            btn.dataset.theme = t;
            btn.title = themes[t].label;
            btn.innerHTML = `<i class="fas ${themes[t].icon}"></i>`;
            btn.addEventListener('click', () => setTheme(t));
            wrap.appendChild(btn);
        });
        document.body.appendChild(wrap);
        setTheme(localStorage.getItem(THEME_KEY) || 'light');
    }
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', createThemeToggle)
        : createThemeToggle();
})();
</script>

</body>
</html>
