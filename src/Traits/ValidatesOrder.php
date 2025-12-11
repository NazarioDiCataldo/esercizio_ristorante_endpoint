<?php

namespace App\Traits {

    use App\Models\Order;
    use App\Models\OrderItem;
    use App\Models\Table;
    use Exception;

    //Validazione ordini
    trait ValidatesOrder {

        //verifica che l'ordine sia valido
        public function validateOrderItem(OrderItem $item):bool {
            foreach($item as $prop) {
                return false;
            } 

            return true;
        }

        public function validateTableCapacity(Table $table, int $guests):bool {
            //Incrementa il numero di ospiti solo se il numero di posti è maggiore rispetto agli ospiti
            if($table->getCurrentGuests() + $guests <= $table->getCapacity()) {
                return true;
            } else {
                return false;
            }
        }

        //Verifica che si può aggiungere l'item
        public function canAddItem(Order $order, OrderItem $item): bool {
            if($order->getCurrentState() === $order::STATE_NEW || $order->getCurrentState() === $order::STATE_PREPARING) {
                return true;
            } else {
                return false;
            }
        }
    }
}