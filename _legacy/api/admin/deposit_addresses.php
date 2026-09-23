<?php
// FILE: /api/admin/deposit_addresses.php
// ============================================================
// PURPOSE: CRUD for the crypto deposit addresses members are shown.
//
// Replaces api/admin/save_deposit_address.php and
// api/admin/get_deposit_address.php, both of which read and wrote two
// hardcoded columns on a single `settings` row - one address in total,
// with no record of which chain it belonged to.
//
// Mutating actions return the refreshed list in data.addresses so the
// caller never needs a follow-up round trip.
// ============================================================
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getPDO();

    // Role gate: this endpoint controls WHERE member deposits are sent - the highest-value target in the console.
    // Only isset($_SESSION['admin_id']) was checked before, so a `support`
    // admin had exactly the same power here as the owner. Read from the DB,
    // fails closed. See mvcRequireAdminRole() in api/utilities/security.php.
    mvcRequireAdminRole($pdo, MVC_ROLE_OWNER);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// Chains the platform is willing to publish an address for. Keeping this a
// closed list is what stops a typo ("erc-20") creating a second, invisible
// row alongside the real one - the UNIQUE (asset, network) key can only
// protect spellings it recognises.
const DEPOSIT_NETWORKS = [
    'bitcoin', 'erc20', 'trc20', 'bep20', 'solana',
    'polygon', 'arbitrum', 'ripple', 'litecoin', 'legacy', 'other',
];

function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Database Error in admin/deposit_addresses.php: ' . $e->getMessage());
        throw $e;
    }
}

