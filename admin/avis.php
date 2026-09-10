<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

$saved = false;
$error = '';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

$review = [
    'id' => 0,
    'client_name' => '',
    'review_text' => '',
    'rating' => 5,
    'review_date' => '',
    'published' => 1,
    'display_order' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = :id');
            $stmt->execute([':id' => $id]);
        }
        header('Location: avis.php?deleted=1');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $clientName = trim((string)($_POST['client_name'] ?? ''));
    $reviewText = trim((string)($_POST['review_text'] ?? ''));
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $reviewDate = trim((string)($_POST['review_date'] ?? ''));
    $published = !empty($_POST['published']) ? 1 : 0;
    $displayOrder = max(0, (int)($_POST['display_order'] ?? 0));

    if ($clientName === '' || $reviewText === '') {
        $error = 'Le nom du client et le texte de l’avis sont obligatoires.';
        $review = compact('id', 'clientName', 'reviewText', 'rating', 'reviewDate', 'published', 'displayOrder');
        $review = [
            'id'=>$id, 'client_name'=>$clientName, 'review_text'=>$reviewText, 'rating'=>$rating,
            'review_date'=>$reviewDate, 'published'=>$published, 'display_order'=>$displayOrder,
        ];
    } elseif ($reviewDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewDate)) {
        $error = 'La date de l’avis n’est pas valide.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE reviews SET client_name=:name, review_text=:text, rating=:rating, review_date=:date, published=:published, display_order=:sort, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
            $stmt->execute([
                ':name'=>$clientName, ':text'=>$reviewText, ':rating'=>$rating,
                ':date'=>$reviewDate !== '' ? $reviewDate : null, ':published'=>$published,
                ':sort'=>$displayOrder, ':id'=>$id,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO reviews(client_name, review_text, rating, review_date, published, display_order) VALUES(:name,:text,:rating,:date,:published,:sort)');
            $stmt->execute([
                ':name'=>$clientName, ':text'=>$reviewText, ':rating'=>$rating,
                ':date'=>$reviewDate !== '' ? $reviewDate : null, ':published'=>$published,
                ':sort'=>$displayOrder,
            ]);
        }
        header('Location: avis.php?saved=1');
        exit;
    }
}

if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $found = $stmt->fetch();
    if ($found) $review = $found;
}

