<?php
// IMPORTANTE: Questo file deve essere salvato con codifica UTF-8 SENZA BOM
// NON deve esserci NESSUNO spazio o carattere prima di <?php

// Attiva output buffering per evitare problemi con headers già inviati
ob_start();

// 1. Configurazione Unica
$giorni_sessione_limite = 15; 
$scadenza_in_secondi = $giorni_sessione_limite * 86400;

// 2. Impostazioni Cookie Persistenti (Prima di session_start)
session_set_cookie_params([
    'lifetime' => $scadenza_in_secondi,
    'path'     => '/',
    'secure'   => true,  // Ora che hai HTTPS, impostalo su true per massima sicurezza
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.gc_maxlifetime', $scadenza_in_secondi);
session_start();

// 3. Gestione Scadenza Logica con KEEP-ALIVE automatico
$messaggio_scadenza = ""; 
if (isset($_SESSION['accesso_concesso']) && $_SESSION['accesso_concesso'] === true) {
    if (!isset($_SESSION['sh_start_time'])) {
        $_SESSION['sh_start_time'] = time();
    }
    
    // Calcola i giorni rimanenti dal cookie (se esiste)
    if (isset($_COOKIE['tabellone_login_time'])) {
        $login_timestamp = intval($_COOKIE['tabellone_login_time']);
        $tempo_trascorso = time() - $login_timestamp;
        $secondi_rimanenti = $scadenza_in_secondi - $tempo_trascorso;
        
        if ($secondi_rimanenti > 0) {
            $gg = floor($secondi_rimanenti / 86400);
            $ore = floor(($secondi_rimanenti % 86400) / 3600);
            $messaggio_scadenza = ($gg > 0) ? "Autenticazione valida ancora per $gg giorni e $ore ore" : "Autenticazione valida ancora per $ore ore";
            
            // DEBUG TEMPORANEO - rimuovere dopo il test
            // $messaggio_scadenza .= " [Debug: login=" . date('H:i:s', $login_timestamp) . ", now=" . date('H:i:s') . ", diff=" . $tempo_trascorso . "s]";
        } else {
            $messaggio_scadenza = "Sessione attiva";
        }
    } else {
        // Se non c'è il cookie, la sessione è solo temporanea (questo non dovrebbe mai verificarsi con il login corretto)
        $messaggio_scadenza = "Sessione attiva";
    }
}

// 4. Configurazione Tabellone
$config_tab = [
    "titolo_tabellone"   => "SOSTITUZIONI GIORNALIERE",
    "cartella_db"        => "archivio/",
    "credits"            => "Developed by S. Basile – F.S. Area 2",
    "mostra_trattamento" => true,
    "tempo_refresh"      => 45,
    "colore_sfondo"      => "#0f172a", 
    "colore_riga_pari"   => "#1e293b", 
    "colore_riga_disp"   => "#1a2233", 
    "colore_testata"     => "#FFF9BE", 
    "colore_ora"         => "#fbbf24", 
    "colore_sostituto"   => "#4ade80", 
    "colore_eccedenza"   => "#ef4444", 
    "colore_testo_gray"  => "#94a3b8", 
    "msg_ora_titolo"     => "⏰ Orario di consultazione previsto per oggi:",
    "msg_ora_fascia"     => "dalle ore <strong>%s</strong> alle <strong>%s</strong>.",
    "msg_ora_avviso"     => "Al di fuori di questo intervallo il servizio potrebbe non essere attivo.",
    "alert_disattivo"    => "PER UNO DEI SEGUENTI MOTIVI: <br>Al momento non risultano sostituzioni disponibili. <br>I dati non sono ancora stati aggiornati. <br>Il servizio è temporaneamente in manutenzione.",
    "usa_password"       => true, 
    "password_accesso"   => "BasileCambiami"
];

// 5. Logica Password con COOKIE PERSISTENTE e REGISTRAZIONE LOG
if ($config_tab['usa_password']) {
    // Logout: elimina sia la sessione che i cookie
    if (isset($_POST['logout'])) {
        session_unset();
        session_destroy();
        // Elimina i cookie persistenti impostandoli con scadenza passata
        setcookie('tabellone_auth', '', time() - 3600, '/', '', true, true);
        setcookie('tabellone_login_time', '', time() - 3600, '/', '', true, true);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Login con password
    if (isset($_POST['pwd_input']) && $_POST['pwd_input'] === $config_tab['password_accesso']) {
        $_SESSION['accesso_concesso'] = true;
        $login_timestamp = time();
        $_SESSION['sh_start_time'] = $login_timestamp;
        
        // Crea un cookie persistente che dura 15 giorni
        // Salva il timestamp del login nel cookie per calcolo preciso
        $cookie_value = hash('sha256', $config_tab['password_accesso'] . '|' . $login_timestamp . '|salt_sicuro');
        setcookie('tabellone_auth', $cookie_value, $login_timestamp + $scadenza_in_secondi, '/', '', true, true);
        
        // Salva anche il timestamp in un cookie separato per il calcolo della scadenza (come STRINGA)
        setcookie('tabellone_login_time', strval($login_timestamp), $login_timestamp + $scadenza_in_secondi, '/', '', true, true);

        // --- REGISTRAZIONE LOG AVANZATA ---
        $file_log = $config_tab['cartella_db'] . 'accessi_log.txt';
        $data_ora = date('d-m-Y H:i:s');
        $ip_utente = $_SERVER['REMOTE_ADDR'];
        
        // Pulizia del nome del browser per renderlo leggibile
        $agente = $_SERVER['HTTP_USER_AGENT'];
        $dispositivo = "PC/Generico";
        if (strpos($agente, 'iPhone')) $dispositivo = "iPhone";
        elseif (strpos($agente, 'Android')) $dispositivo = "Android";
        elseif (strpos($agente, 'iPad')) $dispositivo = "iPad";

        $linea_log = "$data_ora | IP: $ip_utente | Dispositivo: $dispositivo" . PHP_EOL;
        file_put_contents($file_log, $linea_log, FILE_APPEND);
        // ----------------------------------

        // Redirect per pulire il POST e non avere il messaggio di errore al refresh
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Verifica autenticazione: controlla sia la sessione che il cookie
    $cookie_valido = false;
    $login_timestamp = null;
    
    if (isset($_COOKIE['tabellone_auth']) && isset($_COOKIE['tabellone_login_time'])) {
        $login_timestamp = intval($_COOKIE['tabellone_login_time']);
        $hash_atteso = hash('sha256', $config_tab['password_accesso'] . '|' . $login_timestamp . '|salt_sicuro');
        
        // Verifica che il cookie sia valido e non scaduto
        if ($_COOKIE['tabellone_auth'] === $hash_atteso && (time() - $login_timestamp) <= $scadenza_in_secondi) {
            $cookie_valido = true;
        }
    }
    
    // Utente autenticato se ha la sessione attiva O il cookie valido
    if ($cookie_valido && !isset($_SESSION['accesso_concesso'])) {
        $_SESSION['accesso_concesso'] = true;
        $_SESSION['sh_start_time'] = time();
        $_SESSION['login_timestamp_originale'] = $login_timestamp; // Salva il timestamp originale del login
    }
    
    $autenticato = $_SESSION['accesso_concesso'] ?? false;
} else {
    $autenticato = true;
}

// RECUPERO DATI DB
$data_oggi = date('Y-m-d');
$file_db = $config_tab['cartella_db'] . 'sostituzioni_' . $data_oggi . '.json';
$data = file_exists($file_db) ? json_decode(file_get_contents($file_db), true) : null;

// NOTA: La vecchia "--- LOGICA PASSWORD ---" che avevi dopo è stata eliminata 
// perché ora è tutto gestito nel blocco sopra (punto 5).

// --- LOGICA DI RECUPERO FIRME PERSISTENTI ---
if (!$data || empty($data['responsabile_nome']) || empty($data['dirigente_nome'])) {
    $files = glob($config_tab['cartella_db'] . 'sostituzioni_*.json');
    if (!empty($files)) {
        rsort($files); 
        $ultimo_data = json_decode(file_get_contents($files[0]), true);
        $resp_fallback = $ultimo_data['responsabile_nome'] ?? '--';
        $dir_fallback = $ultimo_data['dirigente_nome'] ?? '--';
        $ist_fallback = $ultimo_data['istituto_nome'] ?? "ISTITUTO";
    }
}
$responsabile_visualizzato = $data['responsabile_nome'] ?? ($resp_fallback ?? '--');
$dirigente_visualizzato = $data['dirigente_nome'] ?? ($dir_fallback ?? '--');
$istituto_visualizzato = $data['istituto_nome'] ?? ($ist_fallback ?? "ISTITUTO");

$ora_attuale = date('H:i');
$online_manuale = (isset($data['online_status']) && $data['online_status'] == 'on');
$ora_apertura = $data['ora_apertura'] ?? "07:55";
$ora_chiusura = $data['ora_chiusura'] ?? "14:30";

$entro_orario = ($ora_attuale >= $ora_apertura && $ora_attuale <= $ora_chiusura);
$mostra_tabellone = ($online_manuale && $entro_orario);

function getGiornoSettimana($data_string) {
    $giorni = ["Domenica", "Lunedì", "Martedì", "Mercoledì", "Giovedì", "Venerdì", "Sabato"];
    return $giorni[date('w', strtotime($data_string))];
}

$sostituzioni = $data['sost'] ?? [];
usort($sostituzioni, function($a, $b) { return strcmp($a['ora'] ?? '', $b['ora'] ?? ''); });
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($autenticato): ?>
    <meta http-equiv="refresh" content="<?php echo $config_tab['tempo_refresh']; ?>">
    <?php endif; ?>
    <title>TABELLONE SOSTITUZIONI - <?php echo $istituto_visualizzato; ?></title>
    <style>
        :root { 
            --bg-dark: <?php echo $config_tab['colore_sfondo']; ?>; 
            --row-even: <?php echo $config_tab['colore_riga_pari']; ?>;
            --row-odd: <?php echo $config_tab['colore_riga_disp']; ?>;
            --header-bg: <?php echo $config_tab['colore_testata']; ?>;
            --ora-color: <?php echo $config_tab['colore_ora']; ?>;
            --accent-red: <?php echo $config_tab['colore_eccedenza']; ?>;
            --accent-green: <?php echo $config_tab['colore_sostituto']; ?>;
            --text-gray: <?php echo $config_tab['colore_testo_gray']; ?>;
        }
        
        body { 
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif; 
            background-color: var(--bg-dark); 
            color: #ffffff; 
            margin: 0; 
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .container { width: 100%; max-width: 1400px; text-align: center; }

        h1 { 
            font-size: clamp(1.4rem, 4.5vw, 2.8rem); 
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            margin: 20px 0 10px 0;
            letter-spacing: 2px;
        }

        .info-pill {
            background-color: var(--row-even);
            color: var(--header-bg);
            display: inline-block;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 700;
            font-size: clamp(0.8rem, 2.5vw, 1.1rem);
            margin-bottom: 40px;
            border: 1px solid #334155;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* --- STILI LOGIN --- */
        .login-box {
            background: var(--row-even);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #334155;
            width: 90%;
            max-width: 400px;
            margin: 0 auto;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }
        .login-box h2 { 
            color: var(--header-bg); 
            margin-bottom: 20px;
        }
        .pwd-wrapper {
            position: relative;
            margin-bottom: 15px;
        }
        .pwd-wrapper input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border-radius: 6px;
            border: 1px solid #475569;
            background: var(--bg-dark);
            color: #fff;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .toggle-pwd {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            user-select: none;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            font-weight: 700;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: transform 0.2s;
        }
        .btn-login:hover { transform: scale(1.02); }

        .logout-btn {
            padding: 8px 20px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 20px;
            transition: background 0.3s;
        }
        .logout-btn:hover { background: #dc2626; }

        /* --- STILI TABELLONE --- */
        .board-wrapper {
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--bg-dark);
            font-size: clamp(0.85rem, 2vw, 1.05rem);
        }

        thead tr {
            background-color: var(--header-bg);
            color: #1e293b;
        }

        th {
            padding: 16px 10px;
            text-align: left;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: clamp(0.75rem, 1.8vw, 0.95rem);
        }

        tbody tr {
            border-bottom: 1px solid #334155;
            transition: background-color 0.2s;
        }

        tbody tr:nth-child(even) { background-color: var(--row-even); }
        tbody tr:nth-child(odd)  { background-color: var(--row-odd); }
        tbody tr:hover { background-color: #293548; }

        td {
            padding: 14px 10px;
            text-align: left;
        }

        .alert-box {
            background-color: #7f1d1d;
            padding: 30px;
            border-radius: 12px;
            border: 2px solid var(--accent-red);
            margin: 20px 0;
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.2);
        }
        .alert-title {
            font-size: clamp(1rem, 3vw, 1.4rem);
            font-weight: 800;
            color: var(--header-bg);
            margin-bottom: 10px;
        }
        .alert-subtitle {
            font-size: clamp(0.85rem, 2.2vw, 1rem);
            color: #cbd5e1;
            line-height: 1.6;
        }

        .ora {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1e293b;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 900;
            font-size: 1.05em;
            display: inline-block;
        }

        .classe-badge {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 1em;
            display: inline-block;
        }

        .docente-assente { color: #cbd5e1; font-weight: 500; opacity: 0.9; }
        
        .sostituto { 
            color: var(--accent-green); 
            font-weight: 800; 
            text-transform: uppercase;
        }

        .trattamento-cell { 
            font-size: 0.95rem; 
            font-style: italic; 
            color: var(--text-gray); 
        }

        .eccedenza-evidenza { 
            color: var(--accent-red) !important; 
            font-weight: 900 !important; 
        }

        .signatures-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding: 20px;
            background: rgba(30, 41, 59, 0.5);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            width: 100%;
            box-sizing: border-box;
        }
        .signature-block {
            flex: 1;
            padding: 10px;
        }
        .signature-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-gray);
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        .signature-name {
            font-size: 1rem;
            font-weight: 700;
            color: var(--header-bg);
        }

        .footer { 
            margin-top: auto; 
            padding: 40px 0 20px; 
            font-size: 0.85rem; 
            color: var(--text-gray); 
            width: 100%;
        }

        @media (max-width: 850px) {
            th:nth-child(5), td:nth-child(5) { display: none; }
            th:nth-child(1), td:nth-child(1) { width: 70px; }
            .signatures-container { flex-direction: column; gap: 20px; }
        }

        @media (max-width: 600px) {
             th:nth-child(3), td:nth-child(3) { display: none; }
             .container { padding: 5px; }
             .login-box { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1><?php echo $config_tab['titolo_tabellone']; ?></h1>
        <div class="info-pill">
            <?php echo $istituto_visualizzato; ?> – GIORNO: <?php echo getGiornoSettimana($data_oggi) . " " . date('d/m/Y', strtotime($data_oggi)); ?>
        </div>
    </header>

    <?php if (!$autenticato): ?>
        <div class="login-box">
            <h2 style="margin-top:0;">Accesso Riservato</h2>
            <form method="POST">
                <div class="pwd-wrapper">
                    <input type="password" name="pwd_input" id="pwd_field" placeholder="Inserisci Password" required autofocus>
                    <span class="toggle-pwd" onclick="togglePassword()">👁️</span>
                </div>
                <button type="submit" class="btn-login">Entra</button>
            </form>
            <script>
                function togglePassword() {
                    const pf = document.getElementById('pwd_field');
                    pf.type = pf.type === 'password' ? 'text' : 'password';
                }
            </script>
        </div>
    <?php else: ?>
        
        <?php if ($config_tab['usa_password']): ?>
            <div style="width:100%; text-align: right;">
                <form method="POST">
                    <button type="submit" name="logout" class="logout-btn">Esci</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($mostra_tabellone && !empty($sostituzioni)): ?>
            <?php if (!empty($data['note_stampa'])): ?>
                <div style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; margin: 0 auto 25px auto; max-width: 1400px; border-radius: 8px; text-align: center; font-size: 1.05rem; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.2); width: 95%;">
                    ⚠️ NOTA: <?php echo htmlspecialchars($data['note_stampa']); ?>
                </div>
            <?php endif; ?>
            
            <div class="board-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%;">ORA</th>
                            <th style="width: 12%;">CLASSE</th>
                            <th style="width: 28%;">DOCENTE ASSENTE</th>
                            <th style="width: 30%;">DOCENTE SOSTITUTO</th>
                            <?php if($config_tab['mostra_trattamento']): ?>
                                <th style="width: 20%;">TRATTAMENTO</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sostituzioni as $s): if (!empty($s['sostituto'])): 
                            $is_ecc = (trim($s['trattamento'] ?? '') === 'ECCEDENZA');
                        ?>
                            <tr>
                                <td><span class="ora"><?php echo $s['ora'];?></span></td>
                                <td><span class="classe-badge"><?php echo $s['classe'];?></span></td>
                                <td class="docente-assente"><?php echo $s['assente'];?></td>
                                <td>
                                    <span class="sostituto <?php echo $is_ecc ? 'eccedenza-evidenza' : ''; ?>">
                                        <?php echo $s['sostituto'];?>
                                    </span>
                                </td>
                                <?php if($config_tab['mostra_trattamento']): ?>
                                    <td class="trattamento-cell <?php echo $is_ecc ? 'eccedenza-evidenza' : ''; ?>">
                                        <?php echo $is_ecc ? 'ECCEDENZA' : ($s['trattamento'] ?? '--');?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?php if (!$online_manuale): ?>
                <div class="alert-box">
                    <div class="alert-title">
                        <span style="background: #fff; color: #b04132; border-radius: 5px; padding: 2px 10px; font-size: 0.7em; vertical-align: middle; margin-right:10px;">OFF</span>
                        PUBBLICAZIONE NON DISPONIBILE
                    </div>
                    <div class="alert-subtitle"><?php echo $config_tab['alert_disattivo']; ?></div>
                </div>
            <?php elseif (!$entro_orario): ?>
                <div class="alert-box" style="background-color: var(--row-even); border: 1px solid #334155;">
                    <div class="alert-title"><?php echo $config_tab['msg_ora_titolo']; ?></div>
                    <div class="alert-subtitle">
                        <?php echo sprintf($config_tab['msg_ora_fascia'], $ora_apertura, $ora_chiusura); ?><br><br>
                        <small style="opacity: 0.8;"><?php echo $config_tab['msg_ora_avviso']; ?></small>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert-box" style="background-color: var(--row-even);">
                    <div class="alert-title">📄 Nessun dato</div>
                    <div class="alert-subtitle">Non sono state caricate sostituzioni per la giornata odierna.</div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <div class="signatures-container">
        <div class="signature-block">
            <div class="signature-label">Responsabili del Servizio</div>
            <div class="signature-name"><?php echo $responsabile_visualizzato; ?></div>
        </div>
        <div class="signature-block">
            <div class="signature-label">Dirigente Scolastico</div>
            <div class="signature-name"><?php echo $dirigente_visualizzato; ?></div>
        </div>
    </div>

    <footer class="footer">
        <?php if (!empty($messaggio_scadenza)): ?>
            <div style="margin-bottom: 5px; font-size: 0.85em; color: <?php echo $config_tab['colore_testo_gray']; ?>;">
                <?php echo $messaggio_scadenza; ?>
            </div>
        <?php endif; ?>

        <div><?php echo $config_tab['credits']; ?></div>
        
        <div style="margin-top:8px; opacity: 0.6;">
            Refresh: <?php echo $config_tab['tempo_refresh']; ?>s | Ultimo aggiornamento: <?php echo date('H:i:s'); ?>
        </div>
    </footer>
</div>

</body>
</html>
<?php
// Flush dell'output buffer alla fine
ob_end_flush();
?>
