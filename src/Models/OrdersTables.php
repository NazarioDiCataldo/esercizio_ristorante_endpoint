<?php

namespace App\Models {

    use App\Database\DB;
    use App\Traits\WithValidate;
    use Exception;

    //use App\Traits\CalculatePrices;
    //use App\Traits\HasSpecialNotes;

    class OrdersTables extends BaseModel{
        //richiamo i traits
        //use HasSpecialNotes;
        //use CalculatePrices;
        use WithValidate;

        //Props
        protected ?int $table_id = null;
        protected ?int $order_id = null;

        /**
         * Nome della collection
        */
        protected static ?string $table = 'orders_tables';

        //Costruttore
        public function __construct(array $data = []) {
            parent::__construct($data);
        }

        //Getters
        public function getOrderId():int {
            return $this->order_id;
        }

        public function getTableId():int {
            return $this->table_id;
        }

        //Richiamo l'istanza di Table attraverso l'order_id che è unique e quindi può identificare univocamente la singola istanza
        public static function getTableByOrder(Order $order):Table {
            $result = DB::select("SELECT * FROM " . static::getTableName() . " ot JOIN " . Table::getTableName() ." t ON t.id = ot.table_id WHERE ot.order_id = :id", [
                'id' => $order->getId()
            ]);
            $row = $result[0] ?? null;
            return $row ? new Table($row) : null;
        }

        public static function removeRecord(Order $order):void {
            DB::delete("DELETE FROM " . static::getTableName() . " WHERE order_id = :id", ["id" => $order->getId()]);
        }

        public static function getOrdersTablesById(int $order_id):?static {
            $result = DB::select("SELECT * FROM " . static::getTableName() . " WHERE order_id = :id", ['id' => $order_id]);
            $row = $result[0] ?? null;
            return $row ? new static($row) : null;
        }


        //Override dei metodi validate
        protected static function validationRules(): array {
        return [
            "table_id" => ['sometimes','required', 'numeric', 'min:1'],
            "order_id" => ['sometimes', 'required', 'numeric', 'min:1']
            ];
        }
    }
}