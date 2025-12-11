<?php

namespace App\Interfaces {

    //Interfaccia che comprende metodi per gestire gli stati
    interface Stateful {

        //metodo che ritorna lo stato corrente
        public function getCurrentState():string;

        //metodo per verificare se la transizione è valida
        public function canTransitionTo(string $new_state):bool;

        //metodo che cambia lo stato
        public function transitionTo(string $new_state):void;

        //metodo che ritorna gli stati raggiungibili
        public function getAvailableTransitions():array;
    }
}