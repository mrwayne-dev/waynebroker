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
  $page_title = "Users | Maveren Capital Admin";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
    <div id="wrapper">
        <div id="page" class="">
            <div class="layout-wrap loader-off">
                <!-- Preloader -->
                <div id="preload" class="preload-container">
                    <div class="preloading"><span></span></div>
                </div>

                <!-- Sidebar -->
                <?php $active = "users"; include __DIR__ . "/_partials/sidebar.php"; ?>
                <?php include __DIR__ . "/_partials/dock.php"; ?>
                <!-- /Sidebar -->

                <!-- Main Content -->
                <div class="section-content-right">
                    <!-- Header -->
                    <?php $page_heading = "Users"; include __DIR__ . "/_partials/topbar.php"; ?>
                    <!-- /Header -->

                    <!-- Main Content -->
                    <div class="main-content">
                        <div class="main-content-inner">
                            <div class="main-content-wrap">
                                <div class="tf-container">

                                    <!-- USER STATS CARDS -->
                                    <div class="row mb-32">
                                        <div class="col-12">
                                            <div class="wallet-cards grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap20">
                                                <!-- Total Users -->
                                                <div class="wallet-card wallet-main">
                                                    <div class="wallet-card-header">Total Users</div>
                                                    <div class="wallet-card-balance"><span id="total-users">0</span></div>
                                                    <div class="wallet-card-footer"><i class="ph ph-users-three"></i>
                                                        Registered
                                                    </div>
                                                </div>  
                                                <!-- Active Users -->
                                                <div class="wallet-card wallet-green">
                                                    <div class="wallet-card-header">Active Users</div>
                                                    <div class="wallet-card-balance"><span id="active-users">0</span></div>
                                                    <div class="wallet-card-footer"><i class="ph ph-hourglass"></i>
                                                        Online Now
                                                    </div>
                                                </div>
                                                <!-- Admins -->
                                                <div class="wallet-card wallet-accent">
                                                    <div class="wallet-card-header">Admins</div>
                                                    <div class="wallet-card-balance"><span id="admin-count">0</span></div>
                                                    <div class="wallet-card-footer"><i class="ph ph-shield"></i>
                                                        Privileged
                                                    </div>
                                                </div>
                                                <!-- New Today -->
                                                <div class="wallet-card wallet-purple">
                                                    <div class="wallet-card-header">New Today</div>
                                                    <div class="wallet-card-balance"><span id="new-today">0</span></div>
                                                    <div class="wallet-card-footer"><i class="ph ph-user-plus"></i>
                                                        Joined Today
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SEARCH + FILTERS -->
                                    <div class="topbar-search mb-24">
                                        <form class="form-search flex-grow">
                                            <fieldset class="name">
                                                <input type="text" id="user-search" placeholder="Search by name, email, or ID..." class="show-search style-1">
                                            </fieldset>
                                            <div class="button-submit">
                                                <button type="submit"><i class="ph ph-magnifying-glass"></i></button>
                                            </div>
                                        </form>
                                        <div class="right">
                                            <div class="dropdown default style-fill">
                                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="ph ph-funnel"></i> Filter
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a href="#" data-filter="all">All Users</a></li>
                                                    <li><a href = "#" data-filter="active">Active Only</a></li>
                                                    <li><a href="#" data-filter="suspended">Suspended</a></li>
                                                    <li><a href="#" data-filter="admin">Admins Only</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <?php /* USERS TABLE
                                             Was `.table-list-transaction`, whose header is a DIV
                                             outside the <table>: both sides are flex rows with
                                             hard-coded nth-child pixel widths authored for a
                                             7-column layout (dashboard.css:4957 vs :5004, and
                                             they disagree by 4px on column 1), so the header
                                             never lined up with the body. Same .mvc-table pattern
                                             as the member transactions page - one <table>, so
                                             the browser sizes the columns. */ ?>
                                    <div class="mvc-scroll-table">
                                        <table class="mvc-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Status</th>
                                                    <th>Last Login</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="users-table-body">
                                                <tr><td class="mvc-empty" colspan="6">Loading users...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- PAGINATION -->
                                    <div id="pagination"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Main Content -->

                    <!-- MODALS -->

                    <?php // Rebuilt on .mvc-field. Was the legacy .form-group/.form-control
                          // dialect, which mvc-dashboard.css only patches loosely - the
                          // two selects rendered with the browser default caret and the
                          // labels at 1.1rem, larger than the values beneath them. ?>
                    <div class="modal mvc-modal--compact" id="edit-user-modal" role="dialog" aria-modal="true" aria-hidden="true">
                        <div class="modal-overlay" data-modal-close></div>
                        <div class="modal-content" tabindex="-1" aria-labelledby="edit-user-title">
                            <header class="modal-header">
                                <div>
                                    <h2 id="edit-user-title">Edit member</h2>
                                    <p class="modal-header__sub">Changes take effect on their next request.</p>
                                </div>
                                <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                            </header>
                            <div class="modal-body">
                                <form id="edit-user-form" autocomplete="off">
                                    <input type="hidden" id="edit-user-id" value="">

                                    <div class="mvc-field">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="edit-name">Full name</label>
                                        </div>
                                        <div class="mvc-field__row">
                                            <input type="text" class="mvc-field__input" id="edit-name" maxlength="120" required>
                                        </div>
                                    </div>

                                    <div class="mvc-field">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="edit-email">Email</label>
                                        </div>
                                        <div class="mvc-field__row">
                                            <input type="email" class="mvc-field__input" id="edit-email" maxlength="190" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <label class="mvc-field__label" for="edit-role">Role</label>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <select class="mvc-field__input" id="edit-role">
                                                        <option value="user">User</option>
                                                        <option value="admin">Admin</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mvc-field">
                                                <div class="mvc-field__top">
                                                    <label class="mvc-field__label" for="edit-status">Status</label>
                                                </div>
                                                <div class="mvc-field__row">
                                                    <select class="mvc-field__input" id="edit-status">
                                                        <option value="active">Active</option>
                                                        <option value="suspended">Suspended</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="modal-footer-actions">
                                <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                                <button type="submit" form="edit-user-form" class="modal-confirm-btn">Save changes</button>
                            </div>
                        </div>
                    </div>

                    <div class="modal mvc-modal--compact" id="send-email-modal" role="dialog" aria-modal="true" aria-hidden="true">
                        <div class="modal-overlay" data-modal-close></div>
                        <div class="modal-content" tabindex="-1" aria-labelledby="send-email-title">
                            <header class="modal-header">
                                <div>
                                    <h2 id="send-email-title">Send email</h2>
                                    <p class="modal-header__sub">Sent from the platform address, not your own.</p>
                                </div>
                                <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                            </header>
                            <div class="modal-body">
                                <?php // The recipient was a DISABLED text input, which reads as a
                                      // broken control rather than a fact. Stated, like the other
                                      // read-only rows in the panel. ?>
                                <ul class="mvc-summary">
                                    <li class="mvc-summary__row">
                                        <span class="k"><i class="ph ph-envelope-simple"></i> To</span>
                                        <span class="v" id="email-to"></span>
                                    </li>
                                </ul>
                                <form id="send-email-form" autocomplete="off">
                                    <input type="hidden" id="email-user-id" value="">

                                    <div class="mvc-field">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="email-subject">Subject</label>
                                        </div>
                                        <div class="mvc-field__row">
                                            <input type="text" class="mvc-field__input" id="email-subject" maxlength="150" required>
                                        </div>
                                    </div>

                                    <div class="mvc-field mvc-field--textarea">
                                        <div class="mvc-field__top">
                                            <label class="mvc-field__label" for="email-body">Message</label>
                                        </div>
                                        <div class="mvc-field__row">
                                            <textarea class="mvc-field__input" id="email-body" rows="5" maxlength="4000" required></textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="modal-footer-actions">
                                <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                                <button type="submit" form="send-email-form" class="modal-confirm-btn">Send email</button>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Confirmation Modal -->
                    <div class="modal mvc-modal--danger" id="delete-user-modal">
                        <div class="modal-overlay" data-modal-close></div>
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Delete User</h2>
                                <button type="button" class="modal-close button-close-modal" data-modal-close aria-label="Close dialog">&times;</button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete <strong id="delete-user-name"></strong>?</p>
                                <p class="note">This also removes their wallet, positions and transaction history. It cannot be undone.</p>
                            </div>

                            <div class="modal-footer-actions">
                                <button type="button" class="button-close-modal tf-button" data-modal-close>Cancel</button>
                                <button type="button" id="confirm-delete" class="modal-confirm-btn">Delete member</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Loader & Toast -->
    <div id="loader" class="hidden">
        <div class="line-loader"><div></div><div></div><div></div><div></div><div></div></div>
    </div>
    <div id="toast-container"></div>

    <!-- Scripts -->
    <script src="<?= mvc_asset('../../assets/js/api.js') ?>" defer></script>
    <script src="<?= mvc_asset('../../assets/js/jquery.min.js') ?>"></script>
    <script src="<?= mvc_asset('../../assets/js/bootstrap.min.js') ?>"></script>
    <script src="<?= mvc_asset('../../assets/js/countto.js') ?>" defer></script>
    <script src="<?= mvc_asset('../../assets/js/bootstrap-select.min.js') ?>" defer></script>
    <script src="<?= mvc_asset('../../assets/js/mvc-pagination.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>
    <script src="<?= mvc_asset('../../assets/js/admin/users.js') ?>" defer></script>
    <script src="/assets/js/chart.min.js"></script>
</body>
</html>