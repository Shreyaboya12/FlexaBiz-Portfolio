<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string)$path, '/'));

if (is_file($file) || (is_dir($file) && is_file($file . DIRECTORY_SEPARATOR . 'index.php'))) {
    return false;
}

require __DIR__ . '/index.php';
