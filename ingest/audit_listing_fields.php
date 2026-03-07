<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/normalize.php';

function audit_listing_fields(PDO $pdo): array
{
    $sql = "SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN title IS NULL OR TRIM(title) = '' OR LOWER(TRIM(title)) IN ('n/a','na','null','none','unknown') THEN 1 ELSE 0 END) AS missing_title,
        SUM(CASE WHEN agency_id IS NULL THEN 1 ELSE 0 END) AS missing_agency_id,
        SUM(CASE WHEN vendor_id IS NULL THEN 1 ELSE 0 END) AS missing_vendor_id,
        SUM(CASE WHEN notice_type IS NULL OR TRIM(notice_type) = '' OR LOWER(TRIM(notice_type)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_notice_type,
        SUM(CASE WHEN contract_number IS NULL OR TRIM(contract_number) = '' OR LOWER(TRIM(contract_number)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_contract_number,
        SUM(CASE WHEN posted_date IS NULL THEN 1 ELSE 0 END) AS missing_posted_date,
        SUM(CASE WHEN response_deadline IS NULL THEN 1 ELSE 0 END) AS missing_response_deadline,
        SUM(CASE WHEN set_aside_label IS NULL OR TRIM(set_aside_label) = '' OR LOWER(TRIM(set_aside_label)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_set_aside,
        SUM(CASE WHEN naics_code IS NULL OR TRIM(naics_code) = '' OR LOWER(TRIM(naics_code)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_naics_code,
        SUM(CASE WHEN psc_code IS NULL OR TRIM(psc_code) = '' OR LOWER(TRIM(psc_code)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_psc_code,
        SUM(CASE WHEN place_of_performance IS NULL OR TRIM(place_of_performance) = '' OR LOWER(TRIM(place_of_performance)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_place_of_performance,
        SUM(CASE WHEN contact_name IS NULL OR TRIM(contact_name) = '' OR LOWER(TRIM(contact_name)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_contact_name,
        SUM(CASE WHEN contact_email IS NULL OR TRIM(contact_email) = '' OR LOWER(TRIM(contact_email)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_contact_email,
        SUM(CASE WHEN contact_phone IS NULL OR TRIM(contact_phone) = '' OR LOWER(TRIM(contact_phone)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_contact_phone,
        SUM(CASE WHEN description IS NULL OR TRIM(description) = '' OR LOWER(TRIM(description)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_description,
        SUM(CASE WHEN source_url IS NULL OR TRIM(source_url) = '' OR LOWER(TRIM(source_url)) IN ('n/a','na','null','none') THEN 1 ELSE 0 END) AS missing_source_url
    FROM contracts_clean
    WHERE is_duplicate = 0";

    $summary = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

    $bySource = $pdo->query("SELECT source_type, COUNT(*) AS total,
        SUM(CASE WHEN response_deadline IS NULL THEN 1 ELSE 0 END) AS missing_response_deadline,
        SUM(CASE WHEN place_of_performance IS NULL OR TRIM(place_of_performance) = '' THEN 1 ELSE 0 END) AS missing_place_of_performance,
        SUM(CASE WHEN contact_email IS NULL OR TRIM(contact_email) = '' THEN 1 ELSE 0 END) AS missing_contact_email
        FROM contracts_clean
        WHERE is_duplicate = 0
        GROUP BY source_type
        ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);

    return [
        'generated_at' => date('c'),
        'summary' => $summary,
        'by_source_type' => $bySource,
    ];
}

if (php_sapi_name() === 'cli') {
    $pdo = db();
    $report = audit_listing_fields($pdo);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
