<?php
/**
 * AGROVISE Helper Functions
 * Common utility functions for the agriculture products website
 */

/* ============================================================
   SECURITY BOOTSTRAP  (runs on every page — admin and public)
   ============================================================ */

// --- Hardened session cookie ---
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,     // only sent over HTTPS when available
        'httponly' => true,        // unreadable to JavaScript (blocks cookie theft via XSS)
        'samesite' => 'Lax',       // blocks cross-site request forgery via the cookie
    ]);
    ini_set('session.use_strict_mode', '1');   // reject attacker-fixed session IDs
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// --- Security response headers (skipped for CLI) ---
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');      // stop MIME-sniffing
    header('X-Frame-Options: SAMEORIGIN');          // stop clickjacking
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header_remove('X-Powered-By');                  // hide PHP version
}

/**
 * CSRF token for this session (generated once, reused).
 * @return string
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden CSRF field for forms. Echo inside every <form method="POST">.
 * @return string
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify the CSRF token on a state-changing request; die on mismatch.
 * Called automatically for every POST (see below).
 */
function verifyCsrf() {
    $sent = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        die('Security check failed (invalid or expired form token). Please go back, reload the page, and try again.');
    }
}

/**
 * Safe DB-error handler: logs the real message, shows a generic one.
 * Replaces exposing $e->getMessage() to the browser.
 * @return string
 */
function dbError($e) {
    error_log('[AGROVISE DB] ' . $e->getMessage());
    return 'A database error occurred. Please try again or contact the administrator.';
}

// --- Auto-verify CSRF on every POST from a logged-in admin session ---
// (login.php verifies its own token before this can run; public site has no POST forms)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SESSION['admin_id'])
    && basename($_SERVER['PHP_SELF'] ?? '') !== 'login.php') {
    verifyCsrf();
}

/**
 * Get products by category
 * @param string $category
 * @return array
 */
function getProductsByCategory($category = null) {
    $conn = getDBConnection();
    
    if ($category) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE category = ? AND is_published = TRUE ORDER BY created_at DESC");
        $stmt->execute([$category]);
    } else {
        $stmt = $conn->query("SELECT * FROM products WHERE is_published = TRUE ORDER BY created_at DESC");
    }
    
    return $stmt->fetchAll();
}

/**
 * Get single product by ID
 * @param int $id
 * @return array|null
 */
function getProductById($id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get featured products (latest 3 from each category)
 * @return array
 */
function getFeaturedProducts() {
    $conn = getDBConnection();
    $categories = ['insecticides', 'weedicides', 'fungicides', 'granulars', 'micronutrients'];
    $featured = [];
    
    foreach ($categories as $cat) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE category = ? AND is_published = TRUE ORDER BY created_at DESC LIMIT 3");
        $stmt->execute([$cat]);
        $featured[$cat] = $stmt->fetchAll();
    }
    
    return $featured;
}

/**
 * Check if admin is logged in
 * @return bool
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    loadAdminAccess();
}

/* ============================================================
   Role-based access control
   Accounts live in `admins`: role 'admin' = full access;
   role 'user' = only the modules listed in `permissions` (JSON).
   ============================================================ */

/**
 * Grantable panel modules (key => label).
 * 'employees' is deliberately NOT listed: managing employees and
 * login accounts always requires the admin role.
 * @return array
 */
function adminModules() {
    return [
        'products'   => 'Products & Publishing',
        'purchasing' => 'Purchasing / Stock',
        'vendors'    => 'Vendors',
        'packing'    => 'Packing',
        'invoices'   => 'Invoices',
        'policies'   => 'Sales Policies',
        'payments'   => 'Payment Receipts',
        'clients'    => 'Clients',
        'banking'    => 'Banking & Transactions',
        'ledgers'    => 'Ledgers & Reports',
        'vehicles'   => 'Vehicles / Fleet',
        'expenses'   => 'Expense Approvals (Accounts)',
        'traffic'    => 'Website Traffic',
    ];
}

/**
 * Load the logged-in account's role/permissions into the session (once).
 * Forces logout if the account row no longer exists.
 */
