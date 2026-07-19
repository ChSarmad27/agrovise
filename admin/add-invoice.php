<?php
/**
 * AGROVISE - Add Invoice
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('invoices');

$conn = getDBConnection();

// Generate Invoice No
$row = $conn->query("SELECT MAX(id) as max_id FROM invoices")->fetch();
$nextId = ($row['max_id'] ?? 0) + 1;
$invoiceNo = 'INV-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

$clientsList = $conn->query("SELECT id, name, employee_id FROM clients ORDER BY name ASC")->fetchAll();
$employeesList = $conn->query("SELECT id, name FROM employees ORDER BY name ASC")->fetchAll();
// client -> assigned sales officer, so the invoice defaults to that officer
$clientEmployeeMap = [];
foreach ($clientsList as $c) { $clientEmployeeMap[$c['id']] = $c['employee_id'] ? intval($c['employee_id']) : ''; }

// Fetch available stock from purchasing
$stockStmt = $conn->query("
    SELECT p.id as purchasing_id, p.product_id, p.batch_number, p.type, p.quantity as stock,
           pr.name as product_name, pr.avg_packs_per_carton, pr.packing_type
    FROM purchasing p
    JOIN products pr ON p.product_id = pr.id
    WHERE p.quantity > 0
");
$stockItems = $stockStmt->fetchAll();

// --- Sales policies that can be applied to this invoice ---
// Only ACTIVE policies that are inside their validity window (blank dates = always valid).
$policiesList = $conn->query("
    SELECT id, name, total_amount
    FROM policies
    WHERE status = 'ACTIVE'
      AND (start_date IS NULL OR start_date <= CURDATE())
      AND (end_date   IS NULL OR end_date   >= CURDATE())
    ORDER BY name ASC")->fetchAll();

// policy id -> its product lines, so "Apply Policy" can fill the grid client-side
$policyItems = [];
if ($policiesList) {
    $ids = array_column($policiesList, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $pi  = $conn->prepare("
        SELECT pi.policy_id, pi.product_id, pi.quantity, pi.price, pi.sales_tax, pi.packs_per_carton, pr.name AS product_name
        FROM policy_items pi JOIN products pr ON pr.id = pi.product_id
        WHERE pi.policy_id IN ($in) ORDER BY pi.id ASC");
    $pi->execute($ids);
    foreach ($pi->fetchAll() as $row) {
        $policyItems[$row['policy_id']][] = $row;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = intval($_POST['client_id'] ?? 0);
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $policy_id = !empty($_POST['policy_id']) ? intval($_POST['policy_id']) : null;
    $date = trim($_POST['date'] ?? '');

    // Only record a policy that actually exists
    if ($policy_id) {
        $chk = $conn->prepare("SELECT COUNT(*) FROM policies WHERE id = ?");
        $chk->execute([$policy_id]);
        if (!intval($chk->fetchColumn())) $policy_id = null;
    }
    
    $purchasing_ids = $_POST['purchasing_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $packs = $_POST['packs_per_carton'] ?? [];
    $taxes = $_POST['sales_tax'] ?? [];

    if (empty($client_id)) $errors[] = 'Please select a valid client.';
    if (empty($date)) $errors[] = 'Date is required.';
    if (empty($purchasing_ids)) $errors[] = 'Please add at least one product.';

    $itemsToInsert = [];
    $totalAmount = 0;

    // Validate stock
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
            $pData = $conn->prepare("SELECT quantity, product_id FROM purchasing WHERE id = ?");
            $pData->execute([$pid]);
            $productInfo = $pData->fetch();

            if (!$productInfo) {
                $errors[] = 'Invalid stock item selected.';
            } elseif ($productInfo['quantity'] < $qty) {
                $errors[] = "Not enough stock. Available: {$productInfo['quantity']}";
            } else {
                $itemsToInsert[] = [
                    'purchasing_id' => $pid,
                    'product_id' => $productInfo['product_id'],
                    'quantity' => $qty,
                    'price' => $prc,
                    'packs' => $pk,
                    'tax' => $tax
                ];
                // Line total includes its own sales tax
                $totalAmount += ($qty * $prc) * (1 + $tax / 100);
            }
        }
    }
    
    if (empty($itemsToInsert) && empty($errors)) {
        $errors[] = 'Please provide valid quantities for products.';
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("INSERT INTO invoices (invoice_no, client_id, employee_id, policy_id, date, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$invoiceNo, $client_id, $employee_id, $policy_id, $date, $totalAmount]);
            $invoice_id = $conn->lastInsertId();
            
            foreach ($itemsToInsert as $item) {
                $iStmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, product_id, purchasing_id, quantity, price, sales_tax, packs_per_carton) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $iStmt->execute([$invoice_id, $item['product_id'], $item['purchasing_id'], $item['quantity'], $item['price'], $item['tax'], $item['packs']]);
                
                // Reduce stock in purchasing
                $sStmt = $conn->prepare("UPDATE purchasing SET quantity = quantity - ? WHERE id = ?");
                $sStmt->execute([$item['quantity'], $item['purchasing_id']]);
            }
            
            $conn->commit();

            // --- Queue invoice messages (never blocks invoicing on failure) ---
            $queuedNote = '';
            try {
                if (messagingEnabled($conn)) {
                    // Client details for the message body
                    $cst = $conn->prepare("SELECT name, phone, address, area FROM clients WHERE id = ?");
                    $cst->execute([$client_id]);
                    $msgClient = $cst->fetch();

                    // Product lines: "Name (Qty: X)" joined
                    $lines = [];
                    foreach ($itemsToInsert as $it) {
                        $pn = $conn->prepare("SELECT name FROM products WHERE id = ?");
                        $pn->execute([$it['product_id']]);
                        $lines[] = $pn->fetchColumn() . ' (Qty: ' . rtrim(rtrim(number_format($it['quantity'], 2, '.', ''), '0'), '.') . ')';
                    }
                    $productsTxt = implode(', ', $lines);
                    $addressTxt  = trim(($msgClient['address'] ?? '') . ', ' . ($msgClient['area'] ?? ''), ', ');
                    $stampTxt    = "\nInvoiced on: " . date('d M Y, h:i A');
                    $queued = 0;

                    // Sales officer message
                    if ($employee_id) {
                        $est = $conn->prepare("SELECT name, phone FROM employees WHERE id = ?");
                        $est->execute([$employee_id]);
                        if ($emp = $est->fetch()) {
                            $body = "Asslam-U-Alikum Mr " . $emp['name'] . ", the " . $productsTxt
                                  . " for your client " . $msgClient['name']
                                  . " has been invoiced successfully and will be dispatched soon for the " . $addressTxt . "."
                                  . $stampTxt . messageSignature();
                            if (queueOutboundMessage($conn, 'EMPLOYEE', $emp['name'], $emp['phone'], $body, $invoiceNo)) $queued++;
                        }
                    }

                    // Client message
                    if ($msgClient) {
                        $body = "Asslam-U-Alikum Mr " . $msgClient['name'] . ", the " . $productsTxt
                              . " has been invoiced successfully and will be dispatched soon for the " . $addressTxt . "."
                              . $stampTxt . messageSignature();
                        if (queueOutboundMessage($conn, 'CLIENT', $msgClient['name'], $msgClient['phone'], $body, $invoiceNo)) $queued++;
                    }

                    if ($queued) $queuedNote = " $queued message(s) ready — <a href=\"messaging.php\" style=\"font-weight:700; text-decoration:underline; color:inherit;\">open the Messaging Centre to send them via WhatsApp</a>.";
                }
            } catch (Exception $e) { /* messaging must never break invoicing */ }

            setFlashMessage('success', 'Invoice created successfully!' . $queuedNote);
            header('Location: invoices.php');
            exit;
        } catch (PDOException $e) {
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
    <title>Add Invoice - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header"><h1>Create Invoice</h1></div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                        <?php echo csrfField(); ?>
                <div class="data-card" style="margin-bottom: 20px; padding: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Invoice No</label>
                            <input type="text" name="invoice_no" class="form-input" value="<?php echo $invoiceNo; ?>" readonly style="background:#e9ecef;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date *</label>
                            <input type="date" name="date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Select Client *</label>
                            <select name="client_id" id="clientSelect" class="form-select" required onchange="defaultOfficer()">
                                <option value="">-- Choose Client --</option>
                                <?php foreach($clientsList as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sales Officer (Sale By)</label>
                            <select name="employee_id" id="employeeSelect" class="form-select">
                                <option value="">-- Choose Employee --</option>
                                <?php foreach($employeesList as $e): ?>
                                <option value="<?php echo $e['id']; ?>"><?php echo sanitize($e['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color:#888;">Defaults to the client's assigned officer; change if needed.</small>
                        </div>
                    </div>
                </div>
                
                <div class="data-card" style="margin-bottom: 20px; padding: 20px;">
                    <h3 style="margin-top:0;"><i class="fas fa-scroll" style="color:#c2a04f;"></i> Apply a Sales Policy <small style="font-weight:400; color:#999;">(optional)</small></h3>
                    <p style="color:#5c6356; font-size:0.86rem; margin-top:-6px;">
                        Choosing a policy adds its products, quantities and agreed prices to the invoice automatically. You can still edit any line afterwards.
                    </p>
                    <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="margin:0; flex: 1; min-width: 260px;">
                            <label class="form-label">Policy</label>
                            <select id="policySelect" class="form-select">
                                <option value="">-- No policy --</option>
                                <?php foreach ($policiesList as $p): ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo sanitize($p['name']); ?> (Rs. <?php echo number_format($p['total_amount'], 2); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="applyPolicy()"><i class="fas fa-wand-magic-sparkles"></i> Apply Policy</button>
                        <input type="hidden" name="policy_id" id="policyIdInput" value="">
                    </div>
                    <?php if (empty($policiesList)): ?>
                    <p style="color:#8a8f83; font-size:0.82rem; margin-bottom:0;">
                        No active policies right now. <a href="policy-calculator.php" style="color:#c2a04f; font-weight:600;">Build one in the Policy Calculator</a>.
                    </p>
                    <?php endif; ?>
                    <div id="policyApplied" style="display:none; margin-top:14px;" class="alert alert-success"></div>
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
                        <button type="submit" class="btn btn-primary btn-lg" style="margin-top: 10px;"><i class="fas fa-save"></i> Save Invoice</button>
                    </div>
                </div>
            </form>
        </main>
    </div>
    
    <script>
    const stockItems = <?php echo json_encode($stockItems); ?>;
    const clientEmployeeMap = <?php echo json_encode($clientEmployeeMap); ?>;
    const policyItems = <?php echo json_encode($policyItems); ?>;
    let rowIndex = 0;

    // When a client is chosen, default the Sales Officer to that client's assigned officer
    function defaultOfficer() {
        const cid = document.getElementById('clientSelect').value;
        const eid = clientEmployeeMap[cid];
        if (eid) document.getElementById('employeeSelect').value = eid;
    }

    function addRow(preset) {
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
                    <small>Batch: <span class="disp-batch"></span></small>
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

        // A policy line arrives with its lot already chosen — fill the row as if it had been picked by hand
        if (preset) {
            tr.querySelector('.type-select').value = preset.type;
            tr.querySelector('.item-select-btn').style.display = 'none';
            tr.querySelector('.selected-info').style.display = 'block';
            tr.querySelector('.disp-name').textContent = preset.product_name;
            tr.querySelector('.disp-batch').textContent = preset.batch_number;
            tr.querySelector('.purchasing-id').value = preset.purchasing_id;
            tr.querySelector('.pack-input').value = preset.packs;
            tr.querySelector('.stock-label').textContent = preset.stock;
            tr.querySelector('.qty-input').max = preset.stock;
            tr.querySelector('.qty-input').value = preset.quantity;
            tr.querySelector('.price-input').value = preset.price;
            tr.querySelector('.tax-input').value = parseFloat(preset.sales_tax) > 0 ? preset.sales_tax : '';
            calcTotals();
        }
    }

    /**
     * Apply a saved policy: drop its products, quantities and agreed prices
     * straight into the invoice. Each policy line is matched to a stock lot of
     * that product (a FINISHED lot with enough stock is preferred); anything
     * out of stock is reported rather than silently skipped.
     */
    function applyPolicy() {
        const sel = document.getElementById('policySelect');
        const policyId = sel.value;
        const banner = document.getElementById('policyApplied');

        if (!policyId) {
            document.getElementById('policyIdInput').value = '';
            banner.style.display = 'none';
            return;
        }

        const lines = policyItems[policyId] || [];
        if (!lines.length) {
            Swal.fire('Empty policy', 'This policy has no products in it.', 'info');
            return;
        }

        // Drop rows the user has not filled in yet, so applying does not leave blanks behind
        document.querySelectorAll('#itemsBody tr').forEach(tr => {
            if (!tr.querySelector('.purchasing-id').value) tr.remove();
        });

        const added = [], noStock = [], shortStock = [];

        lines.forEach(line => {
            const lots = stockItems.filter(s => s.product_id == line.product_id && parseFloat(s.stock) > 0);
            if (!lots.length) {
                noStock.push(line.product_name);
                return;
            }

            const want = parseFloat(line.quantity);
            // Prefer a FINISHED lot that can cover the quantity; otherwise any lot that can;
            // otherwise the fullest lot available (and flag it as short).
            const lot = lots.find(l => l.type === 'FINISHED' && parseFloat(l.stock) >= want)
                     || lots.find(l => parseFloat(l.stock) >= want)
                     || lots.reduce((a, b) => parseFloat(a.stock) >= parseFloat(b.stock) ? a : b);

            if (parseFloat(lot.stock) < want) shortStock.push(`${line.product_name} (need ${want}, have ${lot.stock})`);

            addRow({
                type: lot.type,
                purchasing_id: lot.purchasing_id,
                product_name: lot.product_name,
                batch_number: lot.batch_number,
                stock: lot.stock,
                packs: line.packs_per_carton || lot.avg_packs_per_carton || '',
                quantity: line.quantity,
                price: line.price,
                sales_tax: line.sales_tax
            });
            added.push(line.product_name);
        });

        document.getElementById('policyIdInput').value = policyId;

        const policyName = sel.options[sel.selectedIndex].text;
        banner.style.display = 'block';
        banner.innerHTML = `<strong>Policy applied:</strong> ${policyName} &mdash; ${added.length} product line(s) added. Quantities and prices came from the policy and can still be edited.`;

        if (!document.querySelectorAll('#itemsBody tr').length) addRow();

        // Never fail quietly: say exactly which policy products the warehouse cannot cover
        if (noStock.length || shortStock.length) {
            let html = '';
            if (noStock.length) html += `<p style="text-align:left;"><strong>Out of stock (not added):</strong><br>${noStock.join('<br>')}</p>`;
            if (shortStock.length) html += `<p style="text-align:left;"><strong>Not enough stock (added anyway — adjust before saving):</strong><br>${shortStock.join('<br>')}</p>`;
            Swal.fire({ icon: 'warning', title: 'Policy applied with warnings', html: html });
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
        
        // Filter stock
        let filtered = stockItems.filter(item => item.type === type);
        if(filtered.length === 0) {
            Swal.fire('Info', 'No items available in this category.', 'info');
            return;
        }

        let options = {};
        filtered.forEach(i => {
            options[i.purchasing_id] = `${i.product_name} (Batch: ${i.batch_number}) - Avail: ${i.stock}`;
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
                tr.querySelector('.purchasing-id').value = sel.purchasing_id;
                tr.querySelector('.pack-input').value = sel.avg_packs_per_carton;
                tr.querySelector('.stock-label').textContent = sel.stock;
                
                tr.querySelector('.qty-input').max = sel.stock;
                // Optional: set some default price if needed
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
    
    // Add initial row
    addRow();
    </script>
</body>
</html>
