<?php
// error.php - Gestionnaire d'erreurs personnalisé
require_once 'config.php';

$error_code = $_GET['code'] ?? '500';
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Log de l'erreur
if (function_exists('logMessage')) {
    logMessage("Erreur $error_code sur $request_uri - User Agent: $user_agent", 'ERROR');
}

// Configuration des erreurs
$errors = [
    '400' => [
        'title' => 'Requête Invalide',
        'message' => 'La requête envoyée n\'est pas valide.',
        'icon' => '⚠️',
        'color' => '#ffc107'
    ],
    '401' => [
        'title' => 'Non Autorisé',
        'message' => 'Vous n\'êtes pas autorisé à accéder à cette ressource.',
        'icon' => '🔒',
        'color' => '#dc3545'
    ],
    '403' => [
        'title' => 'Accès Interdit',
        'message' => 'L\'accès à cette ressource est interdit.',
        'icon' => '🚫',
        'color' => '#dc3545'
    ],
    '404' => [
        'title' => 'Page Non Trouvée',
        'message' => 'La page que vous cherchez n\'existe pas ou a été déplacée.',
        'icon' => '🔍',
        'color' => '#6c757d'
    ],
    '500' => [
        'title' => 'Erreur Serveur',
        'message' => 'Une erreur interne du serveur s\'est produite. Notre équipe technique a été notifiée.',
        'icon' => '⚙️',
        'color' => '#dc3545'
    ],
    '503' => [
        'title' => 'Service Indisponible',
        'message' => 'Le service est temporairement indisponible. Veuillez réessayer dans quelques minutes.',
        'icon' => '🔧',
        'color' => '#ffc107'
    ]
];

$error_info = $errors[$error_code] ?? $errors['500'];

// Définir le code de réponse HTTP
http_response_code(intval($error_code));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $error_info['title'] ?> - <?= $error_code ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .error-icon {
            font-size: 5em;
            margin-bottom: 20px;
            display: block;
        }

        .error-code {
            font-size: 4em;
            font-weight: bold;
            color: <?= $error_info['color'] ?>;
            margin-bottom: 10px;
        }

        .error-title {
            font-size: 2em;
            color: #333;
            margin-bottom: 15px;
        }

        .error-message {
            font-size: 1.1em;
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .error-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;