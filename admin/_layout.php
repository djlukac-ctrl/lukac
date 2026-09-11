<?php
require_once __DIR__ . '/../src/bootstrap.php';

function admin_header(string $title, string $active = ''): void
{
    $items = [
        'dashboard' => ['Tableau de bord', 'index.php'],
        'devis' => ['Demandes de devis', 'devis.php'],
        'disponibilites' => ['Disponibilités', 'disponibilites.php'],
        'avis' => ['Avis clients', 'avis.php'],
        'bon-plan' => ['Bon plan', 'bon-plan.php'],
        'options' => ['Options', 'options.php'],
        'contenu' => ['Contenu du site', 'contenu.php'],
    ];
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($title) ?> — Administration Luka C</title>
  <link rel="stylesheet" href="../assets/css/admin.css?v=20260910-2">
  <link rel="stylesheet" href="../assets/css/admin-content.css?v=20260910-1">
  <link rel="stylesheet" href="../assets/css/admin-light.css?v=20260910-1">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a class="admin-brand" href="index.php"><img src="../assets/img/logo-lukac.png" alt="Luka C"><span>Administration</span></a>
    <nav>
      <?php foreach ($items as $key => [$label, $href]): ?>
        <a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
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
    <header class="admin-topbar"><div><span>Administration</span><h1><?= e($title) ?></h1></div></header>
<?php
}

function admin_footer(): void
{
    echo '</main></div></body></html>';
}
