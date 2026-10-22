<?php

namespace Junaidnasir\Larainvite\Facades;

use Illuminate\Support\Facades\Facade;
use Junaidnasir\Larainvite\UserInvitation;

/**
 * @method static string invite(string $email, int $referral, \DateTimeInterface|string|null $expires = null, callable|null $beforeSave = null)
 * @method static \Junaidnasir\Larainvite\Models\LaraInviteModel|null get(string $code)
 * @method static string status(string $code)
 * @method static bool isValid(string $code)
 * @method static bool isExpired(string $code)
 * @method static bool isPending(string $code)
 * @method static bool isAllowed(string $code, string $email)
 * @method static bool consume(string $code)
 * @method static bool cancel(string $code)
 * @method static bool reminder(string $code)
 * @method static \Junaidnasir\Larainvite\UserInvitation validateEmail(string $email)
 *
 * @see UserInvitation
 */
class Invite extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'invite';
    }
}
