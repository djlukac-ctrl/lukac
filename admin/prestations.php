<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'create_manual') {
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $clientEmail = trim((string)($_POST['client_email'] ?? ''));
        $clientPhone = trim((string)($_POST['client_phone'] ?? ''));
        $eventType = trim((string)($_POST['event_type'] ?? ''));
        $eventDate = trim((string)($_POST['event_date'] ?? ''));
        $venue = trim((string)($_POST['venue'] ?? ''));
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime = trim((string)($_POST['end_time'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $totalRaw = str_replace(',', '.', trim((string)($_POST['total_amount'] ?? '')));
        $depositRaw = str_replace(',', '.', trim((string)($_POST['deposit_amount'] ?? '')));
        $totalCents = $totalRaw !== '' ? max(0, (int)round(((float)$totalRaw) * 100)) : null;
        $depositCents = $depositRaw !== '' ? max(0, (int)round(((float)$depositRaw) * 100)) : null;
        $contractSigned = isset($_POST['contract_signed']) ? 1 : 0;
        $depositReceived = isset($_POST['deposit_received']) ? 1 : 0;

        if ($clientName === '' || $eventType === '' || $eventDate === '') {
            header('Location: prestations.php?new=1&error=missing');
            exit;
        }

        if ($totalCents !== null && $depositCents !== null && $depositCents > $totalCents) {
            $depositCents = $totalCents;
        }

        $stmt = $pdo->prepare("
            INSERT INTO prestations(
                source_quote_id, client_name, client_email, client_phone,
                event_type, event_date, venue, start_time, end_time, notes,
                total_amount_cents, deposit_amount_cents,
                contract_signed, deposit_received,
                contract_signed_at, deposit_received_at,
                client_meeting_status, created_at, updated_at
            ) VALUES(
                NULL, :client_name, :client_email, :client_phone,
                :event_type, :event_date, :venue, :start_time, :end_time, :notes,
                :total, :deposit,
                :contract_signed, :deposit_received,
                CASE WHEN :contract_signed = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                CASE WHEN :deposit_received = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                'todo', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':client_name'=>$clientName,
            ':client_email'=>$clientEmail !== '' ? $clientEmail : null,
            ':client_phone'=>$clientPhone !== '' ? $clientPhone : null,
            ':event_type'=>$eventType,
            ':event_date'=>$eventDate,
            ':venue'=>$venue !== '' ? $venue : null,
            ':start_time'=>$startTime !== '' ? $startTime : null,
            ':end_time'=>$endTime !== '' ? $endTime : null,
            ':notes'=>$notes !== '' ? $notes : null,
            ':total'=>$totalCents,
            ':deposit'=>$depositCents,
            ':contract_signed'=>$contractSigned,
            ':deposit_received'=>$depositReceived,
        ]);

        $newId = (int)$pdo->lastInsertId();
        header('Location: prestations.php?id=' . $newId . '&created=1');
        exit;
    }

    if ($id > 0 && $action === 'save') {
        $totalRaw = str_replace(',', '.', trim((string)($_POST['total_amount'] ?? '')));
        $depositRaw = str_replace(',', '.', trim((string)($_POST['deposit_amount'] ?? '')));
        $totalCents = $totalRaw !== '' ? max(0, (int)round(((float)$totalRaw) * 100)) : null;
        $depositCents = $depositRaw !== '' ? max(0, (int)round(((float)$depositRaw) * 100)) : null;
        $contractSigned = isset($_POST['contract_signed']) ? 1 : 0;
        $depositReceived = isset($_POST['deposit_received']) ? 1 : 0;
        $clientMeetingStatus = (string)($_POST['client_meeting_status'] ?? 'todo');
        if (!in_array($clientMeetingStatus, ['todo','planned','done'], true)) $clientMeetingStatus = 'todo';
        $clientMeetingAtRaw = trim((string)($_POST['client_meeting_at'] ?? ''));
        $clientMeetingAt = $clientMeetingAtRaw !== '' ? str_replace('T', ' ', $clientMeetingAtRaw) . ':00' : null;
        $clientMeetingNotes = trim((string)($_POST['client_meeting_notes'] ?? ''));
        if ($clientMeetingStatus !== 'planned') $clientMeetingAt = null;
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($totalCents !== null && $depositCents !== null && $depositCents > $totalCents) {
            $depositCents = $totalCents;
        }

        $stmt = $pdo->prepare("
            UPDATE prestations
            SET total_amount_cents=:total,
                deposit_amount_cents=:deposit,
                contract_signed=:contract_signed,
                deposit_received=:deposit_received,
                contract_signed_at=CASE
                    WHEN :contract_signed = 1 AND contract_signed = 0 THEN CURRENT_TIMESTAMP
                    WHEN :contract_signed = 0 THEN NULL
                    ELSE contract_signed_at
                END,
                deposit_received_at=CASE
                    WHEN :deposit_received = 1 AND deposit_received = 0 THEN CURRENT_TIMESTAMP
                    WHEN :deposit_received = 0 THEN NULL
                    ELSE deposit_received_at
                END,
                client_meeting_status=:client_meeting_status,
                client_meeting_at=:client_meeting_at,
                client_meeting_notes=:client_meeting_notes,
                notes=:notes,
                updated_at=CURRENT_TIMESTAMP
            WHERE id=:id
        ");
        $stmt->execute([
            ':total'=>$totalCents,
            ':deposit'=>$depositCents,
            ':contract_signed'=>$contractSigned,
            ':deposit_received'=>$depositReceived,
            ':client_meeting_status'=>$clientMeetingStatus,
            ':client_meeting_at'=>$clientMeetingAt,
            ':client_meeting_notes'=>$clientMeetingNotes !== '' ? $clientMeetingNotes : null,
            ':notes'=>$notes !== '' ? $notes : null,
            ':id'=>$id,
        ]);

        header('Location: prestations.php?id=' . $id . '&saved=1');
        exit;
    }
}

$isNewManual = isset($_GET['new']);
if ($isNewManual) {
    admin_header('Ajouter une prestation', 'prestations');
    ?>
    <style>
    .manual-wrap{max-width:920px;margin:0 auto}.manual-card{padding:24px;border:1px solid rgba(255,255,255,.08);border-radius:20px;background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.018));box-shadow:0 12px 34px rgba(0,0,0,.12)}
    .manual-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:22px}.manual-head h2{margin:0 0 5px;font-size:22px}.manual-head p{margin:0;color:#858585;font-size:11px}.manual-form{display:grid;gap:16px}.manual-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.manual-form .field label{color:#aaa}.manual-form input,.manual-form textarea{width:100%;box-sizing:border-box}.manual-checks{display:grid;grid-template-columns:1fr 1fr;gap:10px}.manual-check{display:flex;align-items:center;gap:9px;padding:11px;border:1px solid rgba(255,255,255,.07);border-radius:12px;background:rgba(255,255,255,.02);font-size:11px;font-weight:700}.manual-actions{display:flex;gap:10px;flex-wrap:wrap}.manual-error{margin-bottom:15px;padding:10px 12px;border:1px solid rgba(255,80,80,.18);border-radius:10px;background:rgba(255,80,80,.07);color:#ff8c8c;font-size:11px}
    @media(max-width:700px){.manual-grid,.manual-checks{grid-template-columns:1fr}}
    </style>
    <div class="manual-wrap">
      <section class="manual-card">
        <div class="manual-head">
          <div>
            <h2>Ajouter une prestation réservée</h2>
            <p>Pour une réservation déjà confirmée en dehors du site, par exemple depuis Djenesis.</p>
          </div>
          <a class="admin-link" href="prestations.php">← Retour</a>
        </div>

        <?php if (($_GET['error'] ?? '') === 'missing'): ?>
          <div class="manual-error">Nom du client, type d’événement et date sont obligatoires.</div>
        <?php endif; ?>

        <form method="post" class="manual-form">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_manual">

          <div class="manual-grid">
            <div class="field"><label for="client_name">Nom du client *</label><input id="client_name" name="client_name" required></div>
            <div class="field"><label for="event_type">Type d’événement *</label><input id="event_type" name="event_type" placeholder="Mariage, anniversaire, soirée..." required></div>
            <div class="field"><label for="event_date">Date *</label><input id="event_date" name="event_date" type="date" required></div>
            <div class="field"><label for="venue">Lieu</label><input id="venue" name="venue"></div>
            <div class="field"><label for="start_time">Heure de début</label><input id="start_time" name="start_time" type="time"></div>
            <div class="field"><label for="end_time">Heure de fin</label><input id="end_time" name="end_time" type="time"></div>
            <div class="field"><label for="client_email">E-mail</label><input id="client_email" name="client_email" type="email"></div>
            <div class="field"><label for="client_phone">Téléphone</label><input id="client_phone" name="client_phone"></div>
            <div class="field"><label for="total_amount">Montant total</label><input id="total_amount" name="total_amount" inputmode="decimal" placeholder="Ex. 950,00"></div>
            <div class="field"><label for="deposit_amount">Acompte</label><input id="deposit_amount" name="deposit_amount" inputmode="decimal" placeholder="Ex. 285,00"></div>
          </div>

          <div class="manual-checks">
            <label class="manual-check"><input type="checkbox" name="contract_signed" value="1">Contrat signé</label>
            <label class="manual-check"><input type="checkbox" name="deposit_received" value="1">Acompte reçu</label>
          </div>

          <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="4" placeholder="Informations utiles sur cette réservation..."></textarea></div>

          <div class="manual-actions">
            <button class="btn btn--primary" type="submit">Ajouter la prestation</button>
            <a class="btn" href="prestations.php">Annuler</a>
          </div>
        </form>
      </section>
    </div>
    <?php
    admin_footer();
    exit;
}

$detailId = (int)($_GET['id'] ?? 0);
if ($detailId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, q.id AS quote_id,
               COALESCE(q.name, p.client_name) AS name,
               COALESCE(q.email, p.client_email) AS email,
               COALESCE(q.phone, p.client_phone) AS phone,
               COALESCE(q.event_type, p.event_type) AS event_type,
               COALESCE(q.event_date, p.event_date) AS event_date,
               COALESCE(q.venue, p.venue) AS venue,
               COALESCE(q.start_time, p.start_time) AS start_time,
               COALESCE(q.end_time, p.end_time) AS end_time,
               COALESCE(q.services, p.services) AS services,
               COALESCE(q.selections, p.selections) AS selections
        FROM prestations p
        LEFT JOIN quotes q ON q.id=p.source_quote_id
        WHERE p.id=:id
        LIMIT 1
    ");
    $stmt->execute([':id'=>$detailId]);
    $prestation = $stmt->fetch();

    if (!$prestation) {
        http_response_code(404);
        exit('Prestation introuvable');
    }

    $commercialQuote = null;
    if (!empty($prestation['quote_id'])) {
        $commercialStmt = $pdo->prepare("
            SELECT quote_number, amount_cents
            FROM commercial_quotes
            WHERE source_quote_id=:quote_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $commercialStmt->execute([':quote_id'=>$prestation['quote_id']]);
        $commercialQuote = $commercialStmt->fetch() ?: null;
    }

    $defaultTotalCents = $prestation['total_amount_cents'];
    if ($defaultTotalCents === null && $commercialQuote) {
        $defaultTotalCents = (int)$commercialQuote['amount_cents'];
    }

    $totalCents = $defaultTotalCents !== null ? (int)$defaultTotalCents : null;
    $depositCents = $prestation['deposit_amount_cents'] !== null
        ? (int)$prestation['deposit_amount_cents']
        : ($totalCents !== null ? (int)round($totalCents * 0.30) : null);
    $receivedCents = !empty($prestation['deposit_received']) && $depositCents !== null ? $depositCents : 0;
    $remainingCents = $totalCents !== null ? max(0, $totalCents - $receivedCents) : null;

    $services = array_values(array_filter(array_map('trim', explode('|', (string)($prestation['services'] ?? '')))));
    $selections = array_values(array_filter(array_map('trim', explode('|', (string)($prestation['selections'] ?? '')))));
    $details = array_merge($services, $selections);


    admin_header('Prestation — ' . $prestation['name'], 'prestations');
    ?>
    <style>
    .prestation-detail{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(320px,.7fr);gap:20px;align-items:start}
    .dark-card{border:1px solid rgba(255,255,255,.08);border-radius:20px;background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.018));box-shadow:0 12px 34px rgba(0,0,0,.12)}
    .detail-main{padding:22px}.detail-main h2,.detail-side h2{margin:0 0 6px;font-size:20px}.detail-lead{margin:0 0 20px;color:#8e8e8e;font-size:11px}
    .detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .detail-field{padding:14px;border:1px solid rgba(255,255,255,.07);border-radius:13px;background:rgba(255,255,255,.02)}
    .detail-field span{display:block;margin-bottom:5px;color:#777;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.detail-field strong{font-size:13px;color:#f5f5f5}.detail-field small{display:block;margin-top:4px;color:#8c8c8c;font-size:10px;line-height:1.4}
    .detail-side{padding:22px;position:sticky;top:22px}.tracking-form{display:grid;gap:12px}.tracking-form .field label{color:#aaa}.tracking-form input[type=text],.tracking-form textarea{width:100%;box-sizing:border-box}
    .tracking-check{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid rgba(255,255,255,.07);border-radius:12px;background:rgba(255,255,255,.02)}.tracking-check input{width:17px;height:17px}.tracking-check span{font-size:12px;font-weight:700}.tracking-check small{display:block;color:#888;font-weight:400;margin-top:2px}
    .money-preview{display:grid;grid-template-columns:1fr 1fr;gap:9px}.money-box{padding:12px;border:1px solid rgba(255,255,255,.07);border-radius:12px;background:rgba(255,255,255,.02)}.money-box span{display:block;color:#777;font-size:9px;margin-bottom:4px}.money-box strong{font-size:17px}
    .meeting-card{padding:15px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(255,255,255,.02);display:grid;gap:11px}.meeting-card h3{margin:0;font-size:14px}.meeting-status{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}.meeting-status label{display:flex;align-items:center;justify-content:center;gap:6px;padding:9px;border:1px solid rgba(255,255,255,.08);border-radius:10px;font-size:10px;font-weight:700;cursor:pointer}.meeting-status input{width:14px;height:14px}.meeting-date[hidden]{display:none}    .detail-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    @media(max-width:950px){.prestation-detail{grid-template-columns:1fr}.detail-side{position:static}}@media(max-width:650px){.detail-grid,.money-preview{grid-template-columns:1fr}}
    </style>

    <?php if (isset($_GET['created'])): ?><div class="flash flash--success">Prestation ajoutée manuellement.</div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="flash flash--success">Suivi de la prestation enregistré.</div><?php endif; ?>

    <div class="prestation-detail">
      <section class="dark-card detail-main">
        <h2><?= e($prestation['name']) ?></h2>
        <p class="detail-lead">Toutes les informations principales de la prestation acceptée.</p>

        <div class="detail-grid">
          <div class="detail-field"><span>Date</span><strong><?= $prestation['event_date'] ? e(date('d/m/Y', strtotime($prestation['event_date']))) : 'À définir' ?></strong><small><?= e(($prestation['start_time'] ?: '—') . ' → ' . ($prestation['end_time'] ?: '—')) ?></small></div>
          <div class="detail-field"><span>Événement</span><strong><?= e($prestation['event_type'] ?: '—') ?></strong><small><?= e($prestation['venue'] ?: 'Lieu à définir') ?></small></div>
          <div class="detail-field"><span>Client</span><strong><?= e($prestation['email'] ?: '—') ?></strong><small><?= e($prestation['phone'] ?: 'Téléphone non renseigné') ?></small></div>
          <div class="detail-field"><span>Prestation</span><strong><?= e($details ? implode(' · ', $details) : 'À préciser') ?></strong></div>
        </div>

        <div class="detail-actions">
          <?php if (!empty($prestation['quote_id'])): ?>
            <a class="btn" href="devis.php?id=<?= (int)$prestation['quote_id'] ?>">Voir la demande →</a>
            <a class="btn" href="documents.php?create_from=<?= (int)$prestation['quote_id'] ?>">Documents →</a>
          <?php else: ?>
            <span class="follow-pill is-done">Ajoutée manuellement</span>
          <?php endif; ?>
          <a class="admin-link" href="prestations.php">← Retour aux prestations</a>
        </div>
      </section>

      <aside class="dark-card detail-side">
        <h2>Suivi</h2>
        <p class="detail-lead">Contrat, acompte et montants de cette prestation.</p>

        <form method="post" class="tracking-form">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$prestation['id'] ?>">

          <div class="field">
            <label for="total_amount">Montant total</label>
            <input id="total_amount" name="total_amount" type="text" inputmode="decimal" value="<?= $totalCents !== null ? e(number_format($totalCents / 100, 2, ',', '')) : '' ?>" placeholder="Ex. 950,00">
          </div>

          <div class="field">
            <label for="deposit_amount">Acompte</label>
            <input id="deposit_amount" name="deposit_amount" type="text" inputmode="decimal" value="<?= $depositCents !== null ? e(number_format($depositCents / 100, 2, ',', '')) : '' ?>" placeholder="30 % par défaut">
          </div>

          <label class="tracking-check">
            <input type="checkbox" name="contract_signed" value="1" <?= !empty($prestation['contract_signed']) ? 'checked' : '' ?>>
            <span>Contrat signé<small><?= !empty($prestation['contract_signed_at']) ? 'Enregistré le ' . e(date('d/m/Y', strtotime($prestation['contract_signed_at']))) : 'À confirmer lorsque le contrat est signé.' ?></small></span>
          </label>

          <label class="tracking-check">
            <input type="checkbox" name="deposit_received" value="1" <?= !empty($prestation['deposit_received']) ? 'checked' : '' ?>>
            <span>Acompte reçu<small><?= !empty($prestation['deposit_received_at']) ? 'Enregistré le ' . e(date('d/m/Y', strtotime($prestation['deposit_received_at']))) : 'À confirmer lorsque le paiement est reçu.' ?></small></span>
          </label>

          <div class="meeting-card">
            <h3>Rendez-vous client</h3>
            <div class="meeting-status">
              <label><input type="radio" name="client_meeting_status" value="todo" <?= ($prestation['client_meeting_status'] ?? 'todo') === 'todo' ? 'checked' : '' ?>>À prendre</label>
              <label><input type="radio" name="client_meeting_status" value="planned" <?= ($prestation['client_meeting_status'] ?? 'todo') === 'planned' ? 'checked' : '' ?>>Planifié</label>
              <label><input type="radio" name="client_meeting_status" value="done" <?= ($prestation['client_meeting_status'] ?? 'todo') === 'done' ? 'checked' : '' ?>>Réalisé</label>
            </div>

            <div class="field meeting-date" id="meeting-date-field" <?= ($prestation['client_meeting_status'] ?? 'todo') === 'planned' ? '' : 'hidden' ?>>
              <label for="client_meeting_at">Date et heure du rendez-vous</label>
              <input id="client_meeting_at" name="client_meeting_at" type="datetime-local" value="<?= !empty($prestation['client_meeting_at']) ? e(date('Y-m-d\TH:i', strtotime($prestation['client_meeting_at']))) : '' ?>">
            </div>

            <div class="field">
              <label for="client_meeting_notes">Notes du rendez-vous</label>
              <textarea id="client_meeting_notes" name="client_meeting_notes" rows="3" placeholder="Ex. points à aborder, lieu du rendez-vous..."><?= e((string)($prestation['client_meeting_notes'] ?? '')) ?></textarea>
            </div>
          </div>

          <div class="money-preview">
            <div class="money-box"><span>Encaissé</span><strong><?= number_format($receivedCents / 100, 2, ',', ' ') ?> €</strong></div>
            <div class="money-box"><span>Reste à encaisser</span><strong><?= $remainingCents !== null ? number_format($remainingCents / 100, 2, ',', ' ') . ' €' : '—' ?></strong></div>
          </div>

          <div class="field">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="4" placeholder="Informations utiles pour cette prestation..."><?= e((string)($prestation['notes'] ?? '')) ?></textarea>
          </div>

          <button class="btn btn--primary" type="submit" name="action" value="save">Enregistrer le suivi</button>
        </form>
      </aside>
    </div>
    <script>
    (() => {
      const radios = document.querySelectorAll('input[name="client_meeting_status"]');
      const dateField = document.getElementById('meeting-date-field');
      const dateInput = document.getElementById('client_meeting_at');
      if (!radios.length || !dateField || !dateInput) return;
      const syncMeeting = () => {
        const selected = document.querySelector('input[name="client_meeting_status"]:checked');
        const planned = selected && selected.value === 'planned';
        dateField.hidden = !planned;
        dateInput.required = planned;
      };
      radios.forEach(radio => radio.addEventListener('change', syncMeeting));
      syncMeeting();
    })();
    </script>
    <?php
    admin_footer();
    exit;
}

$upcoming = $pdo->query("
    SELECT
        p.*,
        q.id AS quote_id,
        COALESCE(q.name, p.client_name) AS name,
        COALESCE(q.email, p.client_email) AS email,
        COALESCE(q.phone, p.client_phone) AS phone,
        COALESCE(q.event_type, p.event_type) AS event_type,
        COALESCE(q.event_date, p.event_date) AS event_date,
        COALESCE(q.venue, p.venue) AS venue,
        COALESCE(q.start_time, p.start_time) AS start_time,
        COALESCE(q.end_time, p.end_time) AS end_time,
        COALESCE(q.services, p.services) AS services,
        COALESCE(q.selections, p.selections) AS selections,
        (
            SELECT cq.amount_cents
            FROM commercial_quotes cq
            WHERE cq.source_quote_id = q.id
            ORDER BY cq.id DESC
            LIMIT 1
        ) AS commercial_amount_cents
    FROM prestations p
    LEFT JOIN quotes q ON q.id = p.source_quote_id
    WHERE (p.source_quote_id IS NULL OR q.status = 'accepted')
      AND (
        COALESCE(q.event_date, p.event_date) IS NULL
        OR COALESCE(q.event_date, p.event_date) = ''
        OR COALESCE(q.event_date, p.event_date) >= date('now','localtime')
      )
    ORDER BY
      CASE WHEN COALESCE(q.event_date, p.event_date) IS NULL OR COALESCE(q.event_date, p.event_date) = '' THEN 1 ELSE 0 END,
      COALESCE(q.event_date, p.event_date) ASC,
      COALESCE(q.name, p.client_name) ASC
")->fetchAll();

$datedUpcoming = array_values(array_filter($upcoming, static fn(array $row): bool => !empty($row['event_date'])));
$nextPrestation = $datedUpcoming[0] ?? null;
$totalUpcoming = count($upcoming);
$amountsKnown = 0;
$knownRevenueCents = 0;

foreach ($upcoming as &$row) {
    $effectiveAmount = $row['total_amount_cents'] !== null
        ? (int)$row['total_amount_cents']
        : ($row['commercial_amount_cents'] !== null ? (int)$row['commercial_amount_cents'] : null);
    $row['effective_amount_cents'] = $effectiveAmount;
    if ($effectiveAmount !== null && $effectiveAmount > 0) {
        $amountsKnown++;
        $knownRevenueCents += $effectiveAmount;
    }
}
unset($row);

admin_header('Prestations', 'prestations');
?>
<style>
.prestations-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
.prestations-stat{padding:18px 20px;border:1px solid rgba(255,255,255,.08);border-radius:18px;background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.018));box-shadow:0 10px 28px rgba(0,0,0,.10)}
.prestations-stat span{display:block;margin-bottom:6px;color:#787878;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.09em}.prestations-stat strong{display:block;color:#f5f5f5;font-size:25px;line-height:1.1;letter-spacing:-.04em}.prestations-stat small{display:block;margin-top:5px;color:#858585;font-size:10px;line-height:1.4}
.prestations-panel{border:1px solid rgba(255,255,255,.08);border-radius:20px;background:linear-gradient(180deg,rgba(255,255,255,.028),rgba(255,255,255,.012));box-shadow:0 12px 34px rgba(0,0,0,.12);overflow:hidden}
.prestations-panel__head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px 15px;border-bottom:1px solid rgba(255,255,255,.07)}.prestations-panel__head h2{margin:0;font-size:20px;color:#f4f4f4}.prestations-panel__head p{margin:4px 0 0;color:#858585;font-size:11px}
.prestations-list{display:grid;gap:8px;padding:14px}
.prestation-row{display:grid;grid-template-columns:105px minmax(170px,1fr) minmax(220px,1.35fr) minmax(190px,1fr) auto;gap:17px;align-items:center;padding:14px;border:1px solid rgba(255,255,255,.07);border-radius:13px;background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.018));transition:.18s ease}.prestation-row:hover{border-color:rgba(255,66,66,.18);background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.025));transform:translateY(-1px)}
.prestation-date{display:flex;flex-direction:column;gap:3px}.prestation-date strong{font-size:15px;color:#f3f3f3}.prestation-date span{color:#858585;font-size:10px}.prestation-client strong{display:block;margin-bottom:3px;font-size:13px;color:#f4f4f4}.prestation-client span{display:block;color:#858585;font-size:10px}.prestation-event strong{display:block;margin-bottom:3px;font-size:12px;color:#e8e8e8}.prestation-event span{display:block;color:#858585;font-size:10px;line-height:1.4}
.prestation-follow{display:flex;gap:6px;flex-wrap:wrap}.follow-pill{display:inline-flex;align-items:center;padding:5px 8px;border:1px solid rgba(255,255,255,.08);border-radius:999px;color:#aaa;font-size:9px;font-weight:700}.follow-pill.is-done{border-color:rgba(70,180,100,.18);background:rgba(70,180,100,.07);color:#8ed49e}.follow-pill.is-todo{border-color:rgba(255,80,80,.16);background:rgba(255,80,80,.06);color:#ff7c7c}
.prestation-money{margin-top:6px;color:#858585;font-size:9px}.prestation-actions{display:flex;align-items:center;gap:10px;justify-content:flex-end;white-space:nowrap}.prestation-empty{padding:34px 22px;text-align:center;color:#777;font-size:12px}
@media(max-width:1100px){.prestation-row{grid-template-columns:90px 1fr 1.2fr auto}.prestation-follow{grid-column:2/4}.prestation-actions{grid-column:4;grid-row:1/3}}@media(max-width:720px){.prestations-summary{grid-template-columns:1fr}.prestations-panel__head{align-items:flex-start;flex-direction:column}.prestation-row{grid-template-columns:1fr;gap:9px}.prestation-follow,.prestation-actions{grid-column:auto;grid-row:auto}.prestation-actions{justify-content:flex-start}}
</style>

<section class="prestations-summary">
  <div class="prestations-stat">
    <span>Prestations à venir</span>
    <strong><?= $totalUpcoming ?></strong>
    <small>Demandes acceptées encore à réaliser.</small>
  </div>
  <div class="prestations-stat">
    <span>Prochaine prestation</span>
    <strong><?= $nextPrestation ? e(date('d/m/Y', strtotime($nextPrestation['event_date']))) : '—' ?></strong>
    <small><?= $nextPrestation ? e($nextPrestation['name'] . ' · ' . $nextPrestation['event_type']) : 'Aucune prestation datée.' ?></small>
  </div>
  <div class="prestations-stat">
    <span>Montants renseignés</span>
    <strong><?= $amountsKnown ?> / <?= $totalUpcoming ?></strong>
    <small><?= $amountsKnown > 0 ? 'Total connu : ' . e(number_format($knownRevenueCents / 100, 0, ',', ' ')) . ' €' : 'Ajoute les montants depuis les fiches prestations.' ?></small>
  </div>
</section>

<section class="prestations-panel">
  <div class="prestations-panel__head">
    <div>
      <h2>Prestations à venir</h2>
      <p>Suivi des contrats, acomptes et informations utiles avant chaque événement.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <a class="btn btn--primary" href="prestations.php?new=1">+ Ajouter une prestation</a>
      <a class="admin-link" href="devis.php?status=accepted">Demandes acceptées →</a>
    </div>
  </div>

  <?php if (!$upcoming): ?>
    <div class="prestation-empty">Aucune prestation à venir pour le moment.</div>
  <?php else: ?>
    <div class="prestations-list">
      <?php foreach ($upcoming as $row): ?>
        <?php
          $services = array_values(array_filter(array_map('trim', explode('|', (string)($row['services'] ?? '')))));
          $selections = array_values(array_filter(array_map('trim', explode('|', (string)($row['selections'] ?? '')))));
          $detailParts = array_merge($services, $selections);
          $detailText = $detailParts ? implode(' · ', $detailParts) : 'Prestation à préciser';
        ?>
        <article class="prestation-row">
          <div class="prestation-date">
            <strong><?= $row['event_date'] ? e(date('d/m/Y', strtotime($row['event_date']))) : 'À définir' ?></strong>
            <span><?= ($row['start_time'] || $row['end_time']) ? e(($row['start_time'] ?: '—') . ' → ' . ($row['end_time'] ?: '—')) : 'Horaires à définir' ?></span>
          </div>

          <div class="prestation-client">
            <strong><?= e($row['name']) ?></strong>
            <span><?= e($row['event_type'] ?: 'Événement') ?> · <?= e($row['venue'] ?: 'Lieu à définir') ?></span>
          </div>

          <div class="prestation-event">
            <strong><?= e($detailText) ?></strong>
            <span><?= e($row['email'] ?: '—') ?></span>
          </div>

          <div>
            <div class="prestation-follow">
              <span class="follow-pill <?= !empty($row['contract_signed']) ? 'is-done' : 'is-todo' ?>"><?= !empty($row['contract_signed']) ? 'Contrat signé' : 'Contrat à faire' ?></span>
              <span class="follow-pill <?= !empty($row['deposit_received']) ? 'is-done' : 'is-todo' ?>"><?= !empty($row['deposit_received']) ? 'Acompte reçu' : 'Acompte à recevoir' ?></span>
            </div>
            <div class="prestation-money">
              <?= $row['effective_amount_cents'] !== null && $row['effective_amount_cents'] > 0 ? e(number_format($row['effective_amount_cents'] / 100, 2, ',', ' ')) . ' €' : 'Montant à renseigner' ?>
            </div>
          </div>

          <div class="prestation-actions">
            <a class="admin-link" href="prestations.php?id=<?= (int)$row['id'] ?>">Ouvrir →</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php admin_footer(); ?>
