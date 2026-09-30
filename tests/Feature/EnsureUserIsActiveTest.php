<?php

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    public function test_inactive_authenticated_user_is_denied_without_database_access(): void
    {
        $user = new User;
        $user->setAttribute('is_active', false);
        $request = Request::create('/api/me');
        $request->setUserResolver(fn () => $user);

        $response = app(\App\Http\Middleware\EnsureUserIsActive::class)
            ->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_active_authenticated_user_continues(): void
    {
        $user = new User;
        $user->setAttribute('is_active', true);
        $request = Request::create('/api/me');
        $request->setUserResolver(fn () => $user);

        $response = app(\App\Http\Middleware\EnsureUserIsActive::class)
            ->handle($request, fn () => response()->json(['ok' => true], 202));

        $this->assertSame(202, $response->getStatusCode());
    }
}
