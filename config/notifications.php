<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Süre dolmadan hatırlatma eşikleri (saat)
    |--------------------------------------------------------------------------
    | Görev bitişine bu kadar saat veya daha az kaldığında bir kez bildirim gönderilir.
    */
    'due_reminder_hours' => [24, 12, 6, 1],

    /*
    |--------------------------------------------------------------------------
    | Süre dolduktan sonra tekrar hatırlatma aralığı (saat)
    |--------------------------------------------------------------------------
    */
    'overdue_reminder_interval_hours' => 24,

];
