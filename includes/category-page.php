<?php
/**
 * AGROVISE - Shared category page template
 * Each file in categories/ sets $category and $metaTail, then requires this.
 * URLs are unchanged: categories/<slug>.php still serves each category.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/tracking.php';

$categoryName = getCategoryName($category);
$categoryDescription = getCategoryDescription($category);
$products = getProductsByCategory($category);

// Record the visit (skipped for bots and logged-in staff)
trackPageView(getDBConnection(), 'category', $category);

// Pagination
$productsPerPage = 9;
$totalProducts = count($products);
$totalPages = ceil($totalProducts / $productsPerPage);
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $productsPerPage;
$paginatedProducts = array_slice($products, $offset, $productsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $categoryName; ?> - AGROVISE</title>
    <meta name="description" content="Browse our range of <?php echo strtolower($categoryName); ?> <?php echo $metaTail; ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        /* Product detail overlay (opening it is what records product interest) */
        .product-card { cursor: pointer; }
        .product-view-link {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 14px;
            font-size: 0.66rem; font-weight: 600;
            letter-spacing: 0.22em; text-transform: uppercase;
            color: #c2a04f;
        }
        .product-view-link i { transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
        .product-card:hover .product-view-link i { transform: translateX(5px); }

        .pd-overlay {
            position: fixed; inset: 0; z-index: 2000;
            background: rgba(15, 26, 14, 0.72);
            display: none; align-items: center; justify-content: center;
            padding: 24px;
        }
        .pd-overlay.open { display: flex; }
        .pd-modal {
            background: #f2efe6;
            max-width: 820px; width: 100%;
            max-height: 90vh; overflow: auto;
            display: grid; grid-template-columns: 1fr 1fr;
            position: relative;
        }
        @media (max-width: 720px) { .pd-modal { grid-template-columns: 1fr; } }
        .pd-modal img { width: 100%; height: 100%; min-height: 260px; object-fit: cover; display: block; }
        .pd-body { padding: 40px 38px; }
        .pd-body .micro {
            display: block; font-size: 0.62rem; font-weight: 600;
            letter-spacing: 0.3em; text-transform: uppercase;
            color: #c2a04f; margin-bottom: 14px;
        }
        .pd-body h3 {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-weight: 500; font-size: 2rem; line-height: 1.15;
            color: #0f1a0e; margin: 0 0 20px;
        }
        .pd-specs { list-style: none; margin: 0; padding: 0; }
        .pd-specs li {
            display: flex; justify-content: space-between; gap: 16px;
            padding: 13px 0;
            border-bottom: 1px solid rgba(15, 26, 14, 0.12);
            font-size: 0.9rem; color: #3c4436;
        }
        .pd-specs li span:first-child {
            font-size: 0.62rem; font-weight: 600;
            letter-spacing: 0.18em; text-transform: uppercase; color: #8a8f83;
        }
        .pd-note { margin-top: 24px; font-size: 0.84rem; line-height: 1.7; color: #5c6356; }
        .pd-close {
            position: absolute; top: 14px; right: 16px;
            background: none; border: 0; cursor: pointer;
            font-size: 1.1rem; color: #0f1a0e;
            width: 38px; height: 38px;
        }
        .pd-close:hover { color: #c2a04f; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar scrolled" id="navbar">
        <div class="container">
            <a href="../index.php" class="logo">
                <img src="../assets/images/logo-green.png" alt="AGROVISE" id="logo-img">
                <span class="logo-text">AGROVISE</span>
            </a>

            <ul class="nav-menu" id="nav-menu">
                <li><a href="../index.php" class="nav-link">Home</a></li>
                <li><a href="../index.php#categories" class="nav-link active">Categories</a></li>
                <li><a href="../index.php#about" class="nav-link">About</a></li>
                <li><a href="../admin/login.php" class="nav-link"><i class="fas fa-lock"></i> Admin</a></li>
            </ul>

            <div class="menu-toggle" id="menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <div class="page-header-bg"></div>
        <div class="page-header-content">
            <h1 class="page-title"><?php echo $categoryName; ?></h1>
            <p class="page-description"><?php echo $categoryDescription; ?></p>
            <nav class="breadcrumb">
                <a href="../index.php">Home</a>
                <span>/</span>
                <span class="current"><?php echo $categoryName; ?></span>
            </nav>
        </div>
    </header>

    <!-- Products Section -->
    <section class="products-section">
        <div class="container">
            <div class="products-grid">
                <?php foreach ($paginatedProducts as $product): ?>
                <div class="product-card reveal" role="button" tabindex="0"
                     onclick="openProduct(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openProduct(this);}"
                     data-id="<?php echo intval($product['id']); ?>"
                     data-name="<?php echo sanitize($product['name']); ?>"
                     data-image="<?php echo sanitize(getProductImagePath($product['image'], '..')); ?>"
                     data-packing="<?php echo sanitize($product['packing_type'] ?? 'None'); ?>"
                     data-packs="<?php echo sanitize($product['avg_packs_per_carton'] ?? '0'); ?>">
                    <div class="product-image">
                        <img src="<?php echo getProductImagePath($product['image'], '..'); ?>" alt="<?php echo sanitize($product['name']); ?>">
                        <span class="product-category-badge"><?php echo $categoryName; ?></span>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?php echo sanitize($product['name']); ?></h3>
                        <p class="product-description" style="margin-top:10px;"><strong style="color: #666;">Packing:</strong> <?php echo sanitize($product['packing_type'] ?? 'None'); ?><br><strong style="color: #666;">Packs/Carton:</strong> <?php echo sanitize($product['avg_packs_per_carton'] ?? '0'); ?></p>
                        <span class="product-view-link">View details <i class="fas fa-arrow-right"></i></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                <a href="?page=<?php echo $currentPage - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $currentPage): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?php echo $currentPage + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <img src="../assets/images/logo-white.png" alt="AGROVISE">
                    <p>Your trusted partner for premium agriculture products. Plow, Grow, Glow with AGROVISE.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="footer-links-section">
                    <h4 class="footer-title">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../index.php#categories">Categories</a></li>
                        <li><a href="../index.php#about">About Us</a></li>
                        <li><a href="../admin/login.php">Admin Login</a></li>
                    </ul>
                </div>

                <div class="footer-links-section">
                    <h4 class="footer-title">Categories</h4>
                    <ul class="footer-links">
                        <li><a href="insecticides.php">Insecticides</a></li>
                        <li><a href="weedicides.php">Weedicides</a></li>
                        <li><a href="fungicides.php">Fungicides</a></li>
                        <li><a href="granulars.php">Granulars</a></li>
                        <li><a href="micronutrients.php">Micronutrients</a></li>
                    </ul>
                </div>

                <div class="footer-contact-section">
                    <h4 class="footer-title">Contact Us</h4>
                    <ul class="footer-contact">
                        <li><i class="fas fa-map-marker-alt"></i><span>123 Agriculture Road, Farming District</span></li>
                        <li><i class="fas fa-phone"></i><span>+1 234 567 8900</span></li>
                        <li><i class="fas fa-envelope"></i><span>info@agrovise.com</span></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> AGROVISE. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Product detail overlay -->
    <div class="pd-overlay" id="pdOverlay" onclick="if(event.target===this) closeProduct();">
        <div class="pd-modal" role="dialog" aria-modal="true" aria-labelledby="pdName">
            <img id="pdImage" src="" alt="">
            <div class="pd-body">
                <button type="button" class="pd-close" onclick="closeProduct()" aria-label="Close"><i class="fas fa-times"></i></button>
                <span class="micro"><?php echo $categoryName; ?></span>
                <h3 id="pdName"></h3>
                <ul class="pd-specs">
                    <li><span>Category</span><span><?php echo $categoryName; ?></span></li>
                    <li><span>Packing</span><span id="pdPacking"></span></li>
                    <li><span>Packs / Carton</span><span id="pdPacks"></span></li>
                </ul>
                <p class="pd-note">
                    For pricing, availability and bulk orders, contact the AGROVISE sales desk
                    &mdash; our officers will guide you to the right dosage for your crop.
                </p>
            </div>
        </div>
    </div>

    <script>
    const AV_CSRF = '<?php echo csrfToken(); ?>';
    const AV_SOURCE = 'category:<?php echo sanitize($category); ?>';

    function openProduct(card) {
        document.getElementById('pdImage').src = card.dataset.image;
        document.getElementById('pdImage').alt = card.dataset.name;
        document.getElementById('pdName').textContent = card.dataset.name;
        document.getElementById('pdPacking').textContent = card.dataset.packing;
        document.getElementById('pdPacks').textContent = card.dataset.packs;
        document.getElementById('pdOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';

        // Log the product view; a failed beacon must never affect the visitor
        const data = new FormData();
        data.append('product_id', card.dataset.id);
        data.append('source', AV_SOURCE);
        data.append('csrf_token', AV_CSRF);
        fetch('../track.php', { method: 'POST', body: data, keepalive: true }).catch(() => {});
    }

    function closeProduct() {
        document.getElementById('pdOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProduct(); });
    </script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
