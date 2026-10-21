<?php

namespace Junaidnasir\Larainvite;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Junaidnasir\Larainvite\Events\InvitationCanceled;
use Junaidnasir\Larainvite\Events\InvitationConsumed;
use Junaidnasir\Larainvite\Events\InvitationExpired;
use Junaidnasir\Larainvite\Events\Invited;
use Junaidnasir\Larainvite\Exceptions\InvalidTokenException;
use ReflectionFunction;

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

        Invited::dispatch($this->instance);

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
            InvitationConsumed::dispatch($this->instance);

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
            InvitationCanceled::dispatch($this->instance);

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
        if (Carbon::parse($this->instance->valid_till)->getTimestamp() >= Carbon::now()->getTimestamp()) {
            return false;
        }
        // only a pending invitation becomes 'expired'; accepted/canceled ones keep their status
        if ($this->instance->status === 'pending' && $this->transitionFromPending('expired', false)) {
            InvitationExpired::dispatch($this->instance);
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
            if ($beforeSave instanceof Closure && ! (new ReflectionFunction($beforeSave))->isStatic()) {
                // $this inside the closure is the model; it is also passed as the first argument
                $beforeSave->call($this->instance, $this->instance);
            } elseif (is_callable($beforeSave)) {
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
            ->where('code', $this->instance->code)
            ->where('status', 'pending');

        if ($requireValid) {
            $query->where('valid_till', '>=', Carbon::now());
        }

        $changed = $query->update(['status' => $status]) === 1;

        $this->instance->refresh();

        return $changed;
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
