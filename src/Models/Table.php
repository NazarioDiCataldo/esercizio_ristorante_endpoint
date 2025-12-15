<?php 

namespace App\Models;

    use App\Traits\WithValidate;

    class Table extends BaseModel{
        use WithValidate;

        //Props
        protected ?int $number = null;
        protected ?int $capacity = null;
        protected ?bool $is_occupied = null;
        protected ?int $current_guests = null;

    /**
     * Nome della collection
    */
    protected static ?string $table = "tables";

    //Costruttore
    public function __construct(array $data = []) {
        parent::__construct($data);
    }

    //Getters
    public function getNumber():int {
        return $this->number;
    }

    public function getCapacity():int {
        return $this->capacity;
    }

    public function getCurrentGuests():int {
        return $this->current_guests;
    }

    //Metodi
    public function occupy(int $guests):void {
        //Prima verifico se il tavolo non sia gà occupato
        if($this->hasCapacity($guests)) {
            //Aggiungo le persone e rendo il tavolo occupato
            $this->update([
                "is_occupied" => true,
                "current_guests" => $guests
            ]);
        }

    }

    public function free():void {
        //Azzera il numero di ospiti al tavolo e lo rende libero
        $this->update([
            "is_occupied" => false,
            "current_guests" => 0
        ]);
    }

    //Verifica se il numero di ospiti sia minore o uguale rispetto alla capacità del tavolo
    public function validateTableCapacity(int $guests):bool {
        //Incrementa il numero di ospiti solo se il numero di posti è maggiore rispetto agli ospiti
        if($this->getCurrentGuests() + $guests <= $this->getCapacity()) {
            return true;
        } else {
            return false;
        }
    }

    public function hasCapacity(int $guests):bool {
        //Ritorna il contrario di is_occupied
        //non occupato(false) -> ha capacità(true)
        //occupato(true) -> non ha capacita(false)
        return !$this->is_occupied && $this->validateTableCapacity($guests);
    }

    //Override dei metodi validate
    protected static function validationRules(): array {
    return [
        "capacity" => ['sometimes','required', 'numeric', 'min:1', 'max:12'],
        "is_occupied" => ['bool'],
        "current_guests" => ["numeric", "min:0", "max:12", function($field, $value, $data) {
            //Prima verifico che value sia valido
            if($value !== null && $value !== '') {
                //Mi prendo l'oggetto table in questione, tramide l'id passato da parametro e lo converto in array
                $table = static::find($data['id'])->toArray();
                //Verifico che la il numero di opsiti non sia superiore alla capienza massima, altrimenti errore di validazione!
                if($value > $table['capacity']) {
                    return "Il numero di ospiti non può essere superiore alla capienza del tavolo";
                }
                return null;
            }
            return null;
        }]
        ];
    }
}
