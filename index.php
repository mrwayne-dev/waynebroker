<?php
// index.php – Entry point for Lymora

// Start session
require_once __DIR__ . '/api/utilities/security.php';
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.
mvcSessionStart();

// Redirect root traffic to public index
header("Location: pages/public/index.php");
exit;
