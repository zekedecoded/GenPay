<?php

// ============================================================
//  BASE
// ============================================================
define('BASE_PATH', dirname(__DIR__)); // points to project root (one level up from connection/)

$projectFolder = basename(BASE_PATH);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host . '/' . $projectFolder);

// ============================================================
//  URL CONSTANTS  (for HTML links, redirects, asset references)
// ============================================================
define('ASSETS_URL',     BASE_URL . '/assets');
define('CSS_URL',        BASE_URL . '/assets/css');
define('JS_URL',         BASE_URL . '/assets/js');
define('IMAGES_URL',     BASE_URL . '/assets/images');
define('ICONS_URL',      BASE_URL . '/assets/icons');

define('ADMIN_URL',      BASE_URL . '/admin');
define('MERCHANT_URL',   BASE_URL . '/merchant');
define('STUDENT_URL',    BASE_URL . '/student');
define('INCLUDES_URL',   BASE_URL . '/includes');
define('CONNECTION_URL', BASE_URL . '/connection');

// ============================================================
//  FILE PATH CONSTANTS  (for require_once / include)
// ============================================================
define('ASSETS_PATH',     BASE_PATH . '/assets');
define('CSS_PATH',        BASE_PATH . '/assets/css');
define('JS_PATH',         BASE_PATH . '/assets/js');
define('IMAGES_PATH',     BASE_PATH . '/assets/images');
define('ICONS_PATH',      BASE_PATH . '/assets/icons');

define('ADMIN_PATH',      BASE_PATH . '/admin');
define('MERCHANT_PATH',   BASE_PATH . '/merchant');
define('STUDENT_PATH',    BASE_PATH . '/student');
define('INCLUDES_PATH',   BASE_PATH . '/includes');
define('CONNECTION_PATH', BASE_PATH . '/connection');
