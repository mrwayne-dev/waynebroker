<?php
/**
 * ============================================================
 * Maveren Capital - security primitives
 *
 * Three concerns, deliberately in one file so the policy lives in one place
 * rather than being restated at every call site:
 *
 *   1. Password hashing        mvcHashPassword / mvcPasswordNeedsRehash
 *   2. Rate limiting           mvcRateLimited / mvcRecordAttempt
 *   3. Session hardening       mvcSessionStart
 *
 * Include this instead of calling password_hash() or session_start() directly.
 * ============================================================
 */

// ============================================================
// 1. PASSWORD HASHING
// ============================================================

/**
 * Argon2id parameters.
 *
 * The OWASP Password Storage baseline: 46 MiB, 3 iterations, 1 lane. Measured
 * on this box at ~91ms to hash and ~91ms to verify, which is the right order
 * for an interactive login - slow enough to be expensive in bulk, fast enough
 * that a member never notices.
 *
 * memory_cost is the parameter that actually resists GPU cracking; raising
 * time_cost instead is comparatively poor value. If this ever moves to
 * constrained shared hosting, lower memory_cost rather than dropping to
 * bcrypt - and note the value must fit inside PHP's memory_limit.
 *
 * Changing any of these does NOT invalidate existing hashes: the parameters
 * are encoded in the hash string, so password_verify() keeps working and
 * mvcPasswordNeedsRehash() picks up the change on each member's next login.
 */
const MVC_ARGON2_OPTIONS = [
    'memory_cost' => 47104,  // 46 MiB
    'time_cost'   => 3,
    'threads'     => 1,
];

/**
 * Hash a plaintext password with argon2id.
 *
 * Was password_hash($p, PASSWORD_DEFAULT) at six separate call sites, which
 * resolves to bcrypt cost 10 on PHP 8.3. Both `users.password` and
 * `admins.password` are VARCHAR(255) and an argon2id hash is 97 chars, so no
 * schema change was needed.
 */
function mvcHashPassword(string $plain): string
{
    return password_hash($plain, PASSWORD_ARGON2ID, MVC_ARGON2_OPTIONS);
}

/**
 * Should this stored hash be upgraded?
 *
 * True for every legacy bcrypt hash, and for any argon2id hash made with
 * weaker parameters than the current policy. Callers use this AFTER a
 * successful password_verify() to re-hash in place - see the note on
 * mvcUpgradePasswordHash().
 */
function mvcPasswordNeedsRehash(string $hash): bool
{
    return password_needs_rehash($hash, PASSWORD_ARGON2ID, MVC_ARGON2_OPTIONS);
}

/**
 * Upgrade a verified password in place.
 *
 * This is the migration path: no mass reset, no forced password change, no
 * downtime. An existing bcrypt hash keeps verifying forever; the moment its
 * owner signs in successfully - which is the only moment the plaintext is
 * available - it is silently re-hashed as argon2id. Accounts that never sign
 * in again simply keep their bcrypt hash, which is not a vulnerability.
 *
 * Failures are swallowed on purpose: a login must never fail because an
 * opportunistic re-hash could not be written.
 *
 * @param string $table 'users' or 'admins' - allowlisted, never interpolated
 *                      from request data.
 */
function mvcUpgradePasswordHash(PDO $pdo, string $table, int $id, string $plain, string $currentHash): void
{
    if (!in_array($table, ['users', 'admins'], true)) {
        return;
    }
    if (!mvcPasswordNeedsRehash($currentHash)) {
        return;
    }
    try {
        $stmt = $pdo->prepare("UPDATE {$table} SET password = ? WHERE id = ?");
        $stmt->execute([mvcHashPassword($plain), $id]);
    } catch (Throwable $e) {
        error_log('mvcUpgradePasswordHash: ' . $e->getMessage());
    }
}

// ============================================================
// 2. RATE LIMITING
// ============================================================

/**
 * Per-scope limits: [max attempts, window in minutes].
 *
 * Tuned so a legitimate user never meets them. The tight ones are where a
 * guess is cheap and the prize is an authenticated session; the loose ones
 * exist to stop automation rather than to police humans.
 */
