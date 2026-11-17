<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();
$categories = ['prestation' => 'Prestations', 'formule' => 'Formules', 'option' => 'Options'];
$priceModes = ['fixed' => 'Prix fixe', 'from' => 'À partir de', 'hourly' => 'Par heure', 'unit' => 'À l’unité'];
$flash = '';
$error = '';

function catalog_money_to_cents(string $value): int
{
    $normalized = str_replace([' ', ','], ['', '.'], trim($value));
    if ($normalized === '' || !is_numeric($normalized)) return 0;
    return max(0, (int)round(((float)$normalized) * 100));
}
function catalog_cents_to_money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $category = (string)($_POST['category'] ?? '');
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priceMode = (string)($_POST['price_mode'] ?? 'fixed');
        $unitLabel = trim((string)($_POST['unit_label'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if (!array_key_exists($category, $categories)) {
            $error = 'Catégorie invalide.';
        } elseif ($name === '') {
            $error = 'Le nom est obligatoire.';
        } elseif (!array_key_exists($priceMode, $priceModes)) {
            $error = 'Mode de prix invalide.';
        } else {
            $price = catalog_money_to_cents((string)($_POST['price'] ?? '0'));

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE catalog_items SET
                       category=:category,
                       name=:name,
                       description=:description,
                       price_cents=:price,
                       price_mode=:price_mode,
                       unit_label=:unit_label,
                       active=:active,
                       display_order=:display_order,
                       updated_at=CURRENT_TIMESTAMP
                     WHERE id=:id'
                );
                $stmt->execute([
                    ':category'=>$category,
                    ':name'=>$name,
                    ':description'=>$description,
                    ':price'=>$price,
                    ':price_mode'=>$priceMode,
                    ':unit_label'=>$unitLabel,
                    ':active'=>$active,
                    ':display_order'=>$displayOrder,
                    ':id'=>$id,
                ]);
                header('Location: catalogue.php?saved=1#item-' . $id);
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO catalog_items(category,name,description,price_cents,price_mode,unit_label,active,display_order)
                 VALUES(:category,:name,:description,:price,:price_mode,:unit_label,:active,:display_order)'
            );
            $stmt->execute([
                ':category'=>$category,
                ':name'=>$name,
                ':description'=>$description,
                ':price'=>$price,
                ':price_mode'=>$priceMode,
                ':unit_label'=>$unitLabel,
                ':active'=>$active,
                ':display_order'=>$displayOrder,
            ]);
            header('Location: catalogue.php?created=1');
            exit;
        }
    }
}

$items = $pdo->query('SELECT * FROM catalog_items ORDER BY category, display_order, id')->fetchAll();
$grouped = ['prestation'=>[], 'formule'=>[], 'option'=>[]];
foreach ($items as $item) {
    if (isset($grouped[$item['category']])) $grouped[$item['category']][] = $item;
}

