<?php

namespace App\Services {

    use App\Abstract\MenuItem;
    use App\Models\Notification;
    use App\Models\Order;
    use App\Models\OrderItem;
    use App\Models\Table;

    //Classe che gestisce il ristorante
    class RestaurantService {
        public static $tables_array = [];

        //Metodi

        //Crea ordine
        public function createOrder(Table $table, int $guests):mixed {
            $order = null;
            //crea ordine, occupa tavolo, notifica "Ordine creato"
            if($table->hasCapacity()) {
                $order = new Order(['table' => $table]);
                new Notification(['message' => 'Messaggio creato']);
                $table->occupy($guests);
            }

            return $order;
        }

        //Metodo per aggiungere un ordine al menù
        public function addDishToOrder(Order $order, MenuItem $item, int $quantity, array $notes = []): void {
            //crea OrderItem, aggiunge note, valida e aggiunge all'ordine
            $order->addOrderItem(new OrderItem(['menu_item' => $item, 'quantity' => $quantity, 'customization' => $notes]));
        }

        //usa OrderStateService per validare e eseguire transizione, genera notifica
        public function updateOrderState(Order $order, string $newState): void {
            //Se il nuovo state è valido, aggiorni lo stato
            $order->transitionTo($newState);
            //Invia la notifica di completamento state
            $order->notify("Cambiamento di stato a {$newState}");
        }   

        //transizione a "served", libera tavolo, notifica
        public function completeOrder(Order $order): void {
            $this->updateOrderState($order, Order::STATE_SERVED);
            $table_number = $order->getTable()->getNumber();
            self::$tables_array[$table_number]->free();
            $order->notify("Ordine servito");
        }

        //filtra ordini per stato
        public function getOrdersByState(string $state): array {
            return array_filter(OrderStateService::$all_orders, function ($order) use($state) {
                return $order->getCurrentState() === $state;
            });
        }

        //Filtra ordini per tavolo
        public function getOrdersByTable(Table $table): array {
            return array_filter(self::$tables_array, function ($order) use($table) {
                return $order->getTable()->getNumber() === $table->getNumber();
            });
        }

        //stampa dettagli ordine completo
        public function printOrderDetails(Order $order): void {
            print_r($order);
        }

        //stampa conto: tavolo, piatti con quantità e note, subtotale, coperto, servizio, mancia, totale, divisione se richiesta
        public function printBill(Order $order, int $split_by = 1): void {
            echo "Numero tavolo: {$order->getTable()->getNumber()} \n";
            //Per ogni OrderItem stampo nome, quantità e prezzo finale
            //Se l'array di note non è vuoto, stampo anch lui
            foreach($order->getItems() as $item) {
                echo "{$item->getMenuItem()->getName()} * {$item->getQuantity()} {$item->getSubTotal()} \n";
                print_r(!empty($item->getCustomization()) 
                ? $item->getCustomization()
                : "");
            }
            echo "Coperto: {$order->getCoverCharge()} * {$order->getTable()->getCurrentGuests()} {$order->getSubTotal()} \n";
            echo "Servizio: {$order->getServiceCharge()} \n";
            echo "Mancia: {$order->getTip()} \n";
            echo "Totale da pagare: {$order->getTotal()} \n";
            print($split_by > 1 
            ? "Diviso {$split_by}: {$order->splitBill($split_by)} a persona"
            : "");
        }
    }
}