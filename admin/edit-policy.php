<?php
/**
 * AGROVISE - Edit Sales Policy
 * Same calculator as add-policy: the lines are replaced wholesale on save and
 * the policy total is recomputed from them.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('policies');

$conn = getDBConnection();

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM policies WHERE id = ?");
$stmt->execute([$id]);
$policy = $stmt->fetch();

if (!$policy) {
    setFlashMessage('error', 'Policy not found.');
    header('Location: policies.php');
    exit;
}

$productsList = $conn->query("SELECT id, name, category, avg_packs_per_carton FROM products ORDER BY name ASC")->fetchAll();

$errors = [];

$name        = $policy['name'];
$description = $policy['description'];
$start_date  = $policy['start_date'];
$end_date    = $policy['end_date'];
$status      = $policy['status'];

// Existing lines prefill the calculator
$items = $conn->prepare("SELECT product_id, quantity, price, sales_tax, packs_per_carton FROM policy_items WHERE policy_id = ? ORDER BY id ASC");
$items->execute([$id]);
$items = $items->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date  = trim($_POST['start_date'] ?? '');
    $end_date    = trim($_POST['end_date'] ?? '');
    $status      = ($_POST['status'] ?? 'ACTIVE') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';

    $productIds = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices     = $_POST['price'] ?? [];
    $taxes      = $_POST['sales_tax'] ?? [];
    $packs      = $_POST['packs_per_carton'] ?? [];

    if ($name === '') $errors[] = 'Policy name is required.';
    if ($start_date !== '' && $end_date !== '' && strtotime($end_date) < strtotime($start_date)) {
        $errors[] = 'The end date cannot be before the start date.';
    }

    $newItems = [];
    $total = 0;
    foreach ($productIds as $i => $pid) {
        $pid = intval($pid);
        $qty = floatval($quantities[$i] ?? 0);
        $prc = floatval($prices[$i] ?? 0);
        $tax = floatval($taxes[$i] ?? 0);
        $pk  = intval($packs[$i] ?? 0);
        if ($pid <= 0) continue;

        if ($qty <= 0)  { $errors[] = 'Every policy line needs a quantity greater than zero.'; continue; }
        if ($prc < 0)   { $errors[] = 'Prices cannot be negative.'; continue; }
        if ($tax < 0 || $tax > 100) { $errors[] = 'Sales tax must be between 0 and 100%.'; continue; }

        $newItems[] = [
            'product_id'       => $pid,
            'quantity'         => $qty,
            'price'            => $prc,
            'sales_tax'        => $tax,
            'packs_per_carton' => $pk,
        ];
        $total += ($qty * $prc) * (1 + $tax / 100);
    }

    if (empty($newItems) && empty($errors)) {
        $errors[] = 'A policy must contain at least one product.';
    }

    // Keep whatever the user submitted on screen if it did not validate
    if (!empty($errors)) {
        $items = $newItems;
    } else {
        try {
            $conn->beginTransaction();

            $conn->prepare("UPDATE policies SET name = ?, description = ?, start_date = ?, end_date = ?, status = ?, total_amount = ? WHERE id = ?")
                 ->execute([$name, $description ?: null, $start_date ?: null, $end_date ?: null, $status, $total, $id]);

            $conn->prepare("DELETE FROM policy_items WHERE policy_id = ?")->execute([$id]);
            $ins = $conn->prepare("INSERT INTO policy_items (policy_id, product_id, quantity, price, sales_tax, packs_per_carton) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($newItems as $it) {
                $ins->execute([$id, $it['product_id'], $it['quantity'], $it['price'], $it['sales_tax'], $it['packs_per_carton']]);
            }

            $conn->commit();
            setFlashMessage('success', 'Policy updated successfully.');
            header('Location: policies.php');
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = dbError($e);
            $items = $newItems;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Policy - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-scroll"></i> Edit Policy</h1>
                <a href="policies.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to Policies</a>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach (array_unique($errors) as $error) echo '<li>' . $error . '</li>'; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>

                <div class="data-card" style="margin-bottom: 20px; padding: 20px;">
                    <h3 style="margin-top:0;">Policy Details</h3>
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Policy Name *</label>
                            <input type="text" name="name" class="form-input" required value="<?php echo sanitize($name); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Valid From</label>
                            <input type="date" name="start_date" class="form-input" value="<?php echo sanitize($start_date); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Valid Until</label>
                            <input type="date" name="end_date" class="form-input" value="<?php echo sanitize($end_date); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="ACTIVE" <?php echo $status === 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                                <option value="INACTIVE" <?php echo $status === 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-input" rows="2"><?php echo sanitize($description); ?></textarea>
                    </div>
                </div>

                <div class="data-card" style="padding: 20px;">
                    <h3 style="margin-top:0;"><i class="fas fa-calculator" style="color:#c2a04f;"></i> Policy Calculator</h3>
                    <p style="color:#5c6356; font-size:0.86rem; margin-top:-6px;">
                        The sum of the lines below is the policy price. Changing a line re-prices the policy on save.
                    </p>

                    <?php require __DIR__ . '/../includes/policy-items.php'; ?>

                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Update Policy</button>
                    </div>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
