<?php
require_once __DIR__ . '/src/bootstrap.php';

$success = false;
$error = '';
$values = [
    'name' => '', 'email' => '', 'phone' => '',
    'address_number' => '', 'address_street' => '', 'address_city' => '', 'address_postcode' => '',
    'referral' => '', 'event_type' => '', 'event_date' => '', 'venue' => '', 'guest_count' => '', 'budget' => '',
    'start_time' => '', 'end_time' => '', 'message' => ''
];
$selectedServices = [];
$selectedSelections = [];

$serviceOptions = ['DJ', 'Animations', 'Karaoké', "Sonorisation de vin d’honneur"];
$selectionOptions = [
    'Essentiel', 'Ambiance', 'Expérience',
    'Pack Instant Magique', 'Pack Instant Magique Signature',
    'Fumée lourde', 'Étincelles froides', 'Éclairage mural', 'Écran & projecteur'
];
$eventOptions = ['Mariage', 'Anniversaire', 'Baptême', 'Retraite', 'Autre'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $selectedServices = array_values(array_intersect($serviceOptions, array_map('strval', (array)($_POST['services'] ?? []))));
    $selectedSelections = array_values(array_intersect($selectionOptions, array_map('strval', (array)($_POST['selections'] ?? []))));
    $honeypot = trim((string)($_POST['company'] ?? ''));

    if ($honeypot !== '') {
        $success = true;
    } elseif ($values['name'] === '' || $values['event_type'] === '' || $values['phone'] === '' || $values['event_date'] === '') {
        $error = 'Merci de renseigner votre nom, votre téléphone, le type d’événement et sa date.';
    } elseif ($values['address_number'] === '' || $values['address_street'] === '' || $values['address_city'] === '' || $values['address_postcode'] === '') {
        $error = 'Merci de renseigner votre adresse postale complète : numéro, rue, ville et code postal.';
    } elseif (!preg_match('/^[0-9A-Za-zÀ-ÿ -]{1,12}$/u', $values['address_number'])) {
        $error = 'Merci de vérifier le numéro de votre adresse.';
    } elseif (!preg_match('/^\d{5}$/', $values['address_postcode'])) {
        $error = 'Merci de renseigner un code postal à 5 chiffres.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Merci de renseigner une adresse e-mail valide.';
    } elseif ($values['start_time'] === '' || $values['end_time'] === '') {
        $error = 'Merci d’indiquer les horaires prévus de votre événement.';
    } elseif (!$selectedServices) {
        $error = 'Merci de sélectionner au moins une prestation souhaitée.';
    } elseif (!$selectedSelections) {
        $error = 'Merci de sélectionner au moins une formule, un pack ou une option.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['event_date'])) {
        $error = 'La date indiquée n’est pas valide.';
    } elseif ($values['guest_count'] !== '' && ((int)$values['guest_count'] < 1 || (int)$values['guest_count'] > 5000)) {
        $error = 'Le nombre d’invités indiqué n’est pas valide.';
    } else {
        $postalAddress = $values['address_number'] . ' ' . $values['address_street'] . ', ' . $values['address_postcode'] . ' ' . $values['address_city'];
        $stmt = db()->prepare('INSERT INTO quotes(name,email,phone,postal_address,referral,event_type,event_date,venue,guest_count,budget,start_time,end_time,services,selections,message) VALUES(:name,:email,:phone,:postal_address,:referral,:event_type,:event_date,:venue,:guest_count,:budget,:start_time,:end_time,:services,:selections,:message)');
        $stmt->execute([
            ':name'=>$values['name'], ':email'=>$values['email'], ':phone'=>$values['phone'],
            ':postal_address'=>$postalAddress, ':referral'=>$values['referral'] ?: null,
            ':event_type'=>$values['event_type'], ':event_date'=>$values['event_date'],
            ':venue'=>$values['venue'] ?: null, ':guest_count'=>$values['guest_count'] !== '' ? (int)$values['guest_count'] : null,
            ':budget'=>$values['budget'] ?: null, ':start_time'=>$values['start_time'], ':end_time'=>$values['end_time'],
            ':services'=>implode(' | ', $selectedServices), ':selections'=>implode(' | ', $selectedSelections),
            ':message'=>$values['message'] ?: null,
        ]);
        $success = true;
        foreach ($values as $key => $_) $values[$key] = '';
        $selectedServices = [];
        $selectedSelections = [];
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Demandez votre devis personnalisé pour votre événement avec Luka C.">
  <title>Demander un devis — Luka C</title>
  <link rel="stylesheet" href="assets/css/modern-dark.css?v=20260909-3">
  <style>
    .quote-page{max-width:1100px;margin:0 auto;padding:70px 34px 110px}.quote-head{max-width:790px;margin-bottom:34px}.quote-head p:first-child{font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--accent);font-weight:700}.quote-head h1{font:600 clamp(46px,6vw,76px)/.98 'Space Grotesk',sans-serif;letter-spacing:-.045em;margin:0 0 18px}.quote-head h1 span{color:var(--accent)}.quote-head>p:last-child{color:#8f8a84;line-height:1.75}.quote-form{border:1px solid var(--line);border-radius:22px;background:#0d0d0d;padding:28px}.quote-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.quote-field{display:grid;gap:7px}.quote-field--full{grid-column:1/-1}.quote-field label,.quote-group>legend{font-size:11px;color:#8f8a84}.quote-field input,.quote-field select,.quote-field textarea{width:100%;border:1px solid var(--line);border-radius:12px;background:#090909;color:#fff;padding:13px 14px;outline:none;font:inherit}.quote-field textarea{min-height:140px;resize:vertical}.quote-field input:focus,.quote-field select:focus,.quote-field textarea:focus{border-color:var(--line-strong)}.quote-address{grid-column:1/-1;border:1px solid var(--line);border-radius:16px;padding:16px;margin:0}.quote-address>legend{padding:0 7px;font-size:11px;color:#8f8a84}.quote-address-grid{display:grid;grid-template-columns:120px 1.5fr 1fr 150px;gap:12px}.quote-group{grid-column:1/-1;border:1px solid var(--line);border-radius:16px;padding:16px;margin:0}.quote-group>legend{padding:0 7px}.quote-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 14px}.quote-check{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1px solid rgba(255,255,255,.06);border-radius:11px;background:#090909;color:#d7d2cc;font-size:12px;cursor:pointer}.quote-check input{width:16px;height:16px;margin:1px 0 0;accent-color:var(--accent)}.quote-actions{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:20px}.quote-actions p{margin:0;color:#66615c;font-size:10px}.quote-submit{border:0;border-radius:999px;background:#f4f1ec;color:#111;padding:14px 20px;font-weight:700;cursor:pointer}.quote-success{padding:26px;border:1px solid rgba(147,178,124,.3);border-radius:18px;background:rgba(147,178,124,.07)}.quote-success h2{font:600 28px 'Space Grotesk',sans-serif;margin:0 0 8px}.quote-success p{margin:0;color:#aabda0;line-height:1.7}.quote-error{margin-bottom:16px;padding:12px 14px;border-radius:12px;background:rgba(184,110,105,.08);color:#e2aaa6;border:1px solid rgba(184,110,105,.25);font-size:12px}.hp{position:absolute;left:-9999px;opacity:0;pointer-events:none}@media(max-width:850px){.quote-address-grid{grid-template-columns:1fr 1fr}}@media(max-width:700px){.quote-page{padding:45px 18px 75px}.quote-grid{grid-template-columns:1fr}.quote-field--full,.quote-group,.quote-address{grid-column:auto}.quote-address-grid{grid-template-columns:1fr}.quote-checks{grid-template-columns:1fr}.quote-form{padding:20px}.quote-actions{align-items:stretch;flex-direction:column}.quote-submit{width:100%}}
  </style>
  <link rel="stylesheet" href="assets/css/site-light.css?v=20260910-1">
</head>
<body>
<header class="site-header" id="top">
  <a class="brand" href="index.html"><img src="assets/img/logo-lukac.png" alt="Luka C" class="brand__logo"></a>
  <nav class="desktop-nav"><a href="index.html">Accueil</a><a href="index.html#prestations">Prestations</a><a href="index.html#formules">Formules</a><a href="index.html#disponibilites">Disponibilités</a><a href="index.html#avis">Avis clients</a></nav>
  <a class="header-cta" href="devis.php">Demander un devis <span>→</span></a>
  <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false"><span></span><span></span></button>
</header>
<nav class="mobile-nav" hidden><a href="index.html">Accueil</a><a href="index.html#prestations">Prestations</a><a href="index.html#formules">Formules</a><a href="index.html#disponibilites">Disponibilités</a><a href="index.html#avis">Avis clients</a></nav>
<main class="quote-page">
  <section class="quote-head"><p>Demande de devis</p><h1>Parlons de <span>votre événement.</span></h1><p>Merci de me transmettre les premières informations concernant votre événement. Elles me permettront d’étudier votre demande et de vous proposer une prestation adaptée à votre date, votre lieu et vos attentes.</p></section>
  <?php if ($success): ?>
    <section class="quote-success"><h2>Demande envoyée ✓</h2><p>Merci pour votre demande. Celle-ci a bien été enregistrée et je reviendrai vers vous dès que possible.</p></section>
  <?php else: ?>
    <?php if ($error): ?><div class="quote-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="quote-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="hp"><label>Entreprise<input name="company" tabindex="-1" autocomplete="off"></label></div>
      <div class="quote-grid">
        <div class="quote-field"><label for="name">Nom et prénom *</label><input id="name" name="name" value="<?= e($values['name']) ?>" required autocomplete="name"></div>
        <div class="quote-field"><label for="email">E-mail *</label><input id="email" name="email" type="email" value="<?= e($values['email']) ?>" required autocomplete="email"></div>
        <div class="quote-field"><label for="phone">Téléphone *</label><input id="phone" name="phone" type="tel" value="<?= e($values['phone']) ?>" required autocomplete="tel"></div>
        <div class="quote-field quote-field--full"><label for="referral">Quelqu’un vous a parlé de moi ? <span>(Si oui, NOM Prénom)</span></label><input id="referral" name="referral" value="<?= e($values['referral']) ?>"></div>

        <fieldset class="quote-address">
          <legend>Adresse postale *</legend>
          <div class="quote-address-grid">
            <div class="quote-field"><label for="address_number">N° *</label><input id="address_number" name="address_number" value="<?= e($values['address_number']) ?>" required autocomplete="address-line1"></div>
            <div class="quote-field"><label for="address_street">Rue *</label><input id="address_street" name="address_street" value="<?= e($values['address_street']) ?>" required></div>
            <div class="quote-field"><label for="address_city">Ville *</label><input id="address_city" name="address_city" value="<?= e($values['address_city']) ?>" required autocomplete="address-level2"></div>
            <div class="quote-field"><label for="address_postcode">Code postal *</label><input id="address_postcode" name="address_postcode" value="<?= e($values['address_postcode']) ?>" required inputmode="numeric" maxlength="5" pattern="[0-9]{5}" autocomplete="postal-code"></div>
          </div>
        </fieldset>

        <div class="quote-field"><label for="event_type">Type d’événement *</label><select id="event_type" name="event_type" required><option value=""></option><?php foreach($eventOptions as $option): ?><option value="<?= e($option) ?>" <?= $values['event_type']===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
        <div class="quote-field"><label for="event_date">Date de l’événement *</label><input id="event_date" name="event_date" type="date" value="<?= e($values['event_date']) ?>" required></div>
        <div class="quote-field"><label for="venue">Lieu de réception / commune</label><input id="venue" name="venue" value="<?= e($values['venue']) ?>"></div>
        <div class="quote-field"><label for="guest_count">Nombre d’invités</label><input id="guest_count" name="guest_count" type="number" min="1" max="5000" value="<?= e($values['guest_count']) ?>"></div>

        <div class="quote-field"><label for="start_time">Arrivée des invités *</label><input id="start_time" name="start_time" type="time" value="<?= e($values['start_time']) ?>" required></div>
        <div class="quote-field"><label for="end_time">Fin de soirée *</label><input id="end_time" name="end_time" type="time" value="<?= e($values['end_time']) ?>" required></div>

        <fieldset class="quote-group">
          <legend>Quelle(s) prestation(s) souhaitez-vous ? *</legend>
          <div class="quote-checks">
            <?php foreach($serviceOptions as $option): ?><label class="quote-check"><input type="checkbox" name="services[]" value="<?= e($option) ?>" <?= in_array($option,$selectedServices,true)?'checked':'' ?>><span><?= e($option) ?></span></label><?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="quote-group">
          <legend>Quelle formule, quel pack ou quelle option avez-vous choisi ? *</legend>
          <div class="quote-checks">
            <?php foreach($selectionOptions as $option): ?><label class="quote-check"><input type="checkbox" name="selections[]" value="<?= e($option) ?>" <?= in_array($option,$selectedSelections,true)?'checked':'' ?>><span><?= e($option) ?></span></label><?php endforeach; ?>
          </div>
        </fieldset>

        <div class="quote-field quote-field--full"><label for="budget">Budget indicatif</label><select id="budget" name="budget"><option value=""></option><?php foreach(['Moins de 800 €','800 à 1 200 €','1 200 à 1 800 €','Plus de 1 800 €'] as $option): ?><option value="<?= e($option) ?>" <?= $values['budget']===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
        <div class="quote-field quote-field--full"><label for="message">Parlez-moi de votre projet :</label><textarea id="message" name="message"><?= e($values['message']) ?></textarea></div>
      </div>
      <div class="quote-actions"><p>* Champs obligatoires afin que je puisse étudier votre demande dans les meilleures conditions.</p><button class="quote-submit" type="submit">Envoyer ma demande →</button></div>
    </form>
  <?php endif; ?>
</main>
<footer class="site-footer"><a class="brand brand--footer" href="index.html"><img src="assets/img/logo-lukac.png" alt="Luka C" class="brand__logo brand__logo--footer"></a><p>DJ & animateur événementiel</p><div class="site-footer__links"><a href="index.html#prestations">Prestations</a><a href="index.html#formules">Formules</a><a href="index.html#disponibilites">Disponibilités</a><a href="index.html#avis">Avis clients</a></div><small>© 2026 Luka C • Tous droits réservés</small></footer>
<script src="assets/js/main.js"></script>
</body></html>