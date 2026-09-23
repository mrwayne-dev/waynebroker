<?php
// ===============================================
// FILE: /api/backend/upload_avatar.php
// PURPOSE: Handle profile photo upload (multipart/form-data).
// Field: avatar | Stores to /uploads/profiles/{user_id}_{ts}.ext
// ===============================================
// Hardened + proxy-aware session cookie (HttpOnly, Secure, SameSite=Strict,
// use_strict_mode). A bare session_start() inherited this box's ini defaults,
// which set NONE of those - see api/utilities/security.php.

require_once __DIR__ . '/../../api/utilities/security.php';
mvcSessionStart();

// CSRF. Safe methods return immediately; anything else must present the
// session token as X-CSRF-Token (assets/js/api.js sends it on every POST).
mvcCsrfEnforce();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

function respond($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Keep in step with the check in assets/js/profile.js.
const MAX_AVATAR_BYTES = 10 * 1024 * 1024;

if (!isset($_FILES['avatar'])) {
    // An upload larger than post_max_size arrives with $_FILES and $_POST both
    // EMPTY - PHP discards the body before this script runs - so the generic
    // "no file uploaded" message was what a user actually saw when they picked
    // an oversized image. Distinguish the two.
    if (($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST)) {
        respond('error', 'That image is larger than the server accepts. Please use one under 10 MB.');
    }
    respond('error', 'No file uploaded or upload failed.');
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    // UPLOAD_ERR_INI_SIZE / _FORM_SIZE are the size rejections PHP performs
    // itself; everything else really is a failed upload.
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        respond('error', 'Image must be 10 MB or smaller.');
    }
    respond('error', 'No file uploaded or upload failed.');
}

if ($file['size'] > MAX_AVATAR_BYTES) {
    respond('error', 'Image must be 10 MB or smaller.');
}

// Validate true MIME type
$allowed = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!isset($allowed[$mime])) {
    respond('error', 'Only PNG, JPG, or WEBP images are allowed.');
}
$ext = $allowed[$mime];

$uploadDir = __DIR__ . '/../../uploads/profiles/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$filename = $user_id . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;
$webPath  = '/uploads/profiles/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respond('error', 'Could not save the uploaded image.');
}

/**
 * Square-crop and downscale in place.
 *
 * The raw upload used to be stored byte for byte, so a 6000x3375 phone photo
 * was shipped to every page load to be drawn in a 50px topbar slot. It also
 * meant the stored aspect ratio was arbitrary, which is what let a portrait
 * image stretch the avatar chip before the CSS clamp was added.
 *
 * Best effort: if GD is missing or the decode fails, keep the original file
 * rather than failing the upload. The CSS clamp covers the display side
 * either way.
 */
function mvc_square_crop_in_place(string $path, string $ext, int $size = 512): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $src = match ($ext) {
        'png'  => @imagecreatefrompng($path),
        'jpg'  => @imagecreatefromjpeg($path),
        'webp' => @imagecreatefromwebp($path),
        default => false,
    };
    if (!$src) {
        return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $edge = min($size, $side);          // never upscale a small avatar

    // Centre crop to a square, then scale that square to $edge.
    $srcX = (int) (($w - $side) / 2);
    $srcY = (int) (($h - $side) / 2);

    $dst = imagecreatetruecolor($edge, $edge);
    if ($ext === 'png' || $ext === 'webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $edge, $edge, $side, $side);

    $ok = match ($ext) {
        'png'  => imagepng($dst, $path, 6),
        'jpg'  => imagejpeg($dst, $path, 85),
        'webp' => imagewebp($dst, $path, 85),
        default => false,
    };

    imagedestroy($src);
    imagedestroy($dst);

    return (bool) $ok;
}

mvc_square_crop_in_place($destPath, $ext);

try {
    $pdo = getPDO();

    // Fetch previous picture for best-effort cleanup
    $prevStmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $prevStmt->execute([$user_id]);
    $prev = $prevStmt->fetchColumn();

    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?")->execute([$webPath, $user_id]);
    $_SESSION['profile_picture'] = $webPath;

    // Delete the old custom upload (never the shared default)
    if ($prev && strpos($prev, '/uploads/profiles/') === 0) {
        $oldFile = __DIR__ . '/../../' . ltrim($prev, '/');
        if (is_file($oldFile)) @unlink($oldFile);
    }

    respond('success', 'Profile photo updated.', ['profile_picture' => $webPath]);
} catch (Throwable $e) {
    error_log('upload_avatar.php: ' . $e->getMessage());
    respond('error', 'Server error while saving image.');
}
