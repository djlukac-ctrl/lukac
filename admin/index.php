<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();

$new = (int) $pdo->query("SELECT COUNT(*) FROM quotes WHERE status='new'")->fetchColumn();
$accepted = (int) $pdo->query("SELECT COUNT(*) FROM quotes WHERE status='accepted'")->fetchColumn();
$pending = (int) $pdo->query("SELECT COUNT(*) FROM quotes WHERE status IN ('new','read','sent')")->fetchColumn();
$nextDate = $pdo->query("SELECT event_date FROM quotes WHERE status='accepted' AND event_date IS NOT NULL AND event_date >= date('now') ORDER BY event_date ASC LIMIT 1")->fetchColumn();
$recent = $pdo->query('SELECT * FROM quotes ORDER BY created_at DESC LIMIT 8')->fetchAll();

$todayVisitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date = date('now','localtime')")->fetchColumn();
$yesterdayVisitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date = date('now','localtime','-1 day')")->fetchColumn();
$last7Visitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= date('now','localtime','-6 day')")->fetchColumn();
$last30Visitors = (int) $pdo->query("SELECT COUNT(*) FROM site_visitors WHERE visit_date >= date('now','localtime','-29 day')")->fetchColumn();

admin_header('Tableau de bord', 'dashboard');
?>
<style>
.dashboard-intro{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin:0 0 26px;padding:26px 28px;border:1px solid rgba(24,23,22,.09);border-radius:20px;background:#fff;box-shadow:0 12px 34px rgba(50,38,28,.04)}
.dashboard-intro__copy{max-width:670px}.dashboard-intro__eyebrow{display:block;margin-bottom:8px;color:#c93431;font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.dashboard-intro h2{margin:0 0 8px;font-size:27px;letter-spacing:-.035em}.dashboard-intro p{margin:0;color:#77706a;font-size:13px;line-height:1.65}
.dashboard-intro__actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}
.dashboard-layout{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,.65fr);gap:22px;align-items:start}
.dashboard-panel{background:#fff;border:1px solid rgba(24,23,22,.09);border-radius:20px;box-shadow:0 12px 34px rgba(50,38,28,.04);overflow:hidden}
.dashboard-panel__head{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:22px 22px 15px}.dashboard-panel__head h2{margin:0;font-size:21px}.dashboard-panel__head p{margin:4px 0 0;color:#85807a;font-size:11px}
.dashboard-side{display:grid;gap:18px}
.dashboard-watch{padding:20px 22px}
.dashboard-watch h2,.dashboard-visitors h2{margin:0 0 16px;font-size:18px}
.dashboard-watch__row{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:13px 0;border-bottom:1px solid rgba(24,23,22,.07)}
.dashboard-watch__row:last-child{border-bottom:0;padding-bottom:0}.dashboard-watch__row:first-of-type{padding-top:0}
.dashboard-watch__row span{color:#706963;font-size:12px}.dashboard-watch__row strong{font-size:16px;text-align:right}
.dashboard-watch__alert{color:#c93431!important}
.dashboard-visitors{padding:20px 22px}.dashboard-visitors__grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.dashboard-visitor{padding:14px;border:1px solid rgba(24,23,22,.08);border-radius:14px;background:#faf8f5}.dashboard-visitor span{display:block;margin-bottom:5px;color:#7d766f;font-size:10px}.dashboard-visitor strong{font-size:24px;letter-spacing:-.04em}
.dashboard-shortcuts{padding:20px 22px}.dashboard-shortcuts h2{margin:0 0 14px;font-size:18px}.dashboard-shortcuts__list{display:grid;gap:8px}.dashboard-shortcuts__list a{display:flex;justify-content:space-between;align-items:center;padding:11px 13px;border:1px solid rgba(24,23,22,.08);border-radius:12px;background:#faf8f5;color:#26221f;text-decoration:none;font-size:12px;font-weight:700;transition:.18s}.dashboard-shortcuts__list a:hover{background:#fff;border-color:rgba(201,52,49,.22);transform:translateX(2px)}
.dashboard-panel .admin-table-wrap{border:0;border-radius:0;box-shadow:none;margin:0}.dashboard-panel table{margin:0}
@media(max-width:1050px){.dashboard-layout{grid-template-columns:1fr}.dashboard-side{grid-template-columns:1fr 1fr}.dashboard-shortcuts{grid-column:1/-1}}
@media(max-width:720px){.dashboard-intro{align-items:flex-start;flex-direction:column;padding:22px}.dashboard-intro__actions{justify-content:flex-start}.dashboard-side{grid-template-columns:1fr}.dashboard-shortcuts{grid-column:auto}.dashboard-visitors__grid{grid-template-columns:1fr 1fr}}
</style>

<section class="dashboard-intro">
  <div class="dashboard-intro__copy">
    <span class="dashboard-intro__eyebrow">Vue d’ensemble</span>
    <h2>Les informations utiles, sans surcharge.</h2>
    <p>Retrouve ici les demandes récentes, les éléments à traiter et un aperçu rapide de la fréquentation du site.</p>
  </div>
  <div class="dashboard-intro__actions">
    <a class="btn btn--primary" href="devis.php">Voir les demandes</a>
    <a class="btn" href="disponibilites.php">Gérer les disponibilités</a>
  </div>
</section>

<div class="dashboard-layout">
  <section class="dashboard-panel">
    <div class="dashboard-panel__head">
      <div>
        <h2>Dernières demandes de devis</h2>
        <p>Les 8 demandes les plus récentes.</p>
      </div>
      <a class="admin-link" href="devis.php">Tout afficher →</a>
    </div>
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

  <aside class="dashboard-side">
    <section class="dashboard-panel dashboard-watch">
      <h2>À surveiller</h2>
      <div class="dashboard-watch__row">
        <span>Nouvelles demandes</span>
        <strong class="<?= $new > 0 ? 'dashboard-watch__alert' : '' ?>"><?= $new ?></strong>
      </div>
      <div class="dashboard-watch__row">
        <span>Demandes en cours</span>
        <strong><?= $pending ?></strong>
      </div>
      <div class="dashboard-watch__row">
        <span>Devis acceptés</span>
        <strong><?= $accepted ?></strong>
      </div>
      <div class="dashboard-watch__row">
        <span>Prochaine prestation acceptée</span>
        <strong><?= $nextDate ? e(date('d/m/Y', strtotime((string)$nextDate))) : '—' ?></strong>
      </div>
    </section>

    <section class="dashboard-panel dashboard-visitors">
      <h2>Visiteurs du site</h2>
      <div class="dashboard-visitors__grid">
        <div class="dashboard-visitor"><span>Aujourd’hui</span><strong><?= $todayVisitors ?></strong></div>
        <div class="dashboard-visitor"><span>Hier</span><strong><?= $yesterdayVisitors ?></strong></div>
        <div class="dashboard-visitor"><span>7 jours</span><strong><?= $last7Visitors ?></strong></div>
        <div class="dashboard-visitor"><span>30 jours</span><strong><?= $last30Visitors ?></strong></div>
      </div>
    </section>

    <section class="dashboard-panel dashboard-shortcuts">
      <h2>Accès rapides</h2>
      <div class="dashboard-shortcuts__list">
        <a href="disponibilites.php"><span>Disponibilités</span><span>→</span></a>
        <a href="avis.php"><span>Avis clients</span><span>→</span></a>
        <a href="options.php"><span>Options</span><span>→</span></a>
        <a href="bon-plan.php"><span>Bon plan</span><span>→</span></a>
      </div>
    </section>
  </aside>
</div>

<?php admin_footer(); ?>
