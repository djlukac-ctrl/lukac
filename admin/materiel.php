<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();

$categories = ['DJ', 'Sonorisation', 'Éclairage', 'Effets', 'Photobooth', 'Vidéo', 'Informatique', 'Câblage', 'Transport', 'Autre'];
$conditions = [
    'excellent' => 'Excellent',
    'good' => 'Bon',
    'watch' => 'À surveiller',
    'replace' => 'À remplacer',
];

function equipment_money_to_cents(string $value): int
{
    $normalized = str_replace([' ', ','], ['', '.'], trim($value));
    if ($normalized === '' || !is_numeric($normalized)) return 0;
    return max(0, (int) round(((float) $normalized) * 100));
}

function equipment_money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ');
}

function equipment_age_years(?string $purchaseDate): float
{
    if (!$purchaseDate) return 0.0;
    try {
        $purchase = new DateTimeImmutable($purchaseDate);
        $today = new DateTimeImmutable('today');
        if ($purchase > $today) return 0.0;
        return max(0.0, ($today->getTimestamp() - $purchase->getTimestamp()) / 31557600);
    } catch (Throwable $e) {
        return 0.0;
    }
}

function equipment_auto_current_value(int $purchasePriceCents, ?string $purchaseDate, string $condition): int
{
    if ($purchasePriceCents <= 0) return 0;

    $age = equipment_age_years($purchaseDate);
    if (!$purchaseDate) {
        $ratio = 0.65;
    } elseif ($age < 1) {
        $ratio = 0.80;
    } elseif ($age < 2) {
        $ratio = 0.65;
    } elseif ($age < 3) {
        $ratio = 0.50;
    } elseif ($age < 5) {
        $ratio = 0.35;
    } else {
        $ratio = 0.20;
    }

    $conditionMultiplier = [
        'excellent' => 1.10,
        'good' => 1.00,
        'watch' => 0.85,
        'replace' => 0.65,
    ][$condition] ?? 1.00;

    return min($purchasePriceCents, max(0, (int) round($purchasePriceCents * $ratio * $conditionMultiplier)));
}

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'Autre'));
        $purchaseDate = trim((string) ($_POST['purchase_date'] ?? ''));
        $purchasePrice = equipment_money_to_cents((string) ($_POST['purchase_price'] ?? '0'));
        $currentValue = equipment_money_to_cents((string) ($_POST['current_value'] ?? '0'));
        $quantity = max(1, min(999, (int)($_POST['quantity'] ?? 1)));
        $condition = (string) ($_POST['condition_status'] ?? 'good');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            $error = 'Le nom du matériel est obligatoire.';
        } elseif (!in_array($category, $categories, true)) {
            $error = 'Catégorie invalide.';
        } elseif (!isset($conditions[$condition])) {
            $error = 'État invalide.';
        } elseif ($purchaseDate !== '' && !DateTimeImmutable::createFromFormat('!Y-m-d', $purchaseDate)) {
            $error = 'Date d’achat invalide.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE equipment SET
                        name=:name, category=:category, purchase_date=:purchase_date,
                        purchase_price_cents=:purchase_price, current_value_cents=:current_value,
                        quantity=:quantity, condition_status=:condition_status,
                        notes=:notes, active=:active, updated_at=CURRENT_TIMESTAMP
                     WHERE id=:id'
                );
                $stmt->execute([
                    ':name'=>$name,
                    ':category'=>$category,
                    ':purchase_date'=>$purchaseDate ?: null,
                    ':purchase_price'=>$purchasePrice,
                    ':current_value'=>$currentValue,
                    ':quantity'=>$quantity,
                    ':condition_status'=>$condition,
                    ':notes'=>$notes,
                    ':active'=>$active,
                    ':id'=>$id,
                ]);
                header('Location: materiel.php?saved=1#item-' . $id);
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO equipment(
                    name, category, purchase_date, purchase_price_cents, current_value_cents,
                    quantity, condition_status, notes, active
                 ) VALUES(
                    :name, :category, :purchase_date, :purchase_price, :current_value,
                    :quantity, :condition_status, :notes, :active
                 )'
            );
            $stmt->execute([
                ':name'=>$name,
                ':category'=>$category,
                ':purchase_date'=>$purchaseDate ?: null,
                ':purchase_price'=>$purchasePrice,
                ':current_value'=>$currentValue,
                ':quantity'=>$quantity,
                ':condition_status'=>$condition,
                ':notes'=>$notes,
                ':active'=>$active,
            ]);
            header('Location: materiel.php?created=1');
            exit;
        }
    }

    if ($action === 'save_formula_equipment') {
        $formula = (string) ($_POST['formula'] ?? '');
        $allowedFormulas = ['Essentiel', 'Ambiance', 'Expérience'];
        if (!in_array($formula, $allowedFormulas, true)) {
            $error = 'Formule invalide.';
        } else {
            $quantities = $_POST['equipment_qty'] ?? [];
            if (!is_array($quantities)) $quantities = [];

            $pdo->beginTransaction();
            try {
                $delete = $pdo->prepare('DELETE FROM formula_equipment WHERE formula_name=:formula');
                $delete->execute([':formula'=>$formula]);

                $check = $pdo->prepare('SELECT quantity FROM equipment WHERE id=:id AND active=1 LIMIT 1');
                $insert = $pdo->prepare('INSERT INTO formula_equipment(formula_name, equipment_id, quantity) VALUES(:formula,:equipment,:quantity)');
                foreach ($quantities as $equipmentIdRaw => $quantityRaw) {
                    $equipmentId = (int)$equipmentIdRaw;
                    $requestedQuantity = max(0, (int)$quantityRaw);
                    if ($equipmentId <= 0 || $requestedQuantity <= 0) continue;

                    $check->execute([':id'=>$equipmentId]);
                    $ownedQuantity = (int)($check->fetchColumn() ?: 0);
                    if ($ownedQuantity <= 0) continue;

                    $usedQuantity = min($requestedQuantity, $ownedQuantity);
                    $insert->execute([
                        ':formula'=>$formula,
                        ':equipment'=>$equipmentId,
                        ':quantity'=>$usedQuantity,
                    ]);
                }

                sync_formula_prices_from_equipment($pdo);
                $pdo->commit();
                header('Location: materiel.php?formula_saved=1#formules');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM equipment WHERE id=:id');
            $stmt->execute([':id'=>$id]);
        }
        header('Location: materiel.php?deleted=1');
        exit;
    }
}

