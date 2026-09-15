# BuddyBridge

BuddyBridge è il progetto sviluppato da Hong Zhao per la tesi di diploma. La piattaforma è rivolta principalmente alle persone cinesi che vivono, studiano o lavorano in Italia e alle persone italiane che vivono, studiano o lavorano in Cina.

Il progetto aiuta gli utenti ad affrontare problemi concreti legati alla lingua, alla vita quotidiana, allo studio e alle differenze culturali. La sua caratteristica principale è il collegamento tra l'aiuto pubblico e la possibilità di costruire, in modo volontario, una relazione più personale e continuativa.

## Caratteristiche del progetto

- **Dall'aiuto pubblico a una relazione privata e volontaria:** il contatto inizia attraverso domande, risposte e commenti visibili alla comunità. Se due persone desiderano continuare lo scambio, una può inviare una richiesta di amicizia di penna. Soltanto dopo l'accettazione dell'altra persona diventano disponibili le lettere e il quaderno condiviso.
- **Bridge Task:** gli utenti possono pubblicare richieste relative alla lingua, alla vita universitaria, ai documenti, alle abitudini culturali e alla vita quotidiana. Gli altri utenti possono rispondere e commentare; l'autore della domanda può scegliere la risposta più utile.
- **Quaderno condiviso:** una risposta pubblica considerata utile può essere conservata nella relazione tra due amici di penna. Entrambi possono aggiungere esempi, osservazioni e informazioni, creando una memoria comune dello scambio.
- **Suggerimenti comprensibili:** i possibili amici di penna vengono ordinati considerando lingue complementari, nazionalità diversa e interessi comuni. La pagina mostra anche il motivo del suggerimento. Genere ed età non partecipano al calcolo.
- **Elementi ispirati ai giochi:** la sezione **Il mio viaggio - Passaporto culturale** presenta traguardi e punti XP ottenuti attraverso le attività svolte. Anche le relazioni tra amici di penna possono avanzare di livello grazie alle lettere, agli appunti e alla continuità dello scambio.
- **Continuità della relazione:** domande, risposte, lettere, appunti condivisi, traguardi e livelli formano un percorso collegato. Questi elementi cercano di incoraggiare la partecipazione nel tempo e lo sviluppo di relazioni più durature.

Traguardi, punti XP e livelli mostrano soltanto le attività registrate nel sito. Servono a rendere visibile il percorso compiuto e non valutano la profondità o la qualità reale di un'amicizia.

## Tecnologia utilizzata

BuddyBridge è stato sviluppato con Symfony 6.4, PHP, Doctrine ORM, Twig, Bootstrap e MariaDB. Symfony gestisce le pagine e le operazioni, Doctrine collega le entità al database, Twig costruisce l'interfaccia e Bootstrap contribuisce alla disposizione delle pagine su computer e telefono.

## Cosa si vede subito

Dopo l'avvio, anche senza effettuare il login, si possono vedere la home, le categorie, le domande pubbliche e le risposte già pubblicate. Per provare gli amici di penna, le lettere, il quaderno condiviso e **Il mio viaggio - Passaporto culturale** bisogna entrare con un account.

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
