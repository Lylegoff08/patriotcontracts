<?php

function normalize_whitespace(string $value): string
{
    $value = preg_replace('/\s+/', ' ', trim($value));
    return $value ?? '';
}

function is_placeholder_value($value): bool
{
    if ($value === null) {
        return true;
    }

    if (is_bool($value)) {
        return false;
    }

    if (is_array($value)) {
        return count($value) === 0;
    }

    if (is_object($value)) {
        return count((array) $value) === 0;
    }

    $text = strtolower(trim((string) $value));
    if ($text === '') {
        return true;
    }

    static $junk = [
        'n/a',
        'na',
        'null',
        'none',
        'not available',
        'not provided',
        'unknown',
        'undefined',
        '-',
        '--',
        'tbd',
    ];

    return in_array($text, $junk, true);
}

function flatten_text_value($value): ?string
{
    if ($value === null) {
        return null;
    }

    if (is_scalar($value)) {
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }

    if (is_object($value)) {
        $value = (array) $value;
    }

    if (!is_array($value)) {
        return null;
    }

    $parts = [];
    foreach ($value as $item) {
        $flattened = flatten_text_value($item);
        if ($flattened !== null && !is_placeholder_value($flattened)) {
            $parts[] = $flattened;
        }
    }

    if (!$parts) {
        return null;
    }

    return implode(', ', array_values(array_unique($parts)));
}

function clean_string($value): ?string
{
    $flat = flatten_text_value($value);
    if ($flat === null) {
        return null;
    }

    $normalized = normalize_whitespace($flat);
    if (is_placeholder_value($normalized)) {
        return null;
    }

    return $normalized;
}

function clean_nullable($value): ?string
{
    return clean_string($value);
}

function clean_contact_field($value): ?string
{
    $text = clean_string($value);
    if ($text === null) {
        return null;
    }
    if (is_placeholder_value($text)) {
        return null;
    }
    return $text;
}

function pick_first_nonempty(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $cleaned = clean_string($candidate);
        if ($cleaned !== null) {
            return $cleaned;
        }
    }
    return null;
}

function clean_date($value): ?string
{
    if (is_placeholder_value($value)) {
        return null;
    }

    if (is_numeric($value) && (int) $value > 0) {
        $ts = (int) $value;
        if ($ts > 1000000000000) {
            $ts = (int) floor($ts / 1000);
        }
        return date('Y-m-d', $ts);
    }

    $text = clean_string($value);
    return normalize_date($text);
}

function clean_money($value): ?float
{
    if (is_placeholder_value($value)) {
        return null;
    }

    if (is_array($value) || is_object($value)) {
        return null;
    }

    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    $negative = false;
    if (preg_match('/^\((.*)\)$/', $text, $m)) {
        $negative = true;
        $text = $m[1];
    }

    $text = str_replace([',', '$', 'USD', 'usd', ' '], '', $text);
    if ($text === '' || !is_numeric($text)) {
        return null;
    }

    $num = round((float) $text, 2);
    return $negative ? -1 * $num : $num;
}

function payload_get($payload, array $path)
{
    $cursor = $payload;
    foreach ($path as $segment) {
        if (is_array($cursor) && array_key_exists($segment, $cursor)) {
            $cursor = $cursor[$segment];
            continue;
        }
        if (is_object($cursor) && isset($cursor->{$segment})) {
            $cursor = $cursor->{$segment};
            continue;
        }
        return null;
    }
    return $cursor;
}

function payload_pick(array $payload, array $candidatePaths): ?string
{
    foreach ($candidatePaths as $path) {
        $value = payload_get($payload, $path);
        $clean = clean_string($value);
        if ($clean !== null) {
            return $clean;
        }
    }
    return null;
}

function normalize_agency_name(string $name): string
{
    $name = strtoupper(normalize_whitespace($name));

    $map = [
        'DOD' => 'DEPARTMENT OF DEFENSE',
        'DEPT OF DEFENSE' => 'DEPARTMENT OF DEFENSE',
        'DEPARTMENT OF THE ARMY' => 'DEPARTMENT OF DEFENSE',
        'DEPARTMENT OF THE NAVY' => 'DEPARTMENT OF DEFENSE',
        'DEPARTMENT OF THE AIR FORCE' => 'DEPARTMENT OF DEFENSE',
        'HHS' => 'DEPARTMENT OF HEALTH AND HUMAN SERVICES',
        'VA' => 'DEPARTMENT OF VETERANS AFFAIRS',
        'GSA' => 'GENERAL SERVICES ADMINISTRATION',
    ];

    return $map[$name] ?? $name;
}

function normalize_vendor_name(string $name): string
{
    $name = strtoupper(normalize_whitespace($name));
    $name = preg_replace('/\b(INC|LLC|LTD|CORP|CORPORATION|CO)\.?\b/', '', $name);
    $name = normalize_whitespace($name ?? '');
    return rtrim($name, ',.');
}

function normalize_date(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d', $timestamp);
}

function normalize_money($value): ?float
{
    return clean_money($value);
}

function normalize_status(?string $status): string
{
    $status = strtoupper(normalize_whitespace((string) $status));
    $map = [
        'ACTIVE' => 'active',
        'OPEN' => 'open',
        'ARCHIVED' => 'archived',
        'AWARDED' => 'awarded',
        'CLOSED' => 'closed',
        'FORECAST' => 'forecast',
    ];

    return $map[$status] ?? strtolower($status ?: 'unknown');
}