$items = $pdo->query('SELECT * FROM equipment ORDER BY active DESC, category ASC, name ASC')->fetchAll();
$formulaNames = ['Essentiel', 'Ambiance', 'Expérience'];
$formulaLinks = ['Essentiel'=>[], 'Ambiance'=>[], 'Expérience'=>[]];
$linkRows = $pdo->query('SELECT formula_name, equipment_id, quantity FROM formula_equipment')->fetchAll();
foreach ($linkRows as $linkRow) {
    if (isset($formulaLinks[$linkRow['formula_name']])) {
        $formulaLinks[$linkRow['formula_name']][(int)$linkRow['equipment_id']] = max(1, (int)$linkRow['quantity']);
    }
}

$formulaPrices = [];
$formulaPurchaseTotals = [];
foreach ($formulaNames as $formulaName) {
    $sum = 0;
    foreach ($items as $equipmentItem) {
        if ((int)$equipmentItem['active'] !== 1) continue;
        $usedQuantity = (int)($formulaLinks[$formulaName][(int)$equipmentItem['id']] ?? 0);
        if ($usedQuantity <= 0) continue;
        $purchasePrice = (int)$equipmentItem['purchase_price_cents'];
        $sum += $purchasePrice * $usedQuantity;
        $rate = $equipmentItem['category'] === 'Autre' ? 0.02 : 0.05;
        $formulaPrices[$formulaName] = ($formulaPrices[$formulaName] ?? 0) + (int) round($purchasePrice * $usedQuantity * $rate);
    }
    $formulaPurchaseTotals[$formulaName] = $sum;
    $formulaPrices[$formulaName] = $formulaPrices[$formulaName] ?? 0;
}


$totalPurchase = 0;
$totalCurrent = 0;
$activeCount = 0;
$watchCount = 0;

foreach ($items as &$item) {
    $purchase = (int) $item['purchase_price_cents'];
    $ownedQuantity = max(1, (int)($item['quantity'] ?? 1));
    $manualCurrent = (int) $item['current_value_cents'];
    $autoCurrent = equipment_auto_current_value($purchase, $item['purchase_date'] ?? null, (string)$item['condition_status']);
    $current = $manualCurrent > 0 ? $manualCurrent : $autoCurrent;
    $item['_current_value_cents'] = $current;
    $item['_current_value_is_manual'] = $manualCurrent > 0;
    $pricingRate = $item['category'] === 'Autre' ? 0.02 : 0.05;
    $item['_pricing_rate'] = $pricingRate;
    $item['_pricing_base_cents'] = (int) round($purchase * $ownedQuantity * $pricingRate);

    if ((int) $item['active'] === 1) {
        $activeCount++;
        $totalPurchase += $purchase * $ownedQuantity;
        $totalCurrent += $current * $ownedQuantity;
        if (in_array($item['condition_status'], ['watch', 'replace'], true)) $watchCount++;
    }
}
unset($item);


