<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'update_status');

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM quotes WHERE id=:id');
        $stmt->execute([':id' => $id]);
        header('Location: devis.php?deleted=1');
        exit;
    }

    $status = (string) ($_POST['status'] ?? '');
    $allowed = ['new','read','contacted','quote_sent','booked','archived'];
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

    $services = array_values(array_filter(array_map('trim', explode('|', (string)($quote['services'] ?? '')))));
    $selections = array_values(array_filter(array_map('trim', explode('|', (string)($quote['selections'] ?? '')))));
    $formulaNames = ['Essentiel', 'Ambiance', 'Expérience'];
    $formulas = array_values(array_intersect($selections, $formulaNames));
    $options = array_values(array_diff($selections, $formulaNames));

    admin_header('Demande #' . $id, 'devis');
    if (isset($_GET['saved'])): ?><div class="flash flash--success">Statut mis à jour.</div><?php endif; ?>
    <div class="quote-detail">
      <section class="quote-main">
        <section class="form-card quote-block">
          <div class="admin-section__head">
            <div>
              <span class="quote-section-kicker">Client</span>
              <h2>Coordonnées du client</h2>
            </div>
            <span class="status status--<?= e($quote['status']) ?>"><?= e(quote_status_label($quote['status'])) ?></span>
          </div>
          <div class="quote-meta quote-meta--client">
            <div><span>Nom et prénom</span><strong><?= e($quote['name']) ?></strong></div>
            <div><span>E-mail</span><a href="mailto:<?= e($quote['email']) ?>"><?= e($quote['email']) ?></a></div>
            <div><span>Téléphone</span><?= $quote['phone'] ? '<a href="tel:' . e($quote['phone']) . '">' . e($quote['phone']) . '</a>' : '—' ?></div>
            <div class="quote-meta__wide"><span>Adresse postale</span><?= e($quote['postal_address'] ?: '—') ?></div>
          </div>
        </section>

        <section class="form-card quote-block">
          <div class="quote-block__heading"><span class="quote-section-kicker">Événement</span><h2>Détails de la soirée</h2></div>
          <div class="quote-meta">
            <div><span>Type d’événement</span><?= e($quote['event_type']) ?></div>
            <div><span>Date</span><?= $quote['event_date'] ? e(date('d/m/Y', strtotime($quote['event_date']))) : 'À définir' ?></div>
            <div><span>Lieu de réception / commune</span><?= e($quote['venue'] ?: 'À définir') ?></div>
            <div><span>Nombre d’invités</span><?= $quote['guest_count'] ? (int) $quote['guest_count'] : '—' ?></div>
            <div><span>Arrivée des invités</span><?= e($quote['start_time'] ?: '—') ?></div>
            <div><span>Fin de soirée</span><?= e($quote['end_time'] ?: '—') ?></div>
            <div><span>Recommandé par</span><?= e($quote['referral'] ?: 'Non renseigné') ?></div>
            <div class="quote-meta__wide"><span>Demande reçue le</span><?= e(date('d/m/Y à H:i', strtotime($quote['created_at']))) ?></div>
          </div>
          <div class="quote-project">
            <span>Projet du client</span>
            <p><?= nl2br(e($quote['message'] ?: 'Aucun message complémentaire.')) ?></p>
          </div>
        </section>

        <section class="form-card quote-block">
          <div class="quote-block__heading"><span class="quote-section-kicker">Choix du client</span><h2>Prestations, formules & options</h2></div>
          <div class="quote-choice-grid">
            <div class="quote-choice">
              <h3>Prestations</h3>
              <?php if ($services): ?><ul><?php foreach ($services as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php else: ?><p>Non renseigné</p><?php endif; ?>
            </div>
            <div class="quote-choice">
              <h3>Formules</h3>
              <?php if ($formulas): ?><ul><?php foreach ($formulas as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php else: ?><p>Aucune formule sélectionnée</p><?php endif; ?>
            </div>
            <div class="quote-choice">
              <h3>Packs & options</h3>
              <?php if ($options): ?><ul><?php foreach ($options as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php else: ?><p>Aucun pack ou option sélectionné</p><?php endif; ?>
            </div>
          </div>
        </section>
      </section>

      <aside class="form-card quote-followup">
        <h2>Suivi de la demande</h2>
        <form method="post" class="admin-form">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="field"><label for="status">Statut</label><select id="status" name="status">
            <?php foreach (['new','read','contacted','quote_sent','booked','archived'] as $status): ?><option value="<?= e($status) ?>" <?= $quote['status'] === $status ? 'selected' : '' ?>><?= e(quote_status_label($status)) ?></option><?php endforeach; ?>
          </select></div>
          <button class="btn btn--primary" type="submit" name="action" value="update_status">Enregistrer le statut</button>
          <a class="btn" href="mailto:<?= e($quote['email']) ?>?subject=Votre%20demande%20de%20devis%20-%20Luka%20C">Répondre par e-mail</a>
          <?php if ($quote['phone']): ?><a class="btn" href="tel:<?= e($quote['phone']) ?>">Appeler le client</a><?php endif; ?>
          <button class="btn btn--danger" type="submit" name="action" value="delete" onclick="return confirm('Supprimer définitivement cette demande de devis ? Cette action est irréversible.');">Supprimer la demande</button>
          <a class="admin-link" href="devis.php">← Retour aux demandes</a>
        </form>
      </aside>
    </div>
    <?php admin_footer(); exit;
}

$statusFilter = (string) ($_GET['status'] ?? '');
$params = [];
$sql = 'SELECT * FROM quotes';
if (in_array($statusFilter, ['new','read','contacted','quote_sent','booked','archived'], true)) {
    $sql .= ' WHERE status=:status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();
admin_header('Demandes de devis', 'devis');
if (isset($_GET['deleted'])): ?><div class="flash flash--success">Demande de devis supprimée.</div><?php endif; ?>
<div class="admin-section__head">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn" href="devis.php">Toutes</a>
    <?php foreach (['new','contacted','quote_sent','booked','archived'] as $status): ?><a class="btn" href="devis.php?status=<?= e($status) ?>"><?= e(quote_status_label($status)) ?></a><?php endforeach; ?>
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