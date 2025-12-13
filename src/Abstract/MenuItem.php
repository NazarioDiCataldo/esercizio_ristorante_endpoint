<?php

namespace App\Abstract {

    use App\Database\DB;
    use App\Models\BaseModel;
    use App\Models\Beverage;
    use App\Models\Dessert;
    use App\Models\Dish;

    //Classe astratta base per tutti gli item del menu
    abstract class MenuItem extends BaseModel{

        //Proprs
        public ?string $name = null;
        public ?string $description = null;
        public ?float $base_price = null;
        public ?string $category = null;
        public ?string $item_type = null;

        //Costanti i tipi di menuItem
        const MENU_ITEM_TYPE_DISH = 'dish';
        const MENU_ITEM_TYPE_DESSERT = 'dessert';
        const MENU_ITEM_TYPE_BEVERAGE = 'beverage';

        /**
         * Nome della collection
        */
        protected static ?string $table = "menu_items";

        //Ritorna tutti i menu_items sotto forma di array associativi
        public static function getAllMenuItems():array {
            return DB::select("SELECT * FROM " . static::getTableName());
        }

        //Ritorna il singolo l'oggetto tramite id
        public static function getMenuItemById(int $id):?static {
            $result = DB::select("SELECT * FROM " . static::getTableName() . " WHERE id = :id", ["id" => $id]);
            //Mi creo l'istanza tramite switch
            switch($result['item_type']) {
                case MenuItem::MENU_ITEM_TYPE_DISH :
                    return Dish::find($result['id']);
                
                case MenuItem::MENU_ITEM_TYPE_BEVERAGE :
                    return Beverage::find($result['id']);
                
                case MenuItem::MENU_ITEM_TYPE_DESSERT :
                    return Dessert::find($result['id']);
                
                default:
                    return null;
            }
        }

        //Costruttore
		public function __construct(array $data = []) {
			parent::__construct($data);
		}

        //Getters
        public function getName():string {
            return $this->name;
        }

        public function getDescription():string {
            return $this->description;
        }

        public function getBasePrice():float {
            return $this->base_price;
        }

        public function getCategoryLabel():string {
            return $this->category;
        }

        public function getItemType():string {
            return $this->item_type;
        }

        //Recupera tutti i prodotti di una categoria 
        public static function getByItemType(string $type): array {
            $table_name = static::getTableName();
            return DB::select("SELECT * FROM {$table_name} WHERE item_type = :item_type", [
                "item_type" => $type
            ]);
        }

        //Metodo astratto
        //Ritorna array con le informazioni dietetiche
        abstract public function getDietaryInfo(): array;

		//Regole di validazione
		protected static function validationRules(): array {
        return [
            "name" => ["sometimes","required", "min:2", "max:100"],
			"description" => ["sometimes","required", "min:2", "max:255"],
			"base_price" => ["sometimes", "required", "numeric", "min:0", "max:9999"],
			"category" => ["sometimes","required", "min:2", "max:30"],
            "item_type" => ['sometimes','required', 'min:2', "max:30", function($field, $value) {
                if($value !== '' && !array_search($value, [MenuItem::MENU_ITEM_TYPE_DISH, MenuItem::MENU_ITEM_TYPE_BEVERAGE, MenuItem::MENU_ITEM_TYPE_DESSERT])) {
                    return "Il tipo deve essere " . MenuItem::MENU_ITEM_TYPE_DISH . ", " . MenuItem::MENU_ITEM_TYPE_BEVERAGE . " oppure " . MenuItem::MENU_ITEM_TYPE_DESSERT . '!';
                }
            }]
        ];
    }

    }
}