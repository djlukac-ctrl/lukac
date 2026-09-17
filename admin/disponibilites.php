<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();
$months = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$statuses = ['open'=>'Ouvert','limited'=>'Quelques disponibilités','closed'=>'Complet / Fermé'];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $availability = $_POST['availability'] ?? [];
    $dates = $_POST['dates'] ?? [];
    $stmt = $pdo->prepare('INSERT INTO availability(year, month, status, note) VALUES(:y,:m,:s,:n) ON CONFLICT(year,month) DO UPDATE SET status=excluded.status, note=excluded.note');
    foreach ([2026, 2027] as $year) {
        foreach ($months as $month => $label) {
            $status = $availability[$year][$month] ?? null;
            if (!isset($statuses[$status])) continue;

            $note = trim((string)($dates[$year][$month] ?? ''));
            $note = strip_tags($note);
            if (function_exists('mb_substr')) {
                $note = mb_substr($note, 0, 120);
            } else {
                $note = substr($note, 0, 120);
            }
            if ($status !== 'limited') $note = '';

            $stmt->execute([
                ':y'=>$year,
                ':m'=>$month,
                ':s'=>$status,
                ':n'=>$note !== '' ? $note : null,
            ]);
        }
    }
    $saved = true;
}

$rows = $pdo->query('SELECT year, month, status, note FROM availability WHERE year IN (2026,2027)')->fetchAll();
$current = [];
$currentDates = [];
foreach ($rows as $row) {
    $year = (int)$row['year'];
    $month = (int)$row['month'];
    $current[$year][$month] = $row['status'];
    $currentDates[$year][$month] = (string)($row['note'] ?? '');
}

admin_header('Disponibilités', 'disponibilites');
?>
<?php if ($saved): ?><div class="flash flash--success">Les disponibilités ont bien été mises à jour sur le site.</div><?php endif; ?>
<style>
.availability-row{
  display:grid!important;
  grid-template-columns:minmax(90px,120px) minmax(0,1fr)!important;
  gap:10px 14px!important;
  align-items:center!important;
}
.availability-row>label{margin:0!important}
.availability-row>select{width:100%;min-width:0}
.availability-dates{
  grid-column:1/-1;
  display:none;
  grid-template-columns:1fr;
  gap:6px;
  padding:12px 0 2px;
  border-top:1px dashed rgba(24,23,22,.10);
}
.availability-row.is-limited .availability-dates{display:grid}
.availability-dates label{font-size:10px;color:#77706a;font-weight:600}
.availability-dates input{width:100%;min-width:0;box-sizing:border-box}
.availability-dates small{color:#8b847d;font-size:10px;line-height:1.35}
@media(max-width:760px){
  .availability-row{grid-template-columns:1fr!important;gap:8px!important}
  .availability-dates{grid-column:1}
}
</style>
<form method="post" class="admin-form" id="availability-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <div class="availability-editor">
    <?php foreach ([2026, 2027] as $year): ?>
      <section class="availability-year">
        <h2><?= $year ?></h2>
        <?php foreach ($months as $month => $label): $statusValue = $current[$year][$month] ?? 'open'; ?>
          <div class="availability-row <?= $statusValue === 'limited' ? 'is-limited' : '' ?>">
            <label for="a-<?= $year ?>-<?= $month ?>"><?= e($label) ?></label>
            <select id="a-<?= $year ?>-<?= $month ?>" name="availability[<?= $year ?>][<?= $month ?>]" data-availability-status>
              <?php foreach ($statuses as $value => $statusLabel): ?>
                <option value="<?= e($value) ?>" <?= $statusValue === $value ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="availability-dates">
              <label for="d-<?= $year ?>-<?= $month ?>">Dates encore disponibles</label>
              <input id="d-<?= $year ?>-<?= $month ?>" name="dates[<?= $year ?>][<?= $month ?>]" maxlength="120" value="<?= e($currentDates[$year][$month] ?? '') ?>" placeholder="Ex. Samedis 3, 10 et 24">
              <small>Ce texte sera affiché sur le site uniquement pour ce mois.</small>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="admin-actions"><button class="btn btn--primary" type="submit">Enregistrer les disponibilités</button></div>
</form>
<script>
document.querySelectorAll('[data-availability-status]').forEach((select) => {
  const sync = () => select.closest('.availability-row')?.classList.toggle('is-limited', select.value === 'limited');
  select.addEventListener('change', sync);
  sync();
});
</script>
<?php admin_footer(); ?>
