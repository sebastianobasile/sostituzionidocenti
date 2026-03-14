<?php
// ==========================================
// CONFIGURAZIONE E DATI DI DEFAULT
// ==========================================
$config = [
    "app_attiva"      => true,
    "usa_password"    => true,
    "password"        => "Basile_cambiala", 
    "online_def"      => true,
    "istituto_def"    => "SECONDARIA - Capuana-DeAmicis", 
    "dirigente_def"   => "PROF. GIUSEPPE CANINO", 
    "resp_def"        => "PROFF. GIUDICE S. e PICCIONE C.", 
    "ora_apertura_def"=> "08:05",
    "ora_chiusura_def"=> "15:00",
    "opzioni_tratt"   => ["--", "Potenziamento", "Servizio", "ECCEDENZA", "BANCA ORE", "Recupero", "Altro"], 
    "max_assenti"     => 12,
    "max_sost"        => 20,
    "max_ore"         => 6,
    "righe_manuali"   => 1,
    "url_tabellone"   => "tabellone.php",
    "url_orario"      => "https://capuanadeamicis.it",
    "cartella_db"     => "archivio/", 
    "titolo_font_size"=> "1.1rem",       // dimensione font titolo stampa (es. 1rem, 1.2rem, 16px)
    "credits"         => "Idea e sviluppo: Sebastiano BASILE – F.S. Area 2" 
];

// Limiti massimi assoluti per il rendering dinamico (non modificano il DB)
$HARD_MAX_ASSENTI = 30;
$HARD_MAX_SOST    = 60;
$HARD_MAX_ORE     = 12;

session_start();
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
if (isset($_POST['login_pass']) && $_POST['login_pass'] === $config['password']) { $_SESSION['autenticato'] = true; }
if (!$config['app_attiva']) die("Applicativo momentaneamente disattivato.");

