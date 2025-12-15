<?php

namespace App\Traits {

    use App\Database\DB;

    //gestire note speciali
    trait HasSpecialNotes {
        
        public ?array $special_notes = null;

        //Setter
        public function setNotes():array {
            return $this->special_notes;
        }

        //aggiunge una note all'array
        public function addNote(array $data):int {
            //Insert nella tabella supplements
            $id = DB::insert("INSERT INTO notes(message, order_item_id) VALUES (:message, :id)", [
                "message" => $data['message'],
                "id" => $this->getId()
            ]);

            //Aggiungo la nota all'orderItem
            $this->special_notes[$id] = $data;
            //$this->getSupplementsArray()->sort();

            return $id;
        }

        //rimuove una nota
        public function removeNote(array $data):void {
            //Query di delete
            DB::delete("DELETE FROM notes WHERE order_item_id = :id AND message = :message", [
                "message" => $data['message'],
                "id" => $this->getId()
            ]);

            //Aggiorno il nuovo array di note
            $this->setAllNotes();
        }

        //Aggiunge le tutte le note all'array dell'oggetto OrderItem
        public function setAllNotes():void {
            $this->setNotes($this->getNotes());
        }

        //ritorna tutte le note
        public function getNotes(string $message = ''):array {
            $query = "SELECT * FROM notes WHERE order_item_id = :id";
            $notes = [];
            if(!$notes) {
                $notes = DB::select($query, ["id" => $this->getId()]);

            } else {
                $notes = DB::select($query . " AND message = :message", ["id" => $this->getId(), 'message' => $message]);
            }

            return $notes;
        }

        //ritorna true/false se c'è la nota
        public function hasNotes(string $note):bool {
            return !empty($this->getNotes($note)) ? true : false;
        }

        //cancella tutte le note
        public function clearNote():void {
            DB::delete("DELETE FROM notes WHERE order_item_id = :id", ['id' => $this->getId()]);

            //Svuoto l'array di note sull'oggetto
            $this->setAllNotes([]);
        }
    }
}