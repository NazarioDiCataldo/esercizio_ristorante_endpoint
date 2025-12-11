<?php 

namespace App\Models {

    use App\Interfaces\Notificable;
    use App\Interfaces\Stateful;
    use App\Services\NotificationService;
    use App\Services\OrderStateService;
    use App\Traits\ValidatesOrder;
    use DateTime;

    class Order implements Stateful, Notificable{

        //richiamo il trait
        use ValidatesOrder;

        //props
        private ?int $id = null;
        private ?Table $table = null; 
        private ?array $items = null;
        private ?string $state = null;
        private ?DateTime $created_at = null;
        private ?float $cover_charge = null;
        private ?float $service_charge = null;
        private ?float $tip = null; 
        private ?array $notifications = null;

        //costanti
        const STATE_NEW = "new";
        const STATE_PREPARING = 'preparing';
        const STATE_READY = "ready";
        const STATE_SERVED = "served";
        const STATE_CANCELLED = 'cancelled';

        //Costruttore
        public function __construct(array $data) {
            $this->id = count(OrderStateService::$all_orders) + 1;
            $this->table = $data['table'] ?? null;
            $this->items = $data['items'] ?? null;
            $this->created_at = new DateTime('now');
            $this->state = $data['state'] ?? 'new';
            $this->cover_charge = $data['cover_charge'] ?? null;
            $this->service_charge = $data['service_charge'] ?? null;
            $this->tip = $data['tip'] ?? null;
            $this->notifications = $data['notifications'] ?? null;

            OrderStateService::$all_orders[$this->id] = $this;
        }

        //Getters
        public function getId():int {
            return $this->id;
        }

        public function getTable():Table {
            return $this->table;
        }

        public function getItems():array {
            return $this->items;
        }

        public function getCoverCharge():float {
            return $this->cover_charge;
        }

        public function getCreatedAt():DateTime {
            return $this->created_at;
        }

        public function getServiceCharge():float {
            return $this->service_charge;
        }

        public function getTip():string {
            return $this->tip;
        }

        public function getNotifications(): array {
            return $this->notifications;
        }

        //Setters
        public function setId(int $id):static {
            $this->id = $id;
            return $this;
        }

        public function setState(string $new_state ) {
            $this->state = $new_state;
            return $this;
        }

        public function setTable(Table $table):static {
            $this->table = $table;
            return $this;
        }

        public function setItems(array $items):static {
            $this->items = $items;
            return $this;
        }

        public function setCreatedAt(DateTime $created_at):static {
            $this->created_at = $created_at;
            return $this;
        }

        public function setCoverCharge(float $cover_charge):static {
            $this->cover_charge = $cover_charge;
            return $this;
        }

        public function setServiceCharge(float $service_charge):static {
            $this->service_charge = $service_charge;
            return $this;
        }

        public function setTip(float $tip):static {
            $this->tip = $tip;
            return $this;
        }

        public function setNotifications(array $notifications):static {
            $this->notifications = $notifications;
            return $this;
        }

        //Metodi nativi

        //aggiunge ordine
        public function addOrderItem(OrderItem $item):void {
            if($this->validateOrderItem($item)) {
                $this->items[] = $item;
            }
        }

        //rimuove ordine tramite indice
        public function removeItem(int $index):void {
            $this->items = array_filter($this->items, function ($item) use($index) {
                return $index !== (int)key($this->items); 
            });
        }

        //Metodi vincolati dall'interfaccia Notificable
        //aggiunge nuova notifica
        public function notify(string $message, string $type = 'info'): void {
            $notifications_id = count(NotificationService::$all_notifications) + 1;

            $this->notifications[$notifications_id] = new Notification([
                "message" => $message,
                "type" => $type,
                "created_at" => date('Y-m-d H:i:s'),
                "is_read" => false
            ]);
        }

        //Somma subtotal di tutti gli items
        public function getItemsTotal(): float {
            $total = 0;
            foreach($this->items as $item) {
                $total += $item->getSubTotal();
            }

            return $total;
        }

        //Coperto * numero persone
        public function getTotalCoverCharge():float {
            return $this->getCoverCharge() * $this->getTable()->getCurrentGuests();
        }

        //getItemsTotal() + totale coperto
        public function getSubTotal():float {
            return $this->getItemsTotal() + $this->getTotalCoverCharge();
        }

        //subtotale + serviceCharge + tip
        public function getTotal():float {
            return $this->getSubTotal() + $this->tip + $this->service_charge;
        }

        //totale / numero persone
        public function splitBill(int $numbers_of_people) {
            $quoz = $this->getTotal() / $numbers_of_people;
            return number_format($quoz, 2, '.');
        }

        //aggiunge mancia
        public function addTip(float $amount): void {
            $this->tip += $amount;
        }

        //Metodi vincolati dall'interfaccia Stateful
        public function getCurrentState():string {
            return $this->state;
        }  
        
        //Verifica se è possibile cambiare lo stato
        public function canTransitionTo(string $new_state): bool {
            //Verifica per ogni stato
            //Se l'array degl stati disponibili ritorna un'array non vuoto, verifica che lo state passato sia contenuto nell'array ritornato
            if(!empty($this->getAvailableTransitions()) && is_numeric(array_search($new_state, $this->getAvailableTransitions())) ) {
                return true;
            } else {
                return false;
            }
        }

        //Ritorna i cambiamenti di stato possibili per ogni state
        public function getAvailableTransitions(): array {
            
            if($this->state === $this::STATE_NEW) {
                //new → preparing
                //new → served (se annullato)
                return [$this::STATE_PREPARING, $this::STATE_SERVED];
                
            } else if($this->state === $this::STATE_PREPARING) {
                //preparing → ready
                //preparing → new (se annullato)
                return [$this::STATE_READY, $this::STATE_NEW];
            } else if($this->state === $this::STATE_READY) {
                //ready → served
                return [$this::STATE_READY];
            } else {
                //se è served, non può cambiare con nessuno e quindi array vuoto
                //oppure se new_state non è compatibile sempre array vuoto
                return [];
            }
        }

        //cambia stato
        public function transitionTo(string $new_state): void {
            //se ritorna true, posso effettuare il passaggio
            if($this->canTransitionTo($new_state)) {
                $this->state = $new_state;
            }
        }
    }
}