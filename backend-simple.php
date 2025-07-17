<?php
// backend-simple.php - Version test sans PDF pour diagnostic
require_once 'config.php';

// Activer le debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$error = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_contract') {
    try {
        logMessage("Début génération contrat simple pour " . $_POST['email_acheteur'], 'INFO');
        
        $result = generateContractSimple($_POST);
        
        if ($result['success']) {
            $message = "✅ " . $result['message'];
            logMessage("Contrat simple généré avec succès", 'SUCCESS');
        } else {
            $error = "❌ " . $result['message'];
            logMessage("Erreur génération simple: " . $result['message'], 'ERROR');
        }
    } catch (Exception $e) {
        $error = "❌ Erreur système: " . $e->getMessage();
        logMessage("Exception backend simple: " . $e->getMessage(), 'ERROR');
    }
}

function generateContractSimple($formData) {
    try {
        // 1. Validation des données
        $validation = validateContractData($formData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // 2. Génération HTML (sans conversion PDF)
        $htmlContent = generateSimpleContractHTML($formData);
        if (!$htmlContent) {
            return ['success' => false, 'message' => 'Impossible de générer le HTML'];
        }
        
        // 3. Sauvegarde HTML pour inspection
        $htmlFile = UPLOAD_DIR . 'contrat_' . $formData['id_boutique'] . '_' . time() . '.html';
        file_put_contents($htmlFile, $htmlContent);
        
        // 4. Simulation envoi SignNow (sans PDF réel)
        $simulationResult = simulateSignNowProcess($formData, $htmlFile);
        
        return $simulationResult;
        
    } catch (Exception $e) {
        logMessage("Erreur generateContractSimple: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur lors de la génération: ' . $e->getMessage()];
    }
}

function validateContractData($data) {
    $requiredFields = [
        'id_boutique' => 'ID Boutique',
        'nom_acheteur' => 'Nom Acheteur',
        'prenom_acheteur' => 'Prénom Acheteur',
        'adresse_acheteur' => 'Adresse Acheteur',
        'telephone_acheteur' => 'Téléphone',
        'email_acheteur' => 'Email',
        'piece_identite' => 'Pièce d\'Identité',
        'date_naissance' => 'Date de Naissance',
        'type_produits' => 'Type de Produits',
        'secteur_activite' => 'Secteur d\'Activité',
        'date_lancement' => 'Date de Lancement',
        'ca_mensuel' => 'CA Mensuel',
        'prix_boutique' => 'Prix Boutique'
    ];
    
    foreach ($requiredFields as $field => $label) {
        if (empty($data[$field])) {
            return ['valid' => false, 'message' => "Le champ '$label' est obligatoire"];
        }
    }
    
    // Validation email
    if (!filter_var($data['email_acheteur'], FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Format email invalide'];
    }
    
    // Validation montants
    if (!is_numeric($data['ca_mensuel']) || floatval($data['ca_mensuel']) <= 0) {
        return ['valid' => false, 'message' => 'Le CA mensuel doit être un nombre positif'];
    }
    
    if (!is_numeric($data['prix_boutique']) || floatval($data['prix_boutique']) <= 0) {
        return ['valid' => false, 'message' => 'Le prix de la boutique doit être un nombre positif'];
    }
    
    return ['valid' => true];
}

function generateSimpleContractHTML($data) {
    // Template HTML simplifié pour test
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat d\'Acquisition Boutique E-commerce</title>
    <style>
        body {
            font-family: "Times New Roman", serif;
            line-height: 1.6;
            margin: 40px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2E86AB;
        }
        
        .logo-placeholder {
            width: 200px;
            height: 80px;
            background: #f0f0f0;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #ccc;
            color: #666;
        }
        
        h1 {
            color: #2E86AB;
            font-size: 2em;
            margin: 20px 0;
        }
        
        h2 {
            color: #A23B72;
            border-bottom: 2px solid #A23B72;
            padding-bottom: 5px;
            margin-top: 30px;
        }
        
        .section {
            margin: 25px 0;
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #2E86AB;
        }
        
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 20px 0;
        }
        
        .partie {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: white;
        }
        
        .signature-area {
            margin-top: 50px;
            padding: 30px;
            border: 2px dashed #A23B72;
            background: #fff5f5;
        }
        
        .text-tags {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2196F3;
            margin: 20px 0;
            font-family: monospace;
        }
        
        .financial-highlight {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #4CAF50;
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9em;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        @media print {
            body { margin: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-placeholder">Logo ShopBuyHere</div>
        <h1>CONTRAT D\'ACQUISITION D\'UNE BOUTIQUE E-COMMERCE</h1>
        <p><strong>Référence:</strong> ' . htmlspecialchars($data['id_boutique']) . '</p>
        <p><strong>Date:</strong> ' . formatDate($data['date_contrat'] ?? date('Y-m-d')) . '</p>
    </div>

    <div class="section">
        <h2>📋 1. Objet du Contrat</h2>
        <p>Le présent contrat a pour objet de formaliser les conditions d\'acquisition différée d\'une boutique e-commerce identifiée par l\'identifiant unique <strong>' . htmlspecialchars($data['id_boutique']) . '</strong>, spécialisée dans la vente de ' . htmlspecialchars($data['type_produits']) . ' dans le secteur ' . htmlspecialchars($data['secteur_activite']) . '.</p>
        
        <p>Cette acquisition s\'effectue selon le modèle de participation financière proposé par <strong>ShopBuyHere</strong>, permettant à l\'acquéreur de devenir propriétaire de la boutique moyennant le paiement du prix convenu.</p>
    </div>

    <div class="section">
        <h2>👥 2. Identification des Parties</h2>
        <div class="parties">
            <div class="partie">
                <h3>🏢 LE VENDEUR</h3>
                <p><strong>ShopBuyHere</strong><br>
                Société opérée par MPI MANAGE LTD<br>
                Société de droit anglais<br>
                Siège social: Angleterre<br>
                Email: support@shopbuyhere.co<br>
                Représentée par: William Davies, Directeur Général</p>
            </div>
            
            <div class="partie">
                <h3>👤 L\'ACHETEUR</h3>
                <p><strong>' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</strong><br>
                Né(e) le: ' . formatDate($data['date_naissance']) . '<br>
                Adresse: ' . htmlspecialchars($data['adresse_acheteur']) . '<br>
                Téléphone: ' . htmlspecialchars($data['telephone_acheteur']) . '<br>
                Email: ' . htmlspecialchars($data['email_acheteur']) . '<br>
                Pièce d\'identité: ' . htmlspecialchars($data['piece_identite']) . '</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>🏪 3. Description de la Boutique</h2>
        <p><strong>Secteur d\'activité:</strong> ' . htmlspecialchars($data['secteur_activite']) . '</p>
        <p><strong>Type de produits vendus:</strong> ' . htmlspecialchars($data['type_produits']) . '</p>
        <p><strong>Date de lancement:</strong> ' . formatDate($data['date_lancement']) . '</p>
        <p><strong>Chiffre d\'affaires mensuel moyen:</strong> ' . formatCurrency($data['ca_mensuel']) . '</p>
        
        <p>La boutique est hébergée sur la plateforme ShopBuyHere et bénéficie de tous les services associés (hébergement, maintenance, support technique, marketing digital).</p>
    </div>

    <div class="section">
        <h2>💰 4. Prix d\'Acquisition et Modalités</h2>
        <div class="financial-highlight">
            Prix d\'acquisition total: ' . formatCurrency($data['prix_boutique']) . '
        </div>
        
        <p>Le prix d\'acquisition est fixé à <strong>' . formatCurrency($data['prix_boutique']) . '</strong> (TTC), payable selon les modalités convenues entre les parties.</p>
        
        <p>Ce prix inclut :</p>
        <ul>
            <li>La propriété complète de la boutique e-commerce</li>
            <li>Tous les droits de propriété intellectuelle associés</li>
            <li>L\'accès complet au dashboard de gestion</li>
            <li>Le support technique pour la transition</li>
            <li>La formation à l\'utilisation de la plateforme</li>
        </ul>
    </div>

    <div class="section">
        <h2>🎯 5. Engagements et Garanties</h2>
        <h3>Engagements du Vendeur :</h3>
        <ul>
            <li>Garantir le bon fonctionnement de la boutique</li>
            <li>Fournir l\'accès complet au dashboard</li>
            <li>Assurer le support technique pendant la transition</li>
            <li>Maintenir la confidentialité des données</li>
        </ul>
        
        <h3>Engagements de l\'Acheteur :</h3>
        <ul>
            <li>Respecter les modalités de paiement</li>
            <li>Maintenir l\'activité commerciale de la boutique</li>
            <li>Respecter les conditions d\'utilisation de la plateforme</li>
            <li>Préserver la réputation de la marque</li>
        </ul>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2>📋 6. Accès au Dashboard et Gestion</h2>
        <p>L\'Acheteur bénéficiera d\'un accès complet au dashboard de gestion de sa boutique, incluant :</p>
        <ul>
            <li>Gestion des produits et des stocks</li>
            <li>Suivi des commandes et des livraisons</li>
            <li>Analyse des performances et statistiques</li>
            <li>Outils de marketing et de promotion</li>
            <li>Interface de gestion client</li>
        </ul>
    </div>

    <div class="section">
        <h2>🔒 7. Confidentialité et Protection des Données</h2>
        <p>Les parties s\'engagent à respecter la confidentialité de toutes les informations échangées dans le cadre de ce contrat, notamment :</p>
        <ul>
            <li>Les données clients et prospects</li>
            <li>Les informations commerciales et financières</li>
            <li>Les méthodes et processus opérationnels</li>
            <li>Toute information technique ou stratégique</li>
        </ul>
    </div>

    <div class="section">
        <h2>⚖️ 8. Litiges et Droit Applicable</h2>
        <p>Le présent contrat est régi par le droit anglais. En cas de litige, les parties s\'efforceront de trouver une solution amiable. À défaut, les tribunaux compétents d\'Angleterre seront seuls compétents.</p>
    </div>

    <div class="section">
        <h2>📄 9. Dispositions Finales</h2>
        <p>Ce contrat entre en vigueur à la date de signature par les deux parties. Il constitue l\'accord complet entre les parties et remplace tous accords antérieurs relatifs au même objet.</p>
        
        <p>Toute modification de ce contrat devra faire l\'objet d\'un avenant écrit et signé par les deux parties.</p>
    </div>

    <div class="signature-area">
        <h2>✍️ 10. Signatures</h2>
        
        <div class="text-tags">
            <strong>📌 Instructions SignNow :</strong><br>
            • Paraphes requis sur chaque page : [[i|initial|req|signer1]]<br>
            • Signature finale ci-dessous : [[s|signature|req|signer1]]
        </div>
        
        <div class="parties">
            <div class="partie">
                <h4>Pour le Vendeur :</h4>
                <p><strong>ShopBuyHere</strong><br>
                MPI MANAGE LTD</p>
                <br><br>
                <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 5px;">
                    William Davies<br>
                    Directeur Général
                </div>
                <p><small>Date: ' . formatDate($data['date_contrat'] ?? date('Y-m-d')) . '</small></p>
            </div>
            
            <div class="partie">
                <h4>Pour l\'Acheteur :</h4>
                <p><strong>' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</strong></p>
                <br><br>
                
                <div class="text-tags">
                    [[s|signature|req|signer1]]
                </div>
                
                <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 5px;">
                    Signature de l\'Acheteur
                </div>
                <p><small>Date: _________________</small></p>
            </div>
        </div>
        
        <div style="margin-top: 30px; text-align: center; background: #f0f8ff; padding: 15px; border-radius: 5px;">
            <p><strong>🔔 Important :</strong> Ce contrat doit être signé dans un délai de ' . ($data['delai_signature'] ?? '30') . ' jours à compter de la réception.</p>
        </div>
    </div>

    <div class="footer">
        <p><strong>Contrat généré automatiquement le ' . date('d/m/Y à H:i:s') . '</strong></p>
        <p>Document légalement contraignant - Toute modification non autorisée invalide ce contrat</p>
        <p>Pour toute question : support@shopbuyhere.co</p>
    </div>

    <!-- Paraphes sur chaque page (pour SignNow) -->
    <div style="position: fixed; bottom: 20px; right: 20px; font-size: 10px; color: #ccc;">
        [[i|initial|req|signer1]]
    </div>

</body>
</html>';

    return $html;
}

function simulateSignNowProcess($formData, $htmlFile) {
    try {
        // Simulation étape 1 : Création du "document"
        logMessage("Simulation - Document HTML créé: " . basename($htmlFile), 'INFO');
        
        // Simulation étape 2 : Test API SignNow (vérification connexion)
        if (!defined('SIGNNOW_API_KEY')) {
            return ['success' => false, 'message' => 'API Key SignNow non configurée'];
        }
        
        // Test connexion API simple
        $apiTest = testSignNowConnection();
        if (!$apiTest['success']) {
            return ['success' => false, 'message' => 'Erreur connexion SignNow: Code ' . $apiTest['code']];
        }
        
        logMessage("Simulation - API SignNow accessible", 'INFO');
        
        // Simulation étape 3 : Préparation des données d'invitation
        $message = !empty($formData['message_client']) ? $formData['message_client'] : 
            "Bonjour " . $formData['prenom_acheteur'] . " " . $formData['nom_acheteur'] . ",\n\n" .
            "Veuillez signer le contrat d'acquisition de la boutique " . $formData['id_boutique'] . ".\n\n" .
            "Instructions :\n" .
            "1. Paraphez en bas de chaque page aux emplacements indiqués\n" .
            "2. Signez à la fin du document dans la zone de signature finale\n\n" .
            "Prix d'acquisition : " . formatCurrency($formData['prix_boutique']) . "\n\n" .
            "Délai de signature : " . ($formData['delai_signature'] ?? 30) . " jours.\n\n" .
            "Cordialement,\n" .
            COMPANY_NAME;
        
        logMessage("Simulation - Message préparé pour " . $formData['email_acheteur'], 'INFO');
        
        // Simulation étape 4 : Résultat final
        $result = [
            'success' => true,
            'message' => "✅ SIMULATION RÉUSSIE !\n\n" .
                        "📄 Contrat HTML généré: " . basename($htmlFile) . "\n" .
                        "👤 Destinataire: " . $formData['prenom_acheteur'] . " " . $formData['nom_acheteur'] . " (" . $formData['email_acheteur'] . ")\n" .
                        "💰 Montant: " . formatCurrency($formData['prix_boutique']) . "\n" .
                        "⏰ Délai: " . ($formData['delai_signature'] ?? 30) . " jours\n" .
                        "🔗 API SignNow: Connexion OK\n\n" .
                        "⚠️ PROCHAINE ÉTAPE: Activer la génération PDF avec DomPDF pour l'envoi réel via SignNow.",
            'html_file' => $htmlFile,
            'simulation_data' => [
                'client_email' => $formData['email_acheteur'],
                'client_name' => $formData['prenom_acheteur'] . ' ' . $formData['nom_acheteur'],
                'contract_amount' => formatCurrency($formData['prix_boutique']),
                'signing_deadline' => ($formData['delai_signature'] ?? 30) . ' jours',
                'api_status' => 'Connexion OK'
            ]
        ];
        
        logMessage("Simulation complète réussie pour " . $formData['id_boutique'], 'SUCCESS');
        return $result;
        
    } catch (Exception $e) {
        logMessage("Erreur simulation: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur simulation: ' . $e->getMessage()];
    }
}

// Fonctions utilitaires
function formatDate($dateString) {
    if (!$dateString) return date('d/m/Y');
    $date = new DateTime($dateString);
    return $date->format('d/m/Y');
}

function formatCurrency($amount) {
    return number_format(floatval($amount), 2, ',', ' ') . ' €';
}

// Interface de résultat si pas de POST
if (!$_POST) {
    header('Location: index.php');
    exit;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat Test Simple</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        
        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn.success {
            background: #28a745;
        }
        
        .btn.warning {
            background: #ffc107;
            color: #212529;
        }
        
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .highlight {
            background: #fff3cd;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Résultat Test Simplifié</h1>
        <p><strong>Version sans PDF - Diagnostic et validation</strong></p>
        
        <?php if ($message): ?>
            <div class="success">
                <h3>✅ Test Réussi</h3>
                <?= nl2br(htmlspecialchars($message)) ?>
                
                <?php if (isset($result['html_file']) && file_exists($result['html_file'])): ?>
                    <div class="highlight">
                        <h4>📄 Fichier HTML généré :</h4>
                        <p><strong>Fichier :</strong> <?= basename($result['html_file']) ?></p>
                        <p><strong>Taille :</strong> <?= number_format(filesize($result['html_file'])) ?> octets</p>
                        <p><strong>Chemin :</strong> <?= $result['html_file'] ?></p>
                        <a href="<?= basename($result['html_file']) ?>" target="_blank" class="btn success">📖 Voir le HTML</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <h3>❌ Erreur Détectée</h3>
                <?= nl2br(htmlspecialchars($error)) ?>
                
                <div class="highlight">
                    <h4>🔧 Actions Recommandées :</h4>
                    <ul>
                        <li>Vérifier les logs : <a href="logs.php">logs.php</a></li>
                        <li>Faire le diagnostic complet : <a href="debug-500.php">debug-500.php</a></li>
                        <li>Tester la connexion SignNow : <a href="test-final.php">test-final.php</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="details">
            <h4>📊 Informations de Debug :</h4>
            <p><strong>Heure :</strong> <?= date('d/m/Y H:i:s') ?></p>
            <p><strong>PHP Version :</strong> <?= PHP_VERSION ?></p>
            <p><strong>Mémoire utilisée :</strong> <?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB</p>
            <p><strong>Pic mémoire :</strong> <?= round(memory_get_peak_usage() / 1024 / 1024, 2) ?> MB</p>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn">🏠 Retour Formulaire</a>
            <a href="debug-500.php" class="btn warning">🔍 Diagnostic Complet</a>
            <a href="logs.php" class="btn">📋 Voir Logs</a>
            
            <?php if ($message): ?>
                <a href="backend.php" class="btn success">🚀 Tester Version PDF</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>