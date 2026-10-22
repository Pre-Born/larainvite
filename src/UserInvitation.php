<?php

namespace Junaidnasir\Larainvite;

use Carbon\Carbon;
use Exception;

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
     * @param  null  $expires
     * @param  null  $beforeSave
     * @return string
     *
     * @throws Exception
     */
    public function invite($email, $referral, $expires = null, $beforeSave = null)
    {
        $expires = $expires === null ? Carbon::now()->addHour(config('larainvite.expires')) : $expires;
        $this->validateEmail($email);

        return $this->interface->invite($email, $referral, $expires, $beforeSave);
    }

    /**
     * @return mixed
     */
    public function get($code)
    {
        return $this->interface->setCode($code)->get();
    }

    /**
     * @return mixed
     */
    public function status($code)
    {
        return $this->interface->setCode($code)->status();
    }

    /**
     * @return mixed
     */
    public function isValid($code)
    {
        return $this->interface->setCode($code)->isValid();
    }

    /**
     * @return mixed
     */
    public function isExpired($code)
    {
        return $this->interface->setCode($code)->isExpired();
    }

    /**
     * @return mixed
     */
    public function isPending($code)
    {
        return $this->interface->setCode($code)->isPending();
    }

    /**
     * @return mixed
     */
    public function isAllowed($code, $email)
    {
        return $this->interface->setCode($code)->isAllowed($email);
    }

    /**
     * @return mixed
     */
    public function consume($code)
    {
        return $this->interface->setCode($code)->consume();
    }

    /**
     * @return mixed
     */
    public function cancel($code)
    {
        return $this->interface->setCode($code)->cancel();
    }

    /**
     * @return mixed
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
