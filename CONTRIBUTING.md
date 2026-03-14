# Come Contribuire

Grazie per l'interesse nel migliorare questo progetto! Ogni contributo è benvenuto.

## Segnalare un bug

1. Vai alla sezione **Issues** del repository
2. Clicca su **New Issue**
3. Descrivi il problema con più dettagli possibili:
   - Cosa hai fatto
   - Cosa ti aspettavi che succedesse
   - Cosa è successo invece
   - Versione di PHP e server web in uso
   - Screenshot se utile

## Proporre una nuova funzione

1. Apri una **Issue** con il tag `enhancement`
2. Descrivi la funzione e il problema che risolverebbe

## Inviare una Pull Request

1. Fai un **Fork** del repository
2. Crea un branch per la tua modifica:
   ```bash
   git checkout -b feature/nome-funzione
   ```
3. Fai le tue modifiche
4. Testa le modifiche sul tuo server locale
5. Esegui il commit con un messaggio descrittivo:
   ```bash
   git commit -m "feat: Aggiunge esportazione CSV del registro fonogrammi"
   ```
6. Fai il push del branch:
   ```bash
   git push origin feature/nome-funzione
   ```
7. Apri una **Pull Request** e descrivi le modifiche

## Linee guida per il codice

- Commenta le parti complesse in italiano
- Mantieni la compatibilità con PHP 7.4+
- Non includere mai dati reali (nomi, IP, password) nel codice
- Usa `htmlspecialchars()` per l'output di dati utente (prevenzione XSS)

## Crediti

Tutti i contributori vengono menzionati nel file [README.md](README.md).
