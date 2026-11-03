<?php
/**
 * includes/services/admin-account-service.php
 * Everything the Super Admin can do to ADMINISTRATOR accounts (role = 'admin').
 * The Super Admin's own account and other super admins can never be changed through these functions.
 *
 * Functions that change data return:
 *   ['ok' => bool, 'message' => string, 'code' => int, 'errors' => [field => msg]?, 'id' => int?, 'secret' => array?]
 * 'secret' (a set-password link or a temporary password) is shown to the Super Admin ONCE and never stored in plain text.
 * Email is not configured in this project, so nothing here ever claims an email was sent.
 */

const ADMIN_GENDERS        = ['Female', 'Male', 'Prefer not to say'];
const ADMIN_STATUS_OPTIONS = ['active' => 'Active', 'deactivated' => 'Deactivated'];
const ADMIN_TOKEN_HOURS    = 24;
const ADMIN_PER_PAGE       = 10;
const ADMIN_PASSWORD_MAX   = 72;   // bcrypt only uses the first 72 bytes

function acct_result(bool $ok, string $message, int $code = 200, array $extra = []): array
{
    return ['ok' => $ok, 'message' => $message, 'code' => $code] + $extra;
}

function acct_fail(string $message, int $code = 422, array $errors = []): array
{
    return acct_result(false, $message, $code, $errors ? ['errors' => $errors] : []);
}

/** Password rules shared by every place a password is chosen. Returns an error message or null. */
function acct_password_error(string $password, string $email = ''): ?string
{
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        return 'The password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    }
    if (strlen($password) > ADMIN_PASSWORD_MAX) {
        return 'The password must be ' . ADMIN_PASSWORD_MAX . ' characters or fewer.';
    }
    if ($email !== '' && mb_strtolower($password) === mb_strtolower($email)) {
        return 'The password must not be the same as the email address.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'The password must contain at least one letter and one number.';
    }
    return null;
}

/** A random temporary password (letters and digits without look-alike characters). */
function acct_generate_password(int $length = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    do {
        $pw = '';
        for ($i = 0; $i < $length; $i++) {
            $pw .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } while (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw));
    return $pw;
}

/** Base URL (scheme + host) used inside copied links. The Host header is validated first. */
function acct_site_origin(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https://' : 'http://') . $host;
}

/** Create a one-time token for an admin; older unused tokens are cancelled. Returns the raw token. */
function acct_create_token(PDO $pdo, int $adminId, string $purpose, ?int $createdBy): string
{
    $pdo->prepare('UPDATE admin_password_tokens SET used_at = now() WHERE admin_id = ? AND used_at IS NULL')->execute([$adminId]);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        "INSERT INTO admin_password_tokens (admin_id, token_hash, purpose, expires_at, created_by)
         VALUES (?, ?, ?, now() + (? * INTERVAL '1 hour'), ?)"
    )->execute([$adminId, hash('sha256', $token), $purpose, ADMIN_TOKEN_HOURS, $createdBy]);
    return $token;
}

function acct_token_link(string $token): string
{
    return acct_site_origin() . url('admin/set-password.php') . '?token=' . $token;
}

/* ---------------------------------------------------------------- reading */