admin_header('Matériel', 'materiel');

if (isset($_GET['saved'])) $flash = 'Matériel mis à jour.';
if (isset($_GET['created'])) $flash = 'Matériel ajouté.';
if (isset($_GET['deleted'])) $flash = 'Matériel supprimé.';
if (isset($_GET['formula_saved'])) $flash = 'Matériel de la formule enregistré et tarif recalculé.';
?>
<style>
.equipment-intro{max-width:900px;margin:-6px 0 22px;color:#aaa6a0;font-size:13px;line-height:1.7}
.equipment-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}
.equipment-metric{padding:18px;border:1px solid rgba(255,255,255,.08);border-radius:17px;background:linear-gradient(180deg,rgba(18,18,21,.98),rgba(10,10,13,.98));box-shadow:0 14px 32px rgba(0,0,0,.13)}
.equipment-metric span{display:block;color:#77726c;font-size:8px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.equipment-metric strong{display:block;margin-top:8px;color:#fff;font-size:24px;letter-spacing:-.03em}.equipment-metric small{display:block;margin-top:5px;color:#77726c;font-size:9px;line-height:1.4}
.equipment-metric--accent{border-color:rgba(227,34,40,.2);background:linear-gradient(180deg,rgba(35,14,17,.92),rgba(12,10,12,.98))}
.equipment-metric--accent strong{color:#ff4a50}
.equipment-layout{display:grid;gap:14px}
.equipment-section{overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:18px;background:linear-gradient(180deg,rgba(18,18,21,.96),rgba(12,12,15,.96))}
.equipment-section__head{display:flex;align-items:center;justify-content:space-between;padding:15px 18px;border-bottom:1px solid rgba(255,255,255,.055)}
.equipment-section__head h2{margin:0;color:#fff;font-size:18px}.equipment-section__head span{color:#77726c;font-size:9px}
.equipment-item{border-bottom:1px solid rgba(255,255,255,.055)}.equipment-item:last-child{border-bottom:0}.equipment-item.is-disabled{opacity:.55}
.equipment-summary{list-style:none;display:grid;grid-template-columns:minmax(0,1.3fr) .75fr .8fr .8fr auto;align-items:center;gap:14px;padding:14px 18px;cursor:pointer}.equipment-summary::-webkit-details-marker{display:none}.equipment-summary:hover{background:rgba(255,255,255,.015)}
.equipment-name strong{display:block;color:#fff;font-size:13px}.equipment-name span{display:block;margin-top:3px;color:#77726c;font-size:9px}
.equipment-value span{display:block;color:#69645f;font-size:8px;text-transform:uppercase;letter-spacing:.06em}.equipment-value strong{display:block;margin-top:3px;color:#ddd9d3;font-size:12px}.equipment-value--pricing span{color:#d65a5f}.equipment-value--pricing strong{color:#ff5b61;font-size:14px}
.equipment-state{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);color:#aaa6a0;font-size:8px;white-space:nowrap}
.equipment-state--watch,.equipment-state--replace{border-color:rgba(227,34,40,.25);color:#ff696d;background:rgba(227,34,40,.06)}
.equipment-edit{padding:2px 18px 17px}.equipment-form{display:grid;grid-template-columns:1.2fr .8fr .8fr .8fr .7fr .8fr;gap:11px;padding:14px;border:1px solid rgba(255,255,255,.06);border-radius:14px;background:#08080b}
.equipment-field{display:grid;gap:5px}.equipment-field--full{grid-column:1/-1}.equipment-field label{color:#706b65;font-size:8px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
.equipment-field input,.equipment-field select,.equipment-field textarea{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.075);border-radius:9px;background:#050507;color:#fff;padding:9px 10px;font:inherit}.equipment-field textarea{min-height:68px;resize:vertical}
.equipment-actions{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px}.equipment-active{display:flex;align-items:center;gap:8px;color:#99948e;font-size:9px}.equipment-active input{accent-color:#e32228}
.equipment-actions__buttons{display:flex;gap:8px}.equipment-delete{border:1px solid rgba(227,34,40,.18);border-radius:9px;background:transparent;color:#d76a6d;padding:9px 11px;cursor:pointer}
.equipment-add{padding:14px 18px;border-top:1px dashed rgba(255,255,255,.07)}.equipment-add details{border:1px dashed rgba(255,255,255,.09);border-radius:13px}.equipment-add summary{padding:12px 14px;cursor:pointer;color:#aaa6a0;font-size:11px;font-weight:700}.equipment-add__body{padding:0 14px 14px}
.equipment-empty{padding:28px 18px;text-align:center;color:#77726c;font-size:11px}
.formula-builder{margin:0 0 20px;border:1px solid rgba(255,255,255,.08);border-radius:18px;background:linear-gradient(180deg,rgba(18,18,21,.96),rgba(12,12,15,.96));overflow:hidden}
.formula-builder__head{padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.055)}.formula-builder__head span{display:block;color:#ff4a50;font-size:8px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.formula-builder__head h2{margin:4px 0 0;color:#fff;font-size:18px}.formula-builder__head p{margin:6px 0 0;color:#77726c;font-size:10px;line-height:1.5}
.formula-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0}
.formula-card{padding:17px;border-right:1px solid rgba(255,255,255,.055)}.formula-card:last-child{border-right:0}.formula-card__top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:13px}.formula-card__top h3{margin:0;color:#fff;font-size:15px}.formula-card__price{text-align:right}.formula-card__price strong{display:block;color:#ff5b61;font-size:21px}.formula-card__price span{display:block;margin-top:2px;color:#6f6964;font-size:8px}
.formula-card__equipment{display:grid;gap:7px;max-height:280px;overflow:auto;padding-right:3px}.formula-equipment-option{display:flex;align-items:flex-start;gap:9px;padding:8px 9px;border:1px solid rgba(255,255,255,.06);border-radius:10px;background:#09090c;color:#aaa6a0;font-size:9px;line-height:1.35}.formula-equipment-option input{margin-top:2px;accent-color:#e32228}.formula-equipment-option__qty{width:58px!important;flex:0 0 58px;margin:0!important;padding:6px 7px!important;text-align:center}.formula-equipment-option strong{display:block;color:#e8e4df;font-size:10px}.formula-equipment-option small{display:block;margin-top:2px;color:#6f6964;font-size:8px}
.formula-card__footer{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:13px;padding-top:12px;border-top:1px solid rgba(255,255,255,.05)}.formula-card__footer span{color:#77726c;font-size:8px}
@media(max-width:1050px){.formula-grid{grid-template-columns:1fr}.formula-card{border-right:0;border-bottom:1px solid rgba(255,255,255,.055)}.formula-card:last-child{border-bottom:0}}
@media(max-width:1250px){.equipment-metrics{grid-template-columns:repeat(2,1fr)}}
@media(max-width:1150px){.equipment-summary{grid-template-columns:minmax(0,1fr) repeat(2,.7fr) auto}.equipment-summary .equipment-value:nth-of-type(3){display:none}.equipment-form{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.equipment-metrics{grid-template-columns:1fr}.equipment-summary{grid-template-columns:1fr auto}.equipment-summary .equipment-value{display:none}.equipment-form{grid-template-columns:1fr}.equipment-field--full,.equipment-actions{grid-column:auto}.equipment-actions{align-items:flex-start;flex-direction:column}}
</style>

<?php if ($flash): ?><div class="flash flash--success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<p class="equipment-intro">
  Suis ici ton matériel article par article. L’administration calcule automatiquement la base tarifaire : 5 % du prix d’achat pour le matériel standard, et 2 % pour la catégorie « Autre » correspondant au confort personnel. La valeur actuelle est estimée automatiquement à partir de l’âge et de l’état, avec possibilité de la corriger manuellement.
</p>

<div class="equipment-metrics">
  <div class="equipment-metric">
    <span>Matériel actif</span>
    <strong><?= $activeCount ?></strong>
    <small>Nombre d’articles actuellement inclus dans tes calculs.</small>
  </div>
  <div class="equipment-metric">
    <span>Valeur actuelle estimée</span>
    <strong><?= e(equipment_money($totalCurrent)) ?> €</strong>
    <small>Estimation automatique selon l’âge et l’état, sauf valeur manuelle renseignée.</small>
  </div>
  <div class="equipment-metric equipment-metric--accent">
    <span>Calcul tarifaire</span>
    <strong>5 % / article</strong>
    <small>5 % sur le matériel visible/client, 2 % sur la catégorie « Autre » dédiée au confort personnel.</small>
  </div>
</div>

<section class="formula-builder" id="formules">
  <div class="formula-builder__head">
    <span>Tarification automatique</span>
    <h2>Matériel utilisé dans les formules</h2>
    <p>Indique la quantité utilisée de chaque matériel dans chaque formule. Le calcul applique 5 % sur le matériel standard et 2 % sur les articles classés « Autre ».</p>
  </div>
  <div class="formula-grid">
    <?php foreach ($formulaNames as $formulaName): ?>
      <form method="post" class="formula-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_formula_equipment">
        <input type="hidden" name="formula" value="<?= e($formulaName) ?>">

        <div class="formula-card__top">
          <h3><?= e($formulaName) ?></h3>
          <div class="formula-card__price">
            <strong><?= e(equipment_money($formulaPrices[$formulaName])) ?> €</strong>
            <span>Calcul selon le taux de chaque article sélectionné</span>
          </div>
        </div>

        <div class="formula-card__equipment">
          <?php $hasActiveEquipment = false; ?>
          <?php foreach ($items as $equipmentItem): ?>
            <?php if (!(int)$equipmentItem['active']) continue; $hasActiveEquipment = true; ?>
            <label class="formula-equipment-option">
              <?php
                $equipmentId = (int)$equipmentItem['id'];
                $ownedQuantity = max(1, (int)($equipmentItem['quantity'] ?? 1));
                $usedQuantity = (int)($formulaLinks[$formulaName][$equipmentId] ?? 0);
                $formulaRate = $equipmentItem['category'] === 'Autre' ? .02 : .05;
              ?>
              <input class="formula-equipment-option__qty" type="number" name="equipment_qty[<?= $equipmentId ?>]" min="0" max="<?= $ownedQuantity ?>" value="<?= $usedQuantity ?>">
              <span>
                <strong><?= e($equipmentItem['name']) ?> · <?= $ownedQuantity ?> possédé<?= $ownedQuantity > 1 ? 's' : '' ?></strong>
                <small><?= e($equipmentItem['category']) ?> · <?= e(equipment_money((int)$equipmentItem['purchase_price_cents'])) ?> € / unité · base <?= $equipmentItem['category'] === 'Autre' ? '2 %' : '5 %' ?> : <?= e(equipment_money((int)round((int)$equipmentItem['purchase_price_cents'] * $formulaRate))) ?> € / unité</small>
              </span>
            </label>
          <?php endforeach; ?>
          <?php if (!$hasActiveEquipment): ?><div class="equipment-empty">Ajoute d’abord du matériel actif.</div><?php endif; ?>
        </div>

        <div class="formula-card__footer">
          <?php $formulaUnitCount = array_sum($formulaLinks[$formulaName]); ?>
          <span><?= $formulaUnitCount ?> unité<?= $formulaUnitCount > 1 ? 's' : '' ?> utilisée<?= $formulaUnitCount > 1 ? 's' : '' ?></span>
          <button class="btn btn--primary" type="submit">Enregistrer la formule</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
</section>

<div class="equipment-layout">
  <section class="equipment-section">
    <div class="equipment-section__head">
      <h2>Inventaire</h2>
      <span><?= count($items) ?> élément<?= count($items) > 1 ? 's' : '' ?> · <?= $watchCount ?> à surveiller / remplacer</span>
    </div>

    <?php if (!$items): ?>
      <div class="equipment-empty">Aucun matériel enregistré pour le moment.</div>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>
      <details class="equipment-item <?= !(int)$item['active'] ? 'is-disabled' : '' ?>" id="item-<?= (int)$item['id'] ?>">
        <summary class="equipment-summary">
          <div class="equipment-name">
            <strong><?= e($item['name']) ?><?= (int)($item['quantity'] ?? 1) > 1 ? ' · ×' . (int)$item['quantity'] : '' ?></strong>
            <span><?= e($item['category']) ?><?= $item['purchase_date'] ? ' · acheté le ' . e((new DateTimeImmutable($item['purchase_date']))->format('d/m/Y')) : '' ?></span>
          </div>
          <div class="equipment-value"><span>Achat / unité</span><strong><?= e(equipment_money((int)$item['purchase_price_cents'])) ?> €</strong></div>
          <div class="equipment-value equipment-value--pricing"><span>Base tarifaire · <?= $item['category'] === 'Autre' ? '2 %' : '5 %' ?></span><strong><?= e(equipment_money((int)$item['_pricing_base_cents'])) ?> €</strong></div>
          <div class="equipment-value"><span>Valeur actuelle totale<?= $item['_current_value_is_manual'] ? ' · manuelle' : ' · auto' ?></span><strong><?= e(equipment_money((int)$item['_current_value_cents'] * max(1,(int)($item['quantity'] ?? 1)))) ?> €</strong></div>
          <div class="equipment-state <?= in_array($item['condition_status'], ['watch','replace'], true) ? 'equipment-state--' . e($item['condition_status']) : '' ?>"><?= e($conditions[$item['condition_status']] ?? $item['condition_status']) ?></div>
        </summary>

        <div class="equipment-edit">
          <form method="post" class="equipment-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

            <div class="equipment-field"><label>Nom</label><input name="name" value="<?= e($item['name']) ?>" required></div>
            <div class="equipment-field"><label>Catégorie</label><select name="category"><?php foreach ($categories as $category): ?><option <?= $item['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></div>
            <div class="equipment-field"><label>Date d’achat</label><input name="purchase_date" type="date" value="<?= e((string)$item['purchase_date']) ?>"></div>
            <div class="equipment-field"><label>Prix d’achat / unité (€)</label><input name="purchase_price" inputmode="decimal" value="<?= e(equipment_money((int)$item['purchase_price_cents'])) ?>"></div>
            <div class="equipment-field"><label>Quantité possédée</label><input name="quantity" type="number" min="1" max="999" value="<?= max(1,(int)($item['quantity'] ?? 1)) ?>"></div>
            <div class="equipment-field"><label>Valeur actuelle manuelle / unité (€) · facultatif</label><input name="current_value" inputmode="decimal" value="<?= (int)$item['current_value_cents'] > 0 ? e(equipment_money((int)$item['current_value_cents'])) : '' ?>" placeholder="Laisser vide = calcul automatique"></div>
            <div class="equipment-field"><label>État</label><select name="condition_status"><?php foreach ($conditions as $key=>$label): ?><option value="<?= e($key) ?>" <?= $item['condition_status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="equipment-field equipment-field--full"><label>Notes</label><textarea name="notes" placeholder="Référence, entretien, accessoires, prochain remplacement…"><?= e((string)$item['notes']) ?></textarea></div>

            <div class="equipment-actions">
              <label class="equipment-active"><input type="checkbox" name="active" value="1" <?= (int)$item['active'] ? 'checked' : '' ?>> Inclus dans les calculs</label>
              <div class="equipment-actions__buttons">
                <button class="btn btn--primary" type="submit">Enregistrer</button>
              </div>
            </div>
          </form>
          <form method="post" style="margin-top:8px" onsubmit="return confirm('Supprimer définitivement ce matériel ?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <button class="equipment-delete" type="submit">Supprimer</button>
          </form>
        </div>
      </details>
    <?php endforeach; ?>

    <div class="equipment-add">
      <details>
        <summary>＋ Ajouter du matériel</summary>
        <div class="equipment-add__body">
          <form method="post" class="equipment-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">

            <div class="equipment-field"><label>Nom</label><input name="name" required placeholder="Ex. Pioneer XDJ-RX3"></div>
            <div class="equipment-field"><label>Catégorie</label><select name="category"><?php foreach ($categories as $category): ?><option><?= e($category) ?></option><?php endforeach; ?></select></div>
            <div class="equipment-field"><label>Date d’achat</label><input name="purchase_date" type="date"></div>
            <div class="equipment-field"><label>Prix d’achat / unité (€)</label><input name="purchase_price" inputmode="decimal" value="0,00"></div>
            <div class="equipment-field"><label>Quantité possédée</label><input name="quantity" type="number" min="1" max="999" value="1"></div>
            <div class="equipment-field"><label>Valeur actuelle manuelle / unité (€) · facultatif</label><input name="current_value" inputmode="decimal" placeholder="Laisser vide = calcul automatique"></div>
            <div class="equipment-field"><label>État</label><select name="condition_status"><?php foreach ($conditions as $key=>$label): ?><option value="<?= e($key) ?>" <?= $key === 'good' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="equipment-field equipment-field--full"><label>Notes</label><textarea name="notes" placeholder="Référence, entretien, accessoires…"></textarea></div>

            <div class="equipment-actions">
              <label class="equipment-active"><input type="checkbox" name="active" value="1" checked> Inclus dans les calculs</label>
              <button class="btn btn--primary" type="submit">Ajouter</button>
            </div>
          </form>
        </div>
      </details>
    </div>
  </section>
</div>

<?php admin_footer(); ?>
