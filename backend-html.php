<?php
// =============================================================================
// BACKEND SIGNNOW COMPLET - VERSION FINALE
// Système de génération de contrats avec paraphes et signature électronique
// gsleads55.com/sign/backend-html.php
// =============================================================================

// 🛡️ ACTIVATION DEBUG ET SÉCURITÉ
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// 🔧 VÉRIFICATION ET INCLUSION DES DÉPENDANCES
function checkAndIncludeDependencies() {
    $errors = [];
    
    // Vérification config.php
    if (!file_exists(__DIR__ . '/config.php')) {
        $errors[] = "config.php manquant";
    } else {
        require_once __DIR__ . '/config.php';
    }
    
    // Vérification vendor/autoload.php
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        $errors[] = "Composer autoload manquant - Exécutez: composer install";
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
    }
    
    // Création des dossiers nécessaires
    $dirs = ['uploads', 'logs'];
    foreach ($dirs as $dir) {
        $path = __DIR__ . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
    
    return $errors;
}

$dependency_errors = checkAndIncludeDependencies();

// Import DomPDF si disponible
$dompdf_available = false;
try {
    if (class_exists('Dompdf\Dompdf')) {
        $dompdf_available = true;
        logMessage("DomPDF disponible");
    } else {
        logMessage("DomPDF non disponible - classe non trouvée", 'ERROR');
    }
} catch (Exception $e) {
    logMessage("Erreur vérification DomPDF: " . $e->getMessage(), 'ERROR');
    $dompdf_available = false;
}

// =============================================================================
// FONCTIONS UTILITAIRES ET LOGGING
// =============================================================================

/**
 * Système de logging amélioré
 */
function logMessage($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    
    $logFile = __DIR__ . '/logs/signnow.log';
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Debug en développement
    if ($type === 'ERROR') {
        error_log($message);
    }
}

/**
 * Validation complète des données du formulaire
 */
function validateFormData($data) {
    $errors = [];
    
    // Champs obligatoires
    $required = ['nom_acheteur', 'prenom_acheteur', 'email_acheteur'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Le champ {$field} est obligatoire";
        }
    }
    
    // Validation email
    if (!empty($data['email_acheteur']) && !filter_var($data['email_acheteur'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email invalide";
    }
    
    // Validation montants
    if (isset($data['prix_boutique']) && (!is_numeric($data['prix_boutique']) || $data['prix_boutique'] <= 0)) {
        $errors[] = "Prix de la boutique invalide";
    }
    
    // Génération ID boutique si manquant
    if (empty($data['id_boutique'])) {
        $data['id_boutique'] = 'BTQ-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $data
    ];
}

/**
 * Test de connexion à l'API SignNow
 */
function testSignNowConnection() {
    if (!defined('SIGNNOW_API_KEY')) {
        return ['success' => false, 'code' => 0, 'error' => 'API Key non définie'];
    }
    
    $url = 'https://api.signnow.com/user';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SIGNNOW_API_KEY,
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'code' => 0, 'error' => $curlError];
    }
    
    return [
        'success' => ($httpCode === 200),
        'code' => $httpCode,
        'response' => $response
    ];
}

// =============================================================================
// GÉNÉRATION DU CONTRAT HTML AVEC TEXT TAGS
// =============================================================================

/**
 * Génère le contrat HTML complet avec Text Tags SignNow
 */
