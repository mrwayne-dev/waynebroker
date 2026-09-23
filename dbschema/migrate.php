<?php
// ============================================================
// FILE: dbschema/migrate.php
// PURPOSE: Apply pending SQL migrations, once each, in order.
//
// Until now `dbschema/migrations/` was six loose .sql files applied by
// hand with `mysql < file`. Nothing recorded what had been run, so there
// was no way to answer "is production's schema current?" except by
// inspecting information_schema by hand - and a fresh install from
// maveren_create.sql silently lacked contact_messages and
// rate_limit_hits, which breaks the contact form and ALL rate limiting.
//
// This runner makes the install path deterministic:
//
//     mysql -u <user> -p <db> < dbschema/maveren_create.sql   # baseline
//     php dbschema/migrate.php                                   # everything since
//
// USAGE
//   php dbschema/migrate.php              apply everything pending
//   php dbschema/migrate.php --status     list applied/pending
//     Applies no MIGRATION, but it is not read-only: the ledger table is
//     created before the mode is dispatched, so --status against a fresh
//     database leaves schema_migrations behind. Anything deciding whether a
//     database is empty must discount that table, or a half-built install
//     reads as populated.
//   php dbschema/migrate.php --dry-run    show what WOULD run
//   php dbschema/migrate.php --baseline   mark all current files as applied
//                                         without running them (for a database
//                                         that already had them applied by hand)
//   php dbschema/migrate.php --resync     re-record the checksum of an
//                                         ALREADY-APPLIED file whose content
//                                         changed. Use only when you have read
//                                         the diff and confirmed it is
//                                         behaviour-preserving for a database
//                                         that already ran the old version -
//                                         adding an existence guard, fixing a
//                                         comment. If the change alters what
//                                         the migration DOES, write a new
//                                         migration instead; this flag will
//                                         happily hide a real divergence.
//
// The migrations are individually idempotent (information_schema guards,
// CREATE TABLE IF NOT EXISTS), so re-running one is harmless. The ledger
// exists to make that a guarantee rather than a hope.
//
// Execution is delegated to the mysql client, not PDO: the migrations use
// PREPARE/EXECUTE/DEALLOCATE multi-statement blocks, and reimplementing a
// SQL statement splitter that handles those correctly is a bug factory.
// The header of every migration already documents `mysql < file` as the
// run command; this uses exactly that.
// ============================================================

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Access Denied: CLI only\n");
}

require_once __DIR__ . '/../config/env.php';

$args     = array_slice($argv, 1);
$status   = in_array('--status', $args, true);
$dryRun   = in_array('--dry-run', $args, true);
$baseline = in_array('--baseline', $args, true);
$resync   = in_array('--resync', $args, true);

$dir = __DIR__ . '/migrations';
if (!is_dir($dir)) {
    exit("No migrations directory at {$dir}\n");
}

// Sorted by filename. The YYYY_MM_DD_ prefix makes lexical order == chronological.
$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_STRING);
if (!$files) {
    exit("No migrations found.\n");
}

// ------------------------------------------------------------
// mysql client invocation
//
// Credentials go in a 0600 defaults-file, never on the command line:
// argv is world-readable through /proc on shared hosting, so
// `mysql -pSECRET` leaks the database password to every other tenant
// for the lifetime of the process.
// ------------------------------------------------------------
$cnf = tempnam(sys_get_temp_dir(), 'mvcmig');
if ($cnf === false) {
    exit("Could not create a temporary defaults-file.\n");
}
chmod($cnf, 0600);
file_put_contents($cnf, sprintf(
    "[client]\nhost=%s\nuser=%s\npassword=\"%s\"\n",
    DB_HOST,
    DB_USER,
    str_replace(['\\', '"'], ['\\\\', '\\"'], DB_PASS)
));
register_shutdown_function(static function () use ($cnf) {
    if (is_file($cnf)) {
        unlink($cnf);
    }
});

/**
 * Run SQL through the mysql client. Returns [exitCode, combinedOutput].
 *
 * @param string|null $file Read from this file instead of $sql when given.
 */
function mvcMysql(string $cnf, string $db, ?string $sql = null, ?string $file = null): array
{
    $cmd = sprintf(
        '%s --defaults-extra-file=%s %s 2>&1',
        escapeshellcmd('mysql'),
        escapeshellarg($cnf),
        escapeshellarg($db)
    );
    if ($file !== null) {
        $cmd .= ' < ' . escapeshellarg($file);
        exec($cmd, $out, $code);
        return [$code, implode("\n", $out)];
    }

    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return [1, 'could not start mysql'];
    }
    fwrite($pipes[0], (string) $sql);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($proc), (string) $out];
}

