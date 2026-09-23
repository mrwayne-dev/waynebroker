<?php
// ============================================================
// ADMIN SIDEBAR partial
// $active = dashboard | users | kyc | transactions | wallets |
//           announcements | plans | deposit_addresses
// ============================================================
$active = $active ?? 'dashboard';
$is = static fn(string $s) => $active === $s ? ' active' : '';
?>
<div class="section-menu-left">
    <div class="box-logo">
        <?php // See the member sidebar: .box-logo is brand orange in both themes,
              // so the ink mark and ink name are the readable pairing here. ?>
        <a href="/admin.dashboard" id="site-logo-inner" class="mvc-brand" aria-label="Maveren Capital admin console">
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
                <ul>
                    <!-- DASHBOARD -->
                    <?php // Was a has-children dropdown wrapping a single "Overview" child. The
                          // submenu had no CSS rule to reveal it, so PHP marked the item active
                          // while it stayed display:none and the first click did nothing. ?>
                    <li class="menu-item<?= $is('dashboard') ?>">
                        <a href="/admin.dashboard" class="menu-item-button<?= $is('dashboard') ?>">
                            <div class="icon"><i class="ph ph-squares-four"></i></div>
                            <div class="text">Dashboard</div>
                        </a>
                    </li>

                    <!-- USERS -->
                    <li class="menu-item<?= $is('users') ?>">
                        <a href="/admin.users" class="menu-item-button<?= $is('users') ?>">
                            <div class="icon"><i class="ph ph-users-three"></i></div>
                            <div class="text">Users</div>
                        </a>
                    </li>

                    <!-- IDENTITY VERIFICATION -->
                    <li class="menu-item<?= $is('kyc') ?>">
                        <a href="/admin.kyc" class="menu-item-button<?= $is('kyc') ?>">
                            <div class="icon"><i class="ph ph-shield-check"></i></div>
                            <div class="text">KYC Verification</div>
                        </a>
                    </li>

                    <!-- TRANSACTIONS -->
                    <li class="menu-item<?= $is('transactions') ?>">
                        <a href="/admin.transactions" class="menu-item-button<?= $is('transactions') ?>">
                            <div class="icon"><i class="ph ph-receipt"></i></div>
                            <div class="text">Transactions</div>
                        </a>
                    </li>

                    <!-- WALLET MANAGEMENT -->
                    <li class="menu-item<?= $is('wallets') ?>">
                        <a href="/admin.wallets" class="menu-item-button<?= $is('wallets') ?>">
                            <div class="icon"><i class="ph ph-wallet"></i></div>
                            <div class="text">Wallet Management</div>
                        </a>
                    </li>

                    <!-- ANNOUNCEMENTS -->
                    <li class="menu-item<?= $is('announcements') ?>">
                        <a href="/admin.announcements" class="menu-item-button<?= $is('announcements') ?>">
                            <div class="icon"><i class="ph ph-megaphone"></i></div>
                            <div class="text">Announcements</div>
                        </a>
                    </li>

                    <!-- PLANS -->
                    <li class="menu-item<?= $is('plans') ?>">
                        <a href="/admin.plans" class="menu-item-button<?= $is('plans') ?>">
                            <div class="icon"><i class="ph ph-chart-line-up"></i></div>
                            <div class="text">Investment Plans</div>
                        </a>
                    </li>

                    <!-- DEPOSIT ADDRESSES -->
                    <li class="menu-item<?= $is('deposit_addresses') ?>">
                        <a href="/admin.deposit-addresses" class="menu-item-button<?= $is('deposit_addresses') ?>">
                            <div class="icon"><i class="ph ph-wallet"></i></div>
                            <div class="text">Deposit Addresses</div>
                        </a>
                    </li>

                    <!-- SETTINGS -->
                    <li class="menu-item<?= $is('settings') ?>">
                        <a href="/admin.settings" class="menu-item-button<?= $is('settings') ?>">
                            <div class="icon"><i class="ph ph-sliders-horizontal"></i></div>
                            <div class="text">Settings</div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
