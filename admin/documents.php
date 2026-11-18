<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();
$statuses = ['draft','sent','accepted','refused','expired'];
$flash = '';
$error = '';

function money_to_cents(string $value): int
{
    $normalized = str_replace([' ', ','], ['', '.'], trim($value));
    if ($normalized === '' || !is_numeric($normalized)) return 0;
    return max(0, (int) round(((float)$normalized) * 100));
}

function cents_to_money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ');
}

function decimal_value(string $value): float
{
    $normalized = str_replace(',', '.', trim($value));
    return is_numeric($normalized) ? max(0, (float)$normalized) : 0.0;
}

function save_quote_items(PDO $pdo, int $quoteId, array $post): array
{
    $ids = $post['catalog_item_id'] ?? [];
    $quantities = $post['quantity'] ?? [];
    $unitPrices = $post['unit_price'] ?? [];

    $pdo->prepare('DELETE FROM commercial_quote_items WHERE quote_id=:quote')->execute([':quote'=>$quoteId]);

    $catalogStmt = $pdo->prepare('SELECT * FROM catalog_items WHERE id=:id AND active=1 LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO commercial_quote_items
        (quote_id,catalog_item_id,category,item_name,description,quantity,unit_price_cents,line_total_cents,display_order)
        VALUES(:quote,:catalog,:category,:name,:description,:quantity,:unit_price,:line_total,:sort)'
    );

    $subtotal = 0;
    $formulaOptionsSubtotal = 0;
    $saved = [];
    $sort = 0;

    foreach ($ids as $index => $rawId) {
        $catalogId = (int)$rawId;
        if ($catalogId <= 0) continue;

        $catalogStmt->execute([':id'=>$catalogId]);
        $item = $catalogStmt->fetch();
        if (!$item || $item['price_mode'] === 'formula' || $item['name'] === 'Déplacement') continue;

        $quantity = decimal_value((string)($quantities[$index] ?? '1'));
        if ($quantity <= 0) continue;

        $unitPrice = money_to_cents((string)($unitPrices[$index] ?? cents_to_money((int)$item['price_cents'])));
        $lineTotal = (int)round($unitPrice * $quantity);
        $sort += 10;

        $insert->execute([
            ':quote'=>$quoteId,
            ':catalog'=>$catalogId,
            ':category'=>$item['category'],
            ':name'=>$item['name'],
            ':description'=>$item['description'],
            ':quantity'=>$quantity,
            ':unit_price'=>$unitPrice,
            ':line_total'=>$lineTotal,
            ':sort'=>$sort,
        ]);

        $subtotal += $lineTotal;
        if (in_array($item['category'], ['formule','option'], true)) {
            $formulaOptionsSubtotal += $lineTotal;
        }

        $saved[] = [
            'catalog_item_id'=>$catalogId,
            'category'=>$item['category'],
            'item_name'=>$item['name'],
            'quantity'=>$quantity,
            'unit_price_cents'=>$unitPrice,
            'line_total_cents'=>$lineTotal,
        ];
    }

    return [
        'items'=>$saved,
        'subtotal'=>$subtotal,
        'formula_options_subtotal'=>$formulaOptionsSubtotal,
    ];
}

