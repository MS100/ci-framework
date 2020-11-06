<?php

require dirname(__DIR__).'/vendor/autoload.php';

$db = \CI\libraries\DB\Factory::load(
    [
        'hostname' => env('MYSQL_HOST'),
        'port' => env('MYSQL_PORT'),
        'username' => env('MYSQL_USERNAME'),
        'password' => env('MYSQL_PASSWORD'),
        'database' => '',
        'dbdriver' => 'mysqli',
        'pconnect' => false,
        'db_debug' => false,
        'cache_on' => false,
        'char_set' => 'utf8mb4',
        'dbcollat' => 'utf8mb4_general_ci',
        'encrypt' => false,
        'compress' => false,
        'stricton' => true,
    ]
);

var_dump($db);


