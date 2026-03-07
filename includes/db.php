<?php

function request_host_from_server(): string
{
    $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    if ($forwardedHost !== '') {
        $parts = explode(',', $forwardedHost);
        return trim((string) ($parts[0] ?? ''));
    }

    return trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
}

function request_scheme_from_server(): string
{
    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto !== '') {
        $parts = explode(',', $forwardedProto);
        $proto = strtolower(trim((string) ($parts[0] ?? '')));
        if (in_array($proto, ['http', 'https'], true)) {
            return $proto;
        }
    }

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return 'https';
    }

    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    return $port === 443 ? 'https' : 'http';
}

function configured_base_path(array $config): string
{
    $configuredBaseUrl = trim((string) ($config['app']['base_url'] ?? ''));
    $configuredPath = (string) parse_url($configuredBaseUrl, PHP_URL_PATH);
    $configuredPath = trim($configuredPath);
    if ($configuredPath === '' || $configuredPath === '/') {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        $scriptDir = trim(str_replace('\\', '/', dirname($scriptName)));
        if ($scriptDir === '' || $scriptDir === '.' || $scriptDir === '/') {
            return '';
        }
        return '/' . trim($scriptDir, '/');
    }
    return '/' . trim($configuredPath, '/');
}

function resolve_runtime_base_url(array $config): string
{
    $configured = rtrim((string) ($config['app']['base_url'] ?? ''), '/');
    if (PHP_SAPI === 'cli') {
        return $configured;
    }

    $host = request_host_from_server();
    if ($host === '') {
        return $configured;
    }

    $scheme = request_scheme_from_server();
    return rtrim($scheme . '://' . $host . configured_base_path($config), '/');
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
        // Runtime BASE_URL follows the active request host/scheme so public links
        // work on localhost, ngrok tunnels, and production without manual edits.
        $config['app']['base_url'] = resolve_runtime_base_url($config);
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
