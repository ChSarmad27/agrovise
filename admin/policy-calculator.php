<?php
/**
 * AGROVISE - Policy Calculator (standalone)
 * A scratchpad for pricing a policy before committing to it: pick products,
 * set quantities and prices, watch the total build up. The same grid used by
 * add-policy.php, so what you price here is exactly what gets saved — filling
 * in a name and hitting "Save as Policy" POSTs these lines to add-policy.php.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('policies');

$conn = getDBConnection();
$productsList = $conn->query("SELECT id, name, category, avg_packs_per_carton FROM products ORDER BY name ASC")->fetchAll();
$items = [];   // always starts blank

// Recent policies, for context while pricing a new one
$recent = $conn->query("SELECT id, name, total_amount, status FROM policies ORDER BY id DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policy Calculator - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <style>
        .calc-hero {
            background: #0f1a0e; color: #f2efe6;
            padding: 30px 34px; margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;
        }
        .calc-hero .micro-label {
            font-size: 0.62rem; font-weight: 500; letter-spacing: 0.34em;
            text-transform: uppercase; color: #c2a04f; display: block; margin-bottom: 8px;
        }
        .calc-hero h1 {
            font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 500;
            font-size: 1.9rem; margin: 0 0 6px; color: #f2efe6;
        }
        .calc-hero p { margin: 0; color: rgba(242, 239, 230, 0.6); font-size: 0.85rem; }
        .save-panel {
            border-top: 1px solid rgba(15, 26, 14, 0.14);
            margin-top: 24px; padding-top: 24px;
        }
        .recent-list { list-style: none; margin: 0; padding: 0; }
        .recent-list li {
            display: flex; justify-content: space-between; align-items: center;
            padding: 11px 0; border-bottom: 1px solid rgba(15, 26, 14, 0.1); font-size: 0.88rem;
        }
        .recent-list li:last-child { border-bottom: 0; }
        .recent-list .amount {
            font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 600;
            font-size: 1.05rem; color: #0f1a0e;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="calc-hero">
                <div>
                    <span class="micro-label">Agrovise &middot; Sales Policies</span>
                    <h1><i class="fas fa-calculator" style="color:#c2a04f;"></i> Policy Calculator</h1>
                    <p>Pick the products, set quantity and price on each &mdash; the sum of the lines is the policy price.</p>
                </div>
                <a href="policies.php" class="btn btn-outline btn-sm" style="border-color: rgba(242,239,230,0.35); color:#f2efe6;">
                    <i class="fas fa-list"></i> All Policies
                </a>
            </div>

            <!-- Posting to add-policy.php means the calculator and the save path validate identically -->
            <form method="POST" action="add-policy.php">
                <?php echo csrfField(); ?>

                <div class="data-card" style="padding: 20px;">
                    <h3 style="margin-top:0;">Build the Policy</h3>

                    <?php require __DIR__ . '/../includes/policy-items.php'; ?>

                    <div class="save-panel">
                        <h3 style="margin-top:0;"><i class="fas fa-floppy-disk" style="color:#c2a04f;"></i> Save as Policy</h3>
                        <p style="color:#5c6356; font-size:0.86rem; margin-top:-6px;">
                            Give the calculated bundle a name to store it. Saved policies can be applied to any invoice in one click.
                        </p>
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Policy Name *</label>
                                <input type="text" name="name" class="form-input" placeholder="e.g. Eid Policy" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Valid From</label>
                                <input type="date" name="start_date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Valid Until</label>
                                <input type="date" name="end_date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="ACTIVE">Active</option>
                                    <option value="INACTIVE">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-input" rows="2" placeholder="Optional notes about this policy..."></textarea>
                        </div>

                        <div style="text-align: right;">
                            <button type="reset" class="btn btn-outline" onclick="setTimeout(calcPolicy, 0);"><i class="fas fa-eraser"></i> Clear</button>
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save as Policy</button>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!empty($recent)): ?>
            <div class="data-card" style="padding: 20px; margin-top: 24px;">
                <h3 style="margin-top:0;">Recent Policies</h3>
                <ul class="recent-list">
                    <?php foreach ($recent as $r): ?>
                    <li>
                        <span>
                            <a href="edit-policy.php?id=<?php echo $r['id']; ?>" style="color:#23291f; font-weight:600;"><?php echo sanitize($r['name']); ?></a>
                            <?php if ($r['status'] === 'INACTIVE'): ?>
                                <small style="color:#8a8f83;">&nbsp;&middot; inactive</small>
                            <?php endif; ?>
                        </span>
                        <span class="amount">Rs. <?php echo number_format($r['total_amount'], 2); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
