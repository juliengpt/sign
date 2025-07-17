<?php
// oauth_callback.php - Callback pour l'autorisation OAuth Google
require_once 'config.php';
require_once 'vendor/autoload.php';

use Google\Client;

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
define('OAUTH_CREDENTIALS_PATH', __DIR__ . '/credentials/oauth-credentials.json');
define('TOKENS_DIR', __DIR__ . '/tokens/');

// Créer le dossier tokens s'il n'existe pas
if (!file_exists(TOKENS_DIR)) {
    mkdir(TOKENS_DIR, 0755, true);
}

$message = '';
$error = '';

try {
    // Vérifier si on a un code d'autorisation
    if (!isset($_GET['code'])) {
        if (isset($_GET['error'])) {
            $error = "❌ Autorisation refusée: " . htmlspecialchars($_GET['error']);
            logMessage("OAuth autorisation refusée: " . $_GET['error'], 'ERROR');
        } else {
            $error = "❌ Code d'autorisation manquant";
            logMessage("OAuth code d'autorisation manquant", 'ERROR');
        }
    } else {
        // Traiter le code d'autorisation
        $authCode = $_GET['code'];
        
        // Créer le client Google
        $client = new Client();
        $client->setAuthConfig(OAUTH_CREDENTIALS_PATH);
        $client->setRedirectUri('https://gsleads55.com/sign/oauth_callback.php');
        
        // Échanger le code contre un token d'accès
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        
        if (isset($accessToken['error'])) {
            $error = "❌ Erreur lors de l'échange du code: " . $accessToken['error'];
            logMessage("OAuth erreur échange code: " . $accessToken['error'], 'ERROR');
        } else {
            // Sauvegarder le token
            $tokenPath = TOKENS_DIR . 'google_oauth_token.json';
            file_put_contents($tokenPath, json_encode($accessToken));
            
            // Marquer l'autorisation comme réussie en session
            $_SESSION['oauth_required'] = false;
            $_SESSION['oauth_success'] = true;
            
            $message = "✅ Autorisation Google réussie ! Vous pouvez maintenant utiliser le système de génération de contrats.";
            logMessage("OAuth autorisation réussie et token sauvegardé", 'SUCCESS');
            
            // Tester l'accès
            testGoogleAccess($client);
        }
    }
    
} catch (Exception $e) {
    $error = "❌ Erreur OAuth: " . $e->getMessage();
    logMessage("Erreur OAuth callback: " . $e->getMessage(), 'ERROR');
}

// Fonction pour tester l'accès Google
function testGoogleAccess($client) {
    try {
        $client->addScope([
            \Google\Service\Docs::DOCUMENTS,
            \Google\Service\Drive::DRIVE_FILE
        ]);
        
        // Tester l'accès à Drive
        $driveService = new \Google\Service\Drive($client);
        $about = $driveService->about->get(['fields' => 'user']);
        
        logMessage("Test accès Google réussi pour l'utilisateur: " . $about->getUser()->getDisplayName(), 'SUCCESS');
        
        // Tester l'accès au document spécifique
        $docId = '1YSWjJnFW0XHG0FJR4iYZcFJLU_xRYEnsmt20FG9inUk';
        $file = $driveService->files->get($docId, ['fields' => 'name,id']);
        
        logMessage("Accès au document template confirmé: " . $file->getName(), 'SUCCESS');
        
    } catch (Exception $e) {
        logMessage("Erreur test accès Google: " . $e->getMessage(), 'WARNING');
    }
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorisation Google OAuth</title>
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

        .container {
            max-width: 600px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
        }

        .header {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            padding: 30px;
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .content {
            padding: 40px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }

        .icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }

        .btn.secondary {
            background: linear-gradient(45deg, #007bff, #0056b3);
        }

        .steps {
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .steps h3 {
            color: #343a40;
            margin-bottom: 15px;
        }

        .steps ol {
            padding-left: 20px;
        }

        .steps li {
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Autorisation Google</h1>
            <p>Système de Génération de Contrats</p>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="icon">✅</div>
                <div class="success">
                    <h3>Autorisation Réussie !</h3>
                    <p><?= $message ?></p>
                </div>
                
                <div class="steps">
                    <h3>🎯 Prochaines étapes :</h3>
                    <ol>
                        <li><strong>Retournez au formulaire</strong> de génération de contrats</li>
                        <li><strong>Remplissez les informations</strong> du client et de la boutique</li>
                        <li><strong>Générez le contrat</strong> - le système utilisera maintenant vos autorisations</li>
                        <li><strong>Le contrat sera automatiquement</strong> envoyé via SignNow</li>
                    </ol>
                </div>
                
                <a href="index.php" class="btn">🚀 Accéder au Formulaire</a>
                <a href="test-oauth.php" class="btn secondary">🔍 Tester le Système</a>
                
            <?php elseif ($error): ?>
                <div class="icon">❌</div>
                <div class="error">
                    <h3>Erreur d'Autorisation</h3>
                    <p><?= $error ?></p>
                </div>
                
                <div class="info">
                    <h4>💡 Que faire maintenant ?</h4>
                    <p>• Assurez-vous d'accepter toutes les autorisations demandées</p>
                    <p>• Vérifiez que vous utilisez le bon compte Google</p>
                    <p>• Réessayez l'autorisation en cliquant sur le bouton ci-dessous</p>
                </div>
                
                <a href="index.php" class="btn">🔄 Réessayer</a>
                <a href="logs.php" class="btn secondary">📋 Voir les Logs</a>
                
            <?php else: ?>
                <div class="icon">🔄</div>
                <h3>Traitement en cours...</h3>
                <p>Veuillez patienter pendant que nous traitons votre autorisation.</p>
                
                <div class="info">
                    <p>Si cette page ne se met pas à jour automatiquement, vérifiez les logs ou retournez au formulaire.</p>
                </div>
                
                <a href="index.php" class="btn">← Retour au Formulaire</a>
            <?php endif; ?>

            <div class="steps" style="margin-top: 30px;">
                <h3>ℹ️ Informations sur l'Autorisation</h3>
                <p><strong>Autorisations demandées :</strong></p>
                <ul style="text-align: left; margin-top: 10px;">
                    <li>📄 <strong>Google Docs</strong> - Pour créer et modifier des documents</li>
                    <li>💾 <strong>Google Drive</strong> - Pour sauvegarder et exporter les contrats</li>
                    <li>🔒 <strong>Accès hors ligne</strong> - Pour éviter de redemander l'autorisation</li>
                </ul>
                
                <div class="info" style="margin-top: 15px;">
                    <p><strong>🔐 Sécurité :</strong> Vos données restent privées. Le système accède uniquement aux documents nécessaires pour générer les contrats.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect vers le formulaire si succès après 5 secondes
        <?php if ($message): ?>
        setTimeout(() => {
            if (confirm('Redirection automatique vers le formulaire dans 5 secondes. Continuer ?')) {
                window.location.href = 'index.php';
            }
        }, 5000);
        <?php endif; ?>
        
        // Fermer la fenêtre si ouverte en popup
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage('oauth_complete', '*');
        }
    </script>
</body>
</html>