// ------------------------------------------------------------
// Ledger
//
// `checksum` is what turns this from a list into a guarantee: if a
// migration file is edited AFTER being applied, the recorded hash no
// longer matches and the runner says so instead of skipping it silently
// and leaving two environments quietly different.
// ------------------------------------------------------------
[$code, $out] = mvcMysql($cnf, DB_NAME, <<<SQL
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `filename`   VARCHAR(191) NOT NULL PRIMARY KEY,
  `checksum`   CHAR(64)     NOT NULL,
  `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
if ($code !== 0) {
    fwrite(STDERR, "Could not create schema_migrations:\n{$out}\n");
    exit(1);
}

[$code, $out] = mvcMysql($cnf, DB_NAME,
    'SELECT filename, checksum FROM schema_migrations;');
if ($code !== 0) {
    fwrite(STDERR, "Could not read schema_migrations:\n{$out}\n");
    exit(1);
}

// Match on row SHAPE rather than dropping "the first line".
//
// mvcMysql() folds stderr into stdout, and the MariaDB client on this host
// prints "mysql: Deprecated program name..." on every invocation. Blindly
// slicing off line 1 therefore discarded the WARNING and let the real header
// row (filename<TAB>checksum) into the map - reporting "7 applied" against 6
// files. It never caused a migration to be skipped, because "filename" matches
// no real file, but a ledger that miscounts itself is not worth having.
$applied = [];
foreach (explode("\n", trim($out)) as $line) {
    if (!str_contains($line, "\t")) {
        continue;                       // warning / notice / blank
    }
    [$name, $sum] = array_pad(explode("\t", $line, 2), 2, '');
    if (!str_ends_with($name, '.sql')) {
        continue;                       // header row, or anything unexpected
    }
    $applied[$name] = $sum;
}

// ------------------------------------------------------------
// Plan
// ------------------------------------------------------------
$pending = [];
$drifted = [];
foreach ($files as $path) {
    $name = basename($path);
    $sum  = hash_file('sha256', $path);
    if (!isset($applied[$name])) {
        $pending[] = [$name, $path, $sum];
    } elseif ($applied[$name] !== $sum) {
        $drifted[] = $name;
    }
}

printf("Database: %s@%s\n", DB_NAME, DB_HOST);
printf("Migrations: %d total, %d applied, %d pending\n\n",
    count($files), count($applied), count($pending));

foreach ($files as $path) {
    $name = basename($path);
    $mark = isset($applied[$name])
        ? (in_array($name, $drifted, true) ? 'CHANGED ' : 'applied ')
        : 'PENDING ';
    printf("  [%s] %s\n", $mark, $name);
}
echo "\n";

if ($drifted) {
    fwrite(STDERR, "WARNING: these files changed after they were applied:\n  - "
        . implode("\n  - ", $drifted) . "\n"
        . "This database and the repo have diverged. Review the diff before trusting either.\n"
        . "The runner will NOT re-apply them; write a new migration instead.\n\n");
}

if ($resync) {
    if (!$drifted) {
        echo "Nothing to resync.\n";
        exit(0);
    }
    foreach ($drifted as $name) {
        $sum = hash_file('sha256', $dir . '/' . $name);
        [$code, $out] = mvcMysql($cnf, DB_NAME, sprintf(
            "UPDATE schema_migrations SET checksum = %s WHERE filename = %s;",
            "'" . addslashes($sum) . "'",
            "'" . addslashes($name) . "'"
        ));
        if ($code !== 0) {
            fwrite(STDERR, "Could not resync {$name}:\n{$out}\n");
            exit(1);
        }
        echo "  resynced {$name}\n";
    }
    echo "\nDone. " . count($drifted) . " checksum(s) re-recorded. Nothing was executed.\n";
    exit(0);
}

if ($status) {
    exit($drifted ? 1 : 0);
}

if (!$pending) {
    echo "Nothing to do.\n";
    exit($drifted ? 1 : 0);
}

if ($dryRun) {
    echo "--dry-run: " . count($pending) . " migration(s) would run.\n";
    exit(0);
}

// ------------------------------------------------------------
// Apply
//
// MySQL DDL is not transactional - an ALTER cannot be rolled back by
// wrapping it - so each file is recorded immediately after it succeeds
// and the run stops at the first failure. That leaves the ledger
// truthful about exactly how far it got, which is the property that
// matters when resuming.
// ------------------------------------------------------------
foreach ($pending as [$name, $path, $sum]) {
    if ($baseline) {
        echo "  baselining {$name} (not executed)\n";
    } else {
        echo "  applying {$name} ... ";
        [$code, $out] = mvcMysql($cnf, DB_NAME, null, $path);
        if ($code !== 0) {
            echo "FAILED\n";
            fwrite(STDERR, "\n{$out}\n\nStopped. "
                . count($pending) . " were pending; the ledger records what succeeded.\n");
            exit(1);
        }
        echo "ok\n";
    }

    [$code, $out] = mvcMysql($cnf, DB_NAME, sprintf(
        "INSERT INTO schema_migrations (filename, checksum) VALUES (%s, %s)
         ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), applied_at = NOW();",
        "'" . addslashes($name) . "'",
        "'" . addslashes($sum) . "'"
    ));
    if ($code !== 0) {
        fwrite(STDERR, "Applied {$name} but could not record it:\n{$out}\n");
        exit(1);
    }
}

echo "\nDone. " . count($pending) . ($baseline ? " baselined.\n" : " applied.\n");
exit(0);
