<?php

namespace CI\libraries\DB\Drivers;

use MongoDB\Client;

class Mongo extends Client
{
    public $databases = [];
    public $collections = [];


    public function __construct($params)
    {
        $uriOptions = [
            'w'       => 'majority',
            'journal' => true,
        ];

        $driverOptions = [
            'typeMap' => [
                'root'     => 'array',
                'document' => 'array',
                'array'    => 'array',
            ],
        ];

        if (isset($params['dns'])) {
            $dns = $params['dns'];
        } else {
            if (is_array($params['hostname'])) {
                $hostname = implode(',', $params['hostname']);
            } else {
                $hostname = empty($params['hostname']) ? '127.0.0.1'
                    : $params['hostname'];
                empty($params['port']) || $hostname .= ':'.$params['port'];
            }
            $dns = 'mongodb://'.$hostname;
        }

        isset($params['username'])
        && $uriOptions['username'] = $params['username'];
        isset($params['password'])
        && $uriOptions['password'] = $params['password'];
        isset($params['w']) && $uriOptions['w'] = $params['w'];
        isset($params['journal'])
        && $uriOptions['journal'] = $params['journal'];
        isset($params['wTimeoutMS'])
        && $uriOptions['wTimeoutMS'] = $params['wTimeoutMS'];
        isset($params['authSource'])
        && $uriOptions['authSource'] = $params['authSource'];
        isset($params['replicaSet'])
        && $uriOptions['replicaSet'] = $params['replicaSet'];


        isset($params['typeMap'])
        && $dirverOptions['typeMap'] = $params['typeMap'];

        parent::__construct($dns, $uriOptions, $driverOptions);
    }

}