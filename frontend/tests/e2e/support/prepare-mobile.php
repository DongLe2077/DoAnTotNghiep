<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$frontend = dirname(__DIR__, 3);
$backend = dirname($frontend).DIRECTORY_SEPARATOR.'backend';
$root = $frontend.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.'mobile-https';
$database = $root.DIRECTORY_SEPARATOR.'owner-demo.sqlite';
$runId = getenv('E2E_RUN_ID');
$storage = $root.DIRECTORY_SEPARATOR.'runs'.DIRECTORY_SEPARATOR.$runId.DIRECTORY_SEPARATOR.'storage';
if (getenv('E2E_MOBILE') !== '1' || getenv('E2E_PRESENTATION') !== '1' || getenv('E2E_DEMO') !== '1'
    || getenv('APP_ENV') !== 'e2e' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $database
    || getenv('E2E_ISOLATED_PATH') !== $root || ! is_file($database)
    || ! preg_match('/^[a-f0-9]{16}$/D', $runId ?: '') || getenv('LARAVEL_STORAGE_PATH') !== $storage) {
    throw new RuntimeException('Mobile setup only accepts its exact synthetic SQLite/storage paths.');
}
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->useStoragePath($storage);
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database
    || ! Schema::hasTable('presentation_seed') || DB::table('presentation_seed')->where('id', 1)->doesntExist()) {
    throw new RuntimeException('Mobile fixtures were not prepared.');
}
$access = json_decode(file_get_contents($root.DIRECTORY_SEPARATOR.'access.json'), true, flags: JSON_THROW_ON_ERROR);
$expected = ['admin' => 'owner', 'user' => 'tenant', 'nhom_demo' => 'tenant', 'thanhvien_demo' => 'tenant', 'phong_demo' => 'tenant'];
if (($access['version'] ?? null) !== 1 || array_keys($access['accounts'] ?? []) !== array_keys($expected)) {
    throw new RuntimeException('Unexpected mobile account manifest.');
}
foreach ($access['accounts'] as $username => $account) {
    $simpleDemoPassword = ($access['simple_demo_passwords'] ?? false) === true
        && in_array($username, ['admin', 'user'], true) && ($account['password'] ?? null) === '123';
    if (($account['role'] ?? null) !== $expected[$username] || ! is_string($account['password'] ?? null)
        || (! $simpleDemoPassword && ! preg_match('/^[A-Za-z0-9_-]{18,80}$/D', $account['password']))) {
        throw new RuntimeException('Mobile credentials must be generated before starting the server.');
    }
}
if (! Schema::hasTable('mobile_demo_security')) {
    Schema::create('mobile_demo_security', function (Blueprint $table) {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('version');
        $table->dateTime('created_at');
    });
}
if (DB::table('mobile_demo_security')->where('id', 1)->doesntExist()) {
    DB::transaction(function () use ($access, $expected) {
        foreach (DB::table('users')->get() as $user) {
            $account = $access['accounts'][$user->username] ?? null;
            if ($account !== null && $user->role !== $expected[$user->username]) {
                throw new RuntimeException('A sample account has an unexpected role.');
            }
            // Replace every known source-fixture password, including accounts disabled below.
            DB::table('users')->where('id', $user->id)->update([
                'password_hash' => Hash::make($account['password'] ?? bin2hex(random_bytes(24))),
                'is_active' => $account !== null, 'remember_token' => null, 'updated_at' => now(),
            ]);
        }
        if (DB::table('users')->whereIn('username', array_keys($expected))->where('is_active', true)->count() !== count($expected)) {
            throw new RuntimeException('Required mobile demo accounts are missing.');
        }
        DB::table('mobile_demo_security')->insert(['id' => 1, 'version' => 1, 'created_at' => now()]);
    });
}

// Backup and actual restore-to-a-new-directory verification before exposing the runtime.
$backup = $root.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.date('Ymd-His').'-'.bin2hex(random_bytes(3));
foreach ([$backup, $backup.'/photos', $backup.'/restored', $backup.'/restored/photos'] as $directory) {
    if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('Cannot prepare the mobile backup directory.');
    }
}
DB::statement('VACUUM INTO ?', [$backup.'/database.sqlite']);
copy($backup.'/database.sqlite', $backup.'/restored/database.sqlite');
function mobileSnapshot(string $path): array
{
    $db = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA query_only = ON');
    if ($db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $db->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
        throw new RuntimeException('Mobile backup integrity or foreign keys failed.');
    }
    $tables = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (! preg_match('/^[a-z_]+$/D', $table)) {
            throw new RuntimeException('Unexpected table in the synthetic database.');
        }
        $rows = array_map(fn ($row) => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $db->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC));
        sort($rows, SORT_STRING);
        $tables[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }

    return ['tables' => $tables, 'balances' => $db->query('SELECT * FROM invoice_balances ORDER BY invoice_id')->fetchAll(PDO::FETCH_ASSOC)];
}
$original = mobileSnapshot($database);
if ($original !== mobileSnapshot($backup.'/database.sqlite') || $original !== mobileSnapshot($backup.'/restored/database.sqlite')) {
    throw new RuntimeException('Mobile restored records differ from the source.');
}
$photos = [];
foreach (DB::table('maintenance_photos')->get() as $photo) {
    $path = $photo->path;
    if (str_contains($path, '..') || ! preg_match('#^[A-Za-z0-9_/-]+\.[A-Za-z0-9]+$#D', $path)) {
        throw new RuntimeException('Unexpected private photo path.');
    }
    $bytes = Storage::disk('maintenance')->get($path);
    if (! hash_equals($photo->sha256, hash('sha256', $bytes))) {
        throw new RuntimeException('A mobile sample photo is missing or damaged.');
    }
    foreach (['photos', 'restored/photos'] as $folder) {
        $destination = $backup.'/'.$folder.'/'.$path;
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0777, true);
        }
        file_put_contents($destination, $bytes);
        if (! hash_equals($photo->sha256, hash_file('sha256', $destination))) {
            throw new RuntimeException('Restored mobile photo differs.');
        }
    }
    $photos[$path] = $photo->sha256;
}
copy($root.'/access.json', $backup.'/access.json');
$result = ['created_at' => now()->format('Y-m-d H:i:s'), 'path' => $backup, 'integrity' => 'ok',
    'all_rows_match' => true, 'invoice_balances_match' => true, 'photos' => $photos, 'synthetic_data_only' => true];
file_put_contents($backup.'/verification.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents($root.'/backup-latest.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['mobile_accounts_ready' => true, 'restored_backup_verified' => true, 'sample_photos' => count($photos)], JSON_THROW_ON_ERROR).PHP_EOL;
