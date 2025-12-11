<?php

// CLIENT HTTP REQUEST -> fetch
// BACKEND -> handles request (builds and sends response)
// CLIENT HTTP handles response -> does something

/* Routes per gestione notifiche */


use App\Utils\Response;
use App\Models\Notification;
use App\Utils\Request;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/notifications - Lista notifiche
 */
Router::get('/notifications', function () {
    try {
        $notifications = Notification::all();
        Response::success($notifications)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista notifiche: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/notifications/{id} - Lista notifiche
 */
Router::get('/notifications/{id}', function ($id) {
    try {
        $notification = Notification::find($id);

        if($notification === null) {
            Response::error('Notifica non trovata', Response::HTTP_NOT_FOUND)->send();
        }

        Response::success($notification)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista notifiche: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/notifications/{id}/messagge - Ritorna il messaggio formattato
*/
Router::get('/notifications/{id}/message', function($id) {
    try {
        //Verifico se la notifica esiste
        $notification = Notification::find($id);

        if($notification === null) {
            Response::error('Notifica non trovata', Response::HTTP_NOT_FOUND)->send();
        }

        Response::success($notification->getFormattedMessage())->send();
    } catch (\Exception $e) {
        Response::error('Errore nella formattazione del messaggio: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * POST /api/notifications - Crea nuovo Notifica
 */
Router::post('/notifications', function () {
    try {
        $request = new Request();
        $data = $request->json();

        // Validazione
        $errors = Notification::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $notification = Notification::create($data);

        Response::success($notification, Response::HTTP_CREATED, "Notifica creata con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante la creazione della nuova notifica: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * PATCH /api/notifications/1/read - Imposta la notifica come letta 
*/
Router::patch('/notifications/{id}/read', function($id) {
    try {
        //Verifico che la notifica esista
        $notification = Notification::find($id);
        if($notification === null) {
            Response::error('Notifica non trovata', Response::HTTP_NOT_FOUND)->send();
        }

        //Chiamo il metodo markAsRRead, che rende il campo 'is_read' 
        $notification->markAsRead();

    } catch (\Exception $e) {
        Response::error('Errore durante la lettura della notifica: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::match(['put', 'patch'], '/notifications/{id}', function($id) {
    try {
        $request = new Request();
        $data = $request->json();

        $notification = Notification::find($id);
        if($notification === null) {
            Response::error('Notifica non trovata', Response::HTTP_NOT_FOUND)->send();
        }

        $errors = Notification::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $notification->update($data);

        Response::success($notification, Response::HTTP_OK, "Notifica aggiornata con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'aggiornamento della notifica: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::delete('/notifications/{id}', function($id) {
    try {
        $notification = Notification::find($id);
        if($notification === null) {
            Response::error('Notifica non trovata', Response::HTTP_NOT_FOUND)->send();
        }

        $notification->delete();

        Response::success(null, Response::HTTP_OK, "Notifica eliminato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'eliminazione della notifica: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});