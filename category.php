<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$slug = request_str('slug', 'uncategorized');

$stmt = $pdo->prepare('SELECT id, name, slug FROM contract_categories WHERE slug = :slug LIMIT 1');
$stmt->execute(['slug' => $slug]);
$category = $stmt->fetch();

$contracts = [];
if ($category) {
    $q = $pdo->prepare('SELECT cc.id, COALESCE(NULLIF(lo.display_title, ""), NULLIF(go.display_title, ""), cc.title) AS title, cc.posted_date
        FROM contracts_clean cc
        LEFT JOIN listing_overrides lo ON lo.contract_id = cc.id
        LEFT JOIN grant_overrides go ON go.contract_id = cc.id
        WHERE COALESCE(lo.category_override, go.category_override, cc.category_id) = :category_id
          AND cc.is_duplicate = 0
          AND COALESCE(lo.is_hidden, go.is_hidden, 0) = 0
        ORDER BY cc.posted_date DESC
        LIMIT 100');
    $q->execute(['category_id' => (int) $category['id']]);
    $contracts = $q->fetchAll();
}

include __DIR__ . '/templates/header.php';
?>
<h1>Category: <?php echo e((string) ($category['name'] ?? 'Unknown')); ?></h1>
<section class="card">
<?php foreach ($contracts as $c): ?>
  <p><a href="contract.php?id=<?php echo (int) $c['id']; ?>"><?php echo e($c['title']); ?></a> <span class="muted"><?php echo e((string) $c['posted_date']); ?></span></p>
<?php endforeach; ?>
</section>
<?php include __DIR__ . '/templates/footer.php'; ?>
