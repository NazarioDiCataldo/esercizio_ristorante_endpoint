<?php

namespace App\Interfaces {

    use App\Models\Order;

    //Interfaccia che comprende metodi per gestire gli stati
    interface Stateful {

        //metodo che ritorna lo stato corrente
        public function getCurrentState():string;

        //metodo per verificare se la transizione è valida
        public static function canTransitionTo(Order $order, string $new_state):bool;

        //metodo che cambia lo stato
        public function transitionTo(Order $order, string $new_state):void;

        //metodo che ritorna gli stati raggiungibili
        public static function getAvailableTransitions(Order $order):array;
    }
}