function list_admin_accounts(PDO $pdo, array $f): array
{
    $where = ["role = 'admin'"];
    $params = [];
    if ($f['q'] !== '') {
        $where[] = '(name ILIKE ? OR email ILIKE ?)';
        $like = '%' . like_escape($f['q']) . '%';
        array_push($params, $like, $like);
    }
    if ($f['status'] === 'active')      { $where[] = 'is_active'; }
    if ($f['status'] === 'deactivated') { $where[] = 'NOT is_active'; }
    $w = implode(' AND ', $where);

    $c = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE $w");
    $c->execute($params);
    $total = (int) $c->fetchColumn();
    $pages = max(1, (int) ceil($total / ADMIN_PER_PAGE));
    $page  = min(max(1, $f['page']), $pages);

    $stmt = $pdo->prepare(
        "SELECT id, name, email, is_active, created_at FROM admins WHERE $w
         ORDER BY created_at DESC, id DESC LIMIT " . ADMIN_PER_PAGE . ' OFFSET ' . (($page - 1) * ADMIN_PER_PAGE)
    );
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/** One administrator (role 'admin' only), or null. */
function get_admin_account(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, name, email, role, gender, is_active, must_change_password, created_at, updated_at, last_login_at
         FROM admins WHERE id = ? AND role = 'admin'"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* ---------------------------------------------------------------- validation */

/** Validate name/email/status (and gender when given). Returns [cleaned, errors]. */
function acct_validate(array $in, bool $withGender): array
{
    $errors = [];
    $name   = trim(preg_replace('/\s+/', ' ', (string) ($in['name'] ?? '')));
    $email  = mb_strtolower(trim((string) ($in['email'] ?? '')));
    $status = (string) ($in['status'] ?? 'active');
    $gender = trim((string) ($in['gender'] ?? ''));

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) { $errors['name'] = 'Enter the name (2 to 100 characters).'; }
    if ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Enter a valid email address.'; }
    if (!isset(ADMIN_STATUS_OPTIONS[$status]))          { $errors['status'] = 'Choose Active or Deactivated.'; }
    if ($withGender && $gender !== '' && !in_array($gender, ADMIN_GENDERS, true)) { $errors['gender'] = 'Choose one of the listed options.'; }
    return [['name' => $name, 'email' => $email, 'status' => $status, 'gender' => $gender === '' ? null : $gender], $errors];
}

/* ---------------------------------------------------------------- create */

/** $in: name, email, status, gender, setup ('link' | 'temp') */
function create_admin_account(PDO $pdo, int $superId, array $in): array
{
    [$v, $errors] = acct_validate($in, true);
    $setup = $in['setup'] ?? '';
    if (!in_array($setup, ['link', 'temp'], true)) {
        $errors['setup'] = 'Choose how the password is set up.';
    }
    if ($errors) {
        return acct_fail('Please correct the highlighted fields.', 422, $errors);
    }

    try {
        $pdo->beginTransaction();
        $tempPassword = $setup === 'temp' ? acct_generate_password() : null;
        // For the link option the account gets a random, never-revealed password until the person sets their own
        $hash = password_hash($tempPassword ?? bin2hex(random_bytes(24)), PASSWORD_DEFAULT);

        $id = insert_returning_id($pdo,
            "INSERT INTO admins (name, email, password_hash, role, is_active, gender, must_change_password, created_by, deactivated_at)
             VALUES (?, ?, ?, 'admin', ?, ?, ?, ?, " . ($v['status'] === 'active' ? 'NULL' : 'now()') . ')',
            [$v['name'], $v['email'], $hash, $v['status'] === 'active' ? 'true' : 'false', $v['gender'],
             $setup === 'temp' ? 'true' : 'false', $superId]);

        $secret = null;
        if ($setup === 'link') {
            $secret = ['type' => 'link', 'value' => acct_token_link(acct_create_token($pdo, $id, 'set_password', $superId)), 'admin_id' => $id];
        } else {
            $secret = ['type' => 'temp', 'value' => $tempPassword, 'admin_id' => $id];
        }
        log_activity('admin_created', 'admin', $id, $v['name'] . ' (' . $v['email'] . ')');
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e->getCode() === '23505') {
            return acct_fail('Please correct the highlighted fields.', 422, ['email' => 'An account with this email already exists.']);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return acct_result(true, 'Admin account created.', 201, ['id' => $id, 'secret' => $secret]);
}

/* ---------------------------------------------------------------- update */

function update_admin_account(PDO $pdo, int $superId, int $id, array $in): array
{
    [$v, $errors] = acct_validate($in, false);
    if ($errors) {
        return acct_fail('Please correct the highlighted fields.', 422, $errors);
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT name, email, is_active, updated_at FROM admins WHERE id = ? AND role = 'admin' FOR UPDATE");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return acct_fail('That administrator account no longer exists.', 404);
        }
        $version = trim((string) ($in['version'] ?? ''));
        if ($version !== '' && $version !== $row['updated_at']) {
            $pdo->rollBack();
            return acct_fail('This account was changed by someone else. Please reload the page and try again.', 409);
        }
        $active = $v['status'] === 'active';
        $pdo->prepare(
            'UPDATE admins SET name = ?, email = ?, is_active = ?,
                    deactivated_at = CASE WHEN ? THEN NULL WHEN is_active THEN now() ELSE deactivated_at END,
                    sessions_valid_after = CASE WHEN ? OR NOT is_active THEN sessions_valid_after ELSE now() END
             WHERE id = ?'
        )->execute([$v['name'], $v['email'], $active ? 'true' : 'false', $active ? 'true' : 'false', $active ? 'true' : 'false', $id]);

        $changes = [];
        if ($row['name'] !== $v['name'])   { $changes[] = 'name'; }
        if ($row['email'] !== $v['email']) { $changes[] = 'email'; }
        if ((bool) $row['is_active'] !== $active) { $changes[] = $active ? 'reactivated' : 'deactivated'; }
        if (!$changes) {
            $pdo->rollBack();
            return acct_result(true, 'No changes to save.');
        }
        $action = in_array('reactivated', $changes, true) ? 'admin_reactivated'
                : (in_array('deactivated', $changes, true) ? 'admin_deactivated' : 'admin_updated');
        log_activity($action, 'admin', $id, $v['name'] . ' (' . $v['email'] . '): ' . implode(', ', $changes));
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e->getCode() === '23505') {
            return acct_fail('Please correct the highlighted fields.', 422, ['email' => 'Another account already uses this email.']);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return acct_result(true, 'Changes saved.');
}

/* ---------------------------------------------------------------- reset / deactivate / remove */

function reset_admin_password(PDO $pdo, int $superId, int $id, bool $signOut): array
{
    $admin = get_admin_account($pdo, $id);
    if (!$admin) {
        return acct_fail('That administrator account no longer exists.', 404);
    }
    if (!$admin['is_active']) {
        return acct_fail('This account is deactivated. Reactivate it before resetting the password.', 409);
    }
    try {
        $pdo->beginTransaction();
        $token = acct_create_token($pdo, $id, 'reset_password', $superId);
        if ($signOut) {
            $pdo->prepare('UPDATE admins SET sessions_valid_after = now() WHERE id = ?')->execute([$id]);
        }
        log_activity('admin_password_reset', 'admin', $id,
            $admin['name'] . ' (' . $admin['email'] . '): reset link created' . ($signOut ? ', sessions ended' : ''));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return acct_result(true, 'Reset link created.', 200, ['secret' => ['type' => 'link', 'value' => acct_token_link($token), 'admin_id' => $id]]);
}

function set_admin_active(PDO $pdo, int $superId, int $id, bool $active): array
{
    $admin = get_admin_account($pdo, $id);
    if (!$admin) {
        return acct_fail('That administrator account no longer exists.', 404);
    }
    if ((bool) $admin['is_active'] === $active) {
        return acct_result(true, $active ? 'The account is already active.' : 'The account is already deactivated.');
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE admins SET is_active = ?, deactivated_at = ' . ($active ? 'NULL' : 'now()') . ',
                    sessions_valid_after = ' . ($active ? 'sessions_valid_after' : 'now()') . ' WHERE id = ?'
        )->execute([$active ? 'true' : 'false', $id]);
        log_activity($active ? 'admin_reactivated' : 'admin_deactivated', 'admin', $id, $admin['name'] . ' (' . $admin['email'] . ')');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return acct_result(true, $active ? 'Account reactivated.' : 'Account deactivated. The administrator has been signed out.');
}

/** Permanently delete an administrator. $confirmEmail must match the account's email exactly. */
function remove_admin_account(PDO $pdo, int $superId, int $id, string $confirmEmail): array
{
    $admin = get_admin_account($pdo, $id);
    if (!$admin) {
        return acct_fail('That administrator account no longer exists.', 404);
    }
    if (mb_strtolower(trim($confirmEmail)) !== $admin['email']) {
        return acct_fail('Type the administrator\'s email address exactly to confirm the removal.', 422);
    }
    $pdo->beginTransaction();
    try {
        // Log first: the name is saved in the entry, so it stays readable after the account is gone
        log_activity('admin_removed', 'admin', $id, $admin['name'] . ' (' . $admin['email'] . ')');
        $pdo->prepare("DELETE FROM admins WHERE id = ? AND role = 'admin'")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return acct_result(true, 'The account was permanently removed.');
}

/* ---------------------------------------------------------------- password links (administrator side) */

/** Look up a usable token. Returns the admin row (id, name, email) or null. */
function acct_find_valid_token(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT t.id AS token_id, a.id, a.name, a.email, a.is_active
         FROM admin_password_tokens t JOIN admins a ON a.id = t.admin_id
         WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > now() AND a.role = 'admin'"
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    return ($row && $row['is_active']) ? $row : null;
}

function set_password_with_token(PDO $pdo, string $token, string $password, string $confirm): array
{
    $admin = acct_find_valid_token($pdo, $token);
    if (!$admin) {
        return acct_fail('This link is invalid, has expired, or was already used. Ask the super administrator for a new one.', 410);
    }
    if ($password !== $confirm) {
        return acct_fail('The two passwords do not match.');
    }
    if ($err = acct_password_error($password, $admin['email'])) {
        return acct_fail($err);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE admins SET password_hash = ?, must_change_password = false, sessions_valid_after = now() WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        $pdo->prepare('UPDATE admin_password_tokens SET used_at = now() WHERE admin_id = ? AND used_at IS NULL')->execute([$admin['id']]);
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$admin['email']]);
        log_activity('admin_password_set', 'admin', (int) $admin['id'], $admin['name'] . ' (' . $admin['email'] . '): password set from link', (int) $admin['id'], $admin['name']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
    return acct_result(true, 'Your password was saved. You can now log in.');
}

/** A logged-in administrator replaces their temporary password. */
function change_own_password(PDO $pdo, int $adminId, string $current, string $new, string $confirm): array
{
    $stmt = $pdo->prepare('SELECT email, password_hash FROM admins WHERE id = ?');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        return acct_fail('The current password is not correct.');
    }
    if ($new !== $confirm) {
        return acct_fail('The two new passwords do not match.');
    }
    if ($new === $current) {
        return acct_fail('The new password must be different from the temporary one.');
    }
    if ($err = acct_password_error($new, $row['email'])) {
        return acct_fail($err);
    }
    $pdo->prepare('UPDATE admins SET password_hash = ?, must_change_password = false WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $adminId]);
    log_activity('admin_password_set', 'admin', $adminId, 'changed own temporary password');
    return acct_result(true, 'Your password was changed.');
}
