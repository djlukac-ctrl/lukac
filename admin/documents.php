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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $sourceId = (int)($_POST['source_quote_id'] ?? 0);
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, $statuses, true)) $status = 'draft';

        if ($clientName === '') {
            $error = 'Le nom du client est obligatoire.';
        } else {
            $number = next_commercial_quote_number($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO commercial_quotes (
                    quote_number, source_quote_id, status, client_name, client_email, client_phone,
                    client_address, event_type, event_date, venue, description, amount_cents,
                    deposit_percent, notes
                ) VALUES (
                    :number, :source, :status, :name, :email, :phone,
                    :address, :event_type, :event_date, :venue, :description, :amount,
                    :deposit, :notes
                )'
            );
            $stmt->execute([
                ':number' => $number,
                ':source' => $sourceId > 0 ? $sourceId : null,
                ':status' => $status,
                ':name' => $clientName,
                ':email' => trim((string)($_POST['client_email'] ?? '')),
                ':phone' => trim((string)($_POST['client_phone'] ?? '')),
                ':address' => trim((string)($_POST['client_address'] ?? '')),
                ':event_type' => trim((string)($_POST['event_type'] ?? '')),
                ':event_date' => trim((string)($_POST['event_date'] ?? '')) ?: null,
                ':venue' => trim((string)($_POST['venue'] ?? '')),
                ':description' => trim((string)($_POST['description'] ?? '')),
                ':amount' => money_to_cents((string)($_POST['amount'] ?? '0')),
                ':deposit' => max(0, min(100, (int)($_POST['deposit_percent'] ?? 30))),
                ':notes' => trim((string)($_POST['notes'] ?? '')),
            ]);
            $id = (int)$pdo->lastInsertId();
            header('Location: documents.php?id=' . $id . '&created=1');
            exit;
        }
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        if (!in_array($status, $statuses, true)) $status = 'draft';

        if ($id <= 0 || $clientName === '') {
            $error = 'Impossible d’enregistrer ce devis.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE commercial_quotes SET
                    status=:status,
                    client_name=:name,
                    client_email=:email,
                    client_phone=:phone,
                    client_address=:address,
                    event_type=:event_type,
                    event_date=:event_date,
                    venue=:venue,
                    description=:description,
                    amount_cents=:amount,
                    deposit_percent=:deposit,
                    notes=:notes,
                    updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id'
            );
            $stmt->execute([
                ':status' => $status,
                ':name' => $clientName,
                ':email' => trim((string)($_POST['client_email'] ?? '')),
                ':phone' => trim((string)($_POST['client_phone'] ?? '')),
                ':address' => trim((string)($_POST['client_address'] ?? '')),
                ':event_type' => trim((string)($_POST['event_type'] ?? '')),
                ':event_date' => trim((string)($_POST['event_date'] ?? '')) ?: null,
                ':venue' => trim((string)($_POST['venue'] ?? '')),
                ':description' => trim((string)($_POST['description'] ?? '')),
                ':amount' => money_to_cents((string)($_POST['amount'] ?? '0')),
                ':deposit' => max(0, min(100, (int)($_POST['deposit_percent'] ?? 30))),
                ':notes' => trim((string)($_POST['notes'] ?? '')),
                ':id' => $id,
            ]);
            header('Location: documents.php?id=' . $id . '&saved=1');
            exit;
        }
    }
}

$createFrom = (int)($_GET['create_from'] ?? 0);
$source = null;
if ($createFrom > 0) {
    $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id=:id');
    $stmt->execute([':id' => $createFrom]);
    $source = $stmt->fetch() ?: null;
}

$id = (int)($_GET['id'] ?? 0);
$current = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM commercial_quotes WHERE id=:id');
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetch() ?: null;
    if (!$current) {
        http_response_code(404);
        exit('Devis introuvable');
    }
}

$documents = $pdo->query('SELECT * FROM commercial_quotes ORDER BY created_at DESC, id DESC')->fetchAll();

$title = $current ? $current['quote_number'] : ($source ? 'Nouveau devis' : 'Devis & factures');
admin_header($title, 'documents');

