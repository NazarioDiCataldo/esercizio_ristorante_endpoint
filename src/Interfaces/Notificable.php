<?php

namespace App\Interfaces {

    //interfaccia per entità che possono inviare notifiche
    interface Notificable {

        //metodo che invia una notifica
        public function notify(array $data):void;

        //ritorna tutte le notifiche
        public function getNotifications():array;
    }
}