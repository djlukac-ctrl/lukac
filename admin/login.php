<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (!admin_exists()) {
    header('Location: setup.php');
    exit;
}
if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        header('Location: index.php');
        exit;
    }
    usleep(450000);
    $error = 'Identifiants incorrects.';
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connexion — Administration Luka C</title><link rel="stylesheet" href="../assets/css/admin.css?v=20260909-1"><link rel="stylesheet" href="../assets/css/admin-light.css?v=20260910-1"></head><body class="login-page">
<div class="login-card">
  <img src="../assets/img/logo-lukac.png" alt="Luka C">
  <h1>Administration</h1>
  <p>Connecte-toi pour gérer les devis, les disponibilités et le contenu du site.</p>
  <?php if (isset($_GET['setup'])): ?><div class="flash flash--success">Compte administrateur créé. Tu peux maintenant te connecter.</div><?php endif; ?>
  <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" autocomplete="username" required></div>
    <div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn btn--primary" type="submit">Se connecter</button>
  </form>
</div></body></html>
