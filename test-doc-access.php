<?php
// test-doc-access.php - Test rapide d'accès au document spécifique
require_once 'vendor/autoload.php';
use Google\Client;
use Google\Service\Docs;
use Google\Service\Drive;

// ID du document depuis votre URL
$documentId = '1yvDeO7k8stK_UQJXfzy425cAu1dwiEeY9WBs5oiXoeY';
$tokenPath = __DIR__ . '/tokens/google_oauth_token.json';
$credentialsPath = __DIR__ . '/credentials/oauth-credentials.json';

echo "<h2>Test d'Accès au Document Spécifique</h2>";

try {
    // Créer le client
    $client = new Client();
    $client->setAuthConfig($credentialsPath);
    $client->addScope([Docs::DOCUMENTS, Drive::DRIVE_FILE]);
    
    // Charger le token
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
        
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            }
        }
    }
    
    // Test Drive
    echo "<p><strong>Test 1 - Accès Drive :</strong> ";
    $driveService = new Drive($client);
    $file = $driveService->files->get($documentId, ['fields' => 'name,id,owners']);
    echo "✅ SUCCÈS - Document trouvé : " . $file->getName() . "</p>";
    
    // Test Docs
    echo "<p><strong>Test 2 - Accès Docs :</strong> ";
    $docsService = new Docs($client);
    $document = $docsService->documents->get($documentId);
    echo "✅ SUCCÈS - Titre : " . $document->getTitle() . "</p>";
    
    // Test placeholders
    echo "<p><strong>Test 3 - Placeholders :</strong> ";
    $content = json_encode($document->getBody());
    $placeholders = ['{{id_boutique}}', '{{nom_acheteur}}', '{{prenom_acheteur}}'];
    $found = 0;
    foreach ($placeholders as $placeholder) {
        if (strpos($content, $placeholder) !== false) {
            $found++;
        }
    }
    echo "✅ Trouvés : $found placeholders</p>";
    
    echo "<h3 style='color: green;'>🎉 TOUT FONCTIONNE !</h3>";
    echo "<p>L'ID du document est correct : <code>$documentId</code></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ ERREUR</h3>";
    echo "<p>Erreur : " . $e->getMessage() . "</p>";
    
    if (strpos($e->getMessage(), 'notFound') !== false) {
        echo "<p><strong>Solution :</strong> L'ID du document est incorrect ou le document n'est pas accessible.</p>";
        echo "<p>ID testé : <code>$documentId</code></p>";
    }
}
?>

<a href="test-oauth.php">← Retour aux tests complets</a>