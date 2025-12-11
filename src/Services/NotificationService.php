<?php

namespace App\Services {

    use App\Interfaces\Notificable;

    //servizio per gestione notifiche
    class NotificationService {
        //Props
        public static $all_notifications = []; //Registro statico

        public function sendNotification(Notificable $target, string $message, string $type):void {
            //Aggiunge una nuova notifica
            //solitamente sarà un oggetto Order a richiamare
            $target->notify($message, $type);
        }

        //Mostra tutte le notifiche non lette
        public function getUnreadNotifications(): array {
            return array_filter(self::$all_notifications, function ($not) {
                //deve essere il contrario di isRead
                return !$not->getIsRead();
            });
        }

        //Ritorna tutte le notifiche di un tipo 
        public function getNotificationsByType(string $type): array {
            return array_filter(self::$all_notifications, function ($not) use ($type) {
                return $not->getType() === $type;
            });
        }

        //Trasforma tutte le notifiche in lette
        public function markAllAsRead(): void {
            foreach(self::$all_notifications as &$not) {
                $not->markAllAsRead();
            }

            unset($not);
        }

        public function printNotificationLog(): void {
            foreach(self::$all_notifications as &$not) {
                $not->getFormattedMessage();
            }
        }
    }
}