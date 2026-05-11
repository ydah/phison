<?php

declare(strict_types=1);

$phpunit = __DIR__ . '/../vendor/bin/phpunit';
if (is_file($phpunit)) {
    passthru(escapeshellarg($phpunit), $exitCode);
    exit($exitCode);
}

fwrite(STDERR, "PHPUnit is not installed. Run composer install first.\n");
exit(1);
