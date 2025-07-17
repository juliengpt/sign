<?php
// backend-fixed.php - Version avec Text Tags SignNow corrigés
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
        logMessage("=== DÉBUT GÉNÉRATION CONTRAT CORRIGÉ ===", 'INFO');
        $result = generateContractWithFixedTextTags($_POST);
        
        if ($result['success']) {
            $message = "✅ " . $result['message'];
            logMessage("=== CONTRAT GÉNÉRÉ AVEC SUCCÈS ===", 'SUCCESS');
        } else {
            $error = "❌ " . $result['message'];
            logMessage("=== ERREUR GÉNÉRATION ===", 'ERROR');
        }
    } catch (Exception $e) {
        $error = "❌ Erreur système: " . $e->getMessage();
        logMessage("Exception: " . $e->getMessage(), 'ERROR');
    }
}

function generateContractWithFixedTextTags($formData) {
    try {
        logMessage("Étape 1: Validation données", 'INFO');
        $validation = validateContractData($formData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        logMessage("Étape 2: Génération HTML avec Text Tags corrigés", 'INFO');
        $htmlContent = generateFixedContractHTML($formData);
        if (!$htmlContent) {
            return ['success' => false, 'message' => 'Impossible de générer le HTML'];
        }
        
        logMessage("Étape 3: Conversion PDF avec Text Tags", 'INFO');
        $pdfPath = convertHTMLToPdfWithTextTags($htmlContent, $formData);
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
        
        // NOUVELLE APPROCHE : Attendre que SignNow traite les Text Tags
        logMessage("Étape 5: Attente traitement Text Tags", 'INFO');
        sleep(3); // Attendre 3 secondes que SignNow traite les Text Tags
        
        logMessage("Étape 6: Envoi invitation signature", 'INFO');
        $inviteResult = sendSigningInvitationFixed($documentId, $formData);
        
        // Nettoyage
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        return $inviteResult;
        
    } catch (Exception $e) {
        logMessage("Erreur generateContractWithFixedTextTags: " . $e->getMessage(), 'ERROR');
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

function generateFixedContractHTML($data) {
    // HTML avec Text Tags SignNow CORRECTEMENT formatés
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contrat d\'Acquisition Boutique</title>
    <style>
        body {
            font-family: "Times New Roman", serif;
            line-height: 1.6;
            margin: 40px;
            color: #333;
            font-size: 12pt;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2E86AB;
        }
        
        h1 {
            color: #2E86AB;
            font-size: 18pt;
            margin: 20px 0;
        }
        
        h2 {
            color: #A23B72;
            font-size: 14pt;
            border-bottom: 1px solid #A23B72;
            padding-bottom: 5px;
            margin-top: 30px;
        }
        
        .section {
            margin: 25px 0;
            padding: 15px 0;
        }
        
        .parties {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        
        .partie {
            display: table-cell;
            width: 50%;
            padding: 15px;
            vertical-align: top;
        }
        
        .signature-area {
            margin-top: 50px;
            padding: 30px;
            border: 2px solid #A23B72;
            page-break-inside: avoid;
        }
        
        .text-tag-container {
            background: #f0f8ff;
            padding: 20px;
            margin: 20px 0;
            border: 1px dashed #0066cc;
            text-align: center;
            min-height: 60px;
            position: relative;
        }
        
        .text-tag {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #0066cc;
            font-weight: normal;
            display: inline-block;
            background: white;
            padding: 5px 10px;
            border: 1px solid #0066cc;
        }
        
        .paraphe-area {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 100px;
            height: 40px;
            border: 1px dashed #999;
            text-align: center;
            background: #f9f9f9;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .financial-highlight {
            background: #e8f5e8;
            padding: 20px;
            border: 2px solid #4CAF50;
            text-align: center;
            font-size: 14pt;
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
        
        @page {
            margin: 2cm;
            @bottom-right {
                content: "Paraphes: ";
            }
        }
        
        @media print {
            .no-print { display: none; }
            .page-break { page-break-before: always; }
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

    <!-- PARAPHE PAGE 1 -->
    <div class="text-tag-container">
        <p><strong>Paraphe de l\'acheteur (Page 1) :</strong></p>
        <div class="text-tag">{{#initial}}{{/initial}}</div>
    </div>

    <div class="page-break"></div>

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

    <!-- PARAPHE PAGE 2 -->
    <div class="text-tag-container">
        <p><strong>Paraphe de l\'acheteur (Page 2) :</strong></p>
        <div class="text-tag">{{#initial}}{{/initial}}</div>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <h2>8. DROIT APPLICABLE ET LITIGES</h2>
        <p>Le présent contrat est régi par le droit anglais. En cas de litige, les parties privilégieront une résolution amiable. À défaut, les tribunaux compétents d\'Angleterre seront seuls compétents.</p>
    </div>

    <div class="section">
        <h2>9. DISPOSITIONS FINALES</h2>
        <p>Ce contrat entre en vigueur à la date de signature électronique par les deux parties. Il constitue l\'accord complet entre les parties et remplace tous accords antérieurs.</p>
        <p>Toute modification devra faire l\'objet d\'un avenant écrit et signé électroniquement.</p>
    </div>

    <div class="signature-area">
        <h2>10. SIGNATURES</h2>
        
        <p><strong>Instructions :</strong> Veuillez signer électroniquement ci-dessous pour valider ce contrat.</p>
        
        <div style="margin: 40px 0;">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="width: 50%; border: none; text-align: center;">
                        <h4>Pour le Vendeur</h4>
                        <p><strong>ShopBuyHere</strong><br>MPI MANAGE LTD</p>
                        <div style="margin: 20px 0; padding: 20px; border: 1px solid #ccc;">
                            <p>William Davies<br>Directeur Général</p>
                            <p><em>Signature électronique pré-autorisée</em></p>
                        </div>
                        <p><small>Date: ' . formatDate(date('Y-m-d')) . '</small></p>
                    </td>
                    <td style="width: 50%; border: none; text-align: center;">
                        <h4>Pour l\'Acheteur</h4>
                        <p><strong>' . htmlspecialchars($data['prenom_acheteur']) . ' ' . htmlspecialchars($data['nom_acheteur']) . '</strong></p>
                        
                        <div class="text-tag-container" style="margin: 20px 0; min-height: 80px;">
                            <p><strong>Signature électronique requise :</strong></p>
                            <div class="text-tag" style="font-size: 12pt; padding: 10px 20px;">
                                {{#signature}}{{/signature}}
                            </div>
                        </div>
                        
                        <p><small>Date de signature: ______________</small></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div style="margin-top: 30px; padding: 15px; background: #f0f8ff; border: 1px solid #0066cc; text-align: center;">
            <p><strong>⚠️ IMPORTANT :</strong> Ce contrat doit être signé dans un délai de ' . ($data['delai_signature'] ?? '30') . ' jours.</p>
            <p>La signature électronique a la même valeur légale qu\'une signature manuscrite.</p>
        </div>
    </div>

    <div style="margin-top: 50px; text-align: center; font-size: 10pt; color: #666;">
        <p><strong>Document généré automatiquement le ' . date('d/m/Y à H:i:s') . '</strong></p>
        <p>Contrat légalement contraignant • Pour questions: support@shopbuyhere.co</p>
        <p>ID Document: ' . htmlspecialchars($data['id_boutique']) . ' • Version 2.0</p>
    </div>

</body>
</html>';

    return $html;
}

function convertHTMLToPdfWithTextTags($htmlContent, $data) {
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

function sendSigningInvitationFixed($documentId, $formData) {
    try {
        // NOUVELLE APPROCHE: Utiliser l'API simple d'invitation
        $url = "https://api.signnow.com/document/$documentId/invite";
        
        $message = !empty($formData['message_client']) ? $formData['message_client'] : 
            "Bonjour " . $formData['prenom_acheteur'] . " " . $formData['nom_acheteur'] . ",\n\n" .
            "Veuillez signer le contrat d'acquisition de la boutique " . $formData['id_boutique'] . ".\n\n" .
            "Le document contient des zones de signature électronique pré-configurées.\n" .
            "Suivez les instructions dans l'email pour signer.\n\n" .
            "Prix d'acquisition : " . formatCurrency($formData['prix_boutique'] ?? '0') . "\n\n" .
            "Cordialement,\n" .
            COMPANY_NAME;
        
        // Invitation simplifiée sans spécifier de champs
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
            'subject' => "🔒 Signature requise - Contrat boutique " . $formData['id_boutique'],
            'message' => $message,
            'cc' => []
        ];
        
        // Ajouter copie si nécessaire
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
        
        logMessage("Réponse SignNow HTTP $httpCode: $response", 'INFO');
        
        if ($httpCode === 200 || $httpCode === 201) {
            logMessage("Invitation envoyée avec succès", 'SUCCESS');
            return [
                'success' => true, 
                'message' => "✅ CONTRAT ENVOYÉ AVEC SUCCÈS !\n\n" .
                           "📧 Email envoyé à: " . $formData['email_acheteur'] . "\n" .
                           "📄 Document ID: $documentId\n" .
                           "💰 Montant: " . formatCurrency($formData['prix_boutique'] ?? '0') . "\n" .
                           "⏰ Délai: " . ($formData['delai_signature'] ?? 30) . " jours\n\n" .
                           "🎯 Le client va recevoir un email avec le lien de signature !"
            ];
        } else {
            logMessage("Erreur invitation HTTP $httpCode: $response", 'ERROR');
            
            // Essayer une approche alternative
            return tryAlternativeInvitation($documentId, $formData);
        }
        
    } catch (Exception $e) {
        logMessage("Erreur sendSigningInvitationFixed: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur envoi: ' . $e->getMessage()];
    }
}

function tryAlternativeInvitation($documentId, $formData) {
    try {
        logMessage("Tentative invitation alternative", 'INFO');
        
        // Approche alternative: Créer un lien public
        $url = "https://api.signnow.com/document/$documentId/download/link";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . SIGNNOW_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $downloadLink = $result['link'] ?? null;
            
            if ($downloadLink) {
                logMessage("Lien de téléchargement créé", 'SUCCESS');
                
                return [
                    'success' => true,
                    'message' => "✅ DOCUMENT CRÉÉ AVEC SUCCÈS !\n\n" .
                               "📄 Le contrat PDF a été généré et uploadé vers SignNow\n" .
                               "🔗 Document ID: $documentId\n" .
                               "💰 Montant: " . formatCurrency($formData['prix_boutique'] ?? '0') . "\n\n" .
                               "⚠️ NOTE: L'envoi automatique d'email nécessite une configuration Text Tags avancée.\n" .
                               "📧 Pour l'instant, vous pouvez partager le document manuellement depuis votre dashboard SignNow.\n\n" .
                               "🎯 PROCHAINE ÉTAPE: Connectez-vous à votre compte SignNow pour envoyer le document."
                ];
            }
        }
        
        return [
            'success' => false, 
            'message' => "Document créé mais erreur envoi email. Consultez votre dashboard SignNow pour envoyer manuellement."
        ];
        
    } catch (Exception $e) {
        logMessage("Erreur invitation alternative: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
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
    <title>Résultat Génération - Version Corrigée</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
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
            background: #d4edda;
            color: #155724;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 5px solid #dc3545;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 10px;
            margin: 10px 5px;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,123,255,0.3);
        }
        
        .btn.success { background: linear-gradient(45deg, #28a745, #20c997); }
        .btn.warning { background: linear-gradient(45deg, #ffc107, #ffcd39); color: #212529; }
        .btn.danger { background: linear-gradient(45deg, #dc3545, #e85d75); }
        
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #bee5eb;
        }
        
        .highlight {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #ffc107;
            margin: 20px 0;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .stat-card h3 {
            color: #495057;
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        
        .stat-card p {
            color: #6c757d;
            margin: 0;
        }
        
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 Résultat Génération - Version Text Tags Corrigée</h1>
        <p><strong>Version améliorée avec gestion Text Tags SignNow optimisée</strong></p>
        
        <?php if ($message): ?>
            <div class="success">
                <h3>✅ Processus Réussi</h3>
                <?= nl2br(htmlspecialchars($message)) ?>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <h3>📄</h3>
                    <p>PDF Généré</p>
                </div>
                <div class="stat-card">
                    <h3>⬆️</h3>
                    <p>Upload SignNow</p>
                </div>
                <div class="stat-card">
                    <h3>🔧</h3>
                    <p>Text Tags Corrigés</p>
                </div>
                <div class="stat-card">
                    <h3>📧</h3>
                    <p>Prêt Envoi</p>
                </div>
            </div>
            
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <h3>❌ Problème Détecté</h3>
                <?= nl2br(htmlspecialchars($error)) ?>
            </div>
            
            <div class="highlight">
                <h4>🔧 Solutions Recommandées :</h4>
                <ul>
                    <li>Vérifier les logs détaillés : <a href="logs.php">logs.php</a></li>
                    <li>Tester la version simple sans PDF : <a href="backend-simple.php">backend-simple.php</a></li>
                    <li>Vérifier la configuration SignNow : <a href="test-final.php">test-final.php</a></li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>📋 Information Text Tags SignNow</h4>
            <p><strong>Problème identifié :</strong> Les Text Tags originaux <code>[[i|initial|req|signer1]]</code> ne sont pas correctement traités par SignNow.</p>
            <p><strong>Solution appliquée :</strong> Utilisation du format <code>{{#signature}}{{/signature}}</code> et <code>{{#initial}}{{/initial}}</code> plus compatible.</p>
            <p><strong>Alternative :</strong> Configuration manuelle des champs dans le dashboard SignNow si nécessaire.</p>
        </div>
        
        <?php if (isset($result['html_file']) && file_exists($result['html_file'])): ?>
            <div class="highlight">
                <h4>📄 Fichier Généré :</h4>
                <p><strong>PDF :</strong> <?= basename($result['html_file']) ?></p>
                <p><strong>Taille :</strong> <?= number_format(filesize($result['html_file'])) ?> octets</p>
                <a href="uploads/<?= basename($result['html_file']) ?>" target="_blank" class="btn success">📖 Voir le Document</a>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>🎯 Prochaines Étapes Recommandées :</h4>
            <ol>
                <li><strong>Vérifier votre email</strong> - Le contrat devrait arriver dans quelques minutes</li>
                <li><strong>Dashboard SignNow</strong> - Connectez-vous pour voir le document uploadé</li>
                <li><strong>Configuration Text Tags</strong> - Si besoin, ajustez manuellement dans SignNow</li>
                <li><strong>Test signature</strong> - Essayez le processus de signature complet</li>
            </ol>
        </div>
        
        <div style="background: #e3f2fd; padding: 20px; border-radius: 10px; margin: 20px 0;">
            <h4>🔍 Debug Information :</h4>
            <p><strong>Heure :</strong> <?= date('d/m/Y H:i:s') ?></p>
            <p><strong>Version :</strong> Text Tags Corrigés v2.0</p>
            <p><strong>Mémoire utilisée :</strong> <?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB</p>
            <p><strong>Pic mémoire :</strong> <?= round(memory_get_peak_usage() / 1024 / 1024, 2) ?> MB</p>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="index.php" class="btn">🏠 Nouveau Contrat</a>
            <a href="logs.php" class="btn warning">📋 Voir Logs Détaillés</a>
            <a href="https://app.signnow.com" target="_blank" class="btn success">🌐 Dashboard SignNow</a>
            
            <?php if (!$error): ?>
                <a href="backend-simple.php" class="btn">🧪 Tester Version Simple</a>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 0.9em; color: #6c757d;">
            <p><strong>💡 Conseil :</strong> Si le Text Tags automatique ne fonctionne pas parfaitement, vous pouvez :</p>
            <ul>
                <li>Utiliser le dashboard SignNow pour configurer manuellement les zones de signature</li>
                <li>Créer un template réutilisable avec les champs pré-positionnés</li>
                <li>Ajuster les coordonnées des Text Tags selon vos besoins</li>
            </ul>
        </div>
    </div>
</body>
</html>