<?php
// ============================================================
// ADMIN TOPBAR partial
// Expects $admin_name in scope. Pass $page_heading for the title.
// ============================================================
$page_heading = $page_heading ?? 'Admin';
$admin_name = $admin_name ?? 'Administrator';
?>
<div class="header-dashboard">
    <div class="wrap">
        <div class="header-left">
            <?php // The hamburger is hidden below 1200px (the dock owns mobile nav
                  // there), so the top-left slot would otherwise be empty. Two <img>
                  // toggled by CSS, matching the public navbar - no JS, no flash. ?>
            <a href="/admin.dashboard" class="mvc-topbar-logo" aria-label="Maveren Capital">
                <img class="mvc-topbar-logo__dark" src="/assets/images/logo/mvc-mark-orange.png" width="128" height="128" alt="">
                <img class="mvc-topbar-logo__light" src="/assets/images/logo/mvc-mark-ink.png" width="128" height="128" alt="">
                <span class="mvc-topbar-logo__name">Maveren Capital</span>
            </a>
            <div class="button-show-hide"><i class="ph ph-list"></i></div>
            <h6><?= htmlspecialchars($page_heading) ?></h6>
        </div>
        <div class="header-grid">
            <button class="theme-toggle" data-theme-toggle type="button" aria-label="Switch to light theme" aria-pressed="false">
                <i class="ph ph-sun"></i>
            </button>
            <div class="line1"></div>
            <div class="popup-wrap user type-header">
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton3" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="header-user wg-user">
                            <span class="image"><img src="/assets/images/avatar/default.png" alt="Admin Avatar"></span>
                            <span class="content flex flex-column">
                                <span class="label-02 text-Black name"><?= htmlspecialchars($admin_name) ?></span>
                                <span class="f14-regular text-Gray">Admin</span>
                            </span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton3">
                        <?php // No admin profile page exists yet, and /admin/profile has no
                              // rewrite rule, so the link that used to sit here 404'd. ?>
                        <li><a href="#" id="logout-btn" class="user-item" data-logout-url="/admin.logout"><div class="body-title-2">Log out</div></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
