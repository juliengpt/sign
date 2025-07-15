<?php
// backend.php - Système complet Google Docs + SignNow
require_once 'config.php';

// Configuration Google Docs
define('GOOGLE_DOCS_ID', 'VOTRE_GOOGLE_DOCS_ID_ICI'); // À remplacer par votre ID Google Docs
define('GOOGLE_SERVICE_ACCOUNT_FILE', 'service-account.json'); // Fichier de credentials Google

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
        
        // 2. Créer une copie du Google Docs et remplacer les placeholders
        $documentContent = fillGoogleDocsTemplate($formData);
        if (!$documentContent) {
            return ['success' => false, 'message' => 'Impossible de traiter le modèle Google Docs'];
        }
        
        // 3. Convertir en PDF
        $pdfPath = convertToPdf($documentContent, $formData);
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
        logMessage("Erreur generateContractFromGoogleDocs: " . $e->getMessage(), 'ERROR');
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

// Fonction pour remplir le modèle Google Docs (Version simplifiée)
function fillGoogleDocsTemplate($data) {
    try {
        // Version simplifiée : on va simuler le contenu du Google Docs
        // En production, il faudrait utiliser l'API Google Docs
        
        $template = getContractTemplate();
        
        // Remplacer tous les placeholders
        $replacements = [
            '{{id_boutique}}' => $data['id_boutique'],
            '{{nom_acheteur}}' => $data['nom_acheteur'],
            '{{prenom_acheteur}}' => $data['prenom_acheteur'],
            '{{adresse_acheteur}}' => $data['adresse_acheteur'],
            '{{telephone_acheteur}}' => $data['telephone_acheteur'],
            '{{email_acheteur}}' => $data['email_acheteur'],
            '{{piece_identite}}' => $data['piece_identite'],
            '{{date_naissance}}' => formatDate($data['date_naissance']),
            '{{type_produits}}' => $data['type_produits'],
            '{{secteur_activite}}' => $data['secteur_activite'],
            '{{date_lancement}}' => formatDate($data['date_lancement']),
            '{{ca_mensuel}}' => formatCurrency($data['ca_mensuel']),
            '{{prix_boutique}}' => formatCurrency($data['prix_boutique']),
            '{{date_contrat}}' => formatDate($data['date_contrat'] ?? date('Y-m-d'))
        ];
        
        $filledContent = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        return $filledContent;
        
    } catch (Exception $e) {
        logMessage("Erreur fillGoogleDocsTemplate: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Template de contrat (à adapter selon votre Google Docs)
function getContractTemplate() {
    return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contrat d\'Acquisition Boutique</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin: 20px 0; }
        .signature-area { margin-top: 50px; }
        .text-tag { color: #ccc; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT D\'ACQUISITION DE BOUTIQUE E-COMMERCE</h1>
        <p><strong>ID Boutique: {{id_boutique}}</strong></p>
        <p>Date du contrat: {{date_contrat}}</p>
    </div>

    <div class="section">
        <h2>PARTIES AU CONTRAT</h2>
        
        <p><strong>VENDEUR :</strong><br>
        MPI MANAGE LTD<br>
        Société de droit anglais<br>
        Email: support@shopbuyhere.co</p>
        
        <p><strong>ACHETEUR :</strong><br>
        <strong>{{prenom_acheteur}} {{nom_acheteur}}</strong><br>
        Né(e) le : {{date_naissance}}<br>
        Adresse : {{adresse_acheteur}}<br>
        Téléphone : {{telephone_acheteur}}<br>
        Email : {{email_acheteur}}<br>
        Pièce d\'identité : {{piece_identite}}</p>
    </div>

    <div class="section">
        <h2>OBJET DU CONTRAT</h2>
        <p>Le présent contrat a pour objet l\'acquisition de la boutique e-commerce identifiée par l\'ID <strong>{{id_boutique}}</strong>, spécialisée dans la vente de {{type_produits}} dans le secteur {{secteur_activite}}.</p>
        
        <p><strong>Informations de la boutique :</strong></p>
        <ul>
            <li>Date de lancement : {{date_lancement}}</li>
            <li>Secteur d\'activité : {{secteur_activite}}</li>
            <li>Type de produits : {{type_produits}}</li>
            <li>Chiffre d\'affaires mensuel moyen : {{ca_mensuel}}</li>
        </ul>
    </div>

    <div class="section">
        <h2>CONDITIONS FINANCIÈRES</h2>
        <p><strong>Prix d\'acquisition total : {{prix_boutique}}</strong></p>
        <p>Le paiement sera effectué selon les modalités convenues entre les parties.</p>
    </div>

    <div class="section">
        <h2>OBLIGATIONS DES PARTIES</h2>
        <p>Le vendeur s\'engage à transférer la propriété complète de la boutique e-commerce à l\'acheteur.</p>
        <p>L\'acheteur s\'engage à respecter les conditions de paiement et à maintenir l\'activité commerciale.</p>
    </div>

    <div class="signature-area">
        <p>Paraphe de l\'acheteur (à apposer en bas de chaque page) :</p>
        <p class="text-tag">[[i|initial|req|signer1]]</p>
    </div>

    <div style="page-break-before: always;">
        <h2>CONDITIONS GÉNÉRALES</h2>
        <p>Ce contrat est régi par le droit anglais. Toute modification doit faire l\'objet d\'un avenant écrit.</p>
        
        <div class="signature-area">
            <p>Paraphe de l\'acheteur :</p>
            <p class="text-tag">[[i|initial|req|signer1]]</p>
        </div>
    </div>

    <div style="page-break-before: always;">
        <h2>SIGNATURES</h2>
        <p>Les parties reconnaissent avoir lu et accepté toutes les clauses du présent contrat.</p>
        
        <div style="margin-top: 100px;">
            <p><strong>Signature de l\'acheteur :</strong></p>
            <p class="text-tag">[[s|signature|req|signer1]]</p>
            <p>{{prenom_acheteur}} {{nom_acheteur}}</p>
            <p>Date : {{date_contrat}}</p>
        </div>
        
        <div style="margin-top: 50px;">
            <p><strong>Signature du vendeur :</strong></p>
            <p>MPI MANAGE LTD</p>
            <p>Date : {{date_contrat}}</p>
        </div>
    </div>
</body>
</html>';
}

// Fonction pour convertir en PDF
function convertToPdf($htmlContent, $data) {
    try {
        // Utilisation de DomPDF (il faut l'installer via Composer)
        // composer require dompdf/dompdf
        
        require_once 'vendor/autoload.php';
        use Dompdf\Dompdf;
        use Dompdf\Options;
        
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Sauvegarder le PDF temporairement
        $filename = 'contrat_' . $data['id_boutique'] . '_' . time() . '.pdf';
        $filepath = UPLOAD_DIR . $filename;
        
        file_put_contents($filepath, $dompdf->output());
        
        return $filepath;
        
    } catch (Exception $e) {
        logMessage("Erreur convertToPdf: " . $e->getMessage(), 'ERROR');
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
    // Rediriger vers l'interface ou inclure le formulaire
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
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🏪 Résultat Génération Contrat</h1>
    
    <?php if ($message): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <a href="index.php" class="btn">← Retour au formulaire</a>
    <a href="logs.php" class="btn">📋 Voir les logs</a>
</body>
</html>