<?php

return [
    'profiles' => [
        'tc' => [
            'label' => 'TC / BGP.NET.BR',
            'source' => 'TC',
            'destination_email' => 'auto-dbm@bgp.net.br',
            'subject' => 'IRR Route Update',
            'uses_password' => true,
            'uses_changed' => true,
            'ipv4_default_max_length' => 24,
            'ipv6_default_max_length' => 48,
        ],

        'radb' => [
            'label' => 'RADB',
            'source' => 'RADB',
            'destination_email' => null,
            'subject' => null,
            'uses_password' => true,
            'uses_changed' => true,
            'ipv4_default_max_length' => 24,
            'ipv6_default_max_length' => 48,
        ],

        'altdb' => [
            'label' => 'ALTDB',
            'source' => 'ALTDB',
            'destination_email' => null,
            'subject' => null,
            'uses_password' => true,
            'uses_changed' => true,
            'ipv4_default_max_length' => 24,
            'ipv6_default_max_length' => 48,
        ],

        'manual' => [
            'label' => 'Manual / Outra base',
            'source' => 'LOCAL',
            'destination_email' => null,
            'subject' => null,
            'uses_password' => false,
            'uses_changed' => false,
            'ipv4_default_max_length' => 24,
            'ipv6_default_max_length' => 48,
        ],
    ],
];
