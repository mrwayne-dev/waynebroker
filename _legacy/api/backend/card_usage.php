<?php
// ========================================
// CARD USAGE API - Maveren Capital
// Splits the member's capital by payout cadence for the dashboard chart.
//
// This used to fan out across six product tables. With a single invest
// product the meaningful breakdown is weekly vs monthly vs idle cash.
// ========================================

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once __DIR__ . '/../../api/utilities/security.php';
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.
mvcSessionStart();

header('Content-Type: application/json');

// User authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getPDO();

    // Keys must match the dashboard chart consumer in assets/js/dashboard.js
    $totals = ['weekly' => 0.0, 'monthly' => 0.0, 'wallet' => 0.0];

    $stmt = $pdo->prepare("SELECT cadence, COALESCE(SUM(amount), 0) AS total
                           FROM investments
                           WHERE user_id = ? AND status = 'active'
                           GROUP BY cadence");
    $stmt->execute([$user_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $totals[$row['cadence']] = (float) $row['total'];
    }

    // Uninvested wallet balance, so the chart accounts for all their capital.
    $wstmt = $pdo->prepare("SELECT COALESCE(balance, 0) FROM wallets WHERE user_id = ?");
    $wstmt->execute([$user_id]);
    $totals['wallet'] = (float) $wstmt->fetchColumn();

    // Calculate percentage share
    $grandTotal = array_sum($totals);
    $percentages = [];
    foreach ($totals as $key => $value) {
        $percentages[$key] = $grandTotal > 0 ? round(($value / $grandTotal) * 100, 2) : 0;
    }

    echo json_encode([
        'success' => true,
        'totals' => $totals,
        'percentages' => $percentages
    ]);

} catch (Exception $e) {
    error_log('card_usage.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load card usage right now.']);
    exit;
}
