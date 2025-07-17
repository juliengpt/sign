<?php
// test-dompdf.php - Test minimal et isolé de DomPDF
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(120);

echo "<!DOCTYPE html>";
echo "<html><head><title>Test DomPDF Minimal</title>";
echo "<style>body{font-family:Arial;max-width:800px;margin:0 auto;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow-x:auto;}</style>";
echo "</head><body>";

echo "<h1>🔬 Test DomPDF Minimal</h1>";
echo "<p><strong>Objectif :</strong> Isoler et tester DomPDF étape par étape</p>";

// Configuration de base
echo "<h2>📋 Configuration</h2>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Mémoire limite:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>Temps limite:</strong> " . ini_get('max_execution_time') . "s</p>";

// Test 1: Vérification autoload
echo "<h2>1. 🔧 Test Autoload</h2>";
try {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "<p class='success'>✅ Autoload chargé</p>";
    } else {
        echo "<p class='error'>❌ vendor/autoload.php manquant</p>";
        echo "<p class='warning'>⚠️ Exécuter: <code>composer install</code></p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur autoload: " . $e->getMessage() . "</p>";
    exit;
}

// Test 2: Vérification classe DomPDF
echo "<h2>2. 📦 Test Classe DomPDF</h2>";
try {
    if (class_exists('Dompdf\\Dompdf')) {
        echo "<p class='success'>✅ Classe Dompdf disponible</p>";
    } else {
        echo "<p class='error'>❌ Classe Dompdf introuvable</p>";
        echo "<p class='warning'>⚠️ DomPDF non installé correctement</p>";
        exit;
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur classe: " . $e->getMessage() . "</p>";
    exit;
}

// Test 3: Création instance
echo "<h2>3. 🏗️ Test Création Instance</h2>";
try {
    $dompdf = new Dompdf\Dompdf();
    echo "<p class='success'>✅ Instance DomPDF créée</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur création instance: " . $e->getMessage() . "</p>";
    exit;
}

// Test 4: Configuration options
echo "<h2>4. ⚙️ Test Configuration Options</h2>";
try {
    $options = new Dompdf\Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', false); // Désactiver remote pour éviter problèmes
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', false); // Sécurité
    
    $dompdf->setOptions($options);
    echo "<p class='success'>✅ Options configurées</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur options: " . $e->getMessage() . "</p>";
    exit;
}

// Test 5: HTML très simple
echo "<h2>5. 📝 Test HTML Ultra-Simple</h2>";
$simple_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: blue; }
    </style>
</head>
<body>
    <h1>Test DomPDF</h1>
    <p>Ceci est un test minimal.</p>
    <p>Date: ' . date('d/m/Y H:i:s') . '</p>
</body>
</html>';

try {
    $dompdf->loadHtml($simple_html);
    echo "<p class='success'>✅ HTML chargé (taille: " . strlen($simple_html) . " caractères)</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur chargement HTML: " . $e->getMessage() . "</p>";
    exit;
}

// Test 6: Configuration papier
echo "<h2>6. 📄 Test Configuration Papier</h2>";
try {
    $dompdf->setPaper('A4', 'portrait');
    echo "<p class='success'>✅ Format A4 portrait configuré</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur configuration papier: " . $e->getMessage() . "</p>";
    exit;
}

// Test 7: Rendu PDF (critique)
echo "<h2>7. 🎯 Test Rendu PDF</h2>";
echo "<p class='info'>ℹ️ Mémoire avant rendu: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</p>";

