<?php
// backend-oauth.php - Système complet OAuth Google Docs + SignNow
require_once 'config.php';
require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Docs;
use Google\Service\Drive;
use Dompdf\Dompdf;
use Dompdf\Options;

// Configuration OAuth Google
define('GOOGLE_DOCS_ID', '1YSWjJnFW0XHG0FJR4iYZcFJLU_xRYEnsmt20FG9inUk');
define('OAUTH_CREDENTIALS_PATH', __DIR__ . '/credentials/oauth-credentials.json');
define('TOKENS_DIR', __DIR__ . '/tokens/');

// Créer le dossier tokens s'il n'existe pas
if (!file_exists(TOKENS_DIR)) {
    mkdir(TOKENS_DIR, 0755, true);
}

// Variables globales
$message = '';
$error = '';

// Traitement du formulaire
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_contract') {
    try {
        $result = generateContractWithOAuth($_POST);
        if ($result['success']) {
            $message = "✅ Contrat généré et envoyé avec succès à " . htmlspecialchars($_POST['email_acheteur']);
            logMessage("Contrat OAuth généré pour " . $_POST['prenom_acheteur'] . " " . $_POST['nom_acheteur'], 'SUCCESS');
        } else {
            $error = "❌ Erreur: " . $result['message'];
            logMessage("Erreur génération OAuth: " . $result['message'], 'ERROR');
        }
    } catch (Exception $e) {
        $error = "❌ Erreur système: " . $e->getMessage();
        logMessage("Erreur système OAuth: " . $e->getMessage(), 'ERROR');
    }
}

