<?php

/**
 * Configurazione Database
 * 
 * Questo file contiene le impostazioni di connessione al database.
 * La classe DB.php legge questo file per stabilire la connessione.
 * 
 * Driver supportati: mysql, pgsql, sqlite
 */

return [
    // Driver del database: 'mysql', 'pgsql', o 'sqlite'
    'driver' => 'pgsql',

    // Configurazione per SQLite
    //'sqlite_database' => __DIR__ . '/../db.sqlite', // Percorso al file SQLite

    // Configurazione per MySQL/PostgreSQL
    'host' => 'localhost',
    'port' => 5432, // 3306 per MySQL, 5432 per PostgreSQL
    'database' => 'my_db',
    'username' => 'postgres',
    'password' => 'postgres',
    //'charset' => 'utf8mb4', // Solo per MySQL

    // Opzioni PDO aggiuntive (opzionale)
    // Le opzioni di default sono già gestite da DB.php:
    // - PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    // - PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    // - PDO::ATTR_EMULATE_PREPARES => false
    'options' => [
        // Aggiungi qui opzioni PDO personalizzate se necessario
    ],
];