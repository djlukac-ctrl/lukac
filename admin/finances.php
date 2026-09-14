<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

$pdo->exec('CREATE TABLE IF NOT EXISTS finance_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL CHECK(type IN ("income","expense")),
    label TEXT NOT NULL,
    category TEXT,
    amount REAL NOT NULL,
    transaction_date TEXT NOT NULL,
    quote_id INTEGER,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_finance_transactions_date ON finance_transactions(transaction_date)');
$pdo->exec('INSERT OR IGNORE INTO content(content_key, value) VALUES("finance.social_rate", "0")');

function finance_money(float $amount): string
{
    return number_format($amount, 2, ',', ' ') . ' €';
}

function finance_month_name(int $month): string
{
    return [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'][$month] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_rate') {
        $raw = str_replace(',', '.', trim((string)($_POST['social_rate'] ?? '0')));
        $rate = is_numeric($raw) ? max(0, min(100, (float)$raw)) : 0;
        $stmt = $pdo->prepare('INSERT INTO content(content_key,value,updated_at) VALUES("finance.social_rate",:value,CURRENT_TIMESTAMP) ON CONFLICT(content_key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([':value' => (string)$rate]);
        header('Location: finances.php?saved=rate');
        exit;
    }

    if ($action === 'add') {
        $type = (string)($_POST['type'] ?? 'income');
        $label = trim((string)($_POST['label'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $date = trim((string)($_POST['transaction_date'] ?? ''));
        $amountRaw = str_replace(',', '.', trim((string)($_POST['amount'] ?? '')));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $quoteId = (int)($_POST['quote_id'] ?? 0);
        $amount = is_numeric($amountRaw) ? (float)$amountRaw : 0;

        if (!in_array($type, ['income','expense'], true) || $label === '' || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            header('Location: finances.php?error=1');
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO finance_transactions(type,label,category,amount,transaction_date,quote_id,notes) VALUES(:type,:label,:category,:amount,:date,:quote_id,:notes)');
        $stmt->execute([
            ':type'=>$type,
            ':label'=>$label,
            ':category'=>$category ?: null,
            ':amount'=>$amount,
            ':date'=>$date,
            ':quote_id'=>$quoteId > 0 ? $quoteId : null,
            ':notes'=>$notes ?: null,
        ]);
        header('Location: finances.php?year=' . substr($date,0,4) . '&month=' . (int)substr($date,5,2) . '&saved=transaction');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM finance_transactions WHERE id=:id');
            $stmt->execute([':id'=>$id]);
        }
        $returnYear = (int)($_POST['return_year'] ?? date('Y'));
        $returnMonth = (int)($_POST['return_month'] ?? date('n'));
        header('Location: finances.php?year=' . $returnYear . '&month=' . $returnMonth . '&deleted=1');
        exit;
    }
}

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($year < 2020 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

$rate = (float)($pdo->query('SELECT value FROM content WHERE content_key="finance.social_rate"')->fetchColumn() ?: 0);
$monthKey = sprintf('%04d-%02d', $year, $month);

$stmt = $pdo->prepare('SELECT * FROM finance_transactions WHERE substr(transaction_date,1,7)=:month ORDER BY transaction_date DESC, id DESC');
$stmt->execute([':month'=>$monthKey]);
$transactions = $stmt->fetchAll();

$income = 0.0;
$expenses = 0.0;
foreach ($transactions as $row) {
    if ($row['type'] === 'income') $income += (float)$row['amount'];
    else $expenses += (float)$row['amount'];
}
$social = $income * ($rate / 100);
$net = $income - $social - $expenses;

$annual = array_fill(1, 12, ['income'=>0.0,'expense'=>0.0]);
$stmt = $pdo->prepare('SELECT type, amount, transaction_date FROM finance_transactions WHERE substr(transaction_date,1,4)=:year');
$stmt->execute([':year'=>(string)$year]);
foreach ($stmt->fetchAll() as $row) {
    $m = (int)substr((string)$row['transaction_date'],5,2);
    if ($m >= 1 && $m <= 12) {
        $key = $row['type'] === 'income' ? 'income' : 'expense';
        $annual[$m][$key] += (float)$row['amount'];
    }
}
$annualIncome = array_sum(array_column($annual, 'income'));
$annualExpenses = array_sum(array_column($annual, 'expense'));
$annualSocial = $annualIncome * ($rate / 100);
$annualNet = $annualIncome - $annualSocial - $annualExpenses;

admin_header('Finances', 'finances');
?>
<style>
.finance-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:22px;flex-wrap:wrap}.finance-filter{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}.finance-filter .field{min-width:150px}.finance-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:24px}.finance-kpi{background:#fff;border:1px solid rgba(24,23,22,.1);border-radius:18px;padding:20px;box-shadow:0 10px 28px rgba(50,38,28,.035)}.finance-kpi span{display:block;color:#7b746e;font-size:11px;margin-bottom:8px}.finance-kpi strong{font-size:27px;letter-spacing:-.04em}.finance-kpi--net strong{color:#4f7d3d}.finance-kpi--negative strong{color:#b44d48}.finance-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.55fr);gap:22px;align-items:start}.finance-form{display:grid;gap:14px}.finance-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.finance-summary{margin-top:24px}.finance-months{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.finance-month{display:block;padding:15px;border:1px solid rgba(24,23,22,.09);border-radius:14px;background:#faf8f5;color:#181716;text-decoration:none}.finance-month.is-active{border-color:rgba(201,52,49,.35);background:#fff5f4}.finance-month strong,.finance-month span{display:block}.finance-month strong{margin-bottom:7px}.finance-month span{font-size:12px;color:#77706a}.finance-rate-note{font-size:12px;line-height:1.55;color:#756e67;margin:0}.finance-table .amount-income{color:#4f7d3d;font-weight:700}.finance-table .amount-expense{color:#b44d48;font-weight:700}.finance-empty{padding:26px;text-align:center;color:#77706a}.finance-delete{border:0;background:none;color:#b44d48;cursor:pointer}.finance-annual{display:flex;gap:18px;flex-wrap:wrap;margin:10px 0 22px;color:#5f5953}.finance-annual strong{color:#181716}@media(max-width:1050px){.finance-kpis{grid-template-columns:repeat(2,1fr)}.finance-layout{grid-template-columns:1fr}.finance-months{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.finance-kpis,.finance-form-grid,.finance-months{grid-template-columns:1fr}.finance-filter{width:100%}.finance-filter .field{min-width:0;flex:1}.finance-toolbar>.btn{width:100%}}
</style>

<?php if (isset($_GET['saved'])): ?><div class="flash flash--success">Modification enregistrée.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="flash flash--success">Opération supprimée.</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="flash flash--error">Merci de vérifier les informations saisies.</div><?php endif; ?>

<div class="finance-toolbar">
  <form method="get" class="finance-filter">
    <div class="field"><label for="month">Mois</label><select id="month" name="month"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= e(finance_month_name($m)) ?></option><?php endfor; ?></select></div>
    <div class="field"><label for="year">Année</label><select id="year" name="year"><?php for($y=(int)date('Y')-2;$y<=(int)date('Y')+3;$y++): ?><option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
    <button class="btn" type="submit">Afficher</button>
  </form>
</div>

<div class="finance-kpis">
  <div class="finance-kpi"><span>Chiffre d’affaires encaissé</span><strong><?= finance_money($income) ?></strong></div>
  <div class="finance-kpi"><span>Cotisations estimées (<?= number_format($rate,2,',',' ') ?> %)</span><strong><?= finance_money($social) ?></strong></div>
  <div class="finance-kpi"><span>Dépenses enregistrées</span><strong><?= finance_money($expenses) ?></strong></div>
  <div class="finance-kpi finance-kpi--net <?= $net < 0 ? 'finance-kpi--negative' : '' ?>"><span>Net estimé du mois</span><strong><?= finance_money($net) ?></strong></div>
</div>

<div class="finance-layout">
  <section>
    <div class="admin-table-wrap finance-table">
      <table>
        <thead><tr><th>Date</th><th>Libellé</th><th>Catégorie</th><th>Type</th><th>Montant</th><th></th></tr></thead>
        <tbody>
        <?php if (!$transactions): ?><tr><td colspan="6" class="finance-empty">Aucune opération enregistrée pour <?= e(finance_month_name($month)) ?> <?= $year ?>.</td></tr><?php else: foreach($transactions as $row): ?>
          <tr>
            <td><?= e(date('d/m/Y', strtotime($row['transaction_date']))) ?></td>
            <td><strong><?= e($row['label']) ?></strong><?php if ($row['notes']): ?><br><span style="color:#777;font-size:11px"><?= e($row['notes']) ?></span><?php endif; ?></td>
            <td><?= e($row['category'] ?: '—') ?></td>
            <td><?= $row['type']==='income' ? 'Encaissement' : 'Dépense' ?></td>
            <td class="<?= $row['type']==='income' ? 'amount-income' : 'amount-expense' ?>"><?= $row['type']==='income' ? '+' : '−' ?><?= finance_money((float)$row['amount']) ?></td>
            <td><form method="post" onsubmit="return confirm('Supprimer cette opération ?');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="return_year" value="<?= $year ?>"><input type="hidden" name="return_month" value="<?= $month ?>"><button class="finance-delete" type="submit">Supprimer</button></form></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <section class="form-card finance-summary">
      <h2>Récapitulatif <?= $year ?></h2>
      <div class="finance-annual"><span>CA annuel : <strong><?= finance_money($annualIncome) ?></strong></span><span>Cotisations estimées : <strong><?= finance_money($annualSocial) ?></strong></span><span>Dépenses : <strong><?= finance_money($annualExpenses) ?></strong></span><span>Net estimé : <strong><?= finance_money($annualNet) ?></strong></span></div>
      <div class="finance-months">
        <?php for($m=1;$m<=12;$m++): $mi=$annual[$m]['income']; $me=$annual[$m]['expense']; $mn=$mi-($mi*$rate/100)-$me; ?>
          <a class="finance-month <?= $m===$month?'is-active':'' ?>" href="finances.php?year=<?= $year ?>&month=<?= $m ?>"><strong><?= e(finance_month_name($m)) ?></strong><span>CA <?= finance_money($mi) ?> · Net <?= finance_money($mn) ?></span></a>
        <?php endfor; ?>
      </div>
    </section>
  </section>

  <aside>
    <section class="form-card" style="margin-bottom:18px">
      <h2>Ajouter une opération</h2>
      <form method="post" class="finance-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add">
        <div class="finance-form-grid">
          <div class="field"><label for="type">Type</label><select id="type" name="type"><option value="income">Encaissement</option><option value="expense">Dépense</option></select></div>
          <div class="field"><label for="transaction_date">Date</label><input id="transaction_date" name="transaction_date" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
        </div>
        <div class="field"><label for="label">Libellé</label><input id="label" name="label" placeholder="Ex. Mariage Dupont" required></div>
        <div class="finance-form-grid">
          <div class="field"><label for="amount">Montant (€)</label><input id="amount" name="amount" inputmode="decimal" placeholder="0,00" required></div>
          <div class="field"><label for="category">Catégorie</label><input id="category" name="category" placeholder="Prestation, carburant…"></div>
        </div>
        <div class="field"><label for="quote_id">N° de demande de devis (facultatif)</label><input id="quote_id" name="quote_id" type="number" min="1"></div>
        <div class="field"><label for="notes">Note</label><textarea id="notes" name="notes" rows="3"></textarea></div>
        <button class="btn btn--primary" type="submit">Ajouter l’opération</button>
      </form>
    </section>

    <section class="form-card">
      <h2>Cotisations sociales</h2>
      <form method="post" class="finance-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_rate">
        <div class="field"><label for="social_rate">Taux appliqué au chiffre d’affaires (%)</label><input id="social_rate" name="social_rate" inputmode="decimal" value="<?= e((string)$rate) ?>"></div>
        <p class="finance-rate-note">Ce taux est volontairement modifiable. Le “net” affiché est une estimation de gestion interne : CA encaissé − cotisations estimées − dépenses enregistrées.</p>
        <button class="btn" type="submit">Enregistrer le taux</button>
      </form>
    </section>
  </aside>
</div>
<?php admin_footer(); ?>