<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'app.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php <college-email>\n");
    exit(1);
}

$email = mb_strtolower(trim($argv[1]));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Provide a valid college email address.\n");
    exit(1);
}

$pdo = app_db();
$statement = $pdo->prepare('SELECT id, role FROM users WHERE college_email = ? LIMIT 1');
$statement->execute([$email]);
$account = $statement->fetch();
if (!$account) {
    fwrite(STDERR, "No student account exists for {$email}. Register the account first.\n");
    exit(1);
}

$statement = $pdo->prepare('UPDATE users SET role = "admin" WHERE id = ?');
$statement->execute([(int) $account['id']]);
record_activity($pdo, (int) $account['id'], 'administrator_enabled', 'The account was granted administrator access.');
fwrite(STDOUT, "Administrator access enabled for {$email}.\n");
