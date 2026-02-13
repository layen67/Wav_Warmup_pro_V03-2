<?php
/**
 * Exemple de script de réception de Webhook (Receiver)
 * Placez ce fichier sur un serveur accessible publiquement et copiez son URL dans les paramètres du plugin.
 */

// Récupérer le contenu brut de la requête (JSON)
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Log pour vérification (dans un fichier webhook.log)
$log_entry = date('Y-m-d H:i:s') . " - Reçu : " . print_r($data, true) . "\n------------------\n";
file_put_contents('webhook.log', $log_entry, FILE_APPEND);

// Traitement selon l'événement
if (isset($data['event'])) {
    switch ($data['event']) {
        case 'MessageSent':
            // Exemple : Faire quelque chose quand un message est envoyé
            break;

        case 'MessageDeliveryFailed':
            // Exemple : Désactiver l'utilisateur dans votre base de données locale
            $email = $data['payload']['to'] ?? 'inconnu';
            $error = $data['payload']['error'] ?? 'Erreur inconnue';
            // my_custom_logic_disable_user($email);
            break;

        case 'MessageBounced':
            // Exemple : Notifier l'admin via Slack
            break;
    }
}

// Répondre toujours avec 200 OK pour confirmer la réception
http_response_code(200);
echo json_encode(['status' => 'success']);
