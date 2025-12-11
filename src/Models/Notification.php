<?php

namespace App\Models {

    use App\Services\NotificationService;
    use App\Traits\WithValidate;
    use DateTime;

    class Notification extends BaseModel{
        use WithValidate;

        //props
        protected ?string $message = null;
        protected ?string $type = null;
        protected ?string $timestamp = null;
        protected ?bool $is_read = null;

        /**
         * Nome della collection
        */
        protected static ?string $table = "notifications";

        //Costruttore
        public function __construct(array $data = []) {
            parent::__construct($data);
        }

        //Metodi
        public function markAsRead():void {
            $this->update(
                [
                    "is_read" => true
                ]);
        }

        public function getFormattedMessage():string {
            return "Tipo: {$this->type}" . PHP_EOL . "Messaggio: {$this->message}" . PHP_EOL. "Data:{$this->timestamp}";
        }

        //Override dei metodi validate
        protected static function validationRules(): array {
        return [
                "messaggio" => ['sometimes','required', 'min:2', 'max:300'],
                "type" => ['min:2', 'max:300'],
                "timestamp" => ['datetime'],
                "is_read" => ['bool']
            ];
        }
    }
}