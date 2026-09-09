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
    $stmt = $pdo->prepare('INSERT INTO availability(year, month, status) VALUES(:y,:m,:s) ON CONFLICT(year,month) DO UPDATE SET status=excluded.status');
    foreach ([2026, 2027] as $year) {
        foreach ($months as $month => $label) {
            $status = $availability[$year][$month] ?? null;
            if (isset($statuses[$status])) {
                $stmt->execute([':y'=>$year, ':m'=>$month, ':s'=>$status]);
            }
        }
    }
    $saved = true;
}

$rows = $pdo->query('SELECT year, month, status FROM availability WHERE year IN (2026,2027)')->fetchAll();
$current = [];
foreach ($rows as $row) {
    $current[(int)$row['year']][(int)$row['month']] = $row['status'];
}

admin_header('Disponibilités', 'disponibilites');
?>
<?php if ($saved): ?><div class="flash flash--success">Les disponibilités ont bien été mises à jour sur le site.</div><?php endif; ?>
<form method="post" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <div class="availability-editor">
    <?php foreach ([2026, 2027] as $year): ?>
      <section class="availability-year">
        <h2><?= $year ?></h2>
        <?php foreach ($months as $month => $label): ?>
          <div class="availability-row">
            <label for="a-<?= $year ?>-<?= $month ?>"><?= e($label) ?></label>
            <select id="a-<?= $year ?>-<?= $month ?>" name="availability[<?= $year ?>][<?= $month ?>]">
              <?php foreach ($statuses as $value => $statusLabel): ?>
                <option value="<?= e($value) ?>" <?= ($current[$year][$month] ?? 'open') === $value ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="admin-actions"><button class="btn btn--primary" type="submit">Enregistrer les disponibilités</button></div>
</form>
<?php admin_footer(); ?>
