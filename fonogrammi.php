<?php
session_start();
if (!isset($_SESSION['autenticato'])) { die("Accesso negato."); }

$cartella_db = "archivio/";
$file_fonogrammi = $cartella_db . "registro_chiamate.json";
$file_disponibili = $cartella_db . "lista_disponibili.json";

// Crediti estratti da index.php
$credits = "Crediti e Sviluppo: Sebastiano BASILE – F.S. Area 2";

$registro = file_exists($file_fonogrammi) ? json_decode(file_get_contents($file_fonogrammi), true) : [];
$disponibili = file_exists($file_disponibili) ? json_decode(file_get_contents($file_disponibili), true) : [];

// --- LOGICA DI GESTIONE ---

// 1. Aggiunta o Modifica docente (Contenitore)
if (isset($_POST['salva_disponibile'])) {
    $nuovo_doc = [
        "nome" => strtoupper(trim($_POST['nuovo_nome'])),
        "tel"  => trim($_POST['nuovo_tel']),
        "note" => trim($_POST['nuove_note'] ?? '')
    ];
    if ($_POST['edit_index'] !== "") { $disponibili[$_POST['edit_index']] = $nuovo_doc; } 
    else { $disponibili[] = $nuovo_doc; }
    usort($disponibili, function($a, $b) { return strcmp($a['nome'], $b['nome']); });
    file_put_contents($file_disponibili, json_encode($disponibili));
    header("Location: fonogrammi.php"); exit;
}

// 2. Eliminazione record fonogramma
if (isset($_GET['del_fonog'])) {
    array_splice($registro, $_GET['del_fonog'], 1);
    file_put_contents($file_fonogrammi, json_encode($registro));
    header("Location: fonogrammi.php"); exit;
}

// 3. Eliminazione docente dal contenitore
if (isset($_GET['del_disp'])) {
    array_splice($disponibili, $_GET['del_disp'], 1);
    file_put_contents($file_disponibili, json_encode($disponibili));
    header("Location: fonogrammi.php"); exit;
}

