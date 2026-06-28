<?php
// ============================================================
//  admin/doc.php  â€” Secure document proxy for admin viewing
//  Serves files from /uploads/ only to authenticated admins.
//  Usage: /admin/doc?f=uploads/stall_applications/1/file.pdf
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);

$relPath = (string) ($_GET['f'] ?? '');

// Sanitise: strip leading slashes, block traversal
$relPath = ltrim(str_replace('\\', '/', $relPath), '/');
if (strpos($relPath, '..') !== false || strpos($relPath, "\0") !== false) {
    http_response_code(400);
    exit('Invalid path.');
}

// Must be inside uploads/
if (!str_starts_with($relPath, 'uploads/')) {
    http_response_code(403);
    exit('Access denied.');
}

$baseUploads = realpath(BASE_PATH . '/uploads');
$absPath = realpath(BASE_PATH . '/' . $relPath);

if (!$baseUploads || !$absPath || !is_file($absPath)) {
    http_response_code(404);
    exit('File not found.');
}

if (strpos($absPath, $baseUploads . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    exit('Access denied.');
}

// Detect MIME
$ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
$mime = match($ext) {
    'pdf'         => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png'         => 'image/png',
    'gif'         => 'image/gif',
    'webp'        => 'image/webp',
    default       => 'application/octet-stream',
};

if (ob_get_length()) {
    ob_end_clean();
}

// For images, wrap in an HTML page so the browser scales them to fit the
// iframe instead of rendering at native (potentially huge) resolution.
if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    $dataUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: private, max-age=3600');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  html,body{width:100%;height:100%;background:#1e1e1e;display:flex;align-items:center;justify-content:center}
  img{max-width:100%;max-height:100vh;object-fit:contain;display:block}
</style></head><body>
<img src="' . $dataUrl . '" alt="' . htmlspecialchars(basename($absPath)) . '">
</body></html>';
    exit;
}

$size = filesize($absPath);

header('Content-Type: '  . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: inline; filename="' . basename($absPath) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');

readfile($absPath);
exit;
