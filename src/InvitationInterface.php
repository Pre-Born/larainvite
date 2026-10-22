<?php

namespace Junaidnasir\Larainvite;

use Junaidnasir\Larainvite\Models\LaraInviteModel;

interface InvitationInterface
{
    /**
     * Create new invitation
     *
     * @param  string  $email  Email to invite
     * @param  int  $referral  Referral
     * @param  \DateTimeInterface|string  $expires  Expiration Date Time
     * @return string Referral code
     */
    public function invite($email, $referral, $expires);

    /**
     * Set referral code and LaraInviteModel instance
     *
     * @param  string  $code  referral Code
     */
    public function setCode($code);

    /**
     * Returns Invitation record
     *
     * @return LaraInviteModel|null
     */
    public function get();

    /**
     * Returns invitation status
     *
     * @return string pending | successful | expired | canceled
     */
    public function status();

    /**
     * Set invitation as successful
     *
     * @return bool true on success | false on error
     */
    public function consume();

    /**
     * Cancel an invitation
     *
     * @return bool true on success | false on error
     */
    public function cancel();

    /**
     * check if a code exist
     *
     * @return bool true if code found | false if not
     */
    public function isExisting();

    /**
     * check if invitation is valid
     *
     * @return bool
     */
    public function isValid();

    /**
     * check if invitation has expired
     *
     * @return bool
     */
    public function isExpired();

    /**
     * check if invitation status is pending
     *
     * @return bool
     */
    public function isPending();

    /**
     * check if given token is valid and given email is allowed
     *
     * @return bool
     */
    public function isAllowed($email);
}
