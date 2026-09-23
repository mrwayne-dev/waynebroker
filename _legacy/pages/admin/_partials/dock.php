<?php
// ============================================================
// ADMIN MOBILE DOCK
// Bottom tab bar shown below the sidebar breakpoint (1200px).
//
// KYC Verification gets a slot of its own rather than living behind "More".
// It is the only section here carrying a QUEUE that blocks members - an
// unreviewed submission is somebody who cannot withdraw - and it was missing
// from mobile navigation entirely, dock and sheet alike, so on a phone the
// review screen was simply unreachable.
//
// That makes six items. The earlier note here said six do not fit; the dock
// CSS now sizes the row from the tab count rather than assuming five, and six
// were measured fitting on one row at 320px.
// $active = dashboard | users | transactions | wallets | kyc |
//           announcements | plans | deposit_addresses
// ============================================================
$active = $active ?? 'dashboard';
$on  = static fn(string $s) => $active === $s ? ' is-active' : '';
$cur = static fn(string $s) => $active === $s ? ' aria-current="page"' : '';
$inMore = in_array($active, ['announcements', 'plans', 'deposit_addresses', 'settings'], true);
?>
<nav class="mvc-dock" aria-label="Primary">
    <a href="/admin.dashboard" class="mvc-dock__item<?= $on('dashboard') ?>"<?= $cur('dashboard') ?>>
        <i class="ph ph-squares-four" aria-hidden="true"></i>
        <span>Home</span>
    </a>
    <a href="/admin.users" class="mvc-dock__item<?= $on('users') ?>"<?= $cur('users') ?>>
        <i class="ph ph-users-three" aria-hidden="true"></i>
        <span>Users</span>
    </a>
    <a href="/admin.transactions" class="mvc-dock__item<?= $on('transactions') ?>"<?= $cur('transactions') ?>>
        <i class="ph ph-receipt" aria-hidden="true"></i>
        <span>Activity</span>
    </a>
    <a href="/admin.wallets" class="mvc-dock__item<?= $on('wallets') ?>"<?= $cur('wallets') ?>>
        <i class="ph ph-wallet" aria-hidden="true"></i>
        <span>Wallets</span>
    </a>
    <a href="/admin.kyc" class="mvc-dock__item<?= $on('kyc') ?>"<?= $cur('kyc') ?>>
        <i class="ph ph-shield-check" aria-hidden="true"></i>
        <span>KYC</span>
    </a>
    <button type="button"
            class="mvc-dock__item<?= $inMore ? ' is-active' : '' ?>"
            data-dock-more
            aria-expanded="false"
            aria-controls="mvc-dock-sheet">
        <i class="ph ph-dots-three" aria-hidden="true"></i>
        <span>More</span>
    </button>
</nav>

<div class="mvc-sheet" id="mvc-dock-sheet" role="dialog" aria-modal="true" aria-hidden="true" aria-label="More sections">
    <div class="mvc-sheet__overlay" data-dock-close></div>
    <div class="mvc-sheet__panel" tabindex="-1">
        <div class="mvc-sheet__handle" aria-hidden="true"></div>
        <a href="/admin.announcements" class="mvc-sheet__item<?= $on('announcements') ?>">
            <i class="ph ph-megaphone" aria-hidden="true"></i>
            <span>Announcements</span>
        </a>
        <a href="/admin.plans" class="mvc-sheet__item<?= $on('plans') ?>">
            <i class="ph ph-chart-line-up" aria-hidden="true"></i>
            <span>Investment Plans</span>
        </a>
        <a href="/admin.deposit-addresses" class="mvc-sheet__item<?= $on('deposit_addresses') ?>">
            <i class="ph ph-wallet" aria-hidden="true"></i>
            <span>Deposit Addresses</span>
        </a>
        <a href="/admin.settings" class="mvc-sheet__item<?= $on('settings') ?>">
            <i class="ph ph-sliders-horizontal" aria-hidden="true"></i>
            <span>Settings</span>
        </a>
    </div>
</div>
