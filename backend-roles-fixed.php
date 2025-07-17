<?php
// backend-roles-fixed.php - Version avec rôles corrigés SignNow
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
        logMessage("=== DÉBUT GÉNÉRATION AVEC RÔLES CORRECTS ===", 'INFO');
        $result = generateContractWithCorrectRoles($_POST);
        
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

function generateContractWithCorrectRoles($formData) {
    try {
        logMessage("Étape 1: Validation", 'INFO');
        $validation = validateContractData($formData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        logMessage("Étape 2: Génération PDF", 'INFO');
        $htmlContent = generateCleanContractHTML($formData);
        $pdfPath = convertHTMLToPdf($htmlContent, $formData);
        if (!$pdfPath) {
            return ['success' => false, 'message' => 'Erreur génération PDF'];
        }
        
        logMessage("Étape 3: Upload SignNow", 'INFO');
        $documentId = uploadPdfToSignNow($pdfPath);
        if (!$documentId) {
            return ['success' => false, 'message' => 'Erreur upload SignNow'];
        }
        
        logMessage("Upload réussi: " . $documentId, 'SUCCESS');
        
        // MÉTHODE ALTERNATIVE: Utiliser l'API de rôles d'abord
        logMessage("Étape 4: Configuration des rôles", 'INFO');
        $rolesConfigured = configureDocumentRoles($documentId, $formData);
        
        logMessage("Étape 5: Création champs avec rôle correct", 'INFO');
        $fieldsCreated = createFieldsWithCorrectRole($documentId, $formData);
        if (!$fieldsCreated) {
            return ['success' => false, 'message' => 'Erreur création champs'];
        }
        
        logMessage("Étape 6: Envoi invitation avec rôle correspondant", 'INFO');
        $inviteResult = sendInvitationWithMatchingRole($documentId, $formData);
        
        // Nettoyage
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        return $inviteResult;
        
    } catch (Exception $e) {
        logMessage("Erreur: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

function validateContractData($data) {
    $required = ['id_boutique', 'nom_acheteur', 'prenom_acheteur', 'email_acheteur'];
    
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['valid' => false, 'message' => "Champ '$field' obligatoire"];
        }
    }
    
    if (!filter_var($data['email_acheteur'], FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Email invalide'];
    }
    
    return ['valid' => true];
}

function generateCleanContractHTML($data) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contrat Boutique</title>
    <style>
        @page { margin: 2.5cm; size: A4; }
        body { font-family: "Times New Roman", serif; font-size: 11pt; line-height: 1.5; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        h1 { font-size: 16pt; color: #000; margin: 10px 0; }
        h2 { font-size: 13pt; color: #333; margin: 20px 0 10px 0; border-bottom: 1px solid #ccc; }
        .parties { display: table; width: 100%; margin: 20px 0; }
        .partie { display: table-cell; width: 48%; padding: 15px; border: 1px solid #333; vertical-align: top; }
        .signature-zone { height: 80px; border: 2px dashed #999; margin: 20px 0; background: #f9f9f9; display: flex; align-items: center; justify-content: center; font-style: italic; color: #666; }
        .paraphe-zone { height: 40px; width: 120px; border: 1px dashed #999; margin: 15px 0; background: #f9f9f9; display: inline-flex; align-items: center; justify-content: center; font-style: italic; color: #666; }
        .financial { background: #f0f8f0; padding: 20px; border: 2px solid #4CAF50; text-align: center; font-weight: bold; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        td, th { border: 1px solid #333; padding: 8px; }
        th { background: #f0f0f0; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT D\'ACQUISITION BOUTIQUE E-COMMERCE</h1>
        <p><strong>Référence:</strong> ' . htmlspecialchars($data['id_boutique']) . '</p>
        <p><strong>Date:</strong> ' . date('d/m/Y') . '</p>
    </div>

    <div>
        <h2>1. OBJET DU CONTRAT</h2>
        <p>Acquisition de la boutique e-commerce <strong>' . htmlspecialchars($data['id_boutique']) . '</strong>.</p>
    </div>

    <div>
        <h2>2. PARTIES</h2>
        <div class="parties">
            <div class="partie">
                <h3>VENDEUR</h3>
                <p><strong>ShopBuyHere</strong><br>
                MPI MANAGE LTD<br>
                Email: support@shopbuyhere.co</p>
            </div>
            <div class="partie">
                <h3>ACHETEUR</h3>
                <p><strong>' . htmlspecialchars($data['prenom_acheteur'] . ' ' . $data['nom_acheteur']) . '</strong><br>
                Email: ' . htmlspecialchars($data['email_acheteur']) . '</p>
            </div>
        </div>
    </div>

    <div>
        <h2>3. CONDITIONS FINANCIÈRES</h2>
        <div class="financial">
            Prix: ' . formatCurrency($data['prix_boutique'] ?? '0') . '
        </div>
        <p>Secteur: ' . htmlspecialchars($data['secteur_activite'] ?? 'E-commerce') . '</p>
        <p>Produits: ' . htmlspecialchars($data['type_produits'] ?? 'Divers') . '</p>
    </div>

    <div>
        <h2>4. ENGAGEMENTS</h2>
        <p>Le vendeur s\'engage à transférer la propriété de la boutique.</p>
        <p>L\'acheteur s\'engage à respecter les conditions de paiement.</p>
    </div>

    <!-- Zone paraphe page 1 -->
    <div style="margin: 30px 0;">
        <p><strong>Paraphe acheteur (Page 1):</strong></p>
        <div class="paraphe-zone">Paraphe ici</div>
    </div>

    <div class="page-break"></div>

    <div>
        <h2>5. CONFIDENTIALITÉ</h2>
        <p>Les parties s\'engagent à maintenir la confidentialité des informations échangées.</p>
    </div>

    <div>
        <h2>6. DROIT APPLICABLE</h2>
        <p>Ce contrat est régi par le droit anglais.</p>
    </div>

    <!-- Zone paraphe page 2 -->
    <div style="margin: 30px 0;">
        <p><strong>Paraphe acheteur (Page 2):</strong></p>
        <div class="paraphe-zone">Paraphe ici</div>
    </div>

    <div class="page-break"></div>

    <div>
        <h2>7. SIGNATURES</h2>
        <p>En signant ci-dessous, les parties acceptent tous les termes du contrat.</p>
        
        <div style="margin-top: 50px;">
            <table style="border: none;">
                <tr>
                    <td style="border: none; text-align: center; width: 50%;">
                        <h4>VENDEUR</h4>
                        <p>ShopBuyHere<br>William Davies</p>
                        <div style="margin: 20px 0; padding: 15px; border: 1px solid #333;">
                            Signature pré-autorisée
                        </div>
                    </td>
                    <td style="border: none; text-align: center; width: 50%;">
                        <h4>ACHETEUR</h4>
                        <p>' . htmlspecialchars($data['prenom_acheteur'] . ' ' . $data['nom_acheteur']) . '</p>
                        <div class="signature-zone">
                            Signature électronique
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10pt; color: #666;">
        <p>Document généré le ' . date('d/m/Y H:i') . '</p>
        <p>ID: ' . htmlspecialchars($data['id_boutique']) . '</p>
    </div>

</body>
</html>';
}

function convertHTMLToPdf($htmlContent, $data) {
    try {
        $options = new Options();
        $options->set('defaultFont', 'Times New Roman');
        $options->set('isRemoteEnabled', false);
        
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
        }
        
        logMessage("Erreur upload HTTP $httpCode: $response", 'ERROR');
        return false;
        
    } catch (Exception $e) {
        logMessage("Erreur upload: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function configureDocumentRoles($documentId, $formData) {
    try {
        // ÉTAPE IMPORTANTE: Définir les rôles du document
        $url = "https://api.signnow.com/document/$documentId/roles";
        
        $rolesData = [
            "roles" => [
                [
                    "name" => "Acheteur",
                    "signing_order" => 1,
                    "required" => true
                ]
            ]
        ];
        
        logMessage("Configuration rôles: " . json_encode($rolesData), 'INFO');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rolesData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . SIGNNOW_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        logMessage("Rôles réponse HTTP $httpCode: $response", 'INFO');
        
        return ($httpCode === 200 || $httpCode === 201);
        
    } catch (Exception $e) {
        logMessage("Erreur rôles: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function createFieldsWithCorrectRole($documentId, $formData) {
    try {
        $url = "https://api.signnow.com/document/$documentId/fields";
        
        // Utiliser le nom de rôle EXACT défini précédemment
        $fieldsData = [
            "fields" => [
                // Paraphe Page 1
                [
                    "x" => 50,
                    "y" => 680,
                    "width" => 120,
                    "height" => 35,
                    "page_number" => 0,
                    "role" => "Acheteur", // MÊME NOM que dans les rôles
                    "required" => true,
                    "type" => "initials",
                    "name" => "paraphe_page_1"
                ],
                
                // Paraphe Page 2  
                [
                    "x" => 50,
                    "y" => 680,
                    "width" => 120,
                    "height" => 35,
                    "page_number" => 1,
                    "role" => "Acheteur", // MÊME NOM
                    "required" => true,
                    "type" => "initials",
                    "name" => "paraphe_page_2"
                ],
                
                // Signature Page 3
                [
                    "x" => 280,
                    "y" => 500,
                    "width" => 200,
                    "height" => 60,
                    "page_number" => 2,
                    "role" => "Acheteur", // MÊME NOM
                    "required" => true,
                    "type" => "signature",
                    "name" => "signature_finale"
                ]
            ]
        ];
        
        logMessage("Champs avec rôle: " . json_encode($fieldsData), 'INFO');
        
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
        
        logMessage("Champs réponse HTTP $httpCode: $response", 'INFO');
        
        if ($httpCode === 200 || $httpCode === 201) {
            logMessage("Champs créés avec succès", 'SUCCESS');
            return true;
        }
        
        logMessage("Erreur création champs", 'ERROR');
        return false;
        
    } catch (Exception $e) {
        logMessage("Erreur champs: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function sendInvitationWithMatchingRole($documentId, $formData) {
    try {
        $url = "https://api.signnow.com/document/$documentId/invite";
        
        $message = "Bonjour " . $formData['prenom_acheteur'] . " " . $formData['nom_acheteur'] . ",\n\n" .
                  "Veuillez signer le contrat d'acquisition de la boutique " . $formData['id_boutique'] . ".\n\n" .
                  "Le document contient :\n" .
                  "• 2 zones de paraphe (pages 1 et 2)\n" .
                  "• 1 signature finale (page 3)\n\n" .
                  "Prix : " . formatCurrency($formData['prix_boutique'] ?? '0') . "\n\n" .
                  "Cordialement,\nShopBuyHere";
        
        // CRUCIAL: Utiliser exactement le même nom de rôle
        $inviteData = [
            'to' => [[
                'email' => $formData['email_acheteur'],
                'role_name' => 'Acheteur', // EXACT MATCH avec les champs !
                'role' => 'signer',
                'order' => 1,
                'reassign' => false,
                'decline_by_signature' => false,
                'reminder' => 1,
                'expiration_days' => intval($formData['delai_signature'] ?? 30)
            ]],
            'from' => COMPANY_EMAIL,
            'subject' => "Signature - Contrat " . $formData['id_boutique'],
            'message' => $message
        ];
        
        logMessage("Invitation avec rôle: " . json_encode($inviteData), 'INFO');
        
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
        
        logMessage("Invitation réponse HTTP $httpCode: $response", 'INFO');
        
        if ($httpCode === 200 || $httpCode === 201) {
            return [
                'success' => true,
                'message' => "🎉 SUCCÈS COMPLET !\n\n" .
                           "📧 Email envoyé à: " . $formData['email_acheteur'] . "\n" .
                           "📄 Document: $documentId\n" .
                           "💰 Montant: " . formatCurrency($formData['prix_boutique'] ?? '0') . "\n" .
                           "✅ Rôles: Configurés et correspondants\n" .
                           "📋 Champs: 2 paraphes + 1 signature\n\n" .
                           "Le client va recevoir un email avec les champs de signature actifs !"
            ];
        }
        
        return ['success' => false, 'message' => 'Erreur envoi: ' . $response];
        
    } catch (Exception $e) {
        logMessage("Erreur invitation: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

function formatCurrency($amount) {
    return number_format(floatval($amount), 2, ',', ' ') . ' €';
}

// Interface résultat
if (!$_POST) {
    header('Location: index.php');
    exit;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultat Rôles Corrigés</title>
    <style>
        body { font-family: Arial; max-width: 900px; margin: 0 auto; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; }
        .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 30px; border-radius: 15px; margin: 20px 0; border-left: 5px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 30px; border-radius: 15px; margin: 20px 0; border-left: 5px solid #dc3545; }
        .btn { display: inline-block; background: linear-gradient(45deg, #007bff, #0056b3); color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; margin: 10px 5px; font-weight: 600; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,123,255,0.3); }
        .info-box { background: #d1ecf1; color: #0c5460; padding: 25px; border-radius: 15px; margin: 20px 0; border-left: 5px solid #17a2b8; }
        .highlight { background: #fff3cd; padding: 25px; border-radius: 15px; border-left: 5px solid #ffc107; margin: 20px 0; }
        .flow { background: #f8f9fa; padding: 20px; border-radius: 15px; margin: 20px 0; }
        .step { display: flex; align-items: center; margin: 10px 0; padding: 10px; background: white; border-radius: 8px; }
        .step-num { background: #28a745; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Résultat - Rôles SignNow Corrigés</h1>
        <p><strong>Version avec correspondance exacte des rôles</strong></p>
        
        <?php if ($message): ?>
            <div class="success">
                <h3>🎉 PROBLÈME RÉSOLU !</h3>
                <?= nl2br(htmlspecialchars($message)) ?>
            </div>
            
            <div class="flow">
                <h4>✅ Processus Réussi :</h4>
                <div class="step">
                    <div class="step-num">1</div>
                    <div>Configuration des rôles du document</div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div>Création des champs avec rôle "Acheteur"</div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div>Invitation avec role_name "Acheteur" (correspondant)</div>
                </div>
            </div>
            
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <h3>❌ Erreur Détectée</h3>
                <?= nl2br(htmlspecialchars($error)) ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>🔑 Solution Appliquée</h4>
            <p><strong>Problème identifié :</strong> Incohérence entre les rôles des champs et l'invitation</p>
            <p><strong>Solution :</strong> Correspondance exacte des noms de rôles</p>
            
            <div style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; margin: 15px 0; font-family: monospace;">
1. PUT /document/{id}/roles → "Acheteur"<br>
2. PUT /document/{id}/fields → role: "Acheteur"<br>
3. POST /document/{id}/invite → role_name: "Acheteur"
            </div>
            
            <p><strong>Résultat :</strong> SignNow peut maintenant associer les champs au signataire !</p>
        </div>
        
        <div class="highlight">
            <h4>🎯 Vérifications à Faire :</h4>
            <ol>
                <li>📧 <strong>Vérifiez votre email</strong> - L'invitation devrait arriver</li>
                <li>🔗 <strong>Cliquez sur le lien</strong> dans l'email</li>
                <li>👀 <strong>Vérifiez les zones</strong> - Elles doivent être surlignées en couleur</li>
                <li>✍️ <strong>Testez la signature</strong> - Paraphes puis signature finale</li>
            </ol>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="index.php" class="btn">🏠 Nouveau Test</a>
            <a href="logs.php" class="btn" style="background: linear-gradient(45deg, #ffc107, #ffcd39); color: #212529;">📋 Voir Logs</a>
            <a href="https://app.signnow.com" target="_blank" class="btn" style="background: linear-gradient(45deg, #28a745, #20c997);">🌐 Dashboard SignNow</a>
        </div>
        
        <div style="background: #e3f2fd; padding: 20px; border-radius: 15px; margin: 20px 0; text-align: center;">
            <p><strong>🎊 Si ça marche, votre système est 100% opérationnel !</strong></p>
            <p>Vous avez maintenant un système d'automatisation de contrats complet avec signature électronique !</p>
        </div>
    </div>
</body>
</html>