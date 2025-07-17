<?php
// test-manual.php - Test manuel avec différents IDs
require_once 'vendor/autoload.php';
use Google\Client;
use Google\Service\Drive;

$tokenPath = __DIR__ . '/tokens/google_oauth_token.json';
$credentialsPath = __DIR__ . '/credentials/oauth-credentials.json';

// IDs à tester
$documentIds = [
    'ID_from_URL' => '1yvDeO7k8stK_UQJXfzy425cAu1dwiEeY9WBs5oiXoeY',
    'Alternative' => '1yvDeO7k8stK_UQJXfzy425cAu1dwiEeY9WBs5oiXoeY',
];

echo "<h2>Test Manuel d'Accès aux Documents</h2>";

try {
    // Créer le client
    $client = new Client();
    $client->setAuthConfig($credentialsPath);
    $client->addScope([\Google\Service\Drive::DRIVE_READONLY]);
    
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
    
    $driveService = new Drive($client);
    
    // Test 1: Lister TOUS les documents accessibles
    echo "<h3>📋 Documents Accessibles dans votre Drive :</h3>";
    
    $files = $driveService->files->listFiles([
        'q' => "mimeType='application/vnd.google-apps.document'",
        'fields' => 'files(id,name,createdTime)',
        'pageSize' => 20
    ]);
    
    if (count($files->getFiles()) == 0) {
        echo "<p>❌ Aucun document trouvé dans votre Drive</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Nom</th><th>ID</th><th>Date</th><th>Test</th></tr>";
        
        foreach ($files->getFiles() as $file) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($file->getName()) . "</td>";
            echo "<td><code>" . $file->getId() . "</code></td>";
            echo "<td>" . $file->getCreatedTime() . "</td>";
            
            // Test d'accès immédiat
            try {
                $testFile = $driveService->files->get($file->getId());
                echo "<td style='color: green;'>✅ Accessible</td>";
                
                // Si c'est un contrat, l'afficher
                if (stripos($file->getName(), 'contrat') !== false) {
                    echo "</tr><tr><td colspan='4' style='background: #e8f5e8;'>";
                    echo "<strong>🎯 DOCUMENT TROUVÉ !</strong><br>";
                    echo "Nom: " . $file->getName() . "<br>";
                    echo "ID à utiliser: <code>" . $file->getId() . "</code>";
                    echo "</td>";
                }
            } catch (Exception $e) {
                echo "<td style='color: red;'>❌ Erreur</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 2: Tester les IDs spécifiques
    echo "<h3>🔍 Test des IDs Spécifiques :</h3>";
    
    foreach ($documentIds as $source => $docId) {
        echo "<p><strong>$source</strong> - ID: <code>$docId</code> - ";
        try {
            $file = $driveService->files->get($docId);
            echo "<span style='color: green;'>✅ TROUVÉ - " . $file->getName() . "</span></p>";
        } catch (Exception $e) {
            echo "<span style='color: red;'>❌ ERREUR - " . $e->getMessage() . "</span></p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERREUR GÉNÉRALE: " . $e->getMessage() . "</p>";
}
?>

<h3>💡 Instructions :</h3>
<ol>
<li>Si des documents apparaissent dans la liste, utilisez l'ID du document "Contrat"</li>
<li>Si aucun document n'apparaît, créez un nouveau document simple</li>
<li>Assurez-vous que le document est partagé "Anyone with the link"</li>
</ol>

<a href="test-oauth.php">← Retour aux tests complets</a>