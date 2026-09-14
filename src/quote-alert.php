<?php

function send_new_quote_alert(int $quoteId, string $eventType, string $eventDate): bool
{
    $to = trim((string)getenv('LUKAC_ALERT_EMAIL'));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Luka C: LUKAC_ALERT_EMAIL is not configured.');
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $eventDate);
    $formattedDate = $date instanceof DateTime ? $date->format('d/m/Y') : $eventDate;

    $subject = 'Nouvelle demande de devis - Luka C';
    $message = "Bonjour,\n\n"
        . "Une nouvelle demande de devis vient d'être reçue sur lukac.fr.\n\n"
        . "Événement : {$eventType}\n"
        . "Date : {$formattedDate}\n\n"
        . "Ouvrir la demande : https://lukac.fr/admin/devis.php?id={$quoteId}\n\n"
        . "Administration Luka C\n";

    $headers = implode("\r\n", [
        'From: Luka C <no-reply@lukac.fr>',
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    $sent = @mail($to, $subject, $message, $headers);
    if (!$sent) {
        error_log('Luka C: quote alert email failed for quote #' . $quoteId);
    }
    return $sent;
}
