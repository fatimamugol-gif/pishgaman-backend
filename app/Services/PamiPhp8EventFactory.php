<?php

namespace App\Services;

use PAMI\Message\Message;
use PAMI\Message\Event\UnknownEvent;

class PamiPhp8EventFactory
{
    /**
     * متد استاتیک اصلاح‌شده برای تبدیل پکت خام استریسک به کلاس رویداد
     */
    public static function createFromRaw(Message $message)
    {
        $event = $message->getKey('Event');
        $parts = explode('_', $event);
        $totalParts = count($parts);
        for ($i = 0; $i < $totalParts; $i++) {
            $parts[$i] = ucfirst($parts[$i]);
        }
        
        // 💡 اصلاح طلایی PHP 8: چسباندن آرایه با جابه‌جایی درست پارامترها
        $name = implode('', $parts); 
        
        $className = '\\PAMI\\Message\\Event\\' . $name . 'Event';
        if (class_exists($className, true)) {
            return new $className($message);
        }
        
        return new UnknownEvent($message);
    }
}