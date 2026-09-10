<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();
$total = (int) $pdo->query('SELECT COUNT(*) FROM quotes')->fetchColumn();
$new = (int) $pdo->query("SELECT COUNT(*) FROM quotes WHERE status='new'")->fetchColumn();
$booked = (int) $pdo->query("SELECT COUNT(*) FROM quotes WHERE status='booked'")->fetchColumn();
$nextDate = $pdo->query("SELECT event_date FROM quotes WHERE status IN ('new','read','contacted','booked') AND event_date IS NOT NULL AND event_date >= date('now') ORDER BY event_date ASC LIMIT 1")->fetchColumn();
$recent = $pdo->query('SELECT * FROM quotes ORDER BY created_at DESC LIMIT 8')->fetchAll();

$todayVisitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date = date('now','localtime')")->fetchColumn();
$yesterdayVisitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date = date('now','localtime','-1 day')")->fetchColumn();
$last7Visitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= date('now','localtime','-6 day')")->fetchColumn();
$last30Visitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= date('now','localtime','-29 day')")->fetchColumn();

admin_header('Tableau de bord', 'dashboard');
?>
<div class="admin-grid">
  <section class="admin-card"><div class="admin-card__label">Demandes reçues</div><div class="admin-card__value"><?= $total ?></div></section>
  <section class="admin-card"><div class="admin-card__label">Nouvelles demandes</div><div class="admin-card__value"><?= $new ?></div></section>
  <section class="admin-card"><div class="admin-card__label">Réservations confirmées</div><div class="admin-card__value"><?= $booked ?></div></section>
  <section class="admin-card"><div class="admin-card__label">Prochaine date suivie</div><div class="admin-card__value small"><?= $nextDate ? e(date('d/m/Y', strtotime((string) $nextDate))) : '—' ?></div></section>
</div>

<section class="admin-section">
  <div class="admin-section__head"><h2>Visiteurs du site</h2><span style="color:#777;font-size:13px">Visiteurs uniques estimés</span></div>
  <div class="admin-grid">
    <section class="admin-card"><div class="admin-card__label">Aujourd’hui</div><div class="admin-card__value"><?= $todayVisitors ?></div></section>
    <section class="admin-card"><div class="admin-card__label">Hier</div><div class="admin-card__value"><?= $yesterdayVisitors ?></div></section>
    <section class="admin-card"><div class="admin-card__label">7 derniers jours</div><div class="admin-card__value"><?= $last7Visitors ?></div></section>
    <section class="admin-card"><div class="admin-card__label">30 derniers jours</div><div class="admin-card__value"><?= $last30Visitors ?></div></section>
  </div>
</section>

<section class="admin-section">
  <div class="admin-section__head"><h2>Dernières demandes de devis</h2><a class="admin-link" href="devis.php">Tout afficher →</a></div>
  <div class="admin-table-wrap">
    <table>
      <thead><tr><th>Client</th><th>Événement</th><th>Date</th><th>Reçue le</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php if (!$recent): ?>
        <tr><td colspan="6">Aucune demande pour le moment.</td></tr>
      <?php else: foreach ($recent as $quote): ?>
        <tr>
          <td><strong><?= e($quote['name']) ?></strong><br><span style="color:#777"><?= e($quote['email']) ?></span></td>
          <td><?= e($quote['event_type']) ?></td>
          <td><?= $quote['event_date'] ? e(date('d/m/Y', strtotime($quote['event_date']))) : 'À définir' ?></td>
          <td><?= e(date('d/m/Y H:i', strtotime($quote['created_at']))) ?></td>
          <td><span class="status status--<?= e($quote['status']) ?>"><?= e(quote_status_label($quote['status'])) ?></span></td>
          <td><a class="admin-link" href="devis.php?id=<?= (int) $quote['id'] ?>">Ouvrir →</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_footer(); ?>