const MVC_RATE_LIMITS = [
    'login'        => [10, 15],   // per IP, failures only
    'admin_login'  => [10, 15],   // separate bucket - see the note below
    'register'     => [5,  60],   // account creation + outbound email
    'reset'        => [5,  15],   // password-reset requests
    'otp'          => [10, 15],   // OTP verification guesses
    'deposit'      => [20, 15],
    'withdraw'     => [10, 60],   // money leaving; deliberately the tightest
    'invest'       => [20, 15],
    'contact'      => [5,  60],
    // Each submission writes three files to disk, so this bounds storage as
    // much as it bounds review spam. Deliberately loose enough that a member
    // who mis-shoots a photo can retry a few times in one sitting.
    'kyc_submit'   => [6,  60],
];

/**
 * Has this (scope, subject) pair exceeded its limit?
 *
 * `subject` is normally the client IP, but any endpoint that knows the
 * account can ALSO call this with the email to get a second, independent
 * bucket - so one attacker cannot lock out a victim by targeting their
 * address from many IPs, and one IP cannot spray many accounts.
 *
 * Scopes are separate on purpose. The previous implementation keyed on IP
 * alone with no scope, so ten failed member logins from an office IP also
 * locked out admin login from that office.
 *
 * FAILS OPEN. A rate limiter that errors closed becomes a self-inflicted
 * denial of service; a database problem must not lock everyone out of the
 * platform.
 */
