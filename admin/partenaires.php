<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$pdo = db();

function partners_store_image(array $file): string
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

    $filename = 'partner-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
        throw new RuntimeException('Impossible d’enregistrer l’image.');
    }
    return 'uploads/contenu/' . $filename;
}

function partners_delete_image(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/contenu/partner-')) return;
    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) @unlink($full);
}

function partners_normalize(array $partners): array
{
    foreach ($partners as $index => &$partner) {
        $partner['order'] = max(1, (int)($partner['order'] ?? ($index + 1)));
        $partner['_index'] = $index;
    }
    unset($partner);

    usort($partners, static function (array $a, array $b): int {
        $cmp = ((int)$a['order']) <=> ((int)$b['order']);
        return $cmp !== 0 ? $cmp : ((int)$a['_index'] <=> (int)$b['_index']);
    });

    foreach ($partners as &$partner) unset($partner['_index']);
    unset($partner);
    return array_values($partners);
}

function partners_load(array $content): array
{
    $raw = trim((string)($content['partners.dynamic'] ?? ''));
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? partners_normalize(array_values($decoded)) : [];
}

function partners_save(PDO $pdo, array $partners): void
{
    $partners = partners_normalize($partners);
    $json = json_encode($partners, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException('Impossible d’enregistrer les partenaires.');
    $stmt = $pdo->prepare('INSERT INTO content(content_key,value,updated_at) VALUES(:k,:v,CURRENT_TIMESTAMP) ON CONFLICT(content_key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP');
    $stmt->execute([':k' => 'partners.dynamic', ':v' => $json]);
}

function partners_clean_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Le lien du partenaire n’est pas valide.');
    }
    return $url;
}

$content = site_content();
$partners = partners_load($content);
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            foreach ($partners as $partner) {
                if ((string)($partner['id'] ?? '') === $id) partners_delete_image($partner['image'] ?? null);
            }
            $partners = array_values(array_filter($partners, static fn(array $partner): bool => (string)($partner['id'] ?? '') !== $id));
            partners_save($pdo, $partners);
            $saved = true;
        }

        if ($action === 'save') {
            $id = (string)($_POST['id'] ?? '');
            foreach ($partners as &$partner) {
                if ((string)($partner['id'] ?? '') !== $id) continue;
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') throw new RuntimeException('Le nom du prestataire est obligatoire.');

                $partner['name'] = $name;
                $partner['activity'] = trim((string)($_POST['activity'] ?? ''));
                $partner['desc'] = trim((string)($_POST['desc'] ?? ''));
                $partner['url'] = partners_clean_url((string)($_POST['url'] ?? ''));
                $partner['order'] = max(1, (int)($_POST['order'] ?? 1));

                if (!empty($_POST['remove_image'])) {
                    partners_delete_image($partner['image'] ?? null);
                    $partner['image'] = '';
                }
                if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $newImage = partners_store_image($_FILES['image']);
                    partners_delete_image($partner['image'] ?? null);
                    $partner['image'] = $newImage;
                }
                break;
            }
            unset($partner);
            partners_save($pdo, $partners);
            $saved = true;
        }

        if ($action === 'add') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Le nom du prestataire est obligatoire.');

            $image = '';
            if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $image = partners_store_image($_FILES['image']);
            }

            $partners[] = [
                'id' => bin2hex(random_bytes(8)),
                'name' => $name,
                'activity' => trim((string)($_POST['activity'] ?? '')),
                'desc' => trim((string)($_POST['desc'] ?? '')),
                'url' => partners_clean_url((string)($_POST['url'] ?? '')),
                'image' => $image,
                'order' => max(1, (int)($_POST['order'] ?? (count($partners) + 1))),
            ];
            partners_save($pdo, $partners);
            $saved = true;
        }

        $content = site_content();
        $partners = partners_load($content);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

