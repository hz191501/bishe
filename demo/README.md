# Dati per la prova

Questa copia permette di aprire il sito con le domande e le risposte già preparate per la presentazione. Contiene 14 account, 6 categorie, 12 domande, 10 risposte e le relazioni tra amici di penna.

Gli account hanno email e password dedicate alla prova. Le lettere sono esempi; il quaderno contiene una risposta salvata e due aggiunte. Non si tratta di dati raccolti da una ricerca con utenti. Le notifiche del database originale non sono incluse.

## Importazione

Dopo l'installazione descritta nel README principale e l'esecuzione delle migrazioni, usare un database ancora vuoto e avviare:

```text
php bin/import-demo.php
```

Il comando si ferma se trova dati già presenti. Non cancella o sostituisce account. Se il database contiene già delle prove, crearne uno nuovo e indicarlo in `.env.local`, poi ripetere creazione del database e migrazioni.

In alternativa, in phpMyAdmin selezionare il database vuoto già preparato con le migrazioni, aprire **Importa** e scegliere `demo/demo.sql`, con codifica UTF-8. Il file contiene solo dati: non crea il database o le tabelle. Importarlo una sola volta e non usarlo sul database personale.

## Account

La password per questi account è `BuddyBridgeDemo2026!`.

| Email | Nome | Uso |
| --- | --- | --- |
| liwei@example.test | Li Wei | Domande, lettere e quaderno |
| admin@example.test | Lin | Risposte, quaderno e gestione delle categorie |
| giulia@example.test | Giulia | Secondo account ordinario per le interazioni |

Gli altri account usano `utente<ID>@example.test`, con gli ID presenti nella tabella `user`. La password è la stessa. Queste credenziali sono pubbliche e servono solo per la prova locale.

## Un percorso breve

1. Aprire la home: le domande sono già visibili.
2. Entrare come Li Wei e aprire la domanda **Come posso presentarmi correttamente in italiano?** (`/bridge/task/3`). Ci sono già due risposte e una risposta migliore.
3. Da **Amici di penna**, aprire la relazione con Lin (`/amici-di-penna/1/messaggi`). Si possono leggere le lettere e aprire il quaderno **Presentarsi in italiano**.
4. Usare un'altra finestra del browser per entrare come Lin. Aggiungere un esempio al quaderno o rispondere con una lettera, poi tornare alla finestra di Li Wei.
5. Per provare il salvataggio, tornare alla domanda, scegliere **Salva nel quaderno** sulla seconda risposta e selezionare Lin. La prima risposta è già salvata: ripetendo il salvataggio si apre la nota esistente.

Le modifiche fatte durante la prova restano nel database di chi ha installato il progetto.