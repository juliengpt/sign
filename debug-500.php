<?php
// debug-500.php - Diagnostic complet pour résoudre l'erreur 500
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Diagnostic Erreur 500</title>";
echo "<style>body{font-family:Arial;max-width:1000px;margin:0 auto;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} .info{color:blue;} .section{background:#f9f9f9;padding:15px;margin:10px 0;border-radius:5px;} .code{background:#333;color:#0f0;padding:10px;border-radius:3px;overflow-x:auto;}</style>";
echo "</head><body>";

echo "<h1>🔍 Diagnostic Complet - Erreur 500</h1>";
echo "<p><strong>Objectif :</strong> Identifier et résoudre le problème de génération PDF</p>";

// Fonction utilitaire pour les tests
function testResult($test, $result, $details = '') {
    $icon = $result ? "✅" : "❌";
    $class = $result ? "success" : "error";
    echo "<p class='$class'>$icon $test";
    if ($details) echo " - $details";
    echo "</p>";
    return $result;
}

function warningResult($test, $details = '') {
    echo "<p class='warning'>⚠️ $test";
    if ($details) echo " - $details";
    echo "</p>";
}

function infoResult($test, $details = '') {
    echo "<p class='info'>ℹ️ $test";
    if ($details) echo " - $details";
    echo "</p>";
}

// 1. TEST PHP DE BASE
echo "<div class='section'>";
echo "<h2>1. 🐘 Test PHP de Base</h2>";

testResult("Version PHP", version_compare(PHP_VERSION, '7.4', '>='), "Version: " . PHP_VERSION);
testResult("Fonction ini_set", function_exists('ini_set'), "Configuration PHP modifiable");
testResult("Fonction curl_init", function_exists('curl_init'), "cURL disponible pour SignNow");
testResult("Fonction file_get_contents", function_exists('file_get_contents'), "Lecture fichiers");

// Limites mémoire et temps
$memory_limit = ini_get('memory_limit');
$max_execution_time = ini_get('max_execution_time');
infoResult("Limite mémoire", $memory_limit);
infoResult("Temps d'exécution max", $max_execution_time . "s");

// Test écriture basique
try {
    $test_file = __DIR__ . '/test_write.tmp';
    file_put_contents($test_file, 'test');
    $can_write = file_exists($test_file);
    if ($can_write) unlink($test_file);
    testResult("Écriture fichiers", $can_write, "Permissions OK");
} catch (Exception $e) {
    testResult("Écriture fichiers", false, "Erreur: " . $e->getMessage());
}

echo "</div>";

// 2. VÉRIFICATION FICHIERS REQUIS
echo "<div class='section'>";
echo "<h2>2. 📁 Vérification Fichiers</h2>";

$required_files = [
    'config.php' => __DIR__ . '/config.php',
    'vendor/autoload.php' => __DIR__ . '/vendor/autoload.php',
    'composer.json' => __DIR__ . '/composer.json',
    'index.php' => __DIR__ . '/index.php'
];

foreach ($required_files as $name => $path) {
    testResult("Fichier $name", file_exists($path), $path);
}

// Vérification dossiers
$required_dirs = [
    'uploads' => __DIR__ . '/uploads',
    'logs' => __DIR__ . '/logs',
    'vendor' => __DIR__ . '/vendor'
];

foreach ($required_dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists ? is_writable($path) : false;
    testResult("Dossier $name", $exists, $writable ? "Accessible et modifiable" : "Problème permissions");
}

echo "</div>";

// 3. TEST INCLUSION CONFIG
echo "<div class='section'>";
echo "<h2>3. ⚙️ Test Configuration</h2>";

try {
    if (file_exists(__DIR__ . '/config.php')) {
        include_once __DIR__ . '/config.php';
        testResult("Inclusion config.php", true, "Chargé sans erreur");
        
        // Vérification constantes
        $constants = ['SIGNNOW_API_KEY', 'COMPANY_EMAIL', 'UPLOAD_DIR', 'LOG_DIR'];
        foreach ($constants as $const) {
            testResult("Constante $const", defined($const), defined($const) ? constant($const) : "Non définie");
        }
    } else {
        testResult("Inclusion config.php", false, "Fichier manquant");
    }
} catch (Exception $e) {
    testResult("Inclusion config.php", false, "Erreur: " . $e->getMessage());
}

echo "</div>";

// 4. TEST AUTOLOAD COMPOSER
echo "<div class='section'>";
echo "<h2>4. 📦 Test Composer Autoload</h2>";

