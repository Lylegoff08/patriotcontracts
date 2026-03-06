<?php

function category_slug_to_id(PDO $pdo, string $slug): ?int
{
    static $cache = [];
    if (isset($cache[$slug])) {
        return $cache[$slug];
    }

    $stmt = $pdo->prepare('SELECT id FROM contract_categories WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $id = $stmt->fetchColumn();
    $cache[$slug] = $id ? (int) $id : null;
    return $cache[$slug];
}

function category_slug_from_id(PDO $pdo, int $id): ?string
{
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }

    $stmt = $pdo->prepare('SELECT slug FROM contract_categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $slug = $stmt->fetchColumn();
    $cache[$id] = $slug ? (string) $slug : null;
    return $cache[$id];
}

function category_from_map(PDO $pdo, string $naics, string $psc): ?array
{
    $naics = preg_replace('/[^0-9]/', '', $naics);
    $psc = strtoupper(trim($psc));

    if ($naics !== '') {
        try {
            $stmt = $pdo->prepare('SELECT category_id, naics_prefix FROM naics_category_map WHERE :naics LIKE CONCAT(naics_prefix, "%") ORDER BY CHAR_LENGTH(naics_prefix) DESC LIMIT 1');
            $stmt->execute(['naics' => $naics]);
            $row = $stmt->fetch();
            if ($row) {
                $slug = category_slug_from_id($pdo, (int) $row['category_id']);
                if ($slug) {
                    return ['slug' => $slug, 'rule' => 'naics_map:' . $row['naics_prefix']];
                }
            }
        } catch (Throwable $e) {
        }
    }

    if ($psc !== '') {
        try {
            $stmt = $pdo->prepare('SELECT category_id, psc_prefix FROM psc_category_map WHERE :psc LIKE CONCAT(psc_prefix, "%") ORDER BY CHAR_LENGTH(psc_prefix) DESC LIMIT 1');
            $stmt->execute(['psc' => $psc]);
            $row = $stmt->fetch();
            if ($row) {
                $slug = category_slug_from_id($pdo, (int) $row['category_id']);
                if ($slug) {
                    return ['slug' => $slug, 'rule' => 'psc_map:' . $row['psc_prefix']];
                }
            }
        } catch (Throwable $e) {
        }
    }

    return null;
}

function categorize_contract(array $row, ?PDO $pdo = null): array
{
    $agency = strtoupper($row['agency_name'] ?? '');
    $title = strtoupper($row['title'] ?? '');
    $desc = strtoupper($row['description'] ?? '');
    $naics = strtoupper((string) ($row['naics_code'] ?? ''));
    $psc = strtoupper((string) ($row['psc_code'] ?? ''));

    $haystack = $agency . ' ' . $title . ' ' . $desc;

    if ($pdo) {
        $mapped = category_from_map($pdo, $naics, $psc);
        if ($mapped) {
            return $mapped;
        }
    }

    if (preg_match('/DEPARTMENT OF DEFENSE|\bDOD\b|\bARMY\b|\bNAVY\b|\bAIR FORCE\b/', $haystack)) {
        return ['slug' => 'defense', 'rule' => 'agency_defense'];
    }

    if (preg_match('/AEROSPACE|AVIATION|AIRCRAFT|AVIONICS|SPACE SYSTEM|SATELLITE|FLIGHT/', $haystack)
        || preg_match('/^372|^3364/', $naics)
    ) {
        return ['slug' => 'aerospace-and-aviation', 'rule' => 'aerospace_keywords'];
    }

    if (preg_match('/CONSTRUCTION|RENOVATION|FACILITY|INFRASTRUCTURE|ENGINEERING/', $haystack) || str_starts_with($naics, '23')) {
        return ['slug' => 'construction', 'rule' => 'construction_keywords'];
    }

    if (preg_match('/MAINTENANCE|JANITORIAL|CUSTODIAL|GROUNDS|BASE OPERATIONS|HVAC/', $haystack)
        || preg_match('/^5612|^5617|^2382/', $naics)
    ) {
        return ['slug' => 'facilities-maintenance', 'rule' => 'facilities_keywords'];
    }

    if (preg_match('/MANUFACTUR|PRODUCTION|FABRICAT|ASSEMBL|COMPONENT/', $haystack)
        || preg_match('/^31|^32|^33/', $naics)
    ) {
        return ['slug' => 'manufacturing', 'rule' => 'manufacturing_keywords'];
    }

    if (preg_match('/SOFTWARE|CYBER|IT SUPPORT|CLOUD|NETWORK|DATA CENTER|INFORMATION TECHNOLOGY/', $haystack)
        || str_starts_with($naics, '5415')
        || preg_match('/^D3|^7/', $psc)
    ) {
        return ['slug' => 'it-cybersecurity', 'rule' => 'it_keywords'];
    }

    if (preg_match('/TELECOM|TELECOMMUNICATION|BROADBAND|WIRELESS|FIBER|VOICE SERVICE/', $haystack)
        || str_starts_with($naics, '517')
    ) {
        return ['slug' => 'telecom', 'rule' => 'telecom_keywords'];
    }

    if (preg_match('/LOGISTICS|TRANSPORT|SUPPLY CHAIN|WAREHOUSE|FREIGHT/', $haystack) || str_starts_with($naics, '48')) {
        return ['slug' => 'logistics', 'rule' => 'logistics_keywords'];
    }

    if (preg_match('/ENERGY|POWER GRID|UTILITY|SOLAR|WIND|ENVIRONMENTAL|REMEDIATION|SUSTAINABILITY/', $haystack)
        || preg_match('/^22|^562/', $naics)
    ) {
        return ['slug' => 'energy-and-environment', 'rule' => 'energy_environment_keywords'];
    }

    if (preg_match('/HEALTH|MEDICAL|HOSPITAL|PHARM|CLINICAL/', $haystack)
        || str_starts_with($naics, '62')
        || preg_match('/^Q/', $psc)
    ) {
        return ['slug' => 'healthcare', 'rule' => 'healthcare_keywords'];
    }

    if (preg_match('/TRAINING|CURRICULUM|INSTRUCTION|EDUCATION|LEARNING MANAGEMENT/', $haystack)
        || preg_match('/^611/', $naics)
    ) {
        return ['slug' => 'education-and-training', 'rule' => 'education_keywords'];
    }

    if (preg_match('/SECURITY GUARD|PROTECTIVE SERVICE|LAW ENFORCEMENT|INVESTIGATIVE|PHYSICAL SECURITY/', $haystack)
        || preg_match('/^5616/', $naics)
    ) {
        return ['slug' => 'security-and-law-enforcement', 'rule' => 'security_keywords'];
    }

    if (preg_match('/RESEARCH|R&D|DEVELOPMENT|LABORATORY|TEST AND EVALUATION|SCIENTIFIC STUDY/', $haystack)
        || preg_match('/^5417/', $naics)
    ) {
        return ['slug' => 'research-and-development', 'rule' => 'rd_keywords'];
    }

    if (preg_match('/GRANT|ASSISTANCE|COOPERATIVE AGREEMENT/', $haystack)) {
        return ['slug' => 'grants-assistance', 'rule' => 'grants_keywords'];
    }

    if (preg_match('/SCIENTIFIC|TECHNICAL|ANALYTICAL|ENGINEER SUPPORT|MODELING|SIMULATION/', $haystack)
        || preg_match('/^5413|^5416|^5419/', $naics)
    ) {
        return ['slug' => 'professional-scientific-technical', 'rule' => 'pst_keywords'];
    }

    if (preg_match('/CONSULTING|ADVISORY|PROGRAM MANAGEMENT|ADMINISTRATIVE|LEGAL|AUDIT/', $haystack)
        || str_starts_with($naics, '5416')
    ) {
        return ['slug' => 'professional-services', 'rule' => 'professional_keywords'];
    }

    if (preg_match('/SUPPLIES|EQUIPMENT|HARDWARE|TOOLS|MATERIALS|PARTS/', $haystack)
        || str_starts_with($naics, '42')
    ) {
        return ['slug' => 'supplies-equipment', 'rule' => 'fallback_supplies'];
    }

    if (preg_match('/INSTALLATION|ON-SITE SUPPORT|FIELD SERVICE|TECHNICIAN/', $haystack)
        || preg_match('/^S|^N/', $psc)
    ) {
        return ['slug' => 'field-services', 'rule' => 'fallback_field_services'];
    }

    if (preg_match('/REPAIR|OVERHAUL|SUSTAINMENT|SERVICE LIFE|DEPOT/', $haystack)
        || preg_match('/^J|^K/', $psc)
    ) {
        return ['slug' => 'repair-maintenance', 'rule' => 'fallback_repair'];
    }

    if (preg_match('/CLERICAL|ADMIN SUPPORT|RECORDS MANAGEMENT|DATA ENTRY|BACK OFFICE/', $haystack)
        || str_starts_with($naics, '5611')
    ) {
        return ['slug' => 'administrative-support', 'rule' => 'fallback_admin_support'];
    }

    return ['slug' => 'uncategorized', 'rule' => 'default'];
}