try {
    $start_time = microtime(true);
    $dompdf->render();
    $end_time = microtime(true);
    
    echo "<p class='success'>✅ PDF rendu avec succès</p>";
    echo "<p class='info'>ℹ️ Temps de rendu: " . round(($end_time - $start_time) * 1000, 2) . " ms</p>";
    echo "<p class='info'>ℹ️ Mémoire après rendu: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</p>";
    echo "<p class='info'>ℹ️ Pic mémoire: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ ERREUR RENDU: " . $e->getMessage() . "</p>";
    echo "<p class='warning'>⚠️ Mémoire au moment de l'erreur: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</p>";
    
    // Diagnostics supplémentaires
    echo "<h3>🔍 Diagnostic Erreur Rendu</h3>";
    echo "<ul>";
    echo "<li>Extensions mbstring: " . (extension_loaded('mbstring') ? "✅" : "❌") . "</li>";
    echo "<li>Extensions dom: " . (extension_loaded('dom') ? "✅" : "❌") . "</li>";
    echo "<li>Extensions gd: " . (extension_loaded('gd') ? "✅" : "❌") . "</li>";
    echo "<li>Extensions xml: " . (extension_loaded('xml') ? "✅" : "❌") . "</li>";
    echo "</ul>";
    
    exit;
}

