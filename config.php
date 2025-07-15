<?php
// config.php - Configuration SignNow Complète

// 🔐 Configuration SignNow avec API Key
define('SIGNNOW_API_KEY', 'fb1318e14c14c1c7e354220b001f89b169f720fc41fa248440809a95de43a235');

// 📋 ID de votre modèle SignNow (IMPORTANT !)
define('SIGNNOW_TEMPLATE_ID', '9065d2c9306c4eaf9a327b2fe89fa40a4f448121');

// 📧 Configuration email
define('COMPANY_EMAIL', 'support@shopbuyhere.co');
define('COMPANY_NAME', 'MPI MANAGE LTD');

// 🌐 Configuration serveur
define('BASE_URL', 'https://gsleads55.com/sign');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// 📁 Configuration des dossiers
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('LOG_DIR', __DIR__ . '/logs/');

// Créer les dossiers s'ils n'existent pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// 📝 Fonction de logging
function logMessage($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents(LOG_DIR . 'signnow.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// 📊 Fonction de test de connexion avec API Key
function testSignNowConnection() {
    $url = 'https://api.signnow.com/user';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SIGNNOW_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Erreur cURL: ' . $error];
    }
    
    if ($httpCode === 200) {
        return ['success' => true, 'message' => 'Connexion SignNow réussie avec API Key'];
    }
    
    return ['success' => false, 'message' => 'Échec authentification SignNow (Code: ' . $httpCode . ')'];
}

// 🔧 Fonction pour valider la configuration Template
function validateTemplateConfig() {
    $errors = [];
    
    if (!defined('SIGNNOW_API_KEY') || SIGNNOW_API_KEY === 'YOUR_API_KEY') {
        $errors[] = 'API Key SignNow non configurée';
    }
    
    if (!defined('SIGNNOW_TEMPLATE_ID') || SIGNNOW_TEMPLATE_ID === 'YOUR_TEMPLATE_ID') {
        $errors[] = 'Template ID SignNow non configuré';
    }
    
    return $errors;
}

// 📋 Fonction pour tester l'accès au template
function testTemplateAccess() {
    if (!defined('SIGNNOW_TEMPLATE_ID')) {
        return ['success' => false, 'message' => 'Template ID non défini'];
    }
    
    $url = 'https://api.signnow.com/template/' . SIGNNOW_TEMPLATE_ID;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SIGNNOW_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'message' => 'Template accessible'];
    } elseif ($httpCode === 404) {
        return ['success' => false, 'message' => 'Template non trouvé - Vérifiez l\'ID'];
    } else {
        return ['success' => false, 'message' => 'Erreur accès template (Code: ' . $httpCode . ')'];
    }
}
?>