try {
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        testResult("Autoload Composer", true, "Chargé sans erreur");
        
        // Test des classes principales
        $classes = [
            'Dompdf\\Dompdf',
            'Dompdf\\Options',
            'Google\\Client'
        ];
        
        foreach ($classes as $class) {
            testResult("Classe $class", class_exists($class), "Disponible via autoload");
        }
        
    } else {
        testResult("Autoload Composer", false, "vendor/autoload.php manquant - Exécuter 'composer install'");
    }
} catch (Exception $e) {
    testResult("Autoload Composer", false, "Erreur: " . $e->getMessage());
}

echo "</div>";

// 5. TEST DOMPDF BASIQUE
echo "<div class='section'>";
echo "<h2>5. 📄 Test DomPDF</h2>";

try {
    if (class_exists('Dompdf\\Dompdf')) {
        // Test création instance
        $dompdf = new Dompdf\Dompdf();
        testResult("Création instance DomPDF", true, "Instance créée sans erreur");
        
        // Test HTML simple
        $simple_html = '<html><body><h1>Test</h1><p>PDF de test</p></body></html>';
        
        try {
            $dompdf->loadHtml($simple_html);
            testResult("Chargement HTML", true, "HTML chargé dans DomPDF");
            
            try {
                $dompdf->setPaper('A4', 'portrait');
                testResult("Configuration papier", true, "Format A4 portrait");
                
                try {
                    // Augmenter la mémoire temporairement
                    ini_set('memory_limit', '512M');
                    $dompdf->render();
                    testResult("Rendu PDF", true, "PDF généré avec succès");
                    
                    try {
                        $pdf_content = $dompdf->output();
                        $pdf_size = strlen($pdf_content);
                        testResult("Sortie PDF", $pdf_size > 0, "Taille: " . number_format($pdf_size) . " bytes");
                        
                        // Test sauvegarde
                        if (defined('UPLOAD_DIR') && is_dir(UPLOAD_DIR)) {
                            $test_pdf = UPLOAD_DIR . 'test_' . time() . '.pdf';
                            file_put_contents($test_pdf, $pdf_content);
                            $saved = file_exists($test_pdf);
                            testResult("Sauvegarde PDF", $saved, $saved ? $test_pdf : "Échec sauvegarde");
                            if ($saved) {
                                // Nettoyer le fichier test
                                unlink($test_pdf);
                            }
                        }
                        
                    } catch (Exception $e) {
                        testResult("Sortie PDF", false, "Erreur output: " . $e->getMessage());
                    }
                    
                } catch (Exception $e) {
                    testResult("Rendu PDF", false, "Erreur render: " . $e->getMessage());
                    warningResult("Suggestion", "Problème mémoire possible - Vérifier memory_limit");
                }
                
            } catch (Exception $e) {
                testResult("Configuration papier", false, "Erreur setPaper: " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            testResult("Chargement HTML", false, "Erreur loadHtml: " . $e->getMessage());
        }
        
    } else {
        testResult("Test DomPDF", false, "Classe Dompdf non disponible - Vérifier composer install");
    }
} catch (Exception $e) {
    testResult("Test DomPDF", false, "Erreur générale: " . $e->getMessage());
}

echo "</div>";

// 6. TEST EXTENSIONS PHP
echo "<div class='section'>";
echo "<h2>6. 🔧 Test Extensions PHP</h2>";

$required_extensions = [
    'mbstring' => 'Gestion chaînes multi-octets',
    'dom' => 'Manipulation DOM XML/HTML',
    'xml' => 'Support XML',
    'gd' => 'Manipulation images',
    'curl' => 'Requêtes HTTP',
    'json' => 'Encodage/décodage JSON',
    'fileinfo' => 'Informations fichiers',
    'zlib' => 'Compression'
];

foreach ($required_extensions as $ext => $desc) {
    testResult("Extension $ext", extension_loaded($ext), $desc);
}

echo "</div>";

// 7. TEST BACKEND SIMPLIFIÉ
echo "<div class='section'>";
echo "<h2>7. 🧪 Test Backend Simplifié</h2>";

echo "<p><strong>Test sans DomPDF :</strong></p>";

try {
    // Simuler les données du formulaire
    $test_data = [
        'id_boutique' => 'BTQ-TEST-' . time(),
        'nom_acheteur' => 'Test',
        'prenom_acheteur' => 'Utilisateur',
        'email_acheteur' => 'test@example.com',
        'adresse_acheteur' => '123 Rue Test',
        'telephone_acheteur' => '0123456789',
        'piece_identite' => 'TEST123',
        'date_naissance' => '1990-01-01',
        'type_produits' => 'Produits test',
        'secteur_activite' => 'Test',
        'date_lancement' => '2024-01-01',
        'ca_mensuel' => '5000',
        'prix_boutique' => '50000',
        'date_contrat' => date('Y-m-d')
    ];
    
    testResult("Données test", true, "Données formulaire simulées");
    
    // Test génération HTML
    $html_content = generateTestContractHTML($test_data);
    testResult("Génération HTML", !empty($html_content), "HTML généré: " . strlen($html_content) . " caractères");
    
    infoResult("Contenu HTML", "Les premières 200 caractères:");
    echo "<div class='code'>" . htmlspecialchars(substr($html_content, 0, 200)) . "...</div>";
    
} catch (Exception $e) {
    testResult("Test backend", false, "Erreur: " . $e->getMessage());
}

echo "</div>";

// 8. RECOMMANDATIONS
echo "<div class='section'>";
echo "<h2>8. 💡 Recommandations</h2>";

echo "<h3>Actions Immédiates :</h3>";
echo "<ol>";

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<li class='error'><strong>URGENT:</strong> Exécuter <code>composer install</code> via SSH</li>";
}

if (!extension_loaded('mbstring')) {
    echo "<li class='error'><strong>URGENT:</strong> Activer l'extension PHP mbstring</li>";
}

if (!extension_loaded('dom')) {
    echo "<li class='error'><strong>URGENT:</strong> Activer l'extension PHP dom</li>";
}

$memory = ini_get('memory_limit');
if (preg_match('/(\d+)/', $memory, $matches) && $matches[1] < 256) {
    echo "<li class='warning'>Augmenter memory_limit à 512M minimum</li>";
}

echo "<li class='info'>Tester d'abord backend-simple.php (sans PDF)</li>";
echo "<li class='info'>Ajouter les logos en base64</li>";
echo "<li class='info'>Tester avec des données minimales</li>";
echo "</ol>";

echo "<h3>Fichiers à Créer/Modifier :</h3>";
echo "<ul>";
echo "<li><strong>backend-simple.php</strong> : Version sans PDF pour tester</li>";
echo "<li><strong>test-minimal.php</strong> : Test DomPDF isolé</li>";
echo "<li><strong>Mise à jour .htaccess</strong> : Augmenter limites PHP</li>";
echo "</ul>";

echo "</div>";

// 9. INFORMATIONS SYSTÈME
echo "<div class='section'>";
echo "<h2>9. 📊 Informations Système</h2>";

echo "<p><strong>Serveur:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu') . "</p>";
echo "<p><strong>PHP SAPI:</strong> " . php_sapi_name() . "</p>";
echo "<p><strong>OS:</strong> " . PHP_OS . "</p>";
echo "<p><strong>Architecture:</strong> " . (PHP_INT_SIZE * 8) . " bits</p>";

echo "<h4>Configuration PHP Critique :</h4>";
$php_config = [
    'memory_limit',
    'max_execution_time',
    'upload_max_filesize',
    'post_max_size',
    'max_input_vars',
    'display_errors',
    'log_errors'
];

echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
echo "<tr><th>Directive</th><th>Valeur</th></tr>";
foreach ($php_config as $directive) {
    echo "<tr><td>$directive</td><td>" . ini_get($directive) . "</td></tr>";
}
echo "</table>";

echo "</div>";

// Fonction pour générer un HTML de test
function generateTestContractHTML($data) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contrat Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin: 20px 0; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 1px solid #ccc; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT D\'ACQUISITION BOUTIQUE E-COMMERCE</h1>
        <p><strong>ID:</strong> ' . htmlspecialchars($data['id_boutique']) . '</p>
        <p><strong>Date:</strong> ' . htmlspecialchars($data['date_contrat']) . '</p>
    </div>
    
    <div class="section">
        <h2>Informations Acheteur</h2>
        <p><strong>Nom:</strong> ' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($data['email_acheteur']) . '</p>
        <p><strong>Adresse:</strong> ' . htmlspecialchars($data['adresse_acheteur']) . '</p>
    </div>
    
    <div class="section">
        <h2>Détails Boutique</h2>
        <p><strong>Secteur:</strong> ' . htmlspecialchars($data['secteur_activite']) . '</p>
        <p><strong>Produits:</strong> ' . htmlspecialchars($data['type_produits']) . '</p>
        <p><strong>Prix:</strong> ' . number_format($data['prix_boutique'], 2) . ' €</p>
    </div>
    
    <div class="section">
        <h2>Signature</h2>
        <p>Document généré automatiquement le ' . date('d/m/Y H:i:s') . '</p>
        <p>[[s|signature|req|signer1]]</p>
    </div>
</body>
</html>';
}

echo "<hr>";
echo "<p><strong>✅ Diagnostic terminé le " . date('d/m/Y H:i:s') . "</strong></p>";
echo "<p><a href='index.php'>← Retour à l'application</a> | <a href='test-final.php'>Test SignNow →</a></p>";

echo "</body></html>";
?>