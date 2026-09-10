<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

$definitions = [
    'Accueil' => [
        'home.hero_line1' => ['Titre principal — ligne 1', 'text'],
        'home.hero_line2' => ['Titre principal — ligne 2', 'text'],
        'home.hero_lead' => ['Texte d’introduction', 'textarea'],
    ],
    'Prestations — introduction' => [
        'prestations.hero_title' => ['Titre', 'text'],
        'prestations.hero_highlight' => ['Titre mis en avant', 'text'],
        'prestations.hero_intro' => ['Texte d’introduction', 'textarea'],
    ],
    'Prestations — DJ' => [
        'prestations.dj.title' => ['Titre', 'text'],
        'prestations.dj.desc' => ['Description', 'textarea'],
        'prestations.dj.items' => ['Points clés — 1 ligne par point', 'textarea'],
    ],
    'Prestations — Animations interactives' => [
        'prestations.animations.title' => ['Titre', 'text'],
        'prestations.animations.desc' => ['Description', 'textarea'],
        'prestations.animations.items' => ['Points clés — 1 ligne par point', 'textarea'],
    ],
    'Prestations — Vin d’honneur' => [
        'prestations.cocktail.title' => ['Titre', 'text'],
        'prestations.cocktail.desc' => ['Description', 'textarea'],
        'prestations.cocktail.items' => ['Points clés — 1 ligne par point', 'textarea'],
    ],
    'Formules — introduction' => [
        'formules.hero_title' => ['Titre', 'text'],
        'formules.hero_highlight' => ['Titre mis en avant', 'text'],
        'formules.hero_intro' => ['Texte d’introduction', 'textarea'],
    ],
    'Formule Essentiel' => [
        'formules.essentiel.title' => ['Titre', 'text'],
        'formules.essentiel.intro' => ['Accroche', 'textarea'],
        'formules.essentiel.items' => ['Éléments inclus — 1 ligne par point', 'textarea'],
    ],
    'Formule Ambiance' => [
        'formules.ambiance.title' => ['Titre', 'text'],
        'formules.ambiance.intro' => ['Accroche', 'textarea'],
        'formules.ambiance.items' => ['Éléments inclus — 1 ligne par point', 'textarea'],
    ],
    'Formule Expérience' => [
        'formules.experience.title' => ['Titre', 'text'],
        'formules.experience.intro' => ['Accroche', 'textarea'],
        'formules.experience.items' => ['Éléments inclus — 1 ligne par point', 'textarea'],
    ],
    'Pack Instant Magique' => [
        'formules.signature1.title' => ['Titre', 'text'],
        'formules.signature1.desc' => ['Description', 'textarea'],
        'formules.signature1.items' => ['Éléments inclus — 1 ligne par point', 'textarea'],
    ],
    'Pack Instant Magique Signature' => [
        'formules.signature2.title' => ['Titre', 'text'],
        'formules.signature2.desc' => ['Description', 'textarea'],
        'formules.signature2.items' => ['Éléments inclus — 1 ligne par point', 'textarea'],
    ],
    'Options à la carte' => [
        'options.fumee.title' => ['Fumée lourde — titre', 'text'],
        'options.fumee.desc' => ['Fumée lourde — description', 'textarea'],
        'options.etincelles.title' => ['Étincelles froides — titre', 'text'],
        'options.etincelles.desc' => ['Étincelles froides — description', 'textarea'],
        'options.eclairage.title' => ['Éclairage mural — titre', 'text'],
        'options.eclairage.desc' => ['Éclairage mural — description', 'textarea'],
        'options.ecran.title' => ['Écran & projecteur — titre', 'text'],
        'options.ecran.desc' => ['Écran & projecteur — description', 'textarea'],
    ],
];

$imageSlots = [
    'Accueil' => ['home_hero' => ['home.hero.image', 'Photo principale de l’accueil']],
    'Prestations — DJ' => ['prestations_dj' => ['prestations.dj.image', 'Image de la prestation DJ']],
    'Prestations — Animations interactives' => ['prestations_animations' => ['prestations.animations.image', 'Image des animations']],
    'Prestations — Vin d’honneur' => ['prestations_cocktail' => ['prestations.cocktail.image', 'Image du vin d’honneur']],
    'Formule Essentiel' => ['formules_essentiel' => ['formules.essentiel.image', 'Image de la formule Essentiel']],
    'Formule Ambiance' => ['formules_ambiance' => ['formules.ambiance.image', 'Image de la formule Ambiance']],
    'Formule Expérience' => ['formules_experience' => ['formules.experience.image', 'Image de la formule Expérience']],
    'Pack Instant Magique' => ['signature_1' => ['formules.signature1.image', 'Image du Pack Instant Magique']],
    'Pack Instant Magique Signature' => ['signature_2' => ['formules.signature2.image', 'Image du Pack Instant Magique Signature']],
    'Options à la carte' => [
        'option_fumee' => ['options.fumee.image', 'Image — Fumée lourde'],
        'option_etincelles' => ['options.etincelles.image', 'Image — Étincelles froides'],
        'option_eclairage' => ['options.eclairage.image', 'Image — Éclairage mural'],
        'option_ecran' => ['options.ecran.image', 'Image — Écran & projecteur'],
    ],
];

