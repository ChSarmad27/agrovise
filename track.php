<?php
/**
 * AGROVISE - Public tracking beacon
 * The public site POSTs here when a visitor opens a product's detail card.
 * Responds with an empty 204 either way: tracking must never be visible to
 * the visitor, and must never fail the page it was fired from.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';   // starts the session; auto-verifies CSRF for logged-in staff
require_once __DIR__ . '/includes/tracking.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $productId = intval($_POST['product_id'] ?? 0);
    $source    = trim((string) ($_POST['source'] ?? ''));
    if ($productId > 0) {
        trackProductView(getDBConnection(), $productId, $source);
    }
}

http_response_code(204);
