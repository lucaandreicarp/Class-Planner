# Class Planner

Web app per la gestione e organizzazione delle interrogazioni scolastiche.

## Descrizione

Class Planner nasce dall'esigenza di sostituire i tradizionali fogli condivisi utilizzati per organizzare interrogazioni e impegni scolastici.

L'applicazione permette agli studenti di una classe di:
- visualizzare il calendario delle interrogazioni;
- prenotarsi per le interrogazioni;
- gestire eventi e impegni;
- distribuire meglio le interrogazioni nel tempo.

L'obiettivo è rendere più semplice e ordinata la pianificazione degli impegni scolastici.

---

## Funzionalità

### Gestione classe
- Creazione di una nuova classe tramite codice condivisibile.
- Accesso alla classe tramite codice.
- Modifica delle informazioni della classe.
- Eliminazione della classe.

### Gestione materie
- Inserimento delle materie.
- Definizione dei giorni in cui ogni materia è presente nell'orario.

### Calendario
- Visualizzazione settimanale.
- Navigazione tra settimane.
- Visualizzazione interrogazioni ed eventi.

### Interrogazioni
- Creazione automatica degli slot disponibili in base all'orario.
- Prenotazione degli studenti.
- Rimozione della prenotazione.

### Eventi
- Creazione di eventi personalizzati.
- Visualizzazione nel calendario.

---

## Tecnologie utilizzate

- HTML
- CSS
- JavaScript
- PHP
- MySQL

---

## Struttura del progetto
class-planner/

├── public/
│ ├── index.html
│ ├── create_class.html
│ ├── class_planner.php
│ ├── css/
│ ├── js/
│ └── assets/
│
├── php/
│ ├── class_data.php
│ ├── events.php
│ ├── oral.php
│ └── delete_class.php
│
├── config/
│ └── database.php
│
├── sql/
│ └── schema.sql
│
└── README.md


---

## Installazione

### Requisiti

- Apache
- PHP
- MySQL

(Ad esempio tramite XAMPP)

### Configurazione database

1. Creare un database chiamato class_planner

2. Importare lo schema SQL presente nel progetto.

3. Modificare il file config/database.php  inserendo i dati della propria connessione MySQL.

---

## Avvio

Copiare il progetto nella cartella del server web.

Esempio con XAMPP:

htdocs/class-planner

Avviare Apache e MySQL e aprire localhost/class-planner/public


---

## Sviluppi futuri

Possibili miglioramenti:

- Interfaccia responsive per smartphone.
- Algoritmo automatico di distribuzione delle interrogazioni.
- Statistiche annuali della classe.
- Sistema di account.
- Miglioramento grafico dell'interfaccia.

---

## Autore

Luca - Andrei Carp