function loadAdminAccess() {
    if (!isAdminLoggedIn() || isset($_SESSION['admin_role'])) {
        return;
    }
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT role, permissions FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $acc = $stmt->fetch();

    if (!$acc) {
        // Account deleted while session alive — invalidate the session.
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $_SESSION['admin_role'] = $acc['role'] ?: 'admin';
    $perms = json_decode($acc['permissions'] ?? '', true);
    $_SESSION['admin_permissions'] = is_array($perms) ? $perms : [];
}

/**
 * Is the logged-in account a full admin?
 * @return bool
 */
function isSuperAdmin() {
    if (!isAdminLoggedIn()) return false;
    loadAdminAccess();
    return ($_SESSION['admin_role'] ?? 'admin') === 'admin';
}

/**
 * Can the logged-in account access a module?
 * Admins can access everything; 'employees' is admin-only.
 * @param string $module
 * @return bool
 */
function hasPermission($module) {
    if (!isAdminLoggedIn()) return false;
    if (isSuperAdmin()) return true;
    if ($module === 'employees') return false;
    return in_array($module, $_SESSION['admin_permissions'] ?? [], true);
}

/**
 * Require access to a module; otherwise flash + bounce to dashboard.
 * @param string $module
 */
function requirePermission($module) {
    requireAdminLogin();
    if (!hasPermission($module)) {
        setFlashMessage('error', 'You do not have permission to access that section. Contact an administrator.');
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Where an account should land after login.
 * Admins and users with at least one granted module go to the dashboard;
 * a user with no modules (e.g. a sales officer) lands on their profile.
 * @return string
 */
function landingPage() {
    if (isSuperAdmin()) return 'dashboard.php';
    $perms = $_SESSION['admin_permissions'] ?? [];
    return empty($perms) ? 'my-profile.php' : 'dashboard.php';
}

/**
 * The employees.id linked to the logged-in account (null if none).
 * @return int|null
 */
function currentEmployeeId() {
    if (!isAdminLoggedIn()) return null;
    if (!array_key_exists('admin_employee_id', $_SESSION)) {
        $conn = getDBConnection();
        $st = $conn->prepare("SELECT employee_id FROM admins WHERE id = ?");
        $st->execute([$_SESSION['admin_id']]);
        $val = $st->fetchColumn();
        $_SESSION['admin_employee_id'] = $val ? intval($val) : null;
    }
    return $_SESSION['admin_employee_id'];
}

/**
 * Can this account approve/decline expense claims?
 * @return bool
 */
function canApproveExpenses() {
    return isSuperAdmin() || hasPermission('expenses');
}

/**
 * Generic validated image upload into a subfolder of uploads/.
 * @param array $file entry from $_FILES
 * @param string $subdir e.g. 'expenses' or 'meters'
 * @param string $prefix filename prefix
 * @return string|false stored filename or false
 */
function uploadImageFile($file, $subdir, $prefix) {
    // Must be a genuine uploaded file with no transport error
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])
        || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return false;
    }

    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        return false;
    }

    // Verify the REAL content — never trust the client-sent MIME or filename.
    // getimagesize() confirms it is actually a decodable image and gives the true type.
    $info = @getimagesize($file['tmp_name']);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if ($info === false || !isset($allowed[$info[2]])) {
        return false;   // not a real image (e.g. a PHP script renamed to .jpg)
    }
    $extension = $allowed[$info[2]];   // extension forced from verified type, not user input

    $uploadDir = __DIR__ . '/../uploads/' . $subdir . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = $prefix . bin2hex(random_bytes(8)) . '.' . $extension;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        @chmod($uploadDir . $filename, 0644);   // never executable
        return $filename;
    }
    return false;
}

/** Expense bill image -> uploads/expenses/ */
function uploadBillImage($file)  { return uploadImageFile($file, 'expenses', 'bill_'); }
/** Odometer photo -> uploads/meters/ */
function uploadMeterImage($file) { return uploadImageFile($file, 'meters', 'meter_'); }

/**
 * Notify every account allowed to approve expenses (admins + 'expenses' users).
 */
function notifyExpenseApprovers($conn, $message) {
    $ins = $conn->prepare("INSERT INTO notifications (admin_id, message) VALUES (?, ?)");
    foreach ($conn->query("SELECT id, role, permissions FROM admins")->fetchAll() as $a) {
        $isApprover = $a['role'] === 'admin';
        if (!$isApprover && !empty($a['permissions'])) {
            $p = json_decode($a['permissions'], true);
            $isApprover = is_array($p) && in_array('expenses', $p, true);
        }
        if ($isApprover) $ins->execute([$a['id'], $message]);
    }
}

/**
 * Notify the login account linked to an employee (no-op when none exists).
 */
function notifyEmployeeAccount($conn, $employee_id, $message) {
    $st = $conn->prepare("SELECT id FROM admins WHERE employee_id = ?");
    $st->execute([$employee_id]);
    if ($acc = $st->fetch()) {
        $conn->prepare("INSERT INTO notifications (admin_id, message) VALUES (?, ?)")
             ->execute([$acc['id'], $message]);
    }
}

/**
 * Unread notification count for the logged-in account (used by the sidebar).
 * @return int
 */
function unreadNotificationCount($conn) {
    if (!isAdminLoggedIn()) return 0;
    try {
        $st = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE admin_id = ? AND is_read = 0");
        $st->execute([$_SESSION['admin_id']]);
        return intval($st->fetchColumn());
    } catch (PDOException $e) {
        return 0;
    }
}

