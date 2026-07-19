<?php
$file = 'F:/wamp64/www/agrovise/admin/add-purchase.php';
$content = file_get_contents($file);

// 1. Fetch vendors
$content = str_replace(
    '$products = $productsStmt->fetchAll();',
    '$products = $productsStmt->fetchAll();' . "\n" . '$vendorsStmt = $conn->query("SELECT id, name FROM vendors ORDER BY name ASC");' . "\n" . '$vendors = $vendorsStmt->fetchAll();',
    $content
);

// 2. Post handler logic
$content = str_replace(
    '$product_id = intval($_POST[\'product_id\'] ?? 0);',
    '$product_id = intval($_POST[\'product_id\'] ?? 0);' . "\n" . '    $vendor_id = intval($_POST[\'vendor_id\'] ?? 0);',
    $content
);

$content = str_replace(
    'if (!$product_id) $errors[] = \'Product selection is required.\';',
    'if (!$product_id) $errors[] = \'Product selection is required.\';' . "\n" . '    if (!$vendor_id) $errors[] = \'Vendor selection is required.\';',
    $content
);

// 3. Insert query logic
$content = str_replace(
    '$stmt = $conn->prepare("INSERT INTO purchasing (product_id, batch_number, type, purchase_price, quantity, total_price) VALUES (?, ?, ?, ?, ?, ?)");',
    '$stmt = $conn->prepare("INSERT INTO purchasing (product_id, vendor_id, batch_number, type, purchase_price, quantity, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");',
    $content
);

$content = str_replace(
    '$stmt->execute([$product_id, $batch_number, $type, $purchase_price, $quantity, $total_price]);',
    '$stmt->execute([$product_id, $vendor_id, $batch_number, $type, $purchase_price, $quantity, $total_price]);',
    $content
);

// 4. Update the form UI (add Vendor dropdown)
$vendorInput = '
                        <div class="form-group">
                            <label class="form-label" for="vendor_id">Select Vendor / Supplier *</label>
                            <select id="vendor_id" name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                <option value="<?php echo $v[\'id\']; ?>" <?php echo (isset($_POST[\'vendor_id\']) && $_POST[\'vendor_id\'] == $v[\'id\']) ? \'selected\' : \'\'; ?>>
                                    <?php echo sanitize($v[\'name\']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>';

$content = str_replace(
    '<div class="form-group">',
    $vendorInput . "\n" . '                        <div class="form-group">',
    $content
);

file_put_contents($file, $content);
echo "add-purchase.php updated successfully!\n";