admin_header('Prestataires partenaires', 'partenaires');
?>
<?php if ($saved): ?><div class="flash flash--success">Les prestataires partenaires ont bien été mis à jour.</div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<div class="content-page-heading">
  <div>
    <span class="content-page-heading__eyebrow">Réseau professionnel</span>
    <h2>Prestataires partenaires</h2>
    <p>Gère les professionnels avec qui tu as l’habitude de travailler. Ils apparaissent sous le bloc Devis sur le site.</p>
  </div>
  <a class="btn" href="../index.html#partenaires" target="_blank" rel="noopener">Voir le site ↗</a>
</div>

<p class="content-help">Le bloc reste automatiquement masqué tant qu’aucun prestataire n’est enregistré.</p>

<div class="content-groups">
  <?php foreach ($partners as $index => $partner): ?>
    <section class="form-card">
      <h2><?= e((string)($partner['name'] ?? 'Prestataire')) ?></h2>
      <form method="post" enctype="multipart/form-data" class="fields admin-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e((string)($partner['id'] ?? '')) ?>">

        <?php $image = trim((string)($partner['image'] ?? '')); ?>
        <div class="content-image-editor">
          <div class="content-image-editor__preview <?= $image ? 'has-image' : '' ?>"<?= $image ? ' style="background-image:url(../' . e($image) . ');background-position:center;background-size:contain;background-repeat:no-repeat"' : '' ?>>
            <?php if (!$image): ?><span>Aucun logo / visuel</span><?php endif; ?>
          </div>
          <div class="content-image-editor__controls">
            <strong>Logo ou photo du prestataire</strong>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <?php if ($image): ?><label class="content-image-remove"><input type="checkbox" name="remove_image" value="1"> Supprimer l’image</label><?php endif; ?>
          </div>
        </div>

        <div class="form-grid">
          <div class="field"><label>Nom du prestataire</label><input type="text" name="name" required value="<?= e((string)($partner['name'] ?? '')) ?>"></div>
          <div class="field"><label>Activité</label><input type="text" name="activity" placeholder="Ex. Photographe, traiteur..." value="<?= e((string)($partner['activity'] ?? '')) ?>"></div>
        </div>
        <div class="field"><label>Description</label><textarea name="desc" rows="3" placeholder="Quelques mots sur ce prestataire..."><?= e((string)($partner['desc'] ?? '')) ?></textarea></div>
        <div class="form-grid">
          <div class="field"><label>Lien (site ou réseau social)</label><input type="text" name="url" placeholder="https://..." value="<?= e((string)($partner['url'] ?? '')) ?>"></div>
          <div class="field"><label>Ordre d’affichage</label><input type="number" name="order" min="1" step="1" value="<?= (int)($partner['order'] ?? ($index + 1)) ?>"></div>
        </div>
        <div class="form-actions"><button class="btn btn--primary" type="submit">Enregistrer</button></div>
      </form>
      <form method="post" class="fields" onsubmit="return confirm('Supprimer ce prestataire partenaire ?');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= e((string)($partner['id'] ?? '')) ?>">
        <button class="btn btn--danger" type="submit">Supprimer ce prestataire</button>
      </form>
    </section>
  <?php endforeach; ?>

  <section class="form-card">
    <h2>Ajouter un prestataire partenaire</h2>
    <form method="post" enctype="multipart/form-data" class="fields admin-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="field"><label>Nom du prestataire</label><input type="text" name="name" required placeholder="Ex. Studio Dupont"></div>
        <div class="field"><label>Activité</label><input type="text" name="activity" placeholder="Ex. Photographe"></div>
      </div>
      <div class="field"><label>Description</label><textarea name="desc" rows="3" placeholder="Quelques mots sur ce prestataire..."></textarea></div>
      <div class="form-grid">
        <div class="field"><label>Lien (site ou réseau social)</label><input type="text" name="url" placeholder="https://..."></div>
        <div class="field"><label>Ordre d’affichage</label><input type="number" name="order" min="1" step="1" value="<?= count($partners) + 1 ?>"></div>
      </div>
      <div class="field"><label>Logo ou photo</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
      <div class="form-actions"><button class="btn btn--primary" type="submit">Ajouter le prestataire</button></div>
    </form>
  </section>
</div>

<?php admin_footer(); ?>
