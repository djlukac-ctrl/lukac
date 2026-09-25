<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

$quoteStatuses = ['new','read','sent','accepted','refused'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'update_status');

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM quotes WHERE id=:id');
        $stmt->execute([':id' => $id]);
        header('Location: devis.php?deleted=1');
        exit;
    }

    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, $quoteStatuses, true)) {
        $stmt = $pdo->prepare('UPDATE quotes SET status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        header('Location: devis.php?id=' . $id . '&saved=1');
        exit;
    }
}

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id=:id');
    $stmt->execute([':id' => $id]);
    $quote = $stmt->fetch();
    if (!$quote) {
        http_response_code(404);
        exit('Demande introuvable');
    }
    if ($quote['status'] === 'new') {
        $pdo->prepare("UPDATE quotes SET status='read', updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute([':id' => $id]);
        $quote['status'] = 'read';
    }

    $services = array_values(array_filter(array_map('trim', explode('|', (string)($quote['services'] ?? '')))));
    $selections = array_values(array_filter(array_map('trim', explode('|', (string)($quote['selections'] ?? '')))));
    $formulaNames = ['Essentiel', 'Ambiance', 'Expérience'];
    $formulas = array_values(array_intersect($selections, $formulaNames));
    $options = array_values(array_diff($selections, $formulaNames));

    admin_header('Demande #' . $id, 'devis');
    if (isset($_GET['saved'])): ?><div class="flash flash--success">Statut mis à jour.</div><?php endif; ?>
    <div class="quote-detail">
      <section class="quote-main">
        <?php
          $postalLine = (string)($quote['postal_address'] ?? '');
          $clientAddress = $postalLine ?: '—';
          $clientPostcode = '—';
          $clientCity = '—';
          if ($postalLine && preg_match('/^(.*),\\s*(\\d{5})\\s+(.+)$/u', $postalLine, $postalMatch)) {
              $clientAddress = trim($postalMatch[1]);
              $clientPostcode = trim($postalMatch[2]);
              $clientCity = trim($postalMatch[3]);
          }

          $fullName = trim((string)($quote['name'] ?? ''));
          $nameParts = preg_split('/\\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
          $clientLastName = $nameParts ? array_shift($nameParts) : '—';
          $clientFirstName = $nameParts ? implode(' ', $nameParts) : '—';

          $quoteLineName = $services ? implode(', ', $services) : 'Prestation';
          $formulaText = $formulas ? implode(', ', $formulas) : 'Non renseignée';
          $venueText = $quote['venue'] ?: 'À définir';

          $djenesisClient = trim(
              "Nom : " . $clientLastName . "\n" .
              "Prénom : " . $clientFirstName . "\n" .
              "E-mail : " . ($quote['email'] ?: '—') . "\n" .
              "Téléphone : " . ($quote['phone'] ?: '—') . "\n" .
              "Adresse : " . $clientAddress . "\n" .
              "Ville : " . $clientCity . "\n" .
              "Code postal : " . $clientPostcode . "\n" .
              "Pays : France"
          );

          $lineParts = [
              "Prestation : " . $quoteLineName,
              "Horaires : " . (($quote['start_time'] ?: '—') . " → " . ($quote['end_time'] ?: '—')),
              "Formule : " . $formulaText
          ];
          foreach ($options as $option) {
              $lineParts[] = "Option : " . $option;
          }
          $lineParts[] = "Lieu : " . $venueText . " (distance en cours)";
          $djenesisLine = implode("\n", $lineParts);

          $djenesisQuoteInfo = trim(
              "Date de prestation : " . ($quote['event_date'] ? date('d/m/Y', strtotime($quote['event_date'])) : 'À définir') . "\n" .
              "Détails :\n" .
              "Recommandé par : " . ($quote['referral'] ?: 'Non renseigné') . "\n" .
              "Invités : " . ($quote['guest_count'] ? (int)$quote['guest_count'] : '—') . "\n" .
              "Projet : " . ($quote['message'] ?: 'Aucun message complémentaire.') . "\n" .
              "Lieu de prestation : " . ($quote['venue'] ?: 'À définir') . "\n" .
              "Type d’événement : " . ($quote['event_type'] ?: '—')
          );

          $djenesisFull = $djenesisClient . "\n\n" . $djenesisLine . "\n\n" . $djenesisQuoteInfo;
        ?>
        <section class="form-card quote-block djenesis-block">
          <div class="quote-block__heading">
            <span class="quote-section-kicker">Préparation devis</span>
            <h2>Préparer pour Djenesis</h2>
            <p class="djenesis-block__lead">Les informations sont déjà regroupées dans un format prêt à copier dans Djenesis.</p>
          </div>

          <div class="djenesis-grid djenesis-grid--three">
            <div class="djenesis-card">
              <div class="djenesis-card__head">
                <div><span>01</span><strong>Créer le client</strong></div>
              </div>
              <div class="djenesis-service-preview djenesis-copy-list">
                <p><span>Nom</span><strong><?= e($clientLastName) ?></strong><button class="djenesis-line-copy" type="button" data-copy-text="<?= e($clientLastName) ?>">Copier</button></p>
                <p><span>Prénom</span><strong><?= e($clientFirstName) ?></strong><button class="djenesis-line-copy" type="button" data-copy-text="<?= e($clientFirstName) ?>">Copier</button></p>
                <p><span>E-mail</span><?= e($quote['email'] ?: '—') ?><button class="djenesis-line-copy" type="button" data-copy-text="<?= e($quote['email'] ?: '') ?>">Copier</button></p>
                <p><span>Téléphone</span><?= e($quote['phone'] ?: '—') ?><button class="djenesis-line-copy" type="button" data-copy-text="<?= e($quote['phone'] ?: '') ?>">Copier</button></p>
                <p><span>Adresse</span><?= e($clientAddress) ?><button class="djenesis-line-copy" type="button" data-copy-text="<?= e($clientAddress === '—' ? '' : $clientAddress) ?>">Copier</button></p>
                <p><span>Ville</span><?= e($clientCity) ?><button class="djenesis-line-copy" type="button" data-copy-text="<?= e($clientCity === '—' ? '' : $clientCity) ?>">Copier</button></p>
                <p><span>Code postal</span><?= e($clientPostcode) ?><button class="djenesis-line-copy" type="button" data-copy-text="<?= e($clientPostcode === '—' ? '' : $clientPostcode) ?>">Copier</button></p>
                <p><span>Pays</span>France<button class="djenesis-line-copy" type="button" data-copy-text="France">Copier</button></p>
              </div>
              <pre id="djenesis-client" hidden><?= e($djenesisClient) ?></pre>
            </div>

            <div class="djenesis-card">
              <div class="djenesis-card__head">
                <div><span>02</span><strong>Ligne du devis</strong></div>
              </div>
              <div class="djenesis-service-preview">
                <p><span>Prestation</span><strong><?= e($quoteLineName) ?></strong></p>
                <p><span>Horaires</span><strong><?= e(($quote['start_time'] ?: '—') . ' → ' . ($quote['end_time'] ?: '—')) ?></strong></p>
                <p><span>Formule</span><strong><?= e($formulaText) ?></strong></p>
                <?php if ($options): foreach ($options as $option): ?>
                  <p><span>Option</span><?= e($option) ?></p>
                <?php endforeach; else: ?>
                  <p><span>Option</span>Aucune</p>
                <?php endif; ?>
                <p><span>Lieu</span><strong id="djenesis-venue-distance" data-venue="<?= e($venueText) ?>"><?= e($venueText) ?> <small>(calcul…)</small></strong></p>
              </div>
              <pre id="djenesis-line" hidden><?= e($djenesisLine) ?></pre>
            </div>

            <div class="djenesis-card">
              <div class="djenesis-card__head">
                <div><span>03</span><strong>Informations devis</strong></div>
              </div>
              <div class="djenesis-service-preview">
                <p><span>Date</span><strong><?= $quote['event_date'] ? e(date('d/m/Y', strtotime($quote['event_date']))) : 'À définir' ?></strong></p>

                <div class="djenesis-details">
                  <div class="djenesis-details__head"><strong class="djenesis-details__title">Détails</strong><button class="djenesis-line-copy djenesis-details-copy" type="button" data-copy-target="djenesis-details-copy">Copier</button></div>
                  <p><span>Recommandé par</span><strong><?= e($quote['referral'] ?: 'Non renseigné') ?></strong></p>
                  <p><span>Invités</span><strong><?= $quote['guest_count'] ? (int)$quote['guest_count'] : '—' ?></strong></p>
                  <p class="djenesis-details__project"><span>Projet</span><?= nl2br(e($quote['message'] ?: 'Aucun message complémentaire.')) ?></p>
                </div>

                <p><span>Lieu</span><strong><?= e($quote['venue'] ?: 'À définir') ?></strong></p>
                <p><span>Type</span><?= e($quote['event_type'] ?: '—') ?></p>
              </div>
              <pre id="djenesis-quote-info" hidden><?= e($djenesisQuoteInfo) ?></pre>
              <pre id="djenesis-details-copy" hidden><?= e(
                "Recommandé par : " . ($quote['referral'] ?: 'Non renseigné') . "\n" .
                "Invités : " . ($quote['guest_count'] ? (int)$quote['guest_count'] : '—') . "\n" .
                "Projet : " . ($quote['message'] ?: 'Aucun message complémentaire.') . "\n" .
                "Lieu : " . ($quote['venue'] ?: 'À définir')
              ) ?></pre>
            </div>          </div>

          <div class="djenesis-actions">
            <a class="btn djenesis-open" href="https://djenesis.net/dj/quotes/create" target="_blank" rel="noopener noreferrer">Créer le devis dans Djenesis ↗</a>
          </div>
        </section>

        <style>
          .quote-detail{gap:20px;align-items:start}
          .quote-main{min-width:0}
          .djenesis-block{overflow:hidden;border-radius:22px!important;border:1px solid rgba(24,23,22,.09)!important;background:linear-gradient(180deg,#fff 0%,#fcfaf7 100%)!important;box-shadow:0 18px 45px rgba(50,38,28,.06)!important}
          .djenesis-block .quote-block__heading{padding-bottom:18px;margin-bottom:2px;border-bottom:1px solid rgba(24,23,22,.07)}
          .djenesis-block .quote-section-kicker{display:inline-block;margin-bottom:6px;color:#c93431;font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
          .djenesis-block .quote-block__heading h2{margin:0;font-size:24px;letter-spacing:-.035em}
          .djenesis-block__lead{margin:8px 0 0;color:#7b746e;font-size:12px;line-height:1.6}
          .djenesis-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.djenesis-grid--three{grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:18px}
          .djenesis-card{position:relative;border:1px solid rgba(24,23,22,.09);border-radius:16px;background:#faf8f5;overflow:hidden;box-shadow:0 8px 22px rgba(50,38,28,.035);transition:.2s ease}
          .djenesis-card:hover{transform:translateY(-1px);box-shadow:0 12px 28px rgba(50,38,28,.055);border-color:rgba(24,23,22,.14)}
          .djenesis-card:before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,#c93431 0%,rgba(201,52,49,.18) 72%,transparent 100%)}
          .djenesis-card__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px 13px 16px;border-bottom:1px solid rgba(24,23,22,.07);background:rgba(255,255,255,.92)}
          .djenesis-card__head>div{display:flex;align-items:center;gap:10px}
          .djenesis-card__head span{display:grid;place-items:center;width:25px;height:25px;border-radius:50%;background:#181716;color:#fff;font-size:9px;font-weight:800;box-shadow:0 5px 12px rgba(24,23,22,.12)}
          .djenesis-card__head strong{font:700 13px 'Space Grotesk',sans-serif;color:#181716}
          .djenesis-card pre{margin:0;padding:15px;white-space:pre-wrap;word-break:break-word;font:500 12px/1.65 'DM Sans',Arial,sans-serif;color:#514b46}
          .djenesis-service-preview{padding:16px;display:grid;gap:9px}
          .djenesis-service-preview p{display:grid;grid-template-columns:92px minmax(0,1fr);gap:10px;margin:0;color:#514b46;font-size:12px;line-height:1.5;align-items:center}
          .djenesis-copy-list p{grid-template-columns:82px minmax(0,1fr) auto;padding-bottom:7px;border-bottom:1px dashed rgba(24,23,22,.08)}
          .djenesis-copy-list p:last-child{padding-bottom:0;border-bottom:0}
          .djenesis-line-copy{border:1px solid rgba(24,23,22,.11);border-radius:999px;background:#fff;color:#6a625b;padding:4px 9px;font-size:9px;font-weight:700;cursor:pointer;transition:.18s ease}
          .djenesis-line-copy:hover{border-color:rgba(201,52,49,.30);color:#c93431;background:#fff8f7}
          .djenesis-service-preview p span{color:#948b83;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
          .djenesis-service-preview p strong{color:#181716;font-weight:800}
          .djenesis-service-preview p strong small{font:600 10px 'DM Sans',Arial,sans-serif;color:#8a8179}
          .djenesis-details{margin-top:4px;padding:13px;border:1px solid rgba(201,52,49,.10);border-radius:13px;background:linear-gradient(145deg,#fff,#fff8f7);display:grid;gap:9px}
          .djenesis-details__head{display:flex;align-items:center;justify-content:space-between;gap:10px}
          .djenesis-details__title{font:800 10px 'Space Grotesk',sans-serif;color:#c93431;text-transform:uppercase;letter-spacing:.10em}
          .djenesis-details-copy{padding:4px 9px!important}
          .djenesis-details p{grid-template-columns:92px minmax(0,1fr)!important}
          .djenesis-details__project{align-items:start!important;padding-top:8px;border-top:1px solid rgba(201,52,49,.08)}
          .djenesis-card .btn{padding:7px 11px;font-size:9px;border-radius:999px}
          .djenesis-card .djenesis-copy{background:#fff}
          .djenesis-card .djenesis-copy:hover{color:#c93431;border-color:rgba(201,52,49,.28)}
          .djenesis-open{background:#fff;color:#181716}
          .djenesis-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid rgba(24,23,22,.07)}
          .djenesis-actions .btn{min-height:38px;border-radius:999px}
          .djenesis-copy-status{color:#4f7d3d;font-size:11px;font-weight:700;min-height:16px}
          .quote-followup{position:sticky;top:22px;border-radius:20px!important;border:1px solid rgba(24,23,22,.09)!important;background:#fff!important;box-shadow:0 16px 38px rgba(50,38,28,.055)!important}
          .quote-followup h2{margin-bottom:14px;font-size:19px;letter-spacing:-.02em}
          .quote-followup .admin-form{gap:10px}
          .quote-followup .field select{background:#faf8f5}
          .quote-followup .btn{min-height:40px;border-radius:999px}
          .quote-followup .btn--primary{box-shadow:0 7px 18px rgba(24,23,22,.10)}
          .quote-followup .btn--danger{background:#fff8f7;border-color:rgba(201,52,49,.16);color:#c93431}
          .quote-followup .admin-link{display:inline-block;margin-top:2px}
          @media(max-width:1100px){.djenesis-grid--three{grid-template-columns:1fr 1fr}.quote-followup{position:static}}
          @media(max-width:800px){.djenesis-grid,.djenesis-grid--three{grid-template-columns:1fr}.djenesis-card:hover{transform:none}.djenesis-block{border-radius:16px!important}}
        </style>

        <script>
          (() => {
            const status = document.querySelector('.djenesis-copy-status');
            const fallbackCopy = (text) => {
              const textarea = document.createElement('textarea');
              textarea.value = text;
              textarea.setAttribute('readonly', '');
              textarea.style.position = 'fixed';
              textarea.style.opacity = '0';
              document.body.appendChild(textarea);
              textarea.select();
              document.execCommand('copy');
              textarea.remove();
            };

            const venueDistance = document.getElementById('djenesis-venue-distance');
            const lineCopy = document.getElementById('djenesis-line');
            const fullCopy = document.getElementById('djenesis-full');
            const originAddress = '65 rue de Verdun, 52290 Eclaron, France';

            const geocode = async (address) => {
              const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=fr&q=' + encodeURIComponent(address);
              const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
              if (!response.ok) throw new Error('Geocoding failed');
              const results = await response.json();
              if (!Array.isArray(results) || !results[0]) throw new Error('Address not found');
              return { lat: Number(results[0].lat), lon: Number(results[0].lon) };
            };

            const updateDistanceInCopies = (venue, distanceText) => {
              const replacement = 'Lieu : ' + venue + ' (' + distanceText + ')';
              if (lineCopy) {
                lineCopy.textContent = lineCopy.textContent.replace(/Lieu : .*?(?: \(distance en cours\))?(?=\n|$)/, replacement);
              }
              if (fullCopy) {
                fullCopy.textContent = fullCopy.textContent.replace(/Lieu : .*?(?: \(distance en cours\))?(?=\n|$)/, replacement);
              }
            };

            const loadDistance = async () => {
              if (!venueDistance) return;
              const venue = venueDistance.dataset.venue || '';
              if (!venue || venue === 'À définir') {
                venueDistance.innerHTML = venue + ' <small>(distance indisponible)</small>';
                return;
              }

              try {
                const [origin, destination] = await Promise.all([
                  geocode(originAddress),
                  geocode(venue + ', France')
                ]);
                const routeUrl = 'https://router.project-osrm.org/route/v1/driving/' +
                  origin.lon + ',' + origin.lat + ';' + destination.lon + ',' + destination.lat +
                  '?overview=false';
                const response = await fetch(routeUrl, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('Routing failed');
                const data = await response.json();
                const meters = data?.routes?.[0]?.distance;
                if (!Number.isFinite(meters)) throw new Error('Distance unavailable');
                const km = Math.round(meters / 1000);
                const distanceText = '≈ ' + km + ' km';
                venueDistance.innerHTML = venue.replace(/</g, '&lt;').replace(/>/g, '&gt;') + ' <small>(' + distanceText + ')</small>';
                updateDistanceInCopies(venue, distanceText);
              } catch (_) {
                venueDistance.innerHTML = venue.replace(/</g, '&lt;').replace(/>/g, '&gt;') + ' <small>(distance indisponible)</small>';
                updateDistanceInCopies(venue, 'distance indisponible');
              }
            };

            loadDistance();

            document.querySelectorAll('.djenesis-line-copy').forEach((button) => {
              button.addEventListener('click', async () => {
                const target = button.dataset.copyTarget ? document.getElementById(button.dataset.copyTarget) : null;
                const text = target ? target.textContent.trim() : (button.dataset.copyText || '');
                if (!text) return;
                try {
                  if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text);
                  else fallbackCopy(text);
                  const original = button.textContent;
                  button.textContent = 'Copié ✓';
                  setTimeout(() => button.textContent = original, 1200);
                } catch (_) {
                  fallbackCopy(text);
                }
              });
            });
          })();
        </script>
      </section>

      <aside class="form-card quote-followup">
        <h2>Suivi de la demande</h2>
        <form method="post" class="admin-form">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="field"><label for="status">Statut</label><select id="status" name="status">
            <?php foreach ($quoteStatuses as $status): ?><option value="<?= e($status) ?>" <?= $quote['status'] === $status ? 'selected' : '' ?>><?= e(quote_status_label($status)) ?></option><?php endforeach; ?>
          </select></div>
          <button class="btn btn--primary" type="submit" name="action" value="update_status">Enregistrer le statut</button>
          <button class="btn btn--danger" type="submit" name="action" value="delete" onclick="return confirm('Supprimer définitivement cette demande de devis ? Cette action est irréversible.');">Supprimer la demande</button>
          <a class="admin-link" href="devis.php">← Retour aux demandes</a>
        </form>
      </aside>
    </div>
    <?php admin_footer(); exit;
}

$statusFilter = (string) ($_GET['status'] ?? '');
$params = [];
$sql = 'SELECT * FROM quotes';
if (in_array($statusFilter, $quoteStatuses, true)) {
    $sql .= ' WHERE status=:status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll();
admin_header('Demandes de devis', 'devis');
if (isset($_GET['deleted'])): ?><div class="flash flash--success">Demande de devis supprimée.</div><?php endif; ?>
<div class="admin-section__head">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn" href="devis.php">Toutes</a>
    <?php foreach ($quoteStatuses as $status): ?><a class="btn" href="devis.php?status=<?= e($status) ?>"><?= e(quote_status_label($status)) ?></a><?php endforeach; ?>
  </div>
</div>
<div class="admin-table-wrap">
<table><thead><tr><th>Client</th><th>Événement</th><th>Date</th><th>Lieu</th><th>Horaires</th><th>Reçue le</th><th>Statut</th><th></th></tr></thead><tbody>
<?php if (!$quotes): ?><tr><td colspan="8">Aucune demande dans cette catégorie.</td></tr><?php else: foreach ($quotes as $quote): ?>
<tr>
  <td><strong><?= e($quote['name']) ?></strong><br><span style="color:#777"><?= e($quote['email']) ?></span></td>
  <td><?= e($quote['event_type']) ?></td>
  <td><?= $quote['event_date'] ? e(date('d/m/Y', strtotime($quote['event_date']))) : 'À définir' ?></td>
  <td><?= e($quote['venue'] ?: '—') ?></td>
  <td><?= e(($quote['start_time'] ?: '—') . ' → ' . ($quote['end_time'] ?: '—')) ?></td>
  <td><?= e(date('d/m/Y H:i', strtotime($quote['created_at']))) ?></td>
  <td><span class="status status--<?= e($quote['status']) ?>"><?= e(quote_status_label($quote['status'])) ?></span></td>
  <td><a class="admin-link" href="devis.php?id=<?= (int) $quote['id'] ?>">Ouvrir →</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php admin_footer(); ?>