<?php

namespace App\Models {

    use App\Abstract\MenuItem;
    use App\Database\DB;
    use App\Traits\CalculatePrices;
    use App\Traits\HasSpecialNotes;
    use App\Traits\WithValidate;
    use App\Utils\Response;

    //use App\Traits\CalculatePrices;
    //use App\Traits\HasSpecialNotes;

    class OrderItem extends BaseModel{
        //richiamo i traits
        use CalculatePrices;
        use HasSpecialNotes;
        use WithValidate;

        //Props
        public ?MenuItem $menu_item = null;
        public ?int $quantity = null;
        public ?string $customization = null;
        public ?int $order_id = null;

        /**
         * Nome della collection
        */
        protected static ?string $table = "order_items";

        //Costruttore
        public function __construct(array $data = []) {
            parent::__construct($data);
        }

        //Getters
        public function getQuantity():int {
            return $this->quantity;
        }

        public function getCustomization():string {
            return $this->customization;
        }

        public function getItem():MenuItem {
            return $this->menu_item;
        }

        public function getSupplementsArray():array {
            return $this->supplements;
        }

        //Setters
        public function setMenuItem(MenuItem $menu_item):void {
            $this->menu_item = $menu_item;
        }

        public function setSpecialNotes(array $notes):void {
            $this->special_notes = $notes;
        }

        public function setSupplements(array $supplements):void {
            $this->supplements = $supplements;
        }

        //Metodi
        public static function checkMenuItemExists(int $menu_item_id):bool {
            //Verifico se il menu item, tramite l'id e la tipologia esista
            $menu_item = MenuItem::getMenuItemById($menu_item_id);
            
            //Se l'array risultante non è vuoto, il prodotto esiste
            return !empty($menu_item) ? true : false;
        }

        //Ritorna il record del Menu Item attraverso le join
        protected function joinMenuItem(string $param = '*'):array {
            $bridge_table = MenuOrderItem::getTableName();
            $menu_item_table = Dish::getTableName();


            return DB::select("
                    SELECT {$menu_item_table}.{$param}
                    FROM {$menu_item_table}
                    JOIN {$bridge_table} ON {$menu_item_table}.id = {$bridge_table}.menu_item_id
                    WHERE {$bridge_table}.order_item_id = :id",
                    ['id' => $this->getId()]);
        } 

        //Ritorna l'oggetto MenuItem
        protected function getMenuItem():?MenuItem {

            
            //Mi prendo il valore dell'id del MenuItem
            $menu_item = $this->joinMenuItem()[0]; //Prendi solo il primo array (Limit 1) ed estrai il valore del primo elemento

            switch($menu_item['item_type']) {
                case MenuItem::MENU_ITEM_TYPE_DISH :
                    return Dish::find($menu_item['id']);
                
                case MenuItem::MENU_ITEM_TYPE_BEVERAGE :
                    return Beverage::find($menu_item['id']);
                
                case MenuItem::MENU_ITEM_TYPE_DESSERT :
                    return Dessert::find($menu_item['id']);
                
                default:
                    return null;
            }

        }

        //Mi creo un'istanza di MenuItem tramite join e lo imposto tramite setter
        public static function getCompleteOrderItem(OrderItem $item):void {
                //Mi ritorno l'oggetto menu_item tramite id dell'orderitem
                $menu_item = $item->getMenuItem();

                if($menu_item === null) {
                    Response::error('Oggetto dal menu non trovato', Response::HTTP_NOT_FOUND)->send();
                }

                //Tramite setter, imposto il menu item
                $item->setMenuItem($menu_item);
        }  

        public static function getAllOrderItems():array {
            //Mi prendo prima tutti gli orderItem
            $order_items = OrderItem::all();
            
            //Itero sull'array e verifico record per record, per fare delle Join precise
            foreach($order_items as &$item) {
                OrderItem::getCompleteOrderItem($item);
                //Mi richiamo tutti i supplementi per ogni item
                $item->setAllSupplements();
            }
            unset($item);

            return $order_items;
        }

        public static function getOrderItemById(int $id):?static {
            //Mi prendo il singolo orderItem
            $order_item = OrderItem::find($id);

            //Verifico che order_item non sia nulla
            if($order_item === null) {
                Response::error('Oggetto dal menu non trovato', Response::HTTP_NOT_FOUND)->send();
            }
            //chiamo la funziona che imposta il set
            OrderItem::getCompleteOrderItem($order_item);

            //richiamo i supplementi
            $order_item->setAllSupplements();


            return $order_item;
        }

        public function deleteOrderItem():int {

            //Prima dobbiamo togliere il record sulla tabella MenuOrderItem
            //Prima recupero menu_order_item tramite l'order_item_id (che è univoco)
            $menu_order_item = MenuOrderItem::getMenuOrderItemById($this->getId());
            //Poi elimino il record
            $menu_order_item->delete();

            //Infine elimino il record dalla tabella order_items
            return $this->delete();
        }

        public function getSubTotal():?float  {
            //calcola il subtotale dell'orderItem
            //quantità * calculateFinalPrice dal trait (prezzo base + supplementi)
            return $this->calculateFinalPrice($this->getMenuItem()->getBasePrice()) * $this->getQuantity();
        }

        //ritorna le info dietetiche
        public function getDietaryWarnings(): array {
            return $this->menu_item->getDietaryInfo();
        }

        //Override dei metodi validate
        protected static function validationRules(): array {
        return [
            "menu_item_id" => ['sometimes','required', 'numeric', 'min:1', 'max:12'],
            "item_type" => ['sometimes','required', function($field, $value) {
                //Controllo se il tipo è uno tra dish, dessert o beverage
                if(!in_array($value, [MenuItem::MENU_ITEM_TYPE_DESSERT, MenuItem::MENU_ITEM_TYPE_BEVERAGE, MenuItem::MENU_ITEM_TYPE_DISH])) {
                    return "Il tipo deve uno tra: " . MenuItem::MENU_ITEM_TYPE_DESSERT . ", " . MenuItem::MENU_ITEM_TYPE_DISH . " o " . MenuItem::MENU_ITEM_TYPE_BEVERAGE;
                }
            }],
            "order_id" => ['sometimes','required', 'numeric', 'min:1'],
            "quantity" => ['required', "sometimes", "numeric", "min:1", "max: 10"],
            "customizations" => ['min:2', "max:300"],
            "name" => ['min:2', 'max:30'],
            "price" => ['numeric', 'min:0', 'max:99'],
            "message" => ['min:1', 'max:300']
            ];
        }
    }
}