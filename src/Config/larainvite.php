<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Invitation Expiration Default
    |--------------------------------------------------------------------------
    |
    | Default Expiry time in Hours from current time.
    | i.e now() + expires (hours)
    |
    */
    'expires' => 48,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    // App\Models\User lives in the host application, not in this package
    'UserModel' => config('auth.providers.users.model', User::class), // @phpstan-ignore class.notFound

    /*
    |--------------------------------------------------------------------------
    | Invitation Model
    |--------------------------------------------------------------------------
    */
    'InvitationModel' => 'Junaidnasir\Larainvite\Models\LaraInviteModel',
];
