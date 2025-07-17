<?php
// index.php - Interface principale avec OAuth Google + SignNow
require_once 'config.php';

// Démarrer la session pour OAuth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'autorisation OAuth est requise
$oauthRequired = false;
$tokenPath = __DIR__ . '/tokens/google_oauth_token.json';

if (!file_exists($tokenPath)) {
    $oauthRequired = true;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Génération Contrat d'Acquisition Boutique</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(45deg, #2E86AB, #A23B72);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .main-content {
            padding: 40px;
        }

        .oauth-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
            border-left: 5px solid #f39c12;
        }

        .oauth-notice h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 1.5em;
        }

        .oauth-notice p {
            color: #856404;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .form-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            border-left: 5px solid #2E86AB;
        }

        .form-section h2 {
            color: #2E86AB;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row-three {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .required {
            color: #e74c3c;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #2E86AB;
            box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
        }

        .btn {
            background: linear-gradient(45deg, #2E86AB, #A23B72);
            color: white;
            padding: 18px 35px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.2em;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 30px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(46, 134, 171, 0.4);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn.oauth {
            background: linear-gradient(45deg, #4285f4, #34a853);
        }

        .btn.secondary {
            background: linear-gradient(45deg, #6c757d, #495057);
            font-size: 1em;
            padding: 12px 20px;
            width: auto;
            margin: 10px;
        }

        .text-tags-info {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }

        .status-message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 600;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .currency-input {
            position: relative;
        }

        .currency-input::before {
            content: '€';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-weight: bold;
        }

        .help-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }

        .system-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .status-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e9ecef;
        }

        .status-card.ready {
            border-color: #28a745;
            background: #f8fff9;
        }

        .status-card.warning {
            border-color: #ffc107;
            background: #fffdf5;
        }

        .status-card.error {
            border-color: #dc3545;
            background: #fff5f5;
        }

        .status-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .form-row, .form-row-three {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .main-content {
                padding: 20px;
            }

            .system-status {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏪 Contrat d'Acquisition de Boutique</h1>
            <p>Génération Automatique avec Google Docs + SignNow</p>
        </div>

        <div class="main-content">
            <?php if ($oauthRequired): ?>
                <div class="oauth-notice">
                    <h3>🔐 Autorisation Google Requise</h3>
                    <p>Pour utiliser le système de génération de contrats, vous devez d'abord autoriser l'accès à Google Docs et Google Drive.</p>
                    <p>Cette autorisation est nécessaire pour :</p>
                    <ul style="text-align: left; margin: 15px 0; max-width: 400px; margin-left: auto; margin-right: auto;">
                        <li>✅ Créer une copie de votre modèle de contrat</li>
                        <li>✅ Remplacer automatiquement les informations client</li>
                        <li>✅ Exporter le document en PDF</li>
                        <li>✅ Envoyer via SignNow pour signature</li>
                    </ul>
                    
                    <?php
                    try {
                        require_once 'vendor/autoload.php';
                        $client = new Google\Client();
                        $client->setAuthConfig(__DIR__ . '/credentials/oauth-credentials.json');
                        $client->addScope([
                            Google\Service\Docs::DOCUMENTS,
                            Google\Service\Drive::DRIVE_FILE
                        ]);
                        $client->setRedirectUri('https://gsleads55.com/sign/oauth_callback.php');
                        $authUrl = $client->createAuthUrl();
                    } catch (Exception $e) {
                        $authUrl = 'test-oauth.php';
                    }
                    ?>
                    
                    <a href="<?= $authUrl ?>" class="btn oauth">
                        🚀 Autoriser l'Accès Google
                    </a>
                    
                    <div style="margin-top: 20px;">
                        <a href="test-oauth.php" class="btn secondary">🔍 Diagnostic Système</a>
                        <a href="logs.php" class="btn secondary">📋 Voir les Logs</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Statut du système -->
                <div class="system-status">
                    <div class="status-card ready">
                        <div class="status-icon">✅</div>
                        <h4>Google OAuth</h4>
                        <p>Autorisé</p>
                    </div>
                    <div class="status-card ready">
                        <div class="status-icon">📄</div>
                        <h4>Google Docs</h4>
                        <p>Accessible</p>
                    </div>
                    <div class="status-card ready">
                        <div class="status-icon">✍️</div>
                        <h4>SignNow</h4>
                        <p>Configuré</p>
                    </div>
                    <div class="status-card ready">
                        <div class="status-icon">🚀</div>
                        <h4>Système</h4>
                        <p>Opérationnel</p>
                    </div>
                </div>

                <div class="text-tags-info">
                    <h3>🏷️ Système Automatisé Complet</h3>
                    <p><strong>Google Docs :</strong> Création automatique du contrat à partir de votre modèle</p>
                    <p><strong>SignNow :</strong> Text Tags automatiques pour paraphes [[i|initial|req|signer1]] et signature [[s|signature|req|signer1]]</p>
                    <p><strong>Processus :</strong> Formulaire → Google Docs → PDF → SignNow → Email automatique</p>
                </div>

                <form id="contractForm" method="POST" action="backend-roles-fixed.php">
                    <input type="hidden" name="action" value="generate_contract">
                    
                    <div class="form-section">
                        <h2>🆔 Identification de la Boutique</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="id_boutique">ID Boutique <span class="required">*</span></label>
                                <input type="text" id="id_boutique" name="id_boutique" required placeholder="BTQ-001-2025">
                                <div class="help-text">Identifiant unique de la boutique</div>
                            </div>
                            <div class="form-group">
                                <label for="date_contrat">Date du Contrat <span class="required">*</span></label>
                                <input type="date" id="date_contrat" name="date_contrat" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>👤 Informations Acheteur</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nom_acheteur">Nom <span class="required">*</span></label>
                                <input type="text" id="nom_acheteur" name="nom_acheteur" required placeholder="Dupont">
                            </div>
                            <div class="form-group">
                                <label for="prenom_acheteur">Prénom <span class="required">*</span></label>
                                <input type="text" id="prenom_acheteur" name="prenom_acheteur" required placeholder="Jean">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="adresse_acheteur">Adresse Complète <span class="required">*</span></label>
                            <textarea id="adresse_acheteur" name="adresse_acheteur" rows="3" required placeholder="123 Rue de la République, 75001 Paris, France"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="telephone_acheteur">Téléphone <span class="required">*</span></label>
                                <input type="tel" id="telephone_acheteur" name="telephone_acheteur" required placeholder="+33 6 12 34 56 78">
                            </div>
                            <div class="form-group">
                                <label for="email_acheteur">Adresse Email <span class="required">*</span></label>
                                <input type="email" id="email_acheteur" name="email_acheteur" required placeholder="jean.dupont@email.com">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="piece_identite">Numéro Pièce d'Identité <span class="required">*</span></label>
                                <input type="text" id="piece_identite" name="piece_identite" required placeholder="1234567890123">
                                <div class="help-text">CNI, Passeport, ou autre document officiel</div>
                            </div>
                            <div class="form-group">
                                <label for="date_naissance">Date de Naissance <span class="required">*</span></label>
                                <input type="date" id="date_naissance" name="date_naissance" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>🏪 Détails de la Boutique</h2>
                        
                        <div class="form-group">
                            <label for="type_produits">Type de Produits Vendus <span class="required">*</span></label>
                            <textarea id="type_produits" name="type_produits" rows="2" required placeholder="Vêtements de mode, accessoires, chaussures..."></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="secteur_activite">Secteur d'Activité <span class="required">*</span></label>
                                <select id="secteur_activite" name="secteur_activite" required>
                                    <option value="">Sélectionner un secteur</option>
                                    <option value="Mode et Accessoires">Mode et Accessoires</option>
                                    <option value="Électronique et High-Tech">Électronique et High-Tech</option>
                                    <option value="Maison et Décoration">Maison et Décoration</option>
                                    <option value="Beauté et Cosmétiques">Beauté et Cosmétiques</option>
                                    <option value="Sport et Loisirs">Sport et Loisirs</option>
                                    <option value="Alimentaire et Boissons">Alimentaire et Boissons</option>
                                    <option value="Santé et Bien-être">Santé et Bien-être</option>
                                    <option value="Art et Culture">Art et Culture</option>
                                    <option value="Automobile">Automobile</option>
                                    <option value="Autre">Autre</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date_lancement">Date de Lancement <span class="required">*</span></label>
                                <input type="date" id="date_lancement" name="date_lancement" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>💰 Informations Financières</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ca_mensuel">Chiffre d'Affaires Mensuel Moyen <span class="required">*</span></label>
                                <div class="currency-input">
                                    <input type="number" id="ca_mensuel" name="ca_mensuel" step="0.01" required placeholder="15000.00">
                                </div>
                                <div class="help-text">Moyenne des 6 derniers mois</div>
                            </div>
                            <div class="form-group">
                                <label for="prix_boutique">Prix de la Boutique <span class="required">*</span></label>
                                <div class="currency-input">
                                    <input type="number" id="prix_boutique" name="prix_boutique" step="0.01" required placeholder="50000.00">
                                </div>
                                <div class="help-text">Prix d'acquisition total</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2>📧 Options d'Envoi</h2>
                        
                        <div class="form-group">
                            <label for="message_client">Message Personnalisé (optionnel)</label>
                            <textarea id="message_client" name="message_client" rows="4" placeholder="Bonjour {{prenom_acheteur}}, veuillez signer ce contrat d'acquisition en paraphant chaque page et en signant à la fin. Merci pour votre confiance."></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="copie_email">Email en Copie (optionnel)</label>
                                <input type="email" id="copie_email" name="copie_email" placeholder="manager@mpimanagetld.com">
                            </div>
                            <div class="form-group">
                                <label for="delai_signature">Délai de Signature</label>
                                <select id="delai_signature" name="delai_signature">
                                    <option value="30">30 jours (Standard)</option>
                                    <option value="15">15 jours</option>
                                    <option value="7">7 jours (Urgent)</option>
                                    <option value="3">3 jours (Très urgent)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn" id="generateBtn">
                        🚀 Générer le Contrat avec Google Docs + SignNow
                    </button>
                </form>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="test-oauth.php" class="btn secondary">🔍 Diagnostic Système</a>
                    <a href="logs.php" class="btn secondary">📋 Voir les Logs</a>
                </div>
            <?php endif; ?>

            <div id="statusMessage"></div>
        </div>
    </div>

    <script>
        // Auto-remplissage de la date du contrat
        document.getElementById('date_contrat').value = new Date().toISOString().split('T')[0];
        
        // Gestion du formulaire
        document.getElementById('contractForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('generateBtn');
            btn.innerHTML = '⏳ Génération en cours...';
            btn.disabled = true;
            
            // Validation basique
            const requiredFields = [
                'id_boutique', 'nom_acheteur', 'prenom_acheteur', 'adresse_acheteur',
                'telephone_acheteur', 'email_acheteur', 'piece_identite', 'date_naissance',
                'type_produits', 'secteur_activite', 'date_lancement', 'ca_mensuel', 'prix_boutique'
            ];
            
            for (let field of requiredFields) {
                const element = document.getElementById(field);
                if (!element || !element.value.trim()) {
                    e.preventDefault();
                    alert('Veuillez remplir tous les champs obligatoires');
                    btn.innerHTML = '🚀 Générer le Contrat avec Google Docs + SignNow';
                    btn.disabled = false;
                    return;
                }
            }
            
            // Validation email
            const email = document.getElementById('email_acheteur').value;
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Format email invalide');
                btn.innerHTML = '🚀 Générer le Contrat avec Google Docs + SignNow';
                btn.disabled = false;
                return;
            }
            
            // Validation montants
            const ca = parseFloat(document.getElementById('ca_mensuel').value);
            const prix = parseFloat(document.getElementById('prix_boutique').value);
            
            if (ca <= 0 || prix <= 0) {
                e.preventDefault();
                alert('Les montants doivent être supérieurs à 0');
                btn.innerHTML = '🚀 Générer le Contrat avec Google Docs + SignNow';
                btn.disabled = false;
                return;
            }
        });
        
        // Validation en temps réel de l'email
        document.getElementById('email_acheteur')?.addEventListener('blur', function() {
            if (this.value && !this.value.includes('@')) {
                showMessage('Format email invalide', 'error');
            }
        });
        
        // Génération automatique d'ID boutique
        document.getElementById('nom_acheteur')?.addEventListener('blur', function() {
            const idField = document.getElementById('id_boutique');
            if (!idField.value) {
                const nom = document.getElementById('nom_acheteur').value;
                const prenom = document.getElementById('prenom_acheteur').value;
                if (nom || prenom) {
                    const initials = (prenom.charAt(0) + nom.charAt(0)).toUpperCase();
                    const timestamp = Date.now().toString().slice(-4);
                    idField.value = `BTQ-${initials}-${timestamp}`;
                }
            }
        });
        
        // Fonction pour afficher les messages
        function showMessage(message, type) {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.innerHTML = `<div class="${type}">${message}</div>`;
        }
        
        // Vérification périodique du statut OAuth
        <?php if ($oauthRequired): ?>
        setInterval(() => {
            fetch('test-oauth.php?check_token=1')
                .then(response => response.json())
                .then(data => {
                    if (data.token_exists) {
                        location.reload();
                    }
                })
                .catch(() => {
                    // Ignore errors
                });
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>