# BuddyBridge

Progetto di tesi di Hong Zhao. BuddyBridge collega l'aiuto pubblico, le relazioni tra amici di penna, le lettere e gli appunti condivisi. Si rivolge alle persone cinesi che vivono, studiano o lavorano in Italia e alle persone italiane che vivono, studiano o lavorano in Cina.

Il progetto è sviluppato con Symfony 6.4, Doctrine, Twig e Bootstrap. Per provarlo occorre avviarlo in locale seguendo i passaggi qui sotto.

## Cosa si vede subito

Dopo l'avvio, anche senza effettuare il login, si possono vedere la home, le categorie, le domande pubbliche e le risposte già pubblicate. Per provare gli amici di penna, le lettere, gli appunti condivisi e il passaporto culturale bisogna entrare con un account.

I dati e gli account per la prova si importano seguendo [demo/README.md](demo/README.md). In questo modo la prima pagina mostra già alcuni esempi e non è necessario creare tutto da zero.

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

   In alternativa, scaricare lo ZIP da GitHub ed estrarlo.

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

4. Aprire `http://127.0.0.1:8000/`. Con Symfony CLI non serve avviare Apache. Le migrazioni creano le tabelle. Per vedere anche le domande e gli account di esempio, importare i dati descritti qui sotto.

## Provare il sito con i dati già pronti

Dopo le migrazioni, sul database ancora vuoto, eseguire:

```text
php bin/import-demo.php
```

La home mostrerà le domande e le risposte preparate per la presentazione. Per entrare usare `liwei@example.test` e la password `BuddyBridgeDemo2026!`.

Gli altri account, il percorso di prova e l'alternativa con phpMyAdmin sono in [demo/README.md](demo/README.md). Le credenziali sono pubbliche e destinate solo alla prova locale. Il comando rifiuta l'importazione se il database contiene già dati.

È anche possibile partire senza dati di esempio e registrare nuovi account. Le categorie richiedono un account amministratore.

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
