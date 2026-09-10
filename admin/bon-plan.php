<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();
$defaults = [
    'deal.enabled' => '0',
    'deal.badge' => 'Offre dernière minute',
    'deal.date' => 'Date disponible prochainement',
    'deal.title' => 'Une date vient de se libérer.',
    'deal.text' => 'Profitez d’une disponibilité de dernière minute pour votre événement. Contactez-moi rapidement pour vérifier les conditions de l’offre.',
    'deal.image' => '',
];
$stmt = $pdo->prepare('INSERT OR IGNORE INTO content(content_key,value) VALUES(:k,:v)');
foreach ($defaults as $key => $value) $stmt->execute([':k'=>$key, ':v'=>$value]);

function deal_delete_image(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/contenu/')) return;
    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) @unlink($full);
}

function deal_store_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Le téléversement de l’image a échoué.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('L’image dépasse la taille maximale de 5 Mo.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($tmp) === false) {
        throw new RuntimeException('Format non accepté. Utilise une image JPG, PNG ou WEBP.');
    }
    $dir = APP_ROOT . '/uploads/contenu';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de créer le dossier des images.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
        throw new RuntimeException('Impossible d’enregistrer l’image.');
    }
    return 'uploads/contenu/' . $filename;
}

$saved = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $content = site_content();
    $values = [
        'deal.enabled' => !empty($_POST['enabled']) ? '1' : '0',
        'deal.badge' => trim((string)($_POST['badge'] ?? '')),
        'deal.date' => trim((string)($_POST['date'] ?? '')),
        'deal.title' => trim((string)($_POST['title'] ?? '')),
        'deal.text' => trim((string)($_POST['text'] ?? '')),
    ];
    $upsert = $pdo->prepare('INSERT INTO content(content_key,value,updated_at) VALUES(:k,:v,CURRENT_TIMESTAMP) ON CONFLICT(content_key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP');
    foreach ($values as $key => $value) $upsert->execute([':k'=>$key, ':v'=>$value]);

    try {
        if (!empty($_POST['remove_image'])) {
            deal_delete_image($content['deal.image'] ?? null);
            $upsert->execute([':k'=>'deal.image', ':v'=>'']);
        }
        if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newPath = deal_store_image($_FILES['image']);
            deal_delete_image($content['deal.image'] ?? null);
            $upsert->execute([':k'=>'deal.image', ':v'=>$newPath]);
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$content = site_content();
admin_header('Bon plan', 'bon-plan');
?>
<?php if ($saved): ?><div class="flash flash--success">Le bon plan a bien été enregistré.</div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<div class="content-page-heading">
  <div>
    <span class="content-page-heading__eyebrow">Mise en avant</span>
    <h2>Opération dernière minute</h2>
    <p>Prépare ici une offre ponctuelle pour une date qui se libère. Le bloc peut être activé ou masqué à tout moment sans supprimer son contenu.</p>
  </div>
  <a class="btn" href="../index.html#bon-plan" target="_blank" rel="noopener">Voir sur le site ↗</a>
</div>

<p class="content-help">Renseigne les informations de l’offre puis active sa visibilité lorsque tout est prêt. L’image est facultative.</p>

<form method="post" enctype="multipart/form-data" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="content-groups">
    <section class="form-card">
      <h2>Visibilité</h2>
      <div class="fields">
        <div class="field">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <input type="checkbox" name="enabled" value="1" <?= ($content['deal.enabled'] ?? '0') === '1' ? 'checked' : '' ?> style="width:auto">
            <span style="font-size:13px;font-weight:700;color:inherit">Afficher le bon plan sur le site</span>
          </label>
        </div>
      </div>
    </section>

    <section class="form-card">
      <h2>Contenu de l’offre</h2>
      <div class="fields">
        <div class="form-grid">
          <div class="field">
            <label for="deal-badge">Accroche</label>
            <input id="deal-badge" type="text" name="badge" value="<?= e($content['deal.badge'] ?? '') ?>" placeholder="Offre dernière minute">
          </div>

          <div class="field">
            <label for="deal-date">Date / disponibilité</label>
            <input id="deal-date" type="text" name="date" value="<?= e($content['deal.date'] ?? '') ?>" placeholder="Samedi 19 septembre">
          </div>

          <div class="field form-grid__full">
            <label for="deal-title">Titre</label>
            <input id="deal-title" type="text" name="title" value="<?= e($content['deal.title'] ?? '') ?>" placeholder="Une date vient de se libérer.">
          </div>

          <div class="field form-grid__full">
            <label for="deal-text">Texte</label>
            <textarea id="deal-text" name="text" rows="5" placeholder="Présente ici les conditions ou l’avantage proposé."><?= e($content['deal.text'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </section>

    <section class="form-card">
      <h2>Visuel</h2>
      <div class="content-images">
        <div class="content-image-editor">
          <div class="content-image-editor__preview <?= !empty($content['deal.image']) ? 'has-image' : '' ?>"<?= !empty($content['deal.image']) ? ' style="background-image:url(../' . e($content['deal.image']) . ')"' : '' ?>>
            <?php if (empty($content['deal.image'])): ?><span>Aucune image personnalisée</span><?php endif; ?>
          </div>

          <div class="content-image-editor__controls">
            <strong>Image du bon plan</strong>
            <div class="field">
              <label for="deal-image">Remplacer ou ajouter une image</label>
              <input id="deal-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>
            <?php if (!empty($content['deal.image'])): ?>
              <label class="content-image-remove">
                <input type="checkbox" name="remove_image" value="1"> Supprimer l’image actuelle
              </label>
            <?php endif; ?>
            <span style="color:#777;font-size:11px">JPG, PNG ou WEBP — 5 Mo maximum.</span>
          </div>
        </div>
      </div>
      <div style="height:22px"></div>
    </section>
  </div>

  <div class="form-actions admin-actions--sticky">
    <button class="btn btn--primary" type="submit">Enregistrer le bon plan</button>
  </div>
</form>

<?php admin_footer(); ?>
