<?php

namespace Junaidnasir\Larainvite;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait InviteTrait
{
    /**
     * return all invitation as laravel collection
     *
     * @return HasMany<Model, $this>
     */
    public function invitations()
    {
        return $this->hasMany(config('larainvite.InvitationModel'));
    }

    /**
     * return successful initation by a user
     *
     * @return HasMany<Model, $this>
     */
    public function invitationSuccess()
    {
        return $this->invitations()->where('status', 'successful');
    }

    /**
     * return pending invitations by a user
     *
     * @return HasMany<Model, $this>
     */
    public function invitationPending()
    {
        return $this->invitations()->where('status', 'pending');
    }
}