$travelRateCents = 100;
$travelRateStmt = $pdo->query("SELECT price_cents FROM catalog_items WHERE category='frais' AND name='Déplacement' LIMIT 1");
if ($travelRateStmt) {
    $travelRateCents = max(0, (int)($travelRateStmt->fetchColumn() ?: 100));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if (in_array($action, ['create','update'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $sourceId = (int)($_POST['source_quote_id'] ?? 0);
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, $statuses, true)) $status = 'draft';

        if ($clientName === '') {
            $error = 'Le nom du client est obligatoire.';
        } elseif ($action === 'update' && $id <= 0) {
            $error = 'Impossible d’enregistrer ce devis.';
        } else {
            $pdo->beginTransaction();
            try {
                if ($action === 'create') {
                    $number = next_commercial_quote_number($pdo);
                    $stmt = $pdo->prepare(
                        'INSERT INTO commercial_quotes (
                            quote_number, source_quote_id, status, client_name, client_email, client_phone,
                            client_address, event_type, event_date, venue, description, amount_cents,
                            deposit_percent, travel_km, notes
                        ) VALUES (
                            :number, :source, :status, :name, :email, :phone,
                            :address, :event_type, :event_date, :venue, :description, 0,
                            :deposit, :travel_km, :notes
                        )'
                    );
                    $stmt->execute([
                        ':number'=>$number,
                        ':source'=>$sourceId > 0 ? $sourceId : null,
                        ':status'=>$status,
                        ':name'=>$clientName,
                        ':email'=>trim((string)($_POST['client_email'] ?? '')),
                        ':phone'=>trim((string)($_POST['client_phone'] ?? '')),
                        ':address'=>trim((string)($_POST['client_address'] ?? '')),
                        ':event_type'=>trim((string)($_POST['event_type'] ?? '')),
                        ':event_date'=>trim((string)($_POST['event_date'] ?? '')) ?: null,
                        ':venue'=>trim((string)($_POST['venue'] ?? '')),
                        ':description'=>trim((string)($_POST['description'] ?? '')),
                        ':deposit'=>30,
                        ':travel_km'=>decimal_value((string)($_POST['travel_km'] ?? '0')),
                        ':notes'=>trim((string)($_POST['notes'] ?? '')),
                    ]);
                    $id = (int)$pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE commercial_quotes SET
                            status=:status, client_name=:name, client_email=:email, client_phone=:phone,
                            client_address=:address, event_type=:event_type, event_date=:event_date,
                            venue=:venue, description=:description, deposit_percent=:deposit,
                            travel_km=:travel_km, notes=:notes, updated_at=CURRENT_TIMESTAMP
                         WHERE id=:id'
                    );
                    $stmt->execute([
                        ':status'=>$status,
                        ':name'=>$clientName,
                        ':email'=>trim((string)($_POST['client_email'] ?? '')),
                        ':phone'=>trim((string)($_POST['client_phone'] ?? '')),
                        ':address'=>trim((string)($_POST['client_address'] ?? '')),
                        ':event_type'=>trim((string)($_POST['event_type'] ?? '')),
                        ':event_date'=>trim((string)($_POST['event_date'] ?? '')) ?: null,
                        ':venue'=>trim((string)($_POST['venue'] ?? '')),
                        ':description'=>trim((string)($_POST['description'] ?? '')),
                        ':deposit'=>30,
                        ':travel_km'=>decimal_value((string)($_POST['travel_km'] ?? '0')),
                        ':notes'=>trim((string)($_POST['notes'] ?? '')),
                        ':id'=>$id,
                    ]);
                }

                $calc = save_quote_items($pdo, $id, $_POST);
                $travelKm = decimal_value((string)($_POST['travel_km'] ?? '0'));
                $travelCents = (int)round($travelKm * $travelRateCents);
                $setupCents = calculate_setup_teardown_cents((int)$calc['formula_options_subtotal']);
                $totalCents = (int)$calc['subtotal'] + $travelCents + $setupCents;

                $pdo->prepare('UPDATE commercial_quotes SET amount_cents=:amount, updated_at=CURRENT_TIMESTAMP WHERE id=:id')
                    ->execute([':amount'=>$totalCents, ':id'=>$id]);

                $pdo->commit();
                header('Location: documents.php?id=' . $id . ($action === 'create' ? '&created=1' : '&saved=1'));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Une erreur est survenue pendant l’enregistrement du devis.';
            }
        }
    }
}

$createFrom = (int)($_GET['create_from'] ?? 0);
$source = null;
if ($createFrom > 0) {
    $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id=:id');
    $stmt->execute([':id'=>$createFrom]);
    $source = $stmt->fetch() ?: null;
}

$id = (int)($_GET['id'] ?? 0);
$current = null;
$currentItems = [];
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM commercial_quotes WHERE id=:id');
    $stmt->execute([':id'=>$id]);
    $current = $stmt->fetch() ?: null;
    if (!$current) {
        http_response_code(404);
        exit('Devis introuvable');
    }

    $stmt = $pdo->prepare('SELECT * FROM commercial_quote_items WHERE quote_id=:quote ORDER BY display_order,id');
    $stmt->execute([':quote'=>$id]);
    $currentItems = $stmt->fetchAll();
}

$catalogItems = $pdo->query(
    "SELECT * FROM catalog_items
     WHERE active=1
       AND price_mode <> 'formula'
       AND NOT (category='frais' AND name='Déplacement')
     ORDER BY CASE category WHEN 'prestation' THEN 1 WHEN 'formule' THEN 2 WHEN 'option' THEN 3 ELSE 4 END,
              display_order,id"
)->fetchAll();

function normalize_catalog_match(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = str_replace(['’', '‘', '`'], "'", $value);
    return preg_replace('/\s+/u', ' ', $value) ?: '';
}

