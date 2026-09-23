<?php
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware: use_strict_mode, and a cookie_secure that
// survives a TLS-terminating proxy (the inline options this replaced
// tested $_SERVER['HTTPS'] === 'on', which is unset behind one).
mvcSessionStart();
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin.login');
    exit;
}
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
?>
<?php
  $page_title = "Settings | Maveren Capital Admin";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
<div id="wrapper">
    <div id="page" class="">
        <div class="layout-wrap loader-off">
            <div id="preload" class="preload-container">
                <div class="preloading"><span></span></div>
            </div>

            <!-- Sidebar -->
            <?php $active = "settings"; include __DIR__ . "/_partials/sidebar.php"; ?>
            <?php include __DIR__ . "/_partials/dock.php"; ?>
            <!-- /Sidebar -->

            <!-- Main Content -->
            <div class="section-content-right">
                <?php $page_heading = "Settings"; include __DIR__ . "/_partials/topbar.php"; ?>

                <div class="main-content">
                    <div class="main-content-inner">
                        <div class="main-content-wrap">
                            <div class="tf-container">

                                <form id="settings-form" novalidate>

                                    <div class="mb-32">
                                        <div class="d-flex justify-between items-center mb-16">
                                            <h5 class="label-01">Transaction limits</h5>
                                        </div>

                                        <section class="mvc-panel">
                                            <div class="mvc-panel__head">
                                                <h2 class="mvc-panel__title">Withdrawals</h2>
                                            </div>
                                            <p class="mvc-panel__lede">
                                                The smallest and largest amount a member may request in one
                                                withdrawal. Leave a field at 0 for no limit.
                                            </p>

                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <span class="mvc-field__label">Minimum withdrawal</span>
                                                    <span class="mvc-field__hint" data-current="withdrawal_min_amount">&nbsp;</span>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <input class="mvc-field__input" type="number" inputmode="decimal"
                                                           id="withdrawal_min_amount" min="0" step="0.01" placeholder="0.00">
                                                    <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
                                                </div>
                                            </div>

                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <span class="mvc-field__label">Maximum withdrawal (per request)</span>
                                                    <span class="mvc-field__hint" data-current="withdrawal_max_amount">&nbsp;</span>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <input class="mvc-field__input" type="number" inputmode="decimal"
                                                           id="withdrawal_max_amount" min="0" step="0.01" placeholder="0.00">
                                                    <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
                                                </div>
                                            </div>
                                        </section>

                                        <section class="mvc-panel">
                                            <div class="mvc-panel__head">
                                                <h2 class="mvc-panel__title">Deposits</h2>
                                            </div>
                                            <p class="mvc-panel__lede">
                                                Applies to both card/exchange checkouts and direct crypto
                                                transfers. Leave a field at 0 for no limit.
                                            </p>

                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <span class="mvc-field__label">Minimum deposit</span>
                                                    <span class="mvc-field__hint" data-current="deposit_min_amount">&nbsp;</span>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <input class="mvc-field__input" type="number" inputmode="decimal"
                                                           id="deposit_min_amount" min="0" step="0.01" placeholder="0.00">
                                                    <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
                                                </div>
                                            </div>

                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <span class="mvc-field__label">Maximum deposit</span>
                                                    <span class="mvc-field__hint" data-current="deposit_max_amount">&nbsp;</span>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <input class="mvc-field__input" type="number" inputmode="decimal"
                                                           id="deposit_max_amount" min="0" step="0.01" placeholder="0.00">
                                                    <span class="mvc-field__chip"><i class="ph ph-currency-dollar"></i> USD</span>
                                                </div>
                                            </div>

                                            <?php // A coin's own minimum can still be higher than the
                                                  // platform floor - the stricter of the two applies. ?>
                                            <div class="mvc-notice">
                                                <span class="mvc-notice__icon"><i class="ph ph-info" aria-hidden="true"></i></span>
                                                <div>
                                                    <div class="mvc-notice__body">
                                                        Individual coins can have their own higher minimum on the
                                                        Deposit Addresses page. Where both apply, the larger of the
                                                        two is what a member has to meet.
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <small class="form-error" id="settings-error"></small>

                                        <div class="mvc-notice">
                                            <span class="mvc-notice__icon"><i class="ph ph-warning-circle" aria-hidden="true"></i></span>
                                            <div>
                                                <div class="mvc-notice__body">
                                                    These apply to new requests only. Anything already waiting for
                                                    approval is unaffected, and a member outside a limit is shown
                                                    the actual figure rather than a generic refusal.
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" id="save-settings-btn"
                                                class="tf-button bg-Primary text-White f12-bold">
                                            <i class="ph ph-floppy-disk"></i> Save settings
                                        </button>
                                    </div>

                                </form>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /Main Content -->
        </div>
    </div>
</div>

<div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
</div>
<div id="toast-container"></div>

<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/settings.js') ?>" defer></script>
</body>
</html>
