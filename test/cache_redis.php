<?php

require dirname(__DIR__).'/vendor/autoload.php';

$redis = \CI\libraries\Cache\Factory::load(
    [
        'driver' => 'redis',
        'connection' => 'cache',
    ]
);

/*var_dump($redis->set('aaa abc1', false));
var_dump($redis->set('aaa abc2', false));
var_dump($redis->get('aaa abc2'));
var_dump($redis->get('aaa abc3'));
var_dump(
    $redis->getMultiple(['aaa abc1', 'aaa abc2', 'aaa abc3', 'aaa abc4'], 'a')
);*/
//var_dump($redis->set('saf42dsfasa33f3', 'a'));
var_dump($redis->set('asaf42dsfasfdsa33f34','b'));

var_dump($redis->replace('asaf42dsfasfdsa33f34', 'a', -1));
/*var_dump($redis->put('4asfasdbb3b3','aaa',-1000));
var_dump($redis->put('4asfasdbb3b3','aaa',1000));
var_dump($redis->has('4asfasdbb3b3'));*/
/*var_dump($redis->get('bbb', null));
var_dump($redis->get('bbb', 'a'));

var_dump($redis->getMultiple(['aaa abc', 'bbb'], 'b'));
var_dump($redis->getMultipleExist(['aaa abc', 'bbb']));
//var_dump($redis->deleteMultiple(['aaa abc', 'bbb']));*/
