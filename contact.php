<?php
session_start();
require_once 'user-functions.php';
require_once 'Database.php';
$currentUser = get_logged_in_user();
$isAdmin = is_admin();
require_once 'nav-notifications.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$subject || !$message) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Save to DB or log — for now just mark success
        // mail('support@campuscycle.com', '[Contact] ' . $subject, "From: $name <$email>\n\n$message");
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Contact Us - CampusCycle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">
    <style>
        .contact-wrapper {
            max-width: 860px;
            margin: 40px auto;
            padding: 0 16px 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }

        @media (max-width: 680px) {
            .contact-wrapper { grid-template-columns: 1fr; }
        }

        /* ── Info card ── */
        .contact-info {
            background: linear-gradient(135deg, var(--primary) 0%, #c0004e 100%);
            border-radius: 16px;
            padding: 36px 28px;
            color: #fff;
        }

        .contact-info h2 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .contact-info p {
            opacity: .88;
            line-height: 1.6;
            margin-bottom: 28px;
            font-size: .95rem;
        }

        .contact-detail {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
        }

        .contact-detail-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
        }

        .contact-detail-text strong {
            display: block;
            font-size: .85rem;
            opacity: .8;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 2px;
        }

        .contact-detail-text span {
            font-size: .95rem;
        }

        /* ── Form card ── */
        .contact-form-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 36px 28px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .contact-form-card h2 {
            color: var(--text-dark);
            font-size: 1.4rem;
            margin-bottom: 6px;
        }

        .contact-form-card .sub {
            color: var(--text-light);
            font-size: .9rem;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg);
            color: var(--text-dark);
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .2s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        @media (max-width: 480px) {
            .form-row { grid-template-columns: 1fr; }
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .1s;
            margin-top: 4px;
        }

        .btn-submit:hover { background: var(--primary-dark); }
        .btn-submit:active { transform: scale(.98); }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: .93rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #e8f8f0;
            color: #1a7a46;
            border: 1px solid #b2dfcc;
        }

        .alert-error {
            background: #fff0f3;
            color: #c0003a;
            border: 1px solid #ffc0cb;
        }

        /* Page hero strip */
        .contact-hero {
            background: linear-gradient(135deg, var(--primary) 0%, #c0004e 100%);
            padding: 40px 16px 28px;
            text-align: center;
            color: #fff;
        }

        .contact-hero h1 {
            font-size: 1.9rem;
            margin-bottom: 6px;
        }

        .contact-hero p {
            opacity: .88;
            font-size: 1rem;
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
            <a href="add-offer.php" class="nav-item btn-add-offer"><i class="fas fa-plus"></i> Add Offer</a>
        </div>
    </nav>
</header>

<!-- Hero strip -->
<div class="contact-hero">
    <h1>Get in Touch</h1>
    <p>Have a question, issue, or just want to say hi? We'd love to hear from you.</p>
</div>

<!-- Main content -->
<div class="contact-wrapper">

    <!-- Info panel -->
    <div class="contact-info">
        <h2>We're here to help</h2>
        <p>Whether you have a question about an offer, need help with your account, or want to report an issue — our team is just a message away.</p>

        <div class="contact-detail">
            <div class="contact-detail-icon"><i class="fas fa-envelope"></i></div>
            <div class="contact-detail-text">
                <strong>Email</strong>
                <span>support@campuscycle.com</span>
            </div>
        </div>

        <div class="contact-detail">
            <div class="contact-detail-icon"><i class="fas fa-clock"></i></div>
            <div class="contact-detail-text">
                <strong>Response time</strong>
                <span>Usually within 24 hours</span>
            </div>
        </div>

        <div class="contact-detail">
            <div class="contact-detail-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="contact-detail-text">
                <strong>Campus</strong>
                <span>Your local student marketplace</span>
            </div>
        </div>

        <div class="contact-detail">
            <div class="contact-detail-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="contact-detail-text">
                <strong>Safety concerns</strong>
                <span>Report suspicious listings immediately</span>
            </div>
        </div>
    </div>

    <!-- Form panel -->
    <div class="contact-form-card">
        <h2>Send a message</h2>
        <p class="sub">Fill in the form and we'll get back to you.</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Thanks! Your message has been sent. We'll be in touch soon.
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="contact.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Your name *</label>
                    <input type="text" id="name" name="name" placeholder="Jane Smith" required value="<?php echo htmlspecialchars($_POST['name'] ?? ($currentUser['username'] ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email address *</label>
                    <input type="email" id="email" name="email" placeholder="jane@uni.ie" required value="<?php echo htmlspecialchars($_POST['email'] ?? ($currentUser['email'] ?? '')); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="subject">Subject *</label>
                <select id="subject" name="subject" required>
                    <option value="" disabled <?php echo empty($_POST['subject']) ? 'selected' : ''; ?>>Choose a topic…</option>
                    <option value="General question" <?php echo ($_POST['subject'] ?? '') === 'General question' ? 'selected' : ''; ?>>General question</option>
                    <option value="Problem with an offer" <?php echo ($_POST['subject'] ?? '') === 'Problem with an offer' ? 'selected' : ''; ?>>Problem with an offer</option>
                    <option value="Account issue" <?php echo ($_POST['subject'] ?? '') === 'Account issue' ? 'selected' : ''; ?>>Account issue</option>
                    <option value="Report a user" <?php echo ($_POST['subject'] ?? '') === 'Report a user' ? 'selected' : ''; ?>>Report a user</option>
                    <option value="Feedback / suggestion" <?php echo ($_POST['subject'] ?? '') === 'Feedback / suggestion' ? 'selected' : ''; ?>>Feedback / suggestion</option>
                    <option value="Other" <?php echo ($_POST['subject'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message" name="message" placeholder="Tell us what's on your mind…" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Send message
            </button>
        </form>
        <?php endif; ?>
    </div>

</div>

<!-- Footer -->
<footer style="text-align: center; padding: 20px; margin-top: 40px; border-top: 1px solid var(--border-color); color: #999;">
    <p>&copy; <?php echo date('Y'); ?> CampusCycle.
        <a href="privacy.php" style="color: var(--primary); text-decoration: none;">Privacy Notice</a> ·
        <a href="contact.php" style="color: var(--primary); text-decoration: none;">Contact Us</a>
    </p>
</footer>

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

<!-- Floating theme toggle -->
<script>
(function() {
    const THEME_KEY = 'campuscycle_theme';
    const themes = {
        light: { icon: '☀️', label: 'Light Mode' },
        dark: { icon: '🌙', label: 'Dark Mode' },
        'high-contrast': { icon: '◑', label: 'High Contrast' }
    };
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        document.querySelectorAll('.theme-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.theme === theme);
        });
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
            btn.innerHTML = themes[t].icon;
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
