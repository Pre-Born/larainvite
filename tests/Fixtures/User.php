<?php

namespace Junaidnasir\Larainvite\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Junaidnasir\Larainvite\InviteTrait;

/**
 * @property int $id
 */
class User extends Model
{
    use InviteTrait;

    protected $table = 'users';
}
