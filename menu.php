<?php
/**
 * Bettavaro public menu include
 *
 * Requirements achieved:
 * - Consistent login state on desktop + mobile
 * - Uses $_SESSION['user'] as primary session container
 * - Falls back to legacy session keys when needed
 * - Login/logout keep redirect back to current public page
 * - Safe enough to include on every public page
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!headers_sent()) {
        session_start();
    }
}

if (!function_exists('bv_h')) {
    function bv_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bv_server_value')) {
    function bv_server_value($key, $default = '')
    {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }
}

if (!function_exists('bv_normalize_role')) {
    function bv_normalize_role($role)
    {
        $role = strtolower(trim((string) $role));
        if ($role === 'super_admin') {
            return 'admin';
        }
        if (in_array($role, array('user', 'seller', 'admin'), true)) {
            return $role;
        }
        return 'user';
    }
}

if (!function_exists('bv_current_request_uri')) {
    function bv_current_request_uri()
    {
        $uri = (string) bv_server_value('REQUEST_URI', '/');
        if ($uri === '' || strpos($uri, '://') !== false || strpos($uri, "\r") !== false || strpos($uri, "\n") !== false) {
            return '/';
        }
        if ($uri[0] !== '/') {
            $uri = '/' . ltrim($uri, '/');
        }
        return $uri;
    }
}

if (!function_exists('bv_safe_redirect_target')) {
    function bv_safe_redirect_target($fallback = '/')
    {
        $candidate = bv_current_request_uri();
        if ($candidate === '' || strpos($candidate, '://') !== false || strpos($candidate, '//') === 0) {
            return $fallback;
        }
        return $candidate;
    }
}

if (!function_exists('bv_build_auth_url')) {
    function bv_build_auth_url($path, $redirectTarget = null)
    {
        $target = $redirectTarget;
        if ($target === null || $target === '') {
            $target = bv_safe_redirect_target('/');
        }
        return $path . '?redirect=' . rawurlencode($target);
    }
}

if (!function_exists('bv_boot_public_auth')) {
    function bv_boot_public_auth()
    {
        static $booted = false;
        static $user = null;

        if ($booted) {
            return $user;
        }

        $booted = true;
        $user = null;

        if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            $source = $_SESSION['user'];

            $firstName = isset($source['first_name']) ? trim((string) $source['first_name']) : '';
            $lastName = isset($source['last_name']) ? trim((string) $source['last_name']) : '';

            $displayName = '';
            if (!empty($source['display_name'])) {
                $displayName = trim((string) $source['display_name']);
            }
            if ($displayName === '') {
                $displayName = trim($firstName . ' ' . $lastName);
            }
            if ($displayName === '' && !empty($source['name'])) {
                $displayName = trim((string) $source['name']);
            }
            if ($displayName === '' && !empty($source['email'])) {
                $displayName = trim((string) $source['email']);
            }

            $user = array(
                'id'           => isset($source['id']) ? (int) $source['id'] : 0,
                'email'        => isset($source['email']) ? trim((string) $source['email']) : '',
                'role'         => bv_normalize_role(isset($source['role']) ? $source['role'] : 'user'),
                'display_name' => $displayName,
                'is_logged_in' => !empty($source['id']) || !empty($source['email']),
            );
        }

        if ($user === null || empty($user['is_logged_in'])) {
            $id = 0;
            foreach (array('user_id', 'member_id', 'customer_id', 'id') as $key) {
                if (!empty($_SESSION[$key])) {
                    $id = (int) $_SESSION[$key];
                    break;
                }
            }

            $email = '';
            foreach (array('user_email', 'member_email', 'email') as $key) {
                if (!empty($_SESSION[$key])) {
                    $email = trim((string) $_SESSION[$key]);
                    break;
                }
            }

            $role = 'user';
            foreach (array('user_role', 'member_role', 'role') as $key) {
                if (!empty($_SESSION[$key])) {
                    $role = bv_normalize_role($_SESSION[$key]);
                    break;
                }
            }

            $displayName = '';
            foreach (array('display_name', 'user_name', 'member_name', 'name') as $key) {
                if (!empty($_SESSION[$key])) {
                    $displayName = trim((string) $_SESSION[$key]);
                    break;
                }
            }
            if ($displayName === '' && $email !== '') {
                $displayName = $email;
            }

            if ($id > 0 || $email !== '') {
                $user = array(
                    'id'           => $id,
                    'email'        => $email,
                    'role'         => $role,
                    'display_name' => $displayName,
                    'is_logged_in' => true,
                );

                $_SESSION['user'] = $user;
            }
        }

        if ($user === null) {
            $user = array(
                'id'           => 0,
                'email'        => '',
                'role'         => 'guest',
                'display_name' => 'Guest',
                'is_logged_in' => false,
            );
        }

        return $user;
    }
}

if (!function_exists('bv_current_user')) {
    function bv_current_user()
    {
        return bv_boot_public_auth();
    }
}

if (!function_exists('bv_is_logged_in')) {
    function bv_is_logged_in()
    {
        $user = bv_current_user();
        return !empty($user['is_logged_in']);
    }
}

if (!function_exists('bv_login_url')) {
    function bv_login_url()
    {
        return bv_build_auth_url('/login.php');
    }
}

if (!function_exists('bv_logout_url')) {
    function bv_logout_url()
    {
        return bv_build_auth_url('/logout.php');
    }
}

if (!function_exists('bv_account_url')) {
    function bv_account_url()
    {
        return '/member/index.php';
    }
}

if (!function_exists('bv_is_current_path')) {
    function bv_is_current_path($path)
    {
        $currentPath = parse_url(bv_current_request_uri(), PHP_URL_PATH);
        if (!is_string($currentPath) || $currentPath === '') {
            $currentPath = '/';
        }
        return rtrim($currentPath, '/') === rtrim((string) $path, '/');
    }
}

$bvUser = bv_current_user();
$bvIsLoggedIn = bv_is_logged_in();
$bvRoleLabel = strtoupper((string) $bvUser['role']);
?>
<header class="bv-topbar">
    <div class="bv-container">
        <div class="bv-nav-row">
            <a class="bv-brand" href="/" aria-label="Bettavaro Home">
                <img src="../assets/img/bettavarologo.png" alt="Bettavaro Logo">
                <span class="bv-brand-copy">
                    <span class="bv-brand-name">Bettavaro</span>
                    <span class="bv-brand-sub">Premium Betta Marketplace</span>
                </span>
            </a>

            <nav class="bv-nav-links" aria-label="Primary navigation">
                <a class="bv-nav-link<?php echo bv_is_current_path('/') ? ' is-active' : ''; ?>" href="/">Home</a>
                <a class="bv-nav-link<?php echo bv_is_current_path('/listings.php') ? ' is-active' : ''; ?>" href="/listings.php">Listings</a>
                <a class="bv-nav-link<?php echo bv_is_current_path('/seller/apply.php') ? ' is-active' : ''; ?>" href="/seller/apply.php">Become a Seller</a>
            </nav>

            <div class="bv-auth-links" aria-label="Account navigation">
                <?php if ($bvIsLoggedIn): ?>
                    <div class="bv-user-chip" title="Signed in">
                        <span class="bv-user-dot" aria-hidden="true"></span>
                        <span class="bv-user-meta">
                            <span class="bv-user-name"><?php echo bv_h($bvUser['display_name']); ?></span>
                            <span class="bv-user-role"><?php echo bv_h($bvRoleLabel); ?></span>
                        </span>
                    </div>
                    <a class="bv-auth-link" href="<?php echo bv_h(bv_account_url()); ?>">My Account</a>
                    <a class="bv-auth-button" href="<?php echo bv_h(bv_logout_url()); ?>">Logout</a>
                <?php else: ?>
                    <a class="bv-auth-link" href="/seller/apply.php">Sell on Bettavaro</a>
                    <a class="bv-auth-button" href="<?php echo bv_h(bv_login_url()); ?>">Sign In</a>
                <?php endif; ?>
            </div>

            <button type="button" class="bv-mobile-toggle" data-bv-mobile-toggle="1" aria-expanded="false" aria-controls="bv-mobile-panel">
                ☰
            </button>
        </div>

        <div id="bv-mobile-panel" class="bv-mobile-panel" data-bv-mobile-panel="1">
            <div class="bv-mobile-stack">
                <?php if ($bvIsLoggedIn): ?>
                    <div class="bv-mobile-user">
                        <strong><?php echo bv_h($bvUser['display_name']); ?></strong>
                        <small><?php echo bv_h($bvRoleLabel); ?> · Signed in</small>
                    </div>
                <?php endif; ?>

                <nav class="bv-mobile-actions" aria-label="Mobile navigation">
                    <a class="bv-mobile-link<?php echo bv_is_current_path('/') ? ' is-active' : ''; ?>" href="/">Home</a>
                    <a class="bv-mobile-link<?php echo bv_is_current_path('/listings.php') ? ' is-active' : ''; ?>" href="/listings.php">Listings</a>
                    <a class="bv-mobile-link<?php echo bv_is_current_path('/seller/apply.php') ? ' is-active' : ''; ?>" href="/seller/apply.php">Become a Seller</a>

                    <?php if ($bvIsLoggedIn): ?>
                        <a class="bv-mobile-link" href="<?php echo bv_h(bv_account_url()); ?>">My Account</a>
                        <a class="bv-auth-button" href="<?php echo bv_h(bv_logout_url()); ?>">Logout</a>
                    <?php else: ?>
                        <a class="bv-mobile-link" href="/seller/apply.php">Sell on Bettavaro</a>
                        <a class="bv-auth-button" href="<?php echo bv_h(bv_login_url()); ?>">Sign In</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>
</header>
<script>
(function () {
    var toggle = document.querySelector('[data-bv-mobile-toggle="1"]');
    var panel = document.querySelector('[data-bv-mobile-panel="1"]');

    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', function () {
        var isOpen = panel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
})();
</script>
