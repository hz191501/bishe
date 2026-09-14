# BuddyBridge

Progetto di tesi di Hong Zhao. BuddyBridge collega l'aiuto pubblico, le relazioni tra amici di penna, le lettere e gli appunti condivisi. Si rivolge alle persone cinesi che vivono, studiano o lavorano in Italia e alle persone italiane che vivono, studiano o lavorano in Cina.

Il progetto è sviluppato con Symfony 6.4, Doctrine, Twig e Bootstrap. Per provarlo occorre avviarlo in locale seguendo i passaggi qui sotto.

## Requisiti

- PHP 8.2 o successivo compatibile con `composer.lock`; estensioni PDO MySQL, mbstring, intl e quelle richieste da Composer. Per i test serve anche PDO SQLite.
- Composer 2 e Symfony CLI.
- MariaDB: ambiente di sviluppo XAMPP con MariaDB 10.4.32. Se si usa una versione diversa, indicarla in `DATABASE_URL`.
- Connessione Internet per installare le dipendenze e caricare le risorse esterne dell'interfaccia.

Non serve installare Node.js.

## Installazione locale

1. Clonare il repository e aprire la cartella:

   ```text
   git clone https://github.com/hz191501/bishe.git
   cd bishe
   ```

   Per un repository privato è necessario un account autorizzato. In alternativa, scaricare lo ZIP da GitHub ed estrarlo.

2. Copiare `.env.example` in `.env` (in PowerShell: `Copy-Item .env.example .env`). Impostare utente e password del proprio database in `DATABASE_URL`, usando un database nuovo dedicato al progetto. Codificare gli eventuali caratteri speciali della password per l'uso in un URL. Generare un segreto locale con il comando seguente e copiarne il risultato in `APP_SECRET`:

   ```text
   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
   ```

   `.env` e `.env.local` sono esclusi da Git. Il file di esempio non contiene le credenziali del computer dell'autore.

3. Avviare MariaDB (in XAMPP, il servizio è chiamato MySQL), quindi eseguire:

   ```text
   composer install
   php bin/console doctrine:database:create --if-not-exists
   php bin/console doctrine:migrations:migrate --no-interaction
   php bin/console doctrine:schema:validate
   symfony server:start --no-tls --port=8000
   ```

4. Aprire `http://127.0.0.1:8000/`. Con Symfony CLI non serve avviare Apache. I comandi di migrazione creano la struttura; il repository non contiene gli account, le lettere o il database personale dell'autore.

## Preparare una prova con due account

1. Registrare due account da `/register`, scegliendo email e password locali. Usare due browser o una finestra privata per tenerli aperti contemporaneamente.
2. Il database nuovo non contiene categorie. Per preparare l'ambiente locale, in phpMyAdmin selezionare **solo il nuovo database BuddyBridge** ed eseguire la seguente istruzione, sostituendo l'email con quella del primo account appena registrato:

   ```sql
   UPDATE user SET roles = '["ROLE_ADMIN"]' WHERE email = 'indirizzo-del-primo-account@example.test';
   ```

3. Uscire e rientrare con il primo account. Aprire `/category/new` e creare una categoria, per esempio `Lingua`.
4. Pubblicare una domanda da `/bridge/task/new`. Con il secondo account, rispondere alla domanda. Il suo autore può scegliere la risposta migliore.
5. Inviare una richiesta di amicizia di penna e accettarla con l'altro account. Aprire la relazione da `/amici-di-penna` per scambiare lettere.
6. Su una risposta pubblica scegliere **Salva nel quaderno** e selezionare la relazione accettata. L'autore può modificare la propria nota; entrambi possono aggiungere contributi.
7. Aprire `/passaporto-culturale` per vedere le attività registrate.

La rimozione di una relazione interrompe l'accesso alle lettere e agli appunti senza cancellarne la cronologia; una nuova richiesta accettata ripristina l'accesso.

## Orientarsi nel codice

| Percorso | Contenuto |
| --- | --- |
| `src/Controller/` | Pagine, azioni e controlli di accesso |
| `src/Entity/` e `src/Repository/` | Dati, relazioni e interrogazioni |
| `src/Form/` | Moduli e validazione |
| `src/Service/` | Indicatori di partecipazione e notifiche |
| `templates/` | Pagine Twig |
| `assets/` e `public/images/` | JavaScript, CSS e immagini |
| `config/` | Configurazione Symfony |
| `migrations/` | Evoluzione della struttura del database |
| `tests/` | Controlli automatici già presenti nel progetto |

Alcuni commenti nel codice e la guida `PROJECT_GUIDE.md` sono in cinese.

## Controlli disponibili

```text
php vendor/bin/phpunit
php bin/console lint:twig templates
php bin/console lint:yaml config
```

I test degli appunti usano un database SQLite in memoria.