admin_header('Catalogue', 'catalogue');
if (isset($_GET['saved'])) $flash = 'Élément du catalogue enregistré.';
if (isset($_GET['created'])) $flash = 'Nouvel élément ajouté au catalogue.';
?>
<style>
.catalogue-intro{max-width:920px;margin:-6px 0 22px;color:#aaa6a0;font-size:13px;line-height:1.7}
.catalogue-private{display:flex;align-items:flex-start;gap:12px;max-width:920px;margin:0 0 26px;padding:13px 15px;border:1px solid rgba(227,34,40,.16);border-radius:14px;background:rgba(227,34,40,.045);color:#aaa6a0;font-size:11px;line-height:1.55}
.catalogue-private strong{color:#fff}.catalogue-private__dot{width:7px;height:7px;margin-top:5px;border-radius:50%;background:#e32228;box-shadow:0 0 11px rgba(227,34,40,.75);flex:0 0 auto}
.catalogue-layout{display:grid;gap:18px}
.catalogue-section{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:18px;background:linear-gradient(180deg,rgba(18,18,21,.96),rgba(12,12,15,.96));box-shadow:0 14px 38px rgba(0,0,0,.15)}
.catalogue-section::before{content:"";position:absolute;left:0;top:0;width:160px;height:1px;background:linear-gradient(90deg,#e32228,transparent);box-shadow:0 0 12px rgba(227,34,40,.28)}
.catalogue-section__head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px 18px;border-bottom:1px solid rgba(255,255,255,.055)}
.catalogue-section__head span{display:block;margin-bottom:3px;color:#ff4a50;font-size:8px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.catalogue-section__head h2{margin:0;color:#fff;font-size:19px;letter-spacing:-.02em}
.catalogue-count{display:grid;place-items:center;min-width:30px;height:30px;padding:0 9px;border-radius:999px;background:#0b0b0e;border:1px solid rgba(255,255,255,.075);color:#99948e;font-size:10px;font-weight:800}
.catalogue-list{display:grid}
.catalogue-item{border-bottom:1px solid rgba(255,255,255,.055);background:transparent}
.catalogue-item:last-child{border-bottom:0}
.catalogue-item[open]{background:rgba(255,255,255,.012)}
.catalogue-summary{list-style:none;display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:18px;align-items:center;padding:14px 18px;cursor:pointer}
.catalogue-summary::-webkit-details-marker{display:none}
.catalogue-summary:hover{background:rgba(255,255,255,.018)}
.catalogue-summary__main{min-width:0}.catalogue-summary__main strong{display:block;color:#fff;font-size:13px}.catalogue-summary__main span{display:block;margin-top:3px;color:#77726c;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.catalogue-summary__price{text-align:right}.catalogue-summary__price strong{display:block;color:#fff;font-size:15px}.catalogue-summary__price span{display:block;margin-top:2px;color:#77726c;font-size:8px}
.catalogue-summary__state{display:flex;align-items:center;gap:8px;color:#8c8781;font-size:9px;text-transform:uppercase;letter-spacing:.06em}.catalogue-summary__state::before{content:"";width:6px;height:6px;border-radius:50%;background:#65a95a;box-shadow:0 0 8px rgba(101,169,90,.35)}.catalogue-item.is-disabled .catalogue-summary__state::before{background:#666;box-shadow:none}.catalogue-item.is-disabled{opacity:.58}
.catalogue-edit{padding:2px 18px 16px 18px}
.catalogue-form{display:grid;grid-template-columns:1fr 160px 170px 1fr;gap:11px;padding:14px;border:1px solid rgba(255,255,255,.06);border-radius:14px;background:#0a0a0d}
.catalogue-field{display:grid;gap:5px}.catalogue-field--full{grid-column:1/-1}
.catalogue-field label{color:#706b65;font-size:8px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
.catalogue-field input,.catalogue-field textarea,.catalogue-field select{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.075);border-radius:9px;background:#070709;color:#fff;padding:9px 10px;font:inherit}
.catalogue-field textarea{min-height:64px;resize:vertical}.catalogue-field input:focus,.catalogue-field textarea:focus,.catalogue-field select:focus{outline:none;border-color:rgba(227,34,40,.5);box-shadow:0 0 0 3px rgba(227,34,40,.06)}
.catalogue-actions{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px}
.catalogue-active{display:flex;align-items:center;gap:8px;color:#99948e;font-size:9px}.catalogue-active input{accent-color:#e32228}
.catalogue-add{padding:14px 18px;border-top:1px dashed rgba(255,255,255,.07);background:rgba(255,255,255,.008)}
.catalogue-add details{border:1px dashed rgba(255,255,255,.09);border-radius:13px}.catalogue-add summary{padding:12px 14px;cursor:pointer;color:#aaa6a0;font-size:11px;font-weight:700}.catalogue-add summary:hover{color:#fff}.catalogue-add__body{padding:0 14px 14px}
@media(max-width:1000px){.catalogue-form{grid-template-columns:1fr 1fr}.catalogue-summary{grid-template-columns:minmax(0,1fr) auto}.catalogue-summary__state{display:none}}
@media(max-width:680px){.catalogue-form{grid-template-columns:1fr}.catalogue-field--full,.catalogue-actions{grid-column:auto}.catalogue-actions{align-items:flex-start;flex-direction:column}.catalogue-summary{grid-template-columns:1fr}.catalogue-summary__price{text-align:left}}
</style>

<?php if ($flash): ?><div class="flash flash--success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<p class="catalogue-intro">
  Fixe ici les tarifs de référence utilisés pour tes futurs devis. Un prix pourra ensuite être adapté ponctuellement dans un devis sans modifier le tarif du catalogue.
</p>
<div class="catalogue-private">
  <span class="catalogue-private__dot"></span>
  <div><strong>Tarifs internes uniquement.</strong> Les prix enregistrés ici servent à la création de tes devis dans l’administration et ne sont jamais affichés sur le site public.</div>
</div>

<div class="catalogue-layout">
<?php foreach ($categories as $categoryKey => $categoryLabel): ?>
  <section class="catalogue-section">
    <div class="catalogue-section__head">
      <div><span>Catalogue Luka C</span><h2><?= e($categoryLabel) ?></h2></div>
      <div class="catalogue-count"><?= count($grouped[$categoryKey]) ?></div>
    </div>

    <div class="catalogue-list">
      <?php foreach ($grouped[$categoryKey] as $item): ?>
        <details class="catalogue-item <?= !$item['active'] ? 'is-disabled' : '' ?>" id="item-<?= (int)$item['id'] ?>">
          <summary class="catalogue-summary">
            <div class="catalogue-summary__main">
              <strong><?= e($item['name']) ?></strong>
              <span><?= e($item['description'] ?: 'Aucune description.') ?></span>
            </div>
            <div class="catalogue-summary__price">
              <strong><?= e(catalog_cents_to_money((int)$item['price_cents'])) ?> €</strong>
              <span><?= e($priceModes[$item['price_mode']] ?? $item['price_mode']) ?><?= $item['unit_label'] ? ' · ' . e($item['unit_label']) : '' ?></span>
            </div>
            <div class="catalogue-summary__state"><?= $item['active'] ? 'Actif' : 'Inactif' ?></div>
          </summary>

          <div class="catalogue-edit">
            <form method="post" class="catalogue-form">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="category" value="<?= e($categoryKey) ?>">

              <div class="catalogue-field"><label>Nom</label><input name="name" value="<?= e($item['name']) ?>" required></div>
              <div class="catalogue-field"><label>Prix (€)</label><input name="price" inputmode="decimal" value="<?= e(catalog_cents_to_money((int)$item['price_cents'])) ?>"></div>
              <div class="catalogue-field"><label>Mode de prix</label><select name="price_mode"><?php foreach ($priceModes as $mode => $modeLabel): ?><option value="<?= e($mode) ?>" <?= $item['price_mode'] === $mode ? 'selected' : '' ?>><?= e($modeLabel) ?></option><?php endforeach; ?></select></div>
              <div class="catalogue-field"><label>Unité / précision</label><input name="unit_label" value="<?= e((string)$item['unit_label']) ?>" placeholder="Ex. Par prestation"></div>
              <div class="catalogue-field catalogue-field--full"><label>Description</label><textarea name="description"><?= e((string)$item['description']) ?></textarea></div>
              <div class="catalogue-field"><label>Ordre</label><input name="display_order" type="number" value="<?= (int)$item['display_order'] ?>"></div>

              <div class="catalogue-actions">
                <label class="catalogue-active"><input type="checkbox" name="active" value="1" <?= $item['active'] ? 'checked' : '' ?>> Actif dans le catalogue</label>
                <button class="btn btn--primary" type="submit">Enregistrer</button>
              </div>
            </form>
          </div>
        </details>
      <?php endforeach; ?>
    </div>

    <div class="catalogue-add">
      <details>
        <summary>＋ Ajouter un élément</summary>
        <div class="catalogue-add__body">
          <form method="post" class="catalogue-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">
            <input type="hidden" name="category" value="<?= e($categoryKey) ?>">

            <div class="catalogue-field"><label>Nom</label><input name="name" required placeholder="Nom"></div>
            <div class="catalogue-field"><label>Prix (€)</label><input name="price" inputmode="decimal" value="0,00"></div>
            <div class="catalogue-field"><label>Mode de prix</label><select name="price_mode"><?php foreach ($priceModes as $mode => $modeLabel): ?><option value="<?= e($mode) ?>"><?= e($modeLabel) ?></option><?php endforeach; ?></select></div>
            <div class="catalogue-field"><label>Unité / précision</label><input name="unit_label" placeholder="Ex. Par prestation"></div>
            <div class="catalogue-field catalogue-field--full"><label>Description</label><textarea name="description"></textarea></div>
            <div class="catalogue-field"><label>Ordre</label><input name="display_order" type="number" value="<?= count($grouped[$categoryKey]) * 10 + 10 ?>"></div>

            <div class="catalogue-actions">
              <label class="catalogue-active"><input type="checkbox" name="active" value="1" checked> Actif dans le catalogue</label>
              <button class="btn btn--primary" type="submit">Ajouter</button>
            </div>
          </form>
        </div>
      </details>
    </div>
  </section>
<?php endforeach; ?>
</div>

<?php admin_footer(); ?>
