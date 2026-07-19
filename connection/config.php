<?php




define('BASE_PATH', dirname(__DIR__));
define('GJC_APP_VERSION', '1.0.0');

$projectFolder = basename(BASE_PATH);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';


if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . '/' . $projectFolder);




define('ASSETS_URL',     BASE_URL . '/assets');
define('CSS_URL',        BASE_URL . '/assets/css');
define('JS_URL',         BASE_URL . '/assets/js');
define('IMAGES_URL',     BASE_URL . '/assets/images');
define('ICONS_URL',      BASE_URL . '/assets/icons');

define('ADMIN_URL',      BASE_URL . '/admin');
define('MERCHANT_URL',   BASE_URL . '/merchant');
define('STUDENT_URL',    BASE_URL . '/student');
define('PARENT_URL',     BASE_URL . '/parent');
define('INCLUDES_URL',   BASE_URL . '/includes');
define('CONNECTION_URL', BASE_URL . '/connection');




define('ASSETS_PATH',     BASE_PATH . '/assets');
define('CSS_PATH',        BASE_PATH . '/assets/css');
define('JS_PATH',         BASE_PATH . '/assets/js');
define('IMAGES_PATH',     BASE_PATH . '/assets/images');
define('ICONS_PATH',      BASE_PATH . '/assets/icons');

define('ADMIN_PATH',      BASE_PATH . '/admin');
define('MERCHANT_PATH',   BASE_PATH . '/merchant');
define('STUDENT_PATH',    BASE_PATH . '/student');
define('PARENT_PATH',     BASE_PATH . '/parent');
define('INCLUDES_PATH',   BASE_PATH . '/includes');
define('CONNECTION_PATH', BASE_PATH . '/connection');

define('DASHBOARD_URL',  BASE_URL . '/dashboard.php');
define('DASHBOARD_PATH', BASE_PATH . '/dashboard.php');