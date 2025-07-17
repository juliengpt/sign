<?php
// backend.php - Système complet Google Docs + SignNow avec DomPDF
require_once 'config.php';
require_once 'vendor/autoload.php'; // Autoloader Composer

use Dompdf\Dompdf;
use Dompdf\Options;

// Traitement du formulaire
$message = '';
$error = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_contract') {
    try {
        $result = generateContractFromTemplate($_POST);
        if ($result['success']) {
            $message = "✅ Contrat généré et envoyé avec succès à " . htmlspecialchars($_POST['email_acheteur']);
            logMessage("Contrat généré pour " . $_POST['prenom_acheteur'] . " " . $_POST['nom_acheteur'], 'SUCCESS');
        } else {
            $error = "❌ Erreur: " . $result['message'];
            logMessage("Erreur génération contrat: " . $result['message'], 'ERROR');
        }
    } catch (Exception $e) {
        $error = "❌ Erreur système: " . $e->getMessage();
        logMessage("Erreur système: " . $e->getMessage(), 'ERROR');
    }
}

// Fonction principale pour générer le contrat
function generateContractFromTemplate($formData) {
    try {
        // 1. Validation des données
        $validation = validateContractData($formData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // 2. Créer le contenu HTML avec les données du formulaire
        $htmlContent = fillContractTemplate($formData);
        if (!$htmlContent) {
            return ['success' => false, 'message' => 'Impossible de traiter le modèle de contrat'];
        }
        
        // 3. Convertir en PDF avec DomPDF
        $pdfPath = convertToPdfWithDomPDF($htmlContent, $formData);
        if (!$pdfPath) {
            return ['success' => false, 'message' => 'Impossible de générer le PDF'];
        }
        
        // 4. Upload vers SignNow
        $documentId = uploadPdfToSignNow($pdfPath);
        if (!$documentId) {
            return ['success' => false, 'message' => 'Impossible d\'uploader vers SignNow'];
        }
        
        // 5. Envoyer invitation de signature
        $inviteResult = sendSigningInvitation($documentId, $formData);
        
        // 6. Nettoyer les fichiers temporaires
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        return $inviteResult;
        
    } catch (Exception $e) {
        logMessage("Erreur generateContractFromTemplate: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur lors de la génération: ' . $e->getMessage()];
    }
}

// Fonction pour valider les données du formulaire
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

// Fonction pour remplir le modèle de contrat
function fillContractTemplate($data) {
    try {
        $template = getContractTemplate();
        
        // Remplacer tous les placeholders
        $replacements = [
            '{{id_boutique}}' => htmlspecialchars($data['id_boutique']),
            '{{nom_acheteur}}' => htmlspecialchars($data['nom_acheteur']),
            '{{prenom_acheteur}}' => htmlspecialchars($data['prenom_acheteur']),
            '{{adresse_acheteur}}' => htmlspecialchars($data['adresse_acheteur']),
            '{{telephone_acheteur}}' => htmlspecialchars($data['telephone_acheteur']),
            '{{email_acheteur}}' => htmlspecialchars($data['email_acheteur']),
            '{{piece_identite}}' => htmlspecialchars($data['piece_identite']),
            '{{date_naissance}}' => formatDate($data['date_naissance']),
            '{{type_produits}}' => htmlspecialchars($data['type_produits']),
            '{{secteur_activite}}' => htmlspecialchars($data['secteur_activite']),
            '{{date_lancement}}' => formatDate($data['date_lancement']),
            '{{ca_mensuel}}' => formatCurrency($data['ca_mensuel']),
            '{{prix_boutique}}' => formatCurrency($data['prix_boutique']),
            '{{date_contrat}}' => formatDate($data['date_contrat'] ?? date('Y-m-d'))
        ];
        
        $filledContent = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        return $filledContent;
        
    } catch (Exception $e) {
        logMessage("Erreur fillContractTemplate: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Template de contrat avec Text Tags SignNow
function getContractTemplate() {
    return '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat d\'Acquisition Boutique E-Commerce</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            margin: 40px;
            color: #333;
        }
        .header { 
            text-align: center; 
            margin-bottom: 40px;
            border-bottom: 2px solid #2E86AB;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2E86AB;
            margin-bottom: 10px;
        }
        .section { 
            margin: 30px 0; 
            page-break-inside: avoid;
        }
        .section h2 {
            color: #A23B72;
            border-left: 4px solid #A23B72;
            padding-left: 10px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .signature-area { 
            margin-top: 50px;
            border: 1px dashed #ccc;
            padding: 20px;
            background: #fafafa;
        }
        .text-tag { 
            color: #007bff; 
            font-size: 12px;
            font-weight: bold;
        }
        .paraphe-zone {
            margin: 30px 0;
            text-align: right;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .financial-highlight {
            background: #e8f5e8;
            border: 2px solid #28a745;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT D\'ACQUISITION DE BOUTIQUE E-COMMERCE</h1>
        <p><strong>ID Boutique: {{id_boutique}}</strong></p>
        <p>Date du contrat: {{date_contrat}}</p>
    </div>

    <div class="section">
        <h2>ARTICLE 1 - PARTIES AU CONTRAT</h2>
        
        <div class="info-box">
            <p><strong>VENDEUR :</strong><br>
            MPI MANAGE LTD<br>
            Société de droit anglais<br>
            Email: support@shopbuyhere.co</p>
        </div>
        
        <div class="info-box">
            <p><strong>ACHETEUR :</strong><br>
            <strong>{{prenom_acheteur}} {{nom_acheteur}}</strong><br>
            Né(e) le : {{date_naissance}}<br>
            Adresse : {{adresse_acheteur}}<br>
            Téléphone : {{telephone_acheteur}}<br>
            Email : {{email_acheteur}}<br>
            Pièce d\'identité : {{piece_identite}}</p>
        </div>
    </div>

    <div class="paraphe-zone">
        <p><strong>Paraphe de l\'acheteur :</strong> <span class="text-tag">[[i|initial|req|signer1]]</span></p>
    </div>

    <div style="page-break-before: always;">
        <div class="section">
            <h2>ARTICLE 2 - OBJET DU CONTRAT</h2>
            <p>Le présent contrat a pour objet l\'acquisition de la boutique e-commerce identifiée par l\'ID <strong>{{id_boutique}}</strong>, spécialisée dans la vente de {{type_produits}} dans le secteur {{secteur_activite}}.</p>
            
            <div class="info-box">
                <p><strong>Informations de la boutique :</strong></p>
                <ul>
                    <li>Date de lancement : {{date_lancement}}</li>
                    <li>Secteur d\'activité : {{secteur_activite}}</li>
                    <li>Type de produits : {{type_produits}}</li>
                    <li>Chiffre d\'affaires mensuel moyen : {{ca_mensuel}}</li>
                </ul>
            </div>
        </div>

        <div class="section">
            <h2>ARTICLE 3 - CONDITIONS FINANCIÈRES</h2>
            <div class="financial-highlight">
                Prix d\'acquisition total : {{prix_boutique}}
            </div>
            <p>Le paiement sera effectué selon les modalités convenues entre les parties. Ce prix comprend l\'ensemble des éléments constitutifs de la boutique e-commerce.</p>
        </div>

        <div class="paraphe-zone">
            <p><strong>Paraphe de l\'acheteur :</strong> <span class="text-tag">[[i|initial|req|signer1]]</span></p>
        </div>
    </div>

    <div style="page-break-before: always;">
        <div class="section">
            <h2>ARTICLE 4 - OBLIGATIONS DES PARTIES</h2>
            
            <h3>4.1 Obligations du vendeur</h3>
            <p>Le vendeur s\'engage à :</p>
            <ul>
                <li>Transférer la propriété complète de la boutique e-commerce à l\'acheteur</li>
                <li>Fournir tous les accès et documentations nécessaires</li>
                <li>Assurer une transition efficace</li>
            </ul>

            <h3>4.2 Obligations de l\'acheteur</h3>
            <p>L\'acheteur s\'engage à :</p>
            <ul>
                <li>Respecter les conditions de paiement</li>
                <li>Maintenir l\'activité commerciale dans le respect des bonnes pratiques</li>
                <li>Respecter les engagements contractuels</li>
            </ul>
        </div>

        <div class="section">
            <h2>ARTICLE 5 - DISPOSITIONS GÉNÉRALES</h2>
            <p>Ce contrat est régi par le droit anglais. Toute modification doit faire l\'objet d\'un avenant écrit signé par les deux parties.</p>
            <p>En cas de litige, les parties s\'engagent à rechercher une solution amiable avant tout recours judiciaire.</p>
        </div>

        <div class="paraphe-zone">
            <p><strong>Paraphe de l\'acheteur :</strong> <span class="text-tag">[[i|initial|req|signer1]]</span></p>
        </div>
    </div>

    <div style="page-break-before: always;">
        <div class="section">
            <h2>ARTICLE 6 - SIGNATURES</h2>
            <p>Les parties reconnaissent avoir lu et accepté toutes les clauses du présent contrat.</p>
            
            <div style="margin-top: 60px; display: flex; justify-content: space-between;">
                <div style="width: 45%;">
                    <p><strong>Signature de l\'acheteur :</strong></p>
                    <div class="signature-area">
                        <p class="text-tag">[[s|signature|req|signer1]]</p>
                        <p>{{prenom_acheteur}} {{nom_acheteur}}</p>
                        <p>Date : {{date_contrat}}</p>
                    </div>
                </div>
                
                <div style="width: 45%;">
                    <p><strong>Signature du vendeur :</strong></p>
                    <div class="signature-area">
                        <p>MPI MANAGE LTD</p>
                        <p>Date : {{date_contrat}}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
}

// Fonction pour convertir en PDF avec DomPDF
function convertToPdfWithDomPDF($htmlContent, $data) {
    try {
        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', realpath(''));
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Sauvegarder le PDF temporairement
        $filename = 'contrat_' . $data['id_boutique'] . '_' . time() . '.pdf';
        $filepath = UPLOAD_DIR . $filename;
        
        file_put_contents($filepath, $dompdf->output());
        
        logMessage("PDF généré avec succès: $filename", 'SUCCESS');
        return $filepath;
        
    } catch (Exception $e) {
        logMessage("Erreur convertToPdfWithDomPDF: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Fonction pour uploader le PDF vers SignNow
function uploadPdfToSignNow($pdfPath) {
    try {
        $url = 'https://api.signnow.com/document';
        
        $cfile = new CURLFile($pdfPath, 'application/pdf', basename($pdfPath));
        $data = ['file' => $cfile];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . SIGNNOW_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            logMessage("PDF uploadé vers SignNow avec succès: " . ($result['id'] ?? 'ID non trouvé'), 'SUCCESS');
            return $result['id'] ?? null;
        } else {
            logMessage("Erreur upload SignNow HTTP $httpCode: $response", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Erreur uploadPdfToSignNow: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Fonction pour envoyer l'invitation de signature
function sendSigningInvitation($documentId, $formData) {
    try {
        $url = "https://api.signnow.com/document/$documentId/invite";
        
        $message = !empty($formData['message_client']) ? $formData['message_client'] : 
            "Bonjour " . $formData['prenom_acheteur'] . " " . $formData['nom_acheteur'] . ",\n\n" .
            "Veuillez signer le contrat d'acquisition de la boutique " . $formData['id_boutique'] . ".\n\n" .
            "Instructions :\n" .
            "1. Paraphez en bas de chaque page aux emplacements indiqués\n" .
            "2. Signez à la fin du document dans la zone de signature\n\n" .
            "Prix d'acquisition : " . formatCurrency($formData['prix_boutique']) . "\n\n" .
            "Délai de signature : " . ($formData['delai_signature'] ?? 30) . " jours.\n\n" .
            "Cordialement,\n" .
            COMPANY_NAME;
        
        $inviteData = [
            'to' => [[
                'email' => $formData['email_acheteur'],
                'role_name' => 'Acheteur',
                'role' => 'signer',
                'order' => 1,
                'reassign' => false,
                'decline_by_signature' => false,
                'reminder' => 1,
                'expiration_days' => intval($formData['delai_signature'] ?? 30)
            ]],
            'from' => COMPANY_EMAIL,
            'subject' => "Signature requise - Contrat d'acquisition boutique " . $formData['id_boutique'],
            'message' => $message
        ];
        
        // Ajouter copie si spécifiée
        if (!empty($formData['copie_email']) && filter_var($formData['copie_email'], FILTER_VALIDATE_EMAIL)) {
            $inviteData['cc'] = [['email' => $formData['copie_email']]];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($inviteData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . SIGNNOW_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            logMessage("Invitation envoyée avec succès à " . $formData['email_acheteur'], 'SUCCESS');
            return ['success' => true, 'message' => 'Contrat envoyé avec succès'];
        } else {
            logMessage("Erreur envoi invitation HTTP $httpCode: $response", 'ERROR');
            return ['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'invitation'];
        }
        
    } catch (Exception $e) {
        logMessage("Erreur sendSigningInvitation: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi: ' . $e->getMessage()];
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

// Interface HTML si pas de POST
if (!$_POST) {
    header('Location: index.php');
    exit;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat Génération Contrat</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            background: #007bff; 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 5px; 
            display: inline-block; 
            margin: 10px 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn.success {
            background: #28a745;
        }
        .btn.success:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏪 Résultat Génération Contrat</h1>
        
        <?php if ($message): ?>
            <div class="success">
                <h3>✅ Succès !</h3>
                <p><?= $message ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <h3>❌ Erreur</h3>
                <p><?= $error ?></p>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn success">← Retour au formulaire</a>
            <a href="logs.php" class="btn">📋 Voir les logs</a>
            <a href="test-final.php" class="btn">🔍 Diagnostic système</a>
        </div>
    </div>
</body>
</html>