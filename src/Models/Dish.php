<?php

namespace App\Models {

    use App\Abstract\MenuItem;
    use App\Traits\WithValidate;

    class Dish extends MenuItem {
        //Richiamo i traits
        use WithValidate;

        //Props
        public ?string $allergens = null;
        public ?bool $is_vegetarian = null;
        public ?bool $is_vegan = null;
        public ?bool $is_gluten_free = null;
        public ?int $preparation_time = null;

        /**
         * Nome della collection
        */
        protected static ?string $table = "menu_items";

        //Costruttore
		public function __construct(array $data = []) {
			parent::__construct($data);
		}

        //Metodo ereditato
        public function getDietaryInfo(): array{
            return ['65 kcal'];
        }
        
        protected static function validationRules(): array {
            //Unisco in un unico array gli schemi di validazione, richiamando il metodo del padre
            return [
                ...parent::validationRules(),
                "allergens" => ["min:2", "max:255"],
                "is_vegetarian" => ["bool"],
                "is_vegan" => ["bool"],
                "is_gluten_free" => ["bool"],
                "preparation_time" => ["numeric", "min:0", "max:60"]
            ];
        }
    }
}