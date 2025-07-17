<?php
// backend-json-fields.php - Version avec création de champs via JSON API
ini_set('memory_limit', '512M');
set_time_limit(300);
require_once 'config.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$message = '';
$error = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_contract') {
    try {
        logMessage("=== DÉBUT GÉNÉRATION AVEC CHAMPS JSON ===", 'INFO');
        $result = generateContractWithJSONFields($_POST);
        
        if ($result['success']) {
            $message = "✅ " . $result['message'];
            logMessage("=== CONTRAT ENVOYÉ AVEC SUCCÈS ===", 'SUCCESS');
        } else {
            $error = "❌ " . $result['message'];
            logMessage("=== ERREUR GÉNÉRATION ===", 'ERROR');
        }
    } catch (Exception $e) {
        $error = "❌ Erreur système: " . $e->getMessage();
        logMessage("Exception: " . $e->getMessage(), 'ERROR');
    }
}

function generateContractWithJSONFields($formData) {
    try {
        logMessage("Étape 1: Validation données", 'INFO');
        $validation = validateContractData($formData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        logMessage("Étape 2: Génération PDF propre (sans Text Tags)", 'INFO');
        $htmlContent = generateCleanContractHTML($formData);
        if (!$htmlContent) {
            return ['success' => false, 'message' => 'Impossible de générer le HTML'];
        }
        
        logMessage("Étape 3: Conversion PDF", 'INFO');
        $pdfPath = convertHTMLToPdf($htmlContent, $formData);
        if (!$pdfPath) {
            return ['success' => false, 'message' => 'Impossible de générer le PDF'];
        }
        
        logMessage("PDF créé: " . basename($pdfPath), 'SUCCESS');
        
        logMessage("Étape 4: Upload vers SignNow", 'INFO');
        $documentId = uploadPdfToSignNow($pdfPath);
        if (!$documentId) {
            return ['success' => false, 'message' => 'Impossible d\'uploader vers SignNow'];
        }
        
        logMessage("Upload SignNow réussi: " . $documentId, 'SUCCESS');
        
        logMessage("Étape 5: Création des champs de signature via JSON", 'INFO');
        $fieldsCreated = createSignatureFieldsJSON($documentId);
        if (!$fieldsCreated) {
            return ['success' => false, 'message' => 'Impossible de créer les champs de signature'];
        }
        
        logMessage("Champs de signature créés avec succès", 'SUCCESS');
        
        logMessage("Étape 6: Envoi invitation signature", 'INFO');
        $inviteResult = sendSigningInvitation($documentId, $formData);
        
        // Nettoyage
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        return $inviteResult;
        
    } catch (Exception $e) {
        logMessage("Erreur generateContractWithJSONFields: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

function validateContractData($data) {
    $requiredFields = [
        'id_boutique' => 'ID Boutique',
        'nom_acheteur' => 'Nom Acheteur',
        'prenom_acheteur' => 'Prénom Acheteur',
        'email_acheteur' => 'Email'
    ];
    
    foreach ($requiredFields as $field => $label) {
        if (empty($data[$field])) {
            return ['valid' => false, 'message' => "Le champ '$label' est obligatoire"];
        }
    }
    
    if (!filter_var($data['email_acheteur'], FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Format email invalide'];
    }
    
    return ['valid' => true];
}

function generateCleanContractHTML($data) {
    // HTML PROPRE sans Text Tags - Les champs seront ajoutés via l'API JSON
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contrat d\'Acquisition Boutique</title>
    <style>
        @page {
            margin: 2cm;
            size: A4;
        }
        
        body {
            font-family: "Times New Roman", serif;
            line-height: 1.6;
            color: #333;
            font-size: 11pt;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2E86AB;
        }
        
        h1 {
            color: #2E86AB;
            font-size: 16pt;
            margin: 15px 0;
        }
        
        h2 {
            color: #A23B72;
            font-size: 13pt;
            border-bottom: 1px solid #A23B72;
            padding-bottom: 3px;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        
        h3 {
            color: #333;
            font-size: 12pt;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        
        .section {
            margin: 20px 0;
            padding: 10px 0;
        }
        
        .parties {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        
        .partie {
            display: table-cell;
            width: 50%;
            padding: 15px;
            vertical-align: top;
            border: 1px solid #ddd;
        }
        
        .signature-area {
            margin-top: 40px;
            padding: 20px;
            border: 2px solid #A23B72;
            page-break-inside: avoid;
        }
        
        .signature-placeholder {
            height: 80px;
            border: 1px dashed #999;
            margin: 15px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            font-style: italic;
            color: #666;
        }
        
        .paraphe-placeholder {
            height: 40px;
            width: 120px;
            border: 1px dashed #999;
            margin: 10px 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            font-style: italic;
            color: #666;
            font-size: 9pt;
        }
        
        .financial-highlight {
            background: #e8f5e8;
            padding: 20px;
            border: 2px solid #4CAF50;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            margin: 20px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        td, th {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background: #f2f2f2;
            font-weight: bold;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        ul, ol {
            padding-left: 20px;
        }
        
        li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT D\'ACQUISITION D\'UNE BOUTIQUE E-COMMERCE</h1>
        <p><strong>Référence:</strong> ' . htmlspecialchars($data['id_boutique']) . '</p>
        <p><strong>Date:</strong> ' . formatDate($data['date_contrat'] ?? date('Y-m-d')) . '</p>
        <p><strong>Société:</strong> ShopBuyHere - MPI MANAGE LTD</p>
    </div>

    <div class="section">
        <h2>1. OBJET DU CONTRAT</h2>
        <p>Le présent contrat a pour objet l\'acquisition de la boutique e-commerce identifiée par l\'ID <strong>' . htmlspecialchars($data['id_boutique']) . '</strong>.</p>
        <p>Cette acquisition permet à l\'acquéreur de devenir propriétaire de la boutique selon les modalités définies ci-après.</p>
    </div>

    <div class="section">
        <h2>2. IDENTIFICATION DES PARTIES</h2>
        <div class="parties">
            <div class="partie">
                <h3>LE VENDEUR</h3>
                <p><strong>ShopBuyHere</strong><br>
                Société opérée par MPI MANAGE LTD<br>
                Société de droit anglais<br>
                Email: support@shopbuyhere.co<br>
                Représentée par: William Davies</p>
            </div>
            <div class="partie">
                <h3>L\'ACHETEUR</h3>
                <p><strong>' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</strong><br>
                Email: ' . htmlspecialchars($data['email_acheteur']) . '<br>
                Adresse: ' . htmlspecialchars($data['adresse_acheteur'] ?? 'À compléter') . '<br>
                Téléphone: ' . htmlspecialchars($data['telephone_acheteur'] ?? 'À compléter') . '</p>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>3. DÉTAILS DE LA BOUTIQUE</h2>
        <table>
            <tr><th>Secteur d\'activité</th><td>' . htmlspecialchars($data['secteur_activite'] ?? 'E-commerce') . '</td></tr>
            <tr><th>Type de produits</th><td>' . htmlspecialchars($data['type_produits'] ?? 'Produits variés') . '</td></tr>
            <tr><th>Date de lancement</th><td>' . formatDate($data['date_lancement'] ?? date('Y-m-d')) . '</td></tr>
            <tr><th>CA mensuel estimé</th><td>' . formatCurrency($data['ca_mensuel'] ?? '0') . '</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>4. CONDITIONS FINANCIÈRES</h2>
        <div class="financial-highlight">
            Prix d\'acquisition: ' . formatCurrency($data['prix_boutique'] ?? '0') . '
        </div>
        <p>Le prix d\'acquisition est payable selon les modalités convenues entre les parties.</p>
        <p>Ce prix inclut la propriété complète de la boutique, l\'accès au dashboard et le support technique.</p>
    </div>

    <div class="section">
        <h2>5. ENGAGEMENTS DES PARTIES</h2>
        <h3>Engagements du Vendeur :</h3>
        <ul>
            <li>Garantir le bon fonctionnement de la boutique</li>
            <li>Fournir l\'accès complet au dashboard de gestion</li>
            <li>Assurer le support technique pendant la transition</li>
            <li>Maintenir la confidentialité des données clients</li>
        </ul>
        
        <h3>Engagements de l\'Acheteur :</h3>
        <ul>
            <li>Respecter les modalités de paiement convenues</li>
            <li>Maintenir l\'activité commerciale de la boutique</li>
            <li>Respecter les conditions d\'utilisation de la plateforme</li>
            <li>Préserver la réputation de la marque</li>
        </ul>
    </div>

    <!-- ZONE PARAPHE PAGE 1 -->
    <div style="margin: 20px 0;">
        <p><strong>Paraphe de l\'acheteur (Page 1) :</strong></p>
        <div class="paraphe-placeholder">Paraphe ici</div>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2>6. PROPRIÉTÉ INTELLECTUELLE</h2>
        <p>La cession inclut tous les droits de propriété intellectuelle associés à la boutique, notamment :</p>
        <ul>
            <li>Les noms de domaine et marques</li>
            <li>Les contenus et descriptions produits</li>
            <li>Les images et visuels</li>
            <li>Les bases de données clients (dans le respect du RGPD)</li>
        </ul>
    </div>

    <div class="section">
        <h2>7. CONFIDENTIALITÉ</h2>
        <p>Les parties s\'engagent à respecter la confidentialité de toutes les informations échangées, notamment les données clients, les informations financières et les méthodes commerciales.</p>
    </div>

    <div class="section">
        <h2>8. DROIT APPLICABLE ET LITIGES</h2>
        <p>Le présent contrat est régi par le droit anglais. En cas de litige, les parties privilégieront une résolution amiable. À défaut, les tribunaux compétents d\'Angleterre seront seuls compétents.</p>
    </div>

    <!-- ZONE PARAPHE PAGE 2 -->
    <div style="margin: 20px 0;">
        <p><strong>Paraphe de l\'acheteur (Page 2) :</strong></p>
        <div class="paraphe-placeholder">Paraphe ici</div>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2>9. DISPOSITIONS FINALES</h2>
        <p>Ce contrat entre en vigueur à la date de signature électronique par les deux parties. Il constitue l\'accord complet entre les parties et remplace tous accords antérieurs.</p>
        <p>Toute modification devra faire l\'objet d\'un avenant écrit et signé électroniquement.</p>
    </div>

    <div class="signature-area">
        <h2>10. SIGNATURES</h2>
        
        <p><strong>Instructions :</strong> Veuillez signer électroniquement ci-dessous pour valider ce contrat.</p>
        
        <table style="width: 100%; border: none; margin-top: 30px;">
            <tr>
                <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                    <h4>Pour le Vendeur</h4>
                    <p><strong>ShopBuyHere</strong><br>MPI MANAGE LTD</p>
                    <div style="margin: 20px 0; padding: 20px; border: 1px solid #ccc;">
                        <p>William Davies<br>Directeur Général</p>
                        <p><em>Signature électronique pré-autorisée</em></p>
                    </div>
                    <p><small>Date: ' . formatDate(date('Y-m-d')) . '</small></p>
                </td>
                <td style="width: 50%; border: none; text-align: center; vertical-align: top;">
                    <h4>Pour l\'Acheteur</h4>
                    <p><strong>' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</strong></p>
                    
                    <!-- ZONE SIGNATURE PRINCIPALE -->
                    <div style="margin: 20px 0;">
                        <p><strong>Signature électronique :</strong></p>
                        <div class="signature-placeholder">Zone de signature</div>
                    </div>
                    
                    <p><small>Date de signature: ______________</small></p>
                </td>
            </tr>
        </table>
        
        <div style="margin-top: 30px; padding: 15px; background: #f0f8ff; border: 1px solid #0066cc; text-align: center;">
            <p><strong>⚠️ IMPORTANT :</strong> Ce contrat doit être signé dans un délai de ' . ($data['delai_signature'] ?? '30') . ' jours.</p>
            <p>La signature électronique a la même valeur légale qu\'une signature manuscrite.</p>
        </div>
    </div>

    <div class="footer">
        <p><strong>Document généré automatiquement le ' . date('d/m/Y à H:i:s') . '</strong></p>
        <p>Contrat légalement contraignant • Pour questions: support@shopbuyhere.co</p>
        <p>ID Document: ' . htmlspecialchars($data['id_boutique']) . ' • Version JSON Fields 3.0</p>
    </div>

</body>
</html>';

    return $html;
}

function convertHTMLToPdf($htmlContent, $data) {
    try {
        $options = new Options();
        $options->set('defaultFont', 'Times New Roman');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'contrat_' . $data['id_boutique'] . '_' . time() . '.pdf';
        $filepath = UPLOAD_DIR . $filename;
        
        file_put_contents($filepath, $dompdf->output());
        
        return $filepath;
        
    } catch (Exception $e) {
        logMessage("Erreur PDF: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

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
            return $result['id'] ?? null;
        } else {
            logMessage("Erreur upload HTTP $httpCode: $response", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Erreur upload: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function createSignatureFieldsJSON($documentId) {
    try {
        logMessage("Création des champs de signature pour document $documentId", 'INFO');
        
        $url = "https://api.signnow.com/document/$documentId/fields";
        
        // Configuration des champs selon votre exemple JSON
        $fieldsData = [
            "fields" => [
                // Paraphe Page 1 (approximativement où se trouve le placeholder)
                [
                    "x" => 50,
                    "y" => 650,
                    "width" => 100,
                    "height" => 30,
                    "page_number" => 0, // Page 1 (0-indexed)
                    "role" => "Signer 1",
                    "required" => true,
                    "type" => "initials",
                    "name" => "ParaphePage1"
                ],
                
                // Paraphe Page 2
                [
                    "x" => 50,
                    "y" => 650,
                    "width" => 100,
                    "height" => 30,
                    "page_number" => 1, // Page 2
                    "role" => "Signer 1",
                    "required" => true,
                    "type" => "initials",
                    "name" => "ParaphePage2"
                ],
                
                // Signature principale Page 3
                [
                    "x" => 300,
                    "y" => 500,
                    "width" => 200,
                    "height" => 60,
                    "page_number" => 2, // Page 3
                    "role" => "Signer 1",
                    "required" => true,
                    "type" => "signature",
                    "name" => "SignaturePrincipale"
                ]
            ]
        ];
        
        logMessage("Configuration champs: " . json_encode($fieldsData), 'INFO');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fieldsData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . SIGNNOW_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        logMessage("Réponse création champs HTTP $httpCode: $response", 'INFO');
        
        if ($httpCode === 200 || $httpCode === 201) {
            logMessage("Champs créés avec succès", 'SUCCESS');
            return true;
        } else {
            logMessage("Erreur création champs HTTP $httpCode: $response", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Erreur createSignatureFieldsJSON: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function sendSigningInvitation($documentId, $formData) {
    try {
        $url = "https://api.signnow.com/document/$documentId/invite";
        
        $message = !empty($formData['message_client']) ? $formData['message_client'] : 
            "Bonjour " . $formData['prenom_acheteur'] . " " . $formData['nom_acheteur'] . ",\n\n" .
            "Veuillez signer le contrat d'acquisition de la boutique " . $formData['id_boutique'] . ".\n\n" .
            "Le document contient des zones de signature pré-configurées :\n" .
            "• Paraphez les pages 1 et 2\n" .
            "• Signez à la fin du document\n\n" .
            "Prix d'acquisition : " . formatCurrency($formData['prix_boutique'] ?? '0') . "\n\n" .
            "Délai de signature : " . ($formData['delai_signature'] ?? 30) . " jours.\n\n" .
            "Cordialement,\n" .
            COMPANY_NAME;
        
        $inviteData = [
            'to' => [[
                'email' => $formData['email_acheteur'],
                'role_name' => 'Signer 1',
                'role' => 'signer',
                'order' => 1,
                'reassign' => false,
                'decline_by_signature' => false,
                'reminder' => 1,
                'expiration_days' => intval($formData['delai_signature'] ?? 30)
            ]],
            'from' => COMPANY_EMAIL,
            'subject' => "🔒 Signature requise - Contrat boutique " . $formData['id_boutique'],
            'message' => $message
        ];
        
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
        
        logMessage("Réponse invitation HTTP $httpCode: $response", 'INFO');
        
        if ($httpCode === 200 || $httpCode === 201) {
            logMessage("Invitation envoyée avec succès", 'SUCCESS');
            return [
                'success' => true, 
                'message' => "🎉 CONTRAT ENVOYÉ AVEC SUCCÈS !\n\n" .
                           "📧 Email envoyé à: " . $formData['email_acheteur'] . "\n" .
                           "📄 Document ID: $documentId\n" .
                           "💰 Montant: " . formatCurrency($formData['prix_boutique'] ?? '0') . "\n" .
                           "⏰ Délai: " . ($formData['delai_signature'] ?? 30) . " jours\n" .
                           "✅ Champs de signature: 2 paraphes + 1 signature\n\n" .
                           "🎯 Le client va recevoir un email avec les zones de signature pré-positionnées !"
            ];
        } else {
            logMessage("Erreur invitation HTTP $httpCode: $response", 'ERROR');
            return ['success' => false, 'message' => 'Erreur envoi invitation: ' . $response];
        }
        
    } catch (Exception $e) {
        logMessage("Erreur sendSigningInvitation: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur envoi: ' . $e->getMessage()];
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

// Interface de résultat
if (!$_POST) {
    header('Location: index.php');
    exit;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat avec Champs JSON</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;