<?php
/**
 * AGROVISE - Public site traffic tracking
 *
 * Records one row in `site_visits` per public page load and one row in
 * `product_views` whenever a visitor opens a product's detail card.
 * Everything here is failure-proofed: a tracking error must never take the
 * public site down, so every DB call is wrapped and returns silently.
 *
 * Requires includes/db.php + includes/functions.php (session already started).
 */

/** Long-lived anonymous visitor id (cookie), so repeat visits group together. */
function visitorId() {
    if (!empty($_COOKIE['av_vid']) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE['av_vid'])) {
        return $_COOKIE['av_vid'];
    }
    $vid = bin2hex(random_bytes(16));
    if (!headers_sent()) {
        setcookie('av_vid', $vid, [
            'expires'  => time() + 31536000,   // 1 year
            'path'     => '/',
            'httponly' => true,                // server-side only; JS never needs it
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE['av_vid'] = $vid;   // usable within this same request
    return $vid;
}

/** Crawlers, scrapers and uptime pingers must not pollute the numbers. */
function isBotAgent($ua) {
    return (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|monitor|curl|wget|python-requests|phantomjs/i', (string) $ua);
}

/** Desktop / Mobile / Tablet from the user agent. */
function deviceFromAgent($ua) {
    if (preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', (string) $ua)) return 'Tablet';
    if (preg_match('/Mobile|iPhone|iPod|Android|BlackBerry|Opera Mini|IEMobile/i', (string) $ua)) return 'Mobile';
    return 'Desktop';
}

/** Rough browser family from the user agent (order matters — Edge/Chrome both say "Chrome"). */
function browserFromAgent($ua) {
    $ua = (string) $ua;
    foreach ([
        'Edge'    => '/Edg[ei]?\//i',
        'Opera'   => '/OPR\/|Opera/i',
        'Samsung' => '/SamsungBrowser/i',
        'Chrome'  => '/Chrome|CriOS/i',
        'Firefox' => '/Firefox|FxiOS/i',
        'Safari'  => '/Safari/i',
        'IE'      => '/MSIE|Trident/i',
    ] as $name => $re) {
        if (preg_match($re, $ua)) return $name;
    }
    return 'Other';
}

/** Should this request be counted at all? Bots and logged-in staff are skipped. */
function trackingEnabled() {
    if (PHP_SAPI === 'cli') return false;
    if (isAdminLoggedIn()) return false;                    // staff browsing isn't traffic
    return !isBotAgent($_SERVER['HTTP_USER_AGENT'] ?? '');
}

/**
 * Record one public page view.
 * @param PDO    $conn
 * @param string $pageType 'home' | 'category' | other short slug
 * @param string|null $category product category slug when the page is a category page
 */
function trackPageView($conn, $pageType, $category = null) {
    if (!trackingEnabled()) return;
    try {
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $stmt = $conn->prepare("
            INSERT INTO site_visits
                (visitor_id, session_id, page_type, page_url, category, referrer, ip_address, device, browser, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            visitorId(),
            session_id(),
            $pageType,
            substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
            $category,
            substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255) ?: null,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            deviceFromAgent($ua),
            browserFromAgent($ua),
            $ua,
        ]);
    } catch (PDOException $e) {
        error_log('[AGROVISE TRACK] ' . $e->getMessage());   // never surface to the visitor
    }
}

/**
 * Record interest in a single product (fired when a visitor opens its detail card).
 * @param PDO $conn
 * @param int $productId
 * @param string $source where the click happened, e.g. 'category:insecticides'
 */
function trackProductView($conn, $productId, $source = null) {
    if (!trackingEnabled()) return;
    try {
        $st = $conn->prepare("SELECT category FROM products WHERE id = ? AND is_published = TRUE");
        $st->execute([$productId]);
        $category = $st->fetchColumn();
        if ($category === false) return;   // unknown/unpublished product — ignore

        $conn->prepare("INSERT INTO product_views (visitor_id, session_id, product_id, category, source) VALUES (?, ?, ?, ?, ?)")
             ->execute([visitorId(), session_id(), $productId, $category, $source ? substr($source, 0, 60) : null]);
    } catch (PDOException $e) {
        error_log('[AGROVISE TRACK] ' . $e->getMessage());
    }
}
