<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();

function options_store_image(array $file): string
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

function options_normalize_order(array $options): array
{
    foreach ($options as $index => &$option) {
        if (!isset($option['order']) || !is_numeric($option['order'])) {
            $option['order'] = $index + 1;
        }
        $option['order'] = max(1, (int)$option['order']);
        $option['_original_index'] = $index;
    }
    unset($option);

    usort($options, static function (array $a, array $b): int {
        $orderCompare = ((int)$a['order']) <=> ((int)$b['order']);
        if ($orderCompare !== 0) return $orderCompare;
        return ((int)$a['_original_index']) <=> ((int)$b['_original_index']);
    });

    foreach ($options as &$option) {
        unset($option['_original_index']);
    }
    unset($option);

    return array_values($options);
}

function options_load(array $content): array
{
    $raw = trim((string)($content['options.dynamic'] ?? ''));
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return options_normalize_order(array_values($decoded));
    }

    $legacy = [
        ['fumee', 'Fumée lourde'],
        ['etincelles', 'Étincelles froides'],
        ['eclairage', 'Éclairage mural'],
        ['ecran', 'Écran & projecteur'],
    ];
    $options = [];
    foreach ($legacy as $index => [$slug, $fallback]) {
        $options[] = [
            'id' => $slug,
            'title' => (string)($content["options.$slug.title"] ?? $fallback),
            'desc' => (string)($content["options.$slug.desc"] ?? ''),
            'image' => (string)($content["options.$slug.image"] ?? ''),
            'x' => (int)($content["options.$slug.image.position_x"] ?? 50),
            'y' => (int)($content["options.$slug.image.position_y"] ?? 50),
            'order' => $index + 1,
        ];
    }
    return $options;
}

function options_save(PDO $pdo, array $options): void
{
    $options = options_normalize_order($options);
    $json = json_encode(array_values($options), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Impossible d’enregistrer les options.');
    $stmt = $pdo->prepare('INSERT INTO content(content_key,value,updated_at) VALUES(:k,:v,CURRENT_TIMESTAMP) ON CONFLICT(content_key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP');
    $stmt->execute([':k' => 'options.dynamic', ':v' => $json]);
}

$content = site_content();
$options = options_load($content);

if (empty($content['options.dynamic'])) {
    options_save($pdo, $options);
    $content = site_content();
}

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            $options = array_values(array_filter($options, static fn($option) => (string)($option['id'] ?? '') !== $id));
            options_save($pdo, $options);
            $saved = true;
        }

        if ($action === 'save') {
            $id = (string)($_POST['id'] ?? '');
            foreach ($options as &$option) {
                if ((string)($option['id'] ?? '') !== $id) continue;
                $title = trim((string)($_POST['title'] ?? ''));
                if ($title === '') throw new RuntimeException('Le nom de l’option est obligatoire.');
                $option['title'] = $title;
                $option['desc'] = trim((string)($_POST['desc'] ?? ''));
                $option['order'] = max(1, (int)($_POST['order'] ?? 1));
                $option['x'] = max(0, min(100, (int)($_POST['x'] ?? 50)));
                $option['y'] = max(0, min(100, (int)($_POST['y'] ?? 50)));

                if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $option['image'] = options_store_image($_FILES['image']);
                }
                if (!empty($_POST['remove_image'])) $option['image'] = '';
                break;
            }
            unset($option);
            options_save($pdo, $options);
            $saved = true;
        }

        if ($action === 'add') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') throw new RuntimeException('Le nom de la nouvelle option est obligatoire.');

            $image = '';
            if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $image = options_store_image($_FILES['image']);
            }

            $options[] = [
                'id' => bin2hex(random_bytes(8)),
                'title' => $title,
                'desc' => trim((string)($_POST['desc'] ?? '')),
                'image' => $image,
                'x' => 50,
                'y' => 50,
                'order' => max(1, (int)($_POST['order'] ?? (count($options) + 1))),
            ];
            options_save($pdo, $options);
            $saved = true;
        }

        $content = site_content();
        $options = options_load($content);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

