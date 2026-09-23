<?php
// ============================================================
// USER DASHBOARD SIDEBAR partial
// Pass $active = one of:
//   dashboard | wallet | transactions | invest | profile | kyc
// ============================================================
$active = $active ?? 'dashboard';
$is = static fn(string $slug) => $active === $slug ? ' active' : '';
?>
<div class="section-menu-left">
    <div class="box-logo">
        <?php // .box-logo keeps dashboard.css's `background: var(--Primary)`, so
              // this bar is brand orange in BOTH themes - no theme swap needed,
              // and the ink mark is the readable one on it (6.6:1, where the
              // white wordmark this replaced was 2.8:1). ?>
        <a href="/dashboard" id="site-logo-inner" class="mvc-brand" aria-label="Maveren Capital dashboard">
            <img id="logo_header" class="mvc-brand__mark" src="/assets/images/logo/mvc-mark-ink.png" width="128" height="128" alt="">
            <span class="mvc-brand__name">Maveren Capital</span>
        </a>
        <div class="button-show-hide">
            <i class="ph ph-caret-left"></i>
        </div>
    </div>
    <div class="section-menu-left-wrap">
        <div class="center">
            <div class="center-item">
                <div class="center-heading f14-regular text-Gray menu-heading mb-12">Navigation</div>
            </div>
            <div class="center-item">
                <ul class="">
                    <li class="menu-item<?= $is('dashboard') ?>">
                        <a href="/dashboard" class="menu-item-button<?= $is('dashboard') ?>">
                            <div class="icon"><i class="ph ph-squares-four"></i></div>
                            <div class="text">Dashboard</div>
                        </a>
                    </li>
                    <li class="menu-item<?= $is('wallet') ?>">
                        <a href="/dashboard.wallet" class="menu-item-button<?= $is('wallet') ?>">
                            <div class="icon"><i class="ph ph-wallet"></i></div>
                            <div class="text">Wallet</div>
                        </a>
                    </li>
                    <li class="menu-item<?= $is('transactions') ?>">
                        <a href="/dashboard.transactions" class="menu-item-button<?= $is('transactions') ?>">
                            <div class="icon"><i class="ph ph-receipt"></i></div>
                            <div class="text">Transactions</div>
                        </a>
                    </li>
                    <li class="menu-item<?= $is('invest') ?>">
                        <a href="/dashboard.invest" class="menu-item-button<?= $is('invest') ?>">
                            <div class="icon"><i class="ph ph-chart-line-up"></i></div>
                            <div class="text">Invest</div>
                        </a>
                    </li>
                    <li class="menu-item<?= $is('profile') ?>">
                        <a href="/dashboard.profile" class="menu-item-button<?= $is('profile') ?>">
                            <div class="icon"><i class="ph ph-user-circle"></i></div>
                            <div class="text">Profile</div>
                        </a>
                    </li>
                    <li class="menu-item<?= $is('kyc') ?>">
                        <a href="/dashboard.kyc" class="menu-item-button<?= $is('kyc') ?>">
                            <div class="icon"><i class="ph ph-shield-check"></i></div>
                            <div class="text">Verification</div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
