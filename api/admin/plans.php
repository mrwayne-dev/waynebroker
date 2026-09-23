<?php
// FILE: /api/admin/plans.php
// ============================================================
// PURPOSE: Manage Investment Plans and Active Investments (Admin View)
// Handles: Metrics, Plan CRUD, Plan List, Active Investment List (Paginated)
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

// Ensure only authenticated admins can access this script
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../../config/database.php';

try {
    $pdo = getPDO();

    // Role gate: this endpoint changes published rates and terms.
    // Only isset($_SESSION['admin_id']) was checked before, so a `support`
    // admin had exactly the same power here as the owner. Read from the DB,
    // fails closed. See mvcRequireAdminRole() in api/utilities/security.php.
    mvcRequireAdminRole($pdo, MVC_ROLE_OPERATOR);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

/**
 * Helper function to execute a prepared statement and return result set.
 */
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error in admin/plans.php: " . $e->getMessage());
        return false;
    }
}

// --- Metric Fetcher ---
function fetchInvestmentMetrics($pdo) {
    $metrics = [
        'total_active_invest' => 0.00,
        'total_roi_paid' => 0.00,
        'ongoing_plans_count' => 0,
        'next_maturity' => '—',
        'next_payout' => '—',
        'total_plans' => 0,
    ];

    try {
        // Investment Summary Metrics
        $stmt = executeQuery($pdo, "
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END), 0) AS total_active,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN roi_earned ELSE 0 END), 0) AS total_roi_paid,
                COUNT(DISTINCT user_id) AS ongoing_users,
                MIN(CASE WHEN status = 'active' AND maturity_date >= CURDATE() THEN maturity_date ELSE NULL END) AS next_maturity 
            FROM investments
        ");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if ($row) {
            $metrics['total_active_invest'] = (float)$row['total_active'];
            // Total ROI Paid includes all roi_earned on completed investments
            $metrics['total_roi_paid'] = (float)$row['total_roi_paid'];
            // Ongoing Plans is counted as unique users with active investments
            $metrics['ongoing_plans_count'] = (int)$row['ongoing_users']; 
            $metrics['next_maturity'] = $row['next_maturity'] ? date('M d, Y', strtotime($row['next_maturity'])) : '—';
        }

        // Total Plans Count
        $metrics['total_plans'] = $pdo->query("SELECT COUNT(id) FROM plans")->fetchColumn() ?? 0;

        // ROI actually credited by the cron, across every position.
        $metrics['total_roi_paid'] = (float) ($pdo->query("SELECT COALESCE(SUM(roi_earned), 0) FROM investments")->fetchColumn() ?? 0);

        // Next scheduled payout run.
        $np = $pdo->query("SELECT MIN(next_payout_date) FROM investments WHERE status = 'active'")->fetchColumn();
        $metrics['next_payout'] = $np ? date('M d, Y', strtotime($np)) : '—';

    } catch (PDOException $e) {
        error_log("Investment Metric Fetch Error: " . $e->getMessage());
    }

    return $metrics;
}

