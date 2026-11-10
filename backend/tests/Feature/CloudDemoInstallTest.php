<?php

namespace Tests\Feature;

use Database\Seeders\CloudDemoSeeder;
use RuntimeException;
use Tests\TestCase;

class CloudDemoInstallTest extends TestCase
{
    public function test_installation_is_disabled_by_default(): void
    {
        config(['deployment.cloud_demo_setup' => false]);
        $this->artisan('rental:install-cloud-demo')->assertFailed();
    }

    public function test_installation_cannot_use_the_local_or_sqlite_database(): void
    {
        config(['deployment.cloud_demo_setup' => true]);
        $this->artisan('rental:install-cloud-demo', ['--confirm' => 'localhost/rental_management'])->assertFailed();
    }

    public function test_seeder_cannot_bypass_the_guarded_installation(): void
    {
        config(['deployment.cloud_demo_setup' => true]);
        $this->expectException(RuntimeException::class);
        app(CloudDemoSeeder::class)->run();
    }
}
