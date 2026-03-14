# Changelog

Tutte le modifiche significative al progetto verranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/it/1.0.0/),
e il progetto segue il [Versionamento Semantico](https://semver.org/lang/it/).

---

## [1.0.0] – 2026

### Aggiunto
- Pannello di amministrazione (`index.php`) con autenticazione tramite password
- Gestione assenti giornalieri con numero di righe configurabile dinamicamente
- Tabella sostituzioni con Ora, Classe, Docente Assente, Sostituto e Trattamento
- Selezione del tipo di trattamento (Potenziamento, Servizio, ECCEDENZA, Banca Ore, Recupero, Altro)
- Navigazione tra le date per consultare lo storico
- Documento di servizio stampabile con intestazione istituto, dirigente e responsabile
- Generazione automatica di QR code puntato al tabellone online
- Stampa tagliandi individuali per ogni docente sostituto
- Tabellone digitale (`tabellone.php`) con visualizzazione ottimizzata per grandi schermi e TV
- Auto-refresh del tabellone configurabile
- Protezione del tabellone tramite password separata con cookie persistente (15 giorni)
- Gestione orario di pubblicazione (il tabellone appare solo nell'intervallo configurato)
- Registro fonogrammi ed eccedenze (`fonogrammi.php`)
- Rubrica docenti disponibili con nome, telefono e note sulle disponibilità
- Storico cronologico delle chiamate con esito (Accettata / Rifiutata / Irreperibile)
- Modifica e cancellazione di ogni record del registro
- Log degli accessi al tabellone (`admin_logs.php`) con statistiche giornaliere
- Pulizia archivio automatica (eliminazione file JSON > 30 giorni)
- Protezione della cartella `archivio/` tramite `.htaccess`
- Responsività per dispositivi mobili (smartphone e tablet)

---

## Prossime versioni (roadmap)

- [ ] File di configurazione separato (`config.php`) per evitare di modificare i file principali
- [ ] Supporto multi-plesso scolastico (es. primaria e secondaria separati)
- [ ] Esportazione del registro fonogrammi in PDF o CSV
- [ ] Notifiche via email o SMS al momento della pubblicazione del tabellone
- [ ] Interfaccia di configurazione via pannello web (senza modificare il codice PHP)
- [ ] Supporto database MySQL/MariaDB come alternativa ai file JSON
