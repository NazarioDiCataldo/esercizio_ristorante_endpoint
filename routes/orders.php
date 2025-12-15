<?php 

/* Routes per gestione orders */

use App\Abstract\MenuItem;
use App\Models\MenuOrderItem;
use App\Models\Notification;
use App\Models\Order;
use App\Utils\Response;
use App\Models\OrderItem;
use App\Models\OrdersTables;
use App\Models\Table;
use App\Services\RestaurantService;
use App\Utils\Request;
use Pecee\SimpleRouter\Route\Route;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/orders - Lista Orders
 */
Router::get('/orders', function () {
    try {
        $orders = null;
        //Verifico che non ci siano query params
        if(empty($_GET)) {
            //Mi prendo prima tutti gli ordini
            $orders = Order::getAllOrders();
        } else if(array_key_exists('state', $_GET)) {
            //Verifico se c'è il filtro state
            $orders = RestaurantService::getOrdersByState($_GET['state']);
        } else if(array_key_exists('table', $_GET)) {
            //Verifico se c'è il filtro table
            print_r($_GET['table']);
            $orders = RestaurantService::getOrdersByTable($_GET['table']);
        }

        //Response::success($orders)->send();
        Response::success($orders ?? [])->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista degli ordini: ' . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/orders/{id}/bill - Lista di ordini in base allo stato
*/
Router::get('/orders/{id}/bill', function ($id) {
    try {
        $order = Order::getOrderById($id);

        $bill = RestaurantService::printBill($order);

        Response::success($bill)->send();
    } catch (\Exception $e) {
        Response::error("Errore nel recupero dell'ordine: " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/orders/{id} - Lista di un ordine tramite id
*/
Router::get('/orders/{id}', function ($id) {
    try {
        $order = Order::getOrderById($id);

        Response::success($order)->send();
    } catch (\Exception $e) {
        Response::error("Errore nel recupero dell'ordine: " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/orders/{id}/total - Ritorna il totale
*/
Router::get('/orders/{id}/total', function($id) {
    try {
        $order = Order::getOrderById($id);

        //Mi prendo i dati in input
        $data = Order::getRequestData();

        //L'input deve avere questa struttura
        /* {
            "guests": 1,
        } */

        //numero di ospiti che si divideranno
        $guests = $data['guests'] ?? 1;

        //Totale
        $total = $order->splitBill($guests);

        Response::success(["total" => $total])->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * POST /api/orders/ - Crea nuovo ordine
*/
Router::post('/orders', function () {
    try {
        
        $request = new Request();
        $data = $request->json();

        //L'input deve avere questa struttura
        /* {
            "guests": 4,
            "table_id": 1,
            "state": "new",
            "cover_charge": 2,
            "service_charge": 1,
            "datetime": "2025/12/11"
        } */

        // Validazione
        $errors = OrderItem::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Metodo statico che crea l'ordine
        $order = RestaurantService::createOrder($data);

        Response::success($order)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * PUTCH/PUT /api/order_items/{id}/notify - Aggiunge nuova notifica all'ordine
*/
Router::patch('/order_items/{id}/notify', function($id) {
    try {
        //Mi prendo i dati in input
        $data = Order::getRequestData();

        //L'input deve avere questa struttura
        /* {
            "message": "ciao",
            "type": "info",
            "timestamp": "2025/12/12 13:54",
            "is_read": false
        } */

        //Verifico che l'ord sia presente
        $order = Order::getOrderById($id);

        if($order === null) {
            Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
            return;
        }

        //Se presente faccio la validazione
        $errors = Notification::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Aggiungo la notifica all'ordine

    } catch(\Exception $e) {
        Response::error("Errore nell'aggiunta della notifica: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * PUTCH/PUT /api/order_items/{id} - Modifica singolo ordine
*/

Router::match(['put', 'patch'], '/orders/{id}', function($id) {
    try {
        //Prendo i dati in input
        $request = new Request();
        $data = $request->json();

        //L'input deve avere questa struttura
        /* {
            "table_id": 1,
        } */

        //Verifico che l'ord sia presente
        $order = Order::getOrderById($id);
        if($order === null) {
            Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
        }

        //Se presente faccio la validazione
        $errors = Order::validate(array_merge($data, ['id' => $id]));
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

    
        //Verifico se table_id esiste ed è diverso -> quindi se si vuole cambiare all'ordine il tavolo assegnato precedentemente
        if(array_key_exists('table_id', $data) && $data['table_id'] !== $order->getTable()->getId()) {
            //Verifico prima che il nuovo tavolo sia disponibile
            $table = Table::find($data['table_id']);
            //Mi prendo il numero di ospiti, se cambia
            $guests = $data['guests'] ?? $order->getTable()->getCurrentGuests();

            //Verifico se il nuovo tavolo ha la stessa capacità per gli ospiti del tavolo precedente
            if(!$table->hasCapacity($guests)) {
                Response::error('Il tavolo non ha più posti disponibili', Response::HTTP_BAD_REQUEST)->send();
                return;
            }

            //Se è cosi, bisogna aggiornare anche la tabella orders_tables
            $orders_tables = OrdersTables::getOrdersTablesById($order->getId());
            //Aggiorniamo il record
            $orders_tables->update(['table_id' => $data['table_id']]);
            //Libero prima il vecchio tavolo
            $order->getTable()->free();
            //Settiamo il nuovo Tavolo 
            $order->setTable($table);
            //Occupo il nuovo tavolo
            $table->occupy($guests);
        }

        //Aggiorno l'orderItem
        $order->update($data);

        Response::success($order, Response::HTTP_OK, "Ordine aggiornato con successo")->send();
    } catch (\Exception $e) {
        Response::error("Errore durante l'aggiornamento dell'ordine: " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * PUTCH/PUT /api/orders/{id}/add_order_item - Aggiungi orderItem all'ordine
*/
Router::patch('orders/{id}/add_order_item', function($id) {
    try {
        //Mi prendo i dati in input
        $data = Order::getRequestData();

        //L'input deve avere questa struttura
        /* {
            "menu_item_id": 1,
            "customizations": "poco piccante",
            "quantity": 2
        } */

        //Verifico che l'orderItem sia presente
        $order = Order::getOrderById($id);

        if($order === null) {
            Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
            return;
        }

        // Validazione
        $errors = OrderItem::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        //Se ha passato i primi controlli creo l'orderItem
        $order->addOrderItem($data);

        Response::success($order, Response::HTTP_OK, "OrderItem aggiunto all'ordine")->send();

    } catch(\Exception $e) {
        Response::error("Errore durante l'aggiunte dell'OrderItem: " . $e->getMessage() . " " . $e->getFile() . " " . $e->getLine(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
}); 

/**
 * PUTCH/PUT /api/orders/{id}/add_tip - Aggiunge mancia all'ordine
*/
Router::patch('/orders/{id}/add_tip', function($id) {
    try {
        //Mi prendo i dati in input
        $data = Order::getRequestData();

        //L'input deve avere questa struttura
        /* {
            "tip": 5,
        } */

        //Verifico che l'orderItem sia presente
        $order = Order::getOrderById($id);
        if($order === null) {
            Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
            return;
        }

        // Validazione
        $errors = OrderItem::validate($data);
        if (!empty($errors)) {
            Response::error('Errore di validazione', Response::HTTP_BAD_REQUEST, $errors)->send();
            return;
        }

        $amount = $data['tip'] ?? 0;

        $order->addTip($amount);

        Response::success($order, Response::HTTP_OK, "Mancia incrementata correttamente")->send();
    } catch(\Exception $e) {
        Response::error("Errore nell'incrementazione della mancia: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
});

/**
 * PUTCH/PUT /api/orders/{id}/complete - Rende l'ordine completo
*/
Router::patch('orders/{id}/complete', function($id) {
    try {
        //Verifico che l'orderItem sia presente
        $order = Order::getOrderById($id);

        if($order === null) {
            Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
            return;
        }

        //Completa
        RestaurantService::completeOrder($order);

        Response::success(null, Response::HTTP_OK, "Ordine completato")->send();
    } catch(\Exception $e) {
        Response::error("Errore nel completamento dell'ordine: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
});


/**
 * DELETE /api/orders/{id} - Rimuove singolo ordine
*/
Router::delete('/orders/{id}', function($id) {
    try {

        //Metodo per rimuovere l'ordine, liberare il tavolo e rimuovere il record nella tabella pivot
        Order::removeOrder($id);

        Response::success(null, Response::HTTP_OK, "Ordine rimosso con successo")->send();
    } catch(\Exception $e) {
        Response::error("Errore nell'eliminazione dell'ordine: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
});

/**
 * DELETE /api/orders/{id}/remove_order_item - Rimuove singolo orderItem dall'ordine
*/
Router::delete('/orders/{id}/remove_order_item', function($id) {
    try {
        //Mi prendo i dati in input
        $data = Order::getRequestData();

        //L'input deve avere questa struttura
        /* {
            "order_item_id": 1,
        } */

        //Verifico che l'ord sia presente
        $order = Order::getOrderById($id);

        if($order === null) {
            Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
            return;
        }

        //rimuovo l'orderItem, facendo prima delle validazioni
        $order->removeItem($data);   
        
        Response::success(null, Response::HTTP_OK, "OrderItem rimosso correttamente dall'ordine ")->send();

    } catch(\Exception $e) {
        Response::error("Errore nell'eliminazione dell'ordine: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
});