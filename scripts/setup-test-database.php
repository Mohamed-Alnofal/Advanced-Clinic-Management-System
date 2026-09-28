<?php

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_DATABASE') ?: 'acms_testing';

$dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $safeName = str_replace('`', '``', $database);
    $pdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    echo "Test database ready: {$database}".PHP_EOL;
} catch (PDOException $exception) {
    fwrite(STDERR, "Failed to prepare test database '{$database}'.".PHP_EOL);
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    fwrite(STDERR, PHP_EOL.'Fix: start MySQL (XAMPP) then run:'.PHP_EOL);
    fwrite(STDERR, '  composer test:db'.PHP_EOL);
    fwrite(STDERR, 'Or in phpMyAdmin: CREATE DATABASE acms_testing;'.PHP_EOL);
    exit(1);
}
