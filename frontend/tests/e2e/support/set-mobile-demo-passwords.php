<?php

// Explicit user-requested shortcut passwords for synthetic mobile fixtures only.
$frontend = dirname(__DIR__, 3);
$root = $frontend.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.'mobile-https';
$database = $root.DIRECTORY_SEPARATOR.'owner-demo.sqlite';
$accessFile = $root.DIRECTORY_SEPARATOR.'access.json';
if (($argv[1] ?? null) !== '123' || ! is_file($database) || ! is_file($accessFile)
    || realpath($database) !== $database || realpath($accessFile) !== $accessFile) {
    throw new RuntimeException('Only explicit 123 passwords on the exact synthetic mobile database are supported.');
}
$pdo = new PDO('sqlite:'.$database, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA busy_timeout = 5000');
if ($pdo->query('SELECT version FROM mobile_demo_security WHERE id = 1')->fetchColumn() !== 1
    || ! is_string($pdo->query('SELECT manifest FROM presentation_seed WHERE id = 1')->fetchColumn())) {
    throw new RuntimeException('The database is not the prepared synthetic mobile demo.');
}
$original = file_get_contents($accessFile);
$access = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
foreach (['admin' => 'owner', 'user' => 'tenant'] as $username => $role) {
    $query = $pdo->prepare('SELECT role FROM users WHERE username = ? AND is_active = 1');
    $query->execute([$username]);
    $actualRole = $query->fetchColumn();
    $query->closeCursor();
    if ($actualRole !== $role || ($access['accounts'][$username]['role'] ?? null) !== $role) {
        throw new RuntimeException('Unexpected demo account roles.');
    }
    $access['accounts'][$username]['password'] = '123';
}
$access['simple_demo_passwords'] = true;
$before = $root.DIRECTORY_SEPARATOR.'before-password-change-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';
$pdo->prepare('VACUUM INTO ?')->execute([$before]);
$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE users SET password_hash = ?, remember_token = NULL WHERE username = ?');
    foreach (['admin', 'user'] as $username) {
        $update->execute([password_hash('123', PASSWORD_BCRYPT, ['cost' => 10]), $username]);
    }
    if (file_put_contents($accessFile, json_encode($access, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Cannot update mobile access instructions.');
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    file_put_contents($accessFile, $original, LOCK_EX);
    throw $error;
}
$status = json_decode(file_get_contents($root.'/status.json'), true, flags: JSON_THROW_ON_ERROR);
$origin = $status['origin'] ?? '';
file_put_contents($root.'/access.txt', "Bản thử điện thoại: {$origin}/login\nChủ trọ: admin / 123\nNgười thuê: user / 123\nChỉ áp dụng dữ liệu mẫu mobile-https.\n");
echo "Synthetic mobile accounts updated: admin / 123 and user / 123.\n";