function delete_content_image(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/contenu/')) return;
    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) @unlink($full);
}

function store_content_image(array $file): string
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
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
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
    $posted = $_POST['content'] ?? [];
    $allowedKeys = [];
    foreach ($definitions as $group) $allowedKeys = array_merge($allowedKeys, array_keys($group));

    $stmt = $pdo->prepare('INSERT INTO content(content_key,value,updated_at) VALUES(:k,:v,CURRENT_TIMESTAMP) ON CONFLICT(content_key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP');
    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $posted)) {
            $stmt->execute([':k' => $key, ':v' => trim((string)$posted[$key])]);
        }
    }

    $current = site_content();
    try {
        foreach ($imageSlots as $slots) {
            foreach ($slots as $token => [$key, $label]) {
                if (!empty($_POST['remove_image'][$token])) {
                    delete_content_image($current[$key] ?? null);
                    $stmt->execute([':k' => $key, ':v' => '']);
                    $current[$key] = '';
                }

                if (isset($_FILES['images']['error'][$token]) && $_FILES['images']['error'][$token] !== UPLOAD_ERR_NO_FILE) {
                    $file = [
                        'name' => $_FILES['images']['name'][$token] ?? '',
                        'type' => $_FILES['images']['type'][$token] ?? '',
                        'tmp_name' => $_FILES['images']['tmp_name'][$token] ?? '',
                        'error' => $_FILES['images']['error'][$token],
                        'size' => $_FILES['images']['size'][$token] ?? 0,
                    ];
                    $newPath = store_content_image($file);
                    delete_content_image($current[$key] ?? null);
                    $stmt->execute([':k' => $key, ':v' => $newPath]);
                    $current[$key] = $newPath;
                }
            }
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$content = site_content();
admin_header('Contenu du site', 'contenu');
?>
<?php if ($saved): ?><div class="flash flash--success">Le contenu et les images ont été enregistrés. Recharge le site pour voir les modifications.</div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
<p class="content-help">Tu peux modifier ici les textes et les visuels du site. Les images acceptées sont JPG, PNG et WEBP, jusqu’à 5 Mo.</p>
<form method="post" enctype="multipart/form-data" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <div class="content-groups">
    <?php foreach ($definitions as $groupTitle => $fields): ?>
      <section class="form-card">
        <h2><?= e($groupTitle) ?></h2>
        <?php if (!empty($imageSlots[$groupTitle])): ?>
          <div class="content-images">
            <?php foreach ($imageSlots[$groupTitle] as $token => [$imageKey, $imageLabel]): $currentImage = trim((string)($content[$imageKey] ?? '')); ?>
              <div class="content-image-editor">
                <div class="content-image-editor__preview <?= $currentImage ? 'has-image' : '' ?>"<?= $currentImage ? ' style="background-image:url(../' . e($currentImage) . ')"' : '' ?>>
                  <?php if (!$currentImage): ?><span>Aucune image personnalisée</span><?php endif; ?>
                </div>
                <div class="content-image-editor__controls">
                  <strong><?= e($imageLabel) ?></strong>
                  <label class="field"><span>Choisir / remplacer l’image</span><input type="file" name="images[<?= e($token) ?>]" accept="image/jpeg,image/png,image/webp"></label>
                  <?php if ($currentImage): ?><label class="content-image-remove"><input type="checkbox" name="remove_image[<?= e($token) ?>]" value="1"> Supprimer l’image actuelle</label><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="fields">
          <?php foreach ($fields as $key => [$label, $type]): ?>
            <div class="field <?= $type === 'textarea' ? 'field--full' : '' ?>">
              <label for="<?= e($key) ?>"><?= e($label) ?></label>
              <?php if ($type === 'textarea'): ?>
                <textarea id="<?= e($key) ?>" name="content[<?= e($key) ?>]"><?= e($content[$key] ?? '') ?></textarea>
              <?php else: ?>
                <input id="<?= e($key) ?>" name="content[<?= e($key) ?>]" value="<?= e($content[$key] ?? '') ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="admin-actions"><button class="btn btn--primary" type="submit">Enregistrer le contenu</button></div>
</form>
<?php admin_footer(); ?>