// Fonction principale pour générer le contrat avec OAuth
function generateContractWithOAuth($formData) {
    try {
        // 1. Validation des données
        $validation = validateContractData($formData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // 2. Obtenir le client Google OAuth
        $client = getGoogleOAuthClient();
        if (!$client) {
            return ['success' => false, 'message' => 'Échec de l\'authentification Google OAuth'];
        }
        
        // 3. Créer une copie du document Google Docs
        $docCopyId = createDocumentCopy($client, $formData);
        if (!$docCopyId) {
            return ['success' => false, 'message' => 'Impossible de créer une copie du document Google'];
        }
        
        // 4. Remplacer les placeholders
        $replaceResult = replacePlaceholders($client, $docCopyId, $formData);
        if (!$replaceResult) {
            return ['success' => false, 'message' => 'Impossible de remplacer les placeholders'];
        }
        
        // 5. Exporter en PDF
        $pdfPath = exportDocumentToPdf($client, $docCopyId, $formData);
        if (!$pdfPath) {
            return ['success' => false, 'message' => 'Impossible d\'exporter en PDF'];
        }
        
        // 6. Upload vers SignNow
        $documentId = uploadPdfToSignNow($pdfPath);
        if (!$documentId) {
            // Nettoyer les fichiers temporaires
            cleanupTempFiles($pdfPath, $client, $docCopyId);
            return ['success' => false, 'message' => 'Impossible d\'uploader vers SignNow'];
        }
        
        // 7. Envoyer invitation de signature
        $inviteResult = sendSigningInvitation($documentId, $formData);
        
        // 8. Nettoyer les fichiers temporaires
        cleanupTempFiles($pdfPath, $client, $docCopyId);
        
        return $inviteResult;
        
    } catch (Exception $e) {
        logMessage("Erreur generateContractWithOAuth: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur lors de la génération: ' . $e->getMessage()];
    }
}

// Fonction pour obtenir le client Google OAuth
function getGoogleOAuthClient() {
    try {
        if (!file_exists(OAUTH_CREDENTIALS_PATH)) {
            logMessage("Fichier credentials OAuth non trouvé: " . OAUTH_CREDENTIALS_PATH, 'ERROR');
            return null;
        }
        
        $client = new Client();
        $client->setAuthConfig(OAUTH_CREDENTIALS_PATH);
        $client->addScope([
            Docs::DOCUMENTS,
            Drive::DRIVE_FILE,
            Drive::DRIVE
        ]);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setRedirectUri('https://gsleads55.com/sign/oauth_callback.php');
        
        // Vérifier si on a un token stocké
        $tokenPath = TOKENS_DIR . 'google_oauth_token.json';
        
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
            
            // Vérifier si le token a expiré
            if ($client->isAccessTokenExpired()) {
                // Essayer de renouveler le token
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($client->getAccessToken()));
                    logMessage("Token OAuth renouvelé automatiquement", 'SUCCESS');
                } else {
                    // Rediriger vers l'autorisation
                    logMessage("Token expiré, redirection vers OAuth", 'INFO');
                    redirectToOAuth($client);
                    return null;
                }
            }
            
            return $client;
        } else {
            // Pas de token, rediriger vers l'autorisation
            logMessage("Aucun token OAuth, redirection vers autorisation", 'INFO');
            redirectToOAuth($client);
            return null;
        }
        
    } catch (Exception $e) {
        logMessage("Erreur OAuth client: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

// Fonction pour rediriger vers OAuth
function redirectToOAuth($client) {
    $authUrl = $client->createAuthUrl();
    
    // Stocker l'URL d'autorisation en session ou afficher un message
    $_SESSION['oauth_required'] = true;
    $_SESSION['oauth_url'] = $authUrl;
    
    // Pour cette implémentation, on va afficher un message d'erreur avec le lien
    global $error;
    $error = "🔐 Autorisation Google requise. <a href='$authUrl' target='_blank' style='color: white; text-decoration: underline;'>Cliquez ici pour autoriser l'accès</a>";
}

// Fonction pour créer une copie du document
function createDocumentCopy($client, $formData) {
    try {
        $driveService = new Drive($client);
        
        $copyName = "Contrat_" . $formData['id_boutique'] . "_" . date('Y-m-d_H-i-s');
        
        $copiedFile = new Drive\DriveFile([
            'name' => $copyName
        ]);
        
        $copy = $driveService->files->copy(GOOGLE_DOCS_ID, $copiedFile);
        
        logMessage("Document copié avec succès: " . $copy->getId(), 'SUCCESS');
        return $copy->getId();
        
    } catch (Exception $e) {
        logMessage("Erreur création copie: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

// Fonction pour remplacer les placeholders
function replacePlaceholders($client, $docId, $formData) {
    try {
        $docsService = new Docs($client);
        
        // Préparer les remplacements
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
        
        // Créer les requêtes de remplacement
        $requests = [];
        foreach ($replacements as $placeholder => $value) {
            $requests[] = new Docs\Request([
                'replaceAllText' => [
                    'containsText' => [
                        'text' => $placeholder,
                        'matchCase' => false
                    ],
                    'replaceText' => $value
                ]
            ]);
        }
        
        // Exécuter les remplacements par batch
        $batchRequest = new Docs\BatchUpdateDocumentRequest([
            'requests' => $requests
        ]);
        
        $response = $docsService->documents->batchUpdate($docId, $batchRequest);
        
        logMessage("Placeholders remplacés avec succès dans le document " . $docId, 'SUCCESS');
        return true;
        
    } catch (Exception $e) {
        logMessage("Erreur remplacement placeholders: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Fonction pour exporter en PDF
function exportDocumentToPdf($client, $docId, $formData) {
    try {
        $driveService = new Drive($client);
        
        // Exporter le document en PDF
        $response = $driveService->files->export($docId, 'application/pdf', [
            'alt' => 'media'
        ]);
        
        $pdfContent = $response->getBody()->getContents();
        
        // Sauvegarder le PDF temporairement
        $filename = 'contrat_' . $formData['id_boutique'] . '_' . time() . '.pdf';
        $filepath = UPLOAD_DIR . $filename;
        
        file_put_contents($filepath, $pdfContent);
        
        logMessage("PDF exporté avec succès: " . $filename, 'SUCCESS');
        return $filepath;
        
    } catch (Exception $e) {
        logMessage("Erreur export PDF: " . $e->getMessage(), 'ERROR');
        return null;
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

// Fonction pour nettoyer les fichiers temporaires
function cleanupTempFiles($pdfPath, $client = null, $docId = null) {
    try {
        // Supprimer le PDF temporaire
        if ($pdfPath && file_exists($pdfPath)) {
            unlink($pdfPath);
            logMessage("PDF temporaire supprimé: " . basename($pdfPath), 'INFO');
        }
        
        // Supprimer la copie du document Google Docs
        if ($client && $docId) {
            $driveService = new Drive($client);
            $driveService->files->delete($docId);
            logMessage("Document Google Docs temporaire supprimé: " . $docId, 'INFO');
        }
        
    } catch (Exception $e) {
        logMessage("Erreur nettoyage fichiers temporaires: " . $e->getMessage(), 'WARNING');
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

// Fonctions utilitaires
function formatDate($dateString) {
    if (!$dateString) return date('d/m/Y');
    $date = new DateTime($dateString);
    return $date->format('d/m/Y');
}

function formatCurrency($amount) {
    return number_format(floatval($amount), 2, ',', ' ') . ' €';
}

// Démarrer la session pour OAuth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    <title>Résultat Génération Contrat OAuth</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
        .oauth-notice { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🏪 Résultat Génération Contrat OAuth</h1>
    
    <?php if ($message): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['oauth_required']) && $_SESSION['oauth_required']): ?>
        <div class="oauth-notice">
            <h3>🔐 Autorisation Google Requise</h3>
            <p>Pour utiliser le système, vous devez autoriser l'accès à Google Docs et Drive.</p>
            <p><a href="<?= $_SESSION['oauth_url'] ?>" class="btn">🚀 Autoriser l'Accès Google</a></p>
        </div>
    <?php endif; ?>
    
    <a href="index.php" class="btn">← Retour au formulaire</a>
    <a href="logs.php" class="btn">📋 Voir les logs</a>
    <a href="test-oauth.php" class="btn">🔍 Test OAuth</a>
</body>
</html>