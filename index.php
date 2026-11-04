<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'app.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'actions.php';

$pdo = app_db();
$user = current_user($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post($pdo, $user);
}

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'browse', 'report', 'my-reports', 'notifications', 'admin', 'login', 'register'];
if (!is_string($page) || !in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}
if ($user === null && !in_array($page, ['login', 'register'], true)) {
    redirect('index.php?page=login');
}
if ($user !== null && in_array($page, ['login', 'register'], true)) {
    redirect('index.php');
}
if ($page === 'admin') {
    require_admin($user);
}

$flashMessage = take_flash();

function item_card(array $item, ?array $user, bool $showInquiry = false): void
{
    $typeLabel = $item['type'] === 'lost' ? 'Lost item' : 'Found item';
    ?>
    <article class="item-card">
        <?php if (!empty($item['image_filename'])): ?>
            <img class="item-photo" src="uploads/<?= e(rawurlencode($item['image_filename'])) ?>" alt="<?= e($item['name']) ?>">
        <?php else: ?>
            <div class="item-photo-placeholder" aria-hidden="true"><?= $item['type'] === 'lost' ? '↗' : '✓' ?></div>
        <?php endif; ?>
        <div class="item-card-body">
            <div class="item-card-top">
                <span class="badge <?= $item['type'] === 'lost' ? 'badge-lost' : 'badge-found' ?>"><?= e($typeLabel) ?></span>
                <?php if (isset($item['match_score'])): ?><span class="match-score"><?= (int) $item['match_score'] ?>% match</span><?php endif; ?>
            </div>
            <h3><?= e($item['name']) ?></h3>
            <p class="item-description"><?= e($item['description']) ?></p>
            <div class="item-meta">
                <span><?= e($item['category_name']) ?></span>
                <span><?= e($item['location_name']) ?><?= $item['location_details'] !== '' ? ' · ' . e($item['location_details']) : '' ?></span>
                <span><?= e(date('M j, Y', strtotime((string) $item['occurred_at']))) ?></span>
            </div>
            <div class="item-card-footer">
                <span>Reported by <?= e($item['reporter_name']) ?></span>
                <?php if ($showInquiry && $item['contact_preference'] === 'college_email' && filter_var($item['reporter_email'] ?? '', FILTER_VALIDATE_EMAIL)): ?>
                    <a class="contact-link" href="mailto:<?= e($item['reporter_email']) ?>">Email reporter</a>
                <?php else: ?>
                    <span><?= $item['contact_preference'] === 'college_email' ? 'College email preferred' : 'Private inquiry' ?></span>
                <?php endif; ?>
                <?php if (($item['item_status'] ?? 'open') === 'recovered'): ?><span class="status-text">Recovered</span><?php endif; ?>
            </div>
            <?php if ($showInquiry && ($item['item_status'] ?? 'open') === 'open' && $user !== null && (int) $item['user_id'] !== (int) $user['id']): ?>
                <details class="inquiry">
                    <summary>Ask about this item</summary>
                    <form method="post" class="inquiry-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_interest">
                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                        <label class="sr-only" for="message-<?= (int) $item['id'] ?>">Message</label>
                        <textarea id="message-<?= (int) $item['id'] ?>" name="message" rows="2" maxlength="500" required placeholder="Share a detail that helps identify the item"></textarea>
                        <button class="button button-small" type="submit">Send private inquiry</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

function count_rows(PDO $pdo, string $sql, array $parameters = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return (int) $statement->fetchColumn();
}

function select_items(PDO $pdo, string $where, array $parameters, int $limit = 24, int $offset = 0): array
{
    $sql = 'SELECT i.*, c.name AS category_name, l.name AS location_name,
                    u.name AS reporter_name, u.college_email AS reporter_email
            FROM items i
            JOIN categories c ON c.id = i.category_id
            JOIN locations l ON l.id = i.location_id
            JOIN users u ON u.id = i.user_id
            WHERE ' . $where . ' ORDER BY i.created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function render_item_grid(array $items, ?array $user, bool $showInquiry = false): void
{
    if (!$items) {
        echo '<div class="empty-state"><span class="empty-icon">⌕</span><h3>No reports found</h3><p>Try changing your filters or check back later.</p></div>';
        return;
    }

    echo '<div class="item-grid">';
    foreach ($items as $item) {
        item_card($item, $user, $showInquiry);
    }
    echo '</div>';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17243b">
    <title><?= e($page === 'dashboard' ? 'Dashboard' : ucfirst(str_replace('-', ' ', $page))) ?> · <?= e(APP_NAME) ?></title>
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php if ($user !== null): ?>
    <aside class="sidebar">
        <a class="brand" href="index.php"><span class="brand-mark">C</span><span>Campus<span class="brand-light">Find</span><small>Lost &amp; found, together</small></span></a>
        <div class="sidebar-label">Workspace</div>
        <nav class="side-nav" aria-label="Main navigation">
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php"><span>◫</span> Dashboard</a>
            <a class="<?= $page === 'browse' ? 'active' : '' ?>" href="index.php?page=browse"><span>⌕</span> Browse items</a>
            <a class="<?= $page === 'report' ? 'active' : '' ?>" href="index.php?page=report"><span>＋</span> Report an item</a>
            <a class="<?= $page === 'my-reports' ? 'active' : '' ?>" href="index.php?page=my-reports"><span>▤</span> My reports</a>
            <?php
            $unreadCount = count_rows($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [(int) $user['id']]);
            ?>
            <a class="<?= $page === 'notifications' ? 'active' : '' ?>" href="index.php?page=notifications"><span>♧</span> Notifications<?php if ($unreadCount > 0): ?><b class="nav-count"><?= $unreadCount ?></b><?php endif; ?></a>
            <?php if ($user['role'] === 'admin'): ?><a class="<?= $page === 'admin' ? 'active' : '' ?>" href="index.php?page=admin"><span>⚙</span> Admin panel</a><?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="profile-chip"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span><span><strong><?= e($user['name']) ?></strong><small><?= $user['role'] === 'admin' ? 'Administrator' : e($user['department']) ?></small></span></div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="logout-button" type="submit">↪ <span>Sign out</span></button>
            </form>
        </div>
    </aside>
<?php endif; ?>

<main class="<?= $user === null ? 'auth-main' : 'main-content' ?>">
    <?php if ($user !== null): ?>
        <header class="topbar">
            <div class="breadcrumb">CampusFind <span>/</span> <?= e(ucfirst(str_replace('-', ' ', $page))) ?></div>
            <div class="topbar-user"><span class="online-dot"></span> Campus community <span class="avatar avatar-small"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span></div>
        </header>
    <?php endif; ?>

    <div class="page-wrap <?= $user === null ? 'auth-wrap' : '' ?>">
        <?php if ($flashMessage !== null): ?>
            <div class="alert alert-<?= e($flashMessage['type'] ?? 'success') ?>" role="status"><?= e($flashMessage['message'] ?? '') ?></div>
        <?php endif; ?>

        <?php if ($page === 'login' || $page === 'register'): ?>
            <section class="auth-card">
                <a class="brand auth-brand" href="index.php"><span class="brand-mark">C</span><span>Campus<span class="brand-light">Find</span><small>Lost &amp; found, together</small></span></a>
                <div class="eyebrow"><?= $page === 'login' ? 'WELCOME BACK' : 'JOIN YOUR CAMPUS' ?></div>
                <h1><?= $page === 'login' ? 'Sign in to your account' : 'Create your student account' ?></h1>
                <p class="subheading"><?= $page === 'login' ? 'Pick up where your campus community left off.' : 'Make it easier to find what matters.' ?></p>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $page === 'login' ? 'login' : 'register' ?>">
                    <?php if ($page === 'register'): ?>
                        <label>Full name<input name="name" autocomplete="name" maxlength="120" required></label>
                    <?php endif; ?>
                    <label>College email<input type="email" name="email" autocomplete="email" maxlength="190" required></label>
                    <?php if ($page === 'register'): ?>
                        <label>Student ID<input name="student_id" maxlength="60" required></label>
                        <div class="form-row">
                            <label>Department<input name="department" maxlength="120" required></label>
                            <label>Study year<select name="study_year" required><option value="">Select</option><?php for ($year = 1; $year <= 7; $year++): ?><option value="<?= $year ?>">Year <?= $year ?></option><?php endfor; ?></select></label>
                        </div>
                    <?php endif; ?>
                    <label>Password<input type="password" name="password" autocomplete="<?= $page === 'login' ? 'current-password' : 'new-password' ?>" minlength="<?= $page === 'login' ? '1' : '8' ?>" maxlength="200" required></label>
                    <button class="button button-primary button-wide" type="submit"><?= $page === 'login' ? 'Sign in' : 'Create account' ?> <span>→</span></button>
                </form>
                <p class="auth-switch"><?= $page === 'login' ? 'New to CampusFind?' : 'Already have an account?' ?> <a href="index.php?page=<?= $page === 'login' ? 'register' : 'login' ?>"><?= $page === 'login' ? 'Create an account' : 'Sign in' ?></a></p>
            </section>
            <div class="auth-note"><span>✦</span> A little care goes a long way on campus.</div>

        <?php elseif ($page === 'dashboard'): ?>
            <?php
            $lostCount = count_rows($pdo, 'SELECT COUNT(*) FROM items WHERE review_status = "approved" AND item_status = "open" AND type = "lost"');
            $foundCount = count_rows($pdo, 'SELECT COUNT(*) FROM items WHERE review_status = "approved" AND item_status = "open" AND type = "found"');
            $myCount = count_rows($pdo, 'SELECT COUNT(*) FROM items WHERE user_id = ?', [(int) $user['id']]);
            $recoveredCount = count_rows($pdo, 'SELECT COUNT(*) FROM items WHERE user_id = ? AND item_status = "recovered"', [(int) $user['id']]);
            $recentItems = select_items($pdo, 'i.review_status = "approved" AND i.item_status = "open"', [], 4);
            $myOpenItems = select_items($pdo, 'i.user_id = ? AND i.review_status = "approved" AND i.item_status = "open"', [(int) $user['id']], 4);
            $months = [];
            $chartLabels = [];
            $monthStart = new DateTime('first day of this month');
            $monthStart->modify('-5 months');
            for ($offset = 0; $offset < 6; $offset++) {
                $month = (clone $monthStart)->modify('+' . $offset . ' months');
                $monthKey = $month->format('Y-m');
                $months[$monthKey] = ['lost' => 0, 'found' => 0];
                $chartLabels[] = $month->format('M');
            }
            $chartStatement = $pdo->query(
                'SELECT DATE_FORMAT(created_at, "%Y-%m") AS month_key, type, COUNT(*) AS total
                 FROM items
                 WHERE review_status = "approved"
                   AND created_at >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 5 MONTH), "%Y-%m-01")
                 GROUP BY month_key, type'
            );
            foreach ($chartStatement->fetchAll() as $row) {
                if (isset($months[$row['month_key']][$row['type']])) {
                    $months[$row['month_key']][$row['type']] = (int) $row['total'];
                }
            }
            $lostSeries = array_column($months, 'lost');
            $foundSeries = array_column($months, 'found');
            ?>
            <section class="welcome-row">
                <div><div class="eyebrow">YOUR CAMPUS, CONNECTED</div><h1>Good to see you, <?= e(explode(' ', $user['name'])[0]) ?> <span class="wave">✦</span></h1><p class="subheading">Every found item is a small act of kindness. Let's bring more of them home.</p></div>
                <a class="button button-primary" href="index.php?page=report">＋ <span>Report an item</span></a>
            </section>
            <section class="stats-grid row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3" aria-label="Campus item statistics">
                <article class="stat-card stat-blue col"><span class="stat-icon">⌕</span><span class="stat-label">Lost items</span><strong><?= $lostCount ?></strong><small>Open campus reports</small></article>
                <article class="stat-card stat-green col"><span class="stat-icon">✓</span><span class="stat-label">Found items</span><strong><?= $foundCount ?></strong><small>Waiting to reunite</small></article>
                <article class="stat-card stat-violet col"><span class="stat-icon">▤</span><span class="stat-label">My reports</span><strong><?= $myCount ?></strong><small>Your submitted items</small></article>
                <article class="stat-card stat-amber col"><span class="stat-icon">✦</span><span class="stat-label">Recovered</span><strong><?= $recoveredCount ?></strong><small>Items reunited by you</small></article>
            </section>
            <section class="panel activity-chart">
                <div class="section-heading"><div><div class="eyebrow">CAMPUS PULSE</div><h2>Reporting activity</h2></div><span class="chart-caption">Approved reports · last 6 months</span></div>
                <canvas id="activity-chart" height="100" aria-label="Lost and found reports by month" role="img"></canvas>
                <script>
                    window.campusFindChartData = <?= json_encode(
                        ['labels' => $chartLabels, 'lost' => $lostSeries, 'found' => $foundSeries],
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    ) ?>;
                </script>
            </section>
            <section class="content-section">
                <div class="section-heading"><div><div class="eyebrow">A GOOD PLACE TO START</div><h2>Recently reported</h2></div><a class="text-link" href="index.php?page=browse">Browse all items <span>→</span></a></div>
                <?php render_item_grid($recentItems, $user, true); ?>
            </section>
            <?php if ($myOpenItems): ?>
                <section class="content-section">
                    <div class="section-heading"><div><div class="eyebrow">SMART SUGGESTIONS</div><h2>Possible matches for your reports</h2></div></div>
                    <?php
                    $suggestions = [];
                    foreach ($myOpenItems as $myItem) {
                        foreach (item_matches($pdo, $myItem, 2) as $match) {
                            $match['source_report'] = $myItem['name'];
                            $suggestions[$match['id']] = $match;
                        }
                    }
                    render_item_grid(array_slice(array_values($suggestions), 0, 4), $user, true);
                    ?>
                </section>
            <?php endif; ?>

        <?php elseif ($page === 'browse'): ?>
            <?php
            $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
            $typeFilter = is_string($_GET['type'] ?? null) && in_array($_GET['type'], ['lost', 'found'], true) ? $_GET['type'] : '';
            $statusFilter = is_string($_GET['status'] ?? null) && in_array($_GET['status'], ['open', 'recovered', 'all'], true) ? $_GET['status'] : 'open';
            $categoryFilter = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT);
            $locationFilter = filter_var($_GET['location'] ?? '', FILTER_VALIDATE_INT);
            $validDate = static function (mixed $value): bool {
                if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return false;
                }
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                return $date !== false && $date->format('Y-m-d') === $value;
            };
            $dateFrom = $validDate($_GET['date_from'] ?? null) ? $_GET['date_from'] : '';
            $dateTo = $validDate($_GET['date_to'] ?? null) ? $_GET['date_to'] : '';
            $requestedPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT);
            $pageNumber = max(1, $requestedPage === false ? 1 : $requestedPage);
            $where = ['i.review_status = "approved"'];
            $parameters = [];
            if ($statusFilter !== 'all') {
                $where[] = 'i.item_status = ?';
                $parameters[] = $statusFilter;
            }
            if ($search !== '') {
                $where[] = '(i.name LIKE ? OR i.description LIKE ? OR l.name LIKE ? OR i.location_details LIKE ?)';
                $term = '%' . mb_substr($search, 0, 100) . '%';
                array_push($parameters, $term, $term, $term, $term);
            }
            if ($typeFilter !== '') {
                $where[] = 'i.type = ?';
                $parameters[] = $typeFilter;
            }
            if ($categoryFilter !== false && $categoryFilter !== null && $categoryFilter > 0) {
                $where[] = 'i.category_id = ?';
                $parameters[] = $categoryFilter;
            }
            if ($locationFilter !== false && $locationFilter !== null && $locationFilter > 0) {
                $where[] = 'i.location_id = ?';
                $parameters[] = $locationFilter;
            }
            if ($dateFrom !== '') {
                $where[] = 'i.occurred_at >= ?';
                $parameters[] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== '') {
                $where[] = 'i.occurred_at < DATE_ADD(?, INTERVAL 1 DAY)';
                $parameters[] = $dateTo;
            }
            $whereSql = implode(' AND ', $where);
            $totalItems = count_rows($pdo, 'SELECT COUNT(*) FROM items i JOIN locations l ON l.id = i.location_id WHERE ' . $whereSql, $parameters);
            $pageSize = 9;
            $pageNumber = min($pageNumber, max(1, (int) ceil($totalItems / $pageSize)));
            $browseItems = select_items($pdo, $whereSql, $parameters, $pageSize, ($pageNumber - 1) * $pageSize);
            $categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
            $locations = $pdo->query('SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name')->fetchAll();
            ?>
            <section class="page-heading"><div class="eyebrow">COMMUNITY BOARD</div><h1>Browse campus reports</h1><p class="subheading">A name, a place, a little detail — the right clue can make someone's day.</p></section>
            <form class="filter-bar" method="get">
                <input type="hidden" name="page" value="browse">
                <div class="filter-primary">
                    <label class="search-input"><span>⌕</span><input name="q" value="<?= e($search) ?>" placeholder="Search items, details, locations…" aria-label="Search reports"></label>
                    <select name="type" aria-label="Item type"><option value="">All types</option><option value="lost" <?= $typeFilter === 'lost' ? 'selected' : '' ?>>Lost</option><option value="found" <?= $typeFilter === 'found' ? 'selected' : '' ?>>Found</option></select>
                    <select name="category" aria-label="Category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
                    <select name="location" aria-label="Location"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>" <?= (int) $locationFilter === (int) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="filter-advanced">
                    <select name="status" aria-label="Item status"><option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open items</option><option value="recovered" <?= $statusFilter === 'recovered' ? 'selected' : '' ?>>Recovered items</option><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option></select>
                    <label>Date from<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
                    <label>Date to<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
                    <button class="button button-primary button-small" type="submit">Apply filters</button>
                </div>
            </form>
            <div class="results-line"><?= $totalItems ?> <?= $totalItems === 1 ? 'report' : 'reports' ?> found</div>
            <?php render_item_grid($browseItems, $user, true); ?>
            <?php if ($totalItems > $pageSize): ?>
                <nav class="pagination" aria-label="Pagination">
                    <?php for ($number = 1; $number <= (int) ceil($totalItems / $pageSize); $number++): ?>
                        <a class="<?= $number === $pageNumber ? 'current' : '' ?>" href="<?= e('index.php?' . http_build_query(['page' => 'browse', 'q' => $search, 'type' => $typeFilter, 'category' => $categoryFilter ?: '', 'location' => $locationFilter ?: '', 'status' => $statusFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'p' => $number])) ?>"><?= $number ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>

        <?php elseif ($page === 'report'): ?>
            <?php
            $categories = $pdo->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll();
            $locations = $pdo->query('SELECT id, name, location_group FROM locations WHERE is_active = 1 ORDER BY location_group, name')->fetchAll();
            ?>
            <section class="page-heading"><div class="eyebrow">HELP YOUR CAMPUS COMMUNITY</div><h1>Report an item</h1><p class="subheading">The more detail you share, the easier it is to get this item back where it belongs.</p></section>
            <form class="panel report-form" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_item">
                <div class="form-section-title"><span>01</span><div><h2>What happened?</h2><p>Choose the report type and tell us about the item.</p></div></div>
                <div class="segmented-control">
                    <label><input type="radio" name="type" value="lost" checked><span>⌕ &nbsp; I lost an item</span></label>
                    <label><input type="radio" name="type" value="found"><span>✓ &nbsp; I found an item</span></label>
                </div>
                <div class="form-row">
                    <label>Item name<input name="name" maxlength="120" required placeholder="e.g. Blue water bottle"></label>
                    <label>Category<select name="category_id" required><option value="">Choose a category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
                </div>
                <label>Description<textarea name="description" rows="4" maxlength="2500" required placeholder="Color, brand, unique marks, or anything else that could help identify it"></textarea></label>
                <div class="form-section-title form-section-spaced"><span>02</span><div><h2>Where and when?</h2><p>An approximate time and campus location are helpful.</p></div></div>
                <div class="form-row">
                    <label>Date and time<input type="datetime-local" name="occurred_at" value="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
                    <label>Campus location<select name="location_id" required><option value="">Choose a location</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= e($location['location_group'] . ' · ' . $location['name']) ?></option><?php endforeach; ?></select></label>
                </div>
                <label>More specific location <span class="optional">(optional)</span><input name="location_details" maxlength="180" placeholder="e.g. Second floor, near the east entrance"></label>
                <div class="form-row">
                    <label>Photo <span class="optional">(optional, max 3 MB)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                    <label>Preferred contact<select name="contact_preference"><option value="in_app">Private in-app inquiry</option><option value="college_email">College email</option></select></label>
                </div>
                <div class="form-footer"><p>Your report will be reviewed before it appears on the campus board.</p><button class="button button-primary" type="submit">Submit report <span>→</span></button></div>
            </form>

        <?php elseif ($page === 'my-reports'): ?>
            <?php $myReports = select_items($pdo, 'i.user_id = ?', [(int) $user['id']], 100); ?>
            <section class="page-heading"><div class="eyebrow">YOUR ACTIVITY</div><h1>My reports</h1><p class="subheading">Follow your reports from review to reunion.</p></section>
            <?php if ($myReports): ?>
                <div class="item-grid">
                    <?php foreach ($myReports as $item): ?>
                        <div class="report-card-wrap">
                            <?php item_card($item, $user); ?>
                            <div class="report-actions">
                                <span class="review-badge review-<?= e($item['review_status']) ?>"><?= e(ucfirst($item['review_status'])) ?></span>
                                <?php if ($item['item_status'] === 'open' && $item['review_status'] === 'approved'): ?>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_recovered"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="text-button" type="submit">Mark recovered</button></form>
                                <?php elseif ($item['item_status'] === 'recovered'): ?><span class="status-text">✓ Recovered</span><?php endif; ?>
                            </div>
                            <?php if ($item['review_status'] === 'approved' && $item['item_status'] === 'open'): ?>
                                <?php $matches = item_matches($pdo, $item, 2); ?>
                                <?php if ($matches): ?><div class="match-note">✦ <?= count($matches) ?> possible <?= count($matches) === 1 ? 'match' : 'matches' ?> — see your dashboard</div><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><span class="empty-icon">▤</span><h3>Your reports will appear here</h3><p>Let the campus community know what you lost or found.</p><a class="button button-primary" href="index.php?page=report">Create your first report</a></div>
            <?php endif; ?>

        <?php elseif ($page === 'notifications'): ?>
            <?php
            $statement = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
            $statement->execute([(int) $user['id']]);
            $notifications = $statement->fetchAll();
            ?>
            <section class="section-heading notification-heading"><div><div class="eyebrow">UPDATES FOR YOU</div><h1>Notifications</h1></div>
                <?php if ($unreadCount > 0): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_read"><button class="text-button" type="submit">Mark all as read</button></form><?php endif; ?>
            </section>
            <div class="notification-list">
                <?php if (!$notifications): ?><div class="empty-state"><span class="empty-icon">♧</span><h3>You're all caught up</h3><p>We'll let you know when there's news about your reports.</p></div><?php endif; ?>
                <?php foreach ($notifications as $notification): ?>
                    <article class="notification-row <?= !(bool) $notification['is_read'] ? 'unread' : '' ?>">
                        <span class="notification-icon"><?= !(bool) $notification['is_read'] ? '✦' : '○' ?></span>
                        <div class="notification-copy"><strong><?= e($notification['title']) ?></strong><p><?= e($notification['message']) ?></p><small><?= e(date('M j, Y · g:i a', strtotime($notification['created_at']))) ?></small></div>
                        <?php if (!(bool) $notification['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><button class="text-button" type="submit">Mark read</button></form><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($page === 'admin'): ?>
            <?php
            $tab = $_GET['tab'] ?? 'overview';
            $adminTabs = ['overview', 'reports', 'users', 'categories', 'locations', 'activity'];
            if (!is_string($tab) || !in_array($tab, $adminTabs, true)) {
                $tab = 'overview';
            }
            $pendingCount = count_rows($pdo, 'SELECT COUNT(*) FROM items WHERE review_status = "pending"');
            ?>
            <section class="page-heading"><div class="eyebrow">CAMPUSFINDS CONTROL ROOM</div><h1>Admin panel</h1><p class="subheading">Keep campus reports accurate, helpful, and safe for everyone.</p></section>
            <nav class="admin-tabs" aria-label="Admin sections"><?php foreach ($adminTabs as $adminTab): ?><a class="<?= $tab === $adminTab ? 'active' : '' ?>" href="index.php?page=admin&amp;tab=<?= e($adminTab) ?>"><?= e(ucfirst($adminTab)) ?><?php if ($adminTab === 'reports' && $pendingCount > 0): ?><span class="tab-count"><?= $pendingCount ?></span><?php endif; ?></a><?php endforeach; ?></nav>

            <?php if ($tab === 'overview'): ?>
                <?php
                $adminStats = [
                    ['Students', count_rows($pdo, 'SELECT COUNT(*) FROM users WHERE role = "student"'), '♙'],
                    ['Pending review', $pendingCount, '◷'],
                    ['Approved reports', count_rows($pdo, 'SELECT COUNT(*) FROM items WHERE review_status = "approved"'), '▤'],
                    ['Recovered items', count_rows($pdo, 'SELECT COUNT(*) FROM items WHERE item_status = "recovered"'), '✓'],
                ];
                $activity = $pdo->query('SELECT a.*, u.name AS user_name FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 8')->fetchAll();
                ?>
                <section class="stats-grid admin-stats row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3"><?php foreach ($adminStats as [$label, $number, $icon]): ?><article class="stat-card stat-blue col"><span class="stat-icon"><?= $icon ?></span><span class="stat-label"><?= e($label) ?></span><strong><?= (int) $number ?></strong></article><?php endforeach; ?></section>
                <section class="panel table-panel"><div class="section-heading"><div><h2>Recent activity</h2><p class="subheading">Latest changes across CampusFind</p></div><a class="text-link" href="index.php?page=admin&amp;tab=activity">View all →</a></div><?php render_activity_table($activity); ?></section>

            <?php elseif ($tab === 'reports'): ?>
                <?php
                $pendingItems = select_items($pdo, 'i.review_status = "pending"', [], 100);
                $reviewedItems = select_items($pdo, 'i.review_status <> "pending"', [], 30);
                ?>
                <section class="panel table-panel"><div class="section-heading"><div><h2>Awaiting review <span class="tab-count"><?= $pendingCount ?></span></h2><p class="subheading">Approve appropriate reports to make them visible to students.</p></div></div>
                    <?php if (!$pendingItems): ?><div class="empty-inline">There are no reports waiting for review.</div><?php endif; ?>
                    <?php foreach ($pendingItems as $item): ?><article class="admin-report">
                        <div class="admin-report-main"><?php if (!empty($item['image_filename'])): ?><img src="uploads/<?= e(rawurlencode($item['image_filename'])) ?>" alt="" class="admin-thumb"><?php endif; ?><div><div class="item-card-top"><span class="badge <?= $item['type'] === 'lost' ? 'badge-lost' : 'badge-found' ?>"><?= e(ucfirst($item['type'])) ?></span><small><?= e(date('M j, Y', strtotime($item['created_at']))) ?></small></div><h3><?= e($item['name']) ?></h3><p><?= e($item['description']) ?></p><div class="item-meta"><span><?= e($item['category_name']) ?></span><span><?= e($item['location_name']) ?></span><span>By <?= e($item['reporter_name']) ?></span></div></div></div>
                        <div class="admin-report-actions"><?php foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $decision => $label): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review_item"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="decision" value="<?= e($decision) ?>"><button class="button <?= $decision === 'approved' ? 'button-primary' : 'button-muted' ?> button-small" type="submit"><?= e($label) ?></button></form><?php endforeach; ?></div>
                    </article><?php endforeach; ?>
                </section>
                <section class="panel table-panel"><h2>Recently reviewed</h2><div class="table-scroll"><table><thead><tr><th>Report</th><th>Type</th><th>Reporter</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody><?php foreach ($reviewedItems as $item): ?><tr><td><?= e($item['name']) ?></td><td><?= e(ucfirst($item['type'])) ?></td><td><?= e($item['reporter_name']) ?></td><td><span class="review-badge review-<?= e($item['review_status']) ?>"><?= e(ucfirst($item['review_status'])) ?></span></td><td><?= e(date('M j, Y', strtotime($item['created_at']))) ?></td><td><form method="post" onsubmit="return confirm('Remove this report permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="text-button danger-text" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>

            <?php elseif ($tab === 'users'): ?>
                <?php $users = $pdo->query('SELECT id, name, college_email, student_id, department, role, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 250')->fetchAll(); ?>
                <section class="panel table-panel"><div class="section-heading"><div><h2>Student accounts</h2><p class="subheading">Manage account access and administrator permissions.</p></div></div><div class="table-scroll"><table><thead><tr><th>Student</th><th>Student ID</th><th>Department</th><th>Role</th><th>Access</th><th>Actions</th></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><strong><?= e($account['name']) ?></strong><small class="table-sub"><?= e($account['college_email']) ?></small></td><td><?= e($account['student_id']) ?></td><td><?= e($account['department']) ?></td><td><span class="review-badge"><?= e(ucfirst($account['role'])) ?></span></td><td><?= (bool) $account['is_active'] ? 'Active' : 'Disabled' ?></td><td><?php if ((int) $account['id'] !== (int) $user['id']): ?><div class="table-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_user_status"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="is_active" value="<?= (bool) $account['is_active'] ? '0' : '1' ?>"><button class="text-button" type="submit"><?= (bool) $account['is_active'] ? 'Disable' : 'Enable' ?></button></form><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="set_user_role"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="role" value="<?= $account['role'] === 'admin' ? 'student' : 'admin' ?>"><button class="text-button" type="submit"><?= $account['role'] === 'admin' ? 'Make student' : 'Make admin' ?></button></form></div><?php else: ?><span class="table-sub">Current account</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section>

            <?php elseif ($tab === 'categories'): ?>
                <?php $categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.category_id = c.id) AS item_count FROM categories c ORDER BY c.name')->fetchAll(); ?>
                <section class="admin-split"><form method="post" class="panel compact-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_category"><h2>Add category</h2><p class="subheading">Organize the items students report.</p><label>Category name<input name="name" maxlength="80" required></label><button class="button button-primary" type="submit">Add category</button></form><div class="panel table-panel"><h2>Categories</h2><div class="table-scroll"><table><thead><tr><th>Name</th><th>Reports</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td><?= e($category['name']) ?></td><td><?= (int) $category['item_count'] ?></td><td><?= (bool) $category['is_active'] ? 'Active' : 'Hidden' ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_category"><input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>"><button class="text-button" type="submit"><?= (bool) $category['is_active'] ? 'Hide' : 'Activate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></div></section>

            <?php elseif ($tab === 'locations'): ?>
                <?php $locations = $pdo->query('SELECT l.*, (SELECT COUNT(*) FROM items i WHERE i.location_id = l.id) AS item_count FROM locations l ORDER BY l.location_group, l.name')->fetchAll(); ?>
                <section class="admin-split"><form method="post" class="panel compact-form"><?= csrf_field() ?><input type="hidden" name="action" value="add_location"><h2>Add campus location</h2><p class="subheading">Keep campus search locations up to date.</p><label>Location name<input name="name" maxlength="100" required></label><label>Location group<input name="location_group" maxlength="80" required placeholder="e.g. Academic"></label><button class="button button-primary" type="submit">Add location</button></form><div class="panel table-panel"><h2>Campus locations</h2><div class="table-scroll"><table><thead><tr><th>Location</th><th>Group</th><th>Reports</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($locations as $location): ?><tr><td><?= e($location['name']) ?></td><td><?= e($location['location_group']) ?></td><td><?= (int) $location['item_count'] ?></td><td><?= (bool) $location['is_active'] ? 'Active' : 'Hidden' ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_location"><input type="hidden" name="location_id" value="<?= (int) $location['id'] ?>"><button class="text-button" type="submit"><?= (bool) $location['is_active'] ? 'Hide' : 'Activate' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div></div></section>

            <?php elseif ($tab === 'activity'): ?>
                <?php $activity = $pdo->query('SELECT a.*, u.name AS user_name FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 100')->fetchAll(); ?>
                <section class="panel table-panel"><div class="section-heading"><div><h2>Activity log</h2><p class="subheading">Recent actions recorded in the system.</p></div></div><?php render_activity_table($activity); ?></section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
<?php if ($page === 'dashboard'): ?><script src="assets/vendor/chart.js/chart.umd.min.js" defer></script><?php endif; ?>
<script src="assets/app.js" defer></script>
</body>
</html>

<?php
function render_activity_table(array $activity): void
{
    ?>
    <div class="table-scroll"><table><thead><tr><th>Activity</th><th>Details</th><th>By</th><th>Time</th></tr></thead><tbody>
        <?php foreach ($activity as $entry): ?><tr><td><?= e(ucwords(str_replace('_', ' ', $entry['action']))) ?></td><td><?= e($entry['details']) ?></td><td><?= e($entry['user_name'] ?? 'System') ?></td><td><?= e(date('M j, Y · g:i a', strtotime($entry['created_at']))) ?></td></tr><?php endforeach; ?>
        <?php if (!$activity): ?><tr><td colspan="4" class="empty-inline">No activity recorded yet.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php
}
