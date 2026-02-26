<?php
// public_html/includes/bootstrap.php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
if (!defined('APP_PUBLIC')) {
    define('APP_PUBLIC', dirname(__DIR__));
}
if (!defined('APP_PRIVATE')) {
    define('APP_PRIVATE', APP_ROOT . DIRECTORY_SEPARATOR . 'private');
}

$configFile = APP_PRIVATE . DIRECTORY_SEPARATOR . '.env.php';
$exampleFile = APP_PRIVATE . DIRECTORY_SEPARATOR . '.env.php.example';
$config = file_exists($configFile)
    ? require $configFile
    : require $exampleFile;

if (isset($config['session_name'])) {
    session_name($config['session_name']);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require APP_PUBLIC . '/includes/i18n.php';
require APP_PUBLIC . '/includes/helpers.php';
require APP_PUBLIC . '/includes/auth.php';
require APP_PUBLIC . '/includes/db.php';
