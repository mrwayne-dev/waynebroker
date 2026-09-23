<?php
/**
 * ============================================================
 * Maveren Capital - PLATFORM SETTINGS
 * ============================================================
 * Reads and writes the `settings` key/value table (see
 * dbschema/migrations/2026_09_03_settings.sql).
 *
 * Kept out of helpers.php on purpose: helpers.php is included by
 * nearly every endpoint, and this is only needed by the withdrawal
 * path and the admin settings screen.
 * ============================================================
 */

if (!function_exists('mvcSettingGet')) {

    /**
     * One setting as a string, or $default when the row is missing.
     *
     * Falls back to $default if the table does not exist yet, so an
     * environment that has not run the migration keeps working rather
     * than throwing on every withdrawal.
     */
    function mvcSettingGet(PDO $pdo, string $key, ?string $default = null): ?string
    {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value === false ? $default : (string) $value;
        } catch (Throwable $e) {
            error_log('mvcSettingGet(' . $key . '): ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * A setting as a non-negative float. Anything unparseable or
     * negative reads as $default, so a corrupt row cannot turn into a
     * negative threshold that silently disables a guard.
     */
    function mvcSettingGetFloat(PDO $pdo, string $key, float $default = 0.0): float
    {
        $raw = mvcSettingGet($pdo, $key, null);
        if ($raw === null || !is_numeric($raw)) {
            return $default;
        }
        $value = (float) $raw;
        return $value < 0 ? $default : $value;
    }

    /**
     * Upsert. Returns true on success.
     */
    function mvcSettingSet(PDO $pdo, string $key, string $value): bool
    {
        try {
            $pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            )->execute([$key, $value]);
            return true;
        } catch (Throwable $e) {
            error_log('mvcSettingSet(' . $key . '): ' . $e->getMessage());
            return false;
        }
    }
}
