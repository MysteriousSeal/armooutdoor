<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the archives are kept
    |--------------------------------------------------------------------------
    |
    | Outside public/: an archive holds every order and every customer's
    | address. Configurable so the tests can write somewhere of their own —
    | they delete what they find, and must never find a real backup.
    |
    */

    'directory' => env('BACKUP_DIRECTORY', storage_path('app/private/backups')),

    /*
    |--------------------------------------------------------------------------
    | What a backup holds
    |--------------------------------------------------------------------------
    |
    | Paths relative to the project root, each mapped to the folder it takes
    | inside the archive. Only what the code cannot recreate: the code itself
    | lives in git, and copying it would double every archive for nothing.
    |
    */

    'sources' => [
        'public/images' => 'images',
        'storage/app/private' => 'private',
    ],

];
