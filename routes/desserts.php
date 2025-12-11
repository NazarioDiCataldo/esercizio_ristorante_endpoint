<?php

// CLIENT HTTP REQUEST -> fetch
// BACKEND -> handles request (builds and sends response)
// CLIENT HTTP handles response -> does something

/* Routes per gestione dessert */

use App\Abstract\MenuItem;
use App\Utils\Response;
use App\Models\Dessert;
use App\Utils\Request;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/desserts - Lista dessert
 */
Router::get('/desserts', function () {
    try {
        $desserts = Dessert::getByItemType(MenuItem::MENU_ITEM_TYPE_DESSERT);
        Response::success($desserts)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista dessert: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/desserts/{id} - Lista dessert
*/
Router::get('/desserts/{id}', function ($id) {
    try {
        $dessert = Dessert::find($id);

        if($dessert === null || $dessert->getItemType() !== MenuItem::MENU_ITEM_TYPE_DESSERT) {
            Response::error('Dessert non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        Response::success($dessert)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista dessert: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * POST /api/desserts - Crea nuovo Dessert
 */
Router::post('/desserts', function () {
    try {
        $request = new Request();
        $data = $request->json();

        // Validazione
        $errors = Dessert::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $dessert = Dessert::create($data);

        Response::success($dessert, Response::HTTP_CREATED, "Dessert creato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante la creazione del nuovo Dessert: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::match(['put', 'patch'], '/desserts/{id}', function($id) {
    try {
        $request = new Request();
        $data = $request->json();

        $dessert = Dessert::find($id);
        if($dessert === null || $dessert->getItemType() !== MenuItem::MENU_ITEM_TYPE_DESSERT) {
            Response::error('Dessert non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $errors = Dessert::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $dessert->update($data);

        Response::success($dessert, Response::HTTP_OK, "Dessert aggiornato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'aggiornamento dell\' Dessert: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::delete('/desserts/{id}', function($id) {
    try {
        $dessert = Dessert::find($id);
        if($dessert === null || $dessert->getItemType() !== MenuItem::MENU_ITEM_TYPE_DESSERT ) {
            Response::error('Dessert non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $dessert->delete();

        Response::success(null, Response::HTTP_OK, "Dessert eliminato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'eliminazione dell\' Dessert: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});