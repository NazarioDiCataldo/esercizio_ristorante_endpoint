<?php

namespace App\Models {

    use App\Abstract\MenuItem;
    use App\Traits\WithValidate;

    class Dessert extends MenuItem {

        //Richiamo i traits
        use WithValidate;

        //Props
        protected ?bool $is_gluten_free = null;
        protected ?bool $is_sugar_free = null;
        protected ?bool $contains_nuts = null;

        /**
         * Nome della collection
        */
        protected static ?string $table = "menu_items";

        //Costruttore
		public function __construct(array $data = []) {
			parent::__construct($data);
		}

        //metodo ereditato dalla classe astratta
        public function getDietaryInfo(): array{
            return ['165 kcal'];
        }

        protected static function validationRules(): array {
            //Unisco in un unico array gli schemi di validazione, richiamando il metodo del padre
            return [
                ...parent::validationRules(),
                "is_sugar_free" => ["bool"],
                "contains_nuts" => ["bool"],
                "is_gluten_free" => ["bool"],
            ];
        }
    }
}