/* ============================================================
   Messaging (invoice / vendor-payment notifications)
   The system composes messages and queues them in outbound_messages;
   they are sent from the authorized company number on messaging.php.
   ============================================================ */

/** Read a settings value (null when unset). */
function getSetting($conn, $name) {
    try {
        $st = $conn->prepare("SELECT value FROM settings WHERE name = ?");
        $st->execute([$name]);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    } catch (PDOException $e) {
        return null;
    }
}

/** Write a settings value (insert or update). */
function setSetting($conn, $name, $value) {
    $conn->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")
         ->execute([$name, $value]);
}

/** Is messaging switched on with an authorized sender number? */
function messagingEnabled($conn) {
    return getSetting($conn, 'messaging_enabled') === '1'
        && trim((string)getSetting($conn, 'messaging_number')) !== '';
}

/** Local Pakistani number -> international +92 form for SMS/WhatsApp APIs. */
function intlPhonePK($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (strpos($digits, '0') === 0) $digits = '92' . substr($digits, 1);
    return '+' . $digits;
}

/** Is the Android SMS gateway configured for the selected app type? */
function smsGatewayConfigured($conn) {
    $url = trim((string)getSetting($conn, 'sms_gateway_url'));
    if ($url === '') return false;
    if (getSetting($conn, 'sms_gateway_type') === 'traccar') {
        // Traccar authenticates with a single API key (stored in the password slot)
        return trim((string)getSetting($conn, 'sms_gateway_pass')) !== '';
    }
    return trim((string)getSetting($conn, 'sms_gateway_user')) !== '';
}

/**
 * Send one SMS through the Android gateway app. Two supported apps:
 *   traccar - Traccar SMS Gateway: POST {url}/ , header "Authorization: <key>",
 *             body {"to": "+92...", "message": ...}
 *   smsgate - SMS Gate (sms-gate.app): POST {url}/message , Basic auth,
 *             body {"message": ..., "phoneNumbers": ["+92..."]}
 * Short timeouts so an offline phone can never hang the invoice flow.
 * @return array [bool ok, string info]
 */
function sendSmsViaGateway($conn, $phone, $body) {
    if (!smsGatewayConfigured($conn)) return [false, 'SMS gateway is not configured.'];

    // Keep the text inside the basic GSM alphabet: fancy punctuation forces
    // UCS-2 encoding, which some phones/carriers reject with "generic failure".
    $body = strtr($body, [
        "\u{2014}" => '-', "\u{2013}" => '-',            // em/en dash
        "\u{2018}" => "'", "\u{2019}" => "'",            // curly single quotes
        "\u{201C}" => '"', "\u{201D}" => '"',            // curly double quotes
        "\u{00A0}" => ' ', "\u{2026}" => '...',          // nbsp, ellipsis
        "\u{00B7}" => '-', "\u{2022}" => '-',            // middots/bullets
    ]);

    $type = getSetting($conn, 'sms_gateway_type') === 'traccar' ? 'traccar' : 'smsgate';
    $url  = rtrim(trim((string)getSetting($conn, 'sms_gateway_url')), '/');
    $user = trim((string)getSetting($conn, 'sms_gateway_user'));
    $pass = (string)getSetting($conn, 'sms_gateway_pass');

    $opts = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
    ];

    if ($type === 'traccar') {
        $opts[CURLOPT_POSTFIELDS] = json_encode(['to' => intlPhonePK($phone), 'message' => $body]);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json', 'Authorization: ' . trim($pass)];
    } else {
        if (substr($url, -8) !== '/message') $url .= '/message';
        $opts[CURLOPT_POSTFIELDS] = json_encode(['message' => $body, 'phoneNumbers' => [intlPhonePK($phone)]]);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        $opts[CURLOPT_USERPWD] = $user . ':' . $pass;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return [false, 'Gateway unreachable: ' . $err];
    if ($code >= 200 && $code < 300) return [true, 'Accepted (HTTP ' . $code . ')'];
    return [false, 'Gateway rejected (HTTP ' . $code . '): ' . substr((string)$resp, 0, 200)];
}

/**
 * Queue a message for sending. Silently skips blank phones so business
 * flows (invoicing, payments) never fail because of messaging.
 * When SMS auto-send is on and the gateway is configured, the SMS goes out
 * over the SIM network immediately; failures simply stay PENDING for retry.
 */