function source_duration_hours(?string $start, ?string $end): float
{
    $start = trim((string)$start);
    $end = trim((string)$end);
    if ($start === '' || $end === '') return 1.0;

    $toMinutes = static function (string $time): ?int {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) return null;
        $h = (int)$m[1];
        $min = (int)$m[2];
        if ($h > 23 || $min > 59) return null;
        return $h * 60 + $min;
    };

    $startMinutes = $toMinutes($start);
    $endMinutes = $toMinutes($end);
    if ($startMinutes === null || $endMinutes === null) return 1.0;
    if ($endMinutes <= $startMinutes) $endMinutes += 24 * 60;

    $hours = ($endMinutes - $startMinutes) / 60;
    return $hours > 0 ? round($hours, 2) : 1.0;
}

if ($source && !$current) {
    $requestedNames = array_values(array_filter(array_map(
        'trim',
        array_merge(
            explode('|', (string)($source['services'] ?? '')),
            explode('|', (string)($source['selections'] ?? ''))
        )
    )));

    $requestedNormalized = [];
    foreach ($requestedNames as $requestedName) {
        $normalized = normalize_catalog_match($requestedName);
        if ($normalized !== '') $requestedNormalized[] = $normalized;
    }

    $matchesRequestedItem = static function (string $catalogName) use ($requestedNormalized): bool {
        $catalog = normalize_catalog_match($catalogName);
        if ($catalog === '') return false;

        foreach ($requestedNormalized as $requested) {
            if ($requested === $catalog) return true;

            // Tolère les variantes comme "Photobooth 300" / "Photobooth 300 tirages".
            $short = mb_strlen($requested, 'UTF-8') <= mb_strlen($catalog, 'UTF-8') ? $requested : $catalog;
            $long = $short === $requested ? $catalog : $requested;

            if (mb_strlen($short, 'UTF-8') >= 6 && str_starts_with($long, $short . ' ')) {
                return true;
            }
        }

        return false;
    };

    $durationHours = source_duration_hours($source['start_time'] ?? null, $source['end_time'] ?? null);

    foreach ($catalogItems as $item) {
        if (!$matchesRequestedItem((string)$item['name'])) continue;

        $quantity = $item['price_mode'] === 'hourly' ? $durationHours : 1.0;
        $unitPrice = (int)$item['price_cents'];
        $currentItems[] = [
            'catalog_item_id'=>(int)$item['id'],
            'category'=>$item['category'],
            'item_name'=>$item['name'],
            'quantity'=>$quantity,
            'unit_price_cents'=>$unitPrice,
            'line_total_cents'=>(int)round($quantity * $unitPrice),
        ];
    }
}

$catalogForJs = [];
foreach ($catalogItems as $item) {
    $catalogForJs[] = [
        'id'=>(int)$item['id'],
        'category'=>$item['category'],
        'name'=>$item['name'],
        'price_cents'=>(int)$item['price_cents'],
        'price_mode'=>$item['price_mode'],
        'unit_label'=>$item['unit_label'] ?? '',
    ];
}

$documents = $pdo->query('SELECT * FROM commercial_quotes ORDER BY created_at DESC,id DESC')->fetchAll();

$title = $current ? $current['quote_number'] : ($source ? 'Nouveau devis' : 'Devis & factures');
admin_header($title, 'documents');

