<?php
// index.php - Application SignNow Complète
require_once 'config.php';

// Traitement du formulaire
$message = '';
$error = '';

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'send_contract_template':
            $result = sendContractFromTemplate($_POST);
            if ($result['success']) {
                $message = "✅ Contrat envoyé avec succès à " . htmlspecialchars($_POST['clientEmail']);
                logMessage("Contrat template envoyé à " . $_POST['clientEmail'], 'SUCCESS');
            } else {
                $error = "❌ Erreur: " . $result['message'];
                logMessage("Erreur envoi template: " . $result['message'], 'ERROR');
            }
            break;
            
        case 'upload_contract':
            $result = uploadAndSendContract($_POST, $_FILES);
            if ($result['success']) {
                $message = "✅ Contrat uploadé et envoyé avec succès à " . htmlspecialchars($_POST['clientEmail']);
                logMessage("Contrat PDF envoyé à " . $_POST['clientEmail'], 'SUCCESS');
            } else {
                $error = "❌ Erreur: " . $result['message'];
                logMessage("Erreur upload PDF: " . $result['message'], 'ERROR');
            }
            break;
    }
}

// Fonction pour envoyer un contrat depuis template
function sendContractFromTemplate($formData) {
    // Validation des données
    if (empty($formData['clientName']) || empty($formData['clientEmail'])) {
        return ['success' => false, 'message' => 'Nom et email du client requis'];
    }
    
    // Validation email
    if (!filter_var($formData['clientEmail'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format email invalide'];
    }
    
    // 1. Créer document depuis template (simulé pour cette version)
    $documentId = createDocumentFromTemplate($formData);
    if (!$documentId) {
        return ['success' => false, 'message' => 'Impossible de créer le document depuis le template'];
    }
    
    // 2. Envoyer invitation
    return sendSigningInvitation($documentId, $formData);
}

// Fonction pour créer un document depuis template
function createDocumentFromTemplate($formData) {
    $url = 'https://api.signnow.com/template/' . SIGNNOW_TEMPLATE_ID . '/copy';
    
    $data = [
        'document_name' => "Contrat_" . str_replace(' ', '_', $formData['clientName']) . "_" . date('Y-m-d_H-i-s')
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . SIGNNOW_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        $result = json_decode($response, true);
        return $result['id'] ?? null;
    }
    
    return null;
}

// Fonction pour upload PDF et envoi
function uploadAndSendContract($formData, $files) {
    if (!isset($files['pdfFile']) || $files['pdfFile']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Fichier PDF requis'];
    }
    
    // Validation du fichier
    if ($files['pdfFile']['type'] !== 'application/pdf') {
        return ['success' => false, 'message' => 'Seuls les fichiers PDF sont acceptés'];
    }
    
    if ($files['pdfFile']['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'Fichier trop volumineux (max 10MB)'];
    }
    
    // 1. Upload PDF vers SignNow
    $documentId = uploadPdfToSignNow($files['pdfFile']);
    if (!$documentId) {
        return ['success' => false, 'message' => 'Impossible d\'uploader le PDF vers SignNow'];
    }
    
    // 2. Configurer les champs de signature automatiquement
    configureSignatureFields($documentId);
    
    // 3. Envoyer invitation
    return sendSigningInvitation($documentId, $formData);
}

// Fonction pour uploader PDF vers SignNow
function uploadPdfToSignNow($file) {
    $url = 'https://api.signnow.com/document';
    
    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
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
    
    return null;
}

// Fonction pour configurer les champs de signature automatiquement
function configureSignatureFields($documentId) {
    // Configuration basique - signature sur la première page
    $url = "https://api.signnow.com/document/$documentId/fields";
    
    $fields = [
        [
            'type' => 'signature',
            'page_number' => 1,
            'x' => 300,
            'y' => 150,
            'width' => 200,
            'height' => 50,
            'required' => true,
            'name' => 'signature_client'
        ]
    ];
    
    $data = ['fields' => $fields];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . SIGNNOW_API_KEY
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}

// Fonction pour envoyer l'invitation de signature
function sendSigningInvitation($documentId, $formData) {
    $url = "https://api.signnow.com/document/$documentId/invite";
    
    $message = !empty($formData['messageClient']) ? $formData['messageClient'] : 
        "Bonjour " . $formData['clientName'] . ",\n\n" .
        "Veuillez signer ce contrat en paraphant chaque page et en signant à la fin du document.\n\n" .
        "Instructions :\n" .
        "1. Paraphez en bas de chaque page\n" .
        "2. Signez à la fin du document\n\n" .
        "Le document doit être signé dans un délai de 30 jours.\n\n" .
        "Cordialement,\n" .
        COMPANY_NAME;
    
    $data = [
        'to' => [[
            'email' => $formData['clientEmail'],
            'role_name' => 'Client',
            'role' => 'signer',
            'order' => 1,
            'reassign' => false,
            'decline_by_signature' => false,
            'reminder' => 1,
            'expiration_days' => 30
        ]],
        'from' => COMPANY_EMAIL,
        'subject' => "Signature requise - " . ($formData['contractType'] ?: 'Contrat'),
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . SIGNNOW_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        return ['success' => true, 'message' => 'Invitation envoyée avec succès'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi: ' . $response];
    }
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SignNow - Automatisation Contrats</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .main-content {
            padding: 40px;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .tab-button {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: #4CAF50;
            border-bottom: 3px solid #4CAF50;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .upload-section {
            border: 3px dashed #ddd;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .upload-section:hover {
            border-color: #4CAF50;
            background: #f0f8f0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .footer-links {
            padding: 20px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #eee;
        }

        .footer-links a {
            color: #007bff;
            text-decoration: none;
            margin: 0 15px;
            font-weight: 500;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #4CAF50;
        }

        .stat-card h3 {
            color: #4CAF50;
            font-size: 2em;
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .main-content {
                padding: 20px;
            }

            .tab-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📄 SignNow Contrats</h1>
            <p>Automatisation de signature électronique - <?= COMPANY_NAME ?></p>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
                <div class="message success"><?= $message ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?= $error ?></div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat-card">
                    <h3>✅</h3>
                    <p>Système Opérationnel</p>
                </div>
                <div class="stat-card">
                    <h3>📧</h3>
                    <p>Envoi Automatique</p>
                </div>
                <div class="stat-card">
                    <h3>🔒</h3>
                    <p>Sécurisé & Légal</p>
                </div>
            </div>

            <div class="tab-buttons">
                <button class="tab-button active" onclick="showTab('template')">📋 Utiliser Template</button>
                <button class="tab-button" onclick="showTab('upload')">📤 Upload PDF</button>
            </div>

            <!-- Template Tab -->
            <div id="template-tab" class="tab-content active">
                <h3>📋 Créer contrat depuis template SignNow</h3>
                <p>Utilise votre template pré-configuré avec paraphes et signature</p>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="send_contract_template">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientName1">Nom complet du client *</label>
                            <input type="text" id="clientName1" name="clientName" required placeholder="Jean Dupont">
                        </div>
                        <div class="form-group">
                            <label for="clientEmail1">Email du client *</label>
                            <input type="email" id="clientEmail1" name="clientEmail" required placeholder="jean.dupont@email.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientPhone1">Téléphone (optionnel)</label>
                            <input type="tel" id="clientPhone1" name="clientPhone" placeholder="+33 6 12 34 56 78">
                        </div>
                        <div class="form-group">
                            <label for="contractType1">Type de contrat</label>
                            <select id="contractType1" name="contractType">
                                <option value="">Sélectionner un type</option>
                                <option value="CDI">CDI - Contrat à durée indéterminée</option>
                                <option value="CDD">CDD - Contrat à durée déterminée</option>
                                <option value="Service">Contrat de service</option>
                                <option value="Prestation">Contrat de prestation</option>
                                <option value="Commercial">Accord commercial</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="messageClient1">Message personnalisé pour le client</label>
                        <textarea id="messageClient1" name="messageClient" rows="4" placeholder="Bonjour [Nom], veuillez signer ce contrat en paraphant chaque page et en signant à la fin. Merci."></textarea>
                    </div>

                    <button type="submit" class="btn" onclick="showLoading(this)">
                        🚀 Créer et Envoyer depuis Template
                    </button>
                </form>
            </div>

            <!-- Upload Tab -->
            <div id="upload-tab" class="tab-content">
                <h3>📤 Upload et envoyer votre PDF</h3>
                <p>Uploadez votre contrat PDF et configurez automatiquement les champs de signature</p>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_contract">
                    
                    <div class="upload-section">
                        <div style="font-size: 3em; color: #4CAF50; margin-bottom: 20px;">📁</div>
                        <h3>Sélectionnez votre contrat PDF</h3>
                        <input type="file" name="pdfFile" accept=".pdf" required style="margin-top: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <p style="margin-top: 10px; color: #666; font-size: 0.9em;">Taille max: 10MB | Format: PDF uniquement</p>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientName2">Nom complet du client *</label>
                            <input type="text" id="clientName2" name="clientName" required placeholder="Jean Dupont">
                        </div>
                        <div class="form-group">
                            <label for="clientEmail2">Email du client *</label>
                            <input type="email" id="clientEmail2" name="clientEmail" required placeholder="jean.dupont@email.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientPhone2">Téléphone (optionnel)</label>
                            <input type="tel" id="clientPhone2" name="clientPhone" placeholder="+33 6 12 34 56 78">
                        </div>
                        <div class="form-group">
                            <label for="contractType2">Type de contrat</label>
                            <select id="contractType2" name="contractType">
                                <option value="">Sélectionner un type</option>
                                <option value="CDI">CDI - Contrat à durée indéterminée</option>
                                <option value="CDD">CDD - Contrat à durée déterminée</option>
                                <option value="Service">Contrat de service</option>
                                <option value="Prestation">Contrat de prestation</option>
                                <option value="Commercial">Accord commercial</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="messageClient2">Message personnalisé pour le client</label>
                        <textarea id="messageClient2" name="messageClient" rows="4" placeholder="Bonjour [Nom], veuillez signer ce contrat. Les champs de signature ont été automatiquement configurés."></textarea>
                    </div>

                    <button type="submit" class="btn" onclick="showLoading(this)">
                        📤 Upload et Envoyer pour Signature
                    </button>
                </form>
            </div>
        </div>

        <div class="footer-links">
            <a href="test-final.php">🔍 Diagnostic Système</a>
            <a href="logs.php">📋 Voir les Logs</a>
            <a href="https://app.signnow.com" target="_blank">🌐 SignNow Dashboard</a>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Cacher tous les tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Désactiver tous les boutons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Afficher le tab sélectionné
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activer le bouton correspondant
            event.target.classList.add('active');
        }

        function showLoading(button) {
            button.innerHTML = '⏳ Traitement en cours...';
            button.disabled = true;
            
            // Réactiver après 30 secondes en cas de problème
            setTimeout(() => {
                button.disabled = false;
                if (button.innerHTML.includes('⏳')) {
                    button.innerHTML = button.innerHTML.replace('⏳ Traitement en cours...', '🚀 Réessayer');
                }
            }, 30000);
        }

        // Auto-complétion des champs dans les deux onglets
        function syncFields() {
            const fields = ['clientName', 'clientEmail', 'clientPhone'];
            
            fields.forEach(field => {
                const field1 = document.getElementById(field + '1');
                const field2 = document.getElementById(field + '2');
                
                if (field1 && field2) {
                    field1.addEventListener('input', () => {
                        field2.value = field1.value;
                    });
                    
                    field2.addEventListener('input', () => {
                        field1.value = field2.value;
                    });
                }
            });
        }

        // Initialiser la synchronisation des champs
        document.addEventListener('DOMContentLoaded', syncFields);

        // Validation des formulaires
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const email = this.querySelector('input[type="email"]').value;
                const name = this.querySelector('input[name="clientName"]').value;
                
                if (!email || !name) {
                    e.preventDefault();
                    alert('Veuillez remplir le nom et l\'email du client');
                    return false;
                }
                
                if (!email.includes('@')) {
                    e.preventDefault();
                    alert('Veuillez entrer un email valide');
                    return false;
                }
            });
        });
    </script>
</body>
</html>