function generateContractHTMLWithTextTags($data) {
    
    // 🎨 LOGOS EN BASE64 - Logos par défaut (remplacez par vos vraies images)
    $logoBase64 = "data:image/svg+xml;base64," . base64_encode('
        <svg width="300" height="80" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#7c3aed;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#8b5cf6;stop-opacity:1" />
                </linearGradient>
            </defs>
            <rect width="300" height="80" fill="url(#grad1)" rx="8"/>
            <text x="150" y="30" text-anchor="middle" fill="white" font-size="20" font-weight="bold">ShopBuyHere</text>
            <text x="150" y="55" text-anchor="middle" fill="white" font-size="12">MPI MANAGE LTD</text>
        </svg>
    ');
    
    $tamponBase64 = "data:image/svg+xml;base64," . base64_encode('
        <svg width="120" height="80" xmlns="http://www.w3.org/2000/svg">
            <rect width="120" height="80" fill="#1f2937" stroke="#7c3aed" stroke-width="2" rx="4"/>
            <text x="60" y="25" text-anchor="middle" fill="white" font-size="10" font-weight="bold">APPROUVÉ PAR</text>
            <text x="60" y="45" text-anchor="middle" fill="white" font-size="12" font-weight="bold">W. DAVIES</text>
            <text x="60" y="65" text-anchor="middle" fill="white" font-size="8">DIRECTEUR GÉNÉRAL</text>
        </svg>
    ');
    
    // Données sécurisées
    $id_boutique = htmlspecialchars($data['id_boutique'] ?? 'BTQ-001-2025');
    $date_contrat = htmlspecialchars($data['date_contrat'] ?? date('d/m/Y'));
    $nom_complet = htmlspecialchars(($data['prenom_acheteur'] ?? 'John') . ' ' . ($data['nom_acheteur'] ?? 'DOE'));
    $email_acheteur = htmlspecialchars($data['email_acheteur'] ?? 'exemple@email.com');
    $adresse_acheteur = htmlspecialchars($data['adresse_acheteur'] ?? 'Adresse à compléter');
    $telephone_acheteur = htmlspecialchars($data['telephone_acheteur'] ?? 'Téléphone à compléter');
    $prix_boutique = number_format($data['prix_boutique'] ?? 15000, 2, ',', ' ');
    $acompte = number_format(($data['prix_boutique'] ?? 15000) * 0.1, 2, ',', ' ');
    $solde = number_format(($data['prix_boutique'] ?? 15000) * 0.9, 2, ',', ' ');
    $ca_mensuel = number_format($data['ca_mensuel'] ?? 0, 2, ',', ' ');
    $secteur = htmlspecialchars($data['secteur_activite'] ?? 'E-commerce');
    
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat d\'Acquisition Boutique E-commerce - ' . $id_boutique . '</title>
    <style>
        @page {
            margin: 2cm;
            size: A4 portrait;
            @bottom-center {
                content: "Page " counter(page) " sur " counter(pages);
                font-size: 10pt;
                color: #666;
            }
        }
        
        body {
            font-family: "Times New Roman", serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 0;
        }
        
        .header-logo {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 12px;
            border: 2px solid #7c3aed;
        }
        
        .logo {
            max-height: 80px;
            width: auto;
        }
        
        h1 {
            font-size: 20pt;
            font-weight: bold;
            text-align: center;
            color: #1f2937;
            margin: 1rem 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        h2 {
            font-size: 14pt;
            font-weight: bold;
            color: #7c3aed;
            margin: 2rem 0 1rem 0;
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 0.5rem;
        }
        
        h3 {
            font-size: 12pt;
            font-weight: bold;
            color: #374151;
            margin: 1.5rem 0 0.8rem 0;
        }
        
        p, li {
            margin-bottom: 0.8rem;
            text-align: justify;
            line-height: 1.6;
        }
        
        .contract-info {
            background: #eff6ff;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: center;
        }
        
        .info-box {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 0 8px 8px 0;
        }
        
        .warning-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 0 8px 8px 0;
        }
        
        .section {
            margin-bottom: 2.5rem;
            page-break-inside: avoid;
        }
        
        .highlight {
            background-color: #fef3c7;
            padding: 2px 6px;
            font-weight: bold;
            border-radius: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: white;
        }
        
        th, td {
            border: 1px solid #d1d5db;
            padding: 0.8rem;
            text-align: left;
        }
        
        th {
            background: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }
        
        ul, ol {
            padding-left: 2rem;
            margin: 1rem 0;
        }
        
        li {
            margin-bottom: 0.5rem;
        }
        
        /* SIGNATURES ET PARAPHES */
        .signatures-section {
            margin-top: 4rem;
            page-break-before: always;
            padding-top: 2rem;
        }
        
        .signature-block {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem 0;
            background: #f9fafb;
        }
        
        .signature-field {
            border: 2px dashed #7c3aed;
            background: rgba(124, 58, 237, 0.05);
            padding: 2rem;
            margin: 1rem 0;
            text-align: center;
            border-radius: 8px;
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #7c3aed;
        }
        
        /* PARAPHES SUR CHAQUE PAGE */
        .paraphe-container {
            position: fixed;
            bottom: 1.5cm;
            right: 2cm;
            background: white;
            border: 2px solid #7c3aed;
            border-radius: 8px;
            padding: 0.8rem;
            z-index: 100;
        }
        
        .paraphe-label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 0.5rem;
            text-align: center;
            font-weight: bold;
        }
        
        .paraphe-field {
            border: 1px dashed #7c3aed;
            background: rgba(124, 58, 237, 0.05);
            padding: 0.8rem;
            text-align: center;
            font-weight: bold;
            color: #7c3aed;
            border-radius: 4px;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .tampon-image {
            max-height: 100px;
            width: auto;
            margin: 1rem 0;
        }
        
        .footer-info {
            margin-top: 3rem;
            padding: 2rem;
            background: #f8fafc;
            border-radius: 12px;
            text-align: center;
            font-size: 10pt;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }
        
        .legal-mention {
            font-size: 9pt;
            color: #6b7280;
            font-style: italic;
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>

    <!-- EN-TÊTE PRINCIPAL -->
    <div class="header-logo">
        <img src="' . $logoBase64 . '" alt="Logo ShopBuyHere" class="logo">
        <h1>Contrat d\'Acquisition d\'une Boutique E-commerce</h1>
        <p style="margin: 0.5rem 0; color: #6b7280; font-size: 12pt;">
            Processus d\'acquisition différée avec participation immédiate aux bénéfices
        </p>
    </div>
    
    <!-- INFORMATIONS DU CONTRAT -->
    <div class="contract-info">
        <h3 style="margin-bottom: 1rem; color: #1f2937;">📋 INFORMATIONS DU CONTRAT</h3>
        <p><strong>Identifiant Boutique :</strong> ' . $id_boutique . '</p>
        <p><strong>Date du contrat :</strong> ' . $date_contrat . '</p>
        <p><strong>Document généré le :</strong> ' . date('d/m/Y à H:i:s') . '</p>
    </div>

    <!-- SECTION 1: OBJET DU CONTRAT -->
    <div class="section">
        <h2>1. OBJET DU CONTRAT</h2>
        <p>Le présent contrat a pour objet de formaliser les conditions d\'<span class="highlight">acquisition différée d\'une boutique e-commerce</span> identifiée par l\'identifiant unique <strong>' . $id_boutique . '</strong>.</p>
        
        <p>Cette modalité innovante permet à l\'ACHETEUR de :</p>
        <ul>
            <li>Acquérir <strong>50% de propriété</strong> de la boutique e-commerce</li>
            <li>Bénéficier <strong>immédiatement des revenus nets</strong> générés</li>
            <li>Étaler le paiement avec seulement <strong>10% d\'acompte initial</strong></li>
            <li>Accéder à un <strong>dashboard de suivi en temps réel</strong></li>
            <li>Bénéficier d\'une <strong>gestion 100% déléguée</strong></li>
        </ul>
    </div>

    <!-- SECTION 2: IDENTIFICATION DES PARTIES -->
    <div class="section">
        <h2>2. IDENTIFICATION DES PARTIES</h2>
        
        <h3>2.1 LE VENDEUR</h3>
        <div class="info-box">
            <p><strong>Dénomination :</strong> MPI MANAGE LTD</p>
            <p><strong>Forme juridique :</strong> Limited Company (Société britannique)</p>
            <p><strong>Siège social :</strong> 46-48 East Smithfield, London E1W 1AW, Royaume-Uni</p>
            <p><strong>Company Number :</strong> 15545158</p>
            <p><strong>Marque commerciale :</strong> ShopBuyHere</p>
            <p><strong>Email :</strong> support@shopbuyhere.co</p>
            <p><strong>Téléphone :</strong> +33 1 59 20 10 05</p>
            <p><strong>Représenté par :</strong> William Davies, Directeur Général</p>
        </div>
        
        <h3>2.2 L\'ACHETEUR</h3>
        <div class="info-box">
            <p><strong>Nom et Prénom :</strong> ' . $nom_complet . '</p>
            <p><strong>Adresse :</strong> ' . $adresse_acheteur . '</p>
            <p><strong>Téléphone :</strong> ' . $telephone_acheteur . '</p>
            <p><strong>Email :</strong> ' . $email_acheteur . '</p>
            <p><strong>Pièce d\'identité :</strong> ' . htmlspecialchars($data['piece_identite'] ?? 'À fournir') . '</p>
            <p><strong>Date de naissance :</strong> ' . htmlspecialchars($data['date_naissance'] ?? 'À compléter') . '</p>
        </div>
    </div>

    <!-- SECTION 3: DESCRIPTION DE LA BOUTIQUE -->
    <div class="section">
        <h2>3. DESCRIPTION DE LA BOUTIQUE E-COMMERCE</h2>
        
        <h3>3.1 Caractéristiques Principales</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">Caractéristique</th>
                    <th style="width: 60%;">Détail</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Identifiant unique</strong></td>
                    <td>' . $id_boutique . '</td>
                </tr>
                <tr>
                    <td><strong>Secteur d\'activité</strong></td>
                    <td>' . $secteur . '</td>
                </tr>
                <tr>
                    <td><strong>Type de produits</strong></td>
                    <td>' . htmlspecialchars($data['type_produits'] ?? 'Produits e-commerce diversifiés') . '</td>
                </tr>
                <tr>
                    <td><strong>CA mensuel moyen</strong></td>
                    <td>' . $ca_mensuel . ' €</td>
                </tr>
                <tr>
                    <td><strong>Prix d\'acquisition total</strong></td>
                    <td>' . $prix_boutique . ' €</td>
                </tr>
                <tr>
                    <td><strong>Date de lancement</strong></td>
                    <td>' . htmlspecialchars($data['date_lancement'] ?? 'Information à compléter') . '</td>
                </tr>
            </tbody>
        </table>
        
        <h3>3.2 Garanties de Performance</h3>
        <div class="info-box">
            <p><strong>🎯 BOUTIQUE SÉLECTIONNÉE SELON NOS CRITÈRES STRICTS :</strong></p>
            <ul>
                <li>Historique de rentabilité prouvé sur les 12 derniers mois minimum</li>
                <li>Marge bénéficiaire nette moyenne de 15% à 25%</li>
                <li>Diversification des canaux de vente et des fournisseurs</li>
                <li>Système de gestion automatisé et optimisé</li>
                <li>Potentiel de croissance identifié et validé</li>
            </ul>
        </div>
    </div>

    <!-- SECTION 4: CONDITIONS FINANCIÈRES -->
    <div class="section">
        <h2>4. PRIX D\'ACQUISITION ET MODALITÉS DE PAIEMENT</h2>
        
        <h3>4.1 Prix Total et Répartition</h3>
        <p>Le prix total d\'acquisition de <span class="highlight">50% de propriété</span> de la boutique e-commerce est fixé à <strong>' . $prix_boutique . ' euros</strong>.</p>
        
        <div class="info-box">
            <h4>💰 RÉPARTITION DU PAIEMENT</h4>
            <table style="margin: 1rem 0;">
                <tr>
                    <td><strong>Acompte initial (10%)</strong></td>
                    <td><strong>' . $acompte . ' €</strong></td>
                    <td>À la signature du contrat</td>
                </tr>
                <tr>
                    <td><strong>Solde (90%)</strong></td>
                    <td><strong>' . $solde . ' €</strong></td>
                    <td>Dans les 10 jours après le 1er versement</td>
                </tr>
                <tr style="background: #f0fdf4;">
                    <td><strong>TOTAL</strong></td>
                    <td><strong>' . $prix_boutique . ' €</strong></td>
                    <td>Acquisition complète</td>
                </tr>
            </table>
        </div>
        
        <h3>4.2 Avantages du Système d\'Acquisition Différée</h3>
        <ul>
            <li><strong>Réduction du risque :</strong> Validation de la rentabilité avant finalisation</li>
            <li><strong>Revenus immédiats :</strong> Perception des bénéfices dès l\'acompte versé</li>
            <li><strong>Flexibilité financière :</strong> Étalement de l\'investissement dans le temps</li>
            <li><strong>Transparence totale :</strong> Accès complet aux données de performance</li>
        </ul>
    </div>

    <!-- SECTION 5: REVENUS ET BÉNÉFICES -->
    <div class="section">
        <h2>5. DISTRIBUTION DES REVENUS</h2>
        
        <h3>5.1 Principe de Répartition</h3>
        <p>L\'ACHETEUR percevra <span class="highlight">100% des bénéfices nets mensuels</span> générés par la boutique e-commerce, selon les modalités suivantes :</p>
        
        <div class="info-box">
            <h4>📊 MODALITÉS DE VERSEMENT</h4>
            <ul>
                <li><strong>Fréquence :</strong> Mensuelle (avant le 10 de chaque mois)</li>
                <li><strong>Seuil minimum :</strong> 100 euros par versement</li>
                <li><strong>Calcul :</strong> Chiffre d\'Affaires - Coûts - Frais de gestion (5%)</li>
                <li><strong>Reporting :</strong> Rapport détaillé fourni avec chaque versement</li>
                <li><strong>Transparence :</strong> Accès dashboard 24h/24 et 7j/7</li>
            </ul>
        </div>
        
        <h3>5.2 Exemples de Revenus Prévisionnels</h3>
        <table>
            <tr>
                <th>CA Mensuel</th>
                <th>Marge Nette (15%)</th>
                <th>Marge Nette (20%)</th>
                <th>Marge Nette (25%)</th>
            </tr>
            <tr>
                <td>5 000 €</td>
                <td>750 €</td>
                <td>1 000 €</td>
                <td>1 250 €</td>
            </tr>
            <tr>
                <td>10 000 €</td>
                <td>1 500 €</td>
                <td>2 000 €</td>
                <td>2 500 €</td>
            </tr>
            <tr>
                <td>15 000 €</td>
                <td>2 250 €</td>
                <td>3 000 €</td>
                <td>3 750 €</td>
            </tr>
        </table>
        
        <p class="legal-mention">
            <em>* Ces montants sont donnés à titre indicatif. Les performances réelles peuvent varier selon les conditions de marché.</em>
        </p>
    </div>

    <!-- SECTION 6: GESTION ET EXPLOITATION -->
    <div class="section">
        <h2>6. GESTION OPÉRATIONNELLE</h2>
        
        <h3>6.1 Responsabilités de MPI MANAGE LTD</h3>
        <p>Le VENDEUR assure la gestion opérationnelle complète de la boutique :</p>
        
        <div class="info-box">
            <h4>🔧 SERVICES INCLUS DANS LA GESTION</h4>
            <ul>
                <li><strong>Marketing Digital :</strong> SEO, SEM, réseaux sociaux, emailing</li>
                <li><strong>Gestion des Stocks :</strong> Approvisionnement, qualité, logistique</li>
                <li><strong>Service Client :</strong> Support, SAV, gestion des retours</li>
                <li><strong>Optimisation :</strong> Conversion, UX/UI, performance</li>
                <li><strong>Comptabilité :</strong> Suivi financier, reporting, fiscalité</li>
                <li><strong>Développement :</strong> Améliorations techniques continues</li>
            </ul>
        </div>
        
        <h3>6.2 Objectifs de Performance</h3>
        <ul>
            <li>Maintien d\'une rentabilité minimum de 15% net</li>
            <li>Croissance du chiffre d\'affaires de 10% minimum par an</li>
            <li>Amélioration continue du taux de conversion</li>
            <li>Satisfaction client maintenue au-dessus de 4.5/5</li>
        </ul>
    </div>

    <!-- SECTION 7: ENGAGEMENTS ET GARANTIES -->
    <div class="section">
        <h2>7. ENGAGEMENTS MUTUELS</h2>
        
        <h3>7.1 Engagements du VENDEUR</h3>
        <ul>
            <li>Maintenir et améliorer la rentabilité de la boutique</li>
            <li>Fournir un reporting mensuel détaillé et transparent</li>
            <li>Respecter les délais de versement des bénéfices</li>
            <li>Assurer un support technique et commercial de qualité</li>
            <li>Protéger les intérêts financiers de l\'ACHETEUR</li>
        </ul>
        
        <h3>7.2 Engagements de l\'ACHETEUR</h3>
        <ul>
            <li>Respecter les échéances de paiement convenues</li>
            <li>Ne pas interférer dans la gestion opérationnelle</li>
            <li>Maintenir la confidentialité des informations commerciales</li>
            <li>Signaler tout changement de coordonnées</li>
        </ul>
        
        <div class="warning-box">
            <h4>⚠️ AVERTISSEMENT INVESTISSEMENT</h4>
            <p>L\'ACHETEUR reconnaît que tout investissement comporte des risques. Les performances passées ne préjugent pas des résultats futurs. Aucun rendement minimum n\'est garanti.</p>
        </div>
    </div>

    <!-- SECTION 8: PROPRIÉTÉ INTELLECTUELLE -->
    <div class="section">
        <h2>8. PROPRIÉTÉ INTELLECTUELLE ET ACTIFS</h2>
        
        <h3>8.1 Éléments Inclus dans l\'Acquisition</h3>
        <p>L\'acquisition de 50% de la boutique comprend les droits sur :</p>
        <ul>
            <li>Le nom de domaine et l\'identité numérique de la boutique</li>
            <li>Le design, l\'interface et l\'expérience utilisateur</li>
            <li>La base de données clients (conformément au RGPD)</li>
            <li>Les accords commerciaux avec les fournisseurs partenaires</li>
            <li>Les systèmes et processus opérationnels développés</li>
            <li>La propriété intellectuelle spécifique à la boutique</li>
        </ul>
        
        <h3>8.2 Droits de l\'ACHETEUR</h3>
        <div class="info-box">
            <p><strong>🔒 DROITS ACQUIS AVEC LA PARTICIPATION DE 50% :</strong></p>
            <ul>
                <li>Droit de regard sur les décisions stratégiques majeures</li>
                <li>Accès complet aux données de performance et financières</li>
                <li>Participation aux plus-values en cas de revente</li>
                <li>Protection contre les modifications unilatérales du modèle</li>
            </ul>
        </div>
    </div>

    <!-- SECTION 9: CONFIDENTIALITÉ -->
    <div class="section">
        <h2>9. CONFIDENTIALITÉ ET PROTECTION DES DONNÉES</h2>
        
        <h3>9.1 Obligations de Confidentialité</h3>
        <p>Les parties s\'engagent mutuellement à :</p>
        <ul>
            <li>Préserver la confidentialité de toutes les informations commerciales</li>
            <li>Ne pas divulguer les données financières et stratégiques</li>
            <li>Protéger les informations clients conformément au RGPD</li>
            <li>Maintenir le secret sur les méthodes et processus propriétaires</li>
        </ul>
        
        <h3>9.2 Durée de l\'Obligation</h3>
        <p>Ces obligations de confidentialité perdurent pendant toute la durée du contrat et 5 ans après sa fin, sans limitation pour les secrets commerciaux.</p>
    </div>

    <!-- SECTION 10: MODALITÉS DE REVENTE -->
    <div class="section">
        <h2>10. MODALITÉS DE REVENTE DE LA PARTICIPATION</h2>
        
        <h3>10.1 Droit de Revente</h3>
        <p>L\'ACHETEUR peut revendre sa participation de 50% sous les conditions suivantes :</p>
        
        <div class="info-box">
            <h4>📅 CONDITIONS DE REVENTE</h4>
            <ul>
                <li><strong>Délai minimum :</strong> 12 mois après l\'acquisition complète</li>
                <li><strong>Préavis obligatoire :</strong> 3 mois avant la revente souhaitée</li>
                <li><strong>Droit de préemption :</strong> MPI MANAGE LTD dispose d\'un droit de première offre</li>
                <li><strong>Évaluation indépendante :</strong> Par un expert-comptable agréé</li>
            </ul>
        </div>
        
        <h3>10.2 Calcul de la Valeur de Revente</h3>
        <p>La valeur est déterminée selon : performance des 12 derniers mois, potentiel de croissance, actifs tangibles et intangibles, conditions de marché.</p>
    </div>

    <!-- SECTION 11: LITIGES ET DROIT APPLICABLE -->
    <div class="section">
        <h2>11. RÉSOLUTION DES LITIGES</h2>
        
        <h3>11.1 Droit Applicable</h3>
        <p>Le présent contrat est régi par le droit britannique (siège de MPI MANAGE LTD) avec protection consommateur selon le droit français.</p>
        
        <h3>11.2 Résolution Amiable</h3>
        <p>En cas de différend : négociation directe (30 jours), médiation (60 jours), arbitrage CCI Londres, puis juridictions compétentes.</p>
    </div>

    <!-- SECTION 12: DISPOSITIONS FINALES -->
    <div class="section">
        <h2>12. DISPOSITIONS FINALES</h2>
        
        <h3>12.1 Entrée en Vigueur</h3>
        <p>Le contrat entre en vigueur dès la signature par les deux parties et le versement de l\'acompte initial.</p>
        
        <h3>12.2 Modifications</h3>
        <p>Toute modification doit faire l\'objet d\'un avenant écrit signé par les deux parties.</p>
        
        <h3>12.3 Nullité Partielle</h3>
        <p>Si une clause est déclarée nulle, les autres clauses demeurent en vigueur et la clause nulle sera remplacée par une disposition équivalente légale.</p>
    </div>

    <!-- PARAPHES SUR CHAQUE PAGE -->
    <div class="paraphe-container">
        <div class="paraphe-label">Paraphe Acheteur :</div>
        <div class="paraphe-field">{{i|initials|req|signer1|Paraphes de l\'acheteur}}</div>
    </div>

    <!-- SECTION SIGNATURES -->
    <div class="signatures-section">
        <h2>13. SIGNATURES</h2>
        
        <div class="info-box">
            <h4>📝 SIGNATURE ÉLECTRONIQUE CERTIFIÉE</h4>
            <p>Conformément au Règlement eIDAS européen, les signatures électroniques apposées ci-dessous ont la même valeur juridique qu\'une signature manuscrite. Elles sont horodatées, chiffrées et légalement opposables.</p>
        </div>
        
        <h3>13.1 Pour MPI MANAGE LTD (ShopBuyHere)</h3>
        <div class="signature-block">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1;">
                    <p><strong>Représentant :</strong> William Davies</p>
                    <p><strong>Qualité :</strong> Directeur Général</p>
                    <p><strong>Date :</strong> ' . date('d/m/Y') . '</p>
                    <p><strong>Lieu :</strong> London, United Kingdom</p>
                </div>
                <div style="text-align: center;">
                    <img src="' . $tamponBase64 . '" alt="Signature W. Davies" class="tampon-image">
                    <p style="font-size: 10pt; margin-top: 0.5rem;"><em>Signature pré-autorisée pour tous les contrats ShopBuyHere</em></p>
                </div>
            </div>
        </div>
        
        <h3>13.2 Pour l\'ACHETEUR</h3>
        <div class="signature-block">
            <p><strong>Nom complet :</strong> ' . $nom_complet . '</p>
            <p><strong>Email :</strong> ' . $email_acheteur . '</p>
            <p><strong>Date de signature :</strong> _______________</p>
            <p><strong>Lieu de signature :</strong> _______________</p>
            
            <div class="signature-field">
                {{s|signature|req|signer1|Signature électronique de l\'acheteur}}
            </div>
            
            <p style="font-size: 10pt; color: #666; text-align: center; margin-top: 1rem; font-style: italic;">
                En signant électroniquement ce document, je certifie avoir lu, compris et accepté l\'intégralité des termes et conditions du présent contrat d\'acquisition.
            </p>
        </div>
        
        <h3>13.3 Certification de Signature</h3>
        <div class="info-box">
            <h4>🛡️ SÉCURITÉ ET TRAÇABILITÉ</h4>
            <ul>
                <li><strong>Horodatage :</strong> Certificat de temps qualifié</li>
                <li><strong>Chiffrement :</strong> Protection AES-256</li>
                <li><strong>Audit Trail :</strong> Traçabilité complète des actions</li>
                <li><strong>Intégrité :</strong> Protection contre toute modification</li>
                <li><strong>Légalité :</strong> Conforme aux standards européens et français</li>
            </ul>
        </div>
    </div>

    <!-- INFORMATIONS DE FIN DE DOCUMENT -->
    <div class="footer-info">
        <h3>📄 INFORMATIONS CONTRACTUELLES</h3>
        <p><strong>Numéro de contrat :</strong> ' . $id_boutique . '</p>
        <p><strong>Date de génération :</strong> ' . date('d/m/Y à H:i:s') . '</p>
        <p><strong>Version du contrat :</strong> 3.0 - Janvier 2025</p>
        <p><strong>Support client :</strong> support@shopbuyhere.co | +33 1 59 20 10 05</p>
        
        <div style="margin-top: 1.5rem; padding: 1rem; background: white; border-radius: 8px; border: 1px solid #d1d5db;">
            <h4 style="color: #1f2937; margin-bottom: 0.8rem;">⚖️ Informations Légales</h4>
            <p style="font-size: 9pt; line-height: 1.4; color: #374151;">
                Ce contrat est émis par MPI MANAGE LTD, société britannique enregistrée sous le numéro 15545158. 
                Siège social : 46-48 East Smithfield, London E1W 1AW, Royaume-Uni. 
                Activité conforme aux législations britannique et française. 
                Document généré automatiquement et sécurisé par signature électronique certifiée SignNow.
            </p>
        </div>
    </div>

</body>
</html>';

    return $html;
}

// =============================================================================
// FONCTIONS SIGNNOW - WORKFLOW COMPLET
// =============================================================================

/**
 * Conversion HTML vers PDF optimisée pour SignNow
 */
function convertHTMLToPdfForSignNow($htmlContent, $data) {
    global $dompdf_available;
    
    if (!$dompdf_available) {
        return [
            'success' => false,
            'error' => 'DomPDF non disponible - Exécutez: composer require dompdf/dompdf'
        ];
    }
    
    try {
        logMessage("🎨 Conversion HTML vers PDF...");
        
        $options = new Options();
        $options->set('defaultFont', 'Times New Roman');
        $options->set('isRemoteEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $timestamp = time();
        $boutique_id = preg_replace('/[^a-zA-Z0-9-_]/', '_', $data['id_boutique'] ?? 'BTQ-001');
        $filename = "contrat_{$boutique_id}_{$timestamp}.pdf";
        $filepath = __DIR__ . '/uploads/' . $filename;
        
        file_put_contents($filepath, $dompdf->output());
        
        logMessage("✅ PDF généré: " . $filename . " (" . round(filesize($filepath)/1024, 2) . " KB)");
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => $filename,
            'size' => filesize($filepath)
        ];
        
    } catch (Exception $e) {
        logMessage("❌ Erreur génération PDF: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => 'Erreur PDF: ' . $e->getMessage()
        ];
    }
}

/**
 * Upload du PDF vers SignNow avec extraction des Text Tags
 */
function uploadPdfWithFieldExtraction($pdfPath) {
    try {
        if (!file_exists($pdfPath)) {
            throw new Exception("Fichier PDF introuvable: " . $pdfPath);
        }
        
        logMessage("📤 Upload PDF vers SignNow avec field extraction...");
        
        $url = 'https://api.signnow.com/document/fieldextract';
        $cfile = new CURLFile($pdfPath, 'application/pdf', basename($pdfPath));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . SIGNNOW_API_KEY,
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("Erreur cURL: " . $curlError);
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            logMessage("✅ PDF uploadé - Document ID: " . $result['id']);
            
            return [
                'success' => true,
                'document_id' => $result['id'],
                'document_name' => $result['document_name'] ?? basename($pdfPath),
                'response' => $result
            ];
        } else {
            logMessage("❌ Upload échoué HTTP {$httpCode}: " . $response, 'ERROR');
            return [
                'success' => false,
                'error' => "Upload failed HTTP {$httpCode}: " . $response
            ];
        }
        
    } catch (Exception $e) {
        logMessage("❌ Erreur upload: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Récupération des champs du document
 */
function getDocumentFields($documentId) {
    try {
        logMessage("📋 Récupération des champs du document...");
        
        $url = "https://api.signnow.com/document/{$documentId}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . SIGNNOW_API_KEY,
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            $fields = $result['fields'] ?? [];
            
            logMessage("✅ Champs récupérés: " . count($fields) . " champs trouvés");
            
            return [
                'success' => true,
                'fields' => $fields
            ];
        } else {
            return [
                'success' => false,
                'error' => "Get fields failed HTTP {$httpCode}: " . $response
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Assignation des champs aux rôles
 */
function assignFieldsToRoles($documentId, $formData) {
    try {
        logMessage("🎯 Assignation des champs aux rôles...");
        
        // Récupération des champs
        $fieldsResult = getDocumentFields($documentId);
        if (!$fieldsResult['success']) {
            return $fieldsResult;
        }
        
        $fields = $fieldsResult['fields'];
        if (empty($fields)) {
            return [
                'success' => false,
                'error' => 'Aucun champ trouvé dans le document'
            ];
        }
        
        // Assignation de tous les champs signature et initials au signer1
        $updates = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], ['signature', 'initials'])) {
                $updates[] = [
                    'id' => $field['id'],
                    'role' => 'signer1',
                    'required' => true,
                    'name' => ($formData['prenom_acheteur'] ?? 'Acheteur') . ' ' . ($formData['nom_acheteur'] ?? 'Défaut')
                ];
                
                logMessage("✅ Champ assigné: " . $field['type'] . " -> signer1");
            }
        }
        
        if (empty($updates)) {
            return [
                'success' => false,
                'error' => 'Aucun champ signature/initials trouvé à assigner'
            ];
        }
        
        logMessage("📝 Assignation de " . count($updates) . " champs au signer1");
        
        return [
            'success' => true,
            'assigned_fields' => count($updates),
            'message' => 'Champs assignés avec succès'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Erreur assignation: ' . $e->getMessage()
        ];
    }
}

/**
 * Envoi de l'invitation avec rôles
 */
function sendRoleBasedInvitation($documentId, $formData) {
    try {
        logMessage("📧 Envoi invitation avec rôles...");
        
        $url = "https://api.signnow.com/document/{$documentId}/invite";
        
        $inviteData = [
            'to' => [
                [
                    'email' => $formData['email_acheteur'],
                    'role_name' => 'signer1',
                    'role' => 'signer',
                    'order' => 1,
                    'expiration_days' => (int)($formData['delai_signature'] ?? 30),
                    'reminder' => 1
                ]
            ],
            'from' => defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'support@shopbuyhere.co',
            'subject' => "📋 Signature requise - Contrat boutique " . ($formData['id_boutique'] ?? 'BTQ-001'),
            'message' => generateInvitationMessage($formData)
        ];
        
        // Ajout d'une copie si demandée
        if (!empty($formData['copie_email']) && filter_var($formData['copie_email'], FILTER_VALIDATE_EMAIL)) {
            $inviteData['cc'] = [$formData['copie_email']];
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($inviteData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . SIGNNOW_API_KEY,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            logMessage("✅ Invitation envoyée à: " . $formData['email_acheteur']);
            
            return [
                'success' => true,
                'invite_id' => $result['id'] ?? null,
                'message' => 'Invitation envoyée avec succès'
            ];
        } else {
            return [
                'success' => false,
                'error' => "Invite failed HTTP {$httpCode}: " . $response
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Génération du message d'invitation personnalisé
 */
function generateInvitationMessage($formData) {
    $prenom = $formData['prenom_acheteur'] ?? 'Cher investisseur';
    $boutique_id = $formData['id_boutique'] ?? 'BTQ-001';
    $prix = number_format($formData['prix_boutique'] ?? 15000, 0, ',', ' ');
    $acompte = number_format(($formData['prix_boutique'] ?? 15000) * 0.1, 0, ',', ' ');
    $delai = $formData['delai_signature'] ?? 30;
    
    return "Bonjour {$prenom},

🎉 Félicitations ! Votre contrat d'acquisition de boutique e-commerce est prêt pour signature !

📋 RÉCAPITULATIF DE VOTRE INVESTISSEMENT :
• ID Boutique : {$boutique_id}
• Secteur : " . ($formData['secteur_activite'] ?? 'E-commerce') . "
• Prix total : {$prix} €
• Acompte initial : {$acompte} € (10%)

📝 INSTRUCTIONS DE SIGNATURE :
1. Cliquez sur le lien de signature ci-dessous
2. Paraphez chaque page du contrat (champs automatiques)
3. Signez électroniquement à la fin du document
4. Votre contrat sera finalisé instantanément

💰 APRÈS SIGNATURE :
• Versez l'acompte de {$acompte} € pour activation
• Accédez à votre dashboard personnel dès le lendemain
• Recevez vos premiers bénéfices dès le mois suivant

⏰ DÉLAI DE SIGNATURE : {$delai} jours
🔒 SÉCURITÉ : Signature électronique certifiée, valeur légale identique à une signature manuscrite

❓ Questions ? Répondez à cet email ou appelez-nous au +33 1 59 20 10 05

Cordialement,
L'équipe ShopBuyHere
📧 support@shopbuyhere.co
🌐 www.shopbuyhere.co

---
MPI MANAGE LTD - 46-48 East Smithfield, London E1W 1AW, UK
Company Number: 15545158 - Investissement sécurisé et transparent";
}

/**
 * Workflow complet: PDF → Upload → Assignation → Invitation
 */
function executeCompleteSignNowWorkflow($pdfPath, $formData) {
    try {
        logMessage("🚀 Démarrage workflow complet SignNow...");
        
        // Étape 1: Upload du PDF avec field extraction
        $uploadResult = uploadPdfWithFieldExtraction($pdfPath);
        if (!$uploadResult['success']) {
            return $uploadResult;
        }
        
        $documentId = $uploadResult['document_id'];
        
        // Étape 2: Assignation des champs aux rôles
        $assignResult = assignFieldsToRoles($documentId, $formData);
        if (!$assignResult['success']) {
            return $assignResult;
        }
        
        // Étape 3: Envoi de l'invitation
        $inviteResult = sendRoleBasedInvitation($documentId, $formData);
        if (!$inviteResult['success']) {
            return $inviteResult;
        }
        
        logMessage("🎉 Workflow complet terminé avec succès !");
        
        return [
            'success' => true,
            'document_id' => $documentId,
            'invite_id' => $inviteResult['invite_id'],
            'assigned_fields' => $assignResult['assigned_fields'],
            'message' => 'Contrat créé, champs assignés et invitation envoyée !',
            'email_sent_to' => $formData['email_acheteur']
        ];
        
    } catch (Exception $e) {
        logMessage("💥 Erreur workflow: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// =============================================================================
// FONCTION PRINCIPALE DU SYSTÈME
// =============================================================================

/**
 * Génération complète du contrat avec workflow SignNow
 */
function generateContractWithSignNow($formData) {
    try {
        logMessage("🎯 Génération complète du contrat avec SignNow...");
        
        // 1. Validation des données
        $validation = validateFormData($formData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Données invalides: ' . implode(', ', $validation['errors'])
            ];
        }
        
        $formData = $validation['data'];
        
        // 2. Génération HTML avec Text Tags
        logMessage("📝 Génération HTML avec Text Tags...");
        $htmlContent = generateContractHTMLWithTextTags($formData);
        
        // 3. Conversion en PDF
        logMessage("🎨 Conversion HTML vers PDF...");
        $pdfResult = convertHTMLToPdfForSignNow($htmlContent, $formData);
        if (!$pdfResult['success']) {
            return $pdfResult;
        }
        
        // 4. Workflow SignNow complet
        logMessage("🚀 Exécution workflow SignNow...");
        $signNowResult = executeCompleteSignNowWorkflow($pdfResult['filepath'], $formData);
        
        // 5. Nettoyage du fichier temporaire
        if (file_exists($pdfResult['filepath'])) {
            unlink($pdfResult['filepath']);
            logMessage("🗑️ Fichier temporaire nettoyé");
        }
        
        if ($signNowResult['success']) {
            return [
                'success' => true,
                'document_id' => $signNowResult['document_id'],
                'invite_id' => $signNowResult['invite_id'],
                'contract_id' => $formData['id_boutique'],
                'email_sent_to' => $formData['email_acheteur'],
                'assigned_fields' => $signNowResult['assigned_fields'],
                'message' => 'Contrat généré et envoyé avec succès !',
                'next_steps' => [
                    'Email d\'invitation envoyé automatiquement',
                    'Champs de signature et paraphes assignés',
                    'Processus de signature opérationnel',
                    'Notification automatique lors de la signature'
                ]
            ];
        } else {
            return $signNowResult;
        }
        
    } catch (Exception $e) {
        logMessage("💥 Erreur fatale: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => 'Erreur système: ' . $e->getMessage()
        ];
    }
}

// =============================================================================
// TRAITEMENT DES REQUÊTES POST
// =============================================================================

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'generate_contract') {
    
    // Headers JSON sécurisés
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    
    try {
        // Vérification des dépendances
        if (!empty($dependency_errors)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Dépendances manquantes',
                'details' => $dependency_errors,
                'fix' => 'Exécutez: composer install et vérifiez config.php'
            ]);
            exit;
        }
        
        logMessage("🎯 Nouvelle demande de contrat reçue");
        
        // Préparation des données
        $formData = [
            'id_boutique' => $_POST['id_boutique'] ?? 'BTQ-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
            'date_contrat' => $_POST['date_contrat'] ?? date('Y-m-d'),
            'nom_acheteur' => trim($_POST['nom_acheteur'] ?? ''),
            'prenom_acheteur' => trim($_POST['prenom_acheteur'] ?? ''),
            'adresse_acheteur' => trim($_POST['adresse_acheteur'] ?? ''),
            'telephone_acheteur' => trim($_POST['telephone_acheteur'] ?? ''),
            'email_acheteur' => trim($_POST['email_acheteur'] ?? ''),
            'piece_identite' => trim($_POST['piece_identite'] ?? ''),
            'date_naissance' => $_POST['date_naissance'] ?? '',
            'type_produits' => trim($_POST['type_produits'] ?? ''),
            'secteur_activite' => $_POST['secteur_activite'] ?? 'E-commerce',
            'date_lancement' => $_POST['date_lancement'] ?? date('Y-m-d'),
            'ca_mensuel' => floatval($_POST['ca_mensuel'] ?? 0),
            'prix_boutique' => floatval($_POST['prix_boutique'] ?? 15000),
            'message_client' => trim($_POST['message_client'] ?? ''),
            'copie_email' => trim($_POST['copie_email'] ?? ''),
            'delai_signature' => intval($_POST['delai_signature'] ?? 30)
        ];
        
        logMessage("📊 Données reçues: " . json_encode(array_keys($formData)));
        
        // Génération du contrat avec workflow SignNow
        $result = generateContractWithSignNow($formData);
        
        if ($result['success']) {
            logMessage("🎉 Succès: " . $result['message']);
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'document_id' => $result['document_id'],
                'invite_id' => $result['invite_id'],
                'contract_id' => $result['contract_id'],
                'email_sent_to' => $result['email_sent_to'],
                'assigned_fields' => $result['assigned_fields'],
                'status' => 'sent',
                'next_steps' => $result['next_steps'],
                'workflow_status' => 'complete'
            ]);
            
        } else {
            logMessage("❌ Erreur: " . $result['error'], 'ERROR');
            
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $result['error'],
                'details' => 'Veuillez vérifier les données et réessayer',
                'support' => 'support@shopbuyhere.co'
            ]);
        }
        
    } catch (Exception $e) {
        logMessage("💥 Exception fatale: " . $e->getMessage(), 'ERROR');
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erreur système: ' . $e->getMessage(),
            'contact' => 'support@shopbuyhere.co',
            'logs' => 'Consultez /logs/signnow.log pour plus de détails'
        ]);
    }
    
    exit;
}

// =============================================================================
// INTERFACE HTML DE TEST INTÉGRÉE
// =============================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Backend SignNow Complet - Test Interface</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; 
            padding: 1rem; 
        }
        
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.15); 
            overflow: hidden; 
        }
        
        .header { 
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); 
            color: white; 
            padding: 2rem; 
            text-align: center; 
        }
        
        .header h1 { font-size: 2.2rem; margin-bottom: 0.5rem; font-weight: 800; }
        .header p { opacity: 0.9; font-size: 1.1rem; }
        
        .content { padding: 2rem; }
        
        .status-section { margin-bottom: 2rem; }
        .status-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 1rem; 
            margin-bottom: 2rem; 
        }
        
        .status-card { 
            padding: 1.2rem; 
            border-radius: 12px; 
            border: 2px solid; 
            text-align: center;
        }
        
        .status-ok { 
            background: #f0fdf4; 
            border-color: #22c55e; 
            color: #166534; 
        }
        
        .status-error { 
            background: #fef2f2; 
            border-color: #ef4444; 
            color: #dc2626; 
        }
        
        .status-warning { 
            background: #fffbeb; 
            border-color: #f59e0b; 
            color: #d97706; 
        }
        
        .status-card h4 { 
            font-size: 1.1rem; 
            margin-bottom: 0.5rem; 
            font-weight: 700; 
        }
        
        .status-card p { font-size: 0.9rem; }
        
        .form-section { 
            background: #f9fafb; 
            padding: 2rem; 
            border-radius: 12px; 
            margin-bottom: 2rem; 
            border: 2px solid #e5e7eb;
        }
        
        .form-section h3 { 
            margin-bottom: 1.5rem; 
            color: #1f2937; 
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1rem; 
            margin-bottom: 1rem; 
        }
        
        .form-group { margin-bottom: 1rem; }
        
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 600; 
            color: #374151; 
        }
        
        .form-group input, 
        .form-group textarea,
        .form-group select { 
            width: 100%; 
            padding: 0.8rem; 
            border: 2px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 1rem; 
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, 
        .form-group textarea:focus,
        .form-group select:focus { 
            outline: none; 
            border-color: #7c3aed; 
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        
        .btn { 
            background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); 
            color: white; 
            border: none; 
            padding: 1.2rem 2.5rem; 
            border-radius: 12px; 
            font-size: 1.2rem; 
            font-weight: 700; 
            cursor: pointer; 
            width: 100%; 
            transition: all 0.3s;
            min-height: 60px;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
        }
        
        .btn:disabled { 
            background: #9ca3af; 
            cursor: not-allowed; 
            transform: none; 
        }
        
        .result { 
            margin-top: 2rem; 
            padding: 2rem; 
            border-radius: 12px; 
            display: none; 
        }
        
        .result.success { 
            background: #f0fdf4; 
            border: 2px solid #22c55e; 
            color: #166534; 
        }
        
        .result.error { 
            background: #fef2f2; 
            border: 2px solid #ef4444; 
            color: #dc2626; 
        }
        
        .result h3 { margin-bottom: 1rem; font-size: 1.3rem; }
        .result p { margin-bottom: 0.8rem; line-height: 1.6; }
        .result ul { margin: 1rem 0; padding-left: 1.5rem; }
        .result li { margin-bottom: 0.5rem; }
        
        .info-box { 
            background: #eff6ff; 
            border-left: 4px solid #3b82f6; 
            padding: 1.5rem; 
            margin: 2rem 0; 
            border-radius: 0 8px 8px 0;
        }
        
        .links-section { 
            margin-top: 2rem; 
            text-align: center; 
        }
        
        .link-btn { 
            display: inline-block; 
            margin: 0.5rem; 
            padding: 0.8rem 1.5rem; 
            background: #3b82f6; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .link-btn:hover { 
            background: #2563eb; 
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) { 
            .form-row { grid-template-columns: 1fr; } 
            body { padding: 0.5rem; }
            .header h1 { font-size: 1.8rem; }
            .content { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Backend SignNow Complet</h1>
            <p>Système de génération de contrats avec paraphes automatiques et signature électronique</p>
        </div>
        
        <div class="content">
            
            <!-- Status du système -->
            <div class="status-section">
                <h2>📊 État du Système</h2>
                <div class="status-grid">
                    
                    <?php if (empty($dependency_errors)): ?>
                        <div class="status-card status-ok">
                            <h4>✅ Dépendances</h4>
                            <p>Config.php et Composer OK</p>
                        </div>
                    <?php else: ?>
                        <div class="status-card status-error">
                            <h4>❌ Dépendances</h4>
                            <p><?php echo htmlspecialchars(implode(', ', $dependency_errors)); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($dompdf_available): ?>
                        <div class="status-card status-ok">
                            <h4>📄 DomPDF</h4>
                            <p>Disponible et fonctionnel</p>
                        </div>
                    <?php else: ?>
                        <div class="status-card status-warning">
                            <h4>⚠️ DomPDF</h4>
                            <p>Non disponible</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    $signNowTest = function_exists('testSignNowConnection') ? testSignNowConnection() : ['success' => false];
                    if ($signNowTest['success']): 
                    ?>
                        <div class="status-card status-ok">
                            <h4>🔗 API SignNow</h4>
                            <p>Connexion réussie</p>
                        </div>
                    <?php else: ?>
                        <div class="status-card status-error">
                            <h4>❌ API SignNow</h4>
                            <p>Connexion échouée</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="status-card status-ok">
                        <h4>🎯 Text Tags</h4>
                        <p>Signature + Paraphes OK</p>
                    </div>
                    
                </div>
            </div>
            
            <div class="info-box">
                <h3>🔥 FONCTIONNALITÉS AVANCÉES</h3>
                <ul>
                    <li><strong>✅ Paraphes automatiques</strong> sur chaque page du contrat</li>
                    <li><strong>✅ Signature électronique</strong> en fin de document</li>
                    <li><strong>✅ Assignation automatique</strong> des champs au destinataire</li>
                    <li><strong>✅ Email d'invitation</strong> envoyé automatiquement</li>
                    <li><strong>✅ Workflow complet</strong> HTML → PDF → SignNow → Email</li>
                </ul>
            </div>
            
            <!-- Formulaire de test -->
            <form id="contractForm" onsubmit="generateContract(event)">
                
                <div class="form-section">
                    <h3>🆔 Informations Contrat</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Boutique *</label>
                            <input type="text" name="id_boutique" value="BTQ-2025-<?php echo str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date du contrat *</label>
                            <input type="date" name="date_contrat" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>👤 Acheteur</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Prénom *</label>
                            <input type="text" name="prenom_acheteur" value="Marie" required>
                        </div>
                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" name="nom_acheteur" value="DUBOIS" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email_acheteur" value="test@shopbuyhere.co" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="telephone_acheteur" value="+33 6 12 34 56 78">
                        </div>
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <input type="date" name="date_naissance" value="1985-03-15">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Adresse complète</label>
                        <textarea name="adresse_acheteur" rows="2">123 Rue de la République, 75001 Paris, France</textarea>
                    </div>
                    <div class="form-group">
                        <label>Pièce d'identité</label>
                        <input type="text" name="piece_identite" value="CNI 123456789">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>🏪 Boutique</h3>
                    <div class="form-group">
                        <label>Type de produits</label>
                        <textarea name="type_produits" rows="2">Accessoires de mode et bijoux fantaisie</textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Secteur d'activité</label>
                            <select name="secteur_activite">
                                <option value="Mode et Accessoires" selected>Mode et Accessoires</option>
                                <option value="Électronique et High-Tech">Électronique et High-Tech</option>
                                <option value="Maison et Jardin">Maison et Jardin</option>
                                <option value="Sport et Loisirs">Sport et Loisirs</option>
                                <option value="Beauté et Santé">Beauté et Santé</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date de lancement</label>
                            <input type="date" name="date_lancement" value="2023-06-01">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>CA mensuel moyen (€)</label>
                            <input type="number" name="ca_mensuel" value="8500" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Prix de la boutique (€) *</label>
                            <input type="number" name="prix_boutique" value="15000" step="0.01" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>📧 Options d'Envoi</h3>
                    <div class="form-group">
                        <label>Message personnalisé (optionnel)</label>
                        <textarea name="message_client" rows="2" placeholder="Message à ajouter dans l'email d'invitation..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email en copie (optionnel)</label>
                            <input type="email" name="copie_email" placeholder="manager@entreprise.com">
                        </div>
                        <div class="form-group">
                            <label>Délai de signature</label>
                            <select name="delai_signature">
                                <option value="7">7 jours (Urgent)</option>
                                <option value="15">15 jours</option>
                                <option value="30" selected>30 jours (Standard)</option>
                                <option value="60">60 jours</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" name="action" value="generate_contract">
                
                <button type="submit" class="btn" id="submitBtn">
                    🚀 Générer et Envoyer le Contrat Complet
                </button>
            </form>
            
            <!-- Résultat -->
            <div id="result" class="result"></div>
            
            <!-- Liens utiles -->
            <div class="links-section">
                <a href="logs.php" class="link-btn">📋 Voir les Logs</a>
                <a href="debug-500.php" class="link-btn">🔍 Debug 500</a>
                <a href="test-final.php" class="link-btn">🔗 Test SignNow</a>
            </div>
            
        </div>
    </div>
    
    <script>
        async function generateContract(event) {
            event.preventDefault();
            
            const form = event.target;
            const btn = document.getElementById('submitBtn');
            const result = document.getElementById('result');
            
            // État de chargement
            btn.disabled = true;
            btn.innerHTML = '⏳ Génération en cours...';
            result.style.display = 'none';
            
            try {
                const formData = new FormData(form);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Réponse non-JSON reçue - Erreur PHP probable');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    result.className = 'result success';
                    result.innerHTML = `
                        <h3>🎉 Contrat généré et envoyé avec succès !</h3>
                        <p><strong>Document ID SignNow :</strong> ${data.document_id}</p>
                        <p><strong>Invitation ID :</strong> ${data.invite_id}</p>
                        <p><strong>Contrat ID :</strong> ${data.contract_id}</p>
                        <p><strong>Email envoyé à :</strong> ${data.email_sent_to}</p>
                        <p><strong>Champs assignés :</strong> ${data.assigned_fields} champs (signature + paraphes)</p>
                        
                        <h4>📋 Prochaines étapes :</h4>
                        <ul>
                            ${data.next_steps.map(step => `<li>${step}</li>`).join('')}
                        </ul>
                        
                        <p style="margin-top: 1.5rem; font-weight: bold; color: #059669;">
                            ✅ Le destinataire va recevoir un email avec le contrat prêt à signer !<br>
                            🎯 Les paraphes et signatures sont automatiquement assignés !
                        </p>
                    `;
                } else {
                    result.className = 'result error';
                    result.innerHTML = `
                        <h3>❌ Erreur lors de la génération</h3>
                        <p><strong>Erreur :</strong> ${data.error}</p>
                        <p><strong>Support :</strong> <a href="mailto:${data.support || data.contact}">${data.support || data.contact}</a></p>
                        ${data.details ? `<p><strong>Détails :</strong> ${data.details}</p>` : ''}
                        ${data.logs ? `<p><strong>Logs :</strong> ${data.logs}</p>` : ''}
                    `;
                }
                
            } catch (error) {
                result.className = 'result error';
                result.innerHTML = `
                    <h3>💥 Erreur de communication</h3>
                    <p><strong>Problème :</strong> ${error.message}</p>
                    <p>Vérifiez la console développeur (F12) pour plus de détails.</p>
                    <p><strong>Support :</strong> <a href="mailto:support@shopbuyhere.co">support@shopbuyhere.co</a></p>
                `;
            }
            
            // Réactiver le formulaire
            btn.disabled = false;
            btn.innerHTML = '🚀 Générer et Envoyer le Contrat Complet';
            
            // Afficher le résultat
            result.style.display = 'block';
            result.scrollIntoView({ behavior: 'smooth' });
        }
        
        // Logs de chargement
        console.log('🚀 Backend SignNow Complet v3.0 chargé !');
        console.log('📋 Text Tags: {{s|signature|req|signer1}} + {{i|initials|req|signer1}}');
        console.log('🔄 Workflow: HTML → PDF → Upload → Assign → Invite');
        console.log('✅ Prêt pour génération de contrats avec paraphes automatiques !');
    </script>
</body>
</html>