admin_header('Options', 'options');
?>
<?php if ($saved): ?><div class="flash flash--success">Les options ont bien été mises à jour.</div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<div class="content-page-heading">
  <div>
    <span class="content-page-heading__eyebrow">Formules</span>
    <h2>Options à la carte</h2>
    <p>Ajoute, modifie, supprime ou change l’ordre des options visibles sur le site. L’ordre est également repris dans le formulaire de devis.</p>
  </div>
  <a class="btn" href="../index.html#formules" target="_blank" rel="noopener">Voir le site ↗</a>
</div>

<p class="content-help">Pour changer l’ordre, indique simplement 1 pour la première option, 2 pour la deuxième, etc., puis enregistre l’option.</p>

<div class="content-groups">
  <?php foreach ($options as $index => $option): ?>
    <section class="form-card">
      <h2><?= e((string)($option['title'] ?? 'Option')) ?></h2>
      <form method="post" enctype="multipart/form-data" class="fields admin-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e((string)($option['id'] ?? '')) ?>">

        <?php $image = trim((string)($option['image'] ?? '')); ?>
        <div class="content-image-editor" data-image-editor>
          <div class="content-image-editor__preview <?= $image ? 'has-image' : '' ?>"<?= $image ? ' style="background-image:url(../' . e($image) . ');background-position:' . (int)($option['x'] ?? 50) . '% ' . (int)($option['y'] ?? 50) . '%"' : '' ?>>
            <?php if (!$image): ?><span>Aucune image</span><?php endif; ?>
          </div>
          <div class="content-image-editor__controls">
            <strong>Image de l’option</strong>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <?php if ($image): ?><label class="content-image-remove"><input type="checkbox" name="remove_image" value="1"> Supprimer l’image</label><?php endif; ?>
          </div>
        </div>

        <div class="form-grid">
          <div class="field"><label>Nom de l’option</label><input type="text" name="title" required value="<?= e((string)($option['title'] ?? '')) ?>"></div>
          <div class="field"><label>Ordre d’affichage</label><input type="number" name="order" min="1" step="1" required value="<?= (int)($option['order'] ?? ($index + 1)) ?>"></div>
        </div>
        <div class="field"><label>Description</label><textarea name="desc" rows="4"><?= e((string)($option['desc'] ?? '')) ?></textarea></div>
        <div class="form-grid">
          <label>Position horizontale de l’image
            <input type="range" name="x" min="0" max="100" value="<?= (int)($option['x'] ?? 50) ?>">
          </label>
          <label>Position verticale de l’image
            <input type="range" name="y" min="0" max="100" value="<?= (int)($option['y'] ?? 50) ?>">
          </label>
        </div>
        <div class="form-actions"><button class="btn btn--primary" type="submit">Enregistrer</button></div>
      </form>
      <form method="post" class="fields" onsubmit="return confirm('Supprimer cette option ?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= e((string)($option['id'] ?? '')) ?>">
        <button class="btn" type="submit">Supprimer cette option</button>
      </form>
    </section>
  <?php endforeach; ?>

  <section class="form-card">
    <h2>Ajouter une option</h2>
    <form method="post" enctype="multipart/form-data" class="fields admin-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="field"><label>Nom de l’option</label><input type="text" name="title" required placeholder="Ex. Livre d’or audio"></div>
        <div class="field"><label>Ordre d’affichage</label><input type="number" name="order" min="1" step="1" value="<?= count($options) + 1 ?>"></div>
      </div>
      <div class="field"><label>Description</label><textarea name="desc" rows="4" placeholder="Décris brièvement l’option..."></textarea></div>
      <div class="field"><label>Image</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Ajouter l’option</button></div>
    </form>
  </section>
</div>

<?php admin_footer(); ?>
