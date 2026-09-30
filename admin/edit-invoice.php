<?php
/**
 * AGROVISE - Edit Invoice
 * True edit: restores old item stock, replaces items, updates the header in place.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('invoices');

$conn = getDBConnection();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    setFlashMessage('error', 'Invalid invoice ID.');
    header('Location: invoices.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlashMessage('error', 'Invoice not found.');
    header('Location: invoices.php');
    exit;
}

$clientsList = $conn->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();
$employeesList = $conn->query("SELECT id, name FROM employees ORDER BY name ASC")->fetchAll();

// Existing items on this invoice
$itemsStmt = $conn->prepare("
    SELECT ii.purchasing_id, ii.quantity, ii.price, ii.sales_tax, ii.packs_per_carton,
           pur.type, pur.batch_number, pur.expiry_date, pr.name as product_name
    FROM invoice_items ii
    JOIN purchasing pur ON ii.purchasing_id = pur.id
    JOIN products pr ON ii.product_id = pr.id
    WHERE ii.invoice_id = ?
");
$itemsStmt->execute([$id]);
$existingItems = $itemsStmt->fetchAll();

// Quantities this invoice currently holds, per lot (available for reallocation while editing)
$held = [];
foreach ($existingItems as $it) {
    $held[$it['purchasing_id']] = ($held[$it['purchasing_id']] ?? 0) + $it['quantity'];
}

// Available stock = current lot stock + what this invoice already holds of that lot
$stockStmt = $conn->prepare("
    SELECT p.id as purchasing_id, p.product_id, p.batch_number, p.type,
           p.quantity + IFNULL(held.qty, 0) as stock,
           p.expiry_date, pr.name as product_name, pr.avg_packs_per_carton, pr.packing_type
    FROM purchasing p
    JOIN products pr ON p.product_id = pr.id
    LEFT JOIN (
        SELECT purchasing_id, SUM(quantity) as qty
        FROM invoice_items WHERE invoice_id = ?
        GROUP BY purchasing_id
    ) held ON held.purchasing_id = p.id
    WHERE p.quantity + IFNULL(held.qty, 0) > 0
");
$stockStmt->execute([$id]);
$stockItems = $stockStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $date = trim($_POST['date'] ?? '');

    $purchasing_ids = $_POST['purchasing_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $packs = $_POST['packs_per_carton'] ?? [];
    $taxes = $_POST['sales_tax'] ?? [];

    if (empty($client_id)) $errors[] = 'Please select a valid client.';
    if (empty($date)) $errors[] = 'Date is required.';
    if (empty($purchasing_ids)) $errors[] = 'Please add at least one product.';

    $requestedItems = [];
    foreach ($purchasing_ids as $index => $pid) {
        $pid = intval($pid);
        $qty = floatval($quantities[$index] ?? 0);
        $prc = floatval($prices[$index] ?? 0);
        $pk = intval($packs[$index] ?? 0);
        // Sales tax percent — optional; blank/absent means 0 (no tax)
        $tax = floatval($taxes[$index] ?? 0);
        if ($tax < 0) $tax = 0;
        if ($tax > 100) $errors[] = 'Sales tax cannot exceed 100%.';
        if ($pid > 0 && $qty > 0) {
            $requestedItems[] = ['purchasing_id' => $pid, 'quantity' => $qty, 'price' => $prc, 'packs' => $pk, 'tax' => $tax];
        }
    }

    if (empty($requestedItems) && empty($errors)) {
        $errors[] = 'Please provide valid quantities for products.';
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // 1. Restore stock held by the old items
            $restore = $conn->prepare("UPDATE purchasing SET quantity = quantity + ? WHERE id = ?");
            $oldItems = $conn->prepare("SELECT purchasing_id, quantity FROM invoice_items WHERE invoice_id = ?");
            $oldItems->execute([$id]);
            foreach ($oldItems->fetchAll() as $old) {
                $restore->execute([$old['quantity'], $old['purchasing_id']]);
            }

            // 2. Remove old items
            $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);

            // 3. Validate new items against the restored stock and build inserts
            $itemsToInsert = [];
            $totalAmount = 0;
            $lookup = $conn->prepare("SELECT quantity, product_id FROM purchasing WHERE id = ?");
            foreach ($requestedItems as $item) {
                $lookup->execute([$item['purchasing_id']]);
                $lot = $lookup->fetch();
                if (!$lot) {
                    throw new Exception('Invalid stock item selected.');
                }
                if ($lot['quantity'] < $item['quantity']) {
                    throw new Exception("Not enough stock for a selected batch. Available: {$lot['quantity']}");
                }
                $item['product_id'] = $lot['product_id'];
                $itemsToInsert[] = $item;
                // Line total includes its own sales tax
                $totalAmount += ($item['quantity'] * $item['price']) * (1 + $item['tax'] / 100);
            }

            // 4. Insert new items and deduct stock
            $ins = $conn->prepare("INSERT INTO invoice_items (invoice_id, product_id, purchasing_id, quantity, price, sales_tax, packs_per_carton) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $deduct = $conn->prepare("UPDATE purchasing SET quantity = quantity - ? WHERE id = ?");
            foreach ($itemsToInsert as $item) {
                $ins->execute([$id, $item['product_id'], $item['purchasing_id'], $item['quantity'], $item['price'], $item['tax'], $item['packs']]);
                $deduct->execute([$item['quantity'], $item['purchasing_id']]);
            }

            // 5. Update invoice header (invoice_no unchanged)
            $upd = $conn->prepare("UPDATE invoices SET client_id = ?, employee_id = ?, date = ?, total_amount = ? WHERE id = ?");
            $upd->execute([$client_id, $employee_id, $date, $totalAmount, $id]);

            $conn->commit();
            setFlashMessage('success', 'Invoice updated successfully!');
            header('Location: invoices.php');
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = dbError($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Invoice - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1>Edit Invoice <?php echo sanitize($invoice['invoice_no']); ?></h1></div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>" . sanitize($error) . "</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                        <?php echo csrfField(); ?>
                <div class="data-card" style="margin-bottom: 20px; padding: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Invoice No</label>
                            <input type="text" class="form-input" value="<?php echo sanitize($invoice['invoice_no']); ?>" readonly style="background:#e9ecef;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date *</label>
                            <input type="date" name="date" class="form-input" required value="<?php echo sanitize($invoice['date']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Select Client *</label>
                            <select name="client_id" class="form-select" required>
                                <option value="">-- Choose Client --</option>
                                <?php foreach($clientsList as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $invoice['client_id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assigned Employee (Sale By)</label>
                            <select name="employee_id" class="form-select">
                                <option value="">-- Choose Employee --</option>
                                <?php foreach($employeesList as $e): ?>
                                <option value="<?php echo $e['id']; ?>" <?php echo $e['id'] == $invoice['employee_id'] ? 'selected' : ''; ?>><?php echo sanitize($e['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="data-card" style="padding: 20px;">
                    <h3>Products</h3>
                    <table class="data-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Product / Batch</th>
                                <th>Packs/Carton</th>
                                <th>Stock</th>
                                <th>Qty</th>
                                <th>Unit Price (Rs)</th>
                                <th>Tax % <small style="font-weight:400; color:#999;">(optional)</small></th>
                                <th>Total (Rs)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- JS Rows -->
                        </tbody>
                    </table>

                    <div style="margin-top: 15px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()"><i class="fas fa-plus"></i> Add Item</button>
                    </div>

                    <div style="text-align: right; margin-top: 20px;">
                        <h3>Grand Total: Rs. <span id="grandTotal">0.00</span></h3>
                        <button type="submit" class="btn btn-primary btn-lg" style="margin-top: 10px;"><i class="fas fa-save"></i> Update Invoice</button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
    const stockItems = <?php echo json_encode($stockItems); ?>;
    const existingItems = <?php echo json_encode($existingItems); ?>;
    let rowIndex = 0;

    function addRow(prefill) {
        rowIndex++;
        const tbody = document.getElementById('itemsBody');
        const tr = document.createElement('tr');
        tr.id = 'row-' + rowIndex;

        tr.innerHTML = `
            <td>
                <select class="form-select type-select" onchange="typeSelected(${rowIndex})">
                    <option value="">-- Select Type --</option>
                    <option value="BULK">Bulk</option>
                    <option value="PACKING">Packing</option>
                    <option value="FINISHED">Finished</option>
                    <option value="OTHERS">Others</option>
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-outline btn-sm item-select-btn" onclick="selectItem(${rowIndex})" style="display:none;">Select Item</button>
                <div class="selected-info" style="display:none;">
                    <strong class="disp-name"></strong><br>
                    <small>Batch: <span class="disp-batch"></span><span class="disp-expiry" style="color:#b26a00;"></span></small>
                </div>
                <input type="hidden" name="purchasing_id[]" class="purchasing-id" required>
            </td>
            <td><input type="number" name="packs_per_carton[]" class="form-input pack-input" style="width: 80px;"></td>
            <td><span class="stock-label">0</span></td>
            <td><input type="number" name="quantity[]" class="form-input qty-input" step="0.01" min="0.01" required style="width: 80px;" oninput="calcTotals()"></td>
            <td><input type="number" name="price[]" class="form-input price-input" step="0.01" required style="width: 100px;" oninput="calcTotals()"></td>
            <td><input type="number" name="sales_tax[]" class="form-input tax-input" step="0.01" min="0" max="100" style="width: 70px;" oninput="calcTotals()" placeholder="0"></td>
            <td><strong class="row-total">0.00</strong></td>
            <td><button type="button" class="btn-icon delete" onclick="this.closest('tr').remove(); calcTotals();"><i class="fas fa-trash"></i></button></td>
        `;
        tbody.appendChild(tr);

        if (prefill) {
            const stock = stockItems.find(s => s.purchasing_id == prefill.purchasing_id);
            tr.querySelector('.type-select').value = prefill.type;
            tr.querySelector('.selected-info').style.display = 'block';
            tr.querySelector('.disp-name').textContent = prefill.product_name;
            tr.querySelector('.disp-batch').textContent = prefill.batch_number;
            tr.querySelector('.disp-expiry').textContent = prefill.expiry_date ? ' · Exp: ' + prefill.expiry_date : '';
            tr.querySelector('.purchasing-id').value = prefill.purchasing_id;
            tr.querySelector('.pack-input').value = prefill.packs_per_carton;
            tr.querySelector('.stock-label').textContent = stock ? stock.stock : '0';
            tr.querySelector('.qty-input').value = parseFloat(prefill.quantity);
            if (stock) tr.querySelector('.qty-input').max = stock.stock;
            tr.querySelector('.price-input').value = parseFloat(prefill.price);
            // Show tax only when it was actually set — zero stays blank
            const preTax = parseFloat(prefill.sales_tax) || 0;
            if (preTax > 0) tr.querySelector('.tax-input').value = preTax;
        }
    }

    function typeSelected(rowId) {
        let tr = document.getElementById('row-' + rowId);
        let type = tr.querySelector('.type-select').value;
        let btn = tr.querySelector('.item-select-btn');
        let info = tr.querySelector('.selected-info');

        if (type) {
            btn.style.display = 'inline-block';
        } else {
            btn.style.display = 'none';
        }

        info.style.display = 'none';
        tr.querySelector('.purchasing-id').value = '';
        tr.querySelector('.pack-input').value = '';
        tr.querySelector('.stock-label').textContent = '0';
        tr.querySelector('.qty-input').max = '';
        calcTotals();
    }

    function selectItem(rowId) {
        let tr = document.getElementById('row-' + rowId);
        let type = tr.querySelector('.type-select').value;

        let filtered = stockItems.filter(item => item.type === type);
        if(filtered.length === 0) {
            Swal.fire('Info', 'No items available in this category.', 'info');
            return;
        }

        let options = {};
        filtered.forEach(i => {
            options[i.purchasing_id] = `${i.product_name} (Batch: ${i.batch_number}) - Avail: ${i.stock}`
                + (i.expiry_date ? ` - Exp: ${i.expiry_date}` : '');
        });

        Swal.fire({
            title: 'Select ' + type + ' Item',
            input: 'select',
            inputOptions: options,
            inputPlaceholder: 'Select an item',
            showCancelButton: true
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const sel = filtered.find(i => i.purchasing_id == result.value);

                tr.querySelector('.item-select-btn').style.display = 'none';
                tr.querySelector('.selected-info').style.display = 'block';

                tr.querySelector('.disp-name').textContent = sel.product_name;
                tr.querySelector('.disp-batch').textContent = sel.batch_number;
                tr.querySelector('.disp-expiry').textContent = sel.expiry_date ? ' · Exp: ' + sel.expiry_date : '';
                tr.querySelector('.purchasing-id').value = sel.purchasing_id;
                tr.querySelector('.pack-input').value = sel.avg_packs_per_carton;
                tr.querySelector('.stock-label').textContent = sel.stock;

                tr.querySelector('.qty-input').max = sel.stock;
                calcTotals();
            }
        });
    }

    function calcTotals() {
        let grand = 0;
        document.querySelectorAll('#itemsBody tr').forEach(tr => {
            const q = parseFloat(tr.querySelector('.qty-input').value) || 0;
            const p = parseFloat(tr.querySelector('.price-input').value) || 0;
            const t = parseFloat(tr.querySelector('.tax-input').value) || 0;   // blank = 0 (no tax)
            const total = q * p * (1 + t / 100);
            tr.querySelector('.row-total').textContent = total.toFixed(2);
            grand += total;
        });
        document.getElementById('grandTotal').textContent = grand.toFixed(2);
    }

    // Prefill rows with the invoice's existing items
    if (existingItems.length > 0) {
        existingItems.forEach(item => addRow(item));
        calcTotals();
    } else {
        addRow();
    }
    </script>
</body>
</html>
