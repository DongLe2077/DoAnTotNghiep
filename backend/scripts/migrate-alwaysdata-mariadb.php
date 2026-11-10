<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require '/home/rentbase/current/vendor/autoload.php';
umask(0077);
const MIGRATION_ROOT = '/home/rentbase/shared/migration';
const TARGET_DB = 'rentbase_rental_management';
const TARGET_HOST = 'mysql-rentbase.alwaysdata.net';
const SOURCE_HOST = 'rentbase-db-badongle-6e53.a.aivencloud.com';

function settings(string $path): array { return Dotenv\Dotenv::parse(file_get_contents($path)); }
function connect(array $env, bool $source): PDO {
    if (($env['DB_HOST'] ?? '') !== ($source ? SOURCE_HOST : TARGET_HOST)
        || ($env['DB_DATABASE'] ?? '') !== ($source ? 'defaultdb' : TARGET_DB)) {
        throw new RuntimeException('Unexpected database target.');
    }
    $db = new PDO('mysql:host='.$env['DB_HOST'].';port='.$env['DB_PORT'].';dbname='.$env['DB_DATABASE'].';charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true, PDO::ATTR_TIMEOUT => 15,
        PDO::MYSQL_ATTR_SSL_CA => $source ? $env['MYSQL_ATTR_SSL_CA'] : '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ]);
    if (empty($db->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_ASSOC)['Value'])) throw new RuntimeException('TLS is required.');
    $db->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $db->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $db;
}
function jsonFile(string $name, array $value): void {
    file_put_contents(MIGRATION_ROOT.'/'.$name, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
function objects(PDO $db): array { return $db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM); }
function safeName(string $name): string {
    if (!preg_match('/^[a-z_][a-z0-9_]*$/D', $name)) throw new RuntimeException('Unsafe object name.');
    return '`'.$name.'`';
}
function ddl(PDO $db, string $name, string $type, string $database): string {
    $sql = $db->query('SHOW CREATE '.$type.' '.safeName($name))->fetch(PDO::FETCH_NUM)[1];
    $sql = str_replace('`'.$database.'`.', '', $sql);
    $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $sql);
    return str_replace('SQL SECURITY DEFINER', 'SQL SECURITY INVOKER', $sql);
}
function viewRows(PDO $db): array {
    return [
        'invoice_balances' => $db->query('SELECT * FROM invoice_balances ORDER BY invoice_id')->fetchAll(PDO::FETCH_ASSOC),
        'room_occupancy_summary' => $db->query('SELECT * FROM room_occupancy_summary ORDER BY room_id')->fetchAll(PDO::FETCH_ASSOC),
        'active_invoice_access' => $db->query('SELECT * FROM active_invoice_access ORDER BY invoice_id, user_id')->fetchAll(PDO::FETCH_ASSOC),
    ];
}
function loadSnapshot(string $kind = 'source'): array {
    if (!in_array($kind, ['source', 'target'], true)) throw new RuntimeException('Unexpected snapshot kind.');
    $path = MIGRATION_ROOT.'/'.($kind === 'source' ? 'snapshot' : 'target-snapshot').'.json.gz';
    $snapshot = json_decode(gzdecode(file_get_contents($path)), true, flags: JSON_THROW_ON_ERROR);
    $snapshot['_archive_sha256'] = hash_file('sha256', $path);
    return $snapshot;
}
function mariaDbView(string $sql): string {
    // Give status literals an explicit common collation before MariaDB merges the view.
    return preg_replace_callback("/(?<![a-zA-Z0-9_])(?:_utf8mb4)?'([a-z_]+)'(?!\\s+COLLATE\\b)/i", fn ($match) => "_utf8mb4'".$match[1]."' COLLATE utf8mb4_unicode_ci", $sql);
}
function verify(PDO $db, array $snapshot): array {
    $counts = [];
    foreach ($snapshot['rows'] as $table => $rows) {
        $actual = $db->query('SELECT * FROM '.safeName($table).' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if ($actual !== $rows) throw new RuntimeException('Data differs in '.$table.'.');
        $counts[$table] = count($actual);
    }
    if (viewRows($db) !== $snapshot['view_rows']) throw new RuntimeException('Calculated views differ from source.');
    $query = $db->prepare('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION');
    $query->execute([TARGET_DB]);
    $groups = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $key) $groups[$key['TABLE_NAME'].':'.$key['CONSTRAINT_NAME']][] = $key;
    foreach ($groups as $keys) {
        $on = $nonnull = [];
        foreach ($keys as $key) {
            $on[] = 'c.'.safeName($key['COLUMN_NAME']).'=p.'.safeName($key['REFERENCED_COLUMN_NAME']);
            $nonnull[] = 'c.'.safeName($key['COLUMN_NAME']).' IS NOT NULL';
        }
        $sql = 'SELECT COUNT(*) FROM '.safeName($keys[0]['TABLE_NAME']).' c LEFT JOIN '.safeName($keys[0]['REFERENCED_TABLE_NAME']).' p ON '.implode(' AND ', $on).' WHERE '.implode(' AND ', $nonnull).' AND p.'.safeName($keys[0]['REFERENCED_COLUMN_NAME']).' IS NULL';
        if ((int)$db->query($sql)->fetchColumn() !== 0) throw new RuntimeException('Orphaned rows at '.$keys[0]['CONSTRAINT_NAME']);
    }
    $photoCount = 0;
    foreach ($snapshot['rows']['maintenance_photos'] as $photo) {
        if (!preg_match('#^[0-9]+/[a-zA-Z0-9-]+\.[a-zA-Z0-9]+$#D', $photo['path'])) throw new RuntimeException('Unsafe photo path.');
        $path = '/home/rentbase/shared/storage/app/private/maintenance/'.$photo['path'];
        if (!is_file($path) || !hash_equals($photo['sha256'], hash_file('sha256', $path))) throw new RuntimeException('Photo is missing or changed.');
        $photoCount++;
    }
    return ['verified_at' => gmdate(DATE_ATOM), 'target_database' => TARGET_DB, 'version' => $db->query('SELECT VERSION()')->fetchColumn(),
        'row_counts' => $counts, 'all_rows_match' => true, 'all_three_views_match' => true, 'foreign_keys_verified' => count($groups), 'photos_verified' => $photoCount,
        'outstanding_amount' => $db->query('SELECT SUM(outstanding_amount) FROM invoice_balances')->fetchColumn(), 'snapshot_sha256' => $snapshot['_archive_sha256']];
}

