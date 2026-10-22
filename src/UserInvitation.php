<?php

namespace Junaidnasir\Larainvite;

use Carbon\Carbon;
use Exception;
use Junaidnasir\Larainvite\Models\LaraInviteModel;

/**
 * User Invitation class
 */
class UserInvitation
{
    /**
     * @var InvitationInterface
     */
    private $interface;

    /**
     * UserInvitation constructor.
     */
    public function __construct(InvitationInterface $interface)
    {
        $this->interface = $interface;
    }

    /**
     * @param  string  $email
     * @param  int  $referral
     * @param  \DateTimeInterface|string|null  $expires
     * @param  callable|null  $beforeSave
     * @return string
     *
     * @throws Exception
     */
    public function invite($email, $referral, $expires = null, $beforeSave = null)
    {
        $expires = $expires === null ? Carbon::now()->addHours(config('larainvite.expires')) : $expires;
        $this->validateEmail($email);

        // InvitationInterface::invite() does not declare $beforeSave; adding it would break custom implementations
        // @phpstan-ignore arguments.count
        return $this->interface->invite($email, $referral, $expires, $beforeSave);
    }

    /**
     * @return LaraInviteModel|null
     */
    public function get($code)
    {
        return $this->interface->setCode($code)->get();
    }

    /**
     * @return string
     */
    public function status($code)
    {
        return $this->interface->setCode($code)->status();
    }

    /**
     * @return bool
     */
    public function isValid($code)
    {
        return $this->interface->setCode($code)->isValid();
    }

    /**
     * @return bool
     */
    public function isExpired($code)
    {
        return $this->interface->setCode($code)->isExpired();
    }

    /**
     * @return bool
     */
    public function isPending($code)
    {
        return $this->interface->setCode($code)->isPending();
    }

    /**
     * @return bool
     */
    public function isAllowed($code, $email)
    {
        return $this->interface->setCode($code)->isAllowed($email);
    }

    /**
     * @return bool
     */
    public function consume($code)
    {
        return $this->interface->setCode($code)->consume();
    }

    /**
     * @return bool
     */
    public function cancel($code)
    {
        return $this->interface->setCode($code)->cancel();
    }

    /**
     * @return bool
     */
    public function reminder($code)
    {
        return $this->interface->setCode($code)->reminder();
    }

    /**
     * @return $this
     */
    public function validateEmail($email)
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid Email Address', 1);
        }

        return $this;
    }
}
