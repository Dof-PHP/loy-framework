<?php

return [
    'default' => 'mysql57-rw',

    'pool' => [
        'mysql57-rw' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'user',
            'passwd' => 'pswd',
        ],
    ],

    'group' => [
        'master' => [
            'mysql57-rw',
        ],

        'slave' => [
            'mysql57-ro-1',
            'mysql57-ro-2',
        ],
    ],
];
