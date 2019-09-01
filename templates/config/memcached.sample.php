<?php

return [
    'default' => 'mem1.5',

    'pool' => [
        'mem1.5' => [
            'host'   => '127.0.0.1',
            'port'   => 11211,
            'weight' => 1,
            'sasl_auth' => false,
            'sasl_user' => '',
            // 'persistent_id' => null,
            // 'libketama_compatible' => false,
            // 'tcp_nodelay' => true,
            // 'compression' => false,
            // 'binary_protocol' => true,
            'sasl_pswd' => '',
        ],

        'user-0' => [
            'host'   => '',
            'port'   => 11211,
            'weight' => 1,
        ],

        // ...
    ],

    'group' => [
        'default' => [
            'mem1.5',
        ],
        'user' => [
            'user-0',
        ],
    ],

    'cache' => [
        'mem1.5',
        'user-0',
    ],
];