// 4. Registrazione o Modifica chiamata (Registro Cronologico)
if (isset($_POST['registra_chiamata'])) {
    $docente_nome = ($_POST['docente'] === 'ALTRO') ? strtoupper(trim($_POST['altro_nome'])) : $_POST['docente'];
    
    if (!empty($_POST['data_differita'])) {
        $data_display = date("d/m/Y H:i", strtotime($_POST['data_differita']));
    } else {
        $data_display = $_POST['data_originale_hidden'] ?: date("d/m/Y H:i:s");
    }

    $nuova_voce = [
        "timestamp" => $data_display,
        "docente"   => $docente_nome,
        "tel"       => $_POST['tel_chiamata'],
        "ora_sost"  => $_POST['ora_sost'],
        "esito"     => $_POST['esito'], 
        "note"      => $_POST['note_chiamata']
    ];

    if ($_POST['edit_chiamata_index'] !== "") {
        $registro[$_POST['edit_chiamata_index']] = $nuova_voce;
    } else {
        array_unshift($registro, $nuova_voce);
    }

    usort($registro, function($a, $b) {
        $t1 = strtotime(str_replace('/', '-', $a['timestamp']));
        $t2 = strtotime(str_replace('/', '-', $b['timestamp']));
        return $t2 - $t1;
    });

    file_put_contents($file_fonogrammi, json_encode($registro));
    header("Location: fonogrammi.php?saved=1"); exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registro Fonogrammi ed Eccedenze</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; font-size: 14px; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .grid { display: grid; grid-template-columns: 400px 1fr; gap: 20px; }
        .box { border: 1px solid #d1d8e0; padding: 15px; border-radius: 8px; background: #fff; height: fit-content; }
        h3 { margin-top: 0; color: #2980b9; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 8px; }
        .btn { cursor: pointer; padding: 8px 12px; border: none; border-radius: 4px; font-weight: bold; color: white; text-decoration: none; }
        .btn-save { background: #27ae60; width: 100%; margin-top: 5px; font-size: 16px; }
        .btn-update { background: #f39c12 !important; }
        .differita-alert { background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; border-radius: 5px; margin-bottom: 10px; font-size: 12px; color: #856404; }
        .badge-note { background: #e8f4fd; color: #2980b9; padding: 3px 6px; border-radius: 4px; display: block; margin-top: 4px; font-size: 11px; border: 1px solid #d1e9f9; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #34495e; color: white; }
        .Accettata { color: #27ae60; font-weight: bold; }
        .Rifiutata { color: #c0392b; font-weight: bold; }
        .Irreperibile { color: #f39c12; font-weight: bold; }
        .btn-edit { background: #f39c12; font-size: 10px; padding: 3px 6px; }
        .btn-del-small { background: #e74c3c; font-size: 10px; padding: 3px 6px; }
        #input_altro { background: #fff5f5; border: 2px solid #e74c3c; display: none; }
        .collapsible-trigger { background: #ecf0f1; color: #2c3e50; width: 100%; text-align: left; padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px; margin-top: 15px; display: flex; justify-content: space-between; align-items: center; }
        .collapsible-content { display: none; max-height: 400px; overflow-y: auto; padding: 10px; border: 1px solid #eee; border-top: none; background: #fafafa; }
        .footer-credits { text-align: center; margin-top: 30px; font-size: 10px; color: #95a5a6; border-top: 1px solid #eee; padding-top: 10px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

<div class="container">
    <div class="no-print">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <a href="index.php" style="font-weight:bold; color:#2980b9; text-decoration:none;">⬅️ TORNA AL PANNELLO</a>
            <button class="btn" onclick="window.print()" style="background:#7f8c8d">🖨️ STAMPA REGISTRO</button>
        </div>

        <div class="grid">
            <div class="box">
                <h3>👥 Gestione Docenti Disponibili</h3>
                <form method="post" id="form_docente">
                    <input type="hidden" name="edit_index" id="edit_index" value="">
                    <input type="text" name="nuovo_nome" id="edit_nome" placeholder="Cognome e Nome" required>
                    <input type="text" name="nuovo_tel" id="edit_tel" placeholder="Telefono" required>
                    <input type="text" name="nuove_note" id="edit_note" placeholder="Disponibilità (es: Lun 1^ e 4^)">
                    <button type="submit" name="salva_disponibile" id="btn_doc_submit" class="btn" style="background:#3498db; width:100%;">AGGIUNGI / SALVA</button>
                    <button type="button" onclick="resetFormDoc()" id="btn_doc_reset" class="btn" style="background:#bdc3c7; width:100%; margin-top:5px; display:none;">ANNULLA</button>
                </form>
                
                <button type="button" class="collapsible-trigger" onclick="toggleDocList()">
                    <span>📂 Docenti disponibili: Mostra/Nascondi (<?php echo count($disponibili); ?>)</span>
                    <span id="list-arrow">▼</span>
                </button>

                <div id="lista-docenti-collapse" class="collapsible-content">
                    <?php foreach($disponibili as $i => $d): ?>
                        <div style="padding: 10px 0; border-bottom: 1px solid #ddd;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div><strong><?php echo $d['nome']; ?></strong><br><small>📞 <?php echo $d['tel']; ?></small></div>
                                <div style="display:flex; gap:3px;">
                                    <a href="javascript:void(0)" onclick="editDoc(<?php echo $i; ?>, '<?php echo addslashes($d['nome']); ?>', '<?php echo addslashes($d['tel']); ?>', '<?php echo addslashes($d['note']); ?>')" class="btn btn-edit">EDIT</a>
                                    <a href="?del_disp=<?php echo $i; ?>" onclick="return confirm('Eliminare?')" class="btn btn-del-small">X</a>
                                </div>
                            </div>
                            <?php if(!empty($d['note'])): ?><span class="badge-note">🗓️ <?php echo htmlspecialchars($d['note']); ?></span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="box" style="border-left: 5px solid #27ae60;">
                <h3 id="titolo_modulo_chiamata">✍️ Registra Fonogramma per ora in eccedenza</h3>
                <form method="post" id="form_chiamata">
                    <input type="hidden" name="edit_chiamata_index" id="edit_chiamata_index" value="">
                    <input type="hidden" name="data_originale_hidden" id="data_originale_hidden" value="">
                    <div class="differita-alert">
                        <strong>🕒 Data/Ora:</strong> Lascia vuoto per ora attuale o correggi per differita.
                        <input type="datetime-local" name="data_differita" id="data_differita" style="margin-top:5px; border: 1px solid #ffeeba;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label>Docente:</label>
                            <select name="docente" id="select_docente" onchange="updateInfo(this)" required>
                                <option value="">-- Seleziona --</option>
                                <?php foreach($disponibili as $d): ?><option value="<?php echo $d['nome']; ?>" data-tel="<?php echo $d['tel']; ?>"><?php echo $d['nome']; ?></option><?php endforeach; ?>
                                <option value="ALTRO">-- ALTRO (Non in elenco) --</option>
                            </select>
                            <input type="text" name="altro_nome" id="input_altro" placeholder="SCRIVI NOME DOCENTE">
                        </div>
                        <div><label>Telefono:</label><input type="text" name="tel_chiamata" id="tel_chiamata" placeholder="Automatico"></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label>Esito:</label>
                            <select name="esito" id="esito_chiamata">
                                <option value="Rifiutata">❌ RIFIUTATA</option>
                                <option value="Accettata">✅ ACCETTATA</option>
                                <option value="Irreperibile">📡 IRREPERIBILE</option>
                            </select>
                        </div>
                        <div><label>Ora/Classe:</label><input type="text" name="ora_sost" id="ora_sost" placeholder="Es: 4^ ora - 3B" required></div>
                    </div>
                    <textarea name="note_chiamata" id="note_chiamata" rows="2" placeholder="Note sulla conversazione..."></textarea>
                    <button type="submit" name="registra_chiamata" id="btn_chiamata_submit" class="btn btn-save">💾 SALVA NEL REGISTRO</button>
                    <button type="button" onclick="resetFormChiamata()" id="btn_chiamata_reset" class="btn" style="background:#bdc3c7; width:100%; margin-top:5px; display:none;">ANNULLA MODIFICA</button>
                </form>
            </div>
        </div>
    </div>

    <h3>📋 Registro cronologico chiamate ore eccedenti – I.C. Capuana-De Amicis</h3>
    <table>
        <thead>
            <tr>
                <th style="width:15%;">Data/Ora</th>
                <th style="width:18%;">Docente</th>
                <th style="width:12%;">Telefono</th>
                <th style="width:15%;">Sostituzione</th>
                <th style="width:10%;">Esito</th>
                <th>Note</th>
                <th class="no-print" style="width:12%;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registro as $idx => $c): ?>
            <tr>
                <td><strong><?php echo $c['timestamp']; ?></strong></td>
                <td><?php echo htmlspecialchars($c['docente']); ?></td>
                <td><a href="tel:<?php echo $c['tel']; ?>" style="text-decoration:none; color:#2980b9; font-weight:bold;"><?php echo htmlspecialchars($c['tel']); ?></a></td>
                <td><?php echo htmlspecialchars($c['ora_sost']); ?></td>
                <td class="<?php echo $c['esito']; ?>"><?php echo strtoupper($c['esito']); ?></td>
                <td><small><?php echo htmlspecialchars($c['note'] ?? ''); ?></small></td>
                <td class="no-print" style="white-space:nowrap;">
                    <button onclick="preparaModificaChiamata(<?php echo $idx; ?>, <?php echo htmlspecialchars(json_encode($c)); ?>)" class="btn btn-edit">MODIFICA</button>
                    <a href="?del_fonog=<?php echo $idx; ?>" class="btn btn-del-small" onclick="return confirm('Eliminare?')">X</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-credits no-print">
        <?php echo htmlspecialchars($credits); ?>
    </div>
</div>

<script>
function preparaModificaChiamata(index, dati) {
    document.getElementById('titolo_modulo_chiamata').innerText = "✏️ Modifica Chiamata";
    document.getElementById('btn_chiamata_submit').innerText = "AGGIORNA RECORD";
    document.getElementById('btn_chiamata_submit').classList.add('btn-update');
    document.getElementById('btn_chiamata_reset').style.display = "block";
    document.getElementById('edit_chiamata_index').value = index;
    document.getElementById('data_originale_hidden').value = dati.timestamp;
    document.getElementById('tel_chiamata').value = dati.tel;
    document.getElementById('ora_sost').value = dati.ora_sost;
    document.getElementById('esito_chiamata').value = dati.esito;
    document.getElementById('note_chiamata').value = dati.note || "";
    let select = document.getElementById('select_docente');
    let altroInput = document.getElementById('input_altro');
    let found = false;
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === dati.docente) { select.selectedIndex = i; found = true; break; }
    }
    if (!found) { select.value = "ALTRO"; altroInput.style.display = "block"; altroInput.value = dati.docente; } 
    else { altroInput.style.display = "none"; }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetFormChiamata() {
    document.getElementById('form_chiamata').reset();
    document.getElementById('edit_chiamata_index').value = "";
    document.getElementById('data_originale_hidden').value = "";
    document.getElementById('titolo_modulo_chiamata').innerText = "✍️ Registra Fonogramma";
    document.getElementById('btn_chiamata_submit').innerText = "💾 SALVA NEL REGISTRO";
    document.getElementById('btn_chiamata_submit').classList.remove('btn-update');
    document.getElementById('btn_chiamata_reset').style.display = "none";
    document.getElementById('input_altro').style.display = "none";
}

function toggleDocList() {
    var content = document.getElementById("lista-docenti-collapse");
    var arrow = document.getElementById("list-arrow");
    if (content.style.display === "block") { content.style.display = "none"; arrow.innerText = "▼"; } 
    else { content.style.display = "block"; arrow.innerText = "▲"; }
}

function updateInfo(select) {
    var telInput = document.getElementById('tel_chiamata');
    var altroInput = document.getElementById('input_altro');
    var selectedOption = select.options[select.selectedIndex];
    if(select.value === 'ALTRO') { altroInput.style.display = 'block'; altroInput.required = true; telInput.value = ''; } 
    else { altroInput.style.display = 'none'; altroInput.required = false; telInput.value = selectedOption.getAttribute('data-tel') || ''; }
}

function editDoc(index, nome, tel, note) {
    document.getElementById('edit_index').value = index;
    document.getElementById('edit_nome').value = nome;
    document.getElementById('edit_tel').value = tel;
    document.getElementById('edit_note').value = note;
    document.getElementById('btn_doc_submit').innerText = "SALVA MODIFICHE";
    document.getElementById('btn_doc_submit').style.background = "#f39c12";
    document.getElementById('btn_doc_reset').style.display = "block";
    document.getElementById("lista-docenti-collapse").style.display = "block";
    document.getElementById("list-arrow").innerText = "▲";
    window.scrollTo(0,0);
}

function resetFormDoc() {
    document.getElementById('form_docente').reset();
    document.getElementById('edit_index').value = "";
    document.getElementById('btn_doc_submit').innerText = "AGGIUNGI / SALVA";
    document.getElementById('btn_doc_submit').style.background = "#3498db";
    document.getElementById('btn_doc_reset').style.display = "none";
}
</script>
</body>
</html>