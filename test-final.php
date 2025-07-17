<?php
require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Test Final SignNow</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:0 auto;padding:20px;} .success{color:green;} .error{color:red;} .btn{background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;}</style>";
echo "</head><body>";

echo "<h1>🔍 Test Final SignNow</h1>";

// Test API Key
if (defined('SIGNNOW_API_KEY')) {
    echo "<p class='success'>✅ API Key définie</p>";
    
    // Test connexion SignNow
    echo "<p>🔗 Test de connexion à l'API SignNow...</p>";
    
    $result = testSignNowConnection();
    
    if ($result['success']) {
        echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:20px 0;'>";
        echo "<h2>🎉 SUCCÈS TOTAL !</h2>";
        echo "<p><strong>✅ " . $result['message'] . "</strong></p>";
        echo "<p>🎯 Votre système est prêt pour l'application SignNow !</p>";
        echo "<p><a href='index.php' class='btn'>🚀 Accéder à l'Application Principale</a></p>";
        echo "</div>";
    } else {
        echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;margin:20px 0;'>";
        echo "<h2>❌ Problème de Connexion</h2>";
        echo "<p>" . $result['message'] . "</p>";
        echo "</div>";
    }
} else {
    echo "<p class='error'>❌ API Key non définie dans config.php</p>";
}

echo "<hr>";
echo "<h3>📊 Informations Système</h3>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Serveur:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu') . "</p>";
echo "<p><strong>Heure:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "</body></html>";
?>