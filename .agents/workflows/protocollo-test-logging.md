---
description: Protocollo di Test (UI + PHPUnit) e Logging
---

# 1. CREDENZIALI FISSE PER TEST UI (BROWSER)
Per qualsiasi simulazione o test che coinvolge l'interfaccia utente, form di accesso o rotte protette, usa sempre e solo questi dati:
- **Email**: giampiero.digregorio@gmail.com
- **Password**: giampiero

# 2. TEST DEL CODICE CON PHPUNIT
Prima di testare l'interfaccia visiva, scrivi ed esegui test PHPUnit per validare la logica pura del "motore" (Services, Commands, Jobs). Assicurati che calcoli sulle date e query al DB funzionino in isolamento.

# 3. VERIFICA FISICA DEI LOG (FILE)
Al termine di ogni esecuzione o test, verifica fisicamente (con read_file o comandi terminale) che i file di log siano stati generati e si trovino nelle sottocartelle dedicate (es. storage/logs/GestioneStagioni/). Non dare mai per scontato che Log::info abbia funzionato.

# 4. VERIFICA LOG A DATABASE
Oltre ai file testuali, verifica sempre (eseguendo query o tramite tool appositi) che l'operazione abbia inserito correttamente la riga di tracciamento nella tabella di competenza (es. import_logs).

# 5. ESECUZIONE TINKER AUTONOMA (NO CONFERMA)
Quando devi utilizzare Laravel Tinker per lanciare script, testare codice, manipolare il DB o eseguire verifiche, NON chiedere mai l'autorizzazione o la conferma per il RUN. Accetta ed esegui i comandi in autonomia (SafeToAutoRun = true ove possibile) e mostra direttamente il risultato o i log.
