<?php
require_once __DIR__ . '/../src/bootstrap.php';

function admin_header(string $title, string $active = ''): void
{
    $newQuoteCount = 0;
    try {
        $newQuoteCount = (int) db()->query("SELECT COUNT(*) FROM quotes WHERE status='new'")->fetchColumn();
    } catch (Throwable $e) {
        $newQuoteCount = 0;
    }

    $groups = [
        'Gestion' => [
            'dashboard' => ['Tableau de bord', 'index.php'],
            'devis' => ['Demandes de devis', 'devis.php'],
            'finances' => ['Finances', 'finances.php'],
        ],
        'Site' => [
            'contenu' => ['Contenu du site', 'contenu.php'],
            'disponibilites' => ['Disponibilités', 'disponibilites.php'],
            'avis' => ['Avis clients', 'avis.php'],
            'options' => ['Options', 'options.php'],
            'partenaires' => ['Prestataires partenaires', 'partenaires.php'],
            'bon-plan' => ['Bon plan', 'bon-plan.php'],
        ],
    ];
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $newQuoteCount > 0 ? '(' . $newQuoteCount . ') ' : '' ?><?= e($title) ?> — Administration Luka C</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20260910-2">
  <link rel="stylesheet" href="../assets/css/admin-content.css?v=20260910-1">
  <link rel="stylesheet" href="../assets/css/admin-light.css?v=20260914-1">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a class="admin-brand" href="index.php"><img src="../assets/img/logo-lukac.png" alt="Luka C"><span>Administration</span></a>
    <nav class="admin-nav">
      <?php foreach ($groups as $groupLabel => $items): ?>
        <div class="admin-nav__group">
          <span class="admin-nav__label"><?= e($groupLabel) ?></span>
          <?php foreach ($items as $key => [$label, $href]): ?>
            <a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>">
              <span><?= e($label) ?></span>
              <?php if ($key === 'devis' && $newQuoteCount > 0): ?><span class="admin-nav__badge"><?= $newQuoteCount ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar__bottom">
      <a href="../index.html" target="_blank" rel="noopener">Voir le site ↗</a>
      <form action="logout.php" method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button type="submit">Se déconnecter</button>
      </form>
    </div>
  </aside>
  <main class="admin-main">
    <header class="admin-topbar">
      <div><span>Administration</span><h1><?= e($title) ?></h1></div>
      <a class="admin-notification <?= $newQuoteCount > 0 ? 'has-alert' : '' ?>" href="devis.php?status=new" aria-label="<?= $newQuoteCount > 0 ? e($newQuoteCount . ' nouvelle(s) demande(s) de devis') : 'Aucune nouvelle demande' ?>" title="Nouvelles demandes de devis">
        <span class="admin-notification__icon" aria-hidden="true">🔔</span>
        <?php if ($newQuoteCount > 0): ?><span class="admin-notification__count"><?= $newQuoteCount ?></span><?php endif; ?>
      </a>
    </header>
<?php
}

function admin_footer(): void
{
    echo '</main></div></body></html>';
}
