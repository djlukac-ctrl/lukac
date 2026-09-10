<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (admin_exists()) {
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } elseif (strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif ($password !== $confirm) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $stmt = db()->prepare('INSERT INTO admins(email, password_hash) VALUES(:email, :hash)');
        $stmt->execute([':email' => $email, ':hash' => password_hash($password, PASSWORD_DEFAULT)]);
        header('Location: login.php?setup=1');
        exit;
    }
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Créer l’administration — Luka C</title><link rel="stylesheet" href="../assets/css/admin.css?v=20260909-1"><link rel="stylesheet" href="../assets/css/admin-light.css?v=20260910-1"></head><body class="login-page">
<div class="login-card">
  <img src="../assets/img/logo-lukac.png" alt="Luka C">
  <h1>Créer l’administration</h1>
  <p>Première installation : crée ton compte administrateur. Cette page sera ensuite automatiquement verrouillée.</p>
  <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" autocomplete="email" required></div>
    <div class="field"><label for="password">Mot de passe (12 caractères minimum)</label><input id="password" name="password" type="password" autocomplete="new-password" required></div>
    <div class="field"><label for="confirm">Confirmer le mot de passe</label><input id="confirm" name="confirm" type="password" autocomplete="new-password" required></div>
    <button class="btn btn--primary" type="submit">Créer mon compte</button>
  </form>
</div></body></html>
