<?php
ini_set('display_errors', 0);
error_reporting(0);
    // ===============================================
    // FILE: /api/backend/wallet.php
    // PURPOSE: Central wallet controller for Maveren Capital
    // DESCRIPTION:
    // Handles all wallet actions - deposits, withdrawals,
    // confirmations, and pending data retrieval.
    // Integrates with NOWPayments for crypto deposits,
    // updates wallet balances, and triggers notification emails.
    // ===============================================

    // Hardened + proxy-aware - see api/utilities/security.php.
require_once __DIR__ . '/../../api/utilities/security.php';
    mvcSessionStart();

    // CSRF. Safe methods return immediately; anything else must present the
    // session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
    mvcCsrfEnforce();
    header('Content-Type: application/json');

    // ---------------------------
    // Security: Ensure user is logged in
    // ---------------------------
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    // ---------------------------
    // Includes
    // ---------------------------
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/env.php';        // must precede constants.php so .env APP_URL wins
    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../utilities/helpers.php';   // formatPaymentMethod()
    require_once __DIR__ . '/../utilities/settings.php';  // mvcSettingGetFloat()
    require_once __DIR__ . '/email.php';
    require_once __DIR__ . '/../../api/utilities/security.php';   // rate limiting + sessions

    $pdo = getPDO();
    $user_id = (int) $_SESSION['user_id'];
    $user_name = $_SESSION['full_name'] ?? ($_SESSION['name'] ?? 'User');
    $user_email = $_SESSION['email'] ?? '';

    // ---------------------------
    // Parse incoming request
    // Supports: form POST, GET, and JSON fetch()
    // ---------------------------
    $parsedJsonBody = null;
    $action = null;

    // 1️⃣ Form POST
    if (isset($_POST['action']) && $_POST['action'] !== '') {
        $action = trim($_POST['action']);
    }

    // 2️⃣ GET param - READ-ONLY actions only.
    //
    // This used to accept ANY action over GET, which made initiate_deposit,
    // confirm_deposit_payment and withdraw_request reachable by a bare
    // cross-site <img src> or <form method=get> - no script, no CORS
    // preflight, nothing for the browser to stop. A GET must never move money.
    //
    // Every actual caller uses fetchApi() (JSON POST), so nothing in this
    // application relied on the GET path; the allow-list only preserves it for
    // the three reads in case something external polls them.
    $readOnlyActions = ['get_pending_deposits', 'get_wallet_summary', 'get_deposit_networks'];
    if (!$action && isset($_GET['action']) && $_GET['action'] !== '') {
        $candidate = trim($_GET['action']);
        if (in_array($candidate, $readOnlyActions, true)) {
            $action = $candidate;
        } else {
            http_response_code(405);
            echo json_encode([
                'status'  => 'error',
                'message' => 'This action requires POST.',
            ]);
            exit;
        }
    }

    // 3️⃣ JSON body (fetch API)
    if (!$action) {
        $raw = @file_get_contents('php://input');
        if ($raw) {
            $json = @json_decode($raw, true);
            if (is_array($json)) {
                $parsedJsonBody = $json;
                if (!empty($json['action'])) {
                    $action = trim((string)$json['action']);
                }
            }
        }
    }

    $action = $action ?? null;

    // ---------------------------
    // Helper: respond + exit
    // ---------------------------
    function jsonResponse($status, $message, $data = []) {
        echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
        exit;
    }

    // ---------------------------
    // Helper: reference & wallet utilities
    // ---------------------------
    function generateReference($prefix) {
        return strtoupper($prefix . '-' . uniqid() . '-' . rand(1000, 9999));
    }

    function getUserWallet($pdo, $uid) {
        $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $stmt->execute([$uid]);
        return $stmt->fetch();
    }

    function updateWalletBalance($pdo, $uid, $amount, $type = 'add') {
        $sql = ($type === 'add')
            ? "UPDATE wallets SET balance = balance + ? WHERE user_id = ?"
            : "UPDATE wallets SET balance = balance - ? WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$amount, $uid]);
    }

    // Optional: local debug log
    function logDebug($msg) {
        $logPath = __DIR__ . '/../../logs/wallet_debug.log';
        if (!is_dir(dirname($logPath))) mkdir(dirname($logPath), 0777, true);
        @file_put_contents($logPath, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
    }

    // ===========================================================
    // ACTION ROUTER
    // ===========================================================
    try {
        switch ($action) {

            // -------------------------------------------------------
            // 1️⃣ INITIATE DEPOSIT
            // -------------------------------------------------------
            case 'initiate_deposit':
                // Money-moving action: throttled per IP AND per account, so one
                // compromised session cannot be used to hammer the endpoint.
                mvcEnforceRateLimit($pdo, 'deposit', (string) $user_id);
                mvcRecordAttempt($pdo, 'deposit', mvcClientIp());
                mvcRecordAttempt($pdo, 'deposit', (string) $user_id);

                $data = $parsedJsonBody ?? (json_decode(file_get_contents('php://input'), true) ?: []);
                $amount = (float) ($data['amount'] ?? 0);
                $method = strtolower(trim((string)($data['method'] ?? '')));
                $addressId = (int) ($data['deposit_address_id'] ?? 0);

                if ($amount <= 0 || !$method) {
                    jsonResponse('error', 'Invalid deposit details provided.');
                }

                // secure_exchange = NOWPayments checkout, which issues its own
                // address. deposit_address = manual transfer to an address WE
                // publish; that transaction stays pending until an admin
                // confirms receipt. Wire transfer and cash mailing are retired.
                //
                // There was no whitelist at all before this - $method went
                // straight into the INSERT and only the MySQL ENUM stood between
                // a caller and an arbitrary value, which fails as a 500 rather
                // than a message.
                if (!in_array($method, ['secure_exchange', 'deposit_address'], true)) {
                    jsonResponse('error', 'Unsupported deposit method.');
                }

                // Platform-wide deposit limits, set by an admin on the Settings
                // page. 0 means no limit, which is how they ship.
                //
                // secure_exchange had NO floor at all before this - only
                // amount > 0, so a one-cent checkout was a valid request that
                // created a real invoice. The per-address minimum below applies
                // to deposit_address transfers only and does not cover it.
                $depositMin = mvcSettingGetFloat($pdo, 'deposit_min_amount', 0.0);
                $depositMax = mvcSettingGetFloat($pdo, 'deposit_max_amount', 0.0);

                if ($depositMin > 0 && $amount < $depositMin) {
                    jsonResponse('error', sprintf(
                        'The minimum deposit is $%s.',
                        number_format($depositMin, 2)
                    ), ['code' => 'below_minimum', 'minimum' => $depositMin]);
                }
                if ($depositMax > 0 && $amount > $depositMax) {
                    jsonResponse('error', sprintf(
                        'The maximum deposit is $%s. Please get in touch to arrange a larger transfer.',
                        number_format($depositMax, 2)
                    ), ['code' => 'above_maximum', 'maximum' => $depositMax]);
                }

                $snapshot = null;
                if ($method === 'deposit_address') {
                    if ($addressId <= 0) {
                        jsonResponse('error', 'Select the coin and network you want to send.');
                    }

                    // is_active lives in the WHERE, not in a check afterwards:
                    // an address an admin hid a second ago must not be handed out.
                    $addrStmt = $pdo->prepare("
                        SELECT id, asset, network, label, address, memo_tag, memo_label,
                               min_amount, confirmations, instructions
                        FROM deposit_addresses
                        WHERE id = ? AND is_active = 1
                        LIMIT 1
                    ");
                    $addrStmt->execute([$addressId]);
                    $addrRow = $addrStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$addrRow) {
                        jsonResponse('error', 'That deposit address is no longer available. Please pick another network.');
                    }

                    $minAmount = (float) $addrRow['min_amount'];
                    if ($minAmount > 0 && $amount < $minAmount) {
                        jsonResponse('error', sprintf(
                            'The minimum deposit for %s is $%s.',
                            $addrRow['label'],
                            number_format($minAmount, 2)
                        ));
                    }

                    // SNAPSHOT: the transaction owns its address forever. Rotating
                    // or deleting a deposit_addresses row must never change what a
                    // member was already told to pay - which is what makes the
                    // "safe to hard-delete" contract in api/admin/deposit_addresses.php
                    // actually true.
                    $snapshot = [
                        'id'            => (int) $addrRow['id'],
                        'asset'         => strtoupper((string) $addrRow['asset']),
                        'network'       => (string) $addrRow['network'],
                        'label'         => (string) $addrRow['label'],
                        'address'       => (string) $addrRow['address'],
                        'memo_tag'      => $addrRow['memo_tag'],
                        'memo_label'    => $addrRow['memo_label'],
                        'min_amount'    => $minAmount,
                        'confirmations' => (int) $addrRow['confirmations'],
                        'instructions'  => (string) ($addrRow['instructions'] ?? ''),
                        'snapshot_at'   => date('Y-m-d H:i:s'),
                    ];
                }

                $reference = generateReference('MVC-DEP');
                $timestamp = date('Y-m-d H:i:s');
                $detailsArr = ['initiated_at' => $timestamp, 'method' => $method];
                if ($snapshot) {
                    $detailsArr['deposit_address'] = $snapshot;
                }
                $details = json_encode($detailsArr);

                $insert = $pdo->prepare("
                    INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
                    VALUES (?, 'deposit', ?, ?, ?, 'pending', ?, ?)
                ");
                $insert->execute([$user_id, $method, $amount, $reference, $details, $timestamp]);

                // 🔹 Secure Exchange (NOWPayments)
                if ($method === 'secure_exchange') {
                    require_once __DIR__ . '/../payments/create_crypto_payment.php';
                    $response = createCryptoPayment($user_id, $user_email, $amount, $reference);

                    if (!is_array($response) || ($response['status'] ?? '') !== 'success') {
                        $errMsg = $response['message'] ?? 'Failed to create crypto payment. Please try again later.';
                        logDebug('NOWPayments error: ' . json_encode($response));
                        jsonResponse('error', $errMsg, $response['data'] ?? []);
                    }

                    $paymentUrl = $response['data']['payment_url'] ?? $response['data']['invoice_url'] ?? null;
                    if (!$paymentUrl) {
                        jsonResponse('error', 'Payment provider did not return a redirect URL.', $response);
                    }

                    // The member is about to leave the site for the provider.
                    // Until now nothing was sent here at all, so if the checkout
                    // was abandoned or the IPN never landed, the deposit existed
                    // only as a pending row nobody had been told about.
                    // deposit_initiated is the right template for this branch -
                    // it says instructions follow, which for a hosted checkout
                    // they do, on the provider's page.
                    sendEmail([
                        'to' => $user_email,
                        'template' => 'deposit_initiated',
                        'variables' => [
                            'user_name' => $user_name,
                            'amount'    => number_format($amount, 2),
                            'method'    => formatPaymentMethod($method),
                            'reference' => $reference,
                        ],
                    ]);

                    sendEmail([
                        'to' => ADMIN_CONTACT_EMAIL,
                        'template' => 'admin_deposit_notification',
                        'variables' => [
                            'admin_name' => 'Admin',
                            'user_name'  => $user_name,
                            'user_email' => $user_email,
                            'amount'     => number_format($amount, 2),
                            'method'     => formatPaymentMethod($method),
                            'reference'  => $reference,
                        ],
                    ]);

                    jsonResponse('success', 'Redirecting to crypto payment...', [
                        'redirect_url' => $paymentUrl,
                        'reference' => $reference
                    ]);
                }

                // 🔹 Manual transfer to a published address.
                //
                // Template is deposit_details_provided, NOT deposit_initiated:
                // the latter promises "you will receive an email shortly with
                // specific instructions", which is no longer true because the
                // instructions are in this very message. deposit_details_provided
                // has a {{deposit_address}} slot and closes by telling the member
                // to come back and press "I Have Paid" - written for exactly this
                // flow and unreachable until now.
                $addressLine = $snapshot['label'] . ' — ' . $snapshot['address'];
                if (!empty($snapshot['memo_tag'])) {
                    $addressLine .= ' (' . ($snapshot['memo_label'] ?: 'Memo') . ': ' . $snapshot['memo_tag'] . ')';
                }

                sendEmail([
                    'to' => $user_email,
                    'template' => 'deposit_details_provided',
                    'variables' => [
                        'user_name' => $user_name,
                        'amount' => number_format($amount, 2),
                        'reference' => $reference,
                        // sendEmail() escapes every variable that is not on its
                        // raw-HTML allowlist, so escaping here too delivered
                        // &amp;amp; to the member for any address containing &.
                        'deposit_address' => $addressLine,
                    ]
                ]);

                sendEmail([
                    'to' => ADMIN_CONTACT_EMAIL,
                    'template' => 'admin_deposit_notification',
                    'variables' => [
                        'user_name' => $user_name,
                        'user_email' => $user_email,
                        'amount' => number_format($amount, 2),
                        'method' => formatPaymentMethod($method) . ' (' . $snapshot['label'] . ')',
                        'reference' => $reference
                    ]
                ]);

                // The snapshot goes back with the response so the modal renders
                // the address with no second round trip.
                jsonResponse('success', 'Deposit instructions ready.', [
                    'reference'       => $reference,
                    'amount'          => round($amount, 2),
                    'method'          => $method,
                    'created_at'      => $timestamp,
                    'deposit_address' => $snapshot,
                ]);
                break;

            // -------------------------------------------------------
            // 2️⃣ CONFIRM DEPOSIT PAYMENT ("I Have Paid")
            // -------------------------------------------------------
            case 'confirm_deposit_payment':
                // Money-moving action: throttled per IP AND per account, so one
                // compromised session cannot be used to hammer the endpoint.
                mvcEnforceRateLimit($pdo, 'deposit', (string) $user_id);
                mvcRecordAttempt($pdo, 'deposit', mvcClientIp());
                mvcRecordAttempt($pdo, 'deposit', (string) $user_id);

                $data = $parsedJsonBody ?? (json_decode(file_get_contents('php://input'), true) ?: []);
                $reference = trim((string)($data['reference'] ?? ''));
                if (!$reference) jsonResponse('error', 'Reference is required.');

                $stmt = $pdo->prepare("
                    SELECT * FROM transactions 
                    WHERE user_id = ? AND reference = ? AND type = 'deposit' AND status = 'pending'
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $reference]);
                $txn = $stmt->fetch();
                if (!$txn) jsonResponse('error', 'No pending deposit found for this reference.');

                // Optional on-chain transaction hash. Without it the admin is
                // approving on the amount alone; with it they can check a block
                // explorer before crediting. Bounded because it is echoed into
                // an email and an admin table.
                $txHash = trim((string)($data['tx_hash'] ?? ''));
                if ($txHash !== '') {
                    if (mb_strlen($txHash) > 120 || preg_match('/\s/', $txHash)) {
                        jsonResponse('error', 'That transaction hash does not look valid.');
                    }
                }

                // Merged at the TOP level, so the nested deposit_address
                // snapshot written by initiate_deposit survives untouched.
                $details = json_decode($txn['details'] ?? '{}', true);
                if (!is_array($details)) $details = [];
                $details['user_marked_paid'] = true;
                $details['marked_paid_at'] = date('Y-m-d H:i:s');
                if ($txHash !== '') {
                    $details['tx_hash'] = $txHash;
                }

                $upd = $pdo->prepare("UPDATE transactions SET details = ? WHERE id = ?");
                $upd->execute([json_encode($details), $txn['id']]);

                sendEmail([
                    'to' => ADMIN_CONTACT_EMAIL,
                    'template' => 'admin_payment_confirmed',
                    'variables' => [
                        'user_name' => $user_name,
                        'user_email' => $user_email,
                        'amount' => number_format($txn['amount'], 2),
                        'method' => formatPaymentMethod($txn['method']),
                        'reference' => $txn['reference'],
                        'details' => $txHash !== ''
                            ? 'User confirmed payment. Transaction hash: ' . $txHash   // sendEmail escapes
                            : 'User confirmed payment manually. No transaction hash supplied.'
                    ]
                ]);

                // Member receipt. Only the admin was told before, so the member
                // pressed a button and got nothing but a toast.
                sendEmail([
                    'to' => $user_email,
                    'template' => 'deposit_marked_paid',
                    'variables' => [
                        'user_name' => $user_name,
                        'amount'    => number_format($txn['amount'], 2),
                        'method'    => formatPaymentMethod($txn['method']),
                        'reference' => $txn['reference'],
                        'tx_hash'   => $txHash !== '' ? $txHash : 'Not supplied',
                    ],
                ]);

                jsonResponse('success', 'Deposit marked as paid. Please wait while we complete verification.');
                break;

            // -------------------------------------------------------
            // 3️⃣ WITHDRAW REQUEST
            // -------------------------------------------------------
            case 'withdraw_request':
                // Money-moving action: throttled per IP AND per account, so one
                // compromised session cannot be used to hammer the endpoint.
                mvcEnforceRateLimit($pdo, 'withdraw', (string) $user_id);
                mvcRecordAttempt($pdo, 'withdraw', mvcClientIp());
                mvcRecordAttempt($pdo, 'withdraw', (string) $user_id);

                // KYC gate. Checked BEFORE anything is parsed or written, and
                // read from users.kyc_status rather than the client, so a
                // crafted request cannot route around it. Deposits and new
                // investments are deliberately NOT gated - only money leaving.
                //
                // The 'kyc_required' code is what assets/js/dashboard.js keys on
                // to link the member to /dashboard.kyc instead of showing a
                // generic failure they cannot act on.
                $kycStmt = $pdo->prepare("SELECT kyc_status FROM users WHERE id = ?");
                $kycStmt->execute([$user_id]);
                $kycStatus = (string) ($kycStmt->fetchColumn() ?: 'none');

                if ($kycStatus !== 'approved') {
                    $kycMessage = match ($kycStatus) {
                        'pending'  => 'Your identity check is still under review. Withdrawals unlock once it is approved.',
                        'rejected' => 'Your identity check was not approved. Please resubmit your documents before withdrawing.',
                        default    => 'Please complete your identity verification before making a withdrawal.',
                    };
                    mvcSecurityLog('withdraw_blocked_kyc', [
                        'user_id'    => $user_id,
                        'kyc_status' => $kycStatus,
                        'ip'         => mvcClientIp(),
                    ]);
                    http_response_code(403);
                    jsonResponse('error', $kycMessage, ['code' => 'kyc_required', 'kyc_status' => $kycStatus]);
                }

                $data = $parsedJsonBody ?? (json_decode(file_get_contents('php://input'), true) ?: []);
                $amount = (float) ($data['amount'] ?? 0);
                $method = strtolower(trim((string)($data['method'] ?? '')));
                $details = $data['details'] ?? [];

                if ($amount <= 0 || !$method) jsonResponse('error', 'Invalid withdrawal details.');

                // Same reasoning as initiate_deposit: there was no whitelist,
                // so the MySQL ENUM was the only gate. cash_mailing is retired.
                if (!in_array($method, ['local_bank', 'wallet_address'], true)) {
                    jsonResponse('error', 'Unsupported withdrawal method.');
                }

                // Minimum withdrawal, set by an admin on the Settings page and
                // read fresh on every request so a change takes effect at once.
                // 0 means no minimum, which is how it ships - see
                // dbschema/migrations/2026_09_03_settings.sql.
                //
                // Checked BEFORE the balance so a member below the threshold is
                // told the actual rule rather than "insufficient balance", which
                // would be both wrong and impossible to act on.
                $minWithdrawal = mvcSettingGetFloat($pdo, 'withdrawal_min_amount', 0.0);
                if ($minWithdrawal > 0 && $amount < $minWithdrawal) {
                    jsonResponse('error', sprintf(
                        'The minimum withdrawal is $%s.',
                        number_format($minWithdrawal, 2)
                    ), ['code' => 'below_minimum', 'minimum' => $minWithdrawal]);
                }

                // Ceiling on a single request. Caps the damage a compromised
                // session can do in one go, and catches the member who types an
                // extra zero, without stopping them withdrawing the full balance
                // across several requests an admin sees individually.
                $maxWithdrawal = mvcSettingGetFloat($pdo, 'withdrawal_max_amount', 0.0);
                if ($maxWithdrawal > 0 && $amount > $maxWithdrawal) {
                    jsonResponse('error', sprintf(
                        'The maximum withdrawal per request is $%s.',
                        number_format($maxWithdrawal, 2)
                    ), ['code' => 'above_maximum', 'maximum' => $maxWithdrawal]);
                }

                $wallet = getUserWallet($pdo, $user_id);
                if (!$wallet || $wallet['balance'] < $amount) jsonResponse('error', 'Insufficient wallet balance.');

                $reference = generateReference('MVC-WD');
                $detailsJson = json_encode([
                    'method' => $method,
                    'withdraw_details' => $details,
                    'requested_at' => date('Y-m-d H:i:s')
                ]);

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE wallets SET balance = balance - ?, pending_withdrawals = pending_withdrawals + ? WHERE user_id = ?")
                        ->execute([$amount, $amount, $user_id]);

                    $pdo->prepare("
                        INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
                        VALUES (?, 'withdraw', ?, ?, ?, 'pending', ?, ?)
                    ")->execute([$user_id, $method, $amount, $reference, $detailsJson, date('Y-m-d H:i:s')]);

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log('Withdraw request error: ' . $e->getMessage());
                    jsonResponse('error', 'Withdrawal processing failed. Please try again.');
                }

                // --- START MODIFIED LOGIC: Format Details for Admin Email ---
                $detailsHtml = '';
                $baseStyle = "style='margin: 6px 0;'";
                
                // Logic to format withdrawal details based on method
                if ($method === 'local_bank') {
                    $detailsHtml .= "<p {$baseStyle}><strong>-- Bank Details --</strong></p>";
                    $detailsHtml .= "<p {$baseStyle}><strong>Country:</strong> " . htmlspecialchars($details['country'] ?? 'N/A') . "</p>";
                    $detailsHtml .= "<p {$baseStyle}><strong>Bank Name:</strong> " . htmlspecialchars($details['bank_name'] ?? 'N/A') . "</p>";
                    $detailsHtml .= "<p {$baseStyle}><strong>Acct Holder:</strong> " . htmlspecialchars($details['account_holder'] ?? 'N/A') . "</p>";
                    if (!empty($details['iban'])) $detailsHtml .= "<p {$baseStyle}><strong>IBAN:</strong> " . htmlspecialchars($details['iban']) . "</p>";
                    if (!empty($details['bic'])) $detailsHtml .= "<p {$baseStyle}><strong>BIC/SWIFT:</strong> " . htmlspecialchars($details['bic']) . "</p>";
                    if (!empty($details['sort_code'])) $detailsHtml .= "<p {$baseStyle}><strong>Sort Code (UK):</strong> " . htmlspecialchars($details['sort_code']) . "</p>";
                    $detailsHtml .= "<p {$baseStyle}><strong>Currency:</strong> " . htmlspecialchars($details['currency'] ?? 'USD') . "</p>";
                    if (!empty($details['transaction_ref'])) $detailsHtml .= "<p {$baseStyle}><strong>User Ref:</strong> " . htmlspecialchars($details['transaction_ref']) . "</p>";
                } elseif ($method === 'wallet_address') {
                    $detailsHtml .= "<p {$baseStyle}><strong>-- Crypto Details --</strong></p>";
                    $detailsHtml .= "<p {$baseStyle}><strong>Coin:</strong> " . strtoupper(htmlspecialchars($details['coin'] ?? 'N/A')) . "</p>";
                    $detailsHtml .= "<p {$baseStyle}><strong>Wallet Address:</strong> " . htmlspecialchars($details['address'] ?? 'N/A') . "</p>";
                } else {
                    // The cash_mailing branch lived here. It went with the
                    // method; the whitelist above means this is now genuinely
                    // unreachable except for a future method added upstream.
                    $detailsHtml = "<p {$baseStyle}><strong>Details:</strong> No structured details provided for this method.</p>";
                }
                // --- END MODIFIED LOGIC ---

                sendEmail([
                    'to' => $user_email,
                    'template' => 'withdrawal_initiated',
                    'variables' => [
                        'user_name' => $user_name,
                        'amount' => number_format($amount, 2),
                        'method' => formatPaymentMethod($method),
                        'reference' => $reference
                    ]
                ]);

                sendEmail([
                    'to' => ADMIN_CONTACT_EMAIL,
                    'template' => 'admin_withdrawal_notification',
                    'variables' => [
                        'user_name' => $user_name,
                        'user_email' => $user_email,
                        'amount' => number_format($amount, 2),
                        'method' => formatPaymentMethod($method),
                        'reference' => $reference,
                        'details_html' => $detailsHtml, // <-- New variable passed here
                    ]
                ]);

                jsonResponse('success', 'Withdrawal request submitted successfully.', ['reference' => $reference]);
                break;

            // -------------------------------------------------------
            // 4️⃣ GET PENDING DEPOSITS
            // -------------------------------------------------------
            case 'get_pending_deposits':
                $stmt = $pdo->prepare("
                    SELECT id, amount, method, reference, details, created_at 
                    FROM transactions 
                    WHERE user_id = ? AND type = 'deposit' AND status = 'pending'
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$user_id]);
                $rows = $stmt->fetchAll();
                jsonResponse('success', 'Pending deposits retrieved.', ['deposits' => $rows]);
                break;

            // -------------------------------------------------------
            // 🧾 5️⃣ GET WALLET SUMMARY (Full balance + combined earnings)
            // -------------------------------------------------------
            case 'get_wallet_summary':
                $wallet = getUserWallet($pdo, $user_id);
                if (!$wallet) jsonResponse('error', 'Wallet not found.');

                // --- Step 1: Total ROI earned, live from the one investment table ---
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(roi_earned), 0) FROM investments WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $totalEarnings = (float) $stmt->fetchColumn();

                // --- Step 2: Principal currently held, split by cadence ---
                // Recomputed from `investments` rather than trusting the denormalised
                // wallet columns, which historically drifted out of sync.
                // Completed positions have already returned principal to `balance`.
                $invested = ['weekly' => 0.0, 'monthly' => 0.0];
                $cstmt = $pdo->prepare("SELECT cadence, COALESCE(SUM(amount), 0) AS total
                                        FROM investments
                                        WHERE user_id = ? AND status = 'active'
                                        GROUP BY cadence");
                $cstmt->execute([$user_id]);
                foreach ($cstmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $invested[$row['cadence']] = (float) $row['total'];
                }
                $totalInvested = array_sum($invested);

                // --- Step 3: Persist the recomputed figures as the authoritative values ---
                $upd = $pdo->prepare("UPDATE wallets SET total_earnings = ?, total_investments = ? WHERE user_id = ?");
                $upd->execute([$totalEarnings, $totalInvested, $user_id]);

                // --- Step 4: Re-fetch wallet record (now includes updated totals) ---
                $walletStmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
                $walletStmt->execute([$user_id]);
                $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

                // --- Step 5: Next scheduled payout, for the wallet header ---
                $npStmt = $pdo->prepare("SELECT next_payout_date,
                                                COALESCE(SUM(amount * roi_percent / 100), 0) AS due
                                         FROM investments
                                         WHERE user_id = ? AND status = 'active'
                                         GROUP BY next_payout_date
                                         ORDER BY next_payout_date ASC
                                         LIMIT 1");
                $npStmt->execute([$user_id]);
                $nextPayout = $npStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                // --- Step 6: Build full summary response for frontend ---
                $summary = [
                    'balance'              => (float)$wallet['balance'],
                    'total_deposited'      => (float)$wallet['total_deposited'],
                    'total_withdrawn'      => (float)$wallet['total_withdrawn'],
                    'total_investments'    => $totalInvested,
                    'weekly_invested'      => $invested['weekly'],
                    'monthly_invested'     => $invested['monthly'],
                    'total_invested'       => $totalInvested,
                    'portfolio_value'      => round((float)$wallet['balance'] + $totalInvested, 2),
                    'pending_withdrawals'  => (float)$wallet['pending_withdrawals'],
                    'total_earnings'       => $totalEarnings,
                    'next_payout_date'     => $nextPayout ? date('M d, Y', strtotime($nextPayout['next_payout_date'])) : null,
                    'next_payout_amount'   => $nextPayout ? round((float)$nextPayout['due'], 2) : 0.00,
                    // So the withdrawal form can state the rule up front rather
                    // than letting a member fill in the whole form and submit
                    // before finding out. 0 means no minimum.
                    'withdrawal_min'       => mvcSettingGetFloat($pdo, 'withdrawal_min_amount', 0.0),
                    'withdrawal_max'       => mvcSettingGetFloat($pdo, 'withdrawal_max_amount', 0.0),
                    'deposit_min'          => mvcSettingGetFloat($pdo, 'deposit_min_amount', 0.0),
                    'deposit_max'          => mvcSettingGetFloat($pdo, 'deposit_max_amount', 0.0),
                ];

                jsonResponse('success', 'Wallet summary retrieved successfully.', $summary);
                break;


        // -------------------------------------------------------
        // 6️⃣ PUBLISHED DEPOSIT ADDRESSES (one per chain)
        //
        // Replaces get_deposit_details, which interpolated a column name
        // into `SELECT {$column} FROM settings LIMIT 1` - no WHERE id = 1,
        // so the moment a second settings row existed members would have
        // been shown an arbitrary address.
        // -------------------------------------------------------
        case 'get_deposit_networks':
            $stmt = $pdo->query("
                SELECT id, asset, network, label, address, memo_tag, memo_label,
                       min_amount, confirmations, instructions
                FROM deposit_addresses
                WHERE is_active = 1
                ORDER BY sort_order ASC, asset ASC, network ASC
            ");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $networks = array_map(static function (array $r): array {
                return [
                    'id'            => (int) $r['id'],
                    'asset'         => strtoupper((string) $r['asset']),
                    'network'       => (string) $r['network'],
                    'label'         => (string) $r['label'],
                    'address'       => (string) $r['address'],
                    'memo_tag'      => $r['memo_tag'],
                    'memo_label'    => $r['memo_label'],
                    'min_amount'    => (float) $r['min_amount'],
                    'confirmations' => (int) $r['confirmations'],
                    'instructions'  => (string) ($r['instructions'] ?? ''),
                ];
            }, $rows);

            jsonResponse('success', 'Deposit networks retrieved.', ['networks' => $networks]);
            break;

                


            // -------------------------------------------------------
            // ❌ INVALID ACTION
            // -------------------------------------------------------
            default:
                jsonResponse('error', 'Invalid action specified.');
        }
    } catch (Exception $e) {
        error_log("Wallet API Exception: " . $e->getMessage());
        logDebug('Exception: ' . $e->getMessage());
        jsonResponse('error', 'Internal server error. Please try again later.');
    }
    ?>