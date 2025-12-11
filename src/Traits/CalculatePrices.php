<?php

namespace App\Traits {

    use App\Database\DB;

    //calcolare prezzi con supplmenti
    trait CalculatePrices {

        protected ?array $supplements = null; //array associativo di supplementi ['nome' => prezzo]

        //Imposta tutti i supplementi in automatico all'orderItem, tramite setter
        public function setAllSupplements():void {
            $supplments = $this->getSupplements();

            $this->setSupplements($supplments);
        }

        //Ritorna tutti i supplimenti dalla tabella supplementi
        public function getSupplements(string $name = ''):array {
            $query = "SELECT * FROM supplements WHERE order_item_id = :id";
            
            $supplments = [];
            if(!$name) {
                $supplments = DB::select($query, ["id" => $this->getId()]);

            } else {
                $supplments = DB::select($query . " AND name = :name", ["id" => $this->getId(), 'name' => $name]);
            }

            return $supplments;
        }

        //aggiunge supplementi all'array di supplementi
        public function addSupplements(array $data):int {
            //Insert nella tabella supplements
            $id = DB::insert("INSERT INTO supplements(name, price, order_item_id) VALUES (:name, :price, :order_item_id)", [
                "name" => $data['name'],
                "price" => $data['price'],
                "order_item_id" => $data['order_item_id']
            ]);

            //Aggiungo il supplement all'orderItem
            $this->supplments[$id] = $data;

            return $id;
        }

        //Rimuovi il supplemento dall'array di supplementi
        public function removeSupplements(string $name):void {
            //Query di delete
            DB::delete("DELETE FROM supplements WHERE order_item_id = :id AND name = :name", ['id' => $this->getId(), 'name' => $name]);

            //Aggiorno il nuovo array di supplementi
            $this->setAllSupplements();
        }

        //Ritorna la somma dei prezzi di tutti i supplementi
        protected function getSupplementsTotal():float {
            //Ritorna la SUM dal db, tramite order_item_id
            $sum = DB::select("SELECT SUM(price) FROM supplements WHERE order_item_id = :id", ['id' => $this->getId()]);
            
            return $sum && array_values($sum[0])[0] !== null
                ? array_values($sum[0])[0]
                : 0;
        }

        public function calculateFinalPrice(float $basePrice): float {
            return $basePrice + $this->getSupplementsTotal();
        }

    }
}