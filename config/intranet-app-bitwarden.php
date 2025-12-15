<?php

// config for Hwkdo/IntranetAppBitwarden
return [
'roles' => [
        'admin' => [
            'name' => 'App-Bitwarden-Admin',
            'permissions' => [
                'see-app-bitwarden',
                'manage-app-bitwarden',
            ]
        ],
        'user' => [
            'name' => 'App-Bitwarden-Benutzer',
            'permissions' => [
                'see-app-bitwarden',                
            ]
        ],
]
];
