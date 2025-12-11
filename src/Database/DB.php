<?php

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

// DB (utilizza PDO -> PHP Data Objects)
// Per evitare SQL Injection adottiamo un implementazione di query parametrizzate
// Es. DB::select("SELECT * FROM users WHERE id = :id", ["id" => 1])
// Es. DB::insert("INSERT INTO users (name, email) VALUES (:name, :email)", ["name" => "Mario", "email" => "mario@rossi.it"])

class DB
{
    private static ?PDO $connection = null;
    private static ?array $config = null;

    /**
     * Ottiene la connessione al db
     */
    private static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }
        return self::$connection;
    }

    /**
     * Crea una connessione al database
     */
    private static function createConnection(): PDO
    {
        $config = self::getConfig();
        $driver = $config['driver'] ?? 'pgsql';

        // Costruisce il DSN (Data Source Name) in base al driver
        switch ($driver) {
            case 'mysql':
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'] ?? 3306,
                    $config['database'],
                    $config['charset'] ?? 'utf8mb4'
                );
                break;

            case 'pgsql':
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $config['host'],
                    $config['port'] ?? 5432,
                    $config['database'],
                );
                break;

            case 'sqlite':
                $dsn = 'sqlite:' . $config['sqlite_database'];
                break;

            default:
                throw new RuntimeException("Driver database non supportato: $driver");
        }

        // Opzioni PDO per sicurezza
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $options = array_merge($defaultOptions, $config['options'] ?? []);

        try {
            // creiamo la connessione al database -> istanza PDO
            return new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, $options);
        } catch (PDOException $e) {
            throw new RuntimeException("Errore connessione database: " . $e->getMessage());
        }
    }

    /**
     * Carica la configurazione del database
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            $configPath = __DIR__ . '/../../config/database.php';
            if (!file_exists($configPath)) {
                throw new RuntimeException("File di configurazione database non trovato");
            }
            self::$config = require $configPath;
        }
        return self::$config;
    }

    /**
     * Esegue una query SELECT
     */
    public static function select(string $query, array $bindings = []): array
    {
        try {
            // 1. Prepara la query (prepared statement) - il database la analizza
            $stmt = self::connection()->prepare($query);

            // 2. Esegue la query sostituendo i valori ai placeholder
            // I valori vengono automaticamente escapati dal database
            $stmt->execute($bindings);

            // 3. Restituisce i risultati come array associativo
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException("Errore SELECT: " . $e->getMessage());
        }
    }

    /**
     * Esegue una query INSERT
     */
    public static function insert(string $query, array $bindings = []): int
    {
        try {
            $stmt = self::connection()->prepare($query);
            $stmt->execute($bindings);
            return (int)self::connection()->lastInsertId();
        } catch (PDOException $e) {
            throw new RuntimeException("Errore INSERT: " . $e->getMessage());
        }
    }

    /**
     * Esegue una query UPDATE
     */
    public static function update(string $query, array $bindings = []): int
    {
        try {
            $stmt = self::connection()->prepare($query);
            if(!empty($bindings)) {
                foreach($bindings as $param => $value) {
                    if (is_bool($value)) {
                        //PDO trasforma automaticamente i false in '' (stringa vuota) e il DB non riconoscendola, va in errore
                        $stmt->bindValue($param, $value, PDO::PARAM_BOOL); //i bool devono rimanere bool
                    } else if(is_array($value) || is_object($value)) {
                        continue;
                    } else {
                        $stmt->bindValue($param, $value);
                    }
                }
            }
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new RuntimeException("Errore UPDATE: " . $e->getMessage());
        }
    }

    /**
     * Esegue una query DELETE
     */
    public static function delete(string $query, array $bindings = []): int
    {
        try {
            $stmt = self::connection()->prepare($query);
            $stmt->execute($bindings);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new RuntimeException("Errore DELETE: " . $e->getMessage());
        }
    }

    
}
