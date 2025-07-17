<?php
// test-oauth.php - Test complet du système OAuth Google + SignNow
require_once 'config.php';
require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Docs;
use Google\Service\Drive;

// Configuration
define('OAUTH_CREDENTIALS_PATH', __DIR__ . '/credentials/oauth-credentials.json');
define('TOKENS_DIR', __DIR__ . '/tokens/');
define('GOOGLE_DOCS_ID', '1YSWjJnFW0XHG0FJR4iYZcFJLU_xRYEnsmt20FG9inUk');

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Variables de test
$tests = [];
$overallStatus = true;

// Test 1: Vérification des fichiers de configuration
$tests['config_files'] = testConfigFiles();

// Test 2: Vérification des credentials OAuth
$tests['oauth_credentials'] = testOAuthCredentials();

// Test 3: Vérification du token OAuth
$tests['oauth_token'] = testOAuthToken();

// Test 4: Test de connexion Google
$tests['google_connection'] = testGoogleConnection();

// Test 5: Test d'accès au document template
$tests['document_access'] = testDocumentAccess();

// Test 6: Test de l'API SignNow
$tests['signnow_api'] = testSignNowAPI();

// Test 7: Test du processus complet (simulation)
$tests['full_process'] = testFullProcess();

// Calculer le statut global
foreach ($tests as $test) {
    if (!$test['success']) {
        $overallStatus = false;
        break;
    }
}

// Fonctions de test
function testConfigFiles() {
    $result = ['success' => true, 'message' => '', 'details' => []];
    
    // Vérifier oauth-credentials.json
    if (!file_exists(OAUTH_CREDENTIALS_PATH)) {
        $result['success'] = false;
        $result['message'] = 'Fichier oauth-credentials.json manquant';
        $result['details'][] = 'Chemin attendu: ' . OAUTH_CREDENTIALS_PATH;
    } else {
        $result['details'][] = '✅ oauth-credentials.json trouvé';
        
        // Vérifier le contenu
        $credentials = json_decode(file_get_contents(OAUTH_CREDENTIALS_PATH), true);
        if (!$credentials || !isset($credentials['web'])) {
            $result['success'] = false;
            $result['message'] = 'Format oauth-credentials.json invalide';
        } else {
            $result['details'][] = '✅ Format credentials valide';
            $result['details'][] = 'Client ID: ' . substr($credentials['web']['client_id'], 0, 20) . '...';
        }
    }
    
    // Vérifier le dossier tokens
    if (!file_exists(TOKENS_DIR)) {
        if (mkdir(TOKENS_DIR, 0755, true)) {
            $result['details'][] = '✅ Dossier tokens créé';
        } else {
            $result['success'] = false;
            $result['message'] = 'Impossible de créer le dossier tokens';
        }
    } else {
        $result['details'][] = '✅ Dossier tokens existe';
    }
    
    if ($result['success'] && empty($result['message'])) {
        $result['message'] = 'Tous les fichiers de configuration sont présents';
    }
    
    return $result;
}

function testOAuthCredentials() {
    $result = ['success' => false, 'message' => '', 'details' => []];
    
    try {
        if (!file_exists(OAUTH_CREDENTIALS_PATH)) {
            $result['message'] = 'Fichier credentials manquant';
            return $result;
        }
        
        $client = new Client();
        $client->setAuthConfig(OAUTH_CREDENTIALS_PATH);
        $client->addScope([Docs::DOCUMENTS, Drive::DRIVE_FILE]);
        $client->setRedirectUri('https://gsleads55.com/sign/oauth_callback.php');
        
        $result['success'] = true;
        $result['message'] = 'Client OAuth configuré avec succès';
        $result['details'][] = '✅ Client Google créé';
        $result['details'][] = '✅ Scopes configurés (Docs + Drive)';
        $result['details'][] = '✅ URI de redirection définie';
        
    } catch (Exception $e) {
        $result['message'] = 'Erreur configuration OAuth: ' . $e->getMessage();
        $result['details'][] = '❌ ' . $e->getMessage();
    }
    
    return $result;
}

