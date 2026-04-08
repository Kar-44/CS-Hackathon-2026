<?php
session_start();
require_once 'user-functions.php';
require_once 'Database.php';

if (!is_admin()) {
    header('Location: account.php?redirect=admin.php');
    exit;
}

$currentUser = get_logged_in_user();
$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// ── Handle POST actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {

        case 'delete_user':
            $username = trim($_POST['username'] ?? '');
            if ($username === $currentUser['username']) {
                $error = 'You cannot delete your own account.';
            } else {
                try {
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $uid = $stmt->fetchColumn();
                    if ($uid) {
                        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                        $message = "User \"$username\" deleted successfully.";
                    } else { $error = 'User not found.'; }
                } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            }
            break;

        case 'ban_user':
            $username = trim($_POST['username'] ?? '');
            if ($username === $currentUser['username']) { $error = 'You cannot ban yourself.'; }
            else {
                try {
                    $db->prepare("UPDATE users SET is_banned = 1 WHERE username = ?")->execute([$username]);
                    $message = "User \"$username\" has been banned.";
                } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            }
            break;

        case 'unban_user':
            $username = trim($_POST['username'] ?? '');
            try {
                $db->prepare("UPDATE users SET is_banned = 0 WHERE username = ?")->execute([$username]);
                $message = "User \"$username\" has been unbanned.";
            } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            break;

        case 'make_admin':
            $username = trim($_POST['username'] ?? '');
            try {
                $db->prepare("UPDATE users SET is_admin = 1 WHERE username = ?")->execute([$username]);
                $message = "\"$username\" is now an admin.";
            } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            break;

        case 'remove_admin':
            $username = trim($_POST['username'] ?? '');
            if ($username === $currentUser['username']) { $error = 'You cannot remove your own admin status.'; }
            else {
                try {
                    $db->prepare("UPDATE users SET is_admin = 0 WHERE username = ?")->execute([$username]);
                    $message = "Admin privileges removed from \"$username\".";
                } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            }
            break;

        case 'reset_password':
            $username = trim($_POST['username'] ?? '');
            $newPass  = trim($_POST['new_password'] ?? '');
            if (strlen($newPass) < 6) { $error = 'Password must be at least 6 characters.'; }
            else {
                try {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE users SET password = ? WHERE username = ?")->execute([$hash, $username]);
                    $message = "Password for \"$username\" has been reset.";
                } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            }
            break;

        case 'delete_offer':
            $offerId = (int)($_POST['offer_id'] ?? 0);
            try {
                $stmt = $db->prepare("SELECT image FROM offers WHERE id = ?");
                $stmt->execute([$offerId]);
                $offer = $stmt->fetch();
                if ($offer) {
                    if (!empty($offer['image']) && strpos($offer['image'], 'uploads/') === 0 && file_exists($offer['image'])) {
                        unlink($offer['image']);
                    }
                    $db->prepare("DELETE FROM offers WHERE id = ?")->execute([$offerId]);
                    $message = "Offer #$offerId deleted.";
                } else { $error = 'Offer not found.'; }
            } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            break;

        case 'delete_chat':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            try {
                $db->prepare("DELETE FROM messages WHERE chat_id = ?")->execute([$chatId]);
                $db->prepare("DELETE FROM chats WHERE id = ?")->execute([$chatId]);
                $message = "Chat #$chatId deleted.";
            } catch (Exception $e) { $error = 'Error: ' . $e->getMessage(); }
            break;
    }
}

