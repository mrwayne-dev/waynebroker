<?php
// ========================================
// DATABASE CONNECTION HANDLER - Maveren Capital
// ========================================

function getPDO() {
    require_once __DIR__ . '/env.php';
    // Needed for TIMEZONE (and the date_default_timezone_set it performs)
    // before the offset below can be computed.
    require_once __DIR__ . '/constants.php';

    $host = DB_HOST;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- One clock for PHP and MySQL ---
        //
        // MySQL sessions default to @@system_time_zone - the HOST's zone -
        // while PHP runs on TIMEZONE (America/New_York). On the current box
        // that is WAT against EDT, so NOW() ran five hours ahead of date().
        //
        // Every comparison that straddled the two was wrong by that offset.
        // The visible casualty was the OTP resend cooldown, which measured
        // time() against a created_at stamped by NOW(): the difference came
        // out around -18000 seconds, always "less than 90", so the cooldown
        // never elapsed and no member could ever be sent a second code. The
        // same shape silently disabled every repeat password-reset request.
        //
        // A numeric offset rather than 'America/New_York': named zones need
        // the mysql.time_zone_* tables, which are empty on a default install
        // and would make this SET fail. date('P') is evaluated per connection
        // so it tracks DST on its own.
        $pdo->exec("SET time_zone = '" . date('P') . "'");

        return $pdo;
    } catch (PDOException $e) {
        if (ENV === 'dev') {
            echo "Database connection failed: " . $e->getMessage();
        } else {
            error_log("Database connection failed: " . $e->getMessage());
        }
        exit();
    }
}
?>
