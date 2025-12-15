<?php

namespace App\Traits {

    use App\Models\Order;
    use App\Models\OrderItem;
    use App\Models\Table;
    use Exception;

    //Validazione ordini
    trait ValidatesOrder {

        //verifica che l'ordine sia valido
        public static function validateOrderItem(array $data):bool {
            return !empty($data) ? true : false; //Gli passo l'array di dati e verifico che non sia vuoto
        }

        //Verifica che si può aggiungere l'item
        public function canAddItem(): bool {
            if($this->getCurrentState() === Order::STATE_NEW || $this->getCurrentState() === Order::STATE_PREPARING) {
                return true;
            } else {
                return false;
            }
        }
    }
}