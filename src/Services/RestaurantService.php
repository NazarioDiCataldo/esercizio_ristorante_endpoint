<?php

namespace App\Services {

    use App\Abstract\MenuItem;
    use App\Database\DB;
    use App\Models\Notification;
    use App\Models\Order;
    use App\Models\OrderItem;
    use App\Models\OrdersTables;
    use App\Models\Table;
    use App\Utils\Response;

    //Classe che gestisce il ristorante
    class RestaurantService {

        //Metodi

        //Crea ordine
        public static function createOrder(array $data): ?Order {
            //Controllo se il tavolo esiste e se il è disponibile
            $table = Table::find($data['table_id']);

            //Verifico se il tavolo esiste
            if($table === null) {
                Response::error('Tavolo non trovato', Response::HTTP_NOT_FOUND)->send();
                return null;
                //Verifico se il tavolo è libero e se il numero di ospiti è minore o uguale alla capacita
            } else if(!$table->hasCapacity($data['guests'])) {
                Response::error('Il tavolo non ha posti disponibili (Max ' . $table->getCapacity() . " posti)"  , Response::HTTP_NOT_FOUND)->send();
                return null;
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
                "table_id" => $table->getId(),
                "guests" => $data['guests']
            ]);

            return $order;
        }

        //Metodo per aggiungere un ordine al menù
        public function addDishToOrder(Order $order, array $data): void {

            //crea OrderItem, aggiunge note, valida e aggiunge all'ordine
            $order->addOrderItem($data);
        }

        //usa OrderStateService per validare e eseguire transizione, genera notifica
        public static function updateOrderState(Order $order, string $newState): void {
            //Se il nuovo state è valido, aggiorni lo stato
            $order->transitionTo($newState);
            //Invia la notifica di completamento state
            $order->notify([
                "message" => "Cambiamento di stato a " . $newState,
                "type" => "info"
            ]);
        }   

        //transizione a "served", libera tavolo, notifica
        public static function completeOrder(Order $order): void {
            //Cambio stato in 'served'
            RestaurantService::updateOrderState($order, Order::STATE_SERVED);

            //Libero il tavolo
            $order->getTable()->free();
            //Creo una notifica
            $order->notify([
                "message" => "Ordine completato",
                "type" => "info"
            ]);
        }

        //filtra ordini per stato
        public static function getOrdersByState(string $state): array {
            //escape del carattere
            $state = htmlspecialchars($state);

            //query per prendermi tutti gli ordini, in base allo stato passato come parametro
            $result = DB::select("SELECT * FROM " . Order::getTableName() . " WHERE state = :state", ['state' => $state]);
        
            //Map per trasformare in un'array di oggetti Order
            return array_map(function ($order) {

                return Order::getOrderById($order['id']);
            } ,$result);
        }

        //Filtra ordini per tavolo
        public static function getOrdersByTable(int $id): array {
            //escape del carattere
            $id = htmlspecialchars($id);

            //query per prendermi tutti gli ordini, in base allo stato passato come parametro
            $result = DB::select("SELECT order_id FROM " . OrdersTables::getTableName() . " ot WHERE ot.table_id = :id", [
                'id' => $id
            ]);
        
            //Map per trasformare in un'array di oggetti Order
            return array_map(function ($order) {
                return Order::getOrderById($order['order_id']);
            } ,$result);
        }

        //stampa dettagli ordine completo
        public function printOrderDetails(Order $order): void {
            print_r($order);
        }

        //stampa conto: tavolo, piatti con quantità e note, subtotale, coperto, servizio, mancia, totale, divisione se richiesta
        public static function printBill(Order $order, int $split_by = 1): array {
            $bill = [];
            $bill['table_number'] = "Numero tavolo: {$order->getTable()->getNumber()} \n";
            //Per ogni OrderItem stampo nome, quantità e prezzo finale
            //Se l'array di note non è vuoto, stampo anch lui
            $menu_item = [];
            
            foreach($order->getItems() as $item) {
                $app = [];
                $app['name'] = $item->getMenuItem()->getName();
                $app['price'] = "{$item->getQuantity()}" . " * {$item->getItem()->getBasePrice()} = {$item->getSubTotal()} € \n";
                //Controllo se ci sono supplementi
                $app['supplements'] = [];
                foreach($item->getSupplementsArray() as $sup) {
                   $sup = [
                        "name" => "Name: {$sup['name']}",
                        "price" => "Price: {$sup['price']} €",
                    ];
                    array_push($app['supplements'], $sup);
                    
                }
                array_push($menu_item, $app);
            }
            $bill['items'] = $menu_item;
            $bill['subtotal'] = "Subtotale: {$order->getSubTotal()} € \n";
            $bill['cover'] = "Coperto: {$order->getCoverCharge()} * {$order->getGuests()} € \n";
            $bill['service'] = "Servizio: {$order->getServiceCharge()} € \n";
            $bill['tip'] = "Mancia: {$order->getTip()} € \n";
            $bill['total'] = "Totale da pagare: {$order->getTotal()} € \n";
            if($split_by > 1) {
                $bill['split'] = "Diviso {$split_by}: {$order->splitBill($split_by)} a persona";
            }

            return $bill;
        }
    }
}