<?php 

namespace App\Models {

    use App\Database\DB;
    use App\Interfaces\Notificable;
    use App\Interfaces\Stateful;
    use App\Services\NotificationService;
    use App\Services\OrderStateService;
    use App\Traits\ValidatesOrder;
    use App\Traits\WithValidate;
    use App\Utils\Response;

    class Order extends BaseModel implements Stateful, Notificable{

        //richiamo i traits
        use ValidatesOrder;
        use WithValidate;

        //props
        protected ?Table $table_guests = null; 
        protected ?array $items = null;
        protected ?string $state = null;
        protected ?string $datetime = null;
        protected ?float $cover_charge = null;
        protected ?float $service_charge = null;
        protected ?float $tip = null; 
        protected ?array $notifications = null;

        //costanti
        const STATE_NEW = "new";
        const STATE_PREPARING = 'preparing';
        const STATE_READY = "ready";
        const STATE_SERVED = "served";
        const STATE_CANCELLED = 'cancelled';

        /**
         * Nome della collection
        */
        protected static ?string $table = "orders";

        //Costruttore
        public function __construct(array $data = []) {
            parent::__construct($data);
        }

        //Getters
        public function getId():int {
            return $this->id;
        }

        public function getTable():Table {
            return $this->table_guests;
        }

        public function getItems():array {
            return $this->items;
        }

        public function getCoverCharge():float {
            return $this->cover_charge;
        }

        public function getDatetime():string {
            return $this->datetime;
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

        public function setTable(Table $table_guests):static {
            $this->table_guests = $table_guests;
            return $this;
        }

        public function setItems(array $items):static {
            $this->items = $items;
            return $this;
        }

        public function setDatetime(string $datetime):static {
            $this->datetime = $datetime;
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

        //Ritorna tutti gli orderItems collegati all'ordine passato come parametro
        public static function getOrderItemByOrder(int $order_id, string $select = 'id'):array { //Di default ci interssa sapere solo l'id dell'orderItem, cosi da poterci creare l'istanza
            $array_order_items = DB::select("SELECT id FROM " . OrderItem::getTableName() . " WHERE order_id = :id", 
                [
                    "id" => $order_id
                ]); 

            //Trasformo da array di risultati della query, in array di oggetti OrderItem
            $array_order_items = array_map(function ($id) {
                $id = array_values($id)[0];
                return OrderItem::getOrderItemById($id);
            } ,$array_order_items);

            return $array_order_items;
        }

        //Ricevo tutti gli ordini, completi di riferimento al tavolo
        public static function getAllOrders(): array {
            //Mi prendo prima tutti gli ordini
            $orders = Order::all();

            //Itero sull'array e verifico record per record, per fare delle Join precise
            foreach($orders as $order) {
                //Mi prendo tutti gli order_item collegati all'ordine
                $order->setItems(Order::getOrderItemByOrder($order->getId()));
                //Mi prendo l'istanza del tavolo dalla tabella pivot
                $tablo = OrdersTables::getTableByOrder($order);
                //Imposto il tavolo tramite setter
                $order->setTable($tablo);
            }

            return $orders;
        } 

        //Ricevo l'ordine specifico, completo di riferimento al tavolo
        public static function getOrderById(int $id): ?static {
            //Mi prendo il singolo ordine, tramite id passato come parametro
            $order = Order::find($id);

            //Verifico che esista, altrimenti lancia errore
            if($order === null) {
                Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
            }

            //Mi prendo tutti gli order_item collegati all'ordine
            $order->setItems(Order::getOrderItemByOrder($order->getId()));

            $tablo = OrdersTables::getTableByOrder($order);
            //Imposto il tavolo tramite setter
            $order->setTable($tablo);

            return $order;
        }

        //Rimuovo l'ordine specifico, rimuovo il record nella tabella pivot e libero il tavolo
        public static function removeOrder(int $id): void {
            //Mi prendo il singolo ordine, tramite id passato come parametro
            $order = Order::find($id);

            //Verifico che esista, altrimenti lancia errore
            if($order === null) {
                Response::error('Ordine non trovato', Response::HTTP_NOT_FOUND)->send();
            }

            //Mi creo l'oggetto Table, per poi poter liberare il tavolo
            $tablo = OrdersTables::getTableByOrder($order);
            $tablo->free();

            //Rimuovo il record sulla tabella
            OrdersTables::removeRecord($order);


            //Infine elimino il record dalla tabella orders
            $order->delete();
        }
        
        //Verifica se è possibile cambiare lo stato
        public static function canTransitionTo(Order $order, string $new_state): bool {
            //Verifica per ogni stato

            $available_transitions = Order::getAvailableTransitions($order);
            //Se l'array degl stati disponibili ritorna un'array non vuoto, verifica che lo state passato sia contenuto nell'array ritornato
            if(!empty($available_transitions) && is_numeric(array_search($new_state, $available_transitions)) ) {
                return true;
            } else {
                return false;
            }
        }

        //Ritorna i cambiamenti di stato possibili per ogni state
        public static function getAvailableTransitions(Order $order): array {
            
            if($order->getCurrentState() === Order::STATE_NEW) {
                //new → preparing
                //new → served (se annullato)
                return [Order::STATE_PREPARING, Order::STATE_SERVED];
                
            } else if($order->getCurrentState() === Order::STATE_PREPARING) {
                //preparing → ready
                //preparing → new (se annullato)
                return [Order::STATE_READY, Order::STATE_NEW];
            } else if($order->getCurrentState() === Order::STATE_READY) {
                //ready → served
                return [Order::STATE_READY];
            } else {
                //se è served, non può cambiare con nessuno e quindi array vuoto
                //oppure se new_state non è compatibile sempre array vuoto
                return [];
            }
        }

        //cambia stato
        public function transitionTo(Order $order, string $new_state): void {
            //se ritorna true, posso effettuare il passaggio
            if(Order::canTransitionTo($order, $new_state)) {
                $this->state = $new_state;
            }
        }

        //Override dei metodi validate
        protected static function validationRules(): array {
        return [
            "guests" => ["sometimes", "required", "numeric", "min:1", "max:12"],
            "state" => ['sometimes','required', function($field, $value, $data) {
                $order = null;
                $current_state = null;
                //Mi prendo l'order, tramite id (se esiste)
                if(array_key_exists('id', $data)) {
                    //Se si, uso il metodo find
                    $order = Order::find($data['id']); //non mi serve sapere il tavolo, quindi semplice find
                }

                //Controllo se il tipo è uno tra dish, dessert o beverage
                if(!in_array($value, [Order::STATE_NEW, Order::STATE_PREPARING, Order::STATE_READY, Order::STATE_SERVED, Order::STATE_CANCELLED])) {
                    return "Il tipo deve uno tra: " . Order::STATE_NEW . ", " . Order::STATE_PREPARING . ", " . Order::STATE_READY . ", " . Order::STATE_SERVED . " o " . Order::STATE_CANCELLED;
                    
                    //Se sono nell'update, verifico se il nuovo state è compatibile
                } else if($order !== null && !Order::canTransitionTo($order, $value)) {
                    return "Il nuovo stato deve essere uno tra " . Order::getAvailableTransitions($order);
                } 

                return null;
            }],
            "cover_charge" => ['required', "sometimes", "numeric", "min:0", "max: 99.99"],
            "service_charge" => ['sometimes', "numeric", "min:0", "max: 99.99"],
            "tip" => ['sometimes', "numeric", "min:0", "max: 99.99"],
            "datetime" => ['sometimes', "datetime"],
            ];
        }
    }
}