<?php
declare(strict_types=1);

const APP_NAME = 'CampusFind';
const UPLOAD_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
const MAX_UPLOAD_BYTES = 3 * 1024 * 1024;

function app_db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('CAMPUSFIND_DB_HOST') ?: '127.0.0.1';
    $database = getenv('CAMPUSFIND_DB_NAME') ?: 'campusfind';
    $username = getenv('CAMPUSFIND_DB_USER') ?: 'root';
    $password = getenv('CAMPUSFIND_DB_PASSWORD') ?: '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();
