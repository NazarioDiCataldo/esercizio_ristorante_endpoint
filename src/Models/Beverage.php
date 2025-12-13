<?php

namespace App\Models {

    use App\Abstract\MenuItem;

    use App\Traits\WithValidate;

    class Beverage extends MenuItem{
        //Richiamo i traits
        use WithValidate;

        //Props
        public ?int $volume = null;
        public ?bool $is_alcoholic = null;
        public ?string $temperature = null;
        
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
            return ['15 kcal'];
        }

        protected static function validationRules(): array {
            //Unisco in un unico array gli schemi di validazione, richiamando il metodo del padre
            return [
                ...parent::validationRules(),
                "is_alcoholic" => ["bool"],
                "temperature" => ["min:3", "max:10"],
                "volume" => ["numeric", "min:10", "max:2000"]
            ];
        }
    }
}