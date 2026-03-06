<?php

function normalize_whitespace(string $value): string
{
    $value = preg_replace('/\s+/', ' ', trim($value));
    return $value ?? '';
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
    if (!$value) {
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
    if ($value === null || $value === '') {
        return null;
    }

    if (is_string($value)) {
        $value = str_replace([',', '$'], '', $value);
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float) $value, 2);
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
