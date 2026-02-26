<?php
$privateRoot = defined('APP_PRIVATE') ? APP_PRIVATE : dirname(__DIR__, 2) . '/private';
$configFile = $privateRoot . '/.env.php';
$exampleFile = $privateRoot . '/.env.php.example';
$config = $config ?? (file_exists($configFile) ? require $configFile : require $exampleFile);
$dbConfig = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['name'],
    $dbConfig['charset']
);

$pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
