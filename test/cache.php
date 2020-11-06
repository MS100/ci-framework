<?php

require dirname(__DIR__).'/vendor/autoload.php';

$memcache = \CI\libraries\Cache\Factory::load(
    [
        'driver'     => 'memcached',
        'key_prefix' => '',
        'servers'    => [
            [
                'hostname' => '192.168.1.116',
                'port'     => 11211,
                'weight'   => '1',
            ],
        ],
    ]
);

//var_dump($memcache->set('saf42dsfasa33f3', 'a'));
var_dump($memcache->add('saf42d312sfasa33f35', 'a', -11));
var_dump($memcache->get('saf42d312sfasa33f35'
));
//var_dump($memcache->getMultiple(['aaaaabc233','adfas','asdfaf','asdfewr']));
//var_dump($memcache->get('aaaaabc233'));

//var_dump($memcache->get('aaa abc2', false));
/*var_dump($memcache->get('bbb', null));
var_dump($memcache->get('bbb', 'a'));

var_dump($memcache->getMultiple(['aaa abc', 'bbb'], 0));
var_dump($memcache->getMultipleExist(['aaa abc', 'bbb']));*/
//var_dump($memcache->deleteMultiple(['aaa abc', 'bbb']));