// --- Investment Plans Fetcher ---
function fetchPlans($pdo) {
    $sql = "SELECT id, title, cadence, roi_percent, duration_days, min_amount, max_amount,
                   risk, status, icon, description, summary, details, created_at
            FROM plans
            ORDER BY cadence DESC, min_amount ASC";

    $stmt = executeQuery($pdo, $sql);
    $plans = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    return array_map(function ($p) {
        $roi     = (float) $p['roi_percent'];
        $days    = (int) $p['duration_days'];
        $cadence = $p['cadence'];

        // Whole payout periods in the term - same rule as api/backend/invest.php.
        $periodDays = $cadence === 'monthly' ? 30 : 7;
        $payouts    = (int) floor($days / $periodDays);

        if ($days >= 365 && $days % 365 === 0) {
            $term_display = ($days / 365) . ' Year(s)';
        } elseif ($days >= 30 && $days % 30 === 0) {
            $term_display = ($days / 30) . ' Month(s)';
        } elseif ($days >= 7 && $days % 7 === 0) {
            $term_display = ($days / 7) . ' Week(s)';
        } else {
            $term_display = $days . ' Days';
        }

        return [
            'id'             => (int) $p['id'],
            'title'          => htmlspecialchars($p['title']),
            'cadence'        => $cadence,
            'cadence_label'  => ucfirst($cadence),
            'roi_percent'    => $roi,
            'roi_display'    => rtrim(rtrim(number_format($roi, 2, '.', ''), '0'), '.') . '% per ' . ($cadence === 'monthly' ? 'month' : 'week'),
            'payouts_total'  => $payouts,
            'total_percent'  => round($roi * $payouts, 2),
            'duration_days'  => $days,
            'term_display'   => $term_display,
            'min_amount'     => (float) $p['min_amount'],
            'max_amount'     => (float) $p['max_amount'],
            'risk'           => ucfirst(htmlspecialchars($p['risk'])),
            'status'         => $p['status'],
            'icon'           => $p['icon'],
            'description'    => htmlspecialchars($p['description']),
            'summary'        => htmlspecialchars($p['summary']),
            'details'        => htmlspecialchars($p['details']),
            'created_at'     => date('Y-m-d', strtotime($p['created_at'])),
        ];
    }, $plans);
}

