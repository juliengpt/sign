<?php
// backend-working.php - Version progressive qui fonctionne
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$message = '';
$error = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_contract') {
    try {
        $result = generateContractStep($_POST);
        if ($result['success']) {
            $message = "✅ " . $result['message'];
            logMessage("Contrat généré avec succès", 'SUCCESS');
        } else {
            $error = "❌ " . $result['message'];
            logMessage("Erreur: " . $result['message'], 'ERROR');
        }
    } catch (Exception $e) {
        $error = "❌ Erreur système: " . $e->getMessage();
        logMessage("Erreur système: " . $e->getMessage(), 'ERROR');
    }
}

function generateContractStep($data) {
    try {
        // 1. Validation
        logMessage("Étape 1: Validation des données", 'INFO');
        if (empty($data['nom_acheteur']) || empty($data['email_acheteur'])) {
            return ['success' => false, 'message' => 'Données manquantes'];
        }
        
        // 2. Génération HTML simple
        logMessage("Étape 2: Génération HTML", 'INFO');
        $html = generateSimpleHTML($data);
        
        // 3. Test PDF simple
        logMessage("Étape 3: Test PDF", 'INFO');
        $pdfPath = createSimplePDF($html, $data);
        if (!$pdfPath) {
            return ['success' => false, 'message' => 'Échec génération PDF'];
        }
        
        // 4. Test SignNow
        logMessage("Étape 4: Test SignNow", 'INFO');
        $documentId = uploadToSignNow($pdfPath);
        if (!$documentId) {
            unlink($pdfPath);
            return ['success' => false, 'message' => 'Échec upload SignNow'];
        }
        
        // 5. Envoi email
        logMessage("Étape 5: Envoi email", 'INFO');
        $emailResult = sendEmail($documentId, $data);
        
        // Nettoyage
        unlink($pdfPath);
        
        if ($emailResult) {
            return ['success' => true, 'message' => 'Contrat envoyé à ' . $data['email_acheteur']];
        } else {
            return ['success' => false, 'message' => 'Document créé mais erreur envoi email'];
        }
        
    } catch (Exception $e) {
        logMessage("Erreur dans generateContractStep: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function generateSimpleHTML($data) {
    // Version simplifiée pour éviter les erreurs
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contrat d\'Acquisition</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        h1 { color: #2E86AB; font-size: 18px; margin-bottom: 10px; }
        h2 { color: #A23B72; font-size: 14px; margin: 20px 0 10px 0; }
        .info-box { background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2E86AB; }
        .text-tag { color: #007bff; font-family: monospace; border: 1px dashed #007bff; padding: 8px; margin: 15px 0; text-align: center; }
        ul { margin: 10px 0; padding-left: 20px; }
        li { margin-bottom: 5px; }
        .paraphe { margin: 30px 0; text-align: right; page-break-inside: avoid; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT D\'ACQUISITION DE BOUTIQUE E-COMMERCE</h1>
        <p><strong>ID Boutique:</strong> ' . htmlspecialchars($data['id_boutique']) . '</p>
        <p><strong>Date:</strong> ' . date('d/m/Y') . '</p>
    </div>

    <h2>1. Objet du Contrat</h2>
    <p>Le présent contrat a pour objet l\'acquisition de la boutique e-commerce <strong>' . htmlspecialchars($data['id_boutique']) . '</strong> par <strong>' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</strong>.</p>

    <h2>2. Parties</h2>
    <div class="info-box">
        <p><strong>VENDEUR:</strong> ShopBuyHere<br>
        46-48 East Smithfield, London E1W 1AW, UK<br>
        Email: support@shopbuyhere.co</p>
        
        <p><strong>ACHETEUR:</strong><br>
        ' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '<br>
        ' . htmlspecialchars($data['adresse_acheteur']) . '<br>
        Tél: ' . htmlspecialchars($data['telephone_acheteur']) . '<br>
        Email: ' . htmlspecialchars($data['email_acheteur']) . '<br>
        Pièce ID: ' . htmlspecialchars($data['piece_identite']) . '</p>
    </div>

    <h2>3. Description de la Boutique</h2>
    <ul>
        <li><strong>Type de produits:</strong> ' . htmlspecialchars($data['type_produits']) . '</li>
        <li><strong>Secteur:</strong> ' . htmlspecialchars($data['secteur_activite']) . '</li>
        <li><strong>Date de lancement:</strong> ' . htmlspecialchars($data['date_lancement']) . '</li>
        <li><strong>CA mensuel moyen:</strong> ' . number_format($data['ca_mensuel'], 2, ',', ' ') . ' €</li>
    </ul>

    <div class="paraphe">
        <p><strong>Paraphe de l\'acheteur:</strong></p>
        <div class="text-tag">[[i|initial|req|signer1]]</div>
    </div>

    <div class="page-break"></div>

    <h2>4. Prix et Modalités</h2>
    <p>Prix d\'acquisition: <strong>' . number_format($data['prix_boutique'], 2, ',', ' ') . ' €</strong></p>
    <ul>
        <li>Acompte: 10% à la signature</li>
        <li>Solde: 90% dans les 10 jours suivant le premier versement</li>
    </ul>

    <h2>5. Participation Financière</h2>
    <p>L\'acheteur recevra 100% des bénéfices nets mensuels. Aucune garantie de rendement n\'est donnée.</p>

    <h2>6. Engagements</h2>
    <p>ShopBuyHere s\'engage à gérer la boutique professionnellement. L\'acheteur s\'engage à respecter la confidentialité.</p>

    <div class="paraphe">
        <p><strong>Paraphe de l\'acheteur:</strong></p>
        <div class="text-tag">[[i|initial|req|signer1]]</div>
    </div>

    <div class="page-break"></div>

    <h2>7. Signatures</h2>
    <p>Date: ' . date('d/m/Y') . '</p>
    
    <div style="margin-top: 50px;">
        <p><strong>Pour ShopBuyHere:</strong><br>
        William Davies, Directeur Général<br>
        Signature: _________________________</p>
    </div>
    
    <div style="margin-top: 50px;">
        <p><strong>Pour l\'Acheteur:</strong><br>
        ' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</p>
        <div class="text-tag">[[s|signature|req|signer1]]</div>
    </div>

    <div style="text-align: center; margin-top: 50px; font-size: 10px;">
        <p>Document généré le ' . date('d/m/Y à H:i') . '</p>
    </div>
</body>
</html>';

    return $html;
}

function createSimplePDF($html, $data) {
    try {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', false);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'contrat_' . $data['id_boutique'] . '_' . time() . '.pdf';
        $filepath = UPLOAD_DIR . $filename;
        
        file_put_contents($filepath, $dompdf->output());
        
        logMessage("PDF créé: " . $filename, 'SUCCESS');
        return $filepath;
        
    } catch (Exception $e) {
        logMessage("Erreur PDF: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function uploadToSignNow($pdfPath) {
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
            $docId = $result['id'] ?? null;
            logMessage("Upload SignNow réussi: " . $docId, 'SUCCESS');
            return $docId;
        } else {
            logMessage("Erreur SignNow HTTP $httpCode: $response", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Erreur upload SignNow: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function sendEmail($documentId, $data) {
    try {
        $url = "https://api.signnow.com/document/$documentId/invite";
        
        $message = "Bonjour " . $data['prenom_acheteur'] . " " . $data['nom_acheteur'] . ",\n\n" .
                  "Veuillez signer le contrat d'acquisition de la boutique " . $data['id_boutique'] . ".\n\n" .
                  "Instructions :\n" .
                  "1. Paraphez en bas de chaque page\n" .
                  "2. Signez à la fin du document\n\n" .
                  "Prix : " . number_format($data['prix_boutique'], 2, ',', ' ') . " €\n\n" .
                  "Cordialement,\nShopBuyHere";
        
        $inviteData = [
            'to' => [[
                'email' => $data['email_acheteur'],
                'role_name' => 'Acheteur',
                'role' => 'signer',
                'order' => 1
            ]],
            'from' => COMPANY_EMAIL,
            'subject' => "Signature requise - Contrat boutique " . $data['id_boutique'],
            'message' => $message
        ];
        
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
            logMessage("Email envoyé à " . $data['email_acheteur'], 'SUCCESS');
            return true;
        } else {
            logMessage("Erreur email HTTP $httpCode: $response", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Erreur envoi email: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat Génération</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>📄 Résultat Génération Contrat</h1>
    
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