<?php

namespace App\Console\Commands;

use Database\Seeders\CloudDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class InstallCloudDemo extends Command
{
    protected $signature = 'rental:install-cloud-demo {--confirm= : Exact MySQL hostname/database}';

    protected $description = 'Initialize an explicitly selected empty Aiven database with the complete schema and synthetic examples';

    public function handle(): int
    {
        try {
            $connection = DB::connection();
            $host = $connection->getConfig('host');
            $database = $connection->getDatabaseName();
            if (! config('deployment.cloud_demo_setup') || $connection->getDriverName() !== 'mysql'
                || ! is_string($host) || ! str_ends_with($host, '.aivencloud.com') || $database === 'rental_management'
                || $this->option('confirm') !== $host.'/'.$database) {
                throw new RuntimeException('Cloud demo installation is disabled or the explicit target does not match.');
            }
            $baseline = json_decode(file_get_contents(database_path('schema/mysql-cloud-baseline.json')), true, flags: JSON_THROW_ON_ERROR);
            $signature = hash('sha256', json_encode($baseline, JSON_THROW_ON_ERROR));
            $markerPath = storage_path('app/private/cloud-demo-install.json');
            $marker = is_file($markerPath) ? json_decode(file_get_contents($markerPath), true, flags: JSON_THROW_ON_ERROR) : null;
            $identity = ['host' => $host, 'database' => $database, 'schema_sha256' => $signature];
            if ($marker !== null && array_intersect_key($marker, $identity) !== $identity) {
                throw new RuntimeException('The existing installation marker belongs to another target/schema.');
            }
            if (($marker['complete'] ?? false) === true) {
                $this->info('The cloud demo is already installed. Existing edits were preserved.');

                return self::SUCCESS;
            }
            $objects = array_map(fn ($row) => array_values((array) $row), DB::select('SHOW FULL TABLES'));
            if ($objects !== [] && $marker === null) {
                throw new RuntimeException('Refusing to initialize a database that already contains objects.');
            }
            $allowed = array_merge(array_keys($baseline['tables']), array_keys($baseline['views']), ['migrations']);
            foreach ($objects as [$name, $type]) {
                if (! in_array($name, $allowed, true) || ($type === 'BASE TABLE' && $name !== 'migrations' && DB::table($name)->exists())) {
                    throw new RuntimeException('Refusing to reseed existing business data or unknown schema objects.');
                }
            }
            if (! is_dir(dirname($markerPath))) {
                mkdir(dirname($markerPath), 0700, true);
            }
            file_put_contents($markerPath, json_encode($identity + ['complete' => false], JSON_THROW_ON_ERROR));
            chmod($markerPath, 0600);
            $existing = array_column($objects, 0);
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($baseline['tables'] as $name => $sql) {
                    if (! in_array($name, $existing, true)) {
                        DB::unprepared($sql);
                    }
                }
                foreach ($baseline['views'] as $name => $sql) {
                    if (! in_array($name, $existing, true)) {
                        DB::unprepared($sql);
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            if (! Schema::hasTable('migrations')) {
                Schema::create('migrations', function (Blueprint $table): void {
                    $table->increments('id');
                    $table->string('migration');
                    $table->integer('batch');
                });
            }
            DB::transaction(function () use ($baseline): void {
                foreach ($baseline['migrations'] as $migration) {
                    if (! DB::table('migrations')->where('migration', $migration)->exists()) {
                        DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
                    }
                }
                app()->instance('cloud-demo.install-authorized', true);
                try {
                    app(CloudDemoSeeder::class)->run();
                } finally {
                    app()->forgetInstance('cloud-demo.install-authorized');
                }
            });
            file_put_contents($markerPath, json_encode($identity + ['complete' => true, 'installed_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR));
            $this->info('Installed 28 application tables, 3 views and synthetic demo workflows. No source data was copied.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Installation stopped: '.($exception instanceof RuntimeException && ! $exception instanceof \PDOException ? $exception->getMessage() : get_class($exception)));

            return self::FAILURE;
        }
    }
}
