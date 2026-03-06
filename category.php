<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$slug = request_str('slug', 'uncategorized');

$stmt = $pdo->prepare('SELECT id, name, slug FROM contract_categories WHERE slug = :slug LIMIT 1');
$stmt->execute(['slug' => $slug]);
$category = $stmt->fetch();

$contracts = [];
if ($category) {
    $q = $pdo->prepare('SELECT id, title, posted_date FROM contracts_clean WHERE category_id = :category_id AND is_duplicate = 0 ORDER BY posted_date DESC LIMIT 100');
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
