<?php
/**
 * AGROVISE - Add Packing Operation
 */

require_once '../includes/db.php';
require_once '../includes/functions.php';

requirePermission('packing');

$conn = getDBConnection();
$errors = [];

// Fetch initial data for JS
$bulkItemsStmt = $conn->query("
    SELECT p.id, pr.name, p.batch_number, p.quantity, p.purchase_price, p.date_added 
    FROM purchasing p JOIN products pr ON p.product_id = pr.id 
    WHERE p.type = 'BULK' AND p.quantity > 0
");
$bulkItems = $bulkItemsStmt->fetchAll();

$packingItemsStmt = $conn->query("
    SELECT p.id, pr.name, p.batch_number, p.quantity, p.purchase_price, p.date_added 
    FROM purchasing p JOIN products pr ON p.product_id = pr.id 
    WHERE p.type = 'PACKING' AND p.quantity > 0
");
$packingItems = $packingItemsStmt->fetchAll();

$allProductsStmt = $conn->query("SELECT id, name, category, packing_type FROM products ORDER BY name ASC");
$allProducts = $allProductsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect Data
    $bulk_id = intval($_POST['bulk_purchase_id'] ?? 0);
    $bulk_qty_used = floatval($_POST['bulk_quantity_used'] ?? 0);
    $bulk_unit_price = floatval($_POST['bulk_unit_price'] ?? 0);
    $bulk_cost = $bulk_qty_used * $bulk_unit_price;
    
    $finished_product_id = intval($_POST['finished_product_id'] ?? 0);
    $finished_batch = trim($_POST['finished_batch'] ?? '');
    $finished_qty = floatval($_POST['finished_qty'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0); // User manual input for info
    
    $materials = $_POST['materials'] ?? [];
    
    // Validation
    if (!$bulk_id) $errors[] = "Bulk product must be selected.";
    if ($bulk_qty_used <= 0) $errors[] = "Bulk quantity used must be greater than zero.";
    if (!$finished_product_id) $errors[] = "Finished product type must be selected.";
    if (empty($finished_batch)) $errors[] = "Finished batch number is required.";
    if ($finished_qty <= 0) $errors[] = "Quantity produced must be > 0.";
    
    // Verify bulk stock
    if ($bulk_id > 0) {
        $checkStmt = $conn->prepare("SELECT quantity FROM purchasing WHERE id = ?");
        $checkStmt->execute([$bulk_id]);
        $stock = $checkStmt->fetchColumn();
        if ($stock < $bulk_qty_used) {
            $errors[] = "Not enough bulk stock! Available: " . $stock;
        }
    }

    // Verify packing material stock (same guard as bulk, so materials can't go negative)
    if (is_array($materials)) {
        $matCheck = $conn->prepare("SELECT quantity FROM purchasing WHERE id = ?");
        foreach ($materials as $mat) {
            $m_id = intval($mat['id'] ?? 0);
            $m_qty = floatval($mat['qty'] ?? 0);
            if ($m_id > 0 && $m_qty > 0) {
                $matCheck->execute([$m_id]);
                $m_stock = $matCheck->fetchColumn();
                if ($m_stock === false || $m_stock < $m_qty) {
                    $errors[] = "Not enough packing material stock! Available: " . ($m_stock === false ? 0 : $m_stock);
                }
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // 1. Deduct bulk stock
            $updBulk = $conn->prepare("UPDATE purchasing SET quantity = quantity - ? WHERE id = ?");
            $updBulk->execute([$bulk_qty_used, $bulk_id]);
            
            // 2. Process packing materials
            $total_material_cost = 0;
            $material_inserts = [];
            
            if (is_array($materials)) {
                foreach ($materials as $mat) {
                    $m_id = intval($mat['id']);
                    $m_qty = floatval($mat['qty']);
                    $m_price = floatval($mat['price']);
                    if ($m_id > 0 && $m_qty > 0) {
                        // Deduct stock
                        $updMat = $conn->prepare("UPDATE purchasing SET quantity = quantity - ? WHERE id = ?");
                        $updMat->execute([$m_qty, $m_id]);
                        
                        $m_cost = $m_qty * $m_price;
                        $total_material_cost += $m_cost;
                        $material_inserts[] = [
                            'id' => $m_id,
                            'qty' => $m_qty,
                            'cost' => $m_cost
                        ];
                    }
                }
            }
            
            $total_cost = $bulk_cost + $total_material_cost;
            $unit_cost_produced = $total_cost / $finished_qty;
            
            // 3. Create FINISHED product in purchasing
            $insFin = $conn->prepare("INSERT INTO purchasing (product_id, batch_number, type, purchase_price, quantity, total_price) VALUES (?, ?, 'FINISHED', ?, ?, ?)");
            $insFin->execute([$finished_product_id, $finished_batch, $unit_cost_produced, $finished_qty, $total_cost]);
            $finished_purchase_id = $conn->lastInsertId();
            
            // 4. Create packing_operations record
            $packing_cost = $total_cost - $bulk_cost; // The difference is exactly the material cost in this basic logic
            
            $insOp = $conn->prepare("INSERT INTO packing_operations (bulk_purchase_id, quantity_used, bulk_cost, finished_purchase_id, total_material_cost, packing_cost, selling_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insOp->execute([$bulk_id, $bulk_qty_used, $bulk_cost, $finished_purchase_id, $total_material_cost, $packing_cost, $selling_price]);
            $operation_id = $conn->lastInsertId();
            
            // 5. Insert materials used
            if (!empty($material_inserts)) {
                $insMat = $conn->prepare("INSERT INTO packing_materials_used (packing_operation_id, material_purchase_id, quantity_used, material_cost) VALUES (?, ?, ?, ?)");
                foreach ($material_inserts as $mi) {
                    $insMat->execute([$operation_id, $mi['id'], $mi['qty'], $mi['cost']]);
                }
            }
            
            $conn->commit();
            setFlashMessage('success', 'Packing operation completed successfully!');
            header('Location: packing.php');
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Packing Operation - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-box-open"></i> New Packing Operation</h1>
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
            
            <form method="POST" action="" id="packingForm">
                        <?php echo csrfField(); ?>
                
                <div class="data-card" style="margin-bottom: 20px;">
                    <div class="data-card-header">
                        <h2>Step 1: Select Bulk Raw Material</h2>
                    </div>
                    <div style="padding: 20px;">
                        <button type="button" class="btn btn-primary" onclick="selectBulk()"><i class="fas fa-search"></i> Select Available Bulk</button>
                        
                        <div id="bulk-details" style="margin-top: 15px; display: none; background: #e8f5e9; padding: 15px; border-radius: 8px;">
                            <h4 id="b_name" style="color: var(--primary-green); margin: 0 0 10px 0;"></h4>
                            <p style="margin: 0;"><strong>Batch:</strong> <span id="b_batch"></span> | <strong>Available Stock:</strong> <span id="b_stock"></span> | <strong>Unit Price: Rs</strong> <span id="b_price_disp"></span></p>
                            
                            <input type="hidden" name="bulk_purchase_id" id="bulk_purchase_id">
                            <input type="hidden" name="bulk_unit_price" id="bulk_unit_price">
                            <input type="hidden" id="max_bulk_qty">
                            
                            <div style="margin-top: 15px;">
                                <label class="form-label">Quantity to Use *</label>
                                <input type="number" name="bulk_quantity_used" id="bulk_quantity_used" class="form-input" step="0.01" style="max-width: 200px;" oninput="calcTotals()">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="data-card" style="margin-bottom: 20px;">
                    <div class="data-card-header">
                        <h2>Step 2: Define Finished Product</h2>
                    </div>
                    <div style="padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Finished Product Type *</label>
                            <select name="finished_product_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($allProducts as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo sanitize($p['name']) . ' (' . sanitize($p['packing_type']) . ')'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Batch Number * (Auto/Editable)</label>
                            <input type="text" name="finished_batch" id="finished_batch" class="form-input" required value="<?php echo 'PK-'.date('Ymd').'-'.rand(10,99); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity Produced *</label>
                            <input type="number" name="finished_qty" id="finished_qty" class="form-input" step="0.01" required oninput="calcTotals()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Planned Selling Price (Manual) Rs</label>
                            <input type="number" name="selling_price" class="form-input" step="0.01">
                        </div>
                    </div>
                </div>

                <div class="data-card" style="margin-bottom: 20px;">
                    <div class="data-card-header">
                        <h2>Step 3: Add Packing Materials</h2>
                    </div>
                    <div style="padding: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="addMaterial()"><i class="fas fa-plus"></i> Add Material</button>
                        
                        <table class="data-table" style="margin-top: 15px;" id="materialsTable">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Quantity Used</th>
                                    <th>Total Cost Rs</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Rows dynamic -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="data-card" style="margin-bottom: 20px; background: var(--dark-green); color: white;">
                    <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin:0 0 5px 0;">Calculations Overview</h3>
                            <p style="margin:0; opacity: 0.9;">Total Bulk Cost: Rs <span id="calc_bulk">0.00</span></p>
                            <p style="margin:0; opacity: 0.9;">Total Material Cost: Rs <span id="calc_mat">0.00</span></p>
                        </div>
                        <div style="text-align: right;">
                            <h2 style="margin:0;">Grand Total Cost: Rs <span id="calc_grand">0.00</span></h2>
                            <p style="margin:0; opacity: 0.9;">Cost per Unit Produced: Rs <span id="calc_unit">0.00</span></p>
                        </div>
                    </div>
                </div>

                <div style="padding-bottom: 50px;">
                    <button type="submit" class="btn btn-primary btn-lg" onclick="return validateSubmit()"><i class="fas fa-check"></i> Complete Packing Operation</button>
                </div>
                
            </form>
        </main>
    </div>
    
    <script>
        const bulkItems = <?php echo json_encode($bulkItems); ?>;
        const packItems = <?php echo json_encode($packingItems); ?>;
        let matIndex = 0;

        function selectBulk() {
            let options = {};
            bulkItems.forEach(i => {
                options[i.id] = `${i.name} (Batch: ${i.batch_number}) - Avail: ${i.quantity}`;
            });

            Swal.fire({
                title: 'Select Bulk Material',
                input: 'select',
                inputOptions: options,
                inputPlaceholder: 'Select a bulk material',
                showCancelButton: true
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const sel = bulkItems.find(i => i.id == result.value);
                    document.getElementById('b_name').innerText = sel.name;
                    document.getElementById('b_batch').innerText = sel.batch_number;
                    document.getElementById('b_stock').innerText = sel.quantity;
                    document.getElementById('b_price_disp').innerText = sel.purchase_price;
                    
                    document.getElementById('bulk_purchase_id').value = sel.id;
                    document.getElementById('bulk_unit_price').value = sel.purchase_price;
                    document.getElementById('max_bulk_qty').value = sel.quantity;
                    
                    document.getElementById('bulk-details').style.display = 'block';
                    calcTotals();
                }
            });
        }

        function addMaterial() {
            let options = {};
            packItems.forEach(i => {
                options[i.id] = `${i.name} (Batch: ${i.batch_number}) - Avail: ${i.quantity}`;
            });

            Swal.fire({
                title: 'Select Packing Material',
                input: 'select',
                inputOptions: options,
                inputPlaceholder: 'Select material',
                showCancelButton: true
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const sel = packItems.find(i => i.id == result.value);
                    
                    const tbody = document.querySelector('#materialsTable tbody');
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <strong>${sel.name}</strong> (Batch: ${sel.batch_number})
                            <input type="hidden" name="materials[${matIndex}][id]" value="${sel.id}">
                            <input type="hidden" name="materials[${matIndex}][price]" value="${sel.purchase_price}">
                        </td>
                        <td>
                            <input type="number" name="materials[${matIndex}][qty]" class="form-input mat-qty" step="0.01" min="0.01" max="${sel.quantity}" required placeholder="Max: ${sel.quantity}" oninput="calcTotals()">
                        </td>
                        <td class="mat-cost">0.00</td>
                        <td><button type="button" class="btn btn-secondary btn-sm" onclick="this.closest('tr').remove(); calcTotals();"><i class="fas fa-trash"></i></button></td>
                    `;
                    tbody.appendChild(tr);
                    matIndex++;
                }
            });
        }

        function calcTotals() {
            let bulkQty = parseFloat(document.getElementById('bulk_quantity_used').value) || 0;
            let bulkPrice = parseFloat(document.getElementById('bulk_unit_price').value) || 0;
            let bulkTotal = bulkQty * bulkPrice;
            
            let matTotal = 0;
            document.querySelectorAll('#materialsTable tbody tr').forEach(tr => {
                let qty = parseFloat(tr.querySelector('.mat-qty').value) || 0;
                let price = parseFloat(tr.querySelector('input[name*="[price]"]').value) || 0;
                let cost = qty * price;
                tr.querySelector('.mat-cost').innerText = cost.toFixed(2);
                matTotal += cost;
            });
            
            let grandTotal = bulkTotal + matTotal;
            let prodQty = parseFloat(document.getElementById('finished_qty').value) || 0;
            let unitCost = prodQty > 0 ? (grandTotal / prodQty) : 0;
            
            document.getElementById('calc_bulk').innerText = bulkTotal.toFixed(2);
            document.getElementById('calc_mat').innerText = matTotal.toFixed(2);
            document.getElementById('calc_grand').innerText = grandTotal.toFixed(2);
            document.getElementById('calc_unit').innerText = unitCost.toFixed(2);
        }

        function validateSubmit() {
            let bId = document.getElementById('bulk_purchase_id').value;
            if(!bId) {
                Swal.fire('Error', 'Please select a bulk material first!', 'error');
                return false;
            }
            let bQty = parseFloat(document.getElementById('bulk_quantity_used').value) || 0;
            let mQty = parseFloat(document.getElementById('max_bulk_qty').value) || 0;
            if(bQty <= 0 || bQty > mQty) {
                Swal.fire('Error', 'Invalid bulk quantity! Must be > 0 and <= ' + mQty, 'error');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
