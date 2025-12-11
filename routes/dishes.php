<?php

// CLIENT HTTP REQUEST -> fetch
// BACKEND -> handles request (builds and sends response)
// CLIENT HTTP handles response -> does something

/* Routes per gestione piatti */

use App\Abstract\MenuItem;
use App\Utils\Response;
use App\Models\Dish;
use App\Utils\Request;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/Dishes - Lista piatti
 */
Router::get('/Dishes', function () {
    try {
        $dishes = Dish::getByItemType(MenuItem::MENU_ITEM_TYPE_DISH);
        Response::success($dishes)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista piatti: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/Dishes/{id} - Lista piatti
*/
Router::get('/Dishes/{id}', function ($id) {
    try {
        $dish = Dish::find($id);

        if($dish === null || $dish->getItemType() !== MenuItem::MENU_ITEM_TYPE_DISH ) {
            Response::error('Piatto non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        Response::success($dish)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista piatti: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * POST /api/Dishes - Crea nuovo Piatto
 */
Router::post('/Dishes', function () {
    try {
        $request = new Request();
        $data = $request->json();

        // Validazione
        $errors = Dish::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Aggiungo l'item type
        $dish = Dish::create(array_merge($data, ['item_type' => 'Dish']));

        Response::success($dish, Response::HTTP_CREATED, "Piatto creato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante la creazione del nuovo Piatto: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::match(['put', 'patch'], '/Dishes/{id}', function($id) {
    try {
        $request = new Request();
        $data = $request->json();

        $dish = Dish::find($id);
        if($dish === null || $dish->getItemType() !== MenuItem::MENU_ITEM_TYPE_DISH) {
            Response::error('Piatto non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $errors = Dish::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $dish->update($data);

        Response::success($dish, Response::HTTP_OK, "Piatto aggiornato con successo")->send();
    } catch (\Exception $e) {
        Response::error("Errore durante l'aggiornamento del Piatto: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

Router::delete('/Dishes/{id}', function($id) {
    try {
        $dish = Dish::find($id);
        if($dish === null || $dish->getItemType() !== MenuItem::MENU_ITEM_TYPE_DISH) {
            Response::error('Piatto non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        $dish->delete();

        Response::success(null, Response::HTTP_OK, "Piatto eliminato con successo")->send();
    } catch (\Exception $e) {
        Response::error("Errore durante l'eliminazione del Piatto: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});