function mvcRateLimited(PDO $pdo, string $scope, string $subject): bool
{
    if ($subject === '') {
        return false;
    }
    [$max, $windowMinutes] = MVC_RATE_LIMITS[$scope] ?? [20, 15];

    try {
        $since = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rate_limit_hits
             WHERE scope = ? AND subject = ? AND created_at > ?"
        );
        $stmt->execute([$scope, $subject, $since]);
        return (int) $stmt->fetchColumn() >= $max;
    } catch (Throwable $e) {
        error_log('mvcRateLimited error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Record one attempt against a (scope, subject) pair.
 *
 * Call on FAILURE for credential endpoints, so a member who signs in
 * correctly every day never accumulates hits. Call on every REQUEST for
 * endpoints where the request itself is the cost - registration, OTP sends,
 * contact submissions, wallet writes.
 */
function mvcRecordAttempt(PDO $pdo, string $scope, string $subject): void
{
    if ($subject === '') {
        return;
    }
    try {
        $pdo->prepare("INSERT INTO rate_limit_hits (scope, subject, created_at) VALUES (?, ?, NOW())")
            ->execute([$scope, substr($subject, 0, 190)]);

        // Opportunistic housekeeping, ~1 request in 50, so the table cannot
        // grow without bound and no cron is needed. 24h comfortably exceeds
        // the widest window above.
        if (random_int(1, 50) === 1) {
            $pdo->exec("DELETE FROM rate_limit_hits WHERE created_at < (NOW() - INTERVAL 24 HOUR)");
        }
    } catch (Throwable $e) {
        error_log('mvcRecordAttempt error: ' . $e->getMessage());
    }
}

/** The client IP used as the default rate-limit subject. */
function mvcClientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Guard helper: emit a 429 and exit if the caller is over its limit.
 *
 * Returns nothing - it either returns normally or terminates the request.
 * The message is deliberately vague about which bucket tripped.
 */
function mvcEnforceRateLimit(PDO $pdo, string $scope, ?string $extraSubject = null): void
{
    $subjects = [mvcClientIp()];
    if ($extraSubject !== null && $extraSubject !== '') {
        $subjects[] = strtolower($extraSubject);
    }
    foreach ($subjects as $subject) {
        if (mvcRateLimited($pdo, $scope, $subject)) {
            http_response_code(429);
            header('Retry-After: 900');
            echo json_encode([
                'status'  => 'error',
                'message' => 'Too many attempts. Please wait a few minutes and try again.',
            ]);
            exit;
        }
    }
}

// ============================================================
// 2b. ONE-TIME CODES (OTP lifetimes)
// ============================================================

/**
 * The tables holding a one-time code, mapped to the column naming the owner.
 *
 * Whitelisted because the helpers below interpolate the table name into SQL.
 * Every call site passes a literal, and this is what keeps it that way.
 */
const MVC_OTP_TABLES = [
    'email_verifications'   => 'user_id',
    'password_resets'       => 'user_id',
    'admin_password_resets' => 'admin_id',
];

/**
 * SQL expression stamping a fresh OTP deadline, OTP_EXPIRY_MINUTES from now.
 *
 * Interpolate it into the INSERT rather than passing a PHP-formatted string:
 *
 *   INSERT INTO password_resets (user_id, otp, expires_at)
 *   VALUES (?, ?, " . mvcOtpExpirySql() . ")
 *
 * The deadline is then written by the same clock that later reads it, so the
 * code stays valid for ten minutes no matter what the two clocks are set to.
 * These columns used to be filled with date('Y-m-d H:i:s', strtotime(...)) -
 * the PHP clock - while their sibling created_at defaulted to CURRENT_TIMESTAMP
 * on the MySQL clock, which is how a single row ended up carrying an
 * expires_at nearly five hours BEFORE its own created_at.
 *
 * No parameter: the value is a constant from constants.php, never input.
 */
function mvcOtpExpirySql(): string
{
    return 'DATE_ADD(NOW(), INTERVAL ' . (int) OTP_EXPIRY_MINUTES . ' MINUTE)';
}

/**
 * Seconds still to wait before this account may be sent another code.
 * 0 means "send it now" - including when no previous code exists.
 *
 * The whole comparison happens inside MySQL. Done in PHP it read
 * `time() - strtotime($row['created_at'])`, mixing a PHP timestamp with a
 * MySQL-stamped column; where MySQL ran ahead that difference is NEGATIVE, so
 * it was always below any cooldown and the caller was told to wait forever.
 */
function mvcOtpCooldownRemaining(PDO $pdo, string $table, int $ownerId, int $cooldownSeconds): int
{
    $column = MVC_OTP_TABLES[$table] ?? null;
    if ($column === null) {
        throw new InvalidArgumentException("mvcOtpCooldownRemaining: unknown OTP table '{$table}'");
    }

    $stmt = $pdo->prepare(
        "SELECT GREATEST(0, ? - TIMESTAMPDIFF(SECOND, created_at, NOW()))
           FROM `{$table}`
          WHERE `{$column}` = ?
       ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute([$cooldownSeconds, $ownerId]);

    $remaining = $stmt->fetchColumn();

    // No prior code at all -> nothing to wait for.
    return $remaining === false ? 0 : (int) $remaining;
}

// ============================================================
// 3. SESSIONS
// ============================================================

/**
 * Start a session with hardened cookie parameters.
 *
 * Twenty files called a bare session_start() with no options, including
 * index.php - the site root. The runtime ini defaults on this box are
 * session.cookie_httponly='', cookie_secure='0', cookie_samesite='' and
 * use_strict_mode='0', so a first-time visitor was issued a cookie with no
 * HttpOnly, no Secure and no SameSite, and the server would accept any
 * session id an attacker cared to supply.
 *
 * use_strict_mode=1 is the important one: without it, session FIXATION works
 * even though login regenerates the id, because the attacker's chosen id is
 * accepted before that point.
 *
 * cookie_secure is proxy-aware. The previous inline test was
 * `$_SERVER['HTTPS'] === 'on'`, which is unset behind a TLS-terminating
 * proxy - so the Secure flag silently dropped off in exactly the deployment
 * where it matters most.
 */
function mvcSessionStart(array $overrides = []): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');

    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');

    session_start(array_merge([
        'cookie_lifetime' => 86400,
        'cookie_httponly' => true,
        'cookie_secure'   => $https,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ], $overrides));
}

/**
 * Regenerate the session id after a privilege change.
 *
 * Must be called anywhere a request goes from anonymous to authenticated.
 * login.php and admin_login.php already did this; verify_email.php and
 * admin_register.php did not, and both open a fully privileged session.
 */
function mvcSessionElevate(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        // The token is bound to the session. A regenerated session must get a
        // fresh one, or a token minted before login stays valid after it.
        unset($_SESSION['csrf_token']);
    }
}

/**
 * Record a security event, wherever it can be recorded.
 *
 * logSecurityEvent() lives in helpers.php, which most endpoints include AFTER
 * the CSRF and role checks have already run - so a bare
 * function_exists('logSecurityEvent') guard meant those rejections were
 * silently never logged. Verified: forged requests were correctly refused but
 * left no trace at all.
 *
 * Falls back to error_log(), which config/env.php now points at
 * logs/php-error.log in production.
 */
function mvcSecurityLog(string $event, array $context = []): void
{
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent($event, $context);
        return;
    }

    $flat = [];
    foreach ($context as $k => $v) {
        $flat[] = $k . '=' . str_replace(["\r", "\n"], ' ', (string) (is_scalar($v) ? $v : json_encode($v)));
    }
    $line = sprintf('[%s] SECURITY %s %s', date('c'), $event, implode(' ', $flat));

    // Resolve the path from THIS file rather than calling error_log() and
    // hoping the destination is set.
    //
    // config/env.php is what points error_log at logs/php-error.log, but the
    // CSRF and role checks deliberately run at the very top of an endpoint -
    // before that require. So error_log() still had PHP's default
    // destination, which on this host is the bare relative name "error_log":
    // one file per directory, scattered through the web root. A rejected CSRF
    // on wallet.php created api/backend/error_log. It was correctly 403'd, but
    // events that exist to be read should not be spread across the tree.
    $path = dirname(__DIR__, 2) . '/logs/security.log';
    if (@file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false) {
        return;
    }
    error_log($line);   // last resort: unwritable logs/ (local dev, www-data)
}