if (isset($_GET['created'])) $flash = 'Le brouillon de devis a été créé.';
if (isset($_GET['saved'])) $flash = 'Le devis a été enregistré.';
?>
<style>
.docs-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:22px;align-items:start}
.docs-card{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:20px;background:linear-gradient(180deg,rgba(18,18,21,.96),rgba(12,12,15,.96));box-shadow:0 18px 48px rgba(0,0,0,.18)}
.docs-card:before{content:"";position:absolute;left:0;top:0;width:170px;height:1px;background:linear-gradient(90deg,#e32228,transparent);box-shadow:0 0 14px rgba(227,34,40,.3)}
.docs-card__head{padding:20px 22px 15px;border-bottom:1px solid rgba(255,255,255,.06)}
.docs-card__head span{display:block;margin-bottom:7px;color:#ff4a50;font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.docs-card__head h2{margin:0;color:#fff;font-size:22px;letter-spacing:-.025em}
.docs-card__body{padding:20px 22px}
.docs-form{display:grid;gap:18px}.docs-form__grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.docs-field{display:grid;gap:7px}.docs-field--full{grid-column:1/-1}
.docs-field label{color:#8f8a84;font-size:10px;font-weight:800;letter-spacing:.05em}
.docs-field input,.docs-field textarea,.docs-field select{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.09);border-radius:12px;background:#09090b;color:#fff;padding:12px 13px;font:inherit}
.docs-field textarea{min-height:105px;resize:vertical}.docs-field input:focus,.docs-field textarea:focus,.docs-field select:focus{outline:none;border-color:rgba(227,34,40,.58);box-shadow:0 0 0 3px rgba(227,34,40,.075)}
.docs-actions{display:flex;gap:10px;flex-wrap:wrap;padding-top:3px}
.docs-summary{display:grid;gap:12px}.docs-summary__item{padding:14px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:#0b0b0e}
.docs-summary__item span{display:block;margin-bottom:5px;color:#77726c;font-size:9px;text-transform:uppercase;letter-spacing:.08em}.docs-summary__item strong{color:#fff;font-size:18px}
.docs-list{display:grid;gap:9px}.docs-list__item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:13px 14px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:#0b0b0e;text-decoration:none;transition:.16s}
.docs-list__item:hover{background:#121216;border-color:rgba(227,34,40,.18);transform:translateX(2px)}.docs-list__item strong{display:block;color:#fff;font-size:12px}.docs-list__item small{display:block;margin-top:4px;color:#77726c;font-size:10px}
.docs-status{display:inline-flex;padding:5px 8px;border:1px solid rgba(255,255,255,.08);border-radius:999px;color:#aaa6a0;font-size:9px;font-weight:800}.docs-status--draft{color:#c7c3be}.docs-status--sent{color:#e4bd70}.docs-status--accepted{color:#a6d597}.docs-status--refused{color:#e69a95}.docs-status--expired{color:#8b8680}
.docs-empty{padding:20px;border:1px dashed rgba(255,255,255,.12);border-radius:14px;color:#817c77;font-size:12px}
.docs-source{margin-bottom:16px;padding:12px 14px;border:1px solid rgba(227,34,40,.14);border-radius:14px;background:rgba(227,34,40,.04);color:#aaa6a0;font-size:11px}.docs-source strong{color:#fff}
@media(max-width:1050px){.docs-grid{grid-template-columns:1fr}.docs-side{order:-1}}
@media(max-width:680px){.docs-form__grid{grid-template-columns:1fr}.docs-field--full{grid-column:auto}}
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
          'source_quote_id' => $source['id'],
          'status' => 'draft',
          'client_name' => $source['name'],
          'client_email' => $source['email'],
          'client_phone' => $source['phone'],
          'client_address' => $source['postal_address'],
          'event_type' => $source['event_type'],
          'event_date' => $source['event_date'],
          'venue' => $source['venue'],
          'description' => trim(($source['services'] ?? '') . "\n" . ($source['selections'] ?? '')),
          'amount_cents' => 0,
          'deposit_percent' => 30,
          'notes' => $source['message'],
        ];
      ?>
        <?php if ($source && !$current): ?><div class="docs-source">Demande source : <strong>#<?= (int)$source['id'] ?> — <?= e($source['name']) ?></strong></div><?php endif; ?>
        <form method="post" class="docs-form">
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
            <div class="docs-field docs-field--full"><label for="description">Prestation / détails</label><textarea id="description" name="description"><?= e((string)$data['description']) ?></textarea></div>
            <div class="docs-field"><label for="amount">Montant total (€)</label><input id="amount" name="amount" inputmode="decimal" value="<?= $current ? e(cents_to_money((int)$data['amount_cents'])) : '0,00' ?>"></div>
            <div class="docs-field"><label for="deposit_percent">Acompte (%)</label><input id="deposit_percent" name="deposit_percent" type="number" min="0" max="100" value="<?= (int)$data['deposit_percent'] ?>"></div>
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
    <div class="docs-card__head">
      <span>Documents</span>
      <h2>Devis enregistrés</h2>
    </div>
    <div class="docs-card__body">
      <?php if ($current):
        $deposit = (int)round(((int)$current['amount_cents']) * ((int)$current['deposit_percent']) / 100);
        $balance = (int)$current['amount_cents'] - $deposit;
      ?>
        <div class="docs-summary" style="margin-bottom:18px">
          <div class="docs-summary__item"><span>Total</span><strong><?= e(cents_to_money((int)$current['amount_cents'])) ?> €</strong></div>
          <div class="docs-summary__item"><span>Acompte <?= (int)$current['deposit_percent'] ?> %</span><strong><?= e(cents_to_money($deposit)) ?> €</strong></div>
          <div class="docs-summary__item"><span>Solde</span><strong><?= e(cents_to_money($balance)) ?> €</strong></div>
        </div>
      <?php endif; ?>

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

<?php admin_footer(); ?>
