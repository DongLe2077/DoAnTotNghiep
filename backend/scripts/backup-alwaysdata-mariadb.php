<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
$root = '/home/rentbase';
$migration = $root.'/shared/migration';
try {
    $lock = fopen($migration.'/backup.lock', 'c');
    if ($lock === false) throw new RuntimeException('Could not open backup lock.');
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        echo 'Another rental backup is already running.'.PHP_EOL;
        exit(0);
    }
    if (!is_file($migration.'/cutover.json')) throw new RuntimeException('Migration has not been activated.');
    passthru('/usr/bin/php '.escapeshellarg($migration.'/migrate.php').' snapshot-target', $exitCode);
    if ($exitCode !== 0) throw new RuntimeException('Native MariaDB snapshot failed.');
    $snapshotPath = $migration.'/target-snapshot.json.gz';
    $snapshot = json_decode(gzdecode(file_get_contents($snapshotPath)), true, flags: JSON_THROW_ON_ERROR);
    if ($snapshot['source_database'] !== 'rentbase_rental_management' || !str_contains($snapshot['source_version'], 'MariaDB')) throw new RuntimeException('Backup source differs.');
    $id = 'mariadb-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
    $base = $root.'/shared/backups';
    $directory = $base.'/'.$id;
    if (file_exists($directory) || !mkdir($directory, 0700)) throw new RuntimeException('Could not create backup directory.');
    copy($snapshotPath, $directory.'/database.json.gz');
    copy($root.'/shared/.env', $directory.'/.env');
    copy('/etc/ssl/certs/ca-certificates.crt', $directory.'/ca-certificates.crt');
    $photos = [];
    foreach ($snapshot['rows']['maintenance_photos'] as $photo) {
        if (!preg_match('#^[0-9]+/[a-zA-Z0-9-]+\.[a-zA-Z0-9]+$#D', $photo['path'])) throw new RuntimeException('Unsafe photo path.');
        $source = $root.'/shared/storage/app/private/maintenance/'.$photo['path'];
        if (!is_file($source) || !hash_equals($photo['sha256'], hash_file('sha256', $source))) throw new RuntimeException('Photo integrity mismatch.');
        $target = $directory.'/photos/'.$photo['path'];
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
        if (!copy($source, $target) || !hash_equals($photo['sha256'], hash_file('sha256', $target))) throw new RuntimeException('Photo copy failed.');
        $photos[$photo['path']] = $photo['sha256'];
    }
    $manifest = ['format' => 'rentbase-mariadb-backup-v1', 'created_at' => gmdate(DATE_ATOM), 'database' => $snapshot['source_database'],
        'version' => $snapshot['source_version'], 'tables' => count($snapshot['tables']), 'views' => count($snapshot['views']),
        'row_counts' => array_map('count', $snapshot['rows']), 'photos' => $photos, 'database_sha256' => hash_file('sha256', $snapshotPath)];
    file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $archive = $base.'/'.$id.'.tar.gz';
    passthru('tar -czf '.escapeshellarg($archive).' -C '.escapeshellarg($base).' '.escapeshellarg($id), $exitCode);
    if ($exitCode !== 0) throw new RuntimeException('Backup archive failed.');
    chmod($archive, 0600);
    $result = ['created_at' => $manifest['created_at'], 'directory' => $directory, 'archive' => $archive,
        'sha256' => hash_file('sha256', $archive), 'tables' => $manifest['tables'], 'views' => $manifest['views'], 'photos' => count($photos)];
    file_put_contents($root.'/deploy/mariadb-backup-latest.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    // Remove only older directories created by this script and carrying its marker.
    $owned = [];
    foreach (glob($base.'/mariadb-*', GLOB_ONLYDIR) as $candidate) {
        if (!preg_match('/^mariadb-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}$/D', basename($candidate)) || is_link($candidate)) continue;
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, realpath($base).'/')) continue;
        $old = json_decode(@file_get_contents($resolved.'/manifest.json') ?: '{}', true);
        if (($old['format'] ?? null) === 'rentbase-mariadb-backup-v1') $owned[] = $resolved;
    }
    rsort($owned, SORT_STRING);
    foreach (array_slice($owned, 7) as $oldDirectory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($oldDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if (!str_starts_with(realpath(dirname($path)).'/', $oldDirectory.'/')) throw new RuntimeException('Unsafe retention path.');
            if ($entry->isLink() || $entry->isFile()) unlink($path); else rmdir($path);
        }
        rmdir($oldDirectory);
        if (is_file($oldDirectory.'.tar.gz') && !is_link($oldDirectory.'.tar.gz')) unlink($oldDirectory.'.tar.gz');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    // Snapshot helper already redacts credentials; errors here contain no credentials.
    fwrite(STDERR, 'Rental backup failed: '.$error->getMessage().PHP_EOL);
    exit(1);
}
