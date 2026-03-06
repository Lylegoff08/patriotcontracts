<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/membership.php';
$pdo = db();
$pageTitle = 'PatriotContracts | Search';
$page = request_int('page', 1);
[$page, $perPage, $offset] = paginate($page, 50);

$filters = build_contract_search_filters();
$viewer = current_user();
$canAdvancedSearch = false;
if ($viewer && (string) ($viewer['account_status'] ?? '') === 'active') {
    $canAdvancedSearch = user_has_feature($pdo, (int) $viewer['id'], 'advanced_search');
}
if (!$canAdvancedSearch) {
    $filters['agency'] = 0;
    $filters['vendor'] = 0;
    $filters['status'] = '';
    $filters['mode'] = '';
    $filters['location'] = '';
    $filters['min_value'] = '';
    $filters['max_value'] = '';
    $filters['deadline_window'] = '';
    $filters['set_aside'] = '';
}
[$where, $params] = contract_search_query_parts($filters);

$countJoins = (($filters['category'] ?? '') !== '') ? ' LEFT JOIN contract_categories cat ON cat.id = cc.category_id' : '';
$countSql = 'SELECT COUNT(*) FROM contracts_clean cc' . $countJoins . ' WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) {
    $countStmt->bindValue(':' . $k, $v);
}
$countStmt->execute();
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    [, , $offset] = paginate($page, $perPage);
}

$sql = 'SELECT cc.id, cc.title, cc.description, cc.contract_number, cc.award_amount, cc.value_min, cc.value_max, cc.posted_date, cc.award_date,
    cc.response_deadline, cc.status, cc.naics_code, cc.psc_code, cc.contact_name, cc.contracting_office, cc.place_of_performance, cc.place_state,
    cc.set_aside_label, cc.notice_type, cc.is_biddable_now, cc.is_upcoming_signal, cc.is_awarded, cc.deadline_soon,
    a.name AS agency_name, v.name AS vendor_name, cat.name AS category_name
    FROM contracts_clean cc
    LEFT JOIN agencies a ON a.id = cc.agency_id
    LEFT JOIN vendors v ON v.id = cc.vendor_id
    LEFT JOIN contract_categories cat ON cat.id = cc.category_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY cc.posted_date DESC, cc.id DESC
    LIMIT :limit OFFSET :offset';

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$categories = $pdo->query('SELECT slug, name FROM contract_categories ORDER BY name')->fetchAll();
$agencies = $pdo->query('SELECT id, name FROM agencies ORDER BY name LIMIT 500')->fetchAll();
$vendors = $pdo->query('SELECT id, name FROM vendors ORDER BY name LIMIT 500')->fetchAll();
$setAsides = $pdo->query('SELECT DISTINCT set_aside_label FROM contracts_clean WHERE set_aside_label IS NOT NULL AND set_aside_label <> "" ORDER BY set_aside_label LIMIT 200')->fetchAll(PDO::FETCH_COLUMN);

$baseQuery = $_GET;
unset($baseQuery['page']);
$baseQs = http_build_query($baseQuery);
$prevUrl = 'search.php?' . $baseQs . ($baseQs !== '' ? '&' : '') . 'page=' . max(1, $page - 1);
$nextUrl = 'search.php?' . $baseQs . ($baseQs !== '' ? '&' : '') . 'page=' . min($totalPages, $page + 1);
$exportUrl = 'search_export.php?' . http_build_query($_GET);

include __DIR__ . '/templates/header.php';
?>
<h1>Search Contracts</h1>
<p class="muted">Filter contracts by intent (open now, early signals, awarded), value, location, deadlines, and set-aside details.</p>
<?php if (!$canAdvancedSearch): ?>
  <p class="muted">Advanced filters are a Pro feature. Basic search is currently enabled.</p>