$sourceEnv = $targetEnv = [];
try {
    $mode = $argv[1] ?? 'verify';
    $targetEnv = settings(MIGRATION_ROOT.'/.env.mariadb');
    if (in_array($mode, ['snapshot', 'snapshot-target'], true)) {
        $isSource = $mode === 'snapshot';
        $sourceEnv = $isSource ? settings('/home/rentbase/shared/.env') : $targetEnv;
        $sourceDatabase = $isSource ? 'defaultdb' : TARGET_DB;
        $snapshotPrefix = $isSource ? 'snapshot' : 'target-snapshot';
        $db = connect($sourceEnv, $isSource);
        $baseline = json_decode(file_get_contents('/home/rentbase/current/database/schema/mysql-cloud-baseline.json'), true, flags: JSON_THROW_ON_ERROR);
        $expected = array_merge(array_keys($baseline['tables']), ['migrations']);
        $actual = array_column(array_filter(objects($db), fn ($item) => $item[1] === 'BASE TABLE'), 0); sort($actual);
        $payosTables = ['invoice_payos_checkouts', 'invoice_payos_transfers'];
        if (! $isSource && array_diff($payosTables, $actual) === []) {
            $expected = array_merge($expected, $payosTables);
        }
        sort($expected);
        if ($actual !== $expected || count(array_filter(objects($db), fn ($item) => $item[1] === 'VIEW')) !== 3) throw new RuntimeException('Unexpected source objects.');
        if ($db->query('SHOW TRIGGERS')->fetchAll() !== []) throw new RuntimeException('Unexpected triggers need explicit support.');
        $db->exec('SET SESSION TRANSACTION READ ONLY');
        $db->beginTransaction();
        try {
            $snapshot = ['captured_at' => gmdate(DATE_ATOM), 'source_database' => $sourceDatabase, 'source_version' => $db->query('SELECT VERSION()')->fetchColumn(), 'tables' => [], 'views' => [], 'rows' => []];
            foreach ($expected as $name) {
                $snapshot['tables'][$name] = ddl($db, $name, 'TABLE', $sourceDatabase);
                $snapshot['rows'][$name] = $db->query('SELECT * FROM '.safeName($name).' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach (array_keys($baseline['views']) as $name) $snapshot['views'][$name] = ddl($db, $name, 'VIEW', $sourceDatabase);
            $snapshot['view_rows'] = viewRows($db);
            $db->commit();
        } catch (Throwable $error) { $db->rollBack(); throw $error; }
        file_put_contents(MIGRATION_ROOT.'/'.$snapshotPrefix.'.json.gz', gzencode(json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 9));
        jsonFile($snapshotPrefix.'-manifest.json', ['captured_at' => $snapshot['captured_at'], 'tables' => count($snapshot['tables']), 'views' => count($snapshot['views']), 'row_counts' => array_map('count', $snapshot['rows']), 'sha256' => hash_file('sha256', MIGRATION_ROOT.'/'.$snapshotPrefix.'.json.gz')]);
        echo 'Read-only source snapshot saved: '.count($snapshot['tables']).' tables, '.count($snapshot['views']).' views.'.PHP_EOL;
        exit(0);
    }
    $db = connect($targetEnv, false);
    $snapshot = loadSnapshot($argv[2] ?? 'source');
    if ($mode === 'reset-unpublished') {
        connect(settings('/home/rentbase/shared/.env'), true); // Never reset a published target.
        $activeConfig = require '/home/rentbase/current/bootstrap/cache/config.php';
        $activeConnection = $activeConfig['database']['connections'][$activeConfig['database']['default']];
        if (($activeConnection['host'] ?? '') !== SOURCE_HOST || ($activeConnection['database'] ?? '') !== 'defaultdb') throw new RuntimeException('Published destination cannot be reset.');
        $marker = json_decode(file_get_contents(MIGRATION_ROOT.'/target-owned.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($marker['database'] ?? '') !== TARGET_DB || ($marker['host'] ?? '') !== TARGET_HOST) throw new RuntimeException('Ownership marker differs.');
        $known = array_merge(array_keys($snapshot['tables']), array_keys($snapshot['views']));
        foreach (objects($db) as [$name, $type]) if (!in_array($name, $known, true)) throw new RuntimeException('Unknown target object; refusing reset.');
        // Preserve rows from the unpublished test before replacing it.
        $before = [];
        foreach (objects($db) as [$name, $type]) if ($type === 'BASE TABLE') $before[$name] = $db->query('SELECT * FROM '.safeName($name).' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents(MIGRATION_ROOT.'/unpublished-before-reset-'.gmdate('Ymd-His').'.json.gz', gzencode(json_encode($before, JSON_THROW_ON_ERROR), 9));
        foreach (objects($db) as [$name, $type]) if ($type === 'VIEW') $db->exec('DROP VIEW '.safeName($name));
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        try { foreach (objects($db) as [$name, $type]) if ($type === 'BASE TABLE') $db->exec('DROP TABLE '.safeName($name)); }
        finally { $db->exec('SET FOREIGN_KEY_CHECKS=1'); }
        echo 'Only the owned, unpublished destination was reset; its rows were backed up.'.PHP_EOL;
        exit(0);
    }
    if ($mode === 'import') {
        if (objects($db) !== []) throw new RuntimeException('Destination must be empty.');
        jsonFile('target-owned.json', ['host' => TARGET_HOST, 'database' => TARGET_DB, 'created_at' => gmdate(DATE_ATOM), 'phase' => 'importing']);
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($snapshot['tables'] as $name => $sql) { $db->exec($sql); echo 'Schema '.$name.PHP_EOL; }
            foreach ($snapshot['rows'] as $name => $rows) {
                $query = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND GENERATION_EXPRESSION IS NOT NULL AND GENERATION_EXPRESSION <> ?');
                $query->execute([TARGET_DB, $name, '']);
                $generated = $query->fetchAll(PDO::FETCH_COLUMN);
                foreach ($rows as $row) {
                    $values = array_diff_key($row, array_flip($generated));
                    $statement = $db->prepare('INSERT INTO '.safeName($name).' ('.implode(',', array_map('safeName', array_keys($values))).') VALUES ('.implode(',', array_fill(0, count($values), '?')).')');
                    $statement->execute(array_values($values));
                }
            }
            foreach ($snapshot['views'] as $name => $sql) $db->exec(mariaDbView($sql));
        } finally { $db->exec('SET FOREIGN_KEY_CHECKS=1'); }
        jsonFile('target-owned.json', ['host' => TARGET_HOST, 'database' => TARGET_DB, 'created_at' => gmdate(DATE_ATOM), 'phase' => 'imported']);
    } elseif ($mode === 'refresh-views') {
        connect(settings('/home/rentbase/shared/.env'), true);
        foreach ($snapshot['views'] as $name => $sql) $db->exec(preg_replace('/^CREATE /', 'CREATE OR REPLACE ', mariaDbView($sql)));
    } elseif ($mode !== 'verify') throw new RuntimeException('Unsupported mode.');
    $result = verify($db, $snapshot);
    jsonFile('verification.json', $result);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    $message = $error->getMessage();
    foreach ([$sourceEnv, $targetEnv] as $env) if (!empty($env['DB_PASSWORD'])) $message = str_replace($env['DB_PASSWORD'], '[redacted]', $message);
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}