if (isset($_GET['created'])) $flash = 'Le brouillon de devis a été créé.';
if (isset($_GET['saved'])) $flash = 'Le devis a été enregistré.';
?>
<style>
.docs-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.55fr);gap:22px;align-items:start}
.docs-card{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:20px;background:linear-gradient(180deg,rgba(18,18,21,.96),rgba(12,12,15,.96));box-shadow:0 18px 48px rgba(0,0,0,.18)}
.docs-card:before{content:"";position:absolute;left:0;top:0;width:170px;height:1px;background:linear-gradient(90deg,#e32228,transparent);box-shadow:0 0 14px rgba(227,34,40,.3)}
.docs-card__head{padding:20px 22px 15px;border-bottom:1px solid rgba(255,255,255,.06)}
.docs-card__head span{display:block;margin-bottom:7px;color:#ff4a50;font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.docs-card__head h2{margin:0;color:#fff;font-size:22px;letter-spacing:-.025em}
.docs-card__body{padding:20px 22px}.docs-form{display:grid;gap:20px}
.docs-form__grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.docs-field{display:grid;gap:7px}.docs-field--full{grid-column:1/-1}
.docs-field label{color:#8f8a84;font-size:10px;font-weight:800;letter-spacing:.05em}.docs-field small{color:#66615c;font-size:9px;line-height:1.5}
.docs-field input,.docs-field textarea,.docs-field select{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.09);border-radius:12px;background:#09090b;color:#fff;padding:12px 13px;font:inherit}
.docs-field textarea{min-height:90px;resize:vertical}.docs-field input:focus,.docs-field textarea:focus,.docs-field select:focus{outline:none;border-color:rgba(227,34,40,.58);box-shadow:0 0 0 3px rgba(227,34,40,.075)}
.docs-source{margin-bottom:16px;padding:12px 14px;border:1px solid rgba(227,34,40,.14);border-radius:14px;background:rgba(227,34,40,.04);color:#aaa6a0;font-size:11px;line-height:1.6}.docs-source strong{color:#fff}
.docs-section{padding-top:4px}.docs-section__title{display:flex;align-items:end;justify-content:space-between;gap:12px;margin-bottom:11px}.docs-section__title h3{margin:0;color:#fff;font-size:15px}.docs-section__title span{color:#77726c;font-size:9px}
.quote-builder{display:grid;gap:10px}
.quote-add{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:12px;border:1px dashed rgba(255,255,255,.1);border-radius:13px;background:#0a0a0d}
.quote-add select{width:100%;border:1px solid rgba(255,255,255,.09);border-radius:10px;background:#070709;color:#fff;padding:10px 11px}
.quote-lines{display:grid;gap:8px}
.quote-line{display:grid;grid-template-columns:minmax(0,1.4fr) 95px 115px 115px 34px;gap:8px;align-items:end;padding:11px;border:1px solid rgba(255,255,255,.07);border-radius:13px;background:#0a0a0d}
.quote-line__name strong{display:block;color:#fff;font-size:12px}.quote-line__name small{display:block;margin-top:4px;color:#77726c;font-size:9px}
.quote-line label{display:grid;gap:5px;color:#706b65;font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
.quote-line input{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:#070709;color:#fff;padding:9px}
.quote-line__total{padding:9px 0;color:#fff;font-size:12px;font-weight:800;text-align:right}
.quote-line__remove{width:34px;height:34px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:#0e0e11;color:#888;cursor:pointer}.quote-line__remove:hover{color:#fff;border-color:rgba(227,34,40,.35)}
.docs-costs{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.docs-cost{padding:13px;border:1px solid rgba(255,255,255,.07);border-radius:13px;background:#0a0a0d}.docs-cost span{display:block;color:#77726c;font-size:8px;text-transform:uppercase;letter-spacing:.07em}.docs-cost strong{display:block;margin-top:5px;color:#fff;font-size:15px}.docs-cost small{display:block;margin-top:4px;color:#66615c;font-size:9px}
.docs-actions{display:flex;gap:10px;flex-wrap:wrap;padding-top:3px}
.docs-summary{display:grid;gap:10px}.docs-summary__item{padding:13px;border:1px solid rgba(255,255,255,.07);border-radius:13px;background:#0b0b0e}.docs-summary__item span{display:block;margin-bottom:4px;color:#77726c;font-size:8px;text-transform:uppercase;letter-spacing:.08em}.docs-summary__item strong{color:#fff;font-size:17px}
.docs-summary__item--total{border-color:rgba(227,34,40,.2);background:rgba(227,34,40,.035)}.docs-summary__item--total strong{font-size:22px}
.docs-list{display:grid;gap:8px}.docs-list__item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 13px;border:1px solid rgba(255,255,255,.07);border-radius:13px;background:#0b0b0e;text-decoration:none;transition:.16s}.docs-list__item:hover{background:#121216;border-color:rgba(227,34,40,.18);transform:translateX(2px)}.docs-list__item strong{display:block;color:#fff;font-size:11px}.docs-list__item small{display:block;margin-top:3px;color:#77726c;font-size:9px}
.docs-status{display:inline-flex;padding:5px 8px;border:1px solid rgba(255,255,255,.08);border-radius:999px;color:#aaa6a0;font-size:8px;font-weight:800}.docs-status--sent{color:#e4bd70}.docs-status--accepted{color:#a6d597}.docs-status--refused{color:#e69a95}.docs-status--expired{color:#8b8680}
.docs-empty{padding:18px;border:1px dashed rgba(255,255,255,.12);border-radius:13px;color:#817c77;font-size:11px}
@media(max-width:1100px){.docs-grid{grid-template-columns:1fr}.docs-side{order:-1}}
@media(max-width:780px){.quote-line{grid-template-columns:1fr 1fr}.quote-line__name{grid-column:1/-1}.quote-line__remove{align-self:end}.docs-form__grid,.docs-costs{grid-template-columns:1fr}.docs-field--full{grid-column:auto}}
</style>

<?php if ($flash): ?><div class="flash flash--success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<div class="docs-grid">
  <section class="docs-card">
    <div class="docs-card__head">
      <span><?= $current ? 'Édition du devis' : ($source ? 'Création depuis une demande' : 'Module commercial') ?></span>
      <h2><?= $current ? e($current['quote_number']) : ($source ? 'Créer un brouillon de devis' : 'Devis Luka C') ?></h2>
    </div>
    <div class="docs-card__body">
      <?php if (!$current && !$source): ?>
        <div class="docs-empty">Ouvre une demande de devis puis utilise le bouton <strong>Créer un devis Luka C</strong>. Les informations du client seront automatiquement reprises ici.</div>
      <?php else:
        $data = $current ?: [
          'source_quote_id'=>$source['id'],
          'status'=>'draft',
          'client_name'=>$source['name'],
          'client_email'=>$source['email'],
          'client_phone'=>$source['phone'],
          'client_address'=>$source['postal_address'],
          'event_type'=>$source['event_type'],
          'event_date'=>$source['event_date'],
          'venue'=>$source['venue'],
          'description'=>trim(
              'Horaires : ' . (($source['start_time'] ?? '') ?: 'À définir') . ' → ' . (($source['end_time'] ?? '') ?: 'À définir') . "\n" .
              'Invités : ' . (($source['guest_count'] ?? '') ?: 'Non renseigné') . "\n" .
              'Recommandé par : ' . (($source['referral'] ?? '') ?: 'Non renseigné')
          ),
          'deposit_percent'=>30,
          'travel_km'=>0,
          'notes'=>'',
        ];
      ?>
        <?php if ($source && !$current): ?>
          <div class="docs-source">
            Demande source : <strong>#<?= (int)$source['id'] ?> — <?= e($source['name']) ?></strong>
            <?php if (!empty($source['message'])): ?><br>Message client : <?= nl2br(e($source['message'])) ?><?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="docs-form" id="quoteForm">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="<?= $current ? 'update' : 'create' ?>">
          <input type="hidden" name="id" value="<?= $current ? (int)$current['id'] : 0 ?>">
          <input type="hidden" name="source_quote_id" value="<?= (int)($data['source_quote_id'] ?? 0) ?>">

          <div class="docs-form__grid">
            <div class="docs-field"><label for="client_name">Client</label><input id="client_name" name="client_name" required value="<?= e((string)$data['client_name']) ?>"></div>
            <div class="docs-field"><label for="client_email">E-mail</label><input id="client_email" name="client_email" type="email" value="<?= e((string)$data['client_email']) ?>"></div>
            <div class="docs-field"><label for="client_phone">Téléphone</label><input id="client_phone" name="client_phone" value="<?= e((string)$data['client_phone']) ?>"></div>
            <div class="docs-field"><label for="status">Statut</label><select id="status" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $data['status'] === $status ? 'selected' : '' ?>><?= e(commercial_quote_status_label($status)) ?></option><?php endforeach; ?></select></div>
            <div class="docs-field docs-field--full"><label for="client_address">Adresse client</label><input id="client_address" name="client_address" value="<?= e((string)$data['client_address']) ?>"></div>
            <div class="docs-field"><label for="event_type">Événement</label><input id="event_type" name="event_type" value="<?= e((string)$data['event_type']) ?>"></div>
            <div class="docs-field"><label for="event_date">Date</label><input id="event_date" name="event_date" type="date" value="<?= e((string)$data['event_date']) ?>"></div>
            <div class="docs-field docs-field--full"><label for="venue">Lieu</label><input id="venue" name="venue" value="<?= e((string)$data['venue']) ?>"></div>
            <div class="docs-field docs-field--full"><label for="description">Informations / détails</label><textarea id="description" name="description"><?= e((string)$data['description']) ?></textarea></div>
          </div>

          <div class="docs-section">
            <div class="docs-section__title"><h3>Prestations du devis</h3><span>Tarifs repris du catalogue, modifiables pour ce devis uniquement</span></div>
            <div class="quote-builder">
              <div class="quote-add">
                <select id="catalogSelect">
                  <option value="">Choisir une prestation, formule ou option…</option>
                  <?php
                  $lastCategory = '';
                  $labels = ['prestation'=>'Prestations','formule'=>'Formules','option'=>'Options'];
                  foreach ($catalogItems as $item):
                    if ($item['category'] !== $lastCategory):
                      if ($lastCategory !== '') echo '</optgroup>';
                      $lastCategory = $item['category'];
                      echo '<optgroup label="' . e($labels[$lastCategory] ?? ucfirst($lastCategory)) . '">';
                    endif;
                  ?>
                    <option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?> — <?= e(cents_to_money((int)$item['price_cents'])) ?> €<?= $item['unit_label'] ? ' · ' . e($item['unit_label']) : '' ?></option>
                  <?php endforeach; if ($lastCategory !== '') echo '</optgroup>'; ?>
                </select>
                <button class="btn" type="button" id="addCatalogItem">Ajouter</button>
              </div>

              <div class="quote-lines" id="quoteLines">
                <?php foreach ($currentItems as $item): ?>
                  <div class="quote-line" data-category="<?= e($item['category']) ?>">
                    <div class="quote-line__name">
                      <strong><?= e($item['item_name']) ?></strong>
                      <small><?= e(ucfirst($item['category'])) ?></small>
                      <input type="hidden" name="catalog_item_id[]" value="<?= (int)$item['catalog_item_id'] ?>">
                    </div>
                    <label>Quantité / heures<input class="line-qty" name="quantity[]" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format((float)$item['quantity'], 2, ',', ''), '0'), ',')) ?>"></label>
                    <label>Prix unitaire (€)<input class="line-price" name="unit_price[]" inputmode="decimal" value="<?= e(cents_to_money((int)$item['unit_price_cents'])) ?>"></label>
                    <div class="quote-line__total"><?= e(cents_to_money((int)$item['line_total_cents'])) ?> €</div>
                    <button class="quote-line__remove" type="button" title="Supprimer">×</button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="docs-section">
            <div class="docs-section__title"><h3>Frais</h3><span>Calcul automatique</span></div>
            <div class="docs-form__grid">
              <div class="docs-field">
                <label for="travel_km">Kilomètres facturés</label>
                <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px">
                  <input id="travel_km" name="travel_km" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format((float)($data['travel_km'] ?? 0), 2, ',', ''), '0'), ',')) ?>">
                  <button class="btn" type="button" id="calculateTravel">Calculer</button>
                </div>
                <small id="travelStatus">Calcul automatique aller-retour depuis le point de départ Luka C · <?= e(cents_to_money($travelRateCents)) ?> € / km. Le kilométrage reste modifiable manuellement.</small>
              </div>
              <div class="docs-costs">
                <div class="docs-cost"><span>Déplacement</span><strong id="travelDisplay">0,00 €</strong><small><span id="travelKmDisplay">0</span> km × <?= e(cents_to_money($travelRateCents)) ?> €</small></div>
                <div class="docs-cost"><span>Montage / démontage</span><strong id="setupDisplay">0,00 €</strong><small>20 % du total Formule + Options</small></div>
              </div>
            </div>
          </div>

          <div class="docs-form__grid">
            <div class="docs-field"><label>Acompte</label><input value="Calcul automatique · maximum 30 %" readonly><small>Le solde restant est arrondi à la dizaine, sans jamais dépasser 30 % d’acompte.</small></div>
            <div class="docs-field docs-field--full"><label for="notes">Notes internes / complémentaires</label><textarea id="notes" name="notes"><?= e((string)$data['notes']) ?></textarea></div>
          </div>

          <div class="docs-actions">
            <button class="btn btn--primary" type="submit"><?= $current ? 'Enregistrer le devis' : 'Créer le brouillon' ?></button>
            <?php if ($current && !empty($current['source_quote_id'])): ?><a class="btn" href="devis.php?id=<?= (int)$current['source_quote_id'] ?>">Voir la demande source</a><?php endif; ?>
            <a class="btn" href="documents.php">Retour à la liste</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>

  <aside class="docs-card docs-side">
    <div class="docs-card__head"><span>Calcul</span><h2>Récapitulatif</h2></div>
    <div class="docs-card__body">
      <?php if ($current || $source): ?>
        <?php
        $displayAmount = $current ? (int)$current['amount_cents'] : 0;
        $displayDepositData = calculate_rounded_deposit($displayAmount);
        $displayDeposit = (int)$displayDepositData['deposit_cents'];
        $displayBalance = (int)$displayDepositData['balance_cents'];
        ?>
        <div class="docs-summary" style="margin-bottom:18px">
          <div class="docs-summary__item"><span>Prestations + formules + options</span><strong id="itemsSubtotalDisplay">0,00 €</strong></div>
          <div class="docs-summary__item"><span>Déplacement</span><strong id="summaryTravelDisplay">0,00 €</strong></div>
          <div class="docs-summary__item"><span>Montage / démontage</span><strong id="summarySetupDisplay">0,00 €</strong></div>
          <div class="docs-summary__item docs-summary__item--total"><span>Total</span><strong id="grandTotalDisplay"><?= e(cents_to_money($displayAmount)) ?> €</strong></div>
          <div class="docs-summary__item"><span>Acompte · max. 30 %</span><strong id="depositDisplay"><?= e(cents_to_money($displayDeposit)) ?> €</strong></div>
          <div class="docs-summary__item"><span>Solde arrondi</span><strong id="balanceDisplay"><?= e(cents_to_money($displayBalance)) ?> €</strong></div>
        </div>
      <?php endif; ?>

      <div class="docs-section__title"><h3>Devis enregistrés</h3></div>
      <?php if (!$documents): ?>
        <div class="docs-empty">Aucun devis Luka C pour le moment.</div>
      <?php else: ?>
        <div class="docs-list">
          <?php foreach ($documents as $doc): ?>
            <a class="docs-list__item" href="documents.php?id=<?= (int)$doc['id'] ?>">
              <div><strong><?= e($doc['quote_number']) ?> · <?= e($doc['client_name']) ?></strong><small><?= $doc['event_date'] ? e(date('d/m/Y', strtotime($doc['event_date']))) : 'Date à définir' ?> · <?= e(cents_to_money((int)$doc['amount_cents'])) ?> €</small></div>
              <span class="docs-status docs-status--<?= e($doc['status']) ?>"><?= e(commercial_quote_status_label($doc['status'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </aside>
</div>

<script>
(() => {
  const catalog = <?= json_encode($catalogForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const travelRateCents = <?= (int)$travelRateCents ?>;
  const lines = document.getElementById('quoteLines');
  const select = document.getElementById('catalogSelect');
  const addBtn = document.getElementById('addCatalogItem');
  const travelInput = document.getElementById('travel_km');
  const venueInput = document.getElementById('venue');
  const calculateTravelBtn = document.getElementById('calculateTravel');
  const travelStatus = document.getElementById('travelStatus');

  if (!lines || !select || !addBtn) return;

  const euros = cents => (cents / 100).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
  const numberValue = value => {
    const n = parseFloat(String(value || '').replace(',', '.'));
    return Number.isFinite(n) && n > 0 ? n : 0;
  };
  const centsValue = value => Math.max(0, Math.round(numberValue(value) * 100));

  function recalc() {
    let subtotal = 0;
    let formulaOptions = 0;

    lines.querySelectorAll('.quote-line').forEach(row => {
      const qty = numberValue(row.querySelector('.line-qty')?.value);
      const price = centsValue(row.querySelector('.line-price')?.value);
      const total = Math.round(qty * price);
      subtotal += total;
      if (['formule','option'].includes(row.dataset.category)) formulaOptions += total;
      const totalEl = row.querySelector('.quote-line__total');
      if (totalEl) totalEl.textContent = euros(total);
    });

    const km = numberValue(travelInput?.value);
    const travel = Math.round(km * travelRateCents);
    const setup = Math.round(formulaOptions * .20);
    const grand = subtotal + travel + setup;
    const minimumBalance = Math.ceil(grand * .70);
    let balance = Math.ceil(minimumBalance / 1000) * 1000;
    if (balance > grand) balance = grand;
    const deposit = Math.max(0, grand - balance);

    const map = {
      itemsSubtotalDisplay: euros(subtotal),
      travelDisplay: euros(travel),
      summaryTravelDisplay: euros(travel),
      setupDisplay: euros(setup),
      summarySetupDisplay: euros(setup),
      grandTotalDisplay: euros(grand),
      depositDisplay: euros(deposit),
      balanceDisplay: euros(balance),
      travelKmDisplay: String(km).replace('.', ',')
    };
    Object.entries(map).forEach(([id,value]) => {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    });
  }

  async function geocodeAddress(address) {
    const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=fr&q=' + encodeURIComponent(address);
    const response = await fetch(url, {headers:{'Accept':'application/json'}});
    if (!response.ok) throw new Error('geocode');
    const results = await response.json();
    if (!Array.isArray(results) || !results[0]) throw new Error('not-found');
    return {lat:Number(results[0].lat), lon:Number(results[0].lon)};
  }

  async function calculateTravelDistance() {
    const venue = String(venueInput?.value || '').trim();
    if (!venue) {
      if (travelStatus) travelStatus.textContent = 'Renseigne d’abord le lieu de l’événement pour calculer le déplacement.';
      return;
    }

    if (calculateTravelBtn) calculateTravelBtn.disabled = true;
    if (travelStatus) travelStatus.textContent = 'Calcul de l’itinéraire en cours…';

    try {
      const originAddress = '65 rue de Verdun, 52290 Eclaron, France';
      const [origin, destination] = await Promise.all([
        geocodeAddress(originAddress),
        geocodeAddress(venue + ', France')
      ]);

      const routeUrl = 'https://router.project-osrm.org/route/v1/driving/' +
        origin.lon + ',' + origin.lat + ';' + destination.lon + ',' + destination.lat +
        '?overview=false';
      const response = await fetch(routeUrl, {headers:{'Accept':'application/json'}});
      if (!response.ok) throw new Error('routing');
      const data = await response.json();
      const meters = Number(data?.routes?.[0]?.distance || 0);
      if (!meters) throw new Error('no-route');

      const oneWayKm = meters / 1000;
      const billedKm = Math.round(oneWayKm * 2);
      travelInput.value = String(billedKm).replace('.', ',');
      if (travelStatus) {
        travelStatus.textContent =
          'Distance calculée : ' + oneWayKm.toLocaleString('fr-FR',{maximumFractionDigits:1}) +
          ' km aller · ' + billedKm + ' km aller-retour facturés à ' +
          (travelRateCents / 100).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' € / km.';
      }
      recalc();
    } catch (error) {
      if (travelStatus) travelStatus.textContent =
        'Distance automatique indisponible pour ce lieu. Tu peux saisir le kilométrage manuellement.';
    } finally {
      if (calculateTravelBtn) calculateTravelBtn.disabled = false;
    }
  }

  function bindRow(row) {
    row.querySelectorAll('input').forEach(input => input.addEventListener('input', recalc));
    row.querySelector('.quote-line__remove')?.addEventListener('click', () => {
      row.remove();
      recalc();
    });
  }

  lines.querySelectorAll('.quote-line').forEach(bindRow);

  addBtn.addEventListener('click', () => {
    const id = parseInt(select.value || '0', 10);
    const item = catalog.find(x => x.id === id);
    if (!item) return;

    const existing = [...lines.querySelectorAll('input[name="catalog_item_id[]"]')]
      .some(input => parseInt(input.value || '0', 10) === id);
    if (existing) {
      const row = [...lines.querySelectorAll('.quote-line')]
        .find(r => parseInt(r.querySelector('input[name="catalog_item_id[]"]')?.value || '0', 10) === id);
      row?.querySelector('.line-qty')?.focus();
      return;
    }

    const row = document.createElement('div');
    row.className = 'quote-line';
    row.dataset.category = item.category;
    const categoryLabel = item.category.charAt(0).toUpperCase() + item.category.slice(1);
    const price = (item.price_cents / 100).toFixed(2).replace('.', ',');

    row.innerHTML = `
      <div class="quote-line__name">
        <strong></strong>
        <small></small>
        <input type="hidden" name="catalog_item_id[]" value="${item.id}">
      </div>
      <label>Quantité / heures<input class="line-qty" name="quantity[]" inputmode="decimal" value="1"></label>
      <label>Prix unitaire (€)<input class="line-price" name="unit_price[]" inputmode="decimal" value="${price}"></label>
      <div class="quote-line__total">${euros(item.price_cents)}</div>
      <button class="quote-line__remove" type="button" title="Supprimer">×</button>
    `;
    row.querySelector('strong').textContent = item.name;
    row.querySelector('small').textContent = categoryLabel + (item.unit_label ? ' · ' + item.unit_label : '');
    lines.appendChild(row);
    bindRow(row);
    select.value = '';
    recalc();
  });

  travelInput?.addEventListener('input', recalc);
  calculateTravelBtn?.addEventListener('click', calculateTravelDistance);

  if (venueInput && travelInput && numberValue(travelInput.value) === 0 && venueInput.value.trim() !== '') {
    calculateTravelDistance();
  } else {
    recalc();
  }
})();
</script>

<?php admin_footer(); ?>
