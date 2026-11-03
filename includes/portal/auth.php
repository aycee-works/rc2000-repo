<?php
/**
 * includes/auth.php
 * Admin login, logout, session handling and access control.
 *
 *  - Pages:       call require_admin() at the top.
 *  - JSON APIs:   call require_admin_api() at the top.
 *  - Role check:  call require_super_admin() where needed.
 * Hiding a menu item is never enough; every page/endpoint checks on the server.
 */

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // Each portal has its OWN session cookie, so being logged in (or out) on the Admin Portal
    // never affects the Super Admin Portal in the same browser, and the other way round.
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $isSuper = strpos($scriptName, '/superadmin/') !== false
        || strpos($scriptName, '/super_admin/') !== false;
    session_name($isSuper ? 'rc2000_super' : 'rc2000_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** The logged-in admin row (id, name, email, role) or null. */
function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return [
        'id'    => (int) $_SESSION['admin_id'],
        'name'  => $_SESSION['admin_name'] ?? '',
        'email' => $_SESSION['admin_email'] ?? '',
        'role'  => $_SESSION['admin_role'] ?? 'admin',
    ];
}

/**
 * Do the Phase 2 security columns exist (must_change_password, sessions_valid_after)?
 * They come from database/super-admin.sql. Until that script is run the app keeps working without them.
 */
function admin_security_columns_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $ready = (int) db()->query(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_name = 'admins' AND column_name IN ('must_change_password', 'sessions_valid_after')"
            )->fetchColumn() === 2;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/**
 * Is the session valid? Also enforces idle timeout and re-checks the database so a
 * deactivated admin is kicked out immediately, and so "sign out of active sessions"
 * (sessions_valid_after) takes effect on the next click.
 */
function admin_session_is_valid(): bool
{
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    $last = $_SESSION['last_activity'] ?? 0;
    if (time() - $last > SESSION_IDLE_SECONDS) {
        return false;
    }
    try {
        $ready = admin_security_columns_ready();
        $stmt = db()->prepare($ready
            ? 'SELECT role, is_active, must_change_password, EXTRACT(EPOCH FROM sessions_valid_after) AS valid_after FROM admins WHERE id = ?'
            : 'SELECT role, is_active FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['is_active'] !== 1) {
            return false;
        }
        if ($ready) {
            if ($row['valid_after'] !== null && (float) ($_SESSION['login_at'] ?? 0) <= (float) $row['valid_after']) {
                return false;
            }
            $_SESSION['must_change_password'] = (bool) $row['must_change_password'];
        }
        $_SESSION['admin_role'] = $row['role'];     // always the CURRENT role from the database
    } catch (Throwable $e) {
        error_log('Session check failed: ' . $e->getMessage());
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/** Valid session AND the account is an ordinary administrator (Admin Portal only). */
function admin_portal_session_is_valid(): bool
{
    return admin_session_is_valid() && ($_SESSION['admin_role'] ?? '') === 'admin';
}

/** Protect an HTML page. Redirects to the login page when not logged in. */
function require_admin(): void
{
    if (!admin_portal_session_is_valid()) {
        $expired = !empty($_SESSION['admin_id']);
        admin_logout_session();
        start_secure_session();
        if ($expired) {
            flash('info', 'Your session has ended. Please log in again.');
        }
        redirect('admin/login.php');
    }
    // A temporary password must be replaced before anything else can be used
    if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'change-password.php') {
        redirect('admin/change-password.php');
    }
}

/** Protect a JSON endpoint. Returns 401 JSON instead of redirecting. */
function require_admin_api(): void
{
    if (!admin_portal_session_is_valid()) {
        json_response(false, 'Authentication required.', null, 401);
    }
    if (!empty($_SESSION['must_change_password'])) {
        json_response(false, 'You must change your temporary password first.', null, 403);
    }
}

function require_super_admin(bool $api = false): void
{
    $admin = current_admin();
    if (!$admin || $admin['role'] !== 'super_admin') {
        if ($api) {
            json_response(false, 'You do not have permission to do that.', null, 403);
        }
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
}

/**
 * Is the CURRENT session a valid Super Admin session? The role is re-read from the
 * database on every request, so demoting or deactivating an account takes effect at once.
 */
function superadmin_session_is_valid(): bool
{
    if (!admin_session_is_valid()) {
        return false;
    }
    try {
        $stmt = db()->prepare('SELECT role FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        return $stmt->fetchColumn() === 'super_admin';
    } catch (Throwable $e) {
        error_log('Super admin check failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Protect a Super Admin page (or, with $api = true, a JSON endpoint).
 * A logged-in ordinary admin is sent to the Super Admin login but is NOT logged out of the Admin Portal.
 */
function require_superadmin_portal(bool $api = false): void
{
    if (superadmin_session_is_valid()) {
        return;
    }
    if ($api) {
        $loggedIn = !empty($_SESSION['admin_id']);
        json_response(false, $loggedIn ? 'You do not have permission to do that.' : 'Authentication required.', null, $loggedIn ? 403 : 401);
    }
    if (!admin_session_is_valid()) {
        $expired = !empty($_SESSION['admin_id']);
        admin_logout_session();
        start_secure_session();
        if ($expired) {
            flash('info', 'Your session has ended. Please log in again.');
        }
    }
    redirect('superadmin/login.php');
}

/** Too many recent failures for this email or IP? */
function login_is_throttled(string $email): bool
{
    $sql = 'SELECT COUNT(*) FROM login_attempts
            WHERE (email = ? OR ip_address = ?)
              AND attempted_at > (NOW() - INTERVAL \'' . (int) LOGIN_WINDOW_MINUTES . ' minutes\')';
    $stmt = db()->prepare($sql);
    $stmt->execute([$email, client_ip()]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function login_record_failure(string $email): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
    $stmt->execute([$email, client_ip()]);
}

/**
 * Try to log in. Returns null on success, or an error message string.
 * The same generic message is used for "unknown email" and "wrong password"
 * so attackers cannot discover which emails exist.
 * $requiredRole: leave null for the Admin Portal (behaves as before); the Super Admin
 * Portal passes 'super_admin', and any other role gets the same generic failure.
 */
function admin_login(string $email, string $password, ?string $requiredRole = null): ?string
{
    $email = mb_strtolower(trim($email));
    if ($email === '' || $password === '') {
        return 'Please enter your email and password.';
    }

    if (login_is_throttled($email)) {
        return 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    }

    $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    // Always run password_verify (even for unknown emails) so timing is similar
    $hash  = $admin['password_hash'] ?? '$2y$10$5OuiVO.ZqiSe9FqYo7bacubmg.f4Z59wI/2BOLfKYT/SHFbT.0Cn6';
    $valid = password_verify($password, $hash);

    $roleOk = $requiredRole === null || ($admin && $admin['role'] === $requiredRole);

    if (!$admin || !$valid || !$roleOk || (int) $admin['is_active'] !== 1) {
        login_record_failure($email);
        return 'Incorrect email or password.';
    }

    // Success: new session ID prevents session fixation
    session_regenerate_id(true);
    $_SESSION['admin_id']      = (int) $admin['id'];
    $_SESSION['admin_name']    = $admin['name'];
    $_SESSION['admin_email']   = $admin['email'];
    $_SESSION['admin_role']    = $admin['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_at']      = microtime(true);
    unset($_SESSION['csrf_token']); // fresh token for the new session
    csrf_token();

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }
    db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
    db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);

    log_activity('login', 'admin', (int) $admin['id']);
    return null;
}

/** Destroy the session data and cookie. */
function admin_logout_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