// --- Active Investments Fetcher (Paginated) ---
function fetchActiveInvestments($pdo, $page = 1, $perPage = 10, $search = '') {
    
    $sql = "FROM investments i
            JOIN users u ON i.user_id = u.id
            WHERE i.status = 'active'";
    $params = [];
    
    // Apply search
    if (!empty($search)) {
        $searchWild = "%$search%";
        // Search by user name, user email, or plan name
        $sql .= " AND (u.full_name LIKE :s OR u.email LIKE :s OR i.plan_name LIKE :s)";
        $params[':s'] = $searchWild;
    }

    // 1. Count Total Records
    $countStmt = executeQuery($pdo, "SELECT COUNT(i.id) " . $sql, $params);
    $total = $countStmt ? (int)$countStmt->fetchColumn() : 0;

    $totalPages = max(1, ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;
    $limitSql = " LIMIT " . $perPage . " OFFSET " . $offset;

    // 2. Fetch Active Investments (Paginated)
    $dataSql = "SELECT 
                    i.id,
                    i.plan_name,
                    i.amount,
                    i.roi_percent,
                    i.duration_days,
                    i.status,
                    i.maturity_date,
                    i.created_at,
                    COALESCE(u.full_name, u.name) AS user_name,
                    u.email AS user_email,
                    u.id AS user_id
                " . $sql . " 
                ORDER BY i.created_at DESC" . $limitSql;

    $stmt = executeQuery($pdo, $dataSql, $params);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    $formatted = array_map(fn($r) => [
        'id' => (int)$r['id'],
        'user_id' => (int)$r['user_id'],
        'user_name' => htmlspecialchars($r['user_name']),
        'user_email' => htmlspecialchars($r['user_email']),
        'plan_name' => htmlspecialchars($r['plan_name']),
        'amount' => (float)number_format((float)$r['amount'], 2, '.', ''),
        'roi_percent' => (float)$r['roi_percent'],
        'duration_days' => (int)$r['duration_days'],
        'status' => htmlspecialchars($r['status']),
        'date_started' => date('Y-m-d', strtotime($r['created_at'])),
        'maturity_date' => $r['maturity_date'] ? date('Y-m-d', strtotime($r['maturity_date'])) : 'N/A'
    ], $rows);
    
    return [
        'investments' => $formatted,
        'current_page' => $page,
        'total_pages' => $totalPages
    ];
}

// --- POST / Management Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST ?: $_GET;
    $action = strtolower(trim($input['action'] ?? ''));

    if ($action === 'add_plan' || $action === 'edit_plan') {
        $title = trim($input['title'] ?? '');
        $min_amount  = (float) ($input['min_amount'] ?? 0);
        $max_amount  = (float) ($input['max_amount'] ?? 0);
        $roi_percent = (float) ($input['roi_percent'] ?? 0);   // PER PAYOUT PERIOD
        $duration    = (int)   ($input['duration'] ?? 0);
        $cadence     = strtolower(trim($input['cadence'] ?? 'monthly'));
        $risk        = trim($input['risk'] ?? 'Low');
        $status      = strtolower(trim($input['status'] ?? 'active'));
        $id          = (int)   ($input['id'] ?? 0);

        // --- Required fields validation ---
        if ($title === '' || $duration <= 0 || $min_amount <= 0 || $max_amount <= 0
            || $roi_percent <= 0 || $min_amount > $max_amount) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or missing required data (title, cadence, duration, ROI, min/max amounts).']);
            exit;
        }
        if (!in_array($cadence, ['weekly', 'monthly'], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Cadence must be weekly or monthly.']);
            exit;
        }
        if (!in_array($status, ['active', 'hidden'], true)) {
            $status = 'active';
        }

        // A term shorter than one payout period would never pay out.
        $periodDays = $cadence === 'monthly' ? 30 : 7;
        if ($duration < $periodDays) {
            echo json_encode(['status' => 'error', 'message' => "A {$cadence} plan needs a term of at least {$periodDays} days."]);
            exit;
        }

        // Defaults for NOT NULL copy fields not exposed in the modal
        $description = trim($input['description'] ?? '') ?: 'Investment plan.';
        $details     = trim($input['details'] ?? '')     ?: 'Detailed plan terms.';
        $summary     = trim($input['summary'] ?? '')     ?: 'Summary of the plan.';
        $icon        = trim($input['icon'] ?? '')        ?: 'ph-chart-line-up';
        $accent      = trim($input['accent'] ?? '')      ?: 'orange';

        try {
            if ($action === 'add_plan') {
                $sql = "INSERT INTO plans
                        (title, cadence, roi_percent, duration_days, min_amount, max_amount,
                         risk, description, details, summary, icon, accent, status)
                        VALUES (:title, :cadence, :roi_percent, :duration, :min_amount, :max_amount,
                                :risk, :description, :details, :summary, :icon, :accent, :status)";
                $params = [
                    ':title'       => $title,
                    ':cadence'     => $cadence,
                    ':roi_percent' => $roi_percent,
                    ':duration'    => $duration,
                    ':min_amount'  => $min_amount,
                    ':max_amount'  => $max_amount,
                    ':risk'        => $risk,
                    ':description' => $description,
                    ':details'     => $details,
                    ':summary'     => $summary,
                    ':icon'        => $icon,
                    ':accent'      => $accent,
                    ':status'      => $status,
                ];
                $stmt = executeQuery($pdo, $sql, $params);

                echo json_encode($stmt
                    ? ['status' => 'success', 'message' => 'New investment plan created successfully.']
                    : ['status' => 'error', 'message' => 'Failed to create plan.']);

            } elseif ($action === 'edit_plan') {
                if ($id <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid plan ID for edit.']);
                    exit;
                }

                // Editing a plan never touches live positions: investments snapshot
                // their own plan_name / cadence / roi_percent / duration at purchase.
                $sql = "UPDATE plans SET
                            title         = :title,
                            cadence       = :cadence,
                            roi_percent   = :roi_percent,
                            duration_days = :duration,
                            min_amount    = :min_amount,
                            max_amount    = :max_amount,
                            risk          = :risk,
                            description   = :description,
                            details       = :details,
                            summary       = :summary,
                            icon          = :icon,
                            accent        = :accent,
                            status        = :status
                        WHERE id = :id";
                $params = [
                    ':title'       => $title,
                    ':cadence'     => $cadence,
                    ':roi_percent' => $roi_percent,
                    ':duration'    => $duration,
                    ':min_amount'  => $min_amount,
                    ':max_amount'  => $max_amount,
                    ':risk'        => $risk,
                    ':description' => $description,
                    ':details'     => $details,
                    ':summary'     => $summary,
                    ':icon'        => $icon,
                    ':accent'      => $accent,
                    ':status'      => $status,
                    ':id'          => $id,
                ];
                $stmt = executeQuery($pdo, $sql, $params);

                echo json_encode($stmt
                    ? ['status' => 'success', 'message' => 'Investment plan updated successfully.']
                    : ['status' => 'error', 'message' => 'Failed to update plan.']);
            }
        } catch (Exception $e) {
            error_log("Plan Action Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error processing plan request.']);
        }
    } elseif ($action === 'edit_investment') {
        /* ------------------------------------------------------------------
         * Rate-only edit.
         *
         * roi_percent is the ONE schedule field that is safe to change
         * directly: the cron reads it fresh each run, so it only affects
         * future payouts and cannot invalidate anything already paid.
         *
         * amount is deliberately NOT editable any more. Editing it silently
         * changed both every future payout AND the principal released at
         * maturity - so an admin could hand back more than the member ever
         * funded, with no money movement to show for it. The old handler also
         * wrote amount unconditionally, including the `amount: 0` the modal
         * sent for a non-active position.
         *
         * Status changes now go through the explicit close/cancel actions
         * below, which fix the schedule columns instead of leaving a stale
         * past next_payout_date for the cron to catch-up-pay.
         * ------------------------------------------------------------------ */
        $inv_id      = (int)($input['id'] ?? 0);
        $roi_percent = isset($input['roi_percent']) ? (float)$input['roi_percent'] : -1;

        if ($inv_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid investment ID.']);
            exit;
        }
        // DECIMAL(5,2) caps at 999.99; a negative rate would pay backwards.
        if ($roi_percent < 0 || $roi_percent > 999.99) {
            echo json_encode(['status' => 'error', 'message' => 'Rate must be between 0 and 999.99 percent.']);
            exit;
        }

        try {
            $stmt = executeQuery($pdo, "SELECT id, status FROM investments WHERE id = :id", [':id' => $inv_id]);
            $inv = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            if (!$inv) {
                echo json_encode(['status' => 'error', 'message' => 'Investment not found.']);
                exit;
            }
            if ($inv['status'] !== 'active') {
                echo json_encode(['status' => 'error', 'message' => 'Only an active position can have its rate changed.']);
                exit;
            }

            executeQuery($pdo, "UPDATE investments SET roi_percent = :roi WHERE id = :id AND status = 'active'",
                [':roi' => number_format($roi_percent, 2, '.', ''), ':id' => $inv_id]);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Rate updated. It applies from the next scheduled payout.',
            ]);

        } catch (Exception $e) {
            error_log("Edit Investment Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error updating the position.']);
        }

    } elseif ($action === 'investment_bonus') {
        /* ------------------------------------------------------------------
         * Add a bonus payout.
         *
         * roi_earned is a running total the cron never reads for arithmetic,
         * so bumping it is safe - but bumping it ALONE moves no money. The
         * wallet credit and the transaction row are what make it real, which
         * is why this is one action rather than an editable field.
         * ------------------------------------------------------------------ */
        $inv_id = (int)($input['id'] ?? 0);
        $amount = round((float)($input['amount'] ?? 0), 2);
        $note   = trim((string)($input['note'] ?? ''));

        if ($inv_id <= 0 || $amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Enter a bonus amount greater than zero.']);
            exit;
        }
        if ($amount > 1000000) {
            echo json_encode(['status' => 'error', 'message' => 'That bonus is larger than the per-action limit.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = executeQuery($pdo, "SELECT user_id, plan_name, status FROM investments WHERE id = :id FOR UPDATE", [':id' => $inv_id]);
            $inv = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            if (!$inv) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Investment not found.']);
                exit;
            }
            if ($inv['status'] !== 'active') {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Only an active position can receive a bonus.']);
                exit;
            }

            $user_id = (int)$inv['user_id'];

            executeQuery($pdo, "UPDATE investments SET roi_earned = roi_earned + :amt WHERE id = :id",
                [':amt' => $amount, ':id' => $inv_id]);

            executeQuery($pdo, "UPDATE wallets SET balance = balance + :amt, total_earnings = total_earnings + :amt WHERE user_id = :uid",
                [':amt' => $amount, ':uid' => $user_id]);

            // Same type the cron writes, so it lands in the member's activity
            // feed alongside their scheduled payouts.
            $reference = 'MVC-BONUS-' . strtoupper(uniqid());
            $details = json_encode([
                'investment_id' => $inv_id,
                'plan_name'     => $inv['plan_name'],
                'admin_action'  => 'bonus_payout',
                'note'          => $note,
            ]);
            executeQuery($pdo, "INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
                                VALUES (?, 'roi_payout', 'system', ?, ?, 'completed', ?, NOW())",
                                [$user_id, $amount, $reference, $details]);

            $pdo->commit();
            echo json_encode([
                'status'  => 'success',
                'message' => 'Bonus of $' . number_format($amount, 2) . ' credited to the wallet.',
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Investment Bonus Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error crediting the bonus.']);
        }

    } elseif ($action === 'investment_term') {
        /* ------------------------------------------------------------------
         * Extend or shorten the term.
         *
         * payouts_total and maturity_date MUST move together. The cron's
         * catch-up loop runs while `payouts_made + due < payouts_total`, and
         * step 2 releases the principal when `maturity_date <= CURDATE()`. If
         * maturity lands before the final scheduled payout the member silently
         * loses one; if payouts_total is raised without moving maturity, the
         * position closes before the extra payouts happen.
         *
         * So the admin picks a payout COUNT and the maturity date is derived
         * by walking nextPayoutDate() forward from the next scheduled date -
         * the same walk api/cron/investment_cron.php and
         * api/backend/invest.php use.
         * ------------------------------------------------------------------ */
        $inv_id = (int)($input['id'] ?? 0);
        $total  = (int)($input['payouts_total'] ?? 0);

        if ($inv_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid investment ID.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = executeQuery($pdo, "SELECT user_id, cadence, payouts_made, payouts_total, next_payout_date, status
                                        FROM investments WHERE id = :id FOR UPDATE", [':id' => $inv_id]);
            $inv = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            if (!$inv) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Investment not found.']);
                exit;
            }
            if ($inv['status'] !== 'active') {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Only an active position can have its term changed.']);
                exit;
            }

            $made = (int)$inv['payouts_made'];

            // Cannot go below what has already been paid, and 520 weeks is a
            // decade - past that it is a data-entry slip, not an intent.
            if ($total <= $made) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => "This position has already made {$made} payouts. Set a total above that."]);
                exit;
            }
            if ($total > 520) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'That is more payouts than the platform supports.']);
                exit;
            }

            // Walk forward from the next scheduled date for every REMAINING
            // payout. Maturity is the last one, so the final payout and the
            // principal release land on the same cron run - the invariant
            // projectInvestment() exists to guarantee.
            $cadence  = $inv['cadence'] === 'monthly' ? 'monthly' : 'weekly';
            $cursor   = $inv['next_payout_date'];
            $remaining = $total - $made;
            for ($i = 1; $i < $remaining; $i++) {
                $cursor = $cadence === 'monthly'
                    ? date('Y-m-d', strtotime($cursor . ' +1 month'))
                    : date('Y-m-d', strtotime($cursor . ' +7 days'));
            }
            $maturity = $cursor;

            executeQuery($pdo, "UPDATE investments SET payouts_total = :total, maturity_date = :mat WHERE id = :id AND status = 'active'",
                [':total' => $total, ':mat' => $maturity, ':id' => $inv_id]);

            $pdo->commit();
            echo json_encode([
                'status'  => 'success',
                'message' => "Term set to {$total} payouts, maturing " . date('M d, Y', strtotime($maturity)) . '.',
                'data'    => ['payouts_total' => $total, 'maturity_date' => $maturity],
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Investment Term Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error updating the term.']);
        }

    } elseif ($action === 'investment_close') {
        /* ------------------------------------------------------------------
         * Close a position now.
         *
         * Two outcomes, both ending with status != 'active' so the cron will
         * never touch the row again:
         *
         *   settle - release principal + everything earned. Mirrors cron
         *            step 2, which credits the principal only, because ROI was
         *            already paid period by period.
         *   cancel - refund the principal and unwind total_investments.
         *
         * The old handler derived a "final ROI" as ONE period's worth when
         * roi_earned happened to be zero, which under-paid any position that
         * had not yet had a payout, and it never cleared the schedule columns.
         * ------------------------------------------------------------------ */
        $inv_id = (int)($input['id'] ?? 0);
        $mode   = ($input['mode'] ?? 'settle') === 'cancel' ? 'cancel' : 'settle';

        if ($inv_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid investment ID.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = executeQuery($pdo, "SELECT user_id, plan_name, amount, roi_earned, status
                                        FROM investments WHERE id = :id FOR UPDATE", [':id' => $inv_id]);
            $inv = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

            if (!$inv) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Investment not found.']);
                exit;
            }
            if ($inv['status'] !== 'active') {
                $pdo->rollBack();
                // 409, same as the compare-and-set below: both mean "someone
                // already did this". The CAS covers the race; this covers the
                // ordinary repeat.
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'That position is already closed.']);
                exit;
            }

            $user_id    = (int)$inv['user_id'];
            $principal  = (float)$inv['amount'];
            $roi_earned = (float)$inv['roi_earned'];

            if ($mode === 'cancel') {
                // Compare-and-set, so a double submit cannot refund twice.
                $upd = executeQuery($pdo, "UPDATE investments SET status = 'cancelled' WHERE id = :id AND status = 'active'", [':id' => $inv_id]);
                if (!$upd || $upd->rowCount() !== 1) {
                    $pdo->rollBack();
                    http_response_code(409);
                    echo json_encode(['status' => 'error', 'message' => 'That position is no longer active.']);
                    exit;
                }

                // total_investments has to come back down - the old handler
                // did this on cancel but NOT on completion.
                executeQuery($pdo, "UPDATE wallets SET balance = balance + :amt,
                                        total_investments = GREATEST(total_investments - :amt, 0)
                                    WHERE user_id = :uid",
                    [':amt' => $principal, ':uid' => $user_id]);

                $reference = 'MVC-REFUND-' . strtoupper(uniqid());
                $details = json_encode(['investment_id' => $inv_id, 'plan_name' => $inv['plan_name'], 'admin_action' => 'cancelled', 'refund' => $principal]);
                executeQuery($pdo, "INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
                                    VALUES (?, 'investment_refund', 'system', ?, ?, 'completed', ?, NOW())",
                                    [$user_id, $principal, $reference, $details]);

                $payout = $principal;
                $msg = 'Position cancelled. $' . number_format($principal, 2) . ' principal returned.';

            } else {
                $upd = executeQuery($pdo, "UPDATE investments SET status = 'completed' WHERE id = :id AND status = 'active'", [':id' => $inv_id]);
                if (!$upd || $upd->rowCount() !== 1) {
                    $pdo->rollBack();
                    http_response_code(409);
                    echo json_encode(['status' => 'error', 'message' => 'That position is no longer active.']);
                    exit;
                }

                // Principal only. roi_earned was credited to the wallet as each
                // period was paid - adding it again would pay it twice.
                executeQuery($pdo, "UPDATE wallets SET balance = balance + :amt,
                                        total_investments = GREATEST(total_investments - :amt, 0)
                                    WHERE user_id = :uid",
                    [':amt' => $principal, ':uid' => $user_id]);

                $reference = 'MVC-CLOSE-' . strtoupper(uniqid());
                $details = json_encode([
                    'investment_id' => $inv_id,
                    'plan_name'     => $inv['plan_name'],
                    'admin_action'  => 'closed_early',
                    'principal'     => $principal,
                    'roi_paid'      => $roi_earned,
                ]);
                executeQuery($pdo, "INSERT INTO transactions (user_id, type, method, amount, reference, status, details, created_at)
                                    VALUES (?, 'investment_release', 'system', ?, ?, 'completed', ?, NOW())",
                                    [$user_id, $principal, $reference, $details]);

                $payout = $principal;
                $msg = 'Position closed. $' . number_format($principal, 2) . ' principal released; $'
                     . number_format($roi_earned, 2) . ' had already been paid out.';
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => $msg, 'data' => ['payout' => $payout]]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Investment Close Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error closing the position.']);
        }

    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid POST action specified.']);
    }
    exit;
}


