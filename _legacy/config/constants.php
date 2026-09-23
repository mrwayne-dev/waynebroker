<?php
// ========================================
// GLOBAL CONSTANTS - Maveren Capital Platform
// ========================================

define('APP_NAME',     'Maveren Capital');
define('APP_SHORT',    'MVC');
// APP_URL is defined in config/env.php (sourced from .env) so the .env value
// is authoritative regardless of include order. Guarded fallback in case a
// caller pulls in constants.php without env.php.
if (!defined('APP_URL')) define('APP_URL', 'https://maverencapital.com');
define('APP_TAGLINE',  'Steady yield. Plainly stated.');
define('CURRENCY',     'USD');
define('TIMEZONE',     'America/New_York');
define('OTP_EXPIRY_MINUTES', 10);
define('MAX_WITHDRAWAL_ATTEMPTS', 3);

// Business Timings / System
date_default_timezone_set(TIMEZONE);
?>
