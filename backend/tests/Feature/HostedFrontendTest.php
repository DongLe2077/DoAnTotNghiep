<?php

namespace Tests\Feature;

use Tests\TestCase;

class HostedFrontendTest extends TestCase
{
    private string $publicDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicDirectory = sys_get_temp_dir().'/rentbase-public-'.bin2hex(random_bytes(8));
        mkdir($this->publicDirectory, 0700, true);
        $this->app->usePublicPath($this->publicDirectory);
    }

    protected function tearDown(): void
    {
        @unlink($this->publicDirectory.'/index.html');
        @rmdir($this->publicDirectory);
        parent::tearDown();
    }

    public function test_known_frontend_paths_use_the_same_public_shell(): void
    {
        config(['frontend.enabled' => true]);
        file_put_contents($this->publicDirectory.'/index.html', '<html><div id="app">rentbase-shell</div></html>');
        foreach (['/', '/login', '/owner/rooms', '/tenant/invoices/42', '/account', '/notifications'] as $path) {
            $this->get($path)->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_missing_build_reports_unavailability_instead_of_a_broken_login(): void
    {
        config(['frontend.enabled' => true]);
        $this->get('/login')->assertStatus(503);
    }

    public function test_frontend_serving_is_opt_in_and_does_not_capture_private_or_unknown_paths(): void
    {
        config(['frontend.enabled' => false]);
        $this->get('/login')->assertNotFound();
        config(['frontend.enabled' => true]);
        foreach (['/.env', '/vendor/autoload.php', '/storage/app/private/file.png', '/not-a-page'] as $path) {
            $this->assertContains($this->get($path)->status(), [403, 404]);
        }
    }

    public function test_api_errors_remain_json_even_with_a_browser_accept_header(): void
    {
        config(['frontend.enabled' => true]);
        foreach (['/api', '/api/not-a-route', '/api/auth/me'] as $path) {
            $response = $this->get($path, ['Accept' => 'text/html']);
            $this->assertContains($response->status(), [401, 404]);
            $response->assertHeader('Content-Type', 'application/json');
        }
    }
}