if ($config['usa_password'] && !isset($_SESSION['autenticato'])):
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso Riservato</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 15px; box-sizing: border-box; }
        .login-card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; width: 100%; max-width: 400px; }
        .login-card h2 { color: #2c3e50; margin-bottom: 25px; font-size: clamp(1.2rem, 5vw, 1.8rem); }
        .pass-wrapper { position: relative; margin-bottom: 20px; }
        .login-card input[type="password"], .login-card input[type="text"] { width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 18px; appearance: none; }
        .login-card input:focus { border-color: #3498db; outline: none; }
        .toggle-btn { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 22px; color: #7f8c8d; user-select: none; }
        .login-card button { width: 100%; padding: 15px; background: #3498db; color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; transition: background 0.3s; }
        .login-card button:active { background: #2980b9; }
        .public-link { display: block; margin-top: 25px; padding: 15px; background: #f0fdf4; border-radius: 8px; text-decoration: none; color: #27ae60; font-weight: bold; border: 1px solid #dcfce7; }
        .scuola-label { font-size: 12px; color: #95a5a6; margin-top: 20px; text-transform: uppercase; line-height: 1.4; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="font-size: 50px; margin-bottom: 10px;">🔒</div>
        <h2>Accesso Gestione</h2>
        <form method="post">
            <div class="pass-wrapper">
                <input type="password" name="login_pass" id="login_pass" placeholder="Password Admin" autofocus required>
                <span class="toggle-btn" onclick="togglePass()">👁️</span>
            </div>
            <button type="submit">ENTRA</button>
        </form>
        <a href="<?php echo $config['url_tabellone']; ?>" class="public-link">📺 VISUALIZZA TABELLONE</a>
        <div class="scuola-label"><?php echo $config['istituto_def']; ?></div>
    </div>
    <script>function togglePass() { var x = document.getElementById("login_pass"); x.type = (x.type === "password") ? "text" : "password"; }</script>
</body>
</html>
<?php exit; endif;

$data_selezionata = $_POST['data_giorno'] ?? $_GET['data_view'] ?? date('Y-m-d');
$file_db = $config['cartella_db'] . 'sostituzioni_' . $data_selezionata . '.json';

if (isset($_POST['pulisci_vecchi'])) {
    $files = glob($config['cartella_db'] . "sostituzioni_*.json");
    $limite = strtotime('-30 days'); $contatore = 0;
    foreach ($files as $f) { if (filemtime($f) < $limite) { unlink($f); $contatore++; } }
    echo "<script>alert('Pulizia completata! Eliminati $contatore file.'); window.location.href='index.php?data_view=$data_selezionata';</script>";
    exit;
}

if (isset($_POST['resetta'])) { if (file_exists($file_db)) { unlink($file_db); } header("Location: index.php"); exit; }

if (isset($_POST['genera'])) {
    $_POST['online_status'] = isset($_POST['online_status']) ? 'on' : 'off';
    $_POST['stampa_tagliandi'] = isset($_POST['stampa_tagliandi']) ? 'on' : 'off';
    if(isset($_POST['assenti'])) { foreach($_POST['assenti'] as $k => $v) $_POST['assenti'][$k] = trim($v); }
    if(isset($_POST['sost'])) { 
        foreach($_POST['sost'] as $k => $v) { 
            $_POST['sost'][$k]['sostituto'] = trim($v['sostituto'] ?? ''); 
            $_POST['sost'][$k]['classe'] = trim($v['classe'] ?? ''); 
            $_POST['sost'][$k]['assente'] = trim($v['assente'] ?? '');
        } 
    }
    if (!is_dir($config['cartella_db'])) mkdir($config['cartella_db'], 0777, true);
    file_put_contents($file_db, json_encode($_POST));
    header("Location: index.php?data_view=" . $_POST['data_giorno'] . "&saved=1");
    exit;
}

$bozza = [];
if (file_exists($file_db)) { $bozza = json_decode(file_get_contents($file_db), true); }

function getDataEstesa($d) {
    $sett = ["Domenica", "Lunedì", "Martedì", "Mercoledì", "Giovedì", "Venerdì", "Sabato"];
    $mesi = ["", "Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno", "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"];
    $ts = strtotime($d);
    return strtolower($sett[date('w', $ts)]) . " " . date('j', $ts) . " " . strtolower($mesi[date('n', $ts)]) . " " . date('Y', $ts);
}

$mostra_stampa = (isset($_GET['data_view']) && file_exists($file_db));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Sostituzioni - Admin</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #ececec; margin: 0; padding: 20px; }
        .no-print { background: white; padding: 30px; border-radius: 15px; max-width: 1100px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header-admin { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
        .btn-link-tabellone { background: #34495e; color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; font-size: 13px; transition: 0.3s; margin-left: 5px; }
        .btn-link-orario { background: #27ae60; color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; font-size: 13px; transition: 0.3s; margin-left: 5px; }
        .btn-salva-header { background: #3498db; color: white; border: none; padding: 8px 20px; border-radius: 5px; font-weight: bold; font-size: 13px; cursor: pointer; transition: 0.3s; margin-left: 5px; }
        .btn-salva-header:hover { background: #2980b9; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 8px; text-align: center; word-wrap: break-word; }
        th { background: #f8f9fa; }
        select option[value="ECCEDENZA"], .evidenzia-ecc { color: red !important; font-weight: bold !important; }
        .select-eccedenza { color: red !important; font-weight: bold !important; border: 2px solid red !important; }
        .tagliando { width: 190mm; border: 2px dashed #000; margin: 0 auto 30px auto; padding: 15px; page-break-inside: avoid; background: white; box-sizing: border-box; }
        .input-assente:not(:placeholder-shown) { color: #1a5276; font-weight: bold; border: 2px solid #e74c3c; background-color: #fff5f5 !important; }
        @media print { .no-print { display: none !important; } body { background: white; padding: 0; margin: 0; } .area-stampa { display: block !important; width: 100%; } .page-break { page-break-before: always; } }
        input, select { padding: 6px; width: 95%; border: 1px solid #ccc; border-radius: 4px; }
        .grid-assenti { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 10px; }
        .btn-genera { background: #27ae60; color: white; padding: 15px 40px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px; }
        .btn-reset { background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 13px; }
        .btn-pulizia { background: #95a5a6; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 13px; }
        .switch-container { display: flex; align-items: center; background: #ebf5fb; padding: 10px; border-radius: 8px; border: 1px solid #3498db; }
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; margin-right: 10px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #27ae60; }
        input:checked + .slider:before { transform: translateX(26px); }
        .campo-note { margin: 20px 0; padding: 10px; background: #fffde7; border-radius: 8px; border: 1px solid #fdd835; }
        /* Larghezze colonne tabella di stampa */
        .tabella-stampa { table-layout: fixed; }
        .tabella-stampa col.col-ora    { width: 9%; }
        .tabella-stampa col.col-cl     { width: 8%; }
        .tabella-stampa col.col-assente  { width: 21%; }
        .tabella-stampa col.col-sost     { width: 21%; }
        .tabella-stampa col.col-tratt    { width: 18%; }
        .tabella-stampa col.col-firma    { width: 23%; }
        @media print { .tabella-stampa tr { page-break-inside: avoid; } }
        .qr-container { position: absolute; top: 0px; right: 10px; text-align: center; }
        .qr-container #qrcode { display: inline-block; }
        .qr-container #qrcode img { display: block; }
        @media print { .qr-container { position: absolute; top: 0px; right: 10px; } }
        /* Controllo numerico sezioni */
        .assente-item-hidden { display: none !important; }
        .sost-row-hidden { display: none !important; }
        .num-ctrl { display:inline-flex; align-items:center; gap:5px; margin-left:12px; font-size:12px; font-weight:normal; color:#555; vertical-align:middle; }
        .num-ctrl input[type=number] { width:52px; padding:2px 5px; border:2px solid #3498db; border-radius:4px; font-size:13px; font-weight:bold; color:#2c3e50; text-align:center; background:#ebf5fb; }
        .num-ctrl .lbl-def { color:#888; font-size:11px; }
    </style>
    <script>
        function cambiaData(d) { window.location.href = 'index.php?data_view=' + d; }
        
        // ---- Controllo numerico righe (solo UI, non tocca il DB) ----
        var hardMaxAssenti = <?php echo $HARD_MAX_ASSENTI; ?>;
        var hardMaxSost    = <?php echo $HARD_MAX_SOST; ?>;
        var hardMaxOre     = <?php echo $HARD_MAX_ORE; ?>;

        function aggiornaOpzioniOre(n) {
            n = Math.max(1, Math.min(parseInt(n) || 1, hardMaxOre));
            document.getElementById('num_ore_ctrl').value = n;
            document.getElementById('max_ore_custom').value = n;
            document.querySelectorAll('.select-ora').forEach(function(sel) {
                var valCorrente = sel.value;
                // Rimuovi tutte le opzioni tranne la prima (il trattino)
                while (sel.options.length > 1) sel.remove(1);
                // Rigenera le opzioni fino a n
                for (var o = 1; o <= n; o++) {
                    var v = o + 'ª';
                    var opt = document.createElement('option');
                    opt.value = v; opt.text = o + 'ª Ora';
                    if (v === valCorrente) opt.selected = true;
                    sel.appendChild(opt);
                }
                // Se il valore precedente è oltre il nuovo limite, azzera
                var numVal = parseInt(valCorrente);
                if (!isNaN(numVal) && numVal > n) sel.value = '';
            });
        }

        function aggiornaVisibilitaAssenti(n) {
            n = Math.max(1, Math.min(parseInt(n) || 1, hardMaxAssenti));
            document.getElementById('num_assenti_ctrl').value = n;
            document.getElementById('max_assenti_custom').value = n;
            document.querySelectorAll('.assente-grid-item').forEach(function(item, idx) {
                item.classList.toggle('assente-item-hidden', idx >= n);
            });
            aggiornaDocentiAssenti();
        }

        function aggiornaVisibilitaSost(n) {
            n = Math.max(1, Math.min(parseInt(n) || 1, hardMaxSost));
            document.getElementById('num_sost_ctrl').value = n;
            document.getElementById('max_sost_custom').value = n;
            document.querySelectorAll('.sost-tbody tr').forEach(function(row, idx) {
                row.classList.toggle('sost-row-hidden', idx >= n);
            });
        }
        // ---- Fine controllo numerico righe ----

        let vecchiaListaAssenti = [];

        function aggiornaDocentiAssenti() {
            const inputs = document.querySelectorAll('.input-assente');
            const selects = document.querySelectorAll('.select-docente-assente');
            
            let nuovaListaAssenti = [];
            inputs.forEach((input, index) => {
                let val = input.value.trim();
                nuovaListaAssenti[index] = val;
            });

            selects.forEach(select => {
                let valoreSelezionato = select.value;
                
                // Se il nome è stato modificato nel Blocco 1, cerchiamo di seguire la modifica
                vecchiaListaAssenti.forEach((vecchioNome, i) => {
                    if (vecchioNome !== "" && valoreSelezionato === vecchioNome && nuovaListaAssenti[i] !== vecchioNome) {
                        valoreSelezionato = nuovaListaAssenti[i];
                    }
                });

                let isManuale = (valoreSelezionato !== "" && !nuovaListaAssenti.includes(valoreSelezionato) && valoreSelezionato !== "ALTRO_MANUALE");

                select.innerHTML = '<option value="">-- seleziona --</option>';
                
                nuovaListaAssenti.forEach(nome => {
                    if(nome !== "") {
                        let opt = document.createElement('option');
                        opt.value = nome; opt.text = nome;
                        if(nome === valoreSelezionato) opt.selected = true;
                        select.appendChild(opt);
                    }
                });

                if(isManuale) {
                    let optMan = document.createElement('option');
                    optMan.value = valoreSelezionato; optMan.text = valoreSelezionato; optMan.selected = true;
                    optMan.classList.add('opzione-manuale');
                    select.appendChild(optMan);
                }

                let optAltro = document.createElement('option');
                optAltro.value = "ALTRO_MANUALE"; optAltro.text = "➕ -- Altro (scrivi nome) --";
                select.appendChild(optAltro);
            });
            
            vecchiaListaAssenti = [...nuovaListaAssenti];
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('select-docente-assente')) {
                if(e.target.value === "ALTRO_MANUALE") {
                    // Trova se c'era un valore manuale precedente
                    let valorePrecedente = "";
                    for(let i = 0; i < e.target.options.length; i++) {
                        let opt = e.target.options[i];
                        if(opt.classList.contains('opzione-manuale')) {
                            valorePrecedente = opt.value;
                            break;
                        }
                    }
                    
                    let messaggio = valorePrecedente 
                        ? "Inserisci nuovo nome (lascia vuoto per cancellare):\nPrecedente: " + valorePrecedente
                        : "Inserisci nome docente o la causale:";
                    
                    let n = prompt(messaggio, valorePrecedente);
                    
                    if(n !== null) {  // L'utente non ha premuto annulla
                        // Rimuovi tutte le opzioni manuali precedenti
                        let opzioniDaRimuovere = [];
                        for(let i = 0; i < e.target.options.length; i++) {
                            let opt = e.target.options[i];
                            if(opt.classList.contains('opzione-manuale')) {
                                opzioniDaRimuovere.push(opt);
                            }
                        }
                        opzioniDaRimuovere.forEach(opt => opt.remove());
                        
                        if(n.trim() !== "") {
                            // Crea nuova opzione manuale
                            let opt = document.createElement('option');
                            opt.value = n.trim(); 
                            opt.text = n.trim(); 
                            opt.selected = true;
                            opt.classList.add('opzione-manuale');
                            e.target.insertBefore(opt, e.target.lastChild);
                        } else {
                            // L'utente vuole cancellare
                            e.target.value = "";
                        }
                    } else {
                        // L'utente ha premuto annulla, ripristina il valore precedente
                        if(valorePrecedente) {
                            e.target.value = valorePrecedente;
                        } else {
                            e.target.value = "";
                        }
                    }
                }
            }
            if (e.target.name && e.target.name.includes('[trattamento]')) {
                e.target.classList.toggle('select-eccedenza', e.target.value === 'ECCEDENZA');
            }
        });

        window.onload = function() {
            // Mostra alert di conferma salvataggio
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('saved') === '1') {
                alert('✅ Dati salvati e documenti generati con successo!');
                // Rimuove il parametro saved dall'URL senza ricaricare la pagina
                const newUrl = window.location.pathname + '?data_view=' + urlParams.get('data_view');
                window.history.replaceState({}, '', newUrl);
            }
            
            const selects = document.querySelectorAll('.select-docente-assente');
            selects.forEach(s => {
                let valIniziale = s.getAttribute('data-prev');
                if(valIniziale && valIniziale !== "") {
                    let opt = document.createElement('option');
                    opt.value = valIniziale; opt.text = valIniziale; opt.selected = true;
                    s.appendChild(opt);
                }
            });
            
            const inputs = document.querySelectorAll('.input-assente');
            inputs.forEach(input => vecchiaListaAssenti.push(input.value.trim()));
            
            aggiornaDocentiAssenti();
            // Inizializza visibilità righe in base al valore salvato o default
            aggiornaVisibilitaAssenti(document.getElementById('num_assenti_ctrl').value);
            aggiornaVisibilitaSost(document.getElementById('num_sost_ctrl').value);
            aggiornaOpzioniOre(document.getElementById('num_ore_ctrl').value);
            document.querySelectorAll('select[name*="[trattamento]"]').forEach(s => {
                if(s.value === 'ECCEDENZA') s.classList.add('select-eccedenza');
            });
        };
    </script>
</head>
<body>

<div class="no-print">
    <div class="header-admin">
        <h1 style="margin:0; border:none;">📝 Gestione Sostituzioni</h1>
        <div style="display: flex; align-items: center;">
            <button type="submit" name="genera" form="form-sostituzioni" class="btn-salva-header">💾 SALVA E GENERA</button>
            <a href="<?php echo $config['url_orario']; ?>" target="_blank" class="btn-link-orario">🕒 ORARIO</a>
            <a href="<?php echo $config['url_tabellone']; ?>" target="_blank" class="btn-link-tabellone">📅 TABELLONE</a>
            <a href="fonogrammi.php" class="btn-link-tabellone" style="background:#e67e22;">📞 FONOGRAMMI</a>
            <button type="button" onclick="window.print()" class="btn-link-tabellone" style="background:#8e44ad; border:none; cursor:pointer;">🖨️ STAMPA</button>
            <?php if($config['usa_password']): ?><a href="?logout=1" style="margin-left:15px; color:#e74c3c; text-decoration:none; font-weight:bold;">Esci</a><?php endif; ?>
        </div>
    </div>
    
    <form method="post" id="form-sostituzioni">
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:20px; margin: 20px 0; background: #fdfdfd; padding: 15px; border-radius: 8px; border: 1px solid #eee;">
            <div>Data:<br><input type="date" name="data_giorno" value="<?php echo $data_selezionata; ?>" onchange="cambiaData(this.value)" required></div>
            <div>Istituto:<br><input type="text" name="istituto_nome" value="<?php echo $bozza['istituto_nome'] ?? $config['istituto_def']; ?>"></div>
            <div class="switch-container">
                <label class="switch">
                    <?php 
                        $check = "";
                        if (file_exists($file_db)) { $check = (isset($bozza['online_status']) && $bozza['online_status'] == 'on') ? 'checked' : ''; }
                        else { $check = ($config['online_def']) ? 'checked' : ''; }
                    ?>
                    <input type="checkbox" name="online_status" <?php echo $check; ?>>
                    <span class="slider"></span>
                </label>
                <span style="font-size:11px; font-weight:bold;">TABELLONE ONLINE</span>
            </div>
            <div>Ora Apertura:<br><input type="time" name="ora_apertura" value="<?php echo $bozza['ora_apertura'] ?? $config['ora_apertura_def']; ?>"></div>
            <div>Ora Chiusura:<br><input type="time" name="ora_chiusura" value="<?php echo $bozza['ora_chiusura'] ?? $config['ora_chiusura_def']; ?>"></div>
            <div class="switch-container" style="background: #f5eef8; border-color: #8e44ad;">
                <label class="switch">
                    <input type="checkbox" name="stampa_tagliandi" <?php echo (isset($bozza['stampa_tagliandi']) && $bozza['stampa_tagliandi'] == 'on') ? 'checked' : ''; ?>>
                    <span class="slider" style="background-color: #8e44ad;"></span>
                </label>
                <span style="font-size:11px; font-weight:bold;">STAMPA ANCHE TAGLIANDI</span>
            </div>
            <div>Responsabili del servizio:<br><input type="text" name="responsabile_nome" value="<?php echo $bozza['responsabile_nome'] ?? $config['resp_def']; ?>" required></div>
            <div style="grid-column: span 2;">Dirigente Scolastico:<br><input type="text" name="dirigente_nome" value="<?php echo $bozza['dirigente_nome'] ?? $config['dirigente_def']; ?>"></div>
        </div>

        <h3>1. Docenti Assenti
            <span class="num-ctrl">
                (n.&nbsp;<input type="number" id="num_assenti_ctrl"
                    min="1" max="<?php echo $HARD_MAX_ASSENTI; ?>"
                    value="<?php echo intval($bozza['max_assenti_custom'] ?? $config['max_assenti']); ?>"
                    onchange="aggiornaVisibilitaAssenti(this.value)"
                    title="Modifica il numero di righe visibili">
                <span class="lbl-def">default: <?php echo $config['max_assenti']; ?> — modificabile</span>
            </span>
            <input type="hidden" name="max_assenti_custom" id="max_assenti_custom" value="<?php echo intval($bozza['max_assenti_custom'] ?? $config['max_assenti']); ?>">
        </h3>
        <div class="grid-assenti">
            <?php for($i=1; $i<=$HARD_MAX_ASSENTI; $i++): ?>
                <div class="assente-grid-item<?php echo ($i > ($bozza['max_assenti_custom'] ?? $config['max_assenti'])) ? ' assente-item-hidden' : ''; ?>"><?php echo $i; ?>. <input type="text" name="assenti[<?php echo $i; ?>]" class="input-assente" oninput="aggiornaDocentiAssenti()" value="<?php echo htmlspecialchars($bozza['assenti'][$i] ?? ''); ?>" placeholder="Libero"></div>
            <?php endfor; ?>
        </div>

        <div class="campo-note">
            <strong>Note eventuali (appariranno nel foglio di stampa e nel tabellone online se compilate):</strong><br>
            <input type="text" name="note_stampa" value="<?php echo htmlspecialchars($bozza['note_stampa'] ?? ''); ?>" style="width:98%; margin-top:5px;" placeholder="Esempio: Le classi entreranno alla seconda ora...">
        </div>

        <h3>2. Tabella Sostituzioni
            <span class="num-ctrl">
                (n.&nbsp;<input type="number" id="num_sost_ctrl"
                    min="1" max="<?php echo $HARD_MAX_SOST; ?>"
                    value="<?php echo intval($bozza['max_sost_custom'] ?? $config['max_sost']); ?>"
                    onchange="aggiornaVisibilitaSost(this.value)"
                    title="Modifica il numero di righe visibili">
                <span class="lbl-def">default: <?php echo $config['max_sost']; ?> — modificabile</span>
            </span>
            <input type="hidden" name="max_sost_custom" id="max_sost_custom" value="<?php echo intval($bozza['max_sost_custom'] ?? $config['max_sost']); ?>">
        </h3>
        <table>
            <thead><tr>
                <th style="width:10%;">Ora
                    <div class="num-ctrl" style="justify-content:center; margin-top:4px;">
                        n.&nbsp;<input type="number" id="num_ore_ctrl"
                            min="1" max="<?php echo $HARD_MAX_ORE; ?>"
                            value="<?php echo intval($bozza['max_ore_custom'] ?? $config['max_ore']); ?>"
                            onchange="aggiornaOpzioniOre(this.value)"
                            title="Modifica il numero di ore disponibili">
                    </div>
                    <input type="hidden" name="max_ore_custom" id="max_ore_custom" value="<?php echo intval($bozza['max_ore_custom'] ?? $config['max_ore']); ?>">
                </th>
                <th style="width:12%;">Classe</th><th>Docente Assente</th><th>Docente Sostituto</th><th style="width:15%;">Trattamento</th>
            </tr></thead>
            <tbody class="sost-tbody">
                <?php for($r=0; $r<$HARD_MAX_SOST; $r++): ?>
                <tr class="<?php echo ($r >= ($bozza['max_sost_custom'] ?? $config['max_sost'])) ? 'sost-row-hidden' : ''; ?>">
                    <td><select name="sost[<?php echo $r; ?>][ora]" class="select-ora"><option value="">-</option>
                        <?php for($o=1; $o<=$HARD_MAX_ORE; $o++) { $v = "{$o}ª"; $s = (isset($bozza['sost'][$r]['ora']) && $bozza['sost'][$r]['ora'] == $v) ? 'selected' : ''; echo "<option value='$v' $s>{$o}ª Ora</option>"; } ?>
                    </select></td>
                    <td><input type="text" name="sost[<?php echo $r; ?>][classe]" value="<?php echo htmlspecialchars($bozza['sost'][$r]['classe'] ?? ''); ?>"></td>
                    <td><select name="sost[<?php echo $r; ?>][assente]" class="select-docente-assente" data-prev="<?php echo htmlspecialchars($bozza['sost'][$r]['assente'] ?? ''); ?>">
                        </select></td>
                    <td><input type="text" name="sost[<?php echo $r; ?>][sostituto]" value="<?php echo htmlspecialchars($bozza['sost'][$r]['sostituto'] ?? ''); ?>"></td>
                    <td><select name="sost[<?php echo $r; ?>][trattamento]">
                        <?php foreach($config['opzioni_tratt'] as $t) { 
                            $s = (isset($bozza['sost'][$r]['trattamento']) && $bozza['sost'][$r]['trattamento'] == $t) ? 'selected' : ''; 
                            echo "<option value='$t' $s style='".($t=='ECCEDENZA'?'color:red;font-weight:bold;':'')."'>$t</option>"; 
                        } ?>
                    </select></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div style="text-align:center; margin-top:30px;">
            <button type="submit" name="genera" class="btn-genera">SALVA E GENERA DOCUMENTI</button>
            <div style="margin-top:20px;">
                <button type="submit" name="resetta" class="btn-reset" onclick="return confirm('Confermi di cancellare i dati inseriti per questo giorno?')">RESETTA GIORNO</button>
                <button type="submit" name="pulisci_vecchi" class="btn-pulizia" onclick="return confirm('Elimina le sostituzioni docenti vecchie di oltre 30 giorni?')">PULISCI ARCHIVIO (>30gg)</button>
            </div>
        </div>
    </form>
</div>

<?php if($mostra_stampa): 
    $dg = $bozza['data_giorno']; $inst = $bozza['istituto_nome']; $dir = $bozza['dirigente_nome'];
    $res = $bozza['responsabile_nome']; $as = $bozza['assenti'] ?? []; $ss = $bozza['sost'] ?? []; $not = $bozza['note_stampa'] ?? '';
    usort($ss, function($a, $b) { $va = (int)str_replace('ª', '', $a['ora'] ?? '99'); $vb = (int)str_replace('ª', '', $b['ora'] ?? '99'); return ($va ?: 99) <=> ($vb ?: 99); });
?>
    <div class="area-stampa" style="position: relative;">
        <div class="qr-container">
            <div id="qrcode"></div>
        </div>
        <div style="min-height: 85px; margin-right: 90px;">
            <h2 style="text-align:center; font-size:<?php echo $config['titolo_font_size']; ?>; margin:0 0 6px 0;"><?php echo $inst; ?> - SOSTITUZIONI</h2>
            <p style="text-align:center; font-weight:bold; text-transform: uppercase; margin:0;">Disposizione di servizio: <?php echo getDataEstesa($dg); ?></p>
        </div>
        <div style="border:1px solid #000; padding:10px; margin-bottom:10px;"><strong>DOCENTI ASSENTI:</strong> <?php $la = array_filter($as); echo !empty($la)?implode(', ',$la):'---';?></div>
        <?php if(!empty(trim($not))): ?><div style="border:1px solid #000; padding:10px; margin-bottom:20px; background:#f9f9f9;"><strong>NOTE:</strong> <?php echo htmlspecialchars($not); ?></div><?php endif; ?>
        <table class="tabella-stampa"><colgroup><col class="col-ora"><col class="col-cl"><col class="col-assente"><col class="col-sost"><col class="col-tratt"><col class="col-firma"></colgroup><thead><tr><th>ORA</th><th>CL.</th><th>DOCENTE ASSENTE</th><th>DOCENTE SOSTITUTO</th><th>TRATT.</th><th>FIRMA</th></tr></thead><tbody>
            <?php foreach($ss as $s): if(!empty($s['sostituto'])): ?>
                <tr><td><?php echo $s['ora'];?></td><td><?php echo $s['classe'];?></td><td><?php echo $s['assente'];?></td><td><?php echo $s['sostituto'];?></td>
                <td class="<?php echo ($s['trattamento']=='ECCEDENZA')?'evidenzia-ecc':'';?>"><?php echo $s['trattamento'];?></td><td style="width:100px;height:35px;"></td></tr>
            <?php endif; endforeach; ?>
            <?php for($i=0; $i<$config['righe_manuali']; $i++): ?><tr><td style="height:35px;"></td><td></td><td></td><td></td><td></td><td></td></tr><?php endfor; ?>
        </tbody></table>
        <div style="text-align:center; font-size:7px; color:#888; margin-top:1mm;"><?php echo htmlspecialchars($config['credits']); ?></div>
        <div style="display:flex; justify-content:space-between; margin-top:20px;">
            <div>RESPONSABILI DEL SERVIZIO<br><strong><?php echo $res; ?></strong></div>
            <div style="text-align:right;">DIRIGENTE SCOLASTICO<br><strong><?php echo $dir; ?></strong></div>
        </div>
    </div>
    
    <div class="<?php echo (isset($bozza['stampa_tagliandi']) && $bozza['stampa_tagliandi'] == 'on') ? '' : 'no-print'; ?>">
        <div class="page-break"></div>
        <div class="area-stampa">
            <?php $pr = []; foreach($ss as $s){ if(!empty($s['sostituto'])){ $nk = mb_strtoupper(trim($s['sostituto']),'UTF-8'); $pr[$nk]['orig']=trim($s['sostituto']); $pr[$nk]['ore'][]=$s; }}
            foreach($pr as $d): ?>
                <div class="tagliando">
                    <div style="display:flex; justify-content:space-between; font-weight:bold; border-bottom:1px solid #000; margin-bottom:10px;"><span>SOSTITUZIONE INTERNA</span><span>Data: <?php echo date('d/m/Y', strtotime($dg));?></span></div>
                    <p>Docente: <strong><?php echo $d['orig'];?></strong></p>
                    <table style="width:100%;"><thead><tr><th>Ora</th><th>Classe</th><th>Assente</th><th>Trattamento</th></tr></thead><tbody>
                    <?php foreach($d['ore'] as $o): ?><tr><td><?php echo $o['ora'];?></td><td><?php echo $o['classe'];?></td><td><?php echo $o['assente'];?></td><td class="<?php echo ($o['trattamento']=='ECCEDENZA')?'evidenzia-ecc':'';?>"><?php echo $o['trattamento'];?></td></tr><?php endforeach; ?>
                    </tbody></table>
                    <div style="text-align:center; font-size:7px; color:#888; margin-top:1mm;"><?php echo htmlspecialchars($config['credits']); ?></div>
                    <div style="display:flex; justify-content:space-between; margin-top:20px;">
                        <div>RESPONSABILI<br><strong><?php echo $res;?></strong></div>
                        <div style="text-align:right;">DIRIGENTE<br><strong><?php echo $dir;?></strong></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<div class="no-print" style="text-align:center; margin-top:40px; font-size:10px; color:#95a5a6;"><?php echo htmlspecialchars($config['credits']); ?></div>

<?php if($mostra_stampa): ?>
<script>
// Genera il QR code per il tabellone online
document.addEventListener('DOMContentLoaded', function() {
    if (typeof QRCode !== 'undefined') {
        // Costruisce l'URL completo del tabellone
        var protocol = window.location.protocol;
        var host = window.location.host;
        var path = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        var tabelloneUrl = protocol + '//' + host + path + '<?php echo $config['url_tabellone']; ?>';
        
        // Genera il QR code
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            text: tabelloneUrl,
            width: 75,
            height: 75,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>