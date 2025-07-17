<?php
// logs.php - Visualisation des logs SignNow
require_once 'config.php';

$logFile = LOG_DIR . 'signnow.log';
$logs = [];
$totalLines = 0;

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $totalLines = count($lines);
    
    // Récupérer les 100 dernières lignes
    $logs = array_reverse(array_slice($lines, -100));
}

// Fonction pour parser une ligne de log
function parseLogLine($line) {
    if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $line, $matches)) {
        return [
            'timestamp' => $matches[1],
            'level' => $matches[2],
            'message' => $matches[3]
        ];
    }
    return ['timestamp' => '', 'level' => 'UNKNOWN', 'message' => $line];
}

// Fonction pour obtenir la couleur selon le niveau
function getLevelColor($level) {
    switch (strtoupper($level)) {
        case 'ERROR': return '#dc3545';
        case 'WARNING': return '#ffc107';
        case 'SUCCESS': return '#28a745';
        case 'INFO': return '#17a2b8';
        default: return '#6c757d';
    }
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs SignNow</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #343a40;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logs-container {
            max-height: 600px;
            overflow-y: auto;
            border-top: 1px solid #dee2e6;
        }
        
        .log-entry {
            padding: 10px 20px;
            border-bottom: 1px solid #f8f9fa;
            display: flex;
            align-items: center;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        .log-entry:hover {
            background: #f8f9fa;
        }
        
        .log-level {
            padding: 2px 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            font-size: 0.8em;
            margin-right: 10px;
            min-width: 60px;
            text-align: center;
        }
        
        .log-timestamp {
            color: #6c757d;
            margin-right: 15px;
            min-width: 120px;
        }
        
        .log-message {
            flex: 1;
            word-break: break-word;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-left: 10px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn.danger {
            background: #dc3545;
        }
        
        .btn.danger:hover {
            background: #c82333;
        }
        
        .no-logs {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .filter-controls {
            padding: 15px 20px;
            background: #e9ecef;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        select, input {
            padding: 5px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 Logs SignNow</h1>
                <small>Dernière mise à jour: <?= date('d/m/Y H:i:s') ?></small>
            </div>
            <div>
                <a href="index.php" class="btn">🏠 Accueil</a>
                <a href="test.php" class="btn">🔍 Diagnostic</a>
                <?php if (file_exists($logFile)): ?>
                    <a href="?action=clear" class="btn danger" onclick="return confirm('Effacer tous les logs ?')">🗑️ Vider</a>
                    <a href="?action=download" class="btn">💾 Télécharger</a>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Actions
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'clear':
                    if (file_exists($logFile)) {
                        file_put_contents($logFile, '');
                        echo '<div style="background: #d4edda; color: #155724; padding: 10px; margin: 20px;">✅ Logs effacés</div>';
                    }
                    break;
                    
                case 'download':
                    if (file_exists($logFile)) {
                        header('Content-Type: text/plain');
                        header('Content-Disposition: attachment; filename="signnow-logs-' . date('Y-m-d') . '.txt"');
                        readfile($logFile);
                        exit;
                    }
                    break;
            }
        }
        ?>

        <div class="stats">
            <div class="stat-card">
                <h3><?= $totalLines ?></h3>
                <p>Total Lignes</p>
            </div>
            
            <div class="stat-card">
                <h3><?= count($logs) ?></h3>
                <p>Lignes Affichées</p>
            </div>
            
            <div class="stat-card">
                <h3><?= file_exists($logFile) ? date('d/m/Y H:i', filemtime($logFile)) : 'N/A' ?></h3>
                <p>Dernière Activité</p>
            </div>
            
            <div class="stat-card">
                <h3><?= file_exists($logFile) ? round(filesize($logFile) / 1024, 2) . ' KB' : '0 KB' ?></h3>
                <p>Taille Fichier</p>
            </div>
        </div>

        <div class="filter-controls">
            <label>Filtrer par niveau:</label>
            <select id="levelFilter">
                <option value="">Tous</option>
                <option value="ERROR">Erreurs</option>
                <option value="WARNING">Avertissements</option>
                <option value="SUCCESS">Succès</option>
                <option value="INFO">Informations</option>
            </select>
            
            <label>Rechercher:</label>
            <input type="text" id="searchFilter" placeholder="Rechercher dans les logs...">
            
            <button onclick="applyFilters()" class="btn">Filtrer</button>
            <button onclick="clearFilters()" class="btn">Réinitialiser</button>
        </div>

        <div class="logs-container" id="logsContainer">
            <?php if (empty($logs)): ?>
                <div class="no-logs">
                    <h3>📝 Aucun log disponible</h3>
                    <p>Les logs apparaîtront ici lorsque l'application sera utilisée.</p>
                </div>
            <?php else: ?>
                <?php foreach ($logs as $line): ?>
                    <?php $parsed = parseLogLine($line); ?>
                    <div class="log-entry" data-level="<?= $parsed['level'] ?>">
                        <div class="log-level" style="background-color: <?= getLevelColor($parsed['level']) ?>">
                            <?= $parsed['level'] ?>
                        </div>
                        <div class="log-timestamp">
                            <?= $parsed['timestamp'] ?>
                        </div>
                        <div class="log-message">
                            <?= htmlspecialchars($parsed['message']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function applyFilters() {
            const levelFilter = document.getElementById('levelFilter').value;
            const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
            const entries = document.querySelectorAll('.log-entry');
            
            entries.forEach(entry => {
                const level = entry.getAttribute('data-level');
                const message = entry.querySelector('.log-message').textContent.toLowerCase();
                
                let showLevel = !levelFilter || level === levelFilter;
                let showSearch = !searchFilter || message.includes(searchFilter);
                
                entry.style.display = (showLevel && showSearch) ? 'flex' : 'none';
            });
        }
        
        function clearFilters() {
            document.getElementById('levelFilter').value = '';
            document.getElementById('searchFilter').value = '';
            
            document.querySelectorAll('.log-entry').forEach(entry => {
                entry.style.display = 'flex';
            });
        }
        
        // Auto-refresh toutes les 30 secondes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
        
        // Scroll automatique vers le bas
        const container = document.getElementById('logsContainer');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    </script>
</body>
</html>