<?php
require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin.login');
    exit;
}
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
?>
<?php
  $page_title = "Identity Verification | Maveren Capital Admin";
  include __DIR__ . "/_partials/head.php";
?>
<body class="counter-scroll mvc-dash">
<div id="wrapper">
    <div id="page" class="">
        <div class="layout-wrap loader-off">
            <div id="preload" class="preload-container">
                <div class="preloading"><span></span></div>
            </div>

            <?php $active = "kyc"; include __DIR__ . "/_partials/sidebar.php"; ?>
            <?php include __DIR__ . "/_partials/dock.php"; ?>

            <div class="section-content-right">
                <?php $page_heading = "Identity Verification"; include __DIR__ . "/_partials/topbar.php"; ?>

                <div class="main-content">
                    <div class="main-content-inner">
                        <div class="main-content-wrap">
                            <div class="tf-container">

                                <div class="mb-32">
                                    <div class="d-flex justify-between items-center mb-16 flex-wrap gap8">
                                        <h5 class="label-01">Submissions</h5>
                                        <div class="mvc-segment" role="tablist" aria-label="Filter by status">
                                            <button class="mvc-segment__btn is-active" data-kyc-filter="pending"  role="tab">Pending <span class="mvc-count" id="kyc-count-pending">0</span></button>
                                            <button class="mvc-segment__btn"           data-kyc-filter="approved" role="tab">Approved</button>
                                            <button class="mvc-segment__btn"           data-kyc-filter="rejected" role="tab">Rejected</button>
                                            <button class="mvc-segment__btn"           data-kyc-filter="all"      role="tab">All</button>
                                        </div>
                                    </div>

                                    <div class="wg-box">
                                        <div class="mvc-scroll-table">
                                            <table class="mvc-table">
                                                <thead>
                                                    <tr>
                                                        <th>Member</th>
                                                        <th>Document</th>
                                                        <th>Country</th>
                                                        <th>Submitted</th>
                                                        <th>Status</th>
                                                        <th class="text-end">Review</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="kyc-rows">
                                                    <tr><td colspan="6" class="mvc-empty">Loading&hellip;</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div id="kyc-pagination"></div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REVIEW MODAL -->
<div class="modal fade" id="kyc-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content mvc-modal mvc-modal--wide">
      <div class="modal-header">
        <h5 class="modal-title">Review submission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="kyc-modal-body">
        <div class="mvc-empty">Loading&hellip;</div>
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
<script src="<?= mvc_asset('../../assets/js/admin/admin.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/mvc-pagination.js') ?>" defer></script>
<script src="<?= mvc_asset('../../assets/js/admin/kyc.js') ?>" defer></script>
</body>
</html>