// Test 8: Sortie PDF
echo "<h2>8. 💾 Test Sortie PDF</h2>";
try {
    $pdf_content = $dompdf->output();
    $pdf_size = strlen($pdf_content);
    
    echo "<p class='success'>✅ PDF généré (taille: " . number_format($pdf_size) . " octets)</p>";
    
    if ($pdf_size > 1000) {
        echo "<p class='success'>✅ Taille PDF correcte (> 1Ko)</p>";
    } else {
        echo "<p class='warning'>⚠️ PDF très petit, vérifier le contenu</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur sortie: " . $e->getMessage() . "</p>";
    exit;
}

// Test 9: Sauvegarde fichier
echo "<h2>9. 💾 Test Sauvegarde</h2>";
try {
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = 'test_dompdf_' . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = $upload_dir . $filename;
    
    file_put_contents($filepath, $pdf_content);
    
    if (file_exists($filepath)) {
        echo "<p class='success'>✅ PDF sauvegardé: $filename</p>";
        echo "<p class='info'>ℹ️ Chemin: $filepath</p>";
        echo "<p class='info'>ℹ️ Taille fichier: " . number_format(filesize($filepath)) . " octets</p>";
        
        // Lien de téléchargement
        echo "<p><a href='uploads/$filename' target='_blank' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>📥 Télécharger le PDF de test</a></p>";
        
    } else {
        echo "<p class='error'>❌ Échec sauvegarde</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur sauvegarde: " . $e->getMessage() . "</p>";
}

// Test 10: Test avec contenu plus complexe
echo "<h2>10. 📊 Test HTML Complexe (Optionnel)</h2>";
if (isset($_GET['complex']) && $_GET['complex'] == '1') {
    
    $complex_html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Complexe</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; line-height: 1.6; }
        .header { text-align: center; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 20px; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background: #f2f2f2; }
        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Document de Test Complexe</h1>
        <p>Généré le ' . date('d/m/Y à H:i:s') . '</p>
    </div>
    
    <div class="section">
        <h2>Section 1: Introduction</h2>
        <p>Ce document teste la capacité de DomPDF à gérer du contenu plus complexe avec des styles CSS avancés.</p>
        <ul>
            <li>Élément de liste 1</li>
            <li>Élément de liste 2</li>
            <li>Élément de liste 3</li>
        </ul>
    </div>
    
    <div class="section">
        <h2>Section 2: Tableau</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Valeur</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Test 1</td>
                    <td>100</td>
                    <td>Premier test</td>
                </tr>
                <tr>
                    <td>Test 2</td>
                    <td>200</td>
                    <td>Deuxième test</td>
                </tr>
                <tr>
                    <td>Test 3</td>
                    <td>300</td>
                    <td>Troisième test</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <h2>Section 3: Informations Techniques</h2>
        <p><strong>Version PHP:</strong> ' . PHP_VERSION . '</p>
        <p><strong>Mémoire limite:</strong> ' . ini_get('memory_limit') . '</p>
        <p><strong>Serveur:</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu') . '</p>
        <p><strong>Extensions chargées:</strong></p>
        <ul>';
    
    $extensions = ['mbstring', 'dom', 'gd', 'xml', 'curl', 'json'];
    foreach ($extensions as $ext) {
        $complex_html .= '<li>' . $ext . ': ' . (extension_loaded($ext) ? 'Activée' : 'Désactivée') . '</li>';
    }
    
    $complex_html .= '</ul>
    </div>
    
    <div class="footer">
        <p>Document généré automatiquement par DomPDF</p>
        <p>Test effectué le ' . date('d/m/Y à H:i:s') . '</p>
    </div>
</body>
</html>';

    try {
        echo "<p class='info'>ℹ️ Test avec HTML complexe (tableaux, CSS, etc.)</p>";
        
        $dompdf_complex = new Dompdf\Dompdf();
        $dompdf_complex->setOptions($options);
        $dompdf_complex->loadHtml($complex_html);
        $dompdf_complex->setPaper('A4', 'portrait');
        
        $start_time = microtime(true);
        $dompdf_complex->render();
        $end_time = microtime(true);
        
        echo "<p class='success'>✅ HTML complexe rendu avec succès</p>";
        echo "<p class='info'>ℹ️ Temps de rendu complexe: " . round(($end_time - $start_time) * 1000, 2) . " ms</p>";
        
        $complex_pdf = $dompdf_complex->output();
        $complex_filename = 'test_dompdf_complex_' . date('Y-m-d_H-i-s') . '.pdf';
        $complex_filepath = $upload_dir . $complex_filename;
        
        file_put_contents($complex_filepath, $complex_pdf);
        echo "<p class='success'>✅ PDF complexe sauvegardé: $complex_filename</p>";
        echo "<p><a href='uploads/$complex_filename' target='_blank' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>📥 Télécharger PDF Complexe</a></p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur HTML complexe: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p><a href='?complex=1' style='background:#ffc107;color:#212529;padding:10px 20px;text-decoration:none;border-radius:5px;'>🧪 Tester HTML Complexe</a></p>";
}

// Résumé final
echo "<h2>📊 Résumé Final</h2>";

$all_tests_passed = true;
$issues = [];

if (!class_exists('Dompdf\\Dompdf')) {
    $all_tests_passed = false;
    $issues[] = "DomPDF non installé";
}

if (!extension_loaded('mbstring')) {
    $all_tests_passed = false;
    $issues[] = "Extension mbstring manquante";
}

if (!extension_loaded('dom')) {
    $all_tests_passed = false;
    $issues[] = "Extension dom manquante";
}

$memory_limit = ini_get('memory_limit');
if (preg_match('/(\d+)/', $memory_limit, $matches) && $matches[1] < 256) {
    $issues[] = "Mémoire limite faible ($memory_limit)";
}

if ($all_tests_passed && empty($issues)) {
    echo "<div style='background:#d4edda;padding:20px;border-radius:10px;border:2px solid #28a745;'>";
    echo "<h3 style='color:#155724;margin:0 0 10px 0;'>🎉 TOUS LES TESTS RÉUSSIS !</h3>";
    echo "<p style='color:#155724;margin:0;'>DomPDF fonctionne parfaitement. Vous pouvez maintenant :</p>";
    echo "<ul style='color:#155724;'>";
    echo "<li>✅ Utiliser backend-html.php (version complète avec PDF)</li>";
    echo "<li>✅ Générer des contrats PDF</li>";
    echo "<li>✅ Envoyer via SignNow</li>";
    echo "</ul>";
    echo "<p style='margin-top:15px;'><a href='index.php' style='background:#28a745;color:white;padding:12px 25px;text-decoration:none;border-radius:5px;font-weight:bold;'>🚀 Tester l'Application Complète</a></p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;padding:20px;border-radius:10px;border:2px solid #dc3545;'>";
    echo "<h3 style='color:#721c24;margin:0 0 10px 0;'>⚠️ PROBLÈMES DÉTECTÉS</h3>";
    
    if (!empty($issues)) {
        echo "<p style='color:#721c24;'>Problèmes à résoudre :</p>";
        echo "<ul style='color:#721c24;'>";
        foreach ($issues as $issue) {
            echo "<li>❌ $issue</li>";
        }
        echo "</ul>";
    }
    
    echo "<h4 style='color:#721c24;'>Solutions recommandées :</h4>";
    echo "<ul style='color:#721c24;'>";
    echo "<li>🔧 Exécuter <code>composer install</code> si vendor/ manque</li>";
    echo "<li>🔧 Contacter Hostinger pour activer les extensions PHP manquantes</li>";
    echo "<li>🔧 Augmenter memory_limit à 512M dans .htaccess ou via support</li>";
    echo "<li>🔧 Utiliser backend-simple.php en attendant la résolution</li>";
    echo "</ul>";
    echo "</div>";
}

// Actions disponibles
echo "<h2>🔗 Actions Disponibles</h2>";
echo "<div style='text-align:center;margin:30px 0;'>";
echo "<a href='index.php' style='background:#007bff;color:white;padding:12px 20px;text-decoration:none;border-radius:5px;margin:5px;display:inline-block;'>🏠 Formulaire Principal</a>";
echo "<a href='backend-simple.php' style='background:#17a2b8;color:white;padding:12px 20px;text-decoration:none;border-radius:5px;margin:5px;display:inline-block;'>🧪 Test Sans PDF</a>";
echo "<a href='debug-500.php' style='background:#ffc107;color:#212529;padding:12px 20px;text-decoration:none;border-radius:5px;margin:5px;display:inline-block;'>🔍 Diagnostic 500</a>";
echo "<a href='logs.php' style='background:#6c757d;color:white;padding:12px 20px;text-decoration:none;border-radius:5px;margin:5px;display:inline-block;'>📋 Logs</a>";
echo "<a href='test-final.php' style='background:#28a745;color:white;padding:12px 20px;text-decoration:none;border-radius:5px;margin:5px;display:inline-block;'>🔗 Test SignNow</a>";
echo "</div>";

// Informations techniques finales
echo "<h2>📋 Informations Techniques</h2>";
echo "<table border='1' style='border-collapse:collapse;width:100%;margin:20px 0;'>";
echo "<tr><th style='padding:10px;background:#f8f9fa;'>Paramètre</th><th style='padding:10px;background:#f8f9fa;'>Valeur</th></tr>";

$tech_info = [
    'Version PHP' => PHP_VERSION,
    'SAPI' => php_sapi_name(),
    'OS' => PHP_OS,
    'Architecture' => (PHP_INT_SIZE * 8) . ' bits',
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution Time' => ini_get('max_execution_time') . 's',
    'Upload Max Size' => ini_get('upload_max_filesize'),
    'Post Max Size' => ini_get('post_max_size'),
    'Extensions DomPDF' => implode(', ', array_filter(['mbstring', 'dom', 'gd', 'xml'], 'extension_loaded')),
    'Dossier Uploads' => is_dir(__DIR__ . '/uploads') ? '✅ Existe' : '❌ Manquant',
    'Permissions Uploads' => is_writable(__DIR__ . '/uploads') ? '✅ Écriture OK' : '❌ Pas d\'écriture',
    'Vendor Autoload' => file_exists(__DIR__ . '/vendor/autoload.php') ? '✅ Présent' : '❌ Manquant',
    'Classe DomPDF' => class_exists('Dompdf\\Dompdf') ? '✅ Disponible' : '❌ Indisponible'
];

foreach ($tech_info as $param => $value) {
    echo "<tr><td style='padding:8px;'>$param</td><td style='padding:8px;'>$value</td></tr>";
}
echo "</table>";

echo "<hr>";
echo "<p style='text-align:center;color:#666;margin-top:30px;'>";
echo "<strong>Test DomPDF terminé le " . date('d/m/Y à H:i:s') . "</strong><br>";
echo "Mémoire finale utilisée: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB<br>";
echo "Pic mémoire: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB";
echo "</p>";

echo "</body></html>";
?>