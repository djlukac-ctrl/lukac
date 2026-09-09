<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    $allowed = ['new','read','contacted','booked','archived'];
    if ($id > 0 && in_array($status, $allowed, true)) {
        $stmt = $pdo->prepare('UPDATE quotes SET status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        header('Location: devis.php?id=' . $id . '&saved=1');
        exit;
    }
}

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id=:id');
    $stmt->execute([':id' => $id]);
    $quote = $stmt->fetch();
    if (!$quote) {
        http_response_code(404);
        exit('Demande introuvable');
    }
    if ($quote['status'] === 'new') {
        $pdo->prepare("UPDATE quotes SET status='read', updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':id' => $id]);
        $quote['status'] = 'read';
    }

    admin_header('Demande #' . $id, 'devis');
    if (isset($_GET['saved'])): ?><div class="flash flash--success">Statut mis à jour.</div><?php endif; ?>
    <div class="quote-detail">
      <section class="form-card">
        <div class="admin-section__head"><h2><?= e($quote['name']) ?></h2><span class="status status--<?= e($quote['status']) ?>"><?= e(quote_status_label($quote['status'])) ?></span></div>
        <div class="quote-meta">
          <div><span>E-mail</span><a href="mailto:<?= e($quote['email']) ?>"><?= e($quote['email']) ?></a></div>
          <div><span>Téléphone</span><?= $quote['phone'] ? '<a href="tel:' . e($quote['phone']) . '">' . e($quote['phone']) . '</a>' : '—' ?></div>
          <div><span>Adresse postale</span><?= e($quote['postal_address'] ?: '—') ?></div>
          <div><span>Recommandé par</span><?= e($quote['referral'] ?: 'Non renseigné') ?></div>
          <div><span>Événement</span><?= e($quote['event_type']) ?></div>
          <div><span>Date</span><?= $quote['event_date'] ? e(date('d/m/Y', strtotime($quote['event_date']))) : 'À définir' ?></div>
          <div><span>Lieu</span><?= e($quote['venue'] ?: 'À définir') ?></div>
          <div><span>Invités</span><?= $quote['guest_count'] ? (int) $quote['guest_count'] : '—' ?></div>
          <div><span>Arrivée des invités</span><?= e($quote['start_time'] ?: '—') ?></div>
          <div><span>Fin de soirée</span><?= e($quote['end_time'] ?: '—') ?></div>
          <div><span>Budget indicatif</span><?= e($quote['budget'] ?: 'Non renseigné') ?></div>
          <div><span>Reçue le</span><?= e(date('d/m/Y à H:i', strtotime($quote['created_at']))) ?></div>
        </div>
        <div class="admin-section"><h2>Prestations souhaitées</h2><div class="quote-message"><?= e($quote['services'] ?: 'Aucune prestation renseignée.') ?></div></div>
        <div class="admin-section"><h2>Formules, packs & options</h2><div class="quote-message"><?= e($quote['selections'] ?: 'Aucune sélection renseignée.') ?></div></div>
        <div class="admin-section"><h2>Projet du client</h2><div class="quote-message"><?= e($quote['message'] ?: 'Aucun message complémentaire.') ?></div></div>
      </section>
      <aside class="form-card">
        <h2>Suivi de la demande</h2>
        <form method="post" class="admin-form">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="field"><label for="status">Statut</label><select id="status" name="status">
            <?php foreach (['new','read','contacted','booked','archived'] as $status): ?><option value="<?= e($status) ?>" <?= $quote['status'] === $status ? 'selected' : '' ?>><?= e(quote_status_label($status)) ?></option><?php endforeach; ?>
          </select></div>
          <button class="btn btn--primary" type="submit">Enregistrer le statut</button>
          <a class="btn" href="mailto:<?= e($quote['email']) ?>?subject=Votre%20demande%20de%20devis%20-%20Luka%20C">Répondre par e-mail</a>
          <?php if ($quote['phone']): ?><a class="btn" href="tel:<?= e($quote['phone']) ?>">Appeler le client</a><?php endif; ?>
          <a class="admin-link" href="devis.php">← Retour aux demandes</a>
        </form>
      </aside>
    </div>
    <?php admin_footer(); exit;
}

$statusFilter = (string) ($_GET['status'] ?? '');
$params = [];
$sql = 'SELECT * FROM quotes';
if (in_array($statusFilter, ['new','read','contacted','booked','archived'], true)) {
    $sql .= ' WHERE status=:status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();
admin_header('Demandes de devis', 'devis');
?>
<div class="admin-section__head">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn" href="devis.php">Toutes</a>
    <?php foreach (['new','contacted','booked','archived'] as $status): ?><a class="btn" href="devis.php?status=<?= e($status) ?>"><?= e(quote_status_label($status)) ?></a><?php endforeach; ?>
  </div>
</div>
<div class="admin-table-wrap">
<table><thead><tr><th>Client</th><th>Événement</th><th>Date</th><th>Lieu</th><th>Horaires</th><th>Reçue le</th><th>Statut</th><th></th></tr></thead><tbody>
<?php if (!$quotes): ?><tr><td colspan="8">Aucune demande dans cette catégorie.</td></tr><?php else: foreach ($quotes as $quote): ?>
<tr>
  <td><strong><?= e($quote['name']) ?></strong><br><span style="color:#777"><?= e($quote['email']) ?></span></td>
  <td><?= e($quote['event_type']) ?></td>
  <td><?= $quote['event_date'] ? e(date('d/m/Y', strtotime($quote['event_date']))) : 'À définir' ?></td>
  <td><?= e($quote['venue'] ?: '—') ?></td>
  <td><?= e(($quote['start_time'] ?: '—') . ' → ' . ($quote['end_time'] ?: '—')) ?></td>
  <td><?= e(date('d/m/Y H:i', strtotime($quote['created_at']))) ?></td>
  <td><span class="status status--<?= e($quote['status']) ?>"><?= e(quote_status_label($quote['status'])) ?></span></td>
  <td><a class="admin-link" href="devis.php?id=<?= (int) $quote['id'] ?>">Ouvrir →</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php admin_footer(); ?>
