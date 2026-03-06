<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ingest_common.php';

function calculate_stats(PDO $pdo): array
{
    $startedAt = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    $insert = $pdo->prepare('INSERT INTO stats_daily (stat_date, metric_key, metric_value) VALUES (:stat_date, :metric_key, :metric_value)
        ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value)');

    $metricCount = 0;

    $totals = $pdo->query('SELECT COUNT(*) AS contracts, COALESCE(SUM(award_amount),0) AS amount FROM contracts_clean WHERE is_duplicate = 0')->fetch();
    $topLevel = [
        'contracts_total' => (float) ($totals['contracts'] ?? 0),
        'amount_total' => (float) ($totals['amount'] ?? 0),
    ];

    $pipeline = $pdo->query('SELECT
        SUM(is_biddable_now = 1) AS open_now,
        SUM(is_upcoming_signal = 1) AS early_signals,
        SUM(is_awarded = 1) AS recent_awards,
        SUM(deadline_soon = 1) AS due_soon
        FROM contracts_clean WHERE is_duplicate = 0')->fetch();
    $topLevel['pipeline_open_now'] = (float) ($pipeline['open_now'] ?? 0);
    $topLevel['pipeline_early_signals'] = (float) ($pipeline['early_signals'] ?? 0);
    $topLevel['pipeline_recent_awards'] = (float) ($pipeline['recent_awards'] ?? 0);
    $topLevel['pipeline_due_soon'] = (float) ($pipeline['due_soon'] ?? 0);

    $timeWindow = $pdo->query('SELECT
        SUM(posted_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS last_7_days,
        SUM(posted_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS last_30_days
        FROM contracts_clean WHERE is_duplicate = 0')->fetch();
    $topLevel['window_last_7_days'] = (float) ($timeWindow['last_7_days'] ?? 0);
    $topLevel['window_last_30_days'] = (float) ($timeWindow['last_30_days'] ?? 0);

    $valueBuckets = $pdo->query('SELECT
        SUM(COALESCE(value_max, award_amount, 0) < 100000) AS val_under_100k,
        SUM(COALESCE(value_max, award_amount, 0) >= 100000 AND COALESCE(value_max, award_amount, 0) < 1000000) AS val_100k_1m,
        SUM(COALESCE(value_max, award_amount, 0) >= 1000000 AND COALESCE(value_max, award_amount, 0) < 10000000) AS val_1m_10m,
        SUM(COALESCE(value_max, award_amount, 0) >= 10000000) AS val_over_10m
        FROM contracts_clean WHERE is_duplicate = 0')->fetch();
    $topLevel['value_under_100k'] = (float) ($valueBuckets['val_under_100k'] ?? 0);
    $topLevel['value_100k_to_1m'] = (float) ($valueBuckets['val_100k_1m'] ?? 0);
    $topLevel['value_1m_to_10m'] = (float) ($valueBuckets['val_1m_10m'] ?? 0);
    $topLevel['value_over_10m'] = (float) ($valueBuckets['val_over_10m'] ?? 0);

    $setAsideRows = $pdo->query('SELECT
        SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%small%") AS sa_small,
        SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%8(a)%") AS sa_8a,
        SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%veteran%") AS sa_veteran,
        SUM(LOWER(CONCAT(COALESCE(set_aside_code,"")," ",COALESCE(set_aside_label,""))) LIKE "%women%") AS sa_women
        FROM contracts_clean WHERE is_duplicate = 0')->fetch();
    $topLevel['set_aside_small_business'] = (float) ($setAsideRows['sa_small'] ?? 0);
    $topLevel['set_aside_8a'] = (float) ($setAsideRows['sa_8a'] ?? 0);
    $topLevel['set_aside_veteran'] = (float) ($setAsideRows['sa_veteran'] ?? 0);
    $topLevel['set_aside_women'] = (float) ($setAsideRows['sa_women'] ?? 0);

    foreach ($topLevel as $key => $value) {
        $insert->execute([
            'stat_date' => $today,
            'metric_key' => $key,
            'metric_value' => $value,
        ]);
        $metricCount++;
    }

    $categoryRows = $pdo->query('SELECT c.slug, COUNT(*) AS total, COALESCE(SUM(cc.award_amount),0) AS amount, COALESCE(AVG(NULLIF(cc.award_amount,0)),0) AS avg_amount
        FROM contracts_clean cc JOIN contract_categories c ON c.id = cc.category_id
        WHERE cc.is_duplicate = 0 GROUP BY c.slug')->fetchAll();
    foreach ($categoryRows as $row) {
        $insert->execute(['stat_date' => $today, 'metric_key' => 'category_count_' . $row['slug'], 'metric_value' => (float) $row['total']]);
        $insert->execute(['stat_date' => $today, 'metric_key' => 'category_amount_' . $row['slug'], 'metric_value' => (float) $row['amount']]);
        $insert->execute(['stat_date' => $today, 'metric_key' => 'category_avg_' . $row['slug'], 'metric_value' => (float) $row['avg_amount']]);
        $metricCount += 3;
    }

    $agencyRows = $pdo->query('SELECT a.id, COUNT(*) AS total, COALESCE(SUM(cc.award_amount),0) AS amount,
        SUM(cc.posted_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS recent_30
        FROM contracts_clean cc JOIN agencies a ON a.id = cc.agency_id
        WHERE cc.is_duplicate = 0
        GROUP BY a.id')->fetchAll();
    foreach ($agencyRows as $row) {
        $keyBase = 'agency_' . (int) $row['id'];
        $insert->execute(['stat_date' => $today, 'metric_key' => $keyBase . '_count', 'metric_value' => (float) $row['total']]);
        $insert->execute(['stat_date' => $today, 'metric_key' => $keyBase . '_amount', 'metric_value' => (float) $row['amount']]);
        $insert->execute(['stat_date' => $today, 'metric_key' => $keyBase . '_recent30', 'metric_value' => (float) $row['recent_30']]);
        $metricCount += 3;
    }

    $locationRows = $pdo->query('SELECT place_state, COUNT(*) AS total, COALESCE(SUM(award_amount),0) AS amount
        FROM contracts_clean
        WHERE is_duplicate = 0 AND place_state IS NOT NULL AND place_state <> ""
        GROUP BY place_state')->fetchAll();
    foreach ($locationRows as $row) {
        $key = strtolower((string) $row['place_state']);
        $insert->execute(['stat_date' => $today, 'metric_key' => 'state_count_' . $key, 'metric_value' => (float) $row['total']]);
        $insert->execute(['stat_date' => $today, 'metric_key' => 'state_amount_' . $key, 'metric_value' => (float) $row['amount']]);
        $metricCount += 2;
    }

    log_ingest($pdo, null, 'calculate_stats.php', 'success', 'Daily stats recalculated', 1, $metricCount, $startedAt);
    return ['status' => 'success', 'metrics' => $metricCount];
}

if (php_sapi_name() === 'cli' || basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $result = calculate_stats(db());
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}
