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
        protected ?int $guests = null;
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

        public function getTip():float {
            return $this->tip ?? 0;
        }

        public function getNotifications(): array {
            return $this->notifications;
        }

        public function getGuests():int {
            return $this->guests;
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

        public function setGuests(int $guests):static {
            if($this->getTable()->validateTableCapacity($guests)) {
                $this->guests = $guests;
            }
            return $this;
        }

        //Metodi nativi

        //aggiunge ordine
        public function addOrderItem(array $data):void {
            //Verifico se lo stato dell'ordine consente l'aggiunta di un orderItem
            if(!$this->canAddItem()) {
                Response::error("Lo stato dell'ordine non consente di aggiungere altri elementi", Response::HTTP_NOT_FOUND)->send();
                return;
            }

            //Verifico che l'array di dati non sia vuoto
            if(!Order::validateOrderItem($data)) {
                Response::error(OrderItem::NO_DATA, Response::HTTP_BAD_REQUEST)->send();
                return;
            }

            //Se ha passato i primi controlli creo l'orderItem
            $order_item = OrderItem::createOrderItem(array_merge($data, ['order_id' => $this->getId()])); //Gli passo pure l'order_id
        
            //notifica di aggiunta orderItem 
            $this->notify([
                "message" => "Nuovo oggetto del menu aggiunto all'ordine: " . $order_item->toArray(),
            ]); 

            //Aggiungo l'orderItem anche all'oggetto corrente
            $current_items = $this->getItems();
            $this->setItems([
                ...$current_items,
                $order_item
            ]);
        }

        //rimuove ordine tramite indice
        public function removeItem(array $data):void {

            //Verifico se lo stato dell'ordine consente la rimozione di un orderItem
            if(!$this->canAddItem()) {
                Response::error("Lo stato dell'ordine non consente di rimuovere elementi", Response::HTTP_NOT_FOUND)->send();
                return;
            }

            //Verifico che l'array di dati non sia vuoto
            if(!Order::validateOrderItem($data)) {
                Response::error(OrderItem::NO_DATA, Response::HTTP_BAD_REQUEST)->send();
                return;
            }

            //Verifico che l'orderItem sia presente
            $index = $data['order_item_id'];
            $order_item = OrderItem::find($index);
            
            if($order_item === null) {
                Response::error('OrderItem non trovato', Response::HTTP_NOT_FOUND)->send();
                return;
            }
            
            //Rimuovo l'orderItem anche dall'array
            $items = array_filter($this->items, function () use($index) {
                return $index !== (int)key($this->items); 
            });

            $this->setItems($items);

            //notifica di aggiunta orderItem 
            $this->notify([
                "message" => "Oggetto del menu rimosso dall'ordine.",
            ]); 


            //Procediamo con la rimozione sul db
            $order_item->deleteOrderItem();


        }

        //Metodi vincolati dall'interfaccia Notificable
        //aggiunge nuova notifica
        public function notify(array $data): void {
            //Creo l'istanza di notifica
            $not = Notification::create(array_merge($data, ['order_id' => $this->getId()]));

            //Aggiungo la notifica all'array di notifiche
            $this->notifications[$not->getId()] = $not;
        }

        //Somma subtotal di tutti gli items
        public function getItemsTotal(): float {
            $total = 0;
            //print_r($this->getItems());
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
        public function splitBill(int $numbers_of_people = 1) {
            $quoz = $this->getTotal() / $numbers_of_people;
            return number_format($quoz, 2, '.');
        }

        //aggiunge mancia
        public function addTip(float $amount): void {
            $this->tip += $amount;
            //Aggiorno il valore sul Database
            $this->update(['tip' => $this->tip]);
        }

        //Metodi vincolati dall'interfaccia Stateful
        public function getCurrentState():string {
            return $this->state;
        }  

        //Ritorna tutte le notifiche associate ad un'ordine
        public function getAllNotifications(): array {
            $result = DB::select("SELECT * FROM " . Notification::getTableName() . " WHERE order_id = :id", ['id' => $this->getId()]);
            if(empty($result)) {
                return [];
            } 

            return array_map(function ($item) {
                return Notification::create($item);
            }, $result);
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
                //Imposto tutte le notifiche
                $nots = $order->getAllNotifications();
                $order->setNotifications($nots);
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

            //Imposto tutte le notifiche
            $nots = $order->getAllNotifications();
            $order->setNotifications($nots);

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

            //verifico se il nuovo state è cancel oppure no
            if($new_state === Order::STATE_CANCELLED) {
                if(OrderStateService::canCancel($order)) {
                    return true;
                }
            
                return false;
            }

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
        public function transitionTo(string $new_state): void {
            //se ritorna true, posso effettuare il passaggio
            if(Order::canTransitionTo($this, $new_state)) {
                $this->update([
                    "state" => $new_state
                ]);
            }
        }

        //Override dei metodi validate
        protected static function validationRules(): array {
        return [
            "guests" => ["sometimes", "required", "numeric", "min:1", "max:12"],
            "state" => ['sometimes','required', function($field, $value, $data) {
                $order = null;

                //Mi prendo l'order, tramite id (se esiste)
                if(array_key_exists('id', $data)) {
                    //Se si, uso il metodo find
                    $order = Order::find($data['id']); //non mi serve sapere il tavolo, quindi semplice find
                }

                //Controllo se lo state inserito è compatibile con quelli disponibili
                if(!in_array($value, [Order::STATE_NEW, Order::STATE_PREPARING, Order::STATE_READY, Order::STATE_SERVED, Order::STATE_CANCELLED])) {
                    return "Il tipo deve uno tra: " . Order::STATE_NEW . ", " . Order::STATE_PREPARING . ", " . Order::STATE_READY . ", " . Order::STATE_SERVED . " o " . Order::STATE_CANCELLED;
                    
                    //Se sono nell'update, verifico se il nuovo state è compatibile
                } else if($order !== null && !Order::canTransitionTo($order, $value)) {
                    return "Il nuovo stato deve essere uno tra " . implode(", ",Order::getAvailableTransitions($order));
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