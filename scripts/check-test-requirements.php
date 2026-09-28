<?php

$required = [
    'pdo_mysql',
    'mbstring',
    'openssl',
    'tokenizer',
    'xml',
    'ctype',
    'json',
    'bcmath',
    'curl',
];

$missing = array_values(array_filter($required, static fn (string $ext): bool => ! extension_loaded($ext)));

if ($missing !== []) {
    fwrite(STDERR, "Missing PHP extensions required for ACMS tests:\n");
    fwrite(STDERR, '  - '.implode("\n  - ", $missing)."\n\n");
    fwrite(STDERR, "Enable them in php.ini (XAMPP: C:\\xampp\\php\\php.ini)\n");
    fwrite(STDERR, "Then run tests with: C:\\xampp\\php\\php.exe artisan test\n");
    exit(1);
}

echo 'PHP test requirements OK ('.PHP_VERSION.')'.PHP_EOL;
