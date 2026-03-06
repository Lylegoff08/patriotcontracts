<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    die('Admin access required');
}

include __DIR__ . '/../templates/header.php';
?>
<h1>Admin Panel</h1>
<div class="grid-2">
  <section class="card">
    <h2>Run Ingestion</h2>
    <p>Operational order: ingest sources -> normalize -> recategorize -> stats -> alerts.</p>
    <a class="btn" href="run_ingest.php">Run Full Pipeline</a>
    <p class="muted">Manual variants:</p>
    <p class="muted"><a href="run_ingest.php?run_source=1&run_normalize=0&run_recategorize=0&run_stats=0&run_alerts=0">Ingest only</a></p>
    <p class="muted"><a href="run_ingest.php?run_source=0&run_normalize=1&run_recategorize=1&run_stats=1&run_alerts=0">Normalize + Recategorize + Stats</a></p>
  </section>
  <section class="card">
    <h2>Recalculate Categories</h2>
    <p>Applies current category rules to existing clean records.</p>
    <a class="btn" href="recategorize.php">Recategorize</a>
  </section>
  <section class="card">
    <h2>Logs</h2>
    <p>View ingest logs and source connection results.</p>
    <a class="btn" href="logs.php">View Logs</a>
  </section>
  <section class="card">
    <h2>Health</h2>
    <p>Last success/failure by source, backlog counts, and status summary.</p>
    <a class="btn" href="health.php">View Health</a>
  </section>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
