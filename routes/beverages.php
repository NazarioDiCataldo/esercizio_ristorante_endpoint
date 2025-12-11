<?php

// CLIENT HTTP REQUEST -> fetch
// BACKEND -> handles request (builds and sends response)
// CLIENT HTTP handles response -> does something

/* Routes per gestione bevande */

use App\Abstract\MenuItem;
use App\Utils\Response;
use App\Models\Beverage;
use App\Utils\Request;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/beverages - Lista bevande
 */
Router::get('/beverages', function () {
    try {
        $beverages = Beverage::getByItemType(MenuItem::MENU_ITEM_TYPE_BEVERAGE);
        Response::success($beverages)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista bevande: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/beverages/{id} - Lista bevande
*/
Router::get('/beverages/{id}', function ($id) {
    try {
        $beverage = Beverage::find($id);

        if($beverage === null || $beverage->getItemType() !== MenuItem::MENU_ITEM_TYPE_BEVERAGE) {
            Response::error('Bevanda non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        Response::success($beverage)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista bevande: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * POST /api/beverages - Crea nuovo Bevanda
 */
Router::post('/beverages', function () {
    try {
        $request = new Request();
        $data = $request->json();

        // Validazione
        $errors = Beverage::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $Beverage = Beverage::create($data);

        Response::success($Beverage, Response::HTTP_CREATED, "Bevanda creato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante la creazione del nuovo Bevanda: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::match(['put', 'patch'], '/beverages/{id}', function($id) {
    try {
        $request = new Request();
        $data = $request->json();

        $beverage = Beverage::find($id);
        if($beverage === null || $beverage->getItemType() !== MenuItem::MENU_ITEM_TYPE_BEVERAGE) {
            Response::error('Bevanda non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $errors = Beverage::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $beverage->update($data);

        Response::success($beverage, Response::HTTP_OK, "Bevanda aggiornato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'aggiornamento dell\' Bevanda: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::delete('/beverages/{id}', function($id) {
    try {
        $beverage = Beverage::find($id);
        if($beverage === null || $beverage->getItemType() !== MenuItem::MENU_ITEM_TYPE_BEVERAGE) {
            Response::error('Bevanda non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $beverage->delete();

        Response::success(null, Response::HTTP_OK, "Bevanda eliminato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'eliminazione dell\' Bevanda: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});