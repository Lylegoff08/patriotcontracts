<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/description_normalizer.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_base_url(): string
{
    return rtrim((string) (app_config()['app']['base_url'] ?? ''), '/');
}

function app_url(string $path = ''): string
{
    $path = trim($path);
    if ($path === '') {
        return app_base_url();
    }
    return app_base_url() . '/' . ltrim($path, '/');
}

function oauth_provider_configured(string $provider): bool
{
    $provider = strtolower(trim($provider));
    $cfg = app_config()['oauth'][$provider] ?? null;
    if (!is_array($cfg)) {
        return false;
    }

    if ($provider === 'google' || $provider === 'apple') {
        return trim((string) ($cfg['client_id'] ?? '')) !== '' && trim((string) ($cfg['redirect_uri'] ?? '')) !== '';
    }
    if ($provider === 'facebook') {
        return trim((string) ($cfg['app_id'] ?? '')) !== '' && trim((string) ($cfg['redirect_uri'] ?? '')) !== '';
    }

    return false;
}

function request_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    return trim((string) $value);
}

function request_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    return (int) $value;
}

function paginate(int $page, int $perPage = 20): array
{
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    return [$page, $perPage, $offset];
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_or_create_agency(PDO $pdo, string $agencyName, ?string $agencyCode = null): ?int
{
    $agencyName = trim($agencyName);
    if ($agencyName === '') {
        return null;
    }

    require_once __DIR__ . '/normalize.php';
    $normalized = normalize_agency_name($agencyName);

    $find = $pdo->prepare('SELECT id FROM agencies WHERE normalized_name = :normalized LIMIT 1');
    $find->execute(['normalized' => $normalized]);
    $existing = $find->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $insert = $pdo->prepare('INSERT INTO agencies (name, normalized_name, agency_code) VALUES (:name, :normalized, :agency_code)');
    $insert->execute([
        'name' => $agencyName,
        'normalized' => $normalized,
        'agency_code' => $agencyCode,
    ]);
    return (int) $pdo->lastInsertId();
}

function get_or_create_vendor(PDO $pdo, string $vendorName, ?string $uei = null, ?string $duns = null): ?int
{
    $vendorName = trim($vendorName);
    if ($vendorName === '') {
        return null;
    }

    require_once __DIR__ . '/normalize.php';
    $normalized = normalize_vendor_name($vendorName);

    $find = $pdo->prepare('SELECT id FROM vendors WHERE normalized_name = :normalized LIMIT 1');
    $find->execute(['normalized' => $normalized]);
    $existing = $find->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $insert = $pdo->prepare('INSERT INTO vendors (name, normalized_name, uei, duns) VALUES (:name, :normalized, :uei, :duns)');
    $insert->execute([
        'name' => $vendorName,
        'normalized' => $normalized,
        'uei' => $uei,
        'duns' => $duns,
    ]);
    return (int) $pdo->lastInsertId();
}

function table_counts(PDO $pdo): array
{
    $tables = ['contracts_raw', 'contracts_clean', 'agencies', 'vendors', 'users'];
    $out = [];
    foreach ($tables as $table) {
        $out[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
    return $out;
}

function site_setting(PDO $pdo, string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();
    } catch (Throwable $e) {
        $value = false;
    }

    $cache[$key] = $value !== false ? (string) $value : $default;
    return $cache[$key];
}

function page_title(string $default = 'PatriotContracts'): string
{
    if (!empty($GLOBALS['pageTitle']) && is_string($GLOBALS['pageTitle'])) {
        return trim($GLOBALS['pageTitle']);
    }
    return $default;
}

function parse_public_date(?string $value): ?DateTimeImmutable
{
    $text = trim((string) $value);
    if ($text === '' || is_empty_display_value($text)) {
        return null;
    }

    $normalized = normalize_date($text);
    if ($normalized === null) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $normalized);
    if (!$date) {
        return null;
    }

    return $date->setTime(0, 0, 0);
}

function public_today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

function normalize_public_label_case(string $text): string
{
    $trimmed = trim($text);
    if ($trimmed === '') {
        return $trimmed;
    }

    $upper = strtoupper($trimmed);
    $letters = preg_replace('/[^A-Za-z]/', '', $trimmed) ?? '';
    $upperLetters = preg_replace('/[^A-Z]/', '', $trimmed) ?? '';
    $isMostlyUpper = $letters !== '' && (strlen($upperLetters) / max(1, strlen($letters))) > 0.78;

    if ($isMostlyUpper) {
        $trimmed = ucwords(strtolower($trimmed));
    }

    $acronyms = ['US', 'USA', 'DOD', 'HHS', 'VA', 'GSA', 'NASA', 'DHS', 'FAA', 'FEMA', 'SBA', 'USDA', 'DOE', 'DOI', 'DOJ', 'CDC', 'CMS'];
    foreach ($acronyms as $acronym) {
        $trimmed = preg_replace('/\b' . preg_quote(ucfirst(strtolower($acronym)), '/') . '\b/', $acronym, $trimmed) ?? $trimmed;
    }

    return normalize_whitespace($trimmed);
}

function normalize_agency_display(?string $value): ?string
{
    $text = clean_string($value);
    if ($text === null) {
        return null;
    }

    $text = preg_replace('~https?://\S+~i', ' ', $text) ?? $text;
    $text = preg_replace('/\s*(?:\||;|>|::|\/{2,}|\\\\)\s*/', ' | ', $text) ?? $text;
    $text = preg_replace('/\s+\/\s+/', ' | ', $text) ?? $text;
    $text = preg_replace('/\s*,\s*(?=[A-Z][A-Za-z ]{3,}\s*,)/', ' | ', $text) ?? $text;

    if (preg_match('/^[A-Z0-9&\-\s\(\)]+(?:\.[A-Z0-9&\-\s\(\)]+){1,}$/', $text)) {
        $text = preg_replace('/\.+/', ' | ', $text) ?? $text;
    }

    $parts = preg_split('/\s+\|\s+/', normalize_whitespace($text)) ?: [];
    $deduped = [];
    $seen = [];
    foreach ($parts as $part) {
        $clean = normalize_whitespace((string) $part);
        $clean = preg_replace('/\b([A-Za-z][A-Za-z &\-\(\)]{3,})\s+\1\b/i', '$1', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B,.;-");
        if ($clean === '' || is_empty_display_value($clean)) {
            continue;
        }

        $canon = preg_replace('/[^A-Z0-9]/', '', strtoupper($clean)) ?? '';
        if ($canon === '' || isset($seen[$canon])) {
            continue;
        }
        $seen[$canon] = true;
        $deduped[] = normalize_public_label_case($clean);
        if (count($deduped) >= 3) {
            break;
        }
    }

    if (!$deduped) {
        return null;
    }

    return implode(' | ', $deduped);
}

function normalize_public_status_label(?string $value): ?string
{
    $status = normalize_status($value);
    $map = [
        'active' => 'Active',
        'open' => 'Open',
        'closed' => 'Closed',
        'awarded' => 'Awarded',
        'archived' => 'Archived',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'forecast' => 'Forecast',
        'historical' => 'Historical',
        'unknown' => null,
    ];

    if (isset($map[$status])) {
        return $map[$status];
    }

    $text = clean_string($value);
    return $text === null ? null : normalize_public_label_case($text);
}

function sanitize_public_field_value(string $field, ?string $value): ?string
{
    if ($field === 'source_url') {
        return safe_external_url($value);
    }

    if (in_array($field, ['posted_date', 'award_date', 'response_deadline', 'end_date'], true)) {
        $date = parse_public_date($value);
        return $date ? $date->format('Y-m-d') : null;
    }

    $text = clean_string($value);
    if ($text === null || is_empty_display_value($text)) {
        return null;
    }

    if (preg_match('~https?://~i', $text) && $field !== 'description') {
        $text = preg_replace('~https?://\S+~i', '', $text) ?? $text;
        $text = normalize_whitespace($text);
    }

    if ($text === '' || is_empty_display_value($text)) {
        return null;
    }

    if ($field === 'agency' || $field === 'department' || $field === 'contracting_office') {
        return normalize_agency_display($text);
    }

    if ($field === 'status') {
        return normalize_public_status_label($text);
    }

    if ($field === 'source_name' || $field === 'category' || $field === 'set_aside') {
        return normalize_public_label_case($text);
    }

    if ($field === 'naics_code' || $field === 'psc_code') {
        $code = strtoupper(trim($text));
        if (!preg_match('/^[A-Z0-9\-]{2,12}$/', $code)) {
            return null;
        }
        return $code;
    }

    return $text;
}

function derive_public_contract_state(array $row): array
{
    $today = public_today();
    $postedDate = parse_public_date($row['posted_date'] ?? null);
    $awardDate = parse_public_date($row['award_date'] ?? null);
    $dueDate = parse_public_date($row['response_deadline'] ?? null);
    $endDate = parse_public_date($row['end_date'] ?? null);
    $hasDueText = !is_empty_display_value($row['response_deadline'] ?? null);

    $status = strtolower(trim((string) ($row['status'] ?? '')));
    $noticeType = strtolower(trim((string) ($row['notice_type'] ?? '')));
    $sourceType = strtolower(trim((string) ($row['source_type'] ?? '')));
    $title = strtolower(trim((string) ($row['title'] ?? '')));
    $desc = strtolower(trim((string) ($row['description'] ?? ($row['description_clean'] ?? $row['description_raw'] ?? ''))));
    $text = $status . ' ' . $noticeType . ' ' . $sourceType . ' ' . $title . ' ' . $desc;

    $explicitEarly = (bool) preg_match('/rfi|request for information|sources sought|presolicitation|pre-solicitation|market research|forecast|industry day/', $text);
    $explicitAward = (bool) preg_match('/award|awarded|historical|contract action/', $text);
    $explicitOpen = (bool) preg_match('/open|active|solicitation|opportunity|combined synopsis|special notice|request for proposal|invitation for bid/', $text);
    $explicitClosed = (bool) preg_match('/closed|archived|expired|cancelled|canceled|inactive|ended/', $text);

    $isAwarded = ((int) ($row['is_awarded'] ?? 0) === 1)
        || $awardDate !== null
        || $explicitAward
        || $sourceType === 'usaspending'
        || $sourceType === 'sam_award';

    $isPastDue = $dueDate !== null && $dueDate < $today;
    $isEnded = $endDate !== null && $endDate < $today;

    $isArchived = !$isAwarded && ($explicitClosed || $isPastDue || $isEnded);
    $isEarlySignal = !$isAwarded && !$isArchived && (((int) ($row['is_upcoming_signal'] ?? 0) === 1) || $explicitEarly);

    $dueIsFutureOrToday = $dueDate !== null && $dueDate >= $today;
    $hasInvalidDue = $hasDueText && $dueDate === null;
    $postedFresh = $postedDate === null || $postedDate >= $today->sub(new DateInterval('P45D'));

    $isOpenNow = false;
    if (!$isAwarded && !$isArchived && !$isEarlySignal && !$hasInvalidDue) {
        if ($dueDate !== null) {
            $isOpenNow = $dueIsFutureOrToday && (((int) ($row['is_biddable_now'] ?? 0) === 1) || $explicitOpen);
        } else {
            $isOpenNow = (((int) ($row['is_biddable_now'] ?? 0) === 1) || $explicitOpen) && $postedFresh;
        }
    }

    $deadlineSoon = false;
    if ($isOpenNow && $dueDate !== null) {
        $days = (int) $today->diff($dueDate)->format('%r%a');
        $deadlineSoon = $days >= 0 && $days <= 7;
    }

    $tags = [];
    if ($isOpenNow) {
        $tags[] = 'Open Now';
    }
    if ($isEarlySignal) {
        $tags[] = 'Early Signal';
    }
    if ($isAwarded) {
        $tags[] = 'Awarded';
    }
    if ($deadlineSoon) {
        $tags[] = 'Deadline Soon';
    }
    if ($isArchived && !$isAwarded) {
        $tags[] = 'Archived';
    }

    $publicStatus = 'Unknown';
    if ($isAwarded) {
        $publicStatus = 'Awarded';
    } elseif ($isArchived) {
        $publicStatus = 'Archived';
    } elseif ($deadlineSoon) {
        $publicStatus = 'Deadline Soon';
    } elseif ($isOpenNow) {
        $publicStatus = 'Open Now';
    } elseif ($isEarlySignal) {
        $publicStatus = 'Early Signals';
    } else {
        $fallbackStatus = normalize_public_status_label($row['status'] ?? null);
        if ($fallbackStatus !== null) {
            $publicStatus = $fallbackStatus;
        }
    }

    return [
        'is_open_now' => $isOpenNow,
        'is_early_signal' => $isEarlySignal,
        'is_awarded' => $isAwarded,
        'is_archived' => $isArchived,
        'deadline_soon' => $deadlineSoon,
        'public_status' => $publicStatus,
        'tags' => $tags,
        'has_invalid_due' => $hasInvalidDue,
    ];
}

function is_suspicious_public_record(array $row): bool
{
    $title = strtolower(trim((string) ($row['title'] ?? '')));
    $desc = strtolower(trim((string) ($row['description'] ?? ($row['description_raw'] ?? ''))));
    $contractNumber = trim((string) ($row['contract_number'] ?? ''));

    $isObviousTest = (bool) preg_match('/\b(test|dummy|sample|lorem ipsum)\b/', $title)
        && $contractNumber === ''
        && ($desc === '' || (bool) preg_match('/\b(test|dummy|sample|placeholder)\b/', $desc));

    $dateFields = ['posted_date', 'award_date', 'response_deadline', 'end_date'];
    foreach ($dateFields as $field) {
        $date = parse_public_date($row[$field] ?? null);
        if ($date === null) {
            continue;
        }
        $year = (int) $date->format('Y');
        if ($year < 1990 || $year > 2100) {
            return true;
        }
    }

    return $isObviousTest;
}

function contract_effective_description(array $row): string
{
    $candidates = [
        $row['display_summary'] ?? null,
        $row['description_clean'] ?? null,
        $row['description_raw'] ?? null,
        $row['description'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $raw = trim((string) $candidate);
        if (is_empty_display_value($raw) || !description_is_displayable_text($raw)) {
            continue;
        }

        $normalized = normalize_listing_description((string) ($row['source_type'] ?? 'unknown'), $raw, [
            'set_aside_label' => $row['set_aside_label'] ?? null,
            'source_record_id' => $row['source_record_id'] ?? null,
        ]);
        $display = trim((string) ($normalized['description_display'] ?? ''));

        if (description_is_displayable_text($display)) {
            return $display;
        }
    }

    return '';
}

function contract_listing_description(array $row): string
{
    $summary = contract_effective_description($row);
    if (is_empty_display_value($summary)) {
        return '';
    }
    if (mb_strlen($summary) > 420) {
        return rtrim(mb_substr($summary, 0, 417)) . '...';
    }
    return $summary;
}

function is_empty_display_value($value): bool
{
    $text = strtolower(trim((string) $value));
    if ($text === '') {
        return true;
    }

    if ($text === 'array' || str_starts_with($text, 'array(') || str_starts_with($text, 'array (')) {
        return true;
    }

    static $junk = [
        'n/a', 'na', 'null', 'none', 'not available', 'not provided', 'unknown', '-', '--',
        'not listed', 'state not listed', 'date not listed', 'not specified',
        'source notice unavailable', 'no summary provided', 'location not specified',
        'no contact listed', 'no contact email listed', 'no contact phone listed', 'address not listed',
    ];
    return in_array($text, $junk, true);
}

function display_text(?string $value, string $fallback = 'N/A'): string
{
    $text = trim((string) $value);
    return is_empty_display_value($text) ? $fallback : $text;
}

function display_date(?string $value, string $fallback = 'N/A'): string
{
    $text = trim((string) $value);
    if (is_empty_display_value($text)) {
        return $fallback;
    }

    $ts = strtotime($text);
    if ($ts === false) {
        return $text;
    }

    return date('Y-m-d', $ts);
}

function display_field_value(string $field, ?string $value): string
{
    static $fallbacks = [
        'title' => 'Untitled listing',
        'agency' => 'Not provided',
        'department' => 'Not provided',
        'vendor' => 'Not provided',
        'category' => 'Not categorized',
        'notice_type' => 'Not specified',
        'contract_number' => 'Not listed',
        'posted_date' => 'Date not listed',
        'award_date' => 'Not listed',
        'response_deadline' => 'No deadline listed',
        'end_date' => 'Not listed',
        'set_aside' => 'Not specified',
        'naics_code' => 'Not listed',
        'naics_description' => 'Description unavailable',
        'psc_code' => 'Not listed',
        'place_of_performance' => 'Location not specified',
        'place_state' => 'State not listed',
        'contact_name' => 'No contact listed',
        'contact_email' => 'No contact email listed',
        'contact_phone' => 'No contact phone listed',
        'contracting_office' => 'Not listed',
        'contact_address' => 'Address not listed',
        'award_value' => 'Not listed',
        'description' => 'No summary provided',
        'status' => 'Status not specified',
        'source_name' => 'Unknown source',
        'source_url' => 'Source notice unavailable',
    ];

    $fallback = $fallbacks[$field] ?? 'Not provided';

    $sanitized = sanitize_public_field_value($field, $value);
    return $sanitized === null ? $fallback : $sanitized;
}

function display_field_or_null(string $field, ?string $value): ?string
{
    return sanitize_public_field_value($field, $value);
}

function display_contract_value(array $row, string $fallback = 'Not listed'): string
{
    $award = $row['award_amount'] ?? null;
    if ($award !== null && $award !== '' && is_numeric($award)) {
        return '$' . number_format((float) $award, 2);
    }

    $min = $row['value_min'] ?? null;
    $max = $row['value_max'] ?? null;
    if ($min !== null && $min !== '' && $max !== null && $max !== '' && is_numeric($min) && is_numeric($max)) {
        return '$' . number_format((float) $min, 2) . ' - $' . number_format((float) $max, 2);
    }

    return $fallback;
}

function display_contract_value_or_null(array $row): ?string
{
    $value = display_contract_value($row, '');
    return is_empty_display_value($value) ? null : $value;
}

function safe_external_url(?string $value): ?string
{
    $url = trim((string) $value);
    if ($url === '' || is_empty_display_value($url)) {
        return null;
    }
    if (!preg_match('~^https?://~i', $url)) {
        return null;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return $url;
}

function join_display_parts(array $parts, string $separator = ' | '): string
{
    $filtered = [];
    foreach ($parts as $part) {
        $text = trim((string) $part);
        if ($text === '' || is_empty_display_value($text)) {
            continue;
        }
        $filtered[] = $text;
    }
    return implode($separator, $filtered);
}

function int_bool($value): int
{
    return (int) (!empty($value));
}

function place_state_from_text(?string $value): ?string
{
    $value = strtoupper(trim((string) $value));
    if ($value === '') {
        return null;
    }

    if (preg_match('/,\s*([A-Z]{2})(?:\s|$)/', $value, $m)) {
        return $m[1];
    }

    $stateMap = [
        'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
        'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
        'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI', 'IDAHO' => 'ID',
        'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA', 'KANSAS' => 'KS',
        'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME', 'MARYLAND' => 'MD',
        'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN', 'MISSISSIPPI' => 'MS',
        'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
        'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM', 'NEW YORK' => 'NY',
        'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH', 'OKLAHOMA' => 'OK',
        'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC',
        'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT',
        'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV',
        'WISCONSIN' => 'WI', 'WYOMING' => 'WY', 'DISTRICT OF COLUMBIA' => 'DC',
    ];

    foreach ($stateMap as $name => $code) {
        if (strpos($value, $name) !== false) {
            return $code;
        }
    }

    return null;
}

function derive_actionability(array $row): array
{
    $state = derive_public_contract_state($row);
    $isBiddable = $state['is_open_now'];
    $isEarly = $state['is_early_signal'];
    $isAwarded = $state['is_awarded'];
    $deadlineSoon = $state['deadline_soon'];
    $hasContact = trim((string) ($row['contact_name'] ?? '')) !== ''
        || trim((string) ($row['contact_email'] ?? '')) !== ''
        || trim((string) ($row['contact_phone'] ?? '')) !== ''
        || trim((string) ($row['contracting_office'] ?? '')) !== '';

    $amount = $row['award_amount'] ?? null;
    $valueMin = $row['value_min'] ?? null;
    $valueMax = $row['value_max'] ?? null;
    $hasValue = ($amount !== null && $amount !== '' && (float) $amount > 0)
        || ($valueMin !== null && $valueMin !== '' && (float) $valueMin > 0)
        || ($valueMax !== null && $valueMax !== '' && (float) $valueMax > 0);

    return [
        'is_biddable_now' => int_bool($isBiddable),
        'is_upcoming_signal' => int_bool($isEarly),
        'is_awarded' => int_bool($isAwarded),
        'deadline_soon' => int_bool($deadlineSoon),
        'has_contact_info' => int_bool($hasContact),
        'has_value_estimate' => int_bool($hasValue),
    ];
}

function parse_value_range(array $payload): array
{
    $single = normalize_money($payload['awardAmount'] ?? $payload['Award Amount'] ?? $payload['amount'] ?? null);
    if ($single !== null) {
        return ['value_min' => $single, 'value_max' => $single, 'award_amount' => $single];
    }

    $min = normalize_money($payload['estimatedValueFrom'] ?? $payload['valueMin'] ?? $payload['baseAndAllOptionsValue'] ?? null);
    $max = normalize_money($payload['estimatedValueTo'] ?? $payload['valueMax'] ?? null);
    $range = (string) ($payload['estimatedValue'] ?? $payload['valueRange'] ?? '');
    if (($min === null || $max === null) && $range !== '') {
        if (preg_match('/\$?\s*([0-9,\.]+)\s*-\s*\$?\s*([0-9,\.]+)/', $range, $m)) {
            $min = normalize_money($m[1]);
            $max = normalize_money($m[2]);
        }
    }

    if ($min === null && $max !== null) {
        $min = $max;
    }
    if ($max === null && $min !== null) {
        $max = $min;
    }

    return ['value_min' => $min, 'value_max' => $max, 'award_amount' => $single];
}

function detect_set_aside(array $payload): array
{
    $code = trim((string) ($payload['typeOfSetAsideDescription'] ?? $payload['typeOfSetAside'] ?? $payload['setAsideCode'] ?? $payload['setAside'] ?? ''));
    $label = trim((string) ($payload['typeOfSetAsideDescription'] ?? $payload['setAside'] ?? ''));
    $text = strtolower($code . ' ' . $label);

    if ($label === '' && $code !== '') {
        $label = $code;
    }

    if ($code === '' && $text !== '') {
        if (strpos($text, '8(a)') !== false) {
            $code = '8A';
            $label = '8(a)';
        } elseif (strpos($text, 'woman') !== false) {
            $code = 'WOSB';
            $label = 'Women-Owned Small Business';
        } elseif (strpos($text, 'veteran') !== false || strpos($text, 'sdvosb') !== false) {
            $code = 'VET';
            $label = 'Veteran-Owned';
        } elseif (strpos($text, 'small') !== false) {
            $code = 'SB';
            $label = 'Small Business';
        }
    }

    return ['set_aside_code' => $code, 'set_aside_label' => $label];
}

function detect_notice_type(array $payload): string
{
    $candidate = $payload['noticeType'] ?? $payload['type'] ?? $payload['Notice Type'] ?? $payload['awardType'] ?? '';
    return trim((string) $candidate);
}

function source_display_name(PDO $pdo, int $sourceId): string
{
    static $cache = [];
    if (isset($cache[$sourceId])) {
        return $cache[$sourceId];
    }
    $stmt = $pdo->prepare('SELECT name FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $name = (string) ($stmt->fetchColumn() ?: 'Unknown Source');
    $cache[$sourceId] = $name;
    return $name;
}

function build_contract_search_filters(): array
{
    return [
        'keyword' => request_str('q'),
        'category' => request_str('category'),
        'agency' => request_int('agency', 0),
        'vendor' => request_int('vendor', 0),
        'date' => request_str('date'),
        'status' => request_str('status'),
        'mode' => request_str('mode'),
        'location' => strtoupper(request_str('location')),
        'min_value' => request_str('min_value'),
        'max_value' => request_str('max_value'),
        'deadline_window' => request_str('deadline_window'),
        'set_aside' => request_str('set_aside'),
    ];
}

function contract_search_query_parts(array $filters): array
{
    $where = [
        'cc.is_duplicate = 0',
        'NOT EXISTS (SELECT 1 FROM listing_overrides loh WHERE loh.contract_id = cc.id AND loh.is_hidden = 1)',
        'NOT EXISTS (SELECT 1 FROM grant_overrides goh WHERE goh.contract_id = cc.id AND goh.is_hidden = 1)',
    ];
    $params = [];

    if (($filters['keyword'] ?? '') !== '') {
        $where[] = '(cc.title LIKE :kw OR COALESCE(NULLIF(cc.description_clean, ""), NULLIF(cc.description_raw, ""), cc.description) LIKE :kw OR cc.contract_number LIKE :kw)';
        $params['kw'] = '%' . $filters['keyword'] . '%';
    }
    if (($filters['category'] ?? '') !== '') {
        $where[] = 'cat.slug = :category';
        $params['category'] = $filters['category'];
    }
    if (!empty($filters['agency'])) {
        $where[] = 'cc.agency_id = :agency';
        $params['agency'] = (int) $filters['agency'];
    }
    if (!empty($filters['vendor'])) {
        $where[] = 'cc.vendor_id = :vendor';
        $params['vendor'] = (int) $filters['vendor'];
    }
    if (($filters['date'] ?? '') !== '') {
        $where[] = 'cc.posted_date = :date';
        $params['date'] = $filters['date'];
    }
    if (($filters['status'] ?? '') !== '') {
        $where[] = 'cc.status = :status';
        $params['status'] = $filters['status'];
    }
    if (($filters['location'] ?? '') !== '') {
        $where[] = '(cc.place_state = :loc_state OR cc.place_of_performance LIKE :loc_text)';
        $params['loc_state'] = strtoupper($filters['location']);
        $params['loc_text'] = '%' . $filters['location'] . '%';
    }
    if (($filters['min_value'] ?? '') !== '' && is_numeric($filters['min_value'])) {
        $where[] = 'COALESCE(cc.value_max, cc.award_amount, 0) >= :min_value';
        $params['min_value'] = (float) $filters['min_value'];
    }
    if (($filters['max_value'] ?? '') !== '' && is_numeric($filters['max_value'])) {
        $where[] = 'COALESCE(cc.value_min, cc.award_amount, 0) <= :max_value';
        $params['max_value'] = (float) $filters['max_value'];
    }
    if (($filters['set_aside'] ?? '') !== '') {
        $where[] = '(cc.set_aside_code = :set_aside OR cc.set_aside_label LIKE :set_aside_like)';
        $params['set_aside'] = $filters['set_aside'];
        $params['set_aside_like'] = '%' . $filters['set_aside'] . '%';
    }

    $mode = $filters['mode'] ?? '';
    if ($mode === 'open') {
        $where[] = 'cc.is_biddable_now = 1';
        $where[] = '(cc.response_deadline IS NULL OR cc.response_deadline >= CURDATE())';
        $where[] = 'cc.is_awarded = 0';
        $where[] = 'cc.status NOT IN ("closed", "archived", "expired", "cancelled")';
    } elseif ($mode === 'signals') {
        $where[] = 'cc.is_upcoming_signal = 1';
        $where[] = 'cc.is_awarded = 0';
    } elseif ($mode === 'awarded') {
        $where[] = 'cc.is_awarded = 1';
    }

    $deadlineWindow = $filters['deadline_window'] ?? '';
    if ($deadlineWindow === '7') {
        $where[] = 'cc.response_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
    } elseif ($deadlineWindow === '30') {
        $where[] = 'cc.response_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
    } elseif ($deadlineWindow === 'past_due') {
        $where[] = 'cc.response_deadline < CURDATE()';
    }

    return [$where, $params];
}