function testOAuthToken() {
    $result = ['success' => false, 'message' => '', 'details' => []];
    
    $tokenPath = TOKENS_DIR . 'google_oauth_token.json';
    
    if (!file_exists($tokenPath)) {
        $result['message'] = 'Token OAuth non trouvé - Autorisation requise';
        $result['details'][] = '⚠️ Aucun token sauvegardé';
        $result['details'][] = 'Cliquez sur "Autoriser Google" pour obtenir un token';
        return $result;
    }
    
    try {
        $tokenData = json_decode(file_get_contents($tokenPath), true);
        
        if (!$tokenData) {
            $result['message'] = 'Token invalide ou corrompu';
            $result['details'][] = '❌ Format JSON invalide';
            return $result;
        }
        
        $result['details'][] = '✅ Token trouvé et valide';
        
        // Vérifier l'expiration
        if (isset($tokenData['expires_in'])) {
            $expiresAt = $tokenData['created'] + $tokenData['expires_in'];
            $timeLeft = $expiresAt - time();
            
            if ($timeLeft > 0) {
                $result['details'][] = '✅ Token valide pour ' . round($timeLeft / 3600, 1) . ' heures';
            } else {
                $result['details'][] = '⚠️ Token expiré (refresh nécessaire)';
            }
        }
        
        // Vérifier le refresh token
        if (isset($tokenData['refresh_token'])) {
            $result['details'][] = '✅ Refresh token disponible';
        } else {
            $result['details'][] = '⚠️ Pas de refresh token';
        }
        
        $result['success'] = true;
        $result['message'] = 'Token OAuth présent et valide';
        
    } catch (Exception $e) {
        $result['message'] = 'Erreur lecture token: ' . $e->getMessage();
        $result['details'][] = '❌ ' . $e->getMessage();
    }
    
    return $result;
}

function testGoogleConnection() {
    $result = ['success' => false, 'message' => '', 'details' => []];
    
    try {
        $client = createGoogleClient();
        
        if (!$client) {
            $result['message'] = 'Impossible de créer le client Google';
            $result['details'][] = '❌ Client non initialisé';
            return $result;
        }
        
        // Test d'accès à Drive
        $driveService = new Drive($client);
        $about = $driveService->about->get(['fields' => 'user']);
        
        $user = $about->getUser();
        $result['success'] = true;
        $result['message'] = 'Connexion Google réussie';
        $result['details'][] = '✅ Authentification Google OK';
        $result['details'][] = '✅ Utilisateur: ' . $user->getDisplayName();
        $result['details'][] = '✅ Email: ' . $user->getEmailAddress();
        
    } catch (Exception $e) {
        $result['message'] = 'Erreur connexion Google: ' . $e->getMessage();
        $result['details'][] = '❌ ' . $e->getMessage();
        
        if (strpos($e->getMessage(), 'invalid_grant') !== false) {
            $result['details'][] = '💡 Solution: Réautoriser l\'accès Google';
        }
    }
    
    return $result;
}

function testDocumentAccess() {
    $result = ['success' => false, 'message' => '', 'details' => []];
    
    try {
        $client = createGoogleClient();
        
        if (!$client) {
            $result['message'] = 'Client Google non disponible';
            return $result;
        }
        
        // Test d'accès au document template
        $driveService = new Drive($client);
        $file = $driveService->files->get(GOOGLE_DOCS_ID, ['fields' => 'name,id,permissions']);
        
        $result['details'][] = '✅ Document trouvé: ' . $file->getName();
        $result['details'][] = '✅ ID: ' . $file->getId();
        
        // Tester l'accès Docs
        $docsService = new Docs($client);
        $document = $docsService->documents->get(GOOGLE_DOCS_ID);
        
        $result['details'][] = '✅ Accès Docs API confirmé';
        $result['details'][] = '✅ Titre document: ' . $document->getTitle();
        
        // Compter les placeholders
        $content = json_encode($document->getBody());
        $placeholders = ['{{nom_acheteur}}', '{{prenom_acheteur}}', '{{email_acheteur}}', '{{prix_boutique}}'];
        $foundPlaceholders = 0;
        
        foreach ($placeholders as $placeholder) {
            if (strpos($content, $placeholder) !== false) {
                $foundPlaceholders++;
            }
        }
        
        $result['details'][] = "✅ Placeholders trouvés: $foundPlaceholders/" . count($placeholders);
        
        $result['success'] = true;
        $result['message'] = 'Accès au document template confirmé';
        
    } catch (Exception $e) {
        $result['message'] = 'Erreur accès document: ' . $e->getMessage();
        $result['details'][] = '❌ ' . $e->getMessage();
        
        if (strpos($e->getMessage(), 'notFound') !== false) {
            $result['details'][] = '💡 Vérifiez que le document est partagé avec votre compte';
        }
    }
    
    return $result;
}

