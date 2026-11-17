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
.catalogue-intro{max-width:860px;margin:-6px 0 24px;color:#aaa6a0;font-size:13px;line-height:1.7}
.catalogue-layout{display:grid;gap:22px}
.catalogue-section{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:20px;background:linear-gradient(180deg,rgba(18,18,21,.96),rgba(12,12,15,.96));box-shadow:0 18px 48px rgba(0,0,0,.18)}
.catalogue-section::before{content:"";position:absolute;left:0;top:0;width:180px;height:1px;background:linear-gradient(90deg,#e32228,transparent);box-shadow:0 0 14px rgba(227,34,40,.32)}
.catalogue-section__head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.06)}
.catalogue-section__head span{display:block;margin-bottom:5px;color:#ff4a50;font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.catalogue-section__head h2{margin:0;color:#fff;font-size:21px;letter-spacing:-.025em}
.catalogue-count{display:grid;place-items:center;min-width:34px;height:34px;padding:0 10px;border-radius:999px;background:#0b0b0e;border:1px solid rgba(255,255,255,.08);color:#aaa6a0;font-size:11px;font-weight:800}
.catalogue-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;padding:18px}
.catalogue-card{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.07);border-radius:16px;background:linear-gradient(180deg,#101013,#0c0c0f)}
.catalogue-card.is-disabled{opacity:.58}
.catalogue-card__top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:16px 16px 10px}
.catalogue-card__top h3{margin:0;color:#fff;font-size:16px}
.catalogue-card__top p{margin:5px 0 0;color:#77726c;font-size:11px;line-height:1.55}
.catalogue-price{flex:0 0 auto;text-align:right}
.catalogue-price strong{display:block;color:#fff;font-size:20px;letter-spacing:-.03em}
.catalogue-price span{display:block;margin-top:3px;color:#8f8a84;font-size:9px}
.catalogue-form{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:0 16px 16px}
.catalogue-field{display:grid;gap:6px}
.catalogue-field--full{grid-column:1/-1}
.catalogue-field label{color:#77726c;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.catalogue-field input,.catalogue-field textarea,.catalogue-field select{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:#09090b;color:#fff;padding:10px 11px;font:inherit}
.catalogue-field textarea{min-height:78px;resize:vertical}
.catalogue-field input:focus,.catalogue-field textarea:focus,.catalogue-field select:focus{outline:none;border-color:rgba(227,34,40,.55);box-shadow:0 0 0 3px rgba(227,34,40,.07)}
.catalogue-actions{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:2px}
.catalogue-active{display:flex;align-items:center;gap:8px;color:#aaa6a0;font-size:10px}.catalogue-active input{accent-color:#e32228}
.catalogue-add{border-style:dashed;background:rgba(255,255,255,.012)}
.catalogue-empty{padding:20px;color:#817c77;font-size:12px}
@media(max-width:1050px){.catalogue-grid{grid-template-columns:1fr}}
@media(max-width:680px){.catalogue-form{grid-template-columns:1fr}.catalogue-field--full{grid-column:auto}.catalogue-actions{align-items:flex-start;flex-direction:column}}
</style>

<?php if ($flash): ?><div class="flash flash--success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<p class="catalogue-intro">
  Fixe ici les tarifs de référence utilisés pour tes futurs devis. Un prix pourra ensuite être adapté ponctuellement dans un devis sans modifier le tarif du catalogue.
</p>

<div class="catalogue-layout">
<?php foreach ($categories as $categoryKey => $categoryLabel): ?>
  <section class="catalogue-section">
    <div class="catalogue-section__head">
      <div><span>Catalogue Luka C</span><h2><?= e($categoryLabel) ?></h2></div>
      <div class="catalogue-count"><?= count($grouped[$categoryKey]) ?></div>
    </div>

    <div class="catalogue-grid">
      <?php foreach ($grouped[$categoryKey] as $item): ?>
        <article class="catalogue-card <?= !$item['active'] ? 'is-disabled' : '' ?>" id="item-<?= (int)$item['id'] ?>">
          <div class="catalogue-card__top">
            <div>
              <h3><?= e($item['name']) ?></h3>
              <p><?= e($item['description'] ?: 'Aucune description.') ?></p>
            </div>
            <div class="catalogue-price">
              <strong><?= e(catalog_cents_to_money((int)$item['price_cents'])) ?> €</strong>
              <span><?= e($priceModes[$item['price_mode']] ?? $item['price_mode']) ?></span>
            </div>
          </div>

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
        </article>
      <?php endforeach; ?>

      <article class="catalogue-card catalogue-add">
        <div class="catalogue-card__top">
          <div><h3>Ajouter un élément</h3><p>Crée une nouvelle <?= strtolower(e(rtrim($categoryLabel, 's'))) ?> personnalisée.</p></div>
        </div>
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
      </article>
    </div>
  </section>
<?php endforeach; ?>
</div>

<?php admin_footer(); ?>
