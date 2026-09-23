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
  $page_title = "Announcements | Maveren Capital Admin";
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
            <?php $active = "announcements"; include __DIR__ . "/_partials/sidebar.php"; ?>
            <?php include __DIR__ . "/_partials/dock.php"; ?>
            <!-- /Sidebar -->

            <!-- Main Content -->
            <div class="section-content-right">
                <?php $page_heading = "Announcements"; include __DIR__ . "/_partials/topbar.php"; ?>

                <div class="main-content">
                    <div class="main-content-inner">
                        <div class="main-content-wrap">
                            <div class="tf-container">

                                <div class="mb-32">
                                    <div class="d-flex justify-between items-center mb-16">
                                        <h5 class="label-01">Member Announcements</h5>
                                        <button id="add-announcement-btn" class="tf-button bg-Primary text-White f12-bold">
                                            <i class="ph ph-plus"></i> New Announcement
                                        </button>
                                    </div>

                                    <?php // See the note on the users table: the div-grid header
                                          // and the table body were sized independently. ?>
                                    <div class="mvc-scroll-table">
                                        <table class="mvc-table">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Category</th>
                                                    <th>Status</th>
                                                    <th>Published</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="announcements-body">
                                                <tr><td class="mvc-empty" colspan="5">Loading announcements...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <?php // Rebuilt on .mvc-field. The inline `style="max-width:700px"` is
                      // gone: .mvc-modal--wide is the one place a dialog width is
                      // declared, and it is guarded by min-width:576px so it cannot
                      // pin a desktop width onto a phone bottom sheet. ?>
                <div class="modal mvc-modal--wide mvc-modal--compact" id="announcement-modal" role="dialog" aria-modal="true" aria-hidden="true">
                    <div class="modal-overlay" data-modal-close></div>
                    <div class="modal-content" tabindex="-1" aria-labelledby="announcement-title">
                        <header class="modal-header">
                            <div>
                                <h2 id="announcement-title">New Announcement</h2>
                                <p class="modal-header__sub">Published announcements appear on every member dashboard.</p>
                            </div>
                            <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                        </header>
                        <div class="modal-body">
                            <form id="announcement-form" autocomplete="off">
                                <input type="hidden" id="announcement-id" value="">

                                <div class="mvc-field">
                                    <div class="mvc-field__top">
                                        <label class="mvc-field__label" for="announcement-title-input">Title</label>
                                        <span class="mvc-field__hint">Required</span>
                                    </div>
                                    <div class="mvc-field__row">
                                        <input type="text" class="mvc-field__input" id="announcement-title-input" maxlength="255" required>
                                    </div>
                                </div>

                                <div class="mvc-field mvc-field--textarea">
                                    <div class="mvc-field__top">
                                        <label class="mvc-field__label" for="announcement-body">Body</label>
                                        <span class="mvc-field__hint">Required</span>
                                    </div>
                                    <div class="mvc-field__row">
                                        <textarea class="mvc-field__input" id="announcement-body" rows="6" required></textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="announcement-category">Category</label>
                                            </div>
                                            <div class="mvc-field__row">
                                                <select class="mvc-field__input" id="announcement-category">
                                                    <option value="general">General</option>
                                                    <option value="product">Product Update</option>
                                                    <option value="maintenance">Maintenance</option>
                                                    <option value="regulatory">Regulatory</option>
                                                    <option value="security">Security</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mvc-field">
                                            <div class="mvc-field__top">
                                                <label class="mvc-field__label" for="announcement-status">Status</label>
                                            </div>
                                            <div class="mvc-field__row">
                                                <select class="mvc-field__input" id="announcement-status">
                                                    <option value="published">Published</option>
                                                    <option value="draft">Draft</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer-actions">
                            <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                            <button type="submit" form="announcement-form" class="modal-confirm-btn">Save announcement</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div id="loader" class="hidden">
    <div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
</div>
<div id="toast-container"></div>

<script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
<script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/announcements.js') ?>" defer></script>
</body>
</html>