// ============================================================
// 4. ADMIN ROLE SEPARATION
// ============================================================

/**
 * `admins.role` is ENUM('super_admin','manager','support') and has existed
 * since the schema was written, but the ONLY place that ever read it was
 * api/admin/email.php - where the check accepts all three values, so it
 * enforces nothing. Every other admin endpoint gated on
 * `isset($_SESSION['admin_id'])` alone.
 *
 * The practical effect: an account created for support - to read tickets and
 * look up members - could approve withdrawals, edit wallet balances, and
 * rewrite the deposit addresses that member funds are sent to. There was no
 * such thing as a limited admin.
 *
 * The role is read from the DATABASE on each call rather than from the
 * session. Demoting or suspending an admin has to take effect immediately,
 * and a session copy would keep the old privileges until they logged out.
 * That costs one indexed primary-key lookup per admin write.
 */
function mvcAdminRole(PDO $pdo, int $adminId): ?string
{
    try {
        $stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$adminId]);
        $role = $stmt->fetchColumn();
        return is_string($role) && $role !== '' ? $role : null;
    } catch (Throwable $e) {
        error_log('mvcAdminRole: ' . $e->getMessage());
        return null;   // fails CLOSED - see mvcRequireAdminRole
    }
}

/**
 * Gate an admin endpoint on role. Emits JSON and exits when not permitted.
 *
 * Fails CLOSED, unlike the rate limiter: a rate limiter that breaks should
 * not lock everyone out, but an authorisation check that breaks must not
 * hand out permissions. A null role (deleted admin, suspended admin, DB
 * error) is denied.
 */
function mvcRequireAdminRole(PDO $pdo, array $allowed): string
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    $role    = $adminId > 0 ? mvcAdminRole($pdo, $adminId) : null;

    if ($role === null || !in_array($role, $allowed, true)) {
        mvcSecurityLog('admin_role_denied', [
            'admin_id' => $adminId,
            'role'     => $role ?? 'none',
            'required' => implode('|', $allowed),
            'endpoint' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
            'ip'       => mvcClientIp(),
        ]);
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'error',
            'message' => 'Your admin role does not permit this action.',
        ]);
        exit;
    }
    return $role;
}

