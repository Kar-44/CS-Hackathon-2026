<?php
/**
 * nav-notifications.php
 * ─────────────────────
 * Include this file near the top of any PHP page (after session_start and
 * require_once user-functions.php / Database.php are already loaded).
 *
 * It queries MySQL for the logged-in user's unread message count and
 * exposes two things for the template:
 *
 *   $unreadCount  – integer, 0 if not logged in or no unread messages
 *   nav_badge()   – helper function that echoes the badge HTML
 *
 * Usage in nav:
 *   <a href="chats.html" class="nav-item" id="chatNavLink">
 *       <i class="far fa-comment-dots"></i><?php nav_badge(); ?>
 *   </a>
 *
 * Also outputs:
 *   - A <style> block with badge + toast CSS  (once per page)
 *   - A <script> block at the bottom of <body> for the toast pop-up
 *     (only when $unreadCount > 0 and user is NOT already on chats.html)
 */

$unreadCount = 0;

// Only query if a user is logged in and DB is available
if (!empty($currentUser) && class_exists('Database')) {
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$currentUser['username']]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM messages m
                JOIN chats c ON m.chat_id = c.id
                WHERE (c.buyer_id = :uid OR c.seller_id = :uid2)
                  AND m.sender_id != :uid3
                  AND m.is_read = 0
            ");
            $stmt->execute([':uid' => $userId, ':uid2' => $userId, ':uid3' => $userId]);
            $unreadCount = (int) $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        // Non-fatal – badge just won't show
        $unreadCount = 0;
    }
}

/**
 * Echoes the badge span when there are unread messages.
 * Call this immediately after the chat icon inside the <a> tag.
 */
function nav_badge() {
    global $unreadCount;
    if ($unreadCount > 0) {
        $display = $unreadCount > 99 ? '99+' : $unreadCount;
        echo '<span class="msg-badge">' . htmlspecialchars($display) . '</span>';
    }
}
?>
<style>
/* ── Message notification badge ── */
#chatNavLink {
    position: relative;
}
.msg-badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background: #ff4757;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 1;
    min-width: 16px;
    height: 16px;
    padding: 2px 4px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    animation: badgePop 0.3s ease-out;
}
@keyframes badgePop {
    0%   { transform: scale(0); }
    70%  { transform: scale(1.3); }
    100% { transform: scale(1); }
}

/* ── Toast notification ── */
#msgToastContainer {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}
.msg-toast {
    background: var(--primary, #2ecc71);
    color: #fff;
    padding: 13px 18px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    font-weight: 500;
    pointer-events: all;
    cursor: pointer;
    max-width: 280px;
    animation: toastIn 0.35s ease-out forwards;
}
.msg-toast.hiding {
    animation: toastOut 0.35s ease-in forwards;
}
.msg-toast i { font-size: 1.1rem; flex-shrink: 0; }
@keyframes toastIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes toastOut {
    from { opacity: 1; transform: translateY(0); }
    to   { opacity: 0; transform: translateY(20px); }
}
@media (max-width: 600px) {
    #msgToastContainer { bottom: 16px; right: 16px; left: 16px; }
    .msg-toast { max-width: 100%; }
}
</style>

<?php if ($unreadCount > 0):
    $toastText = $unreadCount === 1 ? 'You have 1 unread message' : "You have {$unreadCount} unread messages";
    // Detect whether we're already on the chats page — no toast needed there
    $currentPage = basename($_SERVER['PHP_SELF']);
    $isChatsPage = ($currentPage === 'chats.html' || $currentPage === 'chats.php');
?>
<div id="msgToastContainer"></div>
<script>
(function() {
    <?php if (!$isChatsPage): ?>
    // Show toast after a short delay so the page has time to paint
    setTimeout(function() {
        var container = document.getElementById('msgToastContainer');
        if (!container) return;

        var toast = document.createElement('div');
        toast.className = 'msg-toast';
        toast.innerHTML = '<i class="fas fa-comment-dots"></i><span><?php echo $toastText; ?></span>';
        toast.title = 'Go to messages';
        toast.addEventListener('click', function() {
            window.location.href = 'chats.html';
        });

        container.appendChild(toast);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            toast.classList.add('hiding');
            setTimeout(function() { toast.remove(); }, 370);
        }, 5000);
    }, 800);
    <?php endif; ?>
})();
</script>
<?php endif; ?>
