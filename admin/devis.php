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
                <button class="btn djenesis-copy" type="button" data-copy-target="djenesis-client">Copier tout</button>
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
                <button class="btn djenesis-copy" type="button" data-copy-target="djenesis-line">Copier tout</button>
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
                <button class="btn djenesis-copy" type="button" data-copy-target="djenesis-quote-info">Copier tout</button>
              </div>
              <div class="djenesis-service-preview">
                <p><span>Date</span><strong><?= $quote['event_date'] ? e(date('d/m/Y', strtotime($quote['event_date']))) : 'À définir' ?></strong></p>

                <div class="djenesis-details">
                  <strong class="djenesis-details__title">Détails</strong>
                  <p><span>Recommandé par</span><strong><?= e($quote['referral'] ?: 'Non renseigné') ?></strong></p>
                  <p><span>Invités</span><strong><?= $quote['guest_count'] ? (int)$quote['guest_count'] : '—' ?></strong></p>
                  <p class="djenesis-details__project"><span>Projet</span><?= nl2br(e($quote['message'] ?: 'Aucun message complémentaire.')) ?></p>
                </div>

                <p><span>Lieu</span><strong><?= e($quote['venue'] ?: 'À définir') ?></strong></p>
                <p><span>Type</span><?= e($quote['event_type'] ?: '—') ?></p>
              </div>
              <pre id="djenesis-quote-info" hidden><?= e($djenesisQuoteInfo) ?></pre>
            </div>          </div>

          <div class="djenesis-actions">
            <button class="btn btn--primary djenesis-copy" type="button" data-copy-target="djenesis-full">Copier tout pour Djenesis</button>
            <a class="btn djenesis-open" href="https://djenesis.net/dj/quotes/create" target="_blank" rel="noopener noreferrer">Créer le devis dans Djenesis ↗</a>
            <span class="djenesis-copy-status" aria-live="polite"></span>
          </div>
          <pre id="djenesis-full" hidden><?= e($djenesisFull) ?></pre>
        </section>

        <style>
          .djenesis-block{overflow:hidden}
          .djenesis-block__lead{margin:7px 0 0;color:#7b746e;font-size:12px;line-height:1.6}
          .djenesis-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.djenesis-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}
          .djenesis-card{border:1px solid rgba(24,23,22,.10);border-radius:14px;background:#faf8f5;overflow:hidden}
          .djenesis-card__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-bottom:1px solid rgba(24,23,22,.08);background:#fff}
          .djenesis-card__head>div{display:flex;align-items:center;gap:9px}
          .djenesis-card__head span{display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#181716;color:#fff;font-size:9px;font-weight:800}
          .djenesis-card__head strong{font:600 13px 'Space Grotesk',sans-serif}
          .djenesis-card pre{margin:0;padding:15px;white-space:pre-wrap;word-break:break-word;font:500 12px/1.65 'DM Sans',Arial,sans-serif;color:#514b46}
          .djenesis-service-preview{padding:15px;display:grid;gap:7px}
          .djenesis-service-preview p{display:grid;grid-template-columns:92px minmax(0,1fr);gap:10px;margin:0;color:#514b46;font-size:12px;line-height:1.5;align-items:center}
          .djenesis-copy-list p{grid-template-columns:82px minmax(0,1fr) auto}
          .djenesis-line-copy{border:1px solid rgba(24,23,22,.12);border-radius:999px;background:#fff;color:#514b46;padding:4px 8px;font-size:9px;font-weight:700;cursor:pointer}
          .djenesis-line-copy:hover{border-color:rgba(24,23,22,.28);color:#181716}
          .djenesis-service-preview p span{color:#8a8179;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
          .djenesis-service-preview p strong{color:#181716;font-weight:800}
          .djenesis-service-preview p strong small{font:600 10px 'DM Sans',Arial,sans-serif;color:#8a8179}
          .djenesis-service-preview__project{padding-top:8px;margin-top:4px!important;border-top:1px solid rgba(24,23,22,.08)}
          .djenesis-details{margin-top:8px;padding:12px;border:1px solid rgba(24,23,22,.08);border-radius:12px;background:#fff;display:grid;gap:8px}
          .djenesis-details__title{font:700 11px 'Space Grotesk',sans-serif;color:#181716;text-transform:uppercase;letter-spacing:.08em}
          .djenesis-details p{grid-template-columns:92px minmax(0,1fr)!important}
          .djenesis-details__project{align-items:start!important}
          .djenesis-card .btn{padding:8px 12px;font-size:10px}
          .djenesis-open{background:#fff;color:#181716}
          .djenesis-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px}
          .djenesis-copy-status{color:#4f7d3d;font-size:11px;font-weight:700;min-height:16px}
          @media(max-width:1100px){.djenesis-grid--three{grid-template-columns:1fr 1fr}}@media(max-width:800px){.djenesis-grid,.djenesis-grid--three{grid-template-columns:1fr}}
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
                const text = button.dataset.copyText || '';
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

            document.querySelectorAll('.djenesis-copy').forEach((button) => {
              button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copyTarget || '');
                if (!target) return;
                const text = target.textContent.trim();

                try {
                  if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                  } else {
                    fallbackCopy(text);
                  }
                  const original = button.textContent;
                  button.textContent = 'Copié ✓';
                  if (status) status.textContent = 'Prêt à coller dans Djenesis.';
                  setTimeout(() => {
                    button.textContent = original;
                    if (status) status.textContent = '';
                  }, 1800);
                } catch (_) {
                  fallbackCopy(text);
                  if (status) status.textContent = 'Informations copiées.';
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
          <a class="btn" href="mailto:<?= e($quote['email']) ?>?subject=Votre%20demande%20de%20devis%20-%20Luka%20C">Répondre par e-mail</a>
          <?php if ($quote['phone']): ?><a class="btn" href="tel:<?= e($quote['phone']) ?>">Appeler le client</a><?php endif; ?>
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