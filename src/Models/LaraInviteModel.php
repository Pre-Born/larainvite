<?php

namespace Junaidnasir\Larainvite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $email
 * @property int $user_id
 * @property string $status pending | successful | canceled | expired
 * @property \DateTimeInterface|string|null $valid_till
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LaraInviteModel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_invitations';

    /**
     * Referral User
     */
    public function user()
    {
        return $this->belongsTo(config('larainvite.UserModel'));
    }
}
