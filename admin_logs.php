<?php
session_start();

// Protezione: solo chi è loggato nel tabellone può vedere i log
if (!isset($_SESSION['accesso_concesso']) || $_SESSION['accesso_concesso'] !== true) {
    die("Accesso negato. Effettua prima il login sul tabellone.");
}

$file_log = 'archivio/accessi_log.txt'; 
$log_content = file_exists($file_log) ? file($file_log) : [];
$log_content = array_reverse($log_content); // I più recenti in alto

// Raggruppamento per giorni e conteggio
$statistiche_giornaliere = [];
foreach ($log_content as $line) {
    $parts = explode('|', $line);
    if (count($parts) >= 1) {
        $data_piena = trim($parts[0]); // Es: 04-02-2026 21:31:46
        $solo_data = substr($data_piena, 0, 10); // Estrae solo 04-02-2026
        if (!isset($statistiche_giornaliere[$solo_data])) {
            $statistiche_giornaliere[$solo_data] = 0;
        }
        $statistiche_giornaliere[$solo_data]++;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>LOG ACCESSI - AMMINISTRAZIONE</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: white; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .log-table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 8px; overflow: hidden; margin-top: 20px; }
        th { background: #fbbf24; color: #0f172a; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #334155; font-family: monospace; }
        tr:hover { background: #334155; }
        h1 { text-align: center; color: #4ade80; }
        
        /* Box Statistiche */
        .stats-container { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; justify-content: center; }
        .stat-card { background: #334155; padding: 10px 20px; border-radius: 5px; border-left: 4px solid #4ade80; }
        .stat-date { font-size: 0.8em; color: #94a3b8; display: block; }
        .stat-count { font-size: 1.2em; font-weight: bold; color: #4ade80; }
        
        .btn-back { display: block; width: 200px; margin: 20px auto; text-align: center; padding: 10px; background: #4ade80; color: #0f172a; text-decoration: none; font-weight: bold; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Registro Accessi Tabellone</h1>
        
        <div class="stats-container">
            <?php foreach ($statistiche_giornaliere as $data_it => $totale): ?>
                <div class="stat-card">
                    <span class="stat-date"><?php echo $data_it; ?></span>
                    <span class="stat-count"><?php echo $totale; ?> accessi</span>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="tabellone.php" class="btn-back">← Torna al Tabellone</a>
        
        <table class="log-table">
            <thead>
                <tr>
                    <th>Data e Ora</th>
                    <th>Indirizzo IP</th>
                    <th>Dispositivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($log_content as $line): 
                    $parts = explode('|', $line);
                    if (count($parts) >= 3):
                ?>
                    <tr>
                        <td><?php echo trim($parts[0]); ?></td>
                        <td><?php echo trim($parts[1]); ?></td>
                        <td><?php echo trim($parts[2]); ?></td>
                    </tr>
                <?php endif; endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>