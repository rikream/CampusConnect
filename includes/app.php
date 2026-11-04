<?php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url = 'index.php'): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($message) ? $message : null;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('The request could not be verified. Reload the page and try again.');
    }
}

function current_user(PDO $pdo): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT id, name, college_email, student_id, department, study_year, role, is_active
         FROM users WHERE id = ? LIMIT 1'
    );
    $statement->execute([(int) $userId]);
    $user = $statement->fetch();

    if (!$user || !(bool) $user['is_active']) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function require_login(?array $user): void
{
    if ($user === null) {
        flash('Please sign in to continue.', 'error');
        redirect('index.php?page=login');
    }
}

function require_admin(?array $user): void
{
    require_login($user);
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function record_activity(PDO $pdo, ?int $userId, string $action, string $details): void
{
    $statement = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)'
    );
    $statement->execute([$userId, $action, $details]);
}

function notify_user(PDO $pdo, int $userId, string $title, string $message, ?int $itemId = null): void
{
    $statement = $pdo->prepare(
        'INSERT INTO notifications (user_id, item_id, title, message) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$userId, $itemId, $title, $message]);
}

function post_string(string $key, int $maxLength): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return trim(mb_substr($value, 0, $maxLength));
}

function post_id(string $key): int
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value) && !is_int($value)) {
        return 0;
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT);
    return $validated === false || $validated < 1 ? 0 : $validated;
}

function store_item_image(): ?string
{
    $upload = $_FILES['image'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = $upload['error'] ?? null;
    $temporaryPath = $upload['tmp_name'] ?? null;
    $size = $upload['size'] ?? null;
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image could not be uploaded. Please try another file.');
    }
    if (!is_string($temporaryPath) || !is_int($size) || $size < 0
        || $size > MAX_UPLOAD_BYTES || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('Images must be no larger than 3 MB.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mimeType])) {
        throw new RuntimeException('Choose a JPG, PNG, or WebP image.');
    }

    if (!is_dir(UPLOAD_DIRECTORY) && !mkdir(UPLOAD_DIRECTORY, 0755, true) && !is_dir(UPLOAD_DIRECTORY)) {
        throw new RuntimeException('The image upload folder is not available.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($temporaryPath, UPLOAD_DIRECTORY . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The image could not be saved. Please try again.');
    }

    return $filename;
}

function item_matches(PDO $pdo, array $item, int $limit = 4): array
{
    $oppositeType = $item['type'] === 'lost' ? 'found' : 'lost';
    $statement = $pdo->prepare(
        'SELECT i.*, c.name AS category_name, l.name AS location_name, u.name AS reporter_name
         FROM items i
         JOIN categories c ON c.id = i.category_id
         JOIN locations l ON l.id = i.location_id
         JOIN users u ON u.id = i.user_id
         WHERE i.type = ? AND i.review_status = "approved"
           AND i.item_status = "open" AND i.id <> ?
         ORDER BY i.created_at DESC LIMIT 250'
    );
    $statement->execute([$oppositeType, (int) $item['id']]);

    $candidates = [];
    foreach ($statement->fetchAll() as $candidate) {
        $nameScore = 0.0;
        similar_text(mb_strtolower($item['name']), mb_strtolower($candidate['name']), $nameScore);
        $score = min(35, $nameScore * 0.35);

        if ((int) $candidate['category_id'] === (int) $item['category_id']) {
            $score += 25;
        }
        if ((int) $candidate['location_id'] === (int) $item['location_id']) {
            $score += 15;
        }

        $itemDate = strtotime((string) $item['occurred_at']);
        $candidateDate = strtotime((string) $candidate['occurred_at']);
        $daysApart = abs($itemDate - $candidateDate) / 86400;
        $score += max(0, 15 - min(15, $daysApart));

        $itemWords = array_unique(preg_split('/[^a-z0-9]+/i', mb_strtolower($item['description']), -1, PREG_SPLIT_NO_EMPTY));
        $candidateWords = array_unique(preg_split('/[^a-z0-9]+/i', mb_strtolower($candidate['description']), -1, PREG_SPLIT_NO_EMPTY));
        $commonWords = array_intersect($itemWords, $candidateWords);
        $score += min(10, count($commonWords) * 2);

        if ($score >= 35) {
            $candidate['match_score'] = (int) round($score);
            $candidates[] = $candidate;
        }
    }

    usort($candidates, static fn(array $a, array $b): int => $b['match_score'] <=> $a['match_score']);
    return array_slice($candidates, 0, $limit);
}