<?php endif; ?>
<form method="get" class="card filter-grid">
  <input type="text" name="q" placeholder="Keyword" value="<?php echo e($filters['keyword']); ?>">
  <select name="category">
    <option value="">All Categories</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?php echo e($cat['slug']); ?>" <?php echo $filters['category'] === $cat['slug'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($canAdvancedSearch): ?>
    <select name="mode">
      <option value="">All Intent Types</option>
      <option value="open" <?php echo $filters['mode'] === 'open' ? 'selected' : ''; ?>>Open Now</option>
      <option value="signals" <?php echo $filters['mode'] === 'signals' ? 'selected' : ''; ?>>Early Signals</option>
      <option value="awarded" <?php echo $filters['mode'] === 'awarded' ? 'selected' : ''; ?>>Awarded / Historical</option>
    </select>
    <select name="agency"><option value="0">All Agencies</option><?php foreach ($agencies as $a): ?><option value="<?php echo (int) $a['id']; ?>" <?php echo $filters['agency'] === (int) $a['id'] ? 'selected' : ''; ?>><?php echo e($a['name']); ?></option><?php endforeach; ?></select>
    <select name="vendor"><option value="0">All Vendors</option><?php foreach ($vendors as $v): ?><option value="<?php echo (int) $v['id']; ?>" <?php echo $filters['vendor'] === (int) $v['id'] ? 'selected' : ''; ?>><?php echo e($v['name']); ?></option><?php endforeach; ?></select>
    <input type="text" name="location" placeholder="State or location" value="<?php echo e($filters['location']); ?>">
    <input type="number" step="0.01" min="0" name="min_value" placeholder="Min value" value="<?php echo e($filters['min_value']); ?>">
    <input type="number" step="0.01" min="0" name="max_value" placeholder="Max value" value="<?php echo e($filters['max_value']); ?>">
    <input type="date" name="date" value="<?php echo e($filters['date']); ?>">
    <input type="text" name="status" placeholder="status" value="<?php echo e($filters['status']); ?>">
    <select name="deadline_window">
      <option value="">Any Deadline</option>
      <option value="7" <?php echo $filters['deadline_window'] === '7' ? 'selected' : ''; ?>>Due in 7 days</option>
      <option value="30" <?php echo $filters['deadline_window'] === '30' ? 'selected' : ''; ?>>Due in 30 days</option>
      <option value="past_due" <?php echo $filters['deadline_window'] === 'past_due' ? 'selected' : ''; ?>>Past due</option>
    </select>
    <select name="set_aside">
      <option value="">All Set-Asides</option>
      <?php foreach ($setAsides as $sa): ?>
        <option value="<?php echo e((string) $sa); ?>" <?php echo $filters['set_aside'] === (string) $sa ? 'selected' : ''; ?>><?php echo e((string) $sa); ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button class="btn" type="submit">Apply Filters</button>
  <?php if ($viewer && (string) ($viewer['account_status'] ?? '') === 'active' && user_has_feature($pdo, (int) $viewer['id'], 'csv_export')): ?>
    <a class="btn btn-secondary" href="<?php echo e($exportUrl); ?>">Export CSV</a>
  <?php else: ?>
    <span class="muted">Upgrade to export CSV</span>
  <?php endif; ?>
</form>

<section class="card">
  <p class="muted">Total results: <?php echo number_format($totalRows); ?> | Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></p>
  <?php if (!$rows): ?>
    <p>No contracts found.</p>
  <?php else: ?>
    <?php foreach ($rows as $row): ?>
      <article>
        <h3><a href="contract.php?id=<?php echo (int) $row['id']; ?>"><?php echo e($row['title']); ?></a></h3>
        <p class="muted listing-meta"><?php echo e(display_text($row['agency_name'] ?? null)); ?> | <?php echo e(display_text($row['vendor_name'] ?? null)); ?> | <?php echo e(display_text($row['category_name'] ?? null)); ?></p>
        <p class="muted listing-meta">#<?php echo e(display_text($row['contract_number'] ?? null)); ?> | <?php echo e(display_contract_value($row)); ?> | Posted: <?php echo e(display_date($row['posted_date'] ?? null)); ?> | Award: <?php echo e(display_date($row['award_date'] ?? null)); ?> | Due: <?php echo e(display_date($row['response_deadline'] ?? null)); ?> | <?php echo e(display_text($row['status'] ?? null)); ?></p>
        <p class="muted listing-meta">
          <?php if ((int) $row['is_biddable_now'] === 1): ?>Open Now | <?php endif; ?>
          <?php if ((int) $row['is_upcoming_signal'] === 1): ?>Early Signal | <?php endif; ?>
          <?php if ((int) $row['is_awarded'] === 1): ?>Awarded | <?php endif; ?>
          <?php if ((int) $row['deadline_soon'] === 1): ?>Deadline Soon | <?php endif; ?>
          <?php echo e((string) ($row['set_aside_label'] ?? '')); ?>
        </p>
        <p class="listing-desc"><?php echo e(contract_listing_description($row)); ?></p>
      </article>
      <hr>
    <?php endforeach; ?>
    <div class="pager">
      <?php if ($page > 1): ?><a class="btn btn-secondary" href="<?php echo e($prevUrl); ?>">Previous</a><?php endif; ?>
      <?php if ($page < $totalPages): ?><a class="btn btn-secondary" href="<?php echo e($nextUrl); ?>">Next</a><?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
