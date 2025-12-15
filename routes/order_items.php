<?php

// CLIENT HTTP REQUEST -> fetch
// BACKEND -> handles request (builds and sends response)
// CLIENT HTTP handles response -> does something

/* Routes per gestione orderItems */

use App\Abstract\MenuItem;
use App\Models\BaseModel;
use App\Models\MenuOrderItem;
use App\Models\Order;
use App\Utils\Response;
use App\Models\OrderItem;
use App\Utils\Request;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/order_items - Lista orderItems
 */
Router::get('/order_items', function () {
    try {
        $order_items = OrderItem::getAllOrderItems();

        Response::success($order_items)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/order_items/{id} - Lista orderItems
 */
Router::get('/order_items/{id}', function ($id) {
    try {
        $order_item = OrderItem::getOrderItemById($id);

        if($order_item === null) {
            Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        Response::success($order_item)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/order_items/{id}/subtotal - Ritorna il subtotale dell'orderItem
*/

Router::get('/order_items/{id}/subtotal', function($id) {
    try {
            //Verico che l'ordine esiste
            $order_item = OrderItem::getOrderItemById($id);
            if($order_item === null) {
                Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
            }

            $subtotal = $order_item->getSubTotal();

            Response::success($subtotal)->send();
        } catch (\Exception $e) {
            Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
        }
});

/**
 * GET /api/order_items/{id}/notes - Ritorna le note collegate all'orderItem
*/

Router::get('/order_items/{id}/notes', function($id) {
    try {
            //Verico che l'ordine esiste
            $order_item = OrderItem::getOrderItemById($id);
            if($order_item === null) {
                Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
            }

            $notes = $order_item->getNotes();

            Response::success(["notes" => $notes])->send();
        } catch (\Exception $e) {
            Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
        }
});

/**
 * POST /api/order_items - Crea nuovo OrderItem
 */
Router::post('/order_items', function () {
    try {
        //Mi prendo i dati in input
        $data = OrderItem::getRequestData();

        //L'input deve avere questa struttura
        /* {
            "menu_item_id": 1,
            "customizations": "poco piccante",
            "quantity": 2
        } */
       
        //Verifico che l'array di dati non sia vuoto
        if(!Order::validateOrderItem($data)) {
            Response::error(OrderItem::NO_DATA, Response::HTTP_BAD_REQUEST)->send();
            return;
        }

        // Validazione
        $errors = OrderItem::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Se ha passato i primi controlli creo l'orderItem
        $order_item = OrderItem::createOrderItem($data);
        
        Response::success($order_item, Response::HTTP_CREATED, OrderItem::CREATED)->send();

    } catch (\Exception $e) {
        Response::error('Errore durante la creazione della nuova OrderItem: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * PUTCH/PUT /api/order_items/{id} - Modifica OrderItem
*/

Router::match(['put', 'patch'], '/order_items/{id}', function($id) {
    try {
        //Prendo i dati in input
        $request = new Request();
        $data = $request->json();

        //L'input deve avere questa struttura
        /* {
            "menu_item_id": 1,
            "customizations": "poco piccante",
            "quantity": 2
        } */

        //Verifico che l'orderItem sia presente
        $order_item = OrderItem::getOrderItemById($id);
        if($order_item === null) {
            Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        //Se presente faccio la validazione
        $errors = OrderItem::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        
        //Verifico se menu_item_id esiste ed è diverso
        $order_item->hasAnotherMenuItemId($data);

        //Aggiorno l'orderItem
        $order_item->update($data);

        Response::success($order_item, Response::HTTP_OK, "OrderItem aggiornata con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'aggiornamento della OrderItem: ' . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * /PATCH /api/order_items/{id}/add_supplement - Aggiunge un supplemento
*/
Router::patch('/order_items/{id}/add_supplement', function($id) {
    try {
        //Prendo i dati in input
        $request = new Request();
        $data = $request->json(); 
        
        //L'input deve avere questa struttura
            /* {
                "name": "patatine",
                "price": 2
            } */
        //Verifico che l'orderItem sia presente
        $order_item = OrderItem::getOrderItemById($id);
        if($order_item === null) {
            Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        //Se presente faccio la validazione
            $errors = OrderItem::validate($data);
            if (!empty($errors)) {
                Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
                return;
            }

        //Se tutto va a buon fine, faccio l'insert nella tabella Supplements
        $supplement_id = $order_item->addSupplements(array_merge($data, ['order_item_id' => $id]));

        Response::success(["id" => $supplement_id], Response::HTTP_OK, "OrderItem aggiornata con successo")->send();
    } catch(\Exception $e) {
        Response::error("Errore durante l'aggiunta dei supplementi : " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * /PATCH /api/order_items/{id}/add_note - Aggiungi una nuova 
*/

Router::patch('/order_items/{id}/add_note', function($id) {
    try{
        //Prendo i dati in input
        $request = new Request();
        $data = $request->json(); 
        
        //L'input deve avere questa struttura
            /* {
                "message": "A sangue",
            } */

        //Verifico che l'orderItem sia presente
        $order_item = OrderItem::getOrderItemById($id);
        if($order_item === null) {
            Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        //Se presente faccio la validazione
        $errors = OrderItem::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Se tutto va a buon fine, faccio l'insert nella tabella Supplements
        $note_id = $order_item->addNote(array_merge($data, ['order_item_id' => $id]));

        Response::success(["id" => $note_id], Response::HTTP_OK, "Nota aggiunta con successo")->send();
    } catch(\Exception $e) {
        Response::error("Errore durante l'aggiunta dell'annotazione : " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * /DELETE /api/order_items/{id} - Rimuovi ordine 
*/

Router::delete('/order_items/{id}', function($id) {
    try {
        //Verifico che l'orderItem sia presente
        $order_item = OrderItem::find($id);

        if($order_item === null) {
            Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
            return;
        }

        //Procediamo con la rimozione
        $order_item->deleteOrderItem();

        Response::success(null, Response::HTTP_OK, "OrderItem eliminato con successo")->send();
    } catch (\Exception $e) {
        Response::error('Errore durante l\'eliminazione della OrderItem: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * /DELETE /api/order_items/{id}/remove_supplement - Rimuovi ordine 
*/

//Rimuove uno o più supplementi
Router::delete('/order_items/{id}/remove_supplement', function($id) {
    try {
        //Prendo i dati in input
        $request = new Request();
        $data = $request->json(); 
        
        //L'input deve avere questa struttura
            /* {
                "name": "patatine"
            } */

        //Verifico che l'orderItem sia presente
        $order_item = OrderItem::getOrderItemById($id);
        if($order_item === null) {
            Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        //Verifico se esiste almeno un supplemento con il nome richiesto
        $supplements = $order_item->getSupplements($data['name']);

        if(empty($supplements)) {
            Response::error('Supplemento non trovato', Response::HTTP_NOT_FOUND)->send();
            return; 
        }

        $errors = OrderItem::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Adesso che so che il supplemente cercato esiste, è valido ed anche l'orderItem collegato esiste
        //Posso passare a cancellare dalla tabella supplements il record 
        $order_item->removeSupplements($data['name']);
        Response::success(null, Response::HTTP_NO_CONTENT, "Supplemento rimosso con successo")->send();
    } catch(\Exception $e) {
        Response::error("Errore durante l'eliminazione del supplemento: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * /DELETE /api/order_items/{id}/remove_note - Rimuove una nota
*/

//Rimuove uno o più supplementi
Router::delete('/order_items/{id}/remove_note', function($id) {
    try {
        //Prendo i dati in input
        $request = new Request();
        $data = $request->json(); 
        
        //L'input deve avere questa struttura
            /* {
                "name": "patatine"
            } */

        //Verifico che l'orderItem sia presente
        $order_item = OrderItem::getOrderItemById($id);
        if($order_item === null) {
            Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        //Verifico se esiste almeno un supplemento con il nome richiesto
        $notes = $order_item->getNotes($data['message']);

        if(empty($notes)) {
            Response::error('Nota non trovata', Response::HTTP_NOT_FOUND)->send();
            return; 
        }

        $errors = OrderItem::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Adesso che so che il supplemente cercato esiste, è valido ed anche l'orderItem collegato esiste
        //Posso passare a cancellare dalla tabella supplements il record 
        $order_item->removeNote($data);
        Response::success(null, Response::HTTP_NO_CONTENT, "Nota rimossa con successo")->send();
    } catch(\Exception $e) {
        Response::error("Errore durante l'eliminazione della nota: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});