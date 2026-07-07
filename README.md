# mfaadmin — Admin MFA & Passkey per PrestaShop

Modulo per PrestaShop che aggiunge l'autenticazione a due fattori (TOTP + Passkey/WebAuthn) al login del back office, per tutti gli employee.

## Requisiti

- PrestaShop 8.0 – 8.2 (testato su 8.1)
- PHP 8.1+
- Estensione PHP `openssl` (cifratura dei secret TOTP a riposo)
- HTTPS in produzione per l'autenticazione via Passkey (WebAuthn richiede un "secure context"; è esente solo `localhost` in sviluppo)

## Funzionalità

- **TOTP** (RFC 6238, compatibile con Google Authenticator, Authy, ecc.) con QR code di setup
- **Passkey / WebAuthn (FIDO2)** come secondo fattore alternativo, con supporto multi-dispositivo
- **Codici di recupero** monouso (10 per attivazione, rigenerabili) per l'accesso in caso di perdita del dispositivo
- **Blocco temporaneo** dopo tentativi falliti ripetuti (TOTP o codice di recupero), con email di allerta
- Pagina profilo personale per ogni employee (attivazione/disattivazione, gestione passkey, rigenerazione codici)
- Pagina di configurazione globale (MFA obbligatorio per tutti, disattivazione di emergenza, whitelist controller per i cron, email di allerta, reset MFA di un altro employee — riservato al Super Admin)

## Installazione

1. Copia la cartella `mfaadmin/` in `modules/`
2. Da Back Office -> Moduli, cerca "Admin MFA & Passkey" e installa
3. Vai su **Parametri avanzati -> Configurazione MFA** per le impostazioni globali, oppure sul menu employee -> **Profilo MFA** per attivare l'MFA sul proprio account

Le dipendenze Composer (`vendor/`) sono già incluse nel repository: non è necessario eseguire `composer install` per usare il modulo. Per contribuire allo sviluppo, `composer install` rigenera `vendor/` dalle stesse versioni bloccate in `composer.lock`.

## Come funziona

Il modulo si aggancia agli hook `actionAdminControllerInitBefore` (controller "legacy") e `actionDispatcherBefore` (pagine migrate a Symfony), eseguiti prima di ogni pagina del back office: se l'employee autenticato ha l'MFA attivo (o l'MFA è reso obbligatorio globalmente) e non ha ancora verificato il secondo fattore per la sessione corrente, viene reindirizzato alla pagina di verifica. Lo stato "verificato" è tracciato in un cookie dedicato (non nella sessione PHP), così da sopravvivere alla navigazione ma essere sempre invalidato al logout.

I secret TOTP sono cifrati a riposo (AES-256-GCM) con una chiave derivata dal salt di installazione di PrestaShop (`_COOKIE_KEY_`); non è richiesta alcuna chiave aggiuntiva da configurare o proteggere separatamente. I codici di recupero sono salvati come hash bcrypt e sono monouso.

## Licenza

Vedi il file `LICENSE` nella root del repository.

## Sicurezza

Per segnalare una vulnerabilità, apri una issue privata (GitHub Security Advisory) invece di una issue pubblica.
