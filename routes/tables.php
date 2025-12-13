<?php

// CLIENT HTTP REQUEST -> fetch
// BACKEND -> handles request (builds and sends response)
// CLIENT HTTP handles response -> does something

/* Routes per gestione tavoli */


use App\Utils\Response;
use App\Models\Table;
use App\Utils\Request;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/tables - Lista tavoli
 */
Router::get('/tables', function () {
    try {
        $tables = Table::all();
        Response::success($tables)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista tavoli: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/tables/{id} - Lista tavoli
*/
Router::get('/tables/{id}', function ($id) {
    try {
        $table = Table::find($id);

        if($table === null) {
            Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        Response::success($table)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista tavoli: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/tables/{id}/has_capacity - Verifica se una tavolo sia libero
*/
Router::get('/tables/{id}/has_capacity/{guests}', function ($id, $guests) {
    try {
        //Verifica che il tavolo esista
        $table = Table::find($id);

        //Altrimenti errore
        if($table === null) {
            Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        if(!is_numeric($guests) || $guests < 0 ) {
            Response::error('Numero di ospiti non validi', Response::HTTP_BAD_REQUEST)->send();
            return;
        }

        //Chiama il metodo hasCapacity;
        $has_capacity = $table->hasCapacity($guests);
        $message = $has_capacity ? 'Il tavolo ha posti disponibili' : 'Il tavolo non ha più posti disponibili'; 

        Response::success($has_capacity, Response::HTTP_OK, $message)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista tavoli: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * POST /api/tables - Crea nuovo Tavolo
 */
Router::post('/tables', function () {
    try {
        $request = new Request();
        $data = $request->json();

        // Validazione
        $errors = Table::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $table = Table::create($data);

        Response::success($table, Response::HTTP_CREATED, "Tavolo creato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante la creazione del nuovo Tavolo: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/* 
    PATCH /api/tables/{id}/occupy - Rende un tavolo occupato, aggiungendo ospiti passati come parametro
*/

Router::patch('/tables/{id}/occupy', function($id) {
    try {
        //Mi prendo la richiesta dell'input
        $request = new Request();
        $data = $request->json();

        //Verifico che il tavolo esista
        $table = Table::find($id);

        if($table === null) {
            Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        //validazione di errore
        $errors = Table::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $table->occupy($data['current_guests']);

        Response::success($table, Response::HTTP_OK, "Numero di ospiti aggiornato correttamente")->send();
    } catch(\Exception $e) {
        Response::error("Errore durante l'aggiunta degli ospiti: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
});

/* 
    PATCH /api/tables/{id}/free - Rende un tavolo occupato, rimuovendo tutti gli ospiti
*/
Router::patch('/tables/{id}/free', function($id) {

    try {
        //Verifico che il tavolo esista
        $table = Table::find($id);
    
        if($table === null) {
            Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
        }
    
        //Chiama il metodo free che libera il tavolo
        $table->free();
        Response::success($table, Response::HTTP_OK, "Tavolo liberato correttamente")->send();
    } catch(\Exception $e) {
        Response::error("Errore durante la liberazione del tavolo: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }

});

Router::match(['put', 'patch'], '/tables/{id}', function($id) {
    try {
        $request = new Request();
        $data = $request->json();

        $table = Table::find($id);
        if($table === null) {
            Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $errors = Table::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $table->update($data);

        Response::success($table, Response::HTTP_OK, "Tavolo aggiornato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'aggiornamento dell\' Tavolo: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::delete('/tables/{id}', function($id) {
    try {
        $table = Table::find($id);
        if($table === null) {
            Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $table->delete();

        Response::success(null, Response::HTTP_OK, "Tavolo eliminato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'eliminazione dell\' Tavolo: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});