<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        $this->withCredentials();
    }

    public function test_owner_login_rotates_the_session_and_returns_only_public_account_fields(): void
    {
        $owner = $this->account();
        $this->api('GET', '/api/auth/csrf-cookie')->assertNoContent();
        $previousSession = session()->getId();

        $this->login($owner)->assertOk()->assertExactJson([
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'id' => $owner->id,
                'username' => $owner->username,
                'role' => 'owner',
                'tenant_id' => null,
                'is_active' => true,
            ],
        ])->assertHeader('Cache-Control', 'no-store, private');

        $this->assertNotSame($previousSession, session()->getId());
        $this->api('GET', '/api/auth/me')->assertOk()->assertJsonPath('data.id', $owner->id);
        $this->api('GET', '/api/owner/access')->assertOk()->assertJsonPath('data.role', 'owner');
        $this->api('GET', '/api/tenant/access')->assertForbidden();
    }

    public function test_tenant_cannot_promote_their_role_in_the_login_payload(): void
    {
        $tenant = $this->account(['role' => 'tenant', 'tenant_id' => 12]);

        $this->api('POST', '/api/auth/login', [
            'username' => $tenant->username,
            'password' => 'test-secret',
            'role' => 'owner',
            'is_active' => true,
            'tenant_id' => 99,
        ])->assertOk()->assertJsonPath('data.role', 'tenant')->assertJsonPath('data.tenant_id', 12);

        $this->api('GET', '/api/tenant/access')->assertOk();
        $this->api('GET', '/api/owner/access')->assertForbidden();
        $this->assertSame('tenant', $tenant->fresh()->role);
    }

    public function test_protected_routes_return_json_401_for_guests_even_without_an_accept_header(): void
    {
        $this->get('/api/auth/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $this->api('GET', '/api/owner/access')->assertUnauthorized();
        $this->api('GET', '/api/tenant/access')->assertUnauthorized();
        $this->api('POST', '/api/auth/logout')->assertUnauthorized();
    }

    public function test_wrong_password_and_unknown_username_have_the_same_generic_error(): void
    {
        $owner = $this->account();
        $wrongPassword = $this->api('POST', '/api/auth/login', [
            'username' => $owner->username,
            'password' => 'incorrect-secret',
        ])->assertUnauthorized();

        $unknownUser = $this->api('POST', '/api/auth/login', [
            'username' => 'unknown-user',
            'password' => 'test-secret',
        ])->assertUnauthorized();

        $this->assertSame($wrongPassword->json(), $unknownUser->json());
        $this->assertGuest('web');
    }

    public function test_disabled_accounts_cannot_log_in(): void
    {
        $owner = $this->account(['is_active' => false]);

        $this->login($owner)->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_accounts_with_an_unrecognized_role_cannot_log_in(): void
    {
        $user = $this->account(['role' => 'unexpected']);

        $this->login($user)->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_login_validates_username_and_password(): void
    {
        $this->api('POST', '/api/auth/login')->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);
        $this->api('POST', '/api/auth/login', ['username' => ['invalid'], 'password' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);
        $this->api('POST', '/api/auth/login', ['username' => str_repeat('x', 81), 'password' => str_repeat('x', 256)])
            ->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_sixth_failed_login_is_throttled_and_login_works_after_the_timeout(): void
    {
        $owner = $this->account();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->api('POST', '/api/auth/login', ['username' => $owner->username, 'password' => 'incorrect'])
                ->assertUnauthorized();
        }

        $this->login($owner)->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->login($owner)->assertOk();
    }

    public function test_ip_throttle_also_limits_attempts_with_different_usernames(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            // Invalid input avoids password work but still consumes the IP limit.
            $this->api('POST', '/api/auth/login', ['username' => 'user-'.$attempt])->assertUnprocessable();
        }

        $this->api('POST', '/api/auth/login', ['username' => 'another-user'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_successful_login_clears_the_failed_attempt_counter(): void
    {
        $owner = $this->account();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->api('POST', '/api/auth/login', ['username' => $owner->username, 'password' => 'incorrect'])
                ->assertUnauthorized();
        }

        $this->login($owner)->assertOk();
        $this->api('POST', '/api/auth/logout')->assertOk();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->api('POST', '/api/auth/login', ['username' => $owner->username, 'password' => 'incorrect'])
                ->assertUnauthorized();
        }
    }

    public function test_logout_rotates_the_session_and_removes_access(): void
    {
        $this->login($this->account())->assertOk();
        $previousSession = session()->getId();
        $previousToken = session()->token();

        $this->api('POST', '/api/auth/logout')->assertOk();

        $this->assertNotSame($previousSession, session()->getId());
        $this->assertNotSame($previousToken, session()->token());
        $this->api('GET', '/api/auth/me')->assertUnauthorized();
        $this->api('GET', '/api/owner/access')->assertUnauthorized();
    }

    public function test_an_existing_session_is_rejected_when_the_account_is_disabled(): void
    {
        $owner = $this->account();
        $this->login($owner)->assertOk();
        User::whereKey($owner->id)->update(['is_active' => false]);

        $this->api('GET', '/api/auth/me')->assertUnauthorized();
        $this->api('GET', '/api/owner/access')->assertUnauthorized();
    }

    public function test_changed_roles_are_checked_again_on_each_request(): void
    {
        $owner = $this->account();
        $this->login($owner)->assertOk();
        $this->api('GET', '/api/owner/access')->assertOk();
        User::whereKey($owner->id)->update(['role' => 'tenant', 'tenant_id' => 12]);

        $this->api('GET', '/api/owner/access')->assertForbidden();
        $this->api('GET', '/api/tenant/access')->assertOk();
        $this->api('GET', '/api/auth/me')->assertOk()->assertJsonPath('data.role', 'tenant');
    }

    public function test_password_changes_invalidate_an_established_session(): void
    {
        $owner = $this->account();
        $this->login($owner)->assertOk();
        $this->api('GET', '/api/auth/me')->assertOk();
        $owner->update(['password_hash' => 'new-secret']);

        $this->api('GET', '/api/auth/me')->assertUnauthorized();
    }

    public function test_login_requires_logout_before_switching_accounts(): void
    {
        $owner = $this->account();
        $this->login($owner)->assertOk();
        $this->login($owner)->assertStatus(409);
        $this->api('GET', '/api/auth/me')->assertOk()->assertJsonPath('data.id', $owner->id);
    }

    public function test_csrf_is_required_for_login_and_logout_with_real_cookie_values(): void
    {
        // Laravel skips CSRF in tests by default; enable it for this browser flow.
        $this->app->instance('env', 'local');
        $owner = $this->account();
        $csrf = $this->api('GET', '/api/auth/csrf-cookie')->assertNoContent();
        $csrf->assertCookie('XSRF-TOKEN');
        $token = $csrf->getCookie('XSRF-TOKEN', false)->getValue();

        $this->login($owner)->assertStatus(419);
        $login = $this->api('POST', '/api/auth/login', [
            'username' => $owner->username,
            'password' => 'test-secret',
        ], ['X-XSRF-TOKEN' => $token])->assertOk();

        $this->api('POST', '/api/auth/logout')->assertStatus(419);
        $this->api('POST', '/api/auth/logout', [], [
            'X-XSRF-TOKEN' => $login->getCookie('XSRF-TOKEN', false)->getValue(),
        ])->assertOk();
        $this->api('GET', '/api/auth/me')->assertUnauthorized();
    }

    public function test_cors_allows_credentials_only_for_configured_frontend_origins(): void
    {
        $this->options('/api/auth/login', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type,X-XSRF-TOKEN',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->get('/api/auth/csrf-cookie', ['Origin' => 'https://untrusted.example'])
            ->assertNoContent()->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_password_change_requires_current_password_logs_out_and_accepts_only_the_new_password(): void
    {
        $user = $this->account(['role' => 'tenant', 'tenant_id' => 1]);
        $this->login($user)->assertOk();
        $oldSession = session()->getId();
        $oldToken = session()->token();
        $before = $user->fresh()->getAttributes();
        $this->api('PUT', '/api/auth/password', ['current_password' => 'wrong', 'password' => 'new-password',
            'password_confirmation' => 'new-password'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->api('PUT', '/api/auth/password', ['current_password' => 'test-secret', 'password' => 'new-password',
            'password_confirmation' => 'new-password'])->assertOk()->assertJsonPath('requires_login', true)
            ->assertDontSee('password_hash')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->api('GET', '/api/auth/me')->assertUnauthorized();
        $this->login($user)->assertUnauthorized();
        $this->api('POST', '/api/auth/login', ['username' => $user->username, 'password' => 'new-password'])->assertOk();
        $after = $user->fresh()->getAttributes();
        foreach (['id', 'tenant_id', 'username', 'role', 'is_active'] as $field) {
            $this->assertSame($before[$field], $after[$field]);
        }
    }

    public function test_password_change_invalidates_a_second_existing_browser_session(): void
    {
        $user = $this->account();
        $this->login($user)->assertOk();
        $browserA = session()->all();
        $this->api('POST', '/api/auth/logout')->assertOk();
        $this->login($user)->assertOk();
        $this->api('PUT', '/api/auth/password', ['current_password' => 'test-secret', 'password' => 'another-secret',
            'password_confirmation' => 'another-secret'])->assertOk();
        $this->withSession($browserA);
        $this->api('GET', '/api/auth/me')->assertUnauthorized();
    }

    public function test_password_change_rejects_unsafe_values_and_account_field_changes(): void
    {
        $user = $this->account();
        $this->login($user)->assertOk();
        $base = ['current_password' => 'test-secret', 'password' => 'new-secret', 'password_confirmation' => 'new-secret'];
        foreach ([['password' => 'short', 'password_confirmation' => 'short'],
            ['password_confirmation' => 'mismatch'], ['password' => 'test-secret', 'password_confirmation' => 'test-secret'],
            ['password' => str_repeat('á', 37), 'password_confirmation' => str_repeat('á', 37)]] as $changes) {
            $this->api('PUT', '/api/auth/password', array_replace($base, $changes))->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->api('PUT', '/api/auth/password', $base + ['user_id' => 999, 'role' => 'tenant'])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'role']);
        $this->assertTrue(Hash::check('test-secret', $user->fresh()->password_hash));
        $this->api('PUT', '/api/auth/password', $base)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_password_changes_are_protected_by_authentication_and_csrf(): void
    {
        $base = ['current_password' => 'test-secret', 'password' => 'new-secret', 'password_confirmation' => 'new-secret'];
        $this->api('PUT', '/api/auth/password', $base)->assertUnauthorized();
        $user = $this->account();
        $this->login($user)->assertOk();
        $this->app->instance('env', 'local');
        $this->api('PUT', '/api/auth/password', $base)->assertStatus(419);
        $csrf = $this->api('GET', '/api/auth/csrf-cookie')->assertNoContent()->getCookie('XSRF-TOKEN', false)->getValue();
        $this->api('PUT', '/api/auth/password', $base, ['X-XSRF-TOKEN' => $csrf])->assertOk();
    }

    private function account(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['password_hash' => 'test-secret'], $attributes));
    }

    private function login(User $user): TestResponse
    {
        return $this->api('POST', '/api/auth/login', ['username' => $user->username, 'password' => 'test-secret']);
    }

    private function api(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        // Emulate separate HTTP requests, including a browser cookie jar.
        Auth::forgetGuards();
        $response = $this->json($method, $uri, $data, $headers);
        foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $name) {
            $cookie = $response->getCookie($name, false);
            if ($cookie !== null) {
                $this->withUnencryptedCookie($name, $cookie->getValue());
            }
        }

        return $response;
    }
}