// --- GET Requests (Initial Load, Search, Filter, Single Data Fetch) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $input = array_merge($_GET, $_POST); 
    $search = trim($input['search'] ?? '');
    $active_page = max(1, (int)($input['active_page'] ?? 1));
    $per_page = 10; 

    // Case 1: Fetch a single plan's details for editing
    if (isset($input['fetch']) && $input['fetch'] === 'plan_details') {
        $plan_id = (int)($input['id'] ?? 0);
        $stmt = executeQuery($pdo, "SELECT id, title, cadence, roi_percent, duration_days, min_amount, max_amount, risk, status, icon, description, summary, details FROM plans WHERE id = :id", [':id' => $plan_id]);
        $plan = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if ($plan) {
            // A plan has ONE roi_percent, per payout period. This used to
            // synthesise a roi_min/roi_max pair (base * 0.9 / base * 1.1) for a
            // form that had no matching column, and hardcoded status to
            // 'active' so editing a hidden plan silently re-activated it.
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => (int)$plan['id'],
                    'title' => htmlspecialchars($plan['title']),
                    'min_amount' => (string)number_format((float)$plan['min_amount'], 2, '.', ''),
                    'max_amount' => (string)number_format((float)$plan['max_amount'], 2, '.', ''),
                    'roi_percent' => (string)number_format((float)$plan['roi_percent'], 2, '.', ''),
                    'cadence' => $plan['cadence'],
                    'duration_days' => (int)$plan['duration_days'],
                    'risk' => htmlspecialchars($plan['risk']),
                    'status' => $plan['status'],
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Plan not found.']);
        }
        exit;
    }
    
    // Case 2: Fetch a single active investment's details for editing
    if (isset($input['fetch']) && $input['fetch'] === 'investment_details') {
        $inv_id = (int)($input['id'] ?? 0);
        // Returns the schedule columns too. It used to return five fields, so
        // the modal could not show an admin what a term change would actually
        // move.
        $stmt = executeQuery($pdo, "SELECT i.id, i.plan_name, i.cadence, i.amount, i.roi_percent,
                                           i.payouts_made, i.payouts_total, i.next_payout_date,
                                           i.maturity_date, i.roi_earned, i.status,
                                           COALESCE(u.full_name, u.name) AS user_name, u.email AS user_email
                                    FROM investments i JOIN users u ON i.user_id = u.id WHERE i.id = :id", [':id' => $inv_id]);
        $inv = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        if ($inv) {
            $perPayout = round((float)$inv['amount'] * (float)$inv['roi_percent'] / 100, 2);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => (int)$inv['id'],
                    'user_display' => $inv['user_name'] . ' (' . $inv['user_email'] . ')',
                    'plan_name' => $inv['plan_name'],
                    'cadence' => $inv['cadence'],
                    'amount' => (string)number_format((float)$inv['amount'], 2, '.', ''),
                    'roi_percent' => (string)number_format((float)$inv['roi_percent'], 2, '.', ''),
                    'per_payout' => (string)number_format($perPayout, 2, '.', ''),
                    'payouts_made' => (int)$inv['payouts_made'],
                    'payouts_total' => (int)$inv['payouts_total'],
                    'next_payout_date' => $inv['next_payout_date'],
                    'maturity_date' => $inv['maturity_date'],
                    'roi_earned' => (string)number_format((float)$inv['roi_earned'], 2, '.', ''),
                    'status' => $inv['status'],
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Investment not found.']);
        }
        exit;
    }

    // Case 3: Main Dashboard Data Fetch (load_dashboard)
    $metrics = fetchInvestmentMetrics($pdo);
    $plans = fetchPlans($pdo);
    $active_investments = fetchActiveInvestments($pdo, $active_page, $per_page, $search);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'metrics' => $metrics,
            'plans' => $plans,
            'active_investments' => $active_investments['investments'],
            'active_page' => $active_investments['current_page'],
            'active_total_pages' => $active_investments['total_pages']
        ]
    ]);
    exit;
}

// Default response if no action matched
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
exit;
?>