function queueOutboundMessage($conn, $type, $name, $phone, $body, $context = null) {
    $phone = trim((string)$phone);
    if ($phone === '') return false;
    try {
        $conn->prepare("INSERT INTO outbound_messages (recipient_type, recipient_name, phone, body, context) VALUES (?, ?, ?, ?, ?)")
             ->execute([$type, $name, $phone, $body, $context]);
        $msgId = $conn->lastInsertId();

        if (getSetting($conn, 'sms_auto_send') === '1' && smsGatewayConfigured($conn)) {
            list($ok, ) = sendSmsViaGateway($conn, $phone, $body);
            if ($ok) {
                $conn->prepare("UPDATE outbound_messages SET status = 'SENT', sent_via = 'SMS', sent_at = NOW() WHERE id = ?")
                     ->execute([$msgId]);
            }
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * WhatsApp click-to-send link for a phone + prefilled message.
 * Local numbers starting with 0 are converted to Pakistan's +92.
 */
function waSendLink($phone, $body) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (strpos($digits, '0') === 0) $digits = '92' . substr($digits, 1);
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($body);
}

/** Shared sign-off for every system message. */
function messageSignature() {
    return "\n\nRegards,\nAgrovise Team";
}

/* ============================================================
   Numeric format normalizers — dashes are inserted automatically
   server-side, so "03001234567" becomes "0300-1234567" even when
   the user (or a JS-less browser) omits them.
   ============================================================ */

/** 11 digits -> XXXX-XXXXXXX; null when the digit count is wrong. */
function normalizePhonePK($raw) {
    $d = preg_replace('/\D+/', '', (string)$raw);
    return strlen($d) === 11 ? substr($d, 0, 4) . '-' . substr($d, 4) : null;
}

/** 13 digits -> XXXXX-XXXXXXX-X; null when the digit count is wrong. */
function normalizeCNIC($raw) {
    $d = preg_replace('/\D+/', '', (string)$raw);
    return strlen($d) === 13 ? substr($d, 0, 5) . '-' . substr($d, 5, 7) . '-' . substr($d, 12) : null;
}

/**
 * Random readable password (no confusable characters).
 * @param int $length
 * @return string
 */
function generateRandomPassword($length = 10) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $pass = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $pass;
}

/**
 * Upload product image
 * @param array $file
 * @return string|false
 */
function uploadProductImage($file) {
    // Content-verified upload into uploads/ (real type check, forced extension,
    // random name) — shares the same hardened path as bill/meter uploads.
    return uploadImageFile($file, '', 'product_');
}

/**
 * Delete product image
 * @param string $imageName
 * @return bool
 */
function deleteProductImage($imageName) {
    if (empty($imageName) || str_starts_with($imageName, 'default') || $imageName === 'placeholder-product.jpg') {
        return false;
    }

    $paths = [
        '../uploads/' . $imageName,
        '../assets/images/products/' . $imageName,
        '../assets/images/' . $imageName
    ];

    foreach ($paths as $fullPath) {
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
    }
    return false;
}

/**
 * Get product image path with fallback validation
 * @param string $imageName
 * @param string $baseDir either '.' or '..'
 * @return string
 */
function getProductImagePath($imageName, $baseDir = '..') {
    if (empty($imageName)) {
        return $baseDir . '/assets/images/placeholder-product.jpg';
    }
    
    // Check uploads/
    if (file_exists($baseDir . '/uploads/' . basename($imageName))) {
        return $baseDir . '/uploads/' . basename($imageName);
    }
    if (file_exists($baseDir . '/uploads/' . $imageName)) {
        return $baseDir . '/uploads/' . $imageName;
    }

    // Check old style
    if (file_exists($baseDir . '/assets/images/' . $imageName)) {
        return $baseDir . '/assets/images/' . $imageName;
    }

    if (str_starts_with($imageName, 'products/') && file_exists($baseDir . '/assets/images/' . $imageName)) {
        return $baseDir . '/assets/images/' . $imageName;
    }
    
    return $baseDir . '/assets/images/placeholder-product.jpg';
}

/**
 * Get category display name
 * @param string $category
 * @return string
 */
function getCategoryName($category) {
    $names = [
        'insecticides' => 'Insecticides',
        'weedicides' => 'Weedicides',
        'fungicides' => 'Fungicides',
        'granulars' => 'Granulars',
        'micronutrients' => 'Micronutrients & Fertilizers'
    ];
    return $names[$category] ?? ucfirst($category);
}

/**
 * Get category description
 * @param string $category
 * @return string
 */
function getCategoryDescription($category) {
    $descriptions = [
        'insecticides' => 'Protect your crops from harmful pests with our range of effective insecticides.',
        'weedicides' => 'Keep your fields weed-free with our selective and non-selective herbicides.',
        'fungicides' => 'Prevent and control fungal diseases with our comprehensive fungicide solutions.',
        'granulars' => 'Enhance soil health and provide sustained nutrition with our granular products.',
        'micronutrients' => 'Ensure optimal plant growth with essential micronutrients and fertilizers.'
    ];
    return $descriptions[$category] ?? '';
}

/**
 * Display flash message
 * @param string $type
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sanitize input
 * @param string $input
 * @return string
 */
function sanitize($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
