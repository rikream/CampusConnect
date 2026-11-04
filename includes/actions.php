<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app.php';

function handle_post(PDO $pdo, ?array $user): never
{
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'login':
                $email = mb_strtolower(post_string('email', 190));
                $password = $_POST['password'] ?? '';
                $statement = $pdo->prepare(
                    'SELECT id, password_hash, is_active FROM users WHERE college_email = ? LIMIT 1'
                );
                $statement->execute([$email]);
                $account = $statement->fetch();
                if (!$account || !(bool) $account['is_active'] || !is_string($password)
                    || !password_verify($password, $account['password_hash'])) {
                    flash('The email or password was not recognized.', 'error');
                    redirect('index.php?page=login');
                }

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $account['id'];
                flash('Welcome back to CampusFind.');
                redirect('index.php');

            case 'register':
                $name = post_string('name', 120);
                $email = mb_strtolower(post_string('email', 190));
                $studentId = post_string('student_id', 60);
                $department = post_string('department', 120);
                $year = post_id('study_year');
                $password = $_POST['password'] ?? '';

                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
                    || $studentId === '' || $department === '' || $year < 1 || $year > 7
                    || !is_string($password) || strlen($password) < 8 || strlen($password) > 200) {
                    flash('Complete all fields with valid details. Passwords must be at least 8 characters.', 'error');
                    redirect('index.php?page=register');
                }

                $statement = $pdo->prepare(
                    'SELECT id FROM users WHERE college_email = ? OR student_id = ? LIMIT 1'
                );
                $statement->execute([$email, $studentId]);
                if ($statement->fetch()) {
                    flash('That college email or student ID is already registered.', 'error');
                    redirect('index.php?page=register');
                }

                $statement = $pdo->prepare(
                    'INSERT INTO users (name, college_email, student_id, department, study_year, password_hash)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    $name,
                    $email,
                    $studentId,
                    $department,
                    $year,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $newUserId = (int) $pdo->lastInsertId();
                record_activity($pdo, $newUserId, 'account_registered', 'A student account was created.');
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                flash('Your account is ready. Welcome to CampusFind!');
                redirect('index.php');

            case 'logout':
                require_login($user);
                $_SESSION = [];
                session_regenerate_id(true);
                flash('You have been signed out.');
                redirect('index.php?page=login');

            case 'create_item':
                require_login($user);
                $type = post_string('type', 10);
                $name = post_string('name', 120);
                $categoryId = post_id('category_id');
                $description = post_string('description', 2500);
                $occurredAt = post_string('occurred_at', 16);
                $locationId = post_id('location_id');
                $locationDetails = post_string('location_details', 180);
                $contactPreference = post_string('contact_preference', 20);
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $occurredAt);

                if (!in_array($type, ['lost', 'found'], true) || $name === '' || $description === ''
                    || !$date || $date->format('Y-m-d\TH:i') !== $occurredAt
                    || !in_array($contactPreference, ['in_app', 'college_email'], true)) {
                    flash('Check the report details and try again.', 'error');
                    redirect('index.php?page=report');
                }

                $statement = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
                $statement->execute([$categoryId]);
                $categoryExists = (bool) $statement->fetchColumn();
                $statement = $pdo->prepare('SELECT id FROM locations WHERE id = ? AND is_active = 1');
                $statement->execute([$locationId]);
                $locationExists = (bool) $statement->fetchColumn();
                if (!$categoryExists || !$locationExists) {
                    flash('Select a valid category and campus location.', 'error');
                    redirect('index.php?page=report');
                }

                $imageFilename = store_item_image();
                $statement = $pdo->prepare(
                    'INSERT INTO items
                     (user_id, type, name, category_id, description, occurred_at, location_id,
                      location_details, contact_preference, image_filename)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    (int) $user['id'],
                    $type,
                    $name,
                    $categoryId,
                    $description,
                    $date->format('Y-m-d H:i:s'),
                    $locationId,
                    $locationDetails,
                    $contactPreference,
                    $imageFilename,
                ]);
                $itemId = (int) $pdo->lastInsertId();
                record_activity($pdo, (int) $user['id'], 'item_submitted', ucfirst($type) . ' report #' . $itemId . ' submitted.');
                flash('Your report was submitted and is waiting for administrator review.');
                redirect('index.php?page=my-reports');

            case 'send_interest':
                require_login($user);
                $itemId = post_id('item_id');
                $message = post_string('message', 350);
                $statement = $pdo->prepare(
                    'SELECT id, user_id, name FROM items
                     WHERE id = ? AND review_status = "approved" AND item_status = "open" LIMIT 1'
                );
                $statement->execute([$itemId]);
                $item = $statement->fetch();
                if (!$item || (int) $item['user_id'] === (int) $user['id'] || $message === '') {
                    flash('This report is not available for a new inquiry.', 'error');
                    redirect('index.php?page=browse');
                }

                notify_user(
                    $pdo,
                    (int) $item['user_id'],
                    'New item inquiry',
                    mb_substr($user['name'] . ' is asking about "' . $item['name'] . '": ' . $message, 0, 500),
                    (int) $item['id']
                );
                record_activity($pdo, (int) $user['id'], 'item_inquiry_sent', 'An inquiry was sent about item #' . $itemId . '.');
                flash('Your inquiry was sent privately to the person who reported the item.');
                redirect('index.php?page=browse');

            case 'mark_read':
                require_login($user);
                $notificationId = post_id('notification_id');
                $statement = $pdo->prepare(
                    'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
                );
                $statement->execute([$notificationId, (int) $user['id']]);
                redirect('index.php?page=notifications');

            case 'mark_all_read':
                require_login($user);
                $statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
                $statement->execute([(int) $user['id']]);
                flash('All notifications have been marked as read.');
                redirect('index.php?page=notifications');

            case 'mark_recovered':
                require_login($user);
                $itemId = post_id('item_id');
                $statement = $pdo->prepare(
                    'SELECT id, user_id, name FROM items WHERE id = ? AND review_status = "approved" LIMIT 1'
                );
                $statement->execute([$itemId]);
                $item = $statement->fetch();
                if (!$item || ((int) $item['user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
                    http_response_code(403);
                    exit('You cannot update this report.');
                }
                $statement = $pdo->prepare(
                    'UPDATE items SET item_status = "recovered" WHERE id = ? AND item_status = "open"'
                );
                $statement->execute([$itemId]);
                if ($statement->rowCount() > 0) {
                    notify_user($pdo, (int) $item['user_id'], 'Item marked recovered', '"' . $item['name'] . '" has been marked as recovered.', $itemId);
                    record_activity($pdo, (int) $user['id'], 'item_recovered', 'Item #' . $itemId . ' was marked recovered.');
                    flash('The report is now marked as recovered.');
                } else {
                    flash('This report was already marked as recovered.', 'error');
                }
                redirect('index.php?page=my-reports');

            case 'review_item':
                require_admin($user);
                $itemId = post_id('item_id');
                $decision = post_string('decision', 12);
                if (!in_array($decision, ['approved', 'rejected'], true)) {
                    flash('Choose a valid review decision.', 'error');
                    redirect('index.php?page=admin&tab=reports');
                }

                $pdo->beginTransaction();
                $statement = $pdo->prepare('SELECT * FROM items WHERE id = ? FOR UPDATE');
                $statement->execute([$itemId]);
                $item = $statement->fetch();
                if (!$item || $item['review_status'] !== 'pending') {
                    $pdo->rollBack();
                    flash('That report is no longer waiting for review.', 'error');
                    redirect('index.php?page=admin&tab=reports');
                }
                $statement = $pdo->prepare('UPDATE items SET review_status = ? WHERE id = ?');
                $statement->execute([$decision, $itemId]);
                $statusMessage = $decision === 'approved'
                    ? 'Your report "' . $item['name'] . '" is now visible to students.'
                    : 'Your report "' . $item['name'] . '" was not approved. Please contact an administrator if you need help.';
                notify_user($pdo, (int) $item['user_id'], 'Report ' . $decision, $statusMessage, $itemId);
                record_activity($pdo, (int) $user['id'], 'item_' . $decision, 'Item #' . $itemId . ' was ' . $decision . '.');
                $pdo->commit();

                if ($decision === 'approved') {
                    $item['id'] = $itemId;
                    $matches = item_matches($pdo, $item, 5);
                    $notifiedUsers = [];
                    foreach ($matches as $match) {
                        if (!isset($notifiedUsers[(int) $item['user_id']])) {
                            notify_user(
                                $pdo,
                                (int) $item['user_id'],
                                'Possible item match',
                                'A report may match "' . $item['name'] . '". Check the matching reports in your dashboard.',
                                $itemId
                            );
                            $notifiedUsers[(int) $item['user_id']] = true;
                        }
                        $matchOwnerId = (int) $match['user_id'];
                        if ($matchOwnerId !== (int) $item['user_id'] && !isset($notifiedUsers[$matchOwnerId])) {
                            notify_user(
                                $pdo,
                                $matchOwnerId,
                                'Possible item match',
                                'A new report may match "' . $match['name'] . '". Check your dashboard for details.',
                                (int) $match['id']
                            );
                            $notifiedUsers[$matchOwnerId] = true;
                        }
                    }
                }

                flash('The report was ' . $decision . '.');
                redirect('index.php?page=admin&tab=reports');

            case 'delete_item':
                require_admin($user);
                $itemId = post_id('item_id');
                $statement = $pdo->prepare('DELETE FROM items WHERE id = ?');
                $statement->execute([$itemId]);
                record_activity($pdo, (int) $user['id'], 'item_deleted', 'Item #' . $itemId . ' was removed.');
                flash('The report was removed.');
                redirect('index.php?page=admin&tab=reports');

            case 'set_user_status':
                require_admin($user);
                $targetUserId = post_id('user_id');
                $active = post_string('is_active', 1);
                if ($targetUserId === (int) $user['id'] || !in_array($active, ['0', '1'], true)) {
                    flash('You cannot change your own account status.', 'error');
                    redirect('index.php?page=admin&tab=users');
                }
                $statement = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
                $statement->execute([(int) $active, $targetUserId]);
                record_activity($pdo, (int) $user['id'], 'user_status_changed', 'User #' . $targetUserId . ' status changed.');
                flash('The student account was updated.');
                redirect('index.php?page=admin&tab=users');

            case 'set_user_role':
                require_admin($user);
                $targetUserId = post_id('user_id');
                $role = post_string('role', 10);
                if ($targetUserId === (int) $user['id'] || !in_array($role, ['student', 'admin'], true)) {
                    flash('You cannot change your own role.', 'error');
                    redirect('index.php?page=admin&tab=users');
                }
                $statement = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $statement->execute([$role, $targetUserId]);
                record_activity($pdo, (int) $user['id'], 'user_role_changed', 'User #' . $targetUserId . ' role changed.');
                flash('The student role was updated.');
                redirect('index.php?page=admin&tab=users');

            case 'add_category':
                require_admin($user);
                $name = post_string('name', 80);
                if ($name === '') {
                    flash('Enter a category name.', 'error');
                    redirect('index.php?page=admin&tab=categories');
                }
                $statement = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
                $statement->execute([$name]);
                if ($statement->fetch()) {
                    flash('A category with that name already exists.', 'error');
                    redirect('index.php?page=admin&tab=categories');
                }
                $statement = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
                $statement->execute([$name]);
                record_activity($pdo, (int) $user['id'], 'category_added', 'Category "' . $name . '" was added.');
                flash('The category was added.');
                redirect('index.php?page=admin&tab=categories');

            case 'toggle_category':
                require_admin($user);
                $categoryId = post_id('category_id');
                $statement = $pdo->prepare(
                    'UPDATE categories SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?'
                );
                $statement->execute([$categoryId]);
                if ($statement->rowCount() === 0) {
                    flash('The category could not be found.', 'error');
                    redirect('index.php?page=admin&tab=categories');
                }
                record_activity($pdo, (int) $user['id'], 'category_status_changed', 'Category #' . $categoryId . ' visibility changed.');
                flash('The category was updated.');
                redirect('index.php?page=admin&tab=categories');

            case 'add_location':
                require_admin($user);
                $name = post_string('name', 100);
                $group = post_string('location_group', 80);
                if ($name === '' || $group === '') {
                    flash('Enter both a location name and group.', 'error');
                    redirect('index.php?page=admin&tab=locations');
                }
                $statement = $pdo->prepare('SELECT id FROM locations WHERE name = ? LIMIT 1');
                $statement->execute([$name]);
                if ($statement->fetch()) {
                    flash('A location with that name already exists.', 'error');
                    redirect('index.php?page=admin&tab=locations');
                }
                $statement = $pdo->prepare('INSERT INTO locations (name, location_group) VALUES (?, ?)');
                $statement->execute([$name, $group]);
                record_activity($pdo, (int) $user['id'], 'location_added', 'Location "' . $name . '" was added.');
                flash('The campus location was added.');
                redirect('index.php?page=admin&tab=locations');

            case 'toggle_location':
                require_admin($user);
                $locationId = post_id('location_id');
                $statement = $pdo->prepare(
                    'UPDATE locations SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?'
                );
                $statement->execute([$locationId]);
                if ($statement->rowCount() === 0) {
                    flash('The campus location could not be found.', 'error');
                    redirect('index.php?page=admin&tab=locations');
                }
                record_activity($pdo, (int) $user['id'], 'location_status_changed', 'Location #' . $locationId . ' visibility changed.');
                flash('The campus location was updated.');
                redirect('index.php?page=admin&tab=locations');

            default:
                flash('That action is not available.', 'error');
                redirect('index.php');
        }
    } catch (RuntimeException $exception) {
        flash($exception->getMessage(), 'error');
        redirect('index.php?page=report');
    }
}
