<?php

namespace App\Models {

    use App\Database\DB;
    use App\Traits\WithValidate;
    use Exception;

    //use App\Traits\CalculatePrices;
    //use App\Traits\HasSpecialNotes;

    class MenuOrderItem extends BaseModel{
        //richiamo i traits
        //use HasSpecialNotes;
        //use CalculatePrices;
        use WithValidate;

        //Props
        protected ?int $menu_item_id = null;
        protected ?int $order_item_id = null;

        /**
         * Nome della collection
        */
        protected static ?string $table = 'menu_order_items';

        //Costruttore
        public function __construct(array $data = []) {
            parent::__construct($data);
        }

        //Getters
        public function getMenuItemId():int {
            return $this->menu_item_id;
        }

        public function getOrderItemId():int {
            return $this->order_item_id;
        }

        //Richiamo l'istanza di MenuOrderItem attraverso l'order_item_id che è unique e quindi può identificare univocamente la singola istanza
        public static function getMenuOrderItemById(int $order_item_id):?static {
            $result = DB::select("SELECT * FROM " . static::getTableName() . " WHERE order_item_id = :id", ['id' => $order_item_id]);
            $row = $result[0] ?? null;
            return $row ? new static($row) : null;
        }

        public static function getOrdersById(int $order_item_id):array {
            return DB::select("SELECT order_item_id FROM " . static::getTableName() . " WHERE order_item_id = :order_item_id", [
                "order_item_id" => $order_item_id
            ]);
        }


        //Override dei metodi validate
        protected static function validationRules(): array {
        return [
            "menu_item_id" => ['sometimes','required', 'numeric', 'min:1', function($field, $value) {
                //Verifico che il menu_item esista realmente
                if($value !== null && !OrderItem::checkMenuItemExists($value)) {
                    return "L'oggetto del menu con id {$value} non esiste";
                }
            }],
            "order_item_id" => ['sometimes', 'required', 'numeric', 'min:1']
            ];
        }
    }
}