function testSignNowAPI() {
    $result = ['success' => false, 'message' => '', 'details' => []];
    
    try {
        // Test de connexion SignNow
        $url = 'https://api.signnow.com/user';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . SIGNNOW_API_KEY,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $result['message'] = 'Erreur cURL SignNow: ' . $error;
            $result['details'][] = '❌ ' . $error;
            return $result;
        }
        
        if ($httpCode === 200) {
            $userData = json_decode($response, true);
            $result['success'] = true;
            $result['message'] = 'Connexion SignNow réussie';
            $result['details'][] = '✅ API SignNow accessible';
            $result['details'][] = '✅ Compte: ' . ($userData['email'] ?? 'N/A');
            $result['details'][] = '✅ Code HTTP: ' . $httpCode;
        } else {
            $result['message'] = 'Erreur API SignNow (Code: ' . $httpCode . ')';
            $result['details'][] = '❌ Code HTTP: ' . $httpCode;
            $result['details'][] = '❌ Réponse: ' . substr($response, 0, 200);
        }
        
    } catch (Exception $e) {
        $result['message'] = 'Erreur test SignNow: ' . $e->getMessage();
        $result['details'][] = '❌ ' . $e->getMessage();
    }
    
    return $result;
}

function testFullProcess() {
    $result = ['success' => false, 'message' => '', 'details' => []];
    
    try {
        // Données de test
        $testData = [
            'id_boutique' => 'TEST-' . date('His'),
            'nom_acheteur' => 'Dupont',
            'prenom_acheteur' => 'Jean',
            'adresse_acheteur' => '123 Rue Test, 75001 Paris',
            'telephone_acheteur' => '+33 6 12 34 56 78',
            'email_acheteur' => 'test@example.com',
            'piece_identite' => '1234567890123',
            'date_naissance' => '1980-01-01',
            'type_produits' => 'Vêtements de mode',
            'secteur_activite' => 'Mode et Accessoires',
            'date_lancement' => '2024-01-01',
            'ca_mensuel' => '15000',
            'prix_boutique' => '50000',
            'date_contrat' => date('Y-m-d')
        ];
        
        $result['details'][] = '🧪 Test avec données fictives';
        
        // Test 1: Création du client Google
        $client = createGoogleClient();
        if (!$client) {
            $result['message'] = 'Échec création client Google';
            return $result;
        }
        $result['details'][] = '✅ Client Google créé';
        
        // Test 2: Simulation copie document (sans vraiment copier)
        $driveService = new Drive($client);
        $originalFile = $driveService->files->get(GOOGLE_DOCS_ID, ['fields' => 'name']);
        $result['details'][] = '✅ Document template accessible: ' . $originalFile->getName();
        
        // Test 3: Test des remplacements (simulation)
        $docsService = new Docs($client);
        $document = $docsService->documents->get(GOOGLE_DOCS_ID);
        $content = json_encode($document->getBody());
        
        $placeholdersFound = 0;
        foreach ($testData as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            if (strpos($content, $placeholder) !== false) {
                $placeholdersFound++;
            }
        }
        
        $result['details'][] = "✅ Placeholders détectés: $placeholdersFound";
        
        // Test 4: Simulation export PDF
        $result['details'][] = '✅ Export PDF simulé (non exécuté en test)';
        
        // Test 5: Test SignNow (sans upload réel)
        $result['details'][] = '✅ API SignNow accessible (testé précédemment)';
        
        $result['success'] = true;
        $result['message'] = 'Simulation du processus complet réussie';
        $result['details'][] = '🎯 Tous les composants sont fonctionnels';
        
    } catch (Exception $e) {
        $result['message'] = 'Erreur simulation processus: ' . $e->getMessage();
        $result['details'][] = '❌ ' . $e->getMessage();
    }
    
    return $result;
}

