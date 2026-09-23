<?php
// ============================================================
// FILE: /api/utilities/logout.php
// PURPOSE: Shared sign-out routine for both dashboards.
//
// User and admin sign-out are the same sequence over different
// session keys, tables and destinations, so the sequence lives here
// and pages/user/logout.php + pages/admin/logout.php only resolve
// who is signing out and where they go afterwards.
// ============================================================

/**
 * Look up an actor's email + display name from the session and DB.
 *
 * @param string $table  'users' or 'admins'
 * @param int    $id     The id held in the session
 * @return array{email: ?string, name: string}
 */
function mvcResolveActor(string $table, int $id, string $fallbackName = 'User'): array
{
    $out = ['email' => null, 'name' => $fallbackName];

    if ($id <= 0) {
        return $out;
    }

    // Whitelisted: the table name cannot be a bound parameter.
    if (!in_array($table, ['users', 'admins'], true)) {
        return $out;
    }

    require_once __DIR__ . '/../../config/database.php';

    try {
        $pdo  = getPDO();
        $stmt = $pdo->prepare("SELECT email, COALESCE(full_name, name) AS display_name FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $out['email'] = $row['email'];
            if (!empty($row['display_name'])) {
                $out['name'] = $row['display_name'];
            }
        } else {
            error_log("Logout: no {$table} row for id {$id}");
        }
    } catch (Throwable $e) {
        error_log('Logout: DB error resolving actor: ' . $e->getMessage());
    }

    return $out;
}

/**
 * Email the sign-out notice, tear the session down, and redirect.
 * Never returns.
 */
function mvcPerformLogout(?string $email, string $name, string $template, string $redirect): void
{
    if ($email) {
        require_once __DIR__ . '/../backend/email.php';

        try {
            $result = sendEmail([
                'to'        => $email,
                'template'  => $template,
                'variables' => [
                    'user_name'   => $name,
                    'admin_name'  => $name,
                    'logout_time' => date('d M Y, H:i:s T'),
                    'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ],
            ]);

            // sendEmail() returns ['success' => bool, ...] or false. The old code
            // checked $result['status'], a key it never sets, so every send was
            // logged as a failure regardless of outcome.
            if (!is_array($result) || empty($result['success'])) {
                $reason = is_array($result) ? ($result['error'] ?? 'unknown error') : 'invalid recipient';
                error_log("Logout: notification to {$email} failed: {$reason}");
            }
        } catch (Throwable $e) {
            error_log("Logout: exception emailing {$email}: " . $e->getMessage());
        }
    }

    session_unset();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();

    header("Location: {$redirect}");
    exit;
}
