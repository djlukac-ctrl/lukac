<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();
$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $date = trim((string)($_POST['event_date'] ?? ''));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        $valid = $parsed
            && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))
            && $parsed->format('Y-m-d') === $date;

        if (!$valid) {
            $error = 'Merci de choisir une date valide.';
        } elseif ($parsed < new DateTimeImmutable('today')) {
            $error = 'Une date passée ne peut pas être ajoutée comme réservation.';
        } else {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO reserved_dates(event_date) VALUES(:date)');
            $stmt->execute([':date' => $date]);
            $flash = $stmt->rowCount() > 0
                ? 'La date a bien été ajoutée aux réservations.'
                : 'Cette date est déjà enregistrée comme réservée.';
        }
    }

    if ($action === 'delete') {
        $date = trim((string)($_POST['event_date'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $stmt = $pdo->prepare('DELETE FROM reserved_dates WHERE event_date = :date');
            $stmt->execute([':date' => $date]);
            $flash = 'La date a été retirée des réservations.';
        }
    }
}

$rows = $pdo
    ->query('SELECT event_date FROM reserved_dates ORDER BY event_date ASC')
    ->fetchAll(PDO::FETCH_COLUMN);

$future = [];
$past = [];
$today = new DateTimeImmutable('today');
$weekdays = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$months = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];

foreach ($rows as $date) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$date);
    if (!$parsed) continue;
    $item = [
        'iso' => (string)$date,
        'label' => $weekdays[(int)$parsed->format('w')] . ' ' . $parsed->format('j') . ' ' . $months[(int)$parsed->format('n')] . ' ' . $parsed->format('Y'),
    ];
    if ($parsed >= $today) $future[] = $item;
    else $past[] = $item;
}

admin_header('Dates réservées', 'disponibilites');
?>
<?php if ($flash): ?><div class="flash flash--success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<style>
.reserved-intro{max-width:760px;margin-bottom:22px;color:#756e67;font-size:13px;line-height:1.7}
.reserved-add{display:grid;grid-template-columns:minmax(220px,320px) auto;gap:12px;align-items:end;max-width:620px;margin-bottom:28px;padding:20px;border:1px solid rgba(24,23,22,.10);border-radius:18px;background:#fff;box-shadow:0 12px 32px rgba(50,38,28,.05)}
.reserved-add__field{display:grid;gap:7px}.reserved-add__field label{font-size:11px;font-weight:700;color:#6f6862}.reserved-add input{width:100%;box-sizing:border-box}
.reserved-list{display:grid;gap:10px;max-width:760px}
.reserved-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 16px;border:1px solid rgba(24,23,22,.09);border-radius:14px;background:#fff}
.reserved-row__date{display:grid;gap:3px}.reserved-row__date strong{font-size:14px;color:#181716}.reserved-row__date span{font-size:10px;color:#9a938c}
.reserved-row form{margin:0}.reserved-delete{border:1px solid rgba(201,52,49,.18);border-radius:999px;background:#fff;color:#b53d39;padding:9px 12px;font-size:11px;font-weight:700;cursor:pointer}.reserved-delete:hover{background:#c93431;color:#fff;border-color:#c93431}
.reserved-empty{max-width:760px;padding:24px;border:1px dashed rgba(24,23,22,.14);border-radius:16px;color:#817a73;background:rgba(255,255,255,.45);font-size:13px}
.reserved-past{margin-top:34px;max-width:760px}.reserved-past summary{cursor:pointer;color:#817a73;font-size:12px;font-weight:700}.reserved-past .reserved-list{margin-top:12px;opacity:.65}
@media(max-width:680px){.reserved-add{grid-template-columns:1fr}.reserved-add button{width:100%}.reserved-row{align-items:flex-start;flex-direction:column}.reserved-delete{width:100%}}
</style>

<p class="reserved-intro">
  Ajoute ici uniquement les dates déjà réservées. Toutes les autres dates futures restent automatiquement disponibles dans le calendrier de demande de devis.
</p>

<form method="post" class="reserved-add">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add">
  <div class="reserved-add__field">
    <label for="event_date">Nouvelle date réservée</label>
    <input id="event_date" type="date" name="event_date" min="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" required>
  </div>
  <button class="btn btn--primary" type="submit">Ajouter la date</button>
</form>

<h2 style="margin:0 0 14px">Réservations à venir</h2>
<?php if (!$future): ?>
  <div class="reserved-empty">Aucune date réservée à venir pour le moment.</div>
<?php else: ?>
  <div class="reserved-list">
    <?php foreach ($future as $item): ?>
      <div class="reserved-row">
        <div class="reserved-row__date">
          <strong><?= e($item['label']) ?></strong>
          <span><?= e($item['iso']) ?></span>
        </div>
        <form method="post" onsubmit="return confirm('Rendre cette date de nouveau disponible ?');">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="event_date" value="<?= e($item['iso']) ?>">
          <button class="reserved-delete" type="submit">Rendre disponible</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($past): ?>
  <details class="reserved-past">
    <summary>Afficher les anciennes réservations (<?= count($past) ?>)</summary>
    <div class="reserved-list">
      <?php foreach ($past as $item): ?>
        <div class="reserved-row">
          <div class="reserved-row__date">
            <strong><?= e($item['label']) ?></strong>
            <span><?= e($item['iso']) ?></span>
          </div>
          <form method="post" onsubmit="return confirm('Supprimer cette ancienne réservation ?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="event_date" value="<?= e($item['iso']) ?>">
            <button class="reserved-delete" type="submit">Supprimer</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
<?php endif; ?>

<?php admin_footer(); ?>
