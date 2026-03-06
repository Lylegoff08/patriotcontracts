<?php

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = app_config()['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function source_id_by_slug(PDO $pdo, string $slug): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM sources WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}
