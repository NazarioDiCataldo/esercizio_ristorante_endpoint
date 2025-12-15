<?php

namespace App\Models;

use App\Database\JSONDB;
use App\Database\DB;
use App\Utils\Request;

abstract class BaseModel
{
    public ?int $id = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    protected static string $collection;

    /**
     * Driver database da utilizzare: 'json' o 'database'
     * Può essere sovrascritto nelle classi figlie
     */
    protected static string $driver = 'database';

    /**
     * Nome della tabella nel database (se driver = 'database')
     * Se non specificato, usa il valore di $collection
     */
    protected static ?string $table = null;

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    //Getters
    public function getId(): int {
        return $this->id;
    }

    /**
     * Ottiene il prossimo ID disponibile
     */
    protected static function getNextId(): int
    {
        if (static::$driver === 'json') {
            return JSONDB::getNextId(static::$collection);
        } else {
            throw new \Exception("getNextId non supportato per driver database.");
        }
    }

    /**
     * Ottiene il nome della tabella
     */
    public static function getTableName(): string
    {
        return static::$table ?? static::$collection;
    }


    /**
     * Legge tutti i record dalla collection/tabella
     */
    public static function all(): array
    {
        $rows = [];
        if (static::$driver === 'json') {
            $rows = JSONDB::read(static::$collection);
        } else {
            $rows = DB::select("SELECT * FROM " . static::getTableName());
        }
        return array_map(fn($row) => new static($row), $rows);
    }


    /**
     * Trova un record per ID
     */
    public static function find(int $id): ?static
    {
        $row = null;
        if (static::$driver === 'json') {
            $collection = static::all();
            $row = array_find($collection, function ($item) use ($id) {
                return $item->id === $id;
            });
        } else {
            $result = DB::select("SELECT * FROM " . static::getTableName() . " WHERE id = :id", ['id' => $id]);
            $row = $result[0] ?? null;
        }
        return $row ? new static($row) : null;
    }

    /**
     * Inserisce un nuovo record nel database
     */
    public static function create(array $data): static
    {
        $model = new static($data);
        $model->save();
        return $model;
    }

    /**
     * Aggiorna un record nel database
     */
    public function update(array $data): static
    {
        $this->fill($data);
        $this->save();
        return $this;
    }

    /**
     * Riempie il modello con i dati passati
     * @param array $data Dati da riempire
     * @return void
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                //Verifico se il valore è convertibile in stringa, se si lo faccio il format per leggerlo in maniera più ordinata
                //if(strtotime($value)) $value = date('Y-m-d H:i:s', strtotime($value));
                
                $this->$key = $value;
            }
        }
    }

    /**
     * Salva il record nel database
     */
    public function save(): void
    {
        $isNew = !isset($this->id);
        $now = date('Y-m-d H:i:s');

        // Timestamp di creazione e aggiornamento
        $this->created_at = $this->created_at ?: $now; // ?: elvis operator per assegnare il valore di default se non è settato
        $this->updated_at = $now; // aggiorniamo a prescindere se è nuovo o no

        if (static::$driver === 'json') {
            $collectionData = JSONDB::read(static::$collection);
            if ($isNew) {
                $this->id = JSONDB::getNextId(static::$collection);
                $collectionData[] = $this->toArray();
            } else {
                $collectionData = array_map(function ($item) {
                    if ($item['id'] === $this->id) {
                        return $this->toArray();
                    }
                    return $item;
                }, $collectionData);
            }
            JSONDB::write(static::$collection, $collectionData);
        } else {

            // i bindings sono gli array di valori da inserire nella query
            // ['name' => 'Mario', 'email' => 'mario@example.com', ...]
            $bindings = array_filter($this->toArray(), fn($key) => $key !== 'id', ARRAY_FILTER_USE_KEY);

            //Rimuovo dai binding quei valori che sono rappresentati da array o oggetti, che verranno gestiti diversamente
            $bindings = array_filter($bindings, function ($value) {
                return !is_array($value);
            });

            // dobbiamo ottenere i nomi delle colonne per la query nel formato:
            // ['name', 'email', ...]
            $columns = array_keys($bindings);
            
            // dobbiamo ottenere i placeholders per la query nel formato:
            // [':name', ':email', ...]
            $placeholders = array_map(fn($col) => ":{$col}", $columns);

            if ($isNew) {
                $this->id = DB::insert(sprintf("INSERT INTO %s (%s) VALUES (%s)", static::getTableName(), implode(', ', $columns), implode(', ', $placeholders)), $bindings);
                // la query INSERT è tipo:
                // INSERT INTO users (name, email) VALUES (:name, :email)
            } else {
                // mappiamo le colonne con i valori per la query nel formato e 
                // ['name = :name', 'email = :email', ...]
                $columnWithValues = array_map(fn($col) => "{$col} = :{$col}", $columns);
                // la query UPDATE è tipo:
                // UPDATE users SET name = :name, email = :email WHERE id = :id
                // Aggiungiamo id ai bindings perché è necessario per la clausola WHERE
                $bindings['id'] = $this->id;
                DB::update(sprintf("UPDATE %s SET %s WHERE id = :id", static::getTableName(), implode(', ', $columnWithValues)), $bindings);
            }
        }
    }

    public function delete(): int
    {
        $result = 0;
        if (static::$driver === 'json') {
            $collection = static::all();
            $newCollection = array_filter($collection, fn($item) => $item->id !== $this->id);
            $result = JSONDB::write(static::$collection, $newCollection);
        } else {
            $result = DB::delete("DELETE FROM " . static::getTableName() . " WHERE id = :id", ['id' => $this->id]);
        }
        if ($result === 0) {
            throw new \Exception("Errore durante l'eliminazione dell'utente");
        }
        return $result;
    }

    public static function getRequestData():array {
        $request = new Request();
        $data = $request->json();

        return $data ?? [];
    }

    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PRIVATE);

        $result = [];
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            // Escludiamo le proprietà statiche e di configurazione
            if (in_array($propertyName, ['collection', 'driver', 'table'])) {
                continue;
            }
            // Includiamo solo i valori non null (o tutti se necessario)
            $result[$propertyName] = $property->getValue($this);

            //Verifico se la proprietà contiene un oggetto a sua volta
            if(is_object($result[$propertyName])) {
                //Se lo sono, converto a sua volta le proprietà in oggetto
                $result[$propertyName] = $result[$propertyName]->toArray();
            }

            if(is_array($result[$propertyName]) && !empty($result[$propertyName])) {
                
            }

            //escludiamo i valori not null 
            $result = array_filter(
                $result,
                fn($v) => $v !== null
            );
        }

        return $result;
    }
}
