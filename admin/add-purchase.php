<?php
/**
 * AGROVISE - Add Purchase
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

requirePermission('purchasing');

$conn = getDBConnection();
$errors = [];

// Fetch products
$productsStmt = $conn->query("SELECT id, name, category, packing_type FROM products ORDER BY name ASC");
$products = $productsStmt->fetchAll();
$vendorsStmt = $conn->query("SELECT id, name FROM vendors ORDER BY name ASC");
$vendors = $vendorsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $vendor_id = intval($_POST['vendor_id'] ?? 0);
    $batch_number = trim($_POST['batch_number'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $expiry_date = trim($_POST['expiry_date'] ?? '') ?: null;
    $total_price = $quantity * $purchase_price;

    if (!$product_id) $errors[] = 'Product selection is required.';
    if (!$vendor_id) $errors[] = 'Vendor selection is required.';
    if (empty($batch_number)) $errors[] = 'Batch number is required.';
    if (empty($type)) $errors[] = 'Type selection is required.';
    if ($quantity <= 0) $errors[] = 'Quantity must be greater than zero.';
    if ($purchase_price < 0) $errors[] = 'Purchase price cannot be negative.';

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO purchasing (product_id, vendor_id, batch_number, type, purchase_price, quantity, total_price, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $vendor_id, $batch_number, $type, $purchase_price, $quantity, $total_price, $expiry_date]);
            
            setFlashMessage('success', 'Purchase recorded successfully!');
            header('Location: purchasing.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = dbError($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Purchase - AGROVISE Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-plus-circle"></i> Record New Purchase / Stock In</h1>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card" style="max-width: 800px;">
                <div class="data-card-header">
                    <h2>Purchase Details</h2>
                    <a href="purchasing.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                
                <div style="padding: 30px;">
                    <form method="POST" action="" id="purchaseForm">
                        <?php echo csrfField(); ?>
                        
                        
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Select Vendor / Supplier *</label>
                            <select id="vendor_id" name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($v['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="product_id">Select Product *</label>
                            <select id="product_id" name="product_id" class="form-select" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo (isset($_POST['product_id']) && $_POST['product_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($p['name']) . ' (' . sanitize($p['category']) . ') - ' . sanitize($p['packing_type']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Select Vendor / Supplier *</label>
                            <select id="vendor_id" name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($v['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                                <label class="form-label" for="batch_number">Batch Number *</label>
                                <input type="text" id="batch_number" name="batch_number" class="form-input" required value="<?php echo isset($_POST['batch_number']) ? sanitize($_POST['batch_number']) : ''; ?>">
                            </div>
                        <div class="form-group">
                                <label class="form-label" for="expiry_date">Expiry Date (Optional)</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-input" value="<?php echo isset($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : ''; ?>">
                            </div>
                            
                            
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Select Vendor / Supplier *</label>
                            <select id="vendor_id" name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($v['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                                <label class="form-label" for="type">Stock Type *</label>
                                <select id="type" name="type" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="BULK" <?php echo (isset($_POST['type']) && $_POST['type'] == 'BULK') ? 'selected' : ''; ?>>Bulk Raw Material</option>
                                    <option value="PACKING" <?php echo (isset($_POST['type']) && $_POST['type'] == 'PACKING') ? 'selected' : ''; ?>>Packing Material</option>
                                    <option value="FINISHED" <?php echo (isset($_POST['type']) && $_POST['type'] == 'FINISHED') ? 'selected' : ''; ?>>Finished Good</option>
                                    <option value="OTHERS" <?php echo (isset($_POST['type']) && $_POST['type'] == 'OTHERS') ? 'selected' : ''; ?>>Others</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                            
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Select Vendor / Supplier *</label>
                            <select id="vendor_id" name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($v['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                                <label class="form-label" for="quantity">Quantity *</label>
                                <input type="number" id="quantity" name="quantity" class="form-input calc-field" step="0.01" min="0.01" required value="<?php echo isset($_POST['quantity']) ? sanitize($_POST['quantity']) : ''; ?>">
                            </div>
                            
                            
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Select Vendor / Supplier *</label>
                            <select id="vendor_id" name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($v['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                                <label class="form-label" for="purchase_price">Price per Unit (Rs) *</label>
                                <input type="number" id="purchase_price" name="purchase_price" class="form-input calc-field" step="0.01" min="0" required value="<?php echo isset($_POST['purchase_price']) ? sanitize($_POST['purchase_price']) : ''; ?>">
                            </div>
                            
                            
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Select Vendor / Supplier *</label>
                            <select id="vendor_id" name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v['id']; ?>" <?php echo (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($v['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                                <label class="form-label" for="total_price">Total Price (Auto) Rs</label>
                                <input type="number" id="total_price" class="form-input" readonly style="background: var(--light-gray); font-weight: bold;">
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Purchase</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Auto calculate total
        const qtyField = document.getElementById('quantity');
        const priceField = document.getElementById('purchase_price');
        const totalField = document.getElementById('total_price');
        
        function calcTotal() {
            const q = parseFloat(qtyField.value) || 0;
            const p = parseFloat(priceField.value) || 0;
            totalField.value = (q * p).toFixed(2);
        }
        
        document.querySelectorAll('.calc-field').forEach(el => {
            el.addEventListener('input', calcTotal);
        });
        
        // Initial calculation on load (in case of validation failure with post data)
        calcTotal();
    </script>
</body>
</html>
