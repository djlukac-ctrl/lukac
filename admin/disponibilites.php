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
.reserved-page{max-width:980px}
.reserved-intro{max-width:820px;margin:0 0 24px;color:#aaa6a0;font-size:13px;line-height:1.7}

.reserved-add{
  position:relative;
  display:grid;
  grid-template-columns:minmax(240px,340px) auto;
  gap:14px;
  align-items:end;
  max-width:760px;
  margin-bottom:34px;
  padding:22px;
  border:1px solid rgba(255,255,255,.085);
  border-radius:20px;
  background:linear-gradient(180deg,rgba(18,18,21,.96),rgba(12,12,15,.96));
  box-shadow:0 18px 48px rgba(0,0,0,.18),inset 0 1px 0 rgba(255,255,255,.018);
  overflow:hidden;
}
.reserved-add::before{
  content:"";
  position:absolute;
  left:0;
  top:0;
  width:140px;
  height:1px;
  background:linear-gradient(90deg,#e32228,transparent);
  box-shadow:0 0 14px rgba(227,34,40,.35);
}
.reserved-add__field{display:grid;gap:8px}
.reserved-add__field label{
  font-size:10px;
  font-weight:800;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:#8f8a84;
}
.reserved-add input{
  width:100%;
  box-sizing:border-box;
  height:46px;
  border-radius:12px;
  background:#09090b;
  color:#fff;
  border:1px solid rgba(255,255,255,.09);
  padding:0 14px;
}
.reserved-add input:focus{
  outline:none;
  border-color:rgba(227,34,40,.58);
  box-shadow:0 0 0 3px rgba(227,34,40,.075),0 0 22px rgba(227,34,40,.035);
}
.reserved-add .btn{
  min-height:46px;
}

.reserved-section-title{
  display:flex;
  align-items:center;
  gap:10px;
  margin:0 0 14px;
  color:#fff;
  font-size:23px;
  letter-spacing:-.025em;
}
.reserved-section-title::before{
  content:"";
  width:5px;
  height:5px;
  border-radius:50%;
  background:#e32228;
  box-shadow:0 0 10px rgba(227,34,40,.8);
}

.reserved-list{
  display:grid;
  gap:10px;
  max-width:860px;
}
.reserved-row{
  position:relative;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:18px;
  padding:16px 18px 16px 20px;
  border:1px solid rgba(255,255,255,.075);
  border-radius:16px;
  background:linear-gradient(180deg,#111114,#0d0d10);
  box-shadow:0 10px 28px rgba(0,0,0,.10);
  overflow:hidden;
}
.reserved-row::before{
  content:"";
  position:absolute;
  left:0;
  top:0;
  bottom:0;
  width:2px;
  background:linear-gradient(180deg,#e32228,rgba(227,34,40,.16),transparent);
}
.reserved-row:hover{
  border-color:rgba(227,34,40,.16);
  background:linear-gradient(180deg,#151518,#101013);
}
.reserved-row__date{
  display:grid;
  gap:4px;
}
.reserved-row__date strong{
  color:#fff;
  font-size:14px;
  letter-spacing:-.01em;
}
.reserved-row__date span{
  color:#77726c;
  font-size:10px;
  letter-spacing:.05em;
}
.reserved-row form{margin:0}
.reserved-delete{
  border:1px solid rgba(227,34,40,.24);
  border-radius:999px;
  background:rgba(227,34,40,.06);
  color:#ff8a8f;
  padding:9px 13px;
  font-size:11px;
  font-weight:800;
  cursor:pointer;
}
.reserved-delete:hover{
  background:#e32228;
  color:#fff;
  border-color:#e32228;
  box-shadow:0 8px 22px rgba(227,34,40,.16);
}

.reserved-empty{
  max-width:860px;
  padding:24px;
  border:1px dashed rgba(255,255,255,.12);
  border-radius:16px;
  color:#8f8a84;
  background:rgba(255,255,255,.018);
  font-size:13px;
}
.reserved-past{
  margin-top:34px;
  max-width:860px;
}
.reserved-past summary{
  cursor:pointer;
  color:#8f8a84;
  font-size:12px;
  font-weight:700;
}
.reserved-past summary:hover{color:#fff}
.reserved-past .reserved-list{
  margin-top:12px;
  opacity:.72;
}

@media(max-width:680px){
  .reserved-add{grid-template-columns:1fr}
  .reserved-add button{width:100%}
  .reserved-row{align-items:flex-start;flex-direction:column}
  .reserved-delete{width:100%}
}
</style>

<div class="reserved-page">
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

<h2 class="reserved-section-title">Réservations à venir</h2>
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

</div>
<?php admin_footer(); ?>
