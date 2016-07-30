<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default source repository type
    |--------------------------------------------------------------------------
    |
    | The default source repository type you want to pull your updates from.
    |
    */

    'default' => env('SELF_UPDATE_SOURCE', 'github'),

    /*
    |--------------------------------------------------------------------------
    | Repository types
    |--------------------------------------------------------------------------
    |
    | A repository can be of different types, which can be specified here.
    | Current options:
    | - github
    |
    */

    'repository_types' => [
        'github' => [
            'type' => 'github',
            'repository_owner' => '',
            'repository_name' => '',
            'repository_url' => '',
            'download_path' => env('SELF_UPDATE_DOWNLOAD_PATH', storage_path('self-update/github/')),
        ],
    ],

];
