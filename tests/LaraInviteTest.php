<?php

namespace Junaidnasir\Larainvite\Tests;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Junaidnasir\Larainvite\Events\InvitationCanceled;
use Junaidnasir\Larainvite\Events\InvitationConsumed;
use Junaidnasir\Larainvite\Events\InvitationExpired;
use Junaidnasir\Larainvite\Events\Invited;
use Junaidnasir\Larainvite\Facades\Invite;
use Junaidnasir\Larainvite\LaraInvite;
use Junaidnasir\Larainvite\Models\LaraInviteModel;

class LaraInviteTest extends TestCase
{
    private function statusInDb($code)
    {
        return LaraInviteModel::where('code', $code)->value('status');
    }

    public function test_invite_creates_pending_invitation_and_fires_event()
    {
        Event::fake([Invited::class]);

        $code = Invite::invite('john@example.com', 1);

        $this->assertSame('john@example.com', Invite::get($code)->email);
        $this->assertSame('pending', Invite::status($code));
        $this->assertTrue(Invite::isValid($code));
        Event::assertDispatchedTimes(Invited::class, 1);
    }

    public function test_codes_are_long_random_and_unique()
    {
        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $codes[] = Invite::invite("user{$i}@example.com", 1);
        }

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $code);
        }
        $this->assertCount(50, array_unique($codes));
    }

    public function test_code_column_is_unique()
    {
        $code = Invite::invite('john@example.com', 1);

        $this->expectException(QueryException::class);

        $duplicate = new LaraInviteModel;
        $duplicate->code = $code;
        $duplicate->email = 'other@example.com';
        $duplicate->user_id = 1;
        $duplicate->valid_till = Carbon::now()->addDay();
        $duplicate->save();
    }

    public function test_consume_succeeds_only_once()
    {
        Event::fake([InvitationConsumed::class]);
        $code = Invite::invite('john@example.com', 1);

        $this->assertTrue(Invite::consume($code));
        $this->assertFalse(Invite::consume($code));

        $this->assertSame('successful', $this->statusInDb($code));
        Event::assertDispatchedTimes(InvitationConsumed::class, 1);
    }

    public function test_concurrent_consume_of_one_code_succeeds_only_once()
    {
        Event::fake([InvitationConsumed::class]);
        $code = Invite::invite('john@example.com', 1);

        // two requests that both loaded the invitation while it was still pending
        $first = (new LaraInvite)->setCode($code);
        $second = (new LaraInvite)->setCode($code);
        $this->assertTrue($first->isPending());
        $this->assertTrue($second->isPending());

        $this->assertSame([true, false], [$first->consume(), $second->consume()]);
        $this->assertSame('successful', $second->get()->status);
        Event::assertDispatchedTimes(InvitationConsumed::class, 1);
    }

    public function test_concurrent_cancel_succeeds_only_once()
    {
        Event::fake([InvitationCanceled::class]);
        $code = Invite::invite('john@example.com', 1);

        $first = (new LaraInvite)->setCode($code);
        $second = (new LaraInvite)->setCode($code);

        $this->assertSame([true, false], [$first->cancel(), $second->cancel()]);
        $this->assertSame('canceled', $this->statusInDb($code));
        Event::assertDispatchedTimes(InvitationCanceled::class, 1);
    }

    public function test_cancel_after_concurrent_consume_does_not_overwrite_it()
    {
        $code = Invite::invite('john@example.com', 1);
        $canceler = (new LaraInvite)->setCode($code);

        $this->assertTrue(Invite::consume($code));

        $this->assertFalse($canceler->cancel());
        $this->assertSame('successful', $this->statusInDb($code));
    }

    public function test_pending_invitation_past_expiry_is_marked_expired()
    {
        Event::fake([InvitationExpired::class]);
        $code = Invite::invite('john@example.com', 1, Carbon::now()->addHour());

        $this->travel(2)->hours();

        $this->assertTrue(Invite::isExpired($code));
        $this->assertFalse(Invite::isValid($code));
        $this->assertFalse(Invite::consume($code));
        $this->assertSame('expired', $this->statusInDb($code));
        Event::assertDispatchedTimes(InvitationExpired::class, 1);
    }

    public function test_consumed_invitation_keeps_status_after_expiry()
    {
        Event::fake([InvitationExpired::class]);
        $code = Invite::invite('john@example.com', 1, Carbon::now()->addHour());
        $this->assertTrue(Invite::consume($code));

        $this->travel(2)->hours();

        $this->assertTrue(Invite::isExpired($code));
        $this->assertFalse(Invite::isValid($code));
        $this->assertSame('successful', $this->statusInDb($code));
        Event::assertNotDispatched(InvitationExpired::class);
    }

    public function test_canceled_invitation_keeps_status_after_expiry()
    {
        Event::fake([InvitationExpired::class]);
        $code = Invite::invite('john@example.com', 1, Carbon::now()->addHour());
        $this->assertTrue(Invite::cancel($code));

        $this->travel(2)->hours();

        $this->assertTrue(Invite::isExpired($code));
        $this->assertSame('canceled', $this->statusInDb($code));
        Event::assertNotDispatched(InvitationExpired::class);
    }

    public function test_unknown_code_after_known_code_returns_null()
    {
        $code = Invite::invite('john@example.com', 1);

        $this->assertNotNull(Invite::get($code));
        $this->assertNull(Invite::get('does-not-exist'));
        $this->assertFalse(Invite::isValid('does-not-exist'));
        $this->assertFalse(Invite::isAllowed('does-not-exist', 'john@example.com'));
        $this->assertFalse(Invite::consume('does-not-exist'));
        $this->assertSame('Invalid', Invite::status('does-not-exist'));
        $this->assertSame('pending', $this->statusInDb($code));
    }

    public function test_before_save_closure_is_bound_to_model()
    {
        $code = Invite::invite('john@example.com', 1, null, function () {
            $this->email = 'bound@example.com';
        });

        $this->assertSame('bound@example.com', Invite::get($code)->email);
    }

    public function test_before_save_closure_still_receives_model_argument()
    {
        $code = Invite::invite('john@example.com', 1, null, function ($invitation) {
            $invitation->email = 'argument@example.com';
        });

        $this->assertSame('argument@example.com', Invite::get($code)->email);
    }

    public function test_before_save_accepts_static_closures_and_callables()
    {
        $static = Invite::invite('john@example.com', 1, null, static function ($invitation) {
            $invitation->email = 'static@example.com';
        });
        $this->assertSame('static@example.com', Invite::get($static)->email);

        $callable = Invite::invite('jane@example.com', 1, null, [$this, 'setCallableEmail']);
        $this->assertSame('callable@example.com', Invite::get($callable)->email);
    }

    public function setCallableEmail($invitation)
    {
        $invitation->email = 'callable@example.com';
    }

    public function test_is_allowed_compares_email_case_insensitively()
    {
        $code = Invite::invite('John.Doe@Example.com', 1);

        $this->assertTrue(Invite::isAllowed($code, 'john.doe@example.com'));
        $this->assertTrue(Invite::isAllowed($code, '  JOHN.DOE@EXAMPLE.COM '));
        $this->assertFalse(Invite::isAllowed($code, 'someone@example.com'));
    }
}