// Convenience sets, so the policy lives in one place rather than being
// re-typed (and eventually mistyped) at each call site.
const MVC_ROLE_ALL       = ['super_admin', 'manager', 'support'];
const MVC_ROLE_OPERATOR  = ['super_admin', 'manager'];   // can move money
const MVC_ROLE_OWNER     = ['super_admin'];              // can change where money goes

// ============================================================
// 5. CSRF
// ============================================================

/**
 * Why this exists at all.
 *
 * The application had zero CSRF coverage. The only thing standing between
 * a malicious page and a member's wallet was SameSite=Strict on the session
 * cookie - a single control, defeated by any of: a browser that does not
 * enforce it, a future need to relax it to Lax for a payment-provider
 * return trip, or a same-site subdomain that someone else can write to.
 *
 * api/backend/wallet.php makes that materially worse than usual, because it
 * accepts its `action` from $_POST *or* $_GET. That means initiate_deposit,
 * confirm_deposit_payment and withdraw_request are reachable by a plain
 * cross-site <form> submission - no JavaScript, no CORS preflight, nothing
 * for the browser to block except SameSite.
 *
 * Design:
 *   - One token per session, 32 random bytes, hex encoded.
 *   - Rendered into a <meta name="csrf-token"> by the page head partials.
 *   - Sent back by assets/js/api.js as the X-CSRF-Token header on every
 *     non-GET request. A custom header is itself a useful signal: a plain
 *     cross-site form post cannot set one.
 *   - Also accepted as a `csrf_token` field, for the two endpoints that
 *     read multipart/form-data (avatar upload) rather than JSON.
 *   - hash_equals for the comparison, so it is not a timing oracle.
 *
 * Deliberately NOT rotated per request: the dashboard fires several
 * concurrent XHRs on load, and per-request rotation makes whichever one
 * loses the race fail for no reason a user could understand.
 */
function mvcCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        mvcSessionStart();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Read the token the client sent, from either transport.
 */
function mvcCsrfSubmitted(): string
{
    $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($h) && $h !== '') {
        return trim($h);
    }
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return trim($_POST['csrf_token']);
    }
    // JSON bodies: api.js sends the header, but a hand-rolled client may not.
    static $json = null;
    if ($json === null) {
        $raw  = file_get_contents('php://input');
        $json = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
    }
    return is_array($json) && isset($json['csrf_token']) && is_string($json['csrf_token'])
        ? trim($json['csrf_token'])
        : '';
}

/**
 * Verify, without terminating. Returns false on mismatch.
 */
function mvcCsrfValid(): bool
{
    $expected = $_SESSION['csrf_token'] ?? '';
    $given    = mvcCsrfSubmitted();
    return is_string($expected) && $expected !== ''
        && $given !== ''
        && hash_equals($expected, $given);
}

/**
 * Enforce on a state-changing endpoint. Emits JSON and exits on failure.
 *
 * Safe methods are exempt: CSRF is about side effects, and GET/HEAD are
 * supposed to have none. Where a GET *does* mutate - wallet.php's $_GET
 * action fallback - the fix is to stop accepting the mutation over GET,
 * which is done at that call site, not to demand tokens on reads.
 */
function mvcCsrfEnforce(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    if (mvcCsrfValid()) {
        return;
    }

    mvcSecurityLog('csrf_rejected', [
        'path'   => $_SERVER['REQUEST_URI'] ?? '',
        'ip'     => mvcClientIp(),
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '',
    ]);

    // 403, not Laravel's 419: that code is not in the IANA registry and this
    // SAPI turns an unrecognised status into a bare 500, which would have made
    // every CSRF rejection look like a server crash in the logs. Clients that
    // need to distinguish it branch on the `code` field below.
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'error',
        'code'    => 'csrf',
        'message' => 'Your session expired. Refresh the page and try again.',
    ]);
    exit;
}
