<!DOCTYPE html>
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

        .preview-section {
            background: #e9ecef;
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            border: 2px dashed #2E86AB;
        }

        .contract-preview {
            background: white;
            padding: 40px;
            border-radius: 10px;
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ddd;
            font-size: 14px;
        }

        .text-tags-info {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏪 Contrat d'Acquisition de Boutique</h1>
            <p>Génération Automatique avec Text Tags SignNow</p>
        </div>

        <div class="main-content">
            <div class="text-tags-info">
                <h3>🏷️ Text Tags SignNow Intégrés</h3>
                <p><strong>Paraphes automatiques :</strong> [[i|initial|req|signer1]] sur chaque page</p>
                <p><strong>Signature finale :</strong> [[s|signature|req|signer1]] sur la dernière page</p>
                <p><strong>Ces balises seront automatiquement ajoutées</strong> lors de la génération du contrat.</p>
            </div>

            <form id="contractForm" method="POST" action="backend.php">
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
                    🚀 Générer le Contrat avec Text Tags SignNow
                </button>
            </form>

            <div class="preview-section" id="previewSection" style="display: none;">
                <h3>📖 Aperçu du Contrat Généré</h3>
                <div class="contract-preview" id="contractPreview">
                    <!-- Aperçu généré dynamiquement -->
                </div>
            </div>

            <div id="statusMessage"></div>
        </div>
    </div>

    <script>
        // Variables globales pour le processus
        let contractData = {};
        
        function generateContract() {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('contractForm'));
            contractData = Object.fromEntries(formData);
            
            // Validation
            if (!validateForm(contractData)) {
                return;
            }
            
            // Afficher l'aperçu avec Text Tags
            showPreviewWithTextTags(contractData);
            
            // Démarrer le processus de génération
            startGenerationProcess(contractData);
        }
        
        function validateForm(data) {
            const requiredFields = [
                'id_boutique', 'nom_acheteur', 'prenom_acheteur', 'adresse_acheteur',
                'telephone_acheteur', 'email_acheteur', 'piece_identite', 'date_naissance',
                'type_produits', 'secteur_activite', 'date_lancement', 'ca_mensuel', 'prix_boutique'
            ];
            
            for (let field of requiredFields) {
                if (!data[field] || data[field].trim() === '') {
                    showMessage(`Le champ "${getFieldLabel(field)}" est obligatoire`, 'error');
                    return false;
                }
            }
            
            // Validation email
            if (!data.email_acheteur.includes('@')) {
                showMessage('Format email invalide', 'error');
                return false;
            }
            
            // Validation montants
            if (parseFloat(data.ca_mensuel) <= 0 || parseFloat(data.prix_boutique) <= 0) {
                showMessage('Les montants doivent être supérieurs à 0', 'error');
                return false;
            }
            
            return true;
        }
        
        function getFieldLabel(field) {
            const labels = {
                'id_boutique': 'ID Boutique',
                'nom_acheteur': 'Nom Acheteur',
                'prenom_acheteur': 'Prénom Acheteur',
                'adresse_acheteur': 'Adresse Acheteur',
                'telephone_acheteur': 'Téléphone',
                'email_acheteur': 'Email',
                'piece_identite': 'Pièce d\'Identité',
                'date_naissance': 'Date de Naissance',
                'type_produits': 'Type de Produits',
                'secteur_activite': 'Secteur d\'Activité',
                'date_lancement': 'Date de Lancement',
                'ca_mensuel': 'CA Mensuel',
                'prix_boutique': 'Prix Boutique'
            };
            return labels[field] || field;
        }
        
        function showPreviewWithTextTags(data) {
            const preview = document.getElementById('contractPreview');
            const previewSection = document.getElementById('previewSection');
            
            // Template de contrat avec Text Tags SignNow
            const contractTemplate = `
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #2E86AB;">CONTRAT D'ACQUISITION DE BOUTIQUE E-COMMERCE</h1>
                    <p style="font-size: 18px; color: #666;">ID Boutique: <strong>${data.id_boutique}</strong></p>
                </div>
                
                <div style="margin: 30px 0; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <h3 style="color: #A23B72;">PARTIES AU CONTRAT</h3>
                    
                    <p><strong>VENDEUR :</strong><br>
                    MPI MANAGE LTD<br>
                    Société de droit anglais<br>
                    Email: support@shopbuyhere.co</p>
                    
                    <p><strong>ACHETEUR :</strong><br>
                    <strong>${data.prenom_acheteur} ${data.nom_acheteur}</strong><br>
                    Né(e) le : ${formatDate(data.date_naissance)}<br>
                    Adresse : ${data.adresse_acheteur}<br>
                    Téléphone : ${data.telephone_acheteur}<br>
                    Email : ${data.email_acheteur}<br>
                    Pièce d'identité : ${data.piece_identite}</p>
                </div>
                
                <div style="margin: 30px 0;">
                    <h3 style="color: #A23B72;">OBJET DU CONTRAT</h3>
                    <p>Le présent contrat a pour objet l'acquisition de la boutique e-commerce identifiée par l'ID <strong>${data.id_boutique}</strong>, spécialisée dans la vente de ${data.type_produits} dans le secteur ${data.secteur_activite}.</p>
                    
                    <p><strong>Date de lancement de la boutique :</strong> ${formatDate(data.date_lancement)}<br>
                    <strong>Chiffre d'affaires mensuel moyen :</strong> ${formatCurrency(data.ca_mensuel)}<br>
                    <strong>Prix d'acquisition :</strong> ${formatCurrency(data.prix_boutique)}</p>
                </div>
                
                <div style="margin: 30px 0; padding: 15px; background: #e3f2fd; border-left: 4px solid #2E86AB;">
                    <h4>📝 Instructions de Signature</h4>
                    <p><strong>1. Paraphez</strong> en bas de chaque page aux emplacements indiqués</p>
                    <p><strong>2. Signez</strong> à la fin du document dans la zone de signature finale</p>
                    <p><strong>Délai :</strong> ${data.delai_signature || 30} jours à compter de la réception</p>
                </div>
                
                <div style="margin: 40px 0; text-align: center; color: #666;">
                    <p style="border: 2px dashed #ffc107; padding: 15px; background: #fff3cd;">
                        <strong>🏷️ Text Tags SignNow</strong><br>
                        Les champs de paraphe et signature seront automatiquement ajoutés :<br>
                        <code>[[i|initial|req|signer1]]</code> sur chaque page<br>
                        <code>[[s|signature|req|signer1]]</code> pour la signature finale
                    </p>
                </div>
                
                <div style="margin-top: 50px; text-align: center;">
                    <p style="font-style: italic; color: #666;">
                        Contrat généré le ${formatDate(data.date_contrat)}<br>
                        Document légalement contraignant
                    </p>
                </div>
            `;
            
            preview.innerHTML = contractTemplate;
            previewSection.style.display = 'block';
            previewSection.scrollIntoView({ behavior: 'smooth' });
        }
        
        function startGenerationProcess(data) {
            const btn = document.getElementById('generateBtn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Génération en cours...';
            
            // Simulation du processus complet
            showMessage('📝 Génération du contrat avec placeholders...', 'info');
            
            setTimeout(() => {
                showMessage('🏷️ Ajout des Text Tags SignNow...', 'info');
                
                setTimeout(() => {
                    showMessage('📄 Conversion en PDF...', 'info');
                    
                    setTimeout(() => {
                        showMessage('📤 Upload vers SignNow...', 'info');
                        
                        setTimeout(() => {
                            showMessage('🔄 SignNow traite les Text Tags automatiquement...', 'info');
                            
                            setTimeout(() => {
                                showMessage('📧 Envoi par email à l\'acheteur...', 'info');
                                
                                setTimeout(() => {
                                    showMessage(`✅ <strong>Contrat envoyé avec succès !</strong><br>
                                    📧 L'acheteur <strong>${data.prenom_acheteur} ${data.nom_acheteur}</strong> (${data.email_acheteur}) va recevoir un email.<br>
                                    📝 Le contrat contient automatiquement :<br>
                                    • Paraphes requis sur chaque page<br>
                                    • Zone de signature finale<br>
                                    • Délai de signature : ${data.delai_signature || 30} jours<br><br>
                                    <strong>Prochaines étapes :</strong> L'acheteur paraphe et signe directement en ligne !`, 'success');
                                    
                                    btn.disabled = false;
                                    btn.innerHTML = '🎉 Contrat Envoyé ! Générer un Nouveau Contrat';
                                }, 2000);
                            }, 1500);
                        }, 1500);
                    }, 1500);
                }, 1500);
            }, 1500);
        }
        
        function showMessage(message, type) {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.innerHTML = `<div class="${type}">${message}</div>`;
        }
        
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        
        function formatCurrency(amount) {
            if (!amount) return '';
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);
        }
        
        // Auto-remplissage de la date du contrat
        document.getElementById('date_contrat').value = new Date().toISOString().split('T')[0];
        
        // Validation en temps réel
        document.getElementById('email_acheteur').addEventListener('blur', function() {
            if (this.value && !this.value.includes('@')) {
                showMessage('Format email invalide', 'error');
            }
        });
        
        // Calcul automatique d'un ID boutique si vide
        document.getElementById('nom_acheteur').addEventListener('blur', function() {
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
    </script>
</body>
</html>