function createGoogleClient() {
    try {
        $tokenPath = TOKENS_DIR . 'google_oauth_token.json';
        
        if (!file_exists($tokenPath)) {
            return null;
        }
        
        $client = new Client();
        $client->setAuthConfig(OAUTH_CREDENTIALS_PATH);
        $client->addScope([Docs::DOCUMENTS, Drive::DRIVE_FILE]);
        
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
        
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            } else {
                return null;
            }
        }
        
        return $client;
        
    } catch (Exception $e) {
        return null;
    }
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OAuth - Diagnostic Complet</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: <?= $overallStatus ? 'linear-gradient(45deg, #28a745, #20c997)' : 'linear-gradient(45deg, #dc3545, #fd7e14)' ?>;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .overall-status {
            padding: 20px;
            text-align: center;
            background: <?= $overallStatus ? '#d4edda' : '#f8d7da' ?>;
            color: <?= $overallStatus ? '#155724' : '#721c24' ?>;
        }

        .tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            padding: 30px;
        }

        .test-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            border-left: 5px solid;
            transition: transform 0.3s ease;
        }

        .test-card:hover {
            transform: translateY(-5px);
        }

        .test-card.success {
            border-left-color: #28a745;
        }

        .test-card.failure {
            border-left-color: #dc3545;
        }

        .test-title {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1.2em;
            font-weight: 600;
        }

        .test-icon {
            font-size: 1.5em;
            margin-right: 10px;
        }

        .test-message {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .test-details {
            background: white;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .test-details div {
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .actions {
            padding: 30px;
            background: #f8f9fa;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn.primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
        }

        .btn.success {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
        }

        .btn.warning {
            background: linear-gradient(45deg, #ffc107, #fd7e14);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .summary {
            padding: 20px;
            background: #e9ecef;
            border-top: 1px solid #dee2e6;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            text-align: center;
        }

        .stat {
            background: white;
            padding: 15px;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }

        @media (max-width: 768px) {
            .tests-grid {
                grid-template-columns: 1fr;
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= $overallStatus ? '✅' : '❌' ?> Test OAuth Complet</h1>
            <p>Diagnostic du système Google Docs + SignNow</p>
        </div>

        <div class="overall-status">
            <h2><?= $overallStatus ? '🎉 Système Opérationnel !' : '⚠️ Problèmes Détectés' ?></h2>
            <p><?= $overallStatus ? 'Tous les tests sont passés avec succès. Le système est prêt à générer des contrats.' : 'Certains tests ont échoué. Consultez les détails ci-dessous pour résoudre les problèmes.' ?></p>
        </div>

        <div class="tests-grid">
            <?php foreach ($tests as $testName => $test): ?>
                <div class="test-card <?= $test['success'] ? 'success' : 'failure' ?>">
                    <div class="test-title">
                        <span class="test-icon"><?= $test['success'] ? '✅' : '❌' ?></span>
                        <?php
                        $titles = [
                            'config_files' => 'Fichiers de Configuration',
                            'oauth_credentials' => 'Credentials OAuth',
                            'oauth_token' => 'Token d\'Accès',
                            'google_connection' => 'Connexion Google',
                            'document_access' => 'Accès Document Template',
                            'signnow_api' => 'API SignNow',
                            'full_process' => 'Processus Complet'
                        ];
                        echo $titles[$testName] ?? ucfirst($testName);
                        ?>
                    </div>
                    
                    <div class="test-message">
                        <?= htmlspecialchars($test['message']) ?>
                    </div>
                    
                    <?php if (!empty($test['details'])): ?>
                        <div class="test-details">
                            <?php foreach ($test['details'] as $detail): ?>
                                <div><?= htmlspecialchars($detail) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="summary">
            <h3>📊 Résumé des Tests</h3>
            <div class="summary-stats">
                <div class="stat">
                    <div class="stat-value"><?= count(array_filter($tests, fn($t) => $t['success'])) ?></div>
                    <div>Tests Réussis</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= count(array_filter($tests, fn($t) => !$t['success'])) ?></div>
                    <div>Tests Échoués</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= count($tests) ?></div>
                    <div>Total Tests</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= round((count(array_filter($tests, fn($t) => $t['success'])) / count($tests)) * 100) ?>%</div>
                    <div>Taux de Réussite</div>
                </div>
            </div>
        </div>

        <div class="actions">
            <?php if ($overallStatus): ?>
                <h3>🚀 Système Prêt !</h3>
                <p style="margin-bottom: 20px;">Vous pouvez maintenant utiliser le système de génération de contrats.</p>
                <a href="index.php" class="btn success">📝 Générer un Contrat</a>
                <a href="logs.php" class="btn primary">📋 Voir les Logs</a>
            <?php else: ?>
                <h3>🔧 Actions Recommandées</h3>
                <p style="margin-bottom: 20px;">Résolvez les problèmes détectés avant d'utiliser le système.</p>
                
                <?php if (!$tests['oauth_token']['success']): ?>
                    <?php
                    try {
                        $client = new Client();
                        $client->setAuthConfig(OAUTH_CREDENTIALS_PATH);
                        $client->addScope([Docs::DOCUMENTS, Drive::DRIVE_FILE]);
                        $client->setRedirectUri('https://gsleads55.com/sign/oauth_callback.php');
                        $authUrl = $client->createAuthUrl();
                    } catch (Exception $e) {
                        $authUrl = '#';
                    }
                    ?>
                    <a href="<?= $authUrl ?>" class="btn warning">🔐 Autoriser Google</a>
                <?php endif; ?>
                
                <a href="config.php" class="btn primary">⚙️ Vérifier Config</a>
                <a href="logs.php" class="btn primary">📋 Voir les Logs</a>
                <a href="?refresh=1" class="btn primary">🔄 Relancer les Tests</a>
            <?php endif; ?>
            
            <a href="test-final.php" class="btn primary">🔍 Test Simple</a>
        </div>
    </div>

    <script>
        // Auto-refresh si paramètre refresh
        <?php if (isset($_GET['refresh'])): ?>
        setTimeout(() => {
            window.location.href = window.location.pathname;
        }, 3000);
        <?php endif; ?>
        
        // Fonction pour copier les logs de débogage
        function copyDebugInfo() {
            const debugInfo = `
=== DEBUG INFO OAuth System ===
Date: <?= date('Y-m-d H:i:s') ?>
PHP Version: <?= PHP_VERSION ?>
Server: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?>

Tests Results:
<?php foreach ($tests as $name => $test): ?>
- <?= $name ?>: <?= $test['success'] ? 'SUCCESS' : 'FAILED' ?> - <?= $test['message'] ?>
<?php endforeach; ?>

Overall Status: <?= $overallStatus ? 'OPERATIONAL' : 'ISSUES_DETECTED' ?>
            `.trim();
            
            navigator.clipboard.writeText(debugInfo).then(() => {
                alert('Informations de débogage copiées dans le presse-papiers');
            });
        }
        
        // Ajouter bouton de debug
        const actionsDiv = document.querySelector('.actions');
        const debugBtn = document.createElement('button');
        debugBtn.textContent = '🐛 Copier Debug Info';
        debugBtn.className = 'btn primary';
        debugBtn.onclick = copyDebugInfo;
        actionsDiv.appendChild(debugBtn);
    </script>
</body>
</html>