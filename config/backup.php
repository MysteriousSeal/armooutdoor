<?php

return [

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
