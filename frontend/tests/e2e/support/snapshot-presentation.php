<?php

// This helper deliberately does not bootstrap Laravel or read its normal .env.
$frontend = dirname(__DIR__, 3);
$root = $frontend.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.'presentation';
$checkpoints = $frontend.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.'presentation-checkpoints';
$destination = $argv[1] ?? '';
$mode = $argv[2] ?? 'snapshot';
$parent = realpath(dirname($destination));
if ($parent !== realpath($checkpoints) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-\d{2}-\d{3}Z-[a-f0-9]{6}$/D', basename($destination))
    || realpath($destination) !== $destination || ! in_array($mode, ['snapshot', 'verify', 'verify-active'], true)) {
    throw new RuntimeException('Only a new exact presentation checkpoint directory is allowed.');
}
function inspectDatabase(string $path): array
{
    if (! is_file($path) || is_link($path)) {
        throw new RuntimeException('Missing SQLite snapshot.');
    }
    $db = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA query_only = ON');
    if ($db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $db->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
        throw new RuntimeException('SQLite integrity or foreign keys failed.');
    }
    $hashes = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (! preg_match('/^[a-z_]+$/D', $table)) {
            throw new RuntimeException('Unexpected fixture table.');
        }
        $rows = $db->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
        $canonical = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $rows);
        sort($canonical, SORT_STRING);
        $hashes[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $canonical))];
    }
    $scenario = $db->query('SELECT manifest FROM presentation_seed WHERE id = 1')->fetchColumn();
    if (! is_string($scenario)) {
        throw new RuntimeException('Presentation seed is missing.');
    }

    return ['tables' => $hashes, 'scenario' => json_decode($scenario, true, flags: JSON_THROW_ON_ERROR),
        'photos' => $db->query('SELECT path, sha256 FROM maintenance_photos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        'balances' => $db->query('SELECT * FROM invoice_balances ORDER BY invoice_id')->fetchAll(PDO::FETCH_ASSOC)];
}
$source = $root.DIRECTORY_SEPARATOR.'owner-demo.sqlite';
$snapshot = $destination.DIRECTORY_SEPARATOR.'database.sqlite';
if ($mode === 'snapshot') {
    inspectDatabase($source);
    if (file_exists($snapshot)) {
        throw new RuntimeException('Refusing to overwrite a snapshot.');
    }
    $db = new PDO('sqlite:'.$source, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->prepare('VACUUM INTO ?')->execute([$snapshot]);
    if (inspectDatabase($source) !== inspectDatabase($snapshot)) {
        throw new RuntimeException('Source and snapshot data differ.');
    }
    echo json_encode(['snapshot_ready' => true], JSON_THROW_ON_ERROR);
} else {
    $before = inspectDatabase($snapshot);
    $after = inspectDatabase($mode === 'verify-active' ? $source : $destination.DIRECTORY_SEPARATOR.'restored'.DIRECTORY_SEPARATOR.'database.sqlite');
    if ($before !== $after) {
        throw new RuntimeException('Restored records or balances differ.');
    }
    foreach ($after['photos'] as $photo) {
        $path = $photo['path'];
        if (str_contains($path, '..') || ! preg_match('#^[A-Za-z0-9_/-]+\.[A-Za-z0-9]+$#D', $path)) {
            throw new RuntimeException('Unexpected stored photo path.');
        }
        if ($mode === 'verify-active') {
            $runtime = json_decode(file_get_contents($root.DIRECTORY_SEPARATOR.'demo-runtime.json'), true, flags: JSON_THROW_ON_ERROR);
            if (! preg_match('/^[a-f0-9]{16}$/D', $runtime['runId'] ?? '')) {
                throw new RuntimeException('Invalid active storage identity.');
            }
            $photoRoot = $root.DIRECTORY_SEPARATOR.'runs'.DIRECTORY_SEPARATOR.$runtime['runId'].DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'maintenance';
        } else {
            $photoRoot = $destination.DIRECTORY_SEPARATOR.'restored'.DIRECTORY_SEPARATOR.'photos';
        }
        $restored = $photoRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (! is_file($restored) || ! hash_equals($photo['sha256'], hash_file('sha256', $restored))) {
            throw new RuntimeException('A restored photo is missing or damaged.');
        }
    }
    echo json_encode(['integrity' => 'ok', 'foreign_keys' => 'ok', 'all_table_rows_match' => true, 'invoice_balances_match' => true,
        'table_count' => count($after['tables']), 'photo_count' => count($after['photos']), 'scenario_version' => $after['scenario']['version']], JSON_THROW_ON_ERROR);
}
