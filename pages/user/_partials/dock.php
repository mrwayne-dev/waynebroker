<?php
// ============================================================
// MEMBER MOBILE DOCK
// Bottom tab bar shown below the sidebar breakpoint (1200px).
// $active = dashboard | wallet | transactions | invest | profile | kyc
//
// Six tabs. This used to cap at five on the grounds that six will not fit a
// 375px screen - but Verification gates withdrawals, so a member who needs it
// was the one member who could not reach it from the bar they are actually
// looking at. The CSS sizes the row from the tab COUNT instead of assuming
// five, so six fit at 320px.
// Same variable the sidebar partial already receives.
// ============================================================
$active = $active ?? 'dashboard';
$on = static fn(string $s) => $active === $s ? ' is-active' : '';
$cur = static fn(string $s) => $active === $s ? ' aria-current="page"' : '';
?>
<nav class="mvc-dock" aria-label="Primary">
    <a href="/dashboard" class="mvc-dock__item<?= $on('dashboard') ?>"<?= $cur('dashboard') ?>>
        <i class="ph ph-squares-four" aria-hidden="true"></i>
        <span>Home</span>
    </a>
    <a href="/dashboard.wallet" class="mvc-dock__item<?= $on('wallet') ?>"<?= $cur('wallet') ?>>
        <i class="ph ph-wallet" aria-hidden="true"></i>
        <span>Wallet</span>
    </a>
    <a href="/dashboard.invest" class="mvc-dock__item<?= $on('invest') ?>"<?= $cur('invest') ?>>
        <i class="ph ph-chart-line-up" aria-hidden="true"></i>
        <span>Invest</span>
    </a>
    <a href="/dashboard.transactions" class="mvc-dock__item<?= $on('transactions') ?>"<?= $cur('transactions') ?>>
        <i class="ph ph-receipt" aria-hidden="true"></i>
        <span>Activity</span>
    </a>
    <a href="/dashboard.profile" class="mvc-dock__item<?= $on('profile') ?>"<?= $cur('profile') ?>>
        <i class="ph ph-user-circle" aria-hidden="true"></i>
        <span>Profile</span>
    </a>
    <a href="/dashboard.kyc" class="mvc-dock__item<?= $on('kyc') ?>"<?= $cur('kyc') ?>>
        <i class="ph ph-shield-check" aria-hidden="true"></i>
        <span>Verify</span>
    </a>
</nav>
