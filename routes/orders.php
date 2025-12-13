<?php 

/* Routes per gestione orders */

use App\Abstract\MenuItem;
use App\Models\MenuOrderItem;
use App\Models\Order;
use App\Utils\Response;
use App\Models\OrderItem;
use App\Models\OrdersTables;
use App\Models\Table;
use App\Utils\Request;
use Pecee\SimpleRouter\SimpleRouter as Router;

/**
 * GET /api/orders - Lista Orders
 */
Router::get('/orders', function () {
    try {
        //Mi prendo prima tutti gli ordini
        $orders = Order::getAllOrders();

        Response::success($orders)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * GET /api/orders/{id} - Lista Orders
*/
Router::get('/orders/{id}', function ($id) {
    try {
        $order = Order::getOrderById($id);

        Response::success($order)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
    }
});

/**
 * POST /api/orders/ - Lista Orders
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

        //Controllo se il tavolo esiste e se il è disponibile
        $table = Table::find($data['table_id']);

        //Verifico se il tavolo esiste
        if($table === null) {
            Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
            //Verifico se il tavolo è libero e se il numero di ospiti è minore o uguale alla capacita
        } else if(!$table->hasCapacity($data['guests'])) {
            Response::error('Il tavolo non ha posti disponibili (Max ' . $table->getCapacity() . " posti)"  , Response::HTTP_NOT_FOUND)->send();
        } 

        //Se il tavolo esiste, non ci sono errori di validazione, posso creare l'ordine
        $order = Order::create($data);

        //Se tutto è andato a buon fine, occupo il tavolo
        $table->occupy($data['guests']);

        //Aggiungo l'oggetto Table all'istanza di Order
        $order->setTable($table); 

        //Creo una record nella tabella pivot OrdersTables
        OrdersTables::create([
            "order_id" => $order->getId(), 
            "table_id" => $table->getId()
        ]);

        Response::success($order)->send();
    } catch (\Exception $e) {
        Response::error('Errore nel recupero della lista orderItems: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();
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

        //Verifico che l'orderItem sia presente
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
 * PUTCH/PUT /api/order_items/{id}/add_order_item - Aggiungi orderItem all'ordine
*/
Router::patch('order_items/{id}/add_order_item', function($id) {
    try {
        //prima verifico che l'ordine esista
        $order = Order::getOrderById($id);

    } catch(\Exception $e) {
        Response::error("Errore nell'eliminazione dell'ordine: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
}); 

/**
 * DELETE /api/orders/{id} - Rimuove singolo ordine
*/
Router::delete('/orders/{id}', function($id) {
    try {
        //Prendo i dati in input
        $request = new Request();
        $data = $request->json();

        //Metodo per rimuovere l'ordine, liberare il tavolo e rimuovere il record nella tabella pivot
        Order::removeOrder($id);

        Response::success(null, Response::HTTP_OK, "Ordine rimosso con successo")->send();
    } catch(\Exception $e) {
        Response::error("Errore nell'eliminazione dell'ordine: " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR)->send();        
    }
});