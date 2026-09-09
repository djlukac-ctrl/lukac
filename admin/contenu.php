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

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $posted = $_POST['content'] ?? [];
    $allowedKeys = [];
    foreach ($definitions as $group) {
        $allowedKeys = array_merge($allowedKeys, array_keys($group));
    }
    $stmt = $pdo->prepare('INSERT INTO content(content_key,value,updated_at) VALUES(:k,:v,CURRENT_TIMESTAMP) ON CONFLICT(content_key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP');
    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $posted)) {
            $stmt->execute([':k'=>$key, ':v'=>trim((string)$posted[$key])]);
        }
    }
    $saved = true;
}

$content = site_content();
admin_header('Contenu du site', 'contenu');
?>
<?php if ($saved): ?><div class="flash flash--success">Le contenu a été enregistré. Recharge le site pour voir les modifications.</div><?php endif; ?>
<p class="content-help">Tu peux modifier ici les principaux textes du site sans toucher au code. Pour les listes, saisis un élément par ligne.</p>
<form method="post" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <div class="content-groups">
    <?php foreach ($definitions as $groupTitle => $fields): ?>
      <section class="form-card">
        <h2><?= e($groupTitle) ?></h2>
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
