<?php
// ============================================================
//  admin/doc.php  â€” Secure document proxy for admin viewing
//  Serves files from /uploads/ only to authenticated admins.
//  Usage: /admin/doc.php?f=uploads/stall_apps/abc.pdf
// ============================================================
session_start();
require_once __DIR__ . '/../connection/config.php';
require_once __DIR__ . '/../connection/pdo.php';
require_once __DIR__ . '/../connection/app.php';

gjc_require_role(['finance']);

$relPath = $_GET['f'] ?? '';

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

$absPath = BASE_PATH . '/' . $relPath;

if (!file_exists($absPath) || !is_file($absPath)) {
    http_response_code(404);
    exit('File not found.');
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

$size = filesize($absPath);

header('Content-Type: '  . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: inline; filename="' . basename($absPath) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($absPath);
exit;
