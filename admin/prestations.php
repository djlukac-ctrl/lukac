<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();

$upcoming = $pdo->query("
    SELECT
        p.id AS prestation_id,
        q.id AS quote_id,
        q.name,
        q.email,
        q.phone,
        q.event_type,
        q.event_date,
        q.venue,
        q.start_time,
        q.end_time,
        q.services,
        q.selections,
        (
            SELECT cq.amount_cents
            FROM commercial_quotes cq
            WHERE cq.source_quote_id = q.id
            ORDER BY cq.id DESC
            LIMIT 1
        ) AS amount_cents,
        (
            SELECT cq.quote_number
            FROM commercial_quotes cq
            WHERE cq.source_quote_id = q.id
            ORDER BY cq.id DESC
            LIMIT 1
        ) AS quote_number
    FROM prestations p
    INNER JOIN quotes q ON q.id = p.source_quote_id
    WHERE q.status = 'accepted'
      AND (q.event_date IS NULL OR q.event_date = '' OR q.event_date >= date('now','localtime'))
    ORDER BY
      CASE WHEN q.event_date IS NULL OR q.event_date = '' THEN 1 ELSE 0 END,
      q.event_date ASC,
      q.name ASC
")->fetchAll();

$datedUpcoming = array_values(array_filter($upcoming, static fn(array $row): bool => !empty($row['event_date'])));
$nextPrestation = $datedUpcoming[0] ?? null;
$totalUpcoming = count($upcoming);
$knownRevenueCents = 0;

foreach ($upcoming as $row) {
    if ($row['amount_cents'] !== null) {
        $knownRevenueCents += (int) $row['amount_cents'];
    }
}

admin_header('Prestations', 'prestations');
?>
<style>
.prestations-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
.prestations-stat{padding:18px 20px;border:1px solid rgba(24,23,22,.09);border-radius:18px;background:#fff;box-shadow:0 10px 28px rgba(50,38,28,.035)}
.prestations-stat span{display:block;margin-bottom:6px;color:#7c746e;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
.prestations-stat strong{display:block;color:#1e1b19;font-size:25px;line-height:1.1;letter-spacing:-.04em}
.prestations-stat small{display:block;margin-top:5px;color:#8c847d;font-size:10px;line-height:1.4}
.prestations-panel{border:1px solid rgba(24,23,22,.09);border-radius:20px;background:#fff;box-shadow:0 12px 34px rgba(50,38,28,.04);overflow:hidden}
.prestations-panel__head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:21px 22px 16px;border-bottom:1px solid rgba(24,23,22,.07)}
.prestations-panel__head h2{margin:0;font-size:21px}.prestations-panel__head p{margin:4px 0 0;color:#85807a;font-size:11px}
.prestations-list{display:grid;gap:0}
.prestation-row{display:grid;grid-template-columns:100px minmax(180px,1.1fr) minmax(190px,1fr) minmax(140px,.8fr) auto;gap:18px;align-items:center;padding:17px 22px;border-bottom:1px solid rgba(24,23,22,.07)}
.prestation-row:last-child{border-bottom:0}
.prestation-date{display:flex;flex-direction:column;gap:2px}.prestation-date strong{font-size:16px}.prestation-date span{color:#8a8179;font-size:10px}
.prestation-client strong{display:block;margin-bottom:3px;font-size:14px}.prestation-client span{display:block;color:#857d76;font-size:10px}
.prestation-event strong{display:block;margin-bottom:3px;font-size:12px}.prestation-event span{display:block;color:#857d76;font-size:10px;line-height:1.4}
.prestation-money strong{display:block;font-size:14px}.prestation-money span{display:block;margin-top:3px;color:#8a8179;font-size:9px}
.prestation-actions{display:flex;align-items:center;gap:10px;justify-content:flex-end;white-space:nowrap}
.prestation-empty{padding:34px 22px;text-align:center;color:#7d766f;font-size:12px}
@media(max-width:1050px){.prestation-row{grid-template-columns:90px 1fr 1fr auto}.prestation-money{grid-column:2/4}.prestation-actions{grid-column:4;grid-row:1/3}}
@media(max-width:720px){.prestations-summary{grid-template-columns:1fr}.prestations-panel__head{align-items:flex-start;flex-direction:column}.prestation-row{grid-template-columns:1fr;gap:9px}.prestation-money,.prestation-actions{grid-column:auto;grid-row:auto}.prestation-actions{justify-content:flex-start}}
</style>

<section class="prestations-summary">
  <div class="prestations-stat">
    <span>Prestations à venir</span>
    <strong><?= $totalUpcoming ?></strong>
    <small>Demandes acceptées encore à réaliser.</small>
  </div>
  <div class="prestations-stat">
    <span>Prochaine prestation</span>
    <strong><?= $nextPrestation ? e(date('d/m/Y', strtotime($nextPrestation['event_date']))) : '—' ?></strong>
    <small><?= $nextPrestation ? e($nextPrestation['name'] . ' · ' . $nextPrestation['event_type']) : 'Aucune prestation datée.' ?></small>
  </div>
  <div class="prestations-stat">
    <span>Montant connu à venir</span>
    <strong><?= number_format($knownRevenueCents / 100, 0, ',', ' ') ?> €</strong>
    <small>Basé uniquement sur les devis Luka C déjà enregistrés.</small>
  </div>
</section>

<section class="prestations-panel">
  <div class="prestations-panel__head">
    <div>
      <h2>Prestations à venir</h2>
      <p>Créées automatiquement à partir des demandes passées en « Accepté ».</p>
    </div>
    <a class="admin-link" href="devis.php?status=accepted">Voir les demandes acceptées →</a>
  </div>

  <?php if (!$upcoming): ?>
    <div class="prestation-empty">Aucune prestation à venir pour le moment.</div>
  <?php else: ?>
    <div class="prestations-list">
      <?php foreach ($upcoming as $row): ?>
        <?php
          $services = array_values(array_filter(array_map('trim', explode('|', (string)($row['services'] ?? '')))));
          $selections = array_values(array_filter(array_map('trim', explode('|', (string)($row['selections'] ?? '')))));
          $detailParts = array_merge($services, $selections);
          $detailText = $detailParts ? implode(' · ', $detailParts) : 'Prestation à préciser';
        ?>
        <article class="prestation-row">
          <div class="prestation-date">
            <strong><?= $row['event_date'] ? e(date('d/m/Y', strtotime($row['event_date']))) : 'À définir' ?></strong>
            <span><?= ($row['start_time'] || $row['end_time']) ? e(($row['start_time'] ?: '—') . ' → ' . ($row['end_time'] ?: '—')) : 'Horaires à définir' ?></span>
          </div>

          <div class="prestation-client">
            <strong><?= e($row['name']) ?></strong>
            <span><?= e($row['email'] ?: '—') ?></span>
          </div>

          <div class="prestation-event">
            <strong><?= e($row['event_type'] ?: 'Événement') ?> · <?= e($row['venue'] ?: 'Lieu à définir') ?></strong>
            <span><?= e($detailText) ?></span>
          </div>

          <div class="prestation-money">
            <?php if ($row['amount_cents'] !== null): ?>
              <strong><?= number_format(((int)$row['amount_cents']) / 100, 2, ',', ' ') ?> €</strong>
              <span><?= e($row['quote_number'] ?: 'Devis enregistré') ?></span>
            <?php else: ?>
              <strong>—</strong>
              <span>Montant non renseigné</span>
            <?php endif; ?>
          </div>

          <div class="prestation-actions">
            <a class="admin-link" href="devis.php?id=<?= (int)$row['quote_id'] ?>">Demande →</a>
            <a class="admin-link" href="documents.php?create_from=<?= (int)$row['quote_id'] ?>">Documents →</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php admin_footer(); ?>
