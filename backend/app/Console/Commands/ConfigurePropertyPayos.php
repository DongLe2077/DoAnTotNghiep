<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConfigurePropertyPayos extends Command
{
    protected $signature = 'rental:configure-property-payos {property} {--file= : Private JSON file with client_id, api_key, checksum_key, enabled}';

    protected $description = 'Store encrypted payment credentials for exactly one property';

    public function handle(): int
    {
        $id = filter_var($this->argument('property'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $id || ! DB::table('rental_properties')->where('id', $id)->whereNotNull('owner_user_id')->exists()) {
            $this->error('Unknown property owner.');

            return self::FAILURE;
        }
        $file = $this->option('file');
        if (! is_string($file) || ! is_file($file) || ! is_readable($file) || filesize($file) > 16384) {
            $this->error('A readable private JSON file is required.');

            return self::FAILURE;
        }
        $data = json_decode(file_get_contents($file), true);
        $validator = Validator::make(is_array($data) ? $data : [], ['client_id' => 'required|string|max:200', 'api_key' => 'required|string|max:200', 'checksum_key' => 'required|string|max:200', 'enabled' => 'required|boolean']);
        if ($validator->fails()) {
            $this->error('File must contain client_id, api_key, checksum_key and enabled.');

            return self::FAILURE;
        }
        $values = $validator->validated();
        $enabled = (bool) $values['enabled'];
        unset($values['enabled']);
        foreach ($values as $value) {
            if (trim($value) !== $value || $value === '') {
                $this->error('Credentials contain surrounding whitespace.');

                return self::FAILURE;
            }
        }
        DB::table('property_payment_settings')->updateOrInsert(['property_id' => $id], ['credentials' => Crypt::encryptString(json_encode($values, JSON_THROW_ON_ERROR)), 'enabled' => $enabled, 'updated_at' => now()]);
        $this->info('Encrypted settings saved. Credentials are not displayed. Verify the property-specific webhook before collecting payments.');

        return self::SUCCESS;
    }
}
