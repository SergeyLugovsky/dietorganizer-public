<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('APP_ROOT', dirname(__DIR__));
define('APP_PUBLIC', __DIR__);
define('APP_PRIVATE', APP_ROOT . DIRECTORY_SEPARATOR . 'private');

$configFile = APP_PRIVATE . DIRECTORY_SEPARATOR . '.env.php';
$exampleFile = APP_PRIVATE . DIRECTORY_SEPARATOR . '.env.php.example';

$config = file_exists($configFile)
    ? require $configFile
    : require $exampleFile;

if (isset($config['session_name'])) {
    session_name($config['session_name']);
}

session_start();

require APP_PUBLIC . '/includes/i18n.php';
require APP_PUBLIC . '/includes/helpers.php';
require APP_PUBLIC . '/includes/auth.php';

$routes = [
    'home' => APP_PUBLIC . '/pages/home.php',
    'register' => APP_PUBLIC . '/pages/register.php',
    'login' => APP_PUBLIC . '/pages/login.php',
    'google_login' => APP_PUBLIC . '/pages/google_login.php',
    'logout' => APP_PUBLIC . '/pages/logout.php',
    'dashboard' => APP_PUBLIC . '/pages/dashboard.php',
    'foods' => APP_PUBLIC . '/pages/foods.php',
    'meal_categories' => APP_PUBLIC . '/pages/meal_categories.php',
    'diary' => APP_PUBLIC . '/pages/diary.php',
    'profile' => APP_PUBLIC . '/pages/profile.php',
];

$page = $_GET['page'] ?? 'home';
$page = trim($page, '/');

if ($page === '') {
    $page = 'home';
}

if (!array_key_exists($page, $routes)) {
    http_response_code(404);
    echo '<h1>' . h(t('404 Не знайдено')) . '</h1>';
    exit;
}

require $routes[$page];
