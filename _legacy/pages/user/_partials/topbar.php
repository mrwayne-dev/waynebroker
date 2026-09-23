<?php
// ============================================================
// USER DASHBOARD TOPBAR partial
// Expects $user_name in scope. Pass $page_heading for the title.
// ============================================================
$page_heading = $page_heading ?? 'Dashboard';
$user_name = $user_name ?? 'User';

// Backfill avatar into the session for sessions created before profile_picture
// was added to login - so existing users see their photo without re-logging in.
if (!isset($_SESSION['profile_picture']) && !empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../../../config/database.php';
        $__pdo = getPDO();
        $__st = $__pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $__st->execute([(int) $_SESSION['user_id']]);
        $__pic = $__st->fetchColumn();
        $_SESSION['profile_picture'] = $__pic ?: '/assets/images/avatar/default.png';
        unset($__pdo, $__st, $__pic);
    } catch (Throwable $e) {
        $_SESSION['profile_picture'] = '/assets/images/avatar/default.png';
    }
}

$topbar_avatar = htmlspecialchars($_SESSION['profile_picture'] ?? '/assets/images/avatar/default.png');
?>
<div class="header-dashboard">
    <div class="wrap">
        <div class="header-left">
            <?php // The hamburger is hidden below 1200px (the dock owns mobile nav
                  // there), so the top-left slot would otherwise be empty. Two <img>
                  // toggled by CSS, matching the public navbar - no JS, no flash. ?>
            <a href="/dashboard" class="mvc-topbar-logo" aria-label="Maveren Capital">
                <img class="mvc-topbar-logo__dark" src="/assets/images/logo/mvc-mark-orange.png" width="128" height="128" alt="">
                <img class="mvc-topbar-logo__light" src="/assets/images/logo/mvc-mark-ink.png" width="128" height="128" alt="">
                <span class="mvc-topbar-logo__name">Maveren Capital</span>
            </a>
            <div class="button-show-hide"><i class="ph ph-list"></i></div>
            <h6><?= htmlspecialchars($page_heading) ?></h6>
        </div>
        <div class="header-grid">
            <button class="mvc-lang-trigger" data-lang-open type="button" aria-label="Change language" aria-haspopup="dialog">
                <i class="ph ph-translate" aria-hidden="true"></i>
                <span class="mvc-lang-trigger__code"><?= strtoupper(explode('-', mvcI18nLocale())[0]) ?></span>
            </button>
            <button class="theme-toggle" data-theme-toggle type="button" aria-label="Switch to light theme" aria-pressed="false">
                <i class="ph ph-sun"></i>
            </button>
            <div class="line1"></div>
            <div class="popup-wrap user type-header">
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton3" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="header-user wg-user">
                            <span class="image"><img id="topbar-avatar" src="<?= $topbar_avatar ?>" alt="" data-fallback-src="/assets/images/avatar/default.png"></span>
                            <span class="content flex flex-column">
                                <span class="label-02 text-Black name" id="topbar-username"><?= htmlspecialchars($user_name) ?></span>
                                <span class="f14-regular text-Gray">User</span>
                            </span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton3">
                        <li><a href="/dashboard.profile" class="user-item"><div class="body-title-2">Profile</div></a></li>
                        <li><a href="/dashboard.transactions" class="user-item"><div class="body-title-2">Transactions</div></a></li>
                        <li><a href="#" id="logout-btn" class="user-item" data-logout-url="/dashboard.logout"><div class="body-title-2">Log out</div></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
