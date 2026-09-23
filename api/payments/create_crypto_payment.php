<?php
// =============================================================
// FILE: /api/payments/create_crypto_payment.php
// PURPOSE: Create NOWPayments invoice (2025 API–compliant)
// AUTHOR: Lymora/Maveren Capital Framework
// =============================================================

function createCryptoPayment($user_id, $user_email, $amount, $reference)
{
    // -----------------------------------
    // Validate input
    // -----------------------------------
    if (empty($user_id) || empty($amount) || empty($reference)) {
        return [
            'status'  => 'error',
            'message' => 'Missing required parameters.',
            'data'    => []
        ];
    }

    // -----------------------------------
    // Load environment + database helpers
    // -----------------------------------
    if (!defined('NOWPAY_API_KEY')) {
        require_once __DIR__ . '/../../config/env.php';
    }
    if (!function_exists('getPDO')) {
        require_once __DIR__ . '/../../config/database.php';
    }

    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        error_log("createCryptoPayment: DB connection failed - " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Database connection error'];
    }

    // -----------------------------------
    // Resolve base URL automatically if APP_URL not defined
    // -----------------------------------
    if (!defined('APP_URL') || empty(APP_URL)) {
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        define('APP_URL', $scheme . '://' . $host . $basePath);
    }

    // -----------------------------------
    // Build NOWPayments payload (2025 spec)
    // -----------------------------------
    $nowpayments_url = 'https://api.nowpayments.io/v1/invoice';
    $payload = [
        'price_amount'      => (float) $amount,
        'price_currency'    => 'usd',
        'ipn_callback_url'  => rtrim(APP_URL, '/') . '/api/payments/now_webhook.php',
        'order_id'          => $reference,
        'order_description' => "Maveren Capital deposit: {$reference}",
        // Routed URLs, not raw file paths. /pages/user/wallet.php resolves today
        // only because nothing blocks direct /pages/ access - it bypasses the
        // rewrite layer, and any future deny rule on /pages/ would strand every
        // member who has just paid. /dashboard.wallet is the canonical route
        // (.htaccess:109).
        'success_url'       => rtrim(APP_URL, '/') . '/dashboard.wallet?deposit=success&ref=' . urlencode($reference),
        'cancel_url'        => rtrim(APP_URL, '/') . '/dashboard.wallet?deposit=cancel&ref=' . urlencode($reference),
        // ❌ NOTE: buyer_email removed - deprecated by NOWPayments
    ];

    // Remove any empty values for safety
    $payload = array_filter($payload, fn($v) => $v !== null && $v !== '');

    // -----------------------------------
    // Prepare cURL request
    // -----------------------------------
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $nowpayments_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . NOWPAY_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    // Use CA bundle if configured (production SSL verification)
    if (defined('NOWPAY_CA_BUNDLE') && file_exists(NOWPAY_CA_BUNDLE)) {
        curl_setopt($ch, CURLOPT_CAINFO, NOWPAY_CA_BUNDLE);
    }

    // -----------------------------------
    // Execute request
    // -----------------------------------
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    // -----------------------------------
    // Retry once in local dev (SSL off)
    // -----------------------------------
    if ($raw === false) {
        error_log("createCryptoPayment: cURL failed: {$curlErr}");
        // Explicit opt-in only.
        //
        // This branch re-sends the LIVE API key with CURLOPT_SSL_VERIFYPEER
        // disabled. The old test also matched `php_sapi_name() === 'cli'`,
        // so any CLI invocation - a cron, a seed script, a one-off `php -r` -
        // silently transmitted the key over an unverified TLS channel. Now it
        // requires someone to have set NOWPAY_INSECURE_FALLBACK=true in .env
        // on purpose, and it still refuses when the app thinks it is
        // production.
        $optedIn = defined('NOWPAY_INSECURE_FALLBACK') && NOWPAY_INSECURE_FALLBACK === true;
        $notProd = !defined('APP_ENV') || APP_ENV !== 'production';

        if ($optedIn && $notProd) {
            error_log('createCryptoPayment: retrying with TLS peer verification DISABLED (NOWPAY_INSECURE_FALLBACK opt-in).');
            $ch2 = curl_init();
            curl_setopt_array($ch2, [
                CURLOPT_URL            => $nowpayments_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'x-api-key: ' . NOWPAY_API_KEY,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $raw = curl_exec($ch2);
            $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch2);
            curl_close($ch2);
            if ($raw === false) {
                error_log("createCryptoPayment: Fallback failed: {$curlErr}");
                return ['status' => 'error', 'message' => 'Payment gateway unreachable.', 'data' => ['curl_error' => $curlErr]];
            }
        } else {
            return ['status' => 'error', 'message' => 'Payment gateway unreachable.', 'data' => ['curl_error' => $curlErr]];
        }
    }

    // -----------------------------------
    // Decode response and validate
    // -----------------------------------
    $response = json_decode($raw, true);
    if (!is_array($response)) {
        error_log("createCryptoPayment: Invalid JSON response: {$raw}");
        return ['status' => 'error', 'message' => 'Invalid response from provider', 'data' => ['raw' => $raw]];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = $response['message'] ?? $response['error'] ?? 'NOWPayments error';
        error_log("createCryptoPayment: HTTP {$httpCode} " . json_encode($response));
        return ['status' => 'error', 'message' => 'Payment provider error: ' . $msg, 'data' => $response];
    }

    // -----------------------------------
    // Extract invoice/payment URL
    // -----------------------------------
    $paymentUrl = $response['invoice_url'] ?? $response['payment_url'] ?? $response['url'] ?? null;
    $paymentId  = $response['invoice_id'] ?? $response['payment_id'] ?? $response['id'] ?? null;

    if (empty($paymentUrl)) {
        array_walk_recursive($response, function ($v) use (&$paymentUrl) {
            if (!$paymentUrl && is_string($v) && stripos($v, 'http') === 0) {
                $paymentUrl = $v;
            }
        });
    }

    if (empty($paymentUrl)) {
        error_log("createCryptoPayment: No payment URL found. Response: " . json_encode($response));
        return ['status' => 'error', 'message' => 'Payment link not returned by provider', 'data' => $response];
    }

    // -----------------------------------
    // Attach NOWPayments details to transaction (optional)
    // -----------------------------------
    try {
        $stmt = $pdo->prepare("SELECT id, details FROM transactions WHERE reference = ? LIMIT 1");
        $stmt->execute([$reference]);
        if ($txn = $stmt->fetch()) {
            $details = json_decode($txn['details'] ?? '{}', true) ?: [];
            $details['provider'] = 'nowpayments';
            $details['provider_payment_id'] = $paymentId;
            $details['provider_response'] = $response;
            $details['created_invoice_url'] = $paymentUrl;
            $details['invoice_created_at'] = date('Y-m-d H:i:s');
            $upd = $pdo->prepare("UPDATE transactions SET details = ? WHERE id = ?");
            $upd->execute([json_encode($details), $txn['id']]);
        }
    } catch (Exception $e) {
        error_log("createCryptoPayment: Could not update transaction: " . $e->getMessage());
    }

    // -----------------------------------
    // Return unified success structure
    // -----------------------------------
    return [
        'status'  => 'success',
        'message' => 'Invoice created successfully.',
        'data'    => [
            'payment_url' => $paymentUrl,
            'payment_id'  => $paymentId,
            'raw'         => $response,
        ],
    ];
}

// ---------------------------------------
// Allow direct call for testing
// ---------------------------------------
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    $input     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $user_id   = $input['user_id'] ?? null;
    $email     = $input['user_email'] ?? null;
    $amount    = $input['amount'] ?? null;
    $reference = $input['reference'] ?? null;
    echo json_encode(createCryptoPayment($user_id, $email, $amount, $reference));
    exit;
}
?>
