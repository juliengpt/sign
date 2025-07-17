<?php
// backend.php - Système propre Google Docs + SignNow
require_once 'config.php';
require_once 'vendor/autoload.php';

// Configuration Google Docs
define('GOOGLE_DOCS_ID', '1YSWjJnFW0XHG0FJR4iYZcFJLU_xRYEnsmt20FG9inUk');
define('GOOGLE_SERVICE_ACCOUNT_FILE', __DIR__ . '/credentials/service-account.json');
define('SHARED_FOLDER_ID', '1pN7yqrOkUQkDr9dPOl24docB_eUS_u4X'); // Votre dossier partagé

// Traitement du formulaire
$message = '';
$error = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_contract') {
    try {
        $result = generateContractFromGoogleDocs($_POST);
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
function generateContractFromGoogleDocs($formData) {
    try {
        // 1. Validation des données
        $validation = validateContractData($formData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // 2. Créer une copie du Google Docs, la remplir et obtenir le PDF
        $pdfPath = createFilledDocumentAndExportPDF($formData);
        if (!$pdfPath) {
            return ['success' => false, 'message' => 'Impossible de créer le contrat depuis Google Docs'];
        }
        
        // 3. Upload vers SignNow
        $documentId = uploadPdfToSignNow($pdfPath);
        if (!$documentId) {
            // Nettoyer le fichier PDF local en cas d'erreur
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            return ['success' => false, 'message' => 'Impossible d\'uploader vers SignNow'];
        }
        
        // 4. Envoyer invitation de signature
        $inviteResult = sendSigningInvitation($documentId, $formData);
        
        // 5. Nettoyer le fichier PDF local
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        return $inviteResult;
        
    } catch (Exception $e) {
        logMessage("Erreur generateContractFromGoogleDocs: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur lors de la génération: ' . $e->getMessage()];
    }
}

// Fonction pour créer une copie, la remplir et exporter en PDF
function createFilledDocumentAndExportPDF($formData) {
    $copiedDocId = null;
    
    try {
        // Initialisation du client Google avec tous les scopes nécessaires
        $client = new Google_Client();
        $client->setAuthConfig(GOOGLE_SERVICE_ACCOUNT_FILE);
        $client->addScope([
            Google_Service_Docs::DOCUMENTS,
            Google_Service_Drive::DRIVE
        ]);
        
        // Services Google
        $docsService = new Google_Service_Docs($client);
        $driveService = new Google_Service_Drive($client);
        
        // 1. Créer une copie du document dans le dossier partagé
        $copyMetadata = new Google_Service_Drive_DriveFile();
        $copyMetadata->setName('Contrat_' . $formData['id_boutique'] . '_' . date('Y-m-d_H-i-s'));
       //  $copyMetadata->setParents([SHARED_FOLDER_ID]);
        
        $copiedFile = $driveService->files->copy(GOOGLE_DOCS_ID, $copyMetadata);
        $copiedDocId = $copiedFile->getId();
        
        logMessage("Copie créée dans le dossier partagé: " . $copiedDocId, 'SUCCESS');
        
        // 2. Préparer les remplacements
        $replacements = [
            '{{id_boutique}}' => $formData['id_boutique'],
            '{{nom_acheteur}}' => $formData['nom_acheteur'],
            '{{prenom_acheteur}}' => $formData['prenom_acheteur'],
            '{{adresse_acheteur}}' => $formData['adresse_acheteur'],
            '{{telephone_acheteur}}' => $formData['telephone_acheteur'],
            '{{email_acheteur}}' => $formData['email_acheteur'],
            '{{piece_identite}}' => $formData['piece_identite'],
            '{{date_naissance}}' => formatDate($formData['date_naissance']),
            '{{type_produits}}' => $formData['type_produits'],
            '{{secteur_activite}}' => $formData['secteur_activite'],
            '{{date_lancement}}' => formatDate($formData['date_lancement']),
            '{{ca_mensuel}}' => formatCurrency($formData['ca_mensuel']),
            '{{prix_boutique}}' => formatCurrency($formData['prix_boutique']),
            '{{date_contrat}}' => formatDate($formData['date_contrat'] ?? date('Y-m-d'))
        ];
        
        // 3. Remplacer tous les placeholders dans le document copié
        $requests = [];
        foreach ($replacements as $placeholder => $value) {
            $requests[] = new Google_Service_Docs_Request([
                'replaceAllText' => [
                    'containsText' => [
                        'text' => $placeholder,
                        'matchCase' => true
                    ],
                    'replaceText' => $value
                ]
            ]);
        }
        
        // Exécuter les remplacements
        if (!empty($requests)) {
            $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest([
                'requests' => $requests
            ]);
            
            $docsService->documents->batchUpdate($copiedDocId, $batchUpdateRequest);
            logMessage("Placeholders remplacés: " . count($replacements) . " remplacements", 'SUCCESS');
        }
        
        // 4. Attendre que les modifications soient propagées
        sleep(3);
        
        // 5. Exporter le document rempli en PDF
        $pdfContent = $driveService->files->export($copiedDocId, 'application/pdf', [
            'alt' => 'media'
        ]);
        
        // 6. Sauvegarder le PDF localement
        $filename = 'contrat_' . $formData['id_boutique'] . '_' . time() . '.pdf';
        $filepath = UPLOAD_DIR . $filename;
        
        file_put_contents($filepath, $pdfContent->getBody()->getContents());
        
        // 7. Supprimer la copie temporaire du Google Docs
        try {
            $driveService->files->delete($copiedDocId);
            logMessage("Copie temporaire supprimée: " . $copiedDocId, 'SUCCESS');
        } catch (Exception $e) {
            logMessage("Attention: Impossible de supprimer la copie temporaire: " . $e->getMessage(), 'WARNING');
        }
        
        logMessage("PDF généré avec succès: " . $filename, 'SUCCESS');
        return $filepath;
        
    } catch (Exception $e) {
        // En cas d'erreur, essayer de nettoyer la copie temporaire
        if ($copiedDocId) {
            try {
                $client = new Google_Client();
                $client->setAuthConfig(GOOGLE_SERVICE_ACCOUNT_FILE);
                $client->addScope(Google_Service_Drive::DRIVE);
                $driveService = new Google_Service_Drive($client);
                $driveService->files->delete($copiedDocId);
                logMessage("Copie temporaire nettoyée après erreur: " . $copiedDocId, 'INFO');
            } catch (Exception $cleanupError) {
                logMessage("Impossible de nettoyer la copie temporaire: " . $cleanupError->getMessage(), 'ERROR');
            }
        }
        
        logMessage("Erreur createFilledDocumentAndExportPDF: " . $e->getMessage(), 'ERROR');
        return false;
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

// Fonction pour uploader le PDF vers SignNow
function uploadPdfToSignNow($pdfPath) {
    try {
        $url = 'https://api.signnow.com/document';
        
        if (!file_exists($pdfPath)) {
            throw new Exception("Le fichier PDF n'existe pas: " . $pdfPath);
        }
        
        $cfile = new CURLFile($pdfPath, 'application/pdf', basename($pdfPath));
        $data = ['file' => $cfile];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . SIGNNOW_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("Erreur cURL: " . $curlError);
        }
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            $documentId = $result['id'] ?? null;
            
            if ($documentId) {
                logMessage("PDF uploadé vers SignNow avec succès: " . $documentId, 'SUCCESS');
                return $documentId;
            } else {
                throw new Exception("Pas d'ID de document dans la réponse SignNow");
            }
        } else {
            throw new Exception("Erreur HTTP $httpCode: $response");
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
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($inviteData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . SIGNNOW_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("Erreur cURL: " . $curlError);
        }
        
        if ($httpCode === 200 || $httpCode === 201) {
            logMessage("Invitation envoyée avec succès à " . $formData['email_acheteur'], 'SUCCESS');
            return ['success' => true, 'message' => 'Contrat envoyé avec succès'];
        } else {
            $errorMessage = "Erreur HTTP $httpCode";
            if ($response) {
                $errorMessage .= ": " . $response;
            }
            throw new Exception($errorMessage);
        }
        
    } catch (Exception $e) {
        logMessage("Erreur sendSigningInvitation: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi: ' . $e->getMessage()];
    }
}

// Fonctions utilitaires
function formatDate($dateString) {
    if (!$dateString) return date('d/m/Y');
    try {
        $date = new DateTime($dateString);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        logMessage("Erreur formatage date: $dateString", 'ERROR');
        return date('d/m/Y');
    }
}

function formatCurrency($amount) {
    try {
        return number_format(floatval($amount), 2, ',', ' ') . ' €';
    } catch (Exception $e) {
        logMessage("Erreur formatage montant: $amount", 'ERROR');
        return '0,00 €';
    }
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
        <h1>🏪 Contrat d'Acquisition - Résultat</h1>
        
        <?php if ($message): ?>
            <div class="success">
                <h3>✅ Succès !</h3>
                <p><?= $message ?></p>
                <small>🎯 Contrat généré depuis votre Google Docs avec formatage original préservé et tous les placeholders remplis.</small>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <h3>❌ Erreur</h3>
                <p><?= $error ?></p>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn success">← Nouveau contrat</a>
            <a href="logs.php" class="btn">📋 Voir les logs</a>
            <a href="test-final.php" class="btn">🔍 Diagnostic</a>
        </div>
    </div>
</body>
</html>