<?php
return [
 'name'=>env('APP_NAME','Ultimate POS'),'env'=>env('APP_ENV','production'),'debug'=>(bool)env('APP_DEBUG',false),
 'url'=>env('APP_URL','http://localhost'),'timezone'=>env('APP_TIMEZONE','Asia/Dhaka'),'locale'=>env('APP_LOCALE','en'),
 'fallback_locale'=>env('APP_FALLBACK_LOCALE','en'),'faker_locale'=>env('APP_FAKER_LOCALE','en_US'),
 'key'=>env('APP_KEY'),'previous_keys'=>array_filter(explode(',',env('APP_PREVIOUS_KEYS',''))),'cipher'=>'AES-256-CBC',
 'maintenance'=>['driver'=>env('APP_MAINTENANCE_DRIVER','file'),'store'=>env('APP_MAINTENANCE_STORE','database')],
];