// Ensure is_banned column exists
try {
    $check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_banned'");
    $check->execute();
    if ($check->fetchColumn() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {}

// ── Load stats ─────────────────────────────────────────────────────────────────
try {
    $totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalOffers   = $db->query("SELECT COUNT(*) FROM offers")->fetchColumn();
    $activeOffers  = $db->query("SELECT COUNT(*) FROM offers WHERE status = 'active' OR status IS NULL")->fetchColumn();
    $totalChats    = $db->query("SELECT COUNT(*) FROM chats")->fetchColumn();
    $totalMessages = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $bannedUsers   = $db->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = $totalOffers = $activeOffers = $totalChats = $totalMessages = $bannedUsers = 0;
}

// ── Load users ─────────────────────────────────────────────────────────────────
try {
    $users = $db->query("
        SELECT u.id, u.username, u.email, u.full_name, u.registered, u.last_login,
               u.is_admin, u.is_banned,
               COUNT(DISTINCT o.id) AS offer_count
        FROM users u
        LEFT JOIN offers o ON o.seller_id = u.id
        GROUP BY u.id
        ORDER BY u.registered DESC
    ")->fetchAll();
} catch (Exception $e) { $users = []; }

// ── Load offers ────────────────────────────────────────────────────────────────
try {
    $offers = $db->query("
        SELECT o.id, o.title, o.price, o.category, o.status, o.created, o.image,
               u.username AS seller
        FROM offers o
        JOIN users u ON o.seller_id = u.id
        ORDER BY o.created DESC
    ")->fetchAll();
} catch (Exception $e) { $offers = []; }

// ── Load chats ─────────────────────────────────────────────────────────────────
try {
    $chats = $db->query("
        SELECT c.id, c.created, c.last_message_time,
               o.title AS offer_title,
               buyer.username  AS buyer,
               seller.username AS seller,
               COUNT(m.id) AS message_count
        FROM chats c
        JOIN offers o ON c.offer_id = o.id
        JOIN users buyer  ON c.buyer_id  = buyer.id
        JOIN users seller ON c.seller_id = seller.id
        LEFT JOIN messages m ON m.chat_id = c.id
        GROUP BY c.id
        ORDER BY c.last_message_time DESC
        LIMIT 100
    ")->fetchAll();
} catch (Exception $e) { $chats = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel – CampusCycle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=2">
    <style>
        * { box-sizing: border-box; }

        .admin-wrap { max-width: 1300px; margin: 0 auto; padding: 24px 20px; }

        /* ── Stats ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--card-bg); border-radius: 12px; padding: 20px; text-align: center; box-shadow: var(--shadow); border-top: 4px solid var(--primary); }
        .stat-card .num { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-card .lbl { font-size: .8rem; color: var(--text-light); margin-top: 4px; }
        .stat-card.red  { border-top-color: #ff4757; } .stat-card.red  .num { color: #ff4757; }
        .stat-card.green{ border-top-color: #2ed573; } .stat-card.green .num { color: #2ed573; }

        /* ── Tabs ── */
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 22px; border: 2px solid var(--border-color); border-radius: 25px; background: var(--card-bg); color: var(--text-dark); font-weight: 600; cursor: pointer; transition: .2s; font-size: .9rem; }
        .tab-btn:hover { border-color: var(--primary); color: var(--primary); }
        .tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* ── Cards ── */
        .card { background: var(--card-bg); border-radius: 12px; padding: 24px; box-shadow: var(--shadow); margin-bottom: 20px; }
        .card-title { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin: 0 0 18px; display: flex; align-items: center; gap: 8px; }

        /* ── Alerts ── */
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error   { background: #f8d7da; color: #721c24; }

        /* ── Search ── */
        .search-bar { margin-bottom: 14px; }
        .search-bar input { width: 100%; max-width: 340px; padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: .9rem; background: var(--bg-color); color: var(--text-dark); }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        th { background: var(--bg-color); color: var(--text-light); font-weight: 600; padding: 10px 12px; text-align: left; border-bottom: 2px solid var(--border-color); white-space: nowrap; }
        td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-dark); vertical-align: middle; }
        tr:hover td { background: var(--primary-light); }
        .truncate { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ── Badges ── */
        .badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: .73rem; font-weight: 700; }
        .badge-admin  { background: #fff3cd; color: #856404; }
        .badge-banned { background: #f8d7da; color: #721c24; }
        .badge-active { background: #d4edda; color: #155724; }

        /* ── Buttons ── */
        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: .82rem; font-weight: 600; transition: .15s; white-space: nowrap; }
        .btn-red    { background: #ff4757; color: #fff; } .btn-red:hover    { background: #e03545; }
        .btn-pink   { background: var(--primary); color: #fff; } .btn-pink:hover   { background: var(--primary-dark); }
        .btn-orange { background: #ffa502; color: #fff; } .btn-orange:hover { background: #e6940a; }
        .btn-green  { background: #2ed573; color: #fff; } .btn-green:hover  { background: #26bf62; }
        .btn-grey   { background: var(--border-color); color: var(--text-dark); }
        .btn-group  { display: flex; gap: 4px; flex-wrap: wrap; }

        /* ── Offer thumb ── */
        .thumb { width: 42px; height: 42px; object-fit: cover; border-radius: 6px; }

        /* ── Modal ── */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-box { background: var(--card-bg); border-radius: 14px; padding: 30px; width: 100%; max-width: 420px; box-shadow: 0 8px 30px rgba(0,0,0,.2); }
        .modal-box h3 { margin: 0 0 6px; color: var(--primary); }
        .modal-box p  { margin: 0 0 16px; color: var(--text-light); font-size: .9rem; }
        .modal-box input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; background: var(--bg-color); color: var(--text-dark); margin-bottom: 16px; }
        .modal-footer { display: flex; gap: 10px; justify-content: flex-end; }

        @media(max-width:700px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .tabs { gap: 6px; }
            .tab-btn { padding: 8px 14px; font-size: .82rem; }
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
        <div class="nav-links">
            <a href="index.php" class="nav-item" title="Home"><i class="far fa-compass"></i></a>
            <a href="favorites.html" class="nav-item" title="Favorites"><i class="far fa-heart"></i></a>
            <a href="chats.php" class="nav-item" title="Messages"><i class="far fa-comment-dots"></i></a>
            <a href="account.php" class="nav-item" title="Account"><i class="far fa-user-circle"></i></a>
            <a href="admin.php" class="nav-item" title="Admin" style="color:var(--primary) !important;"><i class="fas fa-shield-alt"></i></a>
            <a href="add-offer.php" class="nav-item btn-add-offer"><i class="fas fa-plus"></i> Add Offer</a>
        </div>
    </nav>
</header>

<div class="admin-wrap">

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="num"><?php echo $totalUsers; ?></div>
            <div class="lbl"><i class="fas fa-users"></i> Total Users</div>
        </div>
        <div class="stat-card green">
            <div class="num"><?php echo $activeOffers; ?></div>
            <div class="lbl"><i class="fas fa-tag"></i> Active Offers</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo $totalOffers; ?></div>
            <div class="lbl"><i class="fas fa-box"></i> All Offers</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo $totalChats; ?></div>
            <div class="lbl"><i class="fas fa-comments"></i> Chats</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo $totalMessages; ?></div>
            <div class="lbl"><i class="fas fa-envelope"></i> Messages</div>
        </div>
        <div class="stat-card red">
            <div class="num"><?php echo $bannedUsers; ?></div>
            <div class="lbl"><i class="fas fa-ban"></i> Banned</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('users',this)"><i class="fas fa-users"></i> Users (<?php echo count($users); ?>)</button>
        <button class="tab-btn" onclick="switchTab('offers',this)"><i class="fas fa-tag"></i> Offers (<?php echo count($offers); ?>)</button>
        <button class="tab-btn" onclick="switchTab('chats',this)"><i class="fas fa-comments"></i> Chats (<?php echo count($chats); ?>)</button>
    </div>

    <!-- ── USERS ───────────────────────────────────────────────────────────── -->
    <div id="tab-users" class="tab-pane active">
        <div class="card">
            <div class="card-title"><i class="fas fa-users"></i> User Management</div>
            <div class="search-bar">
                <input type="text" placeholder="Search users..." oninput="filterTable('userTbl',this.value)">
            </div>
            <div class="table-wrap">
                <table id="userTbl">
                    <thead>
                        <tr>
                            <th>#</th><th>Username</th><th>Email</th><th>Registered</th>
                            <th>Last Login</th><th>Offers</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                <?php if ($u['is_admin']): ?> <span class="badge badge-admin">Admin</span><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                            <td><?php echo $u['registered'] ? date('d M Y', strtotime($u['registered'])) : '—'; ?></td>
                            <td><?php echo $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never'; ?></td>
                            <td><?php echo $u['offer_count']; ?></td>
                            <td>
                                <?php if (!empty($u['is_banned'])): ?>
                                    <span class="badge badge-banned">Banned</span>
                                <?php elseif ($u['is_admin']): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-active">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['username'] !== $currentUser['username']): ?>
                                <div class="btn-group">
                                    <!-- Reset password -->
                                    <button class="btn btn-pink" onclick="openResetModal('<?php echo htmlspecialchars($u['username']); ?>')">
                                        <i class="fas fa-key"></i> Reset PW
                                    </button>
                                    <!-- Ban / Unban -->
                                    <?php if (!empty($u['is_banned'])): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="unban_user">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <button class="btn btn-green" type="submit"><i class="fas fa-user-check"></i> Unban</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Ban <?php echo htmlspecialchars($u['username']); ?>?')">
                                            <input type="hidden" name="action" value="ban_user">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <button class="btn btn-orange" type="submit"><i class="fas fa-ban"></i> Ban</button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Make / Remove admin -->
                                    <?php if ($u['is_admin']): ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Remove admin from <?php echo htmlspecialchars($u['username']); ?>?')">
                                            <input type="hidden" name="action" value="remove_admin">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <button class="btn btn-grey" type="submit"><i class="fas fa-user-minus"></i> Demote</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Make <?php echo htmlspecialchars($u['username']); ?> an admin?')">
                                            <input type="hidden" name="action" value="make_admin">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                            <button class="btn btn-pink" type="submit"><i class="fas fa-user-shield"></i> Make Admin</button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Delete -->
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Permanently delete <?php echo htmlspecialchars($u['username']); ?> and all their data?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                        <button class="btn btn-red" type="submit"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span style="color:var(--text-light);font-size:.82rem;">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── OFFERS ─────────────────────────────────────────────────────────── -->
    <div id="tab-offers" class="tab-pane">
        <div class="card">
            <div class="card-title"><i class="fas fa-tag"></i> Offer Management</div>
            <div class="search-bar">
                <input type="text" placeholder="Search offers..." oninput="filterTable('offerTbl',this.value)">
            </div>
            <div class="table-wrap">
                <table id="offerTbl">
                    <thead>
                        <tr><th>#</th><th>Image</th><th>Title</th><th>Seller</th><th>Price</th><th>Category</th><th>Status</th><th>Posted</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($offers as $o): ?>
                        <tr>
                            <td><?php echo $o['id']; ?></td>
                            <td><img src="<?php echo htmlspecialchars($o['image'] ?? 'https://via.placeholder.com/42'); ?>" class="thumb" onerror="this.src='https://via.placeholder.com/42'"></td>
                            <td class="truncate"><?php echo htmlspecialchars($o['title']); ?></td>
                            <td><?php echo htmlspecialchars($o['seller']); ?></td>
                            <td>€<?php echo number_format($o['price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($o['category'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo ($o['status'] === 'active' || $o['status'] === null) ? 'badge-active' : 'badge-banned'; ?>"><?php echo $o['status'] ?? 'active'; ?></span></td>
                            <td><?php echo $o['created'] ? date('d M Y', strtotime($o['created'])) : '—'; ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this offer?')">
                                    <input type="hidden" name="action" value="delete_offer">
                                    <input type="hidden" name="offer_id" value="<?php echo $o['id']; ?>">
                                    <button class="btn btn-red" type="submit"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── CHATS ──────────────────────────────────────────────────────────── -->
    <div id="tab-chats" class="tab-pane">
        <div class="card">
            <div class="card-title"><i class="fas fa-comments"></i> Chat Management</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Offer</th><th>Buyer</th><th>Seller</th><th>Messages</th><th>Last Activity</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($chats as $c): ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td class="truncate"><?php echo htmlspecialchars($c['offer_title']); ?></td>
                            <td><?php echo htmlspecialchars($c['buyer']); ?></td>
                            <td><?php echo htmlspecialchars($c['seller']); ?></td>
                            <td><?php echo $c['message_count']; ?></td>
                            <td><?php echo $c['last_message_time'] ? date('d M Y H:i', strtotime($c['last_message_time'])) : '—'; ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this entire chat and all messages?')">
                                    <input type="hidden" name="action" value="delete_chat">
                                    <input type="hidden" name="chat_id" value="<?php echo $c['id']; ?>">
                                    <button class="btn btn-red" type="submit"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Reset Password Modal -->
<div class="modal" id="resetModal">
    <div class="modal-box">
        <h3><i class="fas fa-key"></i> Reset Password</h3>
        <p>Set a new password for <strong id="resetUsername"></strong></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="username" id="resetUsernameInput">
            <input type="password" name="new_password" placeholder="New password (min 6 chars)" required minlength="6">
            <div class="modal-footer">
                <button type="button" class="btn btn-grey" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-pink"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
function filterTable(id, q) {
    q = q.toLowerCase();
    document.querySelectorAll('#' + id + ' tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
function openResetModal(username) {
    document.getElementById('resetUsername').textContent = username;
    document.getElementById('resetUsernameInput').value = username;
    document.getElementById('resetModal').classList.add('open');
}
function closeModal() { document.getElementById('resetModal').classList.remove('open'); }
document.getElementById('resetModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

// Apply saved theme
(function() {
    const t = localStorage.getItem('campuscycle_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
})();
</script>
</body>
</html>