function fetchAddresses(PDO $pdo, bool $activeOnly = false): array {
    $sql = "SELECT id, asset, network, label, address, memo_tag, memo_label,
                   min_amount, confirmations, instructions, is_active, sort_order,
                   updated_at
            FROM deposit_addresses"
         . ($activeOnly ? " WHERE is_active = 1" : "")
         . " ORDER BY sort_order ASC, asset ASC, network ASC";

    $stmt = executeQuery($pdo, $sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    return array_map(static function (array $r): array {
        $address = (string) $r['address'];
        return [
            'id'            => (int) $r['id'],
            'asset'         => strtoupper((string) $r['asset']),
            'network'       => (string) $r['network'],
            'label'         => (string) $r['label'],
            // NOT escaped: this is the clipboard payload. Escaping it here
            // would put "&amp;" into somebody's wallet. The consumers insert
            // it with .text()/.val(), never innerHTML.
            'address'       => $address,
            'address_short' => mb_strlen($address) > 22
                ? mb_substr($address, 0, 10) . '...' . mb_substr($address, -8)
                : $address,
            'memo_tag'      => $r['memo_tag'],
            'memo_label'    => $r['memo_label'],
            'min_amount'    => (float) $r['min_amount'],
            'confirmations' => (int) $r['confirmations'],
            'instructions'  => (string) ($r['instructions'] ?? ''),
            'is_active'     => ((int) $r['is_active']) === 1,
            'sort_order'    => (int) $r['sort_order'],
            'updated_at'    => date('M d, Y H:i', strtotime((string) $r['updated_at'])),
        ];
    }, $rows);
}

/**
 * @return array{0: string[], 1: array<string,mixed>} [errors, bound params]
 */
function validateAddress(array $in): array {
    $errors = [];

    $asset   = strtoupper(trim((string) ($in['asset'] ?? '')));
    $network = strtolower(trim((string) ($in['network'] ?? '')));
    $address = trim((string) ($in['address'] ?? ''));
    $label   = trim((string) ($in['label'] ?? ''));

    if (!preg_match('/^[A-Z0-9]{2,12}$/', $asset)) {
        $errors[] = 'Asset must be 2-12 letters or digits (e.g. BTC, USDT).';
    }
    if (!in_array($network, DEPOSIT_NETWORKS, true)) {
        $errors[] = 'Unknown network.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    } elseif (mb_strlen($address) > 255) {
        $errors[] = 'Address cannot exceed 255 characters.';
    } elseif (preg_match('/\s/', $address)) {
        // A pasted address that picked up a trailing newline is the classic
        // way to publish an address nobody can actually send to.
        $errors[] = 'Address cannot contain spaces or line breaks.';
    }
    if ($label === '') {
        $errors[] = 'Display label is required.';
    } elseif (mb_strlen($label) > 80) {
        $errors[] = 'Display label cannot exceed 80 characters.';
    }

    $memoTag   = trim((string) ($in['memo_tag'] ?? ''));
    $memoLabel = trim((string) ($in['memo_label'] ?? ''));
    if ($memoTag !== '' && $memoLabel === '') {
        $errors[] = 'A memo or tag needs a label so members know what to enter.';
    }

    return [$errors, [
        ':asset'         => $asset,
        ':network'       => $network,
        ':label'         => $label,
        ':address'       => $address,
        ':memo_tag'      => $memoTag !== '' ? $memoTag : null,
        ':memo_label'    => $memoLabel !== '' ? $memoLabel : null,
        ':min_amount'    => max(0, (float) ($in['min_amount'] ?? 0)),
        ':confirmations' => max(0, min(255, (int) ($in['confirmations'] ?? 0))),
        ':instructions'  => trim((string) ($in['instructions'] ?? '')) ?: null,
        ':is_active'     => !empty($in['is_active']) ? 1 : 0,
        ':sort_order'    => (int) ($in['sort_order'] ?? 0),
    ]];
}

// ============================================================
// POST - mutations
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input   = json_decode(file_get_contents('php://input'), true) ?: ($_POST ?: $_GET);
    $action  = strtolower(trim((string) ($input['action'] ?? '')));
    $adminId = (int) $_SESSION['admin_id'];

    try {
        if ($action === 'add_address' || $action === 'edit_address') {
            [$errors, $p] = validateAddress($input);
            if ($errors) {
                echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
                exit;
            }

            if ($action === 'add_address') {
                $p[':created_by'] = $adminId;
                $p[':updated_by'] = $adminId;
                executeQuery($pdo, "
                    INSERT INTO deposit_addresses
                        (asset, network, label, address, memo_tag, memo_label, min_amount,
                         confirmations, instructions, is_active, sort_order, created_by, updated_by)
                    VALUES
                        (:asset, :network, :label, :address, :memo_tag, :memo_label, :min_amount,
                         :confirmations, :instructions, :is_active, :sort_order, :created_by, :updated_by)
                ", $p);
                $savedId = (int) $pdo->lastInsertId();
                $message = 'Deposit address added.';
            } else {
                $id = (int) ($input['id'] ?? 0);
                if ($id <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid address ID.']);
                    exit;
                }
                $p[':id']         = $id;
                $p[':updated_by'] = $adminId;
                executeQuery($pdo, "
                    UPDATE deposit_addresses SET
                        asset = :asset, network = :network, label = :label, address = :address,
                        memo_tag = :memo_tag, memo_label = :memo_label, min_amount = :min_amount,
                        confirmations = :confirmations, instructions = :instructions,
                        is_active = :is_active, sort_order = :sort_order, updated_by = :updated_by
                    WHERE id = :id
                ", $p);
                $savedId = $id;
                $message = 'Deposit address updated.';
            }

            echo json_encode([
                'status'  => 'success',
                'message' => $message,
                'data'    => ['id' => $savedId, 'addresses' => fetchAddresses($pdo)],
            ]);
            exit;
        }

        if ($action === 'toggle_address' || $action === 'delete_address') {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid address ID.']);
                exit;
            }

            if ($action === 'toggle_address') {
                executeQuery(
                    $pdo,
                    "UPDATE deposit_addresses SET is_active = :a, updated_by = :u WHERE id = :id",
                    [':a' => !empty($input['is_active']) ? 1 : 0, ':u' => $adminId, ':id' => $id]
                );
                $message = 'Visibility updated.';
            } else {
                // Safe to hard-delete: a pending deposit reads its address from
                // the snapshot stored on the transaction, not from this table,
                // so removing a row never rewrites what a member was told.
                executeQuery($pdo, "DELETE FROM deposit_addresses WHERE id = :id", [':id' => $id]);
                $message = 'Deposit address deleted.';
            }

            echo json_encode([
                'status'  => 'success',
                'message' => $message,
                'data'    => ['addresses' => fetchAddresses($pdo)],
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid POST action specified.']);
        exit;

    } catch (PDOException $e) {
        // 23000 is the UNIQUE (asset, network) key doing its job.
        if ($e->getCode() === '23000') {
            echo json_encode([
                'status'  => 'error',
                'message' => 'An address already exists for that asset and network. Edit that one instead.',
            ]);
            exit;
        }
        error_log('Deposit address error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error saving the address.']);
        exit;
    }
}

// ============================================================
// GET - reads
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (($_GET['fetch'] ?? '') === 'address_details') {
            $id   = (int) ($_GET['id'] ?? 0);
            $rows = array_values(array_filter(
                fetchAddresses($pdo),
                static fn(array $a): bool => $a['id'] === $id
            ));
            echo json_encode($rows
                ? ['status' => 'success', 'data' => $rows[0]]
                : ['status' => 'error', 'message' => 'Address not found.']);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'addresses' => fetchAddresses($pdo),
                'networks'  => DEPOSIT_NETWORKS,
            ],
        ]);
        exit;
    } catch (PDOException $e) {
        error_log('Deposit address read error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error loading addresses.']);
        exit;
    }
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