$reviews = $pdo->query('SELECT * FROM reviews ORDER BY display_order ASC, review_date DESC, id DESC')->fetchAll();
admin_header('Avis clients', 'avis');
?>
<style>
  .reviews-admin-grid{display:grid;grid-template-columns:minmax(360px,.8fr) minmax(0,1.2fr);gap:18px;align-items:start}.review-editor{position:sticky;top:24px}.review-stars{color:#c9a77a;letter-spacing:.1em;font-size:13px}.review-admin-list{display:grid;gap:12px}.review-admin-card{padding:18px;border:1px solid var(--line);border-radius:15px;background:var(--panel)}.review-admin-card__top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:12px}.review-admin-card h3{margin:0 0 4px;font:600 16px 'Space Grotesk',sans-serif}.review-admin-card__meta{color:#77726c;font-size:10px}.review-admin-card p{margin:0;color:#c9c4bd;font-size:12px;line-height:1.65}.review-admin-card__bottom{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:15px;padding-top:13px;border-top:1px solid rgba(255,255,255,.06)}.review-admin-actions{display:flex;gap:8px;flex-wrap:wrap}.review-visibility{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.07em}.review-visibility.is-on{background:rgba(147,178,124,.10);color:#b9d7a4}.review-visibility.is-off{background:#171717;color:#777}.review-empty{padding:28px;border:1px dashed var(--line);border-radius:16px;color:#77726c;text-align:center}.review-check{display:flex;align-items:center;gap:8px;color:#c9c4bd;font-size:12px}.review-check input{accent-color:var(--accent)}@media(max-width:980px){.reviews-admin-grid{grid-template-columns:1fr}.review-editor{position:static}}
</style>

<?php if (isset($_GET['saved'])): ?><div class="flash flash--success">L’avis a bien été enregistré.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="flash flash--success">L’avis a été supprimé.</div><?php endif; ?>
<?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

<div class="reviews-admin-grid">
  <section class="form-card review-editor">
    <h2><?= !empty($review['id']) ? 'Modifier l’avis' : 'Ajouter un avis' ?></h2>
    <form method="post" class="admin-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($review['id'] ?? 0) ?>">
      <div class="fields">
        <div class="field field--full">
          <label for="client_name">Nom du client *</label>
          <input id="client_name" name="client_name" value="<?= e((string)$review['client_name']) ?>" required placeholder="Ex. DUPONT Marie">
        </div>
        <div class="field">
          <label for="review_date">Date de l’avis</label>
          <input id="review_date" name="review_date" type="date" value="<?= e((string)($review['review_date'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="rating">Note</label>
          <select id="rating" name="rating">
            <?php for ($i=5; $i>=1; $i--): ?><option value="<?= $i ?>" <?= (int)$review['rating'] === $i ? 'selected' : '' ?>><?= $i ?>/5 <?= str_repeat('★', $i) ?></option><?php endfor; ?>
          </select>
        </div>
        <div class="field field--full">
          <label for="review_text">Avis du client *</label>
          <textarea id="review_text" name="review_text" required placeholder="Copie ici le message laissé par le client."><?= e((string)$review['review_text']) ?></textarea>
        </div>
        <div class="field">
          <label for="display_order">Ordre d’affichage</label>
          <input id="display_order" name="display_order" type="number" min="0" value="<?= (int)$review['display_order'] ?>">
        </div>
        <div class="field">
          <span>Visibilité</span>
          <label class="review-check"><input type="checkbox" name="published" value="1" <?= !empty($review['published']) ? 'checked' : '' ?>> Afficher sur le site</label>
        </div>
      </div>
      <div class="admin-actions">
        <?php if (!empty($review['id'])): ?><a class="btn" href="avis.php">Annuler</a><?php endif; ?>
        <button class="btn btn--primary" type="submit"><?= !empty($review['id']) ? 'Enregistrer les modifications' : 'Ajouter l’avis' ?></button>
      </div>
    </form>
  </section>

  <section>
    <div class="admin-section__head"><h2>Avis enregistrés</h2><span class="admin-link"><?= count($reviews) ?> avis</span></div>
    <div class="review-admin-list">
      <?php if (!$reviews): ?>
        <div class="review-empty">Aucun avis enregistré pour le moment.</div>
      <?php endif; ?>
      <?php foreach ($reviews as $item): ?>
        <article class="review-admin-card">
          <div class="review-admin-card__top">
            <div><h3><?= e((string)$item['client_name']) ?></h3><div class="review-stars"><?= str_repeat('★', max(1,min(5,(int)$item['rating']))) ?></div></div>
            <span class="review-visibility <?= !empty($item['published']) ? 'is-on' : 'is-off' ?>"><?= !empty($item['published']) ? 'Visible' : 'Masqué' ?></span>
          </div>
          <p><?= nl2br(e((string)$item['review_text'])) ?></p>
          <div class="review-admin-card__bottom">
            <span class="review-admin-card__meta"><?= !empty($item['review_date']) ? e(date('d/m/Y', strtotime((string)$item['review_date']))) : 'Sans date' ?> · ordre <?= (int)$item['display_order'] ?></span>
            <div class="review-admin-actions">
              <a class="btn" href="avis.php?edit=<?= (int)$item['id'] ?>">Modifier</a>
              <form method="post" onsubmit="return confirm('Supprimer définitivement cet avis ?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <button class="btn btn--danger" type="submit">Supprimer</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php admin_footer(); ?>
