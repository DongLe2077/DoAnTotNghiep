<?php

// Read schema only from the development database; no business rows are exported.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
$source = $db->getDatabaseName();
if ($db->getDriverName() !== 'mysql' || $source !== 'rental_management') {
    throw new RuntimeException('Only the original rental_management MySQL schema can be exported.');
}
$db->statement('SET SESSION TRANSACTION READ ONLY');
$objects = $db->select('SHOW FULL TABLES');
$tables = $views = [];
$sanitize = static function (string $sql) use ($source): string {
    $sql = str_replace('`'.$source.'`.', '', $sql);
    $sql = preg_replace('/\sAUTO_INCREMENT=\d+/', '', $sql);
    $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $sql);

    return str_replace('SQL SECURITY DEFINER', 'SQL SECURITY INVOKER', $sql);
};
foreach ($objects as $object) {
    [$name, $type] = array_values((array) $object);
    if ($name === 'migrations') {
        continue;
    }
    if (! preg_match('/^[a-z_]+$/D', $name)) {
        throw new RuntimeException('Unexpected schema object name.');
    }
    $row = array_values((array) $db->selectOne('SHOW CREATE '.($type === 'VIEW' ? 'VIEW' : 'TABLE').' `'.$name.'`'));
    if ($type === 'VIEW') {
        $views[$name] = $sanitize($row[1]);
    } else {
        $tables[$name] = $sanitize($row[1]);
    }
}
ksort($tables);
ksort($views);
if (array_keys($tables) !== array_keys(config('rental.models')) || count($views) !== 3) {
    throw new RuntimeException('The current schema is not the expected complete rental application.');
}
if ($db->select('SHOW TRIGGERS') !== [] || $db->select('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ?', [$source]) !== []) {
    throw new RuntimeException('Unexpected triggers/routines require explicit export support.');
}
$migrations = array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(dirname(__DIR__).'/database/migrations/*.php'));
sort($migrations);
$data = ['format' => 1, 'source_version' => $db->selectOne('SELECT VERSION() AS version')->version,
    'tables' => $tables, 'views' => $views, 'migrations' => $migrations];
$target = dirname(__DIR__).'/database/schema/mysql-cloud-baseline.json';
file_put_contents($target, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
echo json_encode(['schema_only' => true, 'tables' => count($tables), 'views' => count($views), 'migrations' => count($migrations)]).PHP_EOL;
