<?php

namespace App\Services {

    use App\Models\Order;

    class OrderStateService {
        //Props
        public static $all_orders = []; //Registro statico

        //Metodo per varificare se è possibile avanzare lo stato
        public static function validateTransition(Order $order, string $newState): bool {
            return empty(Order::canTransitionTo($order, $newState)) ? false : true;
        }

        //Metodo che ritorna gli avanzamenti disponibili
        public static function getNextValidStates(Order $order): array {
            return Order::getAvailableTransitions($order);
        }

        //Metodo per verificare se un ordine sia cancellabile
        public static function canCancel(Order $order):bool {
            if($order->getCurrentState() === $order::STATE_NEW || $order->getCurrentState() === $order::STATE_PREPARING) {
                return true;
            } else {
                return false;
            }
        }

        //Cancella ordine
        public static function cancelOrder(Order $order):void {
            if(self::canCancel($order)) {
                $order->update([
                    "state" => Order::STATE_CANCELLED
                ]);
            }
        }
    }
}