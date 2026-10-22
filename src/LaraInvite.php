<?php

namespace Junaidnasir\Larainvite;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Junaidnasir\Larainvite\Events\InvitationCanceled;
use Junaidnasir\Larainvite\Events\InvitationConsumed;
use Junaidnasir\Larainvite\Events\InvitationExpired;
use Junaidnasir\Larainvite\Events\Invited;
use Junaidnasir\Larainvite\Exceptions\InvalidTokenException;

/**
 *   Laravel Invitation class.
 */
class LaraInvite implements InvitationInterface
{
    /**
     * Email address to invite.
     * @var string
     */
    private $email;

    /**
     * Referral Code for invitation.
     * @var string
     */
    private $code = null;

    /**
     * Status of code existing in DB.
     * @var bool
     */
    private $exist = false;

    /**
     * integer ID of referral.
     * @var [type]
     */
    private $referral;

    /**
     * DateTime of referral code expiration.
     * @var DateTime
     */
    private $expires;

    /**
     * Invitation Model.
     * @var Junaidnasir\Larainvite\Models\LaraInviteModel
     */
    private $instance = null;

    /**
     * {@inheritdoc}
     */
    public function invite($email, $referral, $expires, $beforeSave = null)
    {
        $this->readyPayload($email, $referral, $expires)
             ->createInvite($beforeSave);

        $this->dispatchAfterCommit(Invited::class);

        return $this->code;
    }

    /**
     * {@inheritdoc}
     */
    public function setCode($code)
    {
        $this->code = $code;
        // never let a previous lookup leak into this one (singleton in long-running processes)
        $this->instance = null;
        $this->exist = false;
        try {
            $this->getModelInstance(false);
        } catch (InvalidTokenException $exception) {
            // handle invalid codes
            $this->exist = false;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        return $this->instance;
    }

    /**
     * {@inheritdoc}
     */
    public function status()
    {
        if ($this->isValid()) {
            return $this->instance->status;
        }

        return 'Invalid';
    }

    /**
     * {@inheritdoc}
     */
    public function consume()
    {
        if ($this->isValid() && $this->transitionFromPending('successful', true)) {
            $this->dispatchAfterCommit(InvitationConsumed::class);

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        if ($this->isValid() && $this->transitionFromPending('canceled', true)) {
            $this->dispatchAfterCommit(InvitationCanceled::class);

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isExisting()
    {
        return $this->exist;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return ! $this->isExpired() && $this->isPending();
    }

    /**
     * {@inheritdoc}
     */
    public function isExpired()
    {
        if (! $this->isExisting()) {
            return true;
        }
        if (! $this->validTillHasPassed()) {
            return false;
        }
        // only a pending invitation becomes 'expired'; accepted/canceled ones keep their status
        if ($this->instance->status === 'pending' && $this->transitionFromPending('expired', false)) {
            $this->dispatchAfterCommit(InvitationExpired::class);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        if (! $this->isExisting()) {
            return false;
        }

        return ($this->instance->status === 'pending') ? true : false;
    }

    /**
     * {@inheritdoc}
     */
    public function isAllowed($email)
    {
        return $this->isValid()
            && Str::lower(trim((string) $this->instance->email)) === Str::lower(trim((string) $email));
    }

    /**
     * Fire junaidnasir.larainvite.invited again for the invitation.
     * @return true
     */
    public function reminder()
    {
        Invited::dispatch($this->instance);

        return true;
    }

    /**
     * generate invitation code and call save.
     * @param null $beforeSave
     * @return self
     * @throws InvalidTokenException
     */
    private function createInvite($beforeSave = null)
    {
        $code = Str::random(40);

        return $this->save($code, $beforeSave);
    }

    /**
     * saves invitation in DB.
     * @param string $code referral code
     * @param null $beforeSave
     * @return self
     * @throws InvalidTokenException
     */
    private function save($code, $beforeSave = null)
    {
        $this->getModelInstance();
        $this->instance->email = $this->email;
        $this->instance->user_id = $this->referral;
        $this->instance->valid_till = $this->expires;
        $this->instance->code = $code;

        if ($beforeSave !== null) {
            if (is_callable($beforeSave)) {
                $beforeSave($this->instance);
            }
        }
        $this->instance->save();

        $this->code = $code;
        $this->exist = true;

        return $this;
    }

    /**
     * atomically move the invitation from 'pending' to $status.
     * returns true only for the one caller whose update actually changed the row.
     * @param  string $status       new status
     * @param  bool   $requireValid also require valid_till to be in the future
     * @return bool
     */
    private function transitionFromPending($status, $requireValid)
    {
        $query = $this->instance->newQuery()
            ->whereKey($this->instance->getKey())
            ->where('status', 'pending');

        if ($requireValid) {
            $query->where('valid_till', '>=', Carbon::now());
        }

        // the conditional update decides the single winner
        if ($query->toBase()->update(['status' => $status]) !== 1) {
            $fresh = $this->instance->fresh();
            if ($fresh !== null) {
                $this->instance = $fresh;
            }

            return false;
        }

        // persist through Eloquent as well, so model events, observers and save() overrides still run
        $this->instance->status = $status;
        $this->instance->save();

        return true;
    }

    /**
     * dispatch $event for the current invitation once the surrounding transaction commits.
     * runs immediately when no transaction is open, and never runs if the transaction rolls back.
     * @param  string $event event class
     * @return void
     */
    private function dispatchAfterCommit($event)
    {
        // capture the model now; the singleton may point at another invitation by commit time
        $invitation = $this->instance;

        $invitation->getConnection()->afterCommit(function () use ($event, $invitation) {
            $event::dispatch($invitation);
        });
    }

    /**
     * check if valid_till is in the past; a missing or unparseable value counts as expired.
     * @return bool
     */
    private function validTillHasPassed()
    {
        $validTill = $this->instance->valid_till;
        if ($validTill === null || $validTill === '') {
            return true;
        }

        try {
            return Carbon::parse($validTill)->getTimestamp() < Carbon::now()->getTimestamp();
        } catch (\Exception $exception) {
            return true;
        }
    }

    /**
     * set $this->instance to Junaidnasir\Larainvite\Models\LaraInviteModel instance.
     * @param bool $allowNew allow new model
     * @return self
     * @throws InvalidTokenException
     */
    private function getModelInstance($allowNew = true)
    {
        $model = config('larainvite.InvitationModel');
        if ($allowNew) {
            $this->instance = new $model;

            return $this;
        }
        try {
            $this->instance = (new $model)->where('code', $this->code)->firstOrFail();
            $this->exist = true;

            return $this;
        } catch (ModelNotFoundException $e) {
            throw new InvalidTokenException("Invalid Token {$this->code}", 401);
        }
    }

    /**
     * set input variables.
     * @param  string   $email    email to invite
     * @param  int  $referral referral id
     * @param  DateTime $expires  expiration of token
     * @return self
     */
    private function readyPayload($email, $referral, $expires)
    {
        $this->email = $email;
        $this->referral = $referral;
        $this->expires = $expires;

        return $this;
    }
}
