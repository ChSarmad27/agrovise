<?php
$file = 'F:/wamp64/www/agrovise/admin/add-transaction.php';
$content = file_get_contents($file);

// 1. Update fetching logic
$oldFetch = '$accounts = $conn->query("SELECT id, account_name, balance FROM banking ORDER BY account_name")->fetchAll();';
$newFetch = '$accounts = $conn->query("SELECT id, account_name, balance FROM banking ORDER BY account_name")->fetchAll();
$vendors = $conn->query("SELECT id, name FROM vendors ORDER BY name")->fetchAll();
$employees = $conn->query("SELECT id, name FROM employees ORDER BY name")->fetchAll();
$clients = $conn->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();';

$content = str_replace($oldFetch, $newFetch, $content);

// 2. Update POST logic
$oldPost = '$bank_id = intval($_POST[\'bank_id\'] ?? 0);';
$newPost = '$bank_id = intval($_POST[\'bank_id\'] ?? 0);
    $vendor_id = !empty($_POST[\'vendor_id\']) ? intval($_POST[\'vendor_id\']) : null;
    $employee_id = !empty($_POST[\'employee_id\']) ? intval($_POST[\'employee_id\']) : null;
    $client_id = !empty($_POST[\'client_id\']) ? intval($_POST[\'client_id\']) : null;';

$content = str_replace($oldPost, $newPost, $content);

// 3. Update SQL Insert
$oldSql = '$stmt = $conn->prepare("INSERT INTO transactions (bank_id, type, category, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bank_id, $type, $category, $amount, $description, $transaction_date]);';
$newSql = '$stmt = $conn->prepare("INSERT INTO transactions (bank_id, vendor_id, employee_id, client_id, type, category, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bank_id, $vendor_id, $employee_id, $client_id, $type, $category, $amount, $description, $transaction_date]);';

$content = str_replace($oldSql, $newSql, $content);

// 4. Update Category list in UI (Add Client Payment back)
$oldCats = '<option value="Purchasing">Purchasing / Supplier Payment</option>
                                    <option value="Salaries">Salaries</option>';
$newCats = '<option value="Purchasing">Purchasing / Supplier Payment</option>
                                    <option value="Salaries">Salaries</option>
                                    <option value="Client Payment">Client Payment</option>';
$content = str_replace($oldCats, $newCats, $content);

// 5. Add Entity Dropdowns before Description
$entityDropdowns = '
                        <div id="vendor_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Vendor / Supplier *</label>
                            <select name="vendor_id" class="form-select">
                                <option value="">-- Select Vendor --</option>
                                <?php foreach($vendors as $v): ?>
                                <option value="<?php echo $v[\'id\']; ?>"><?php echo sanitize($v[\'name\']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="employee_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Employee *</label>
                            <select name="employee_id" class="form-select">
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employees as $e): ?>
                                <option value="<?php echo $e[\'id\']; ?>"><?php echo sanitize($e[\'name\']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="client_select" class="form-group entity-select" style="display:none;">
                            <label class="form-label">Select Client *</label>
                            <select name="client_id" class="form-select">
                                <option value="">-- Select Client --</option>
                                <?php foreach($clients as $c): ?>
                                <option value="<?php echo $c[\'id\']; ?>"><?php echo sanitize($c[\'name\']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>';

$content = str_replace('<div class="form-group">', $entityDropdowns . "\n" . '                        <div class="form-group">', $content);

// 6. Add JS Toggle
$jsToggle = '
    <script>
    document.querySelector(\'select[name="category"]\').addEventListener(\'change\', function() {
        // Hide all
        document.querySelectorAll(\'.entity-select\').forEach(el => el.style.display = \'none\');
        document.querySelectorAll(\'.entity-select select\').forEach(el => el.required = false);

        const category = this.value;
        if (category === \'Purchasing\') {
            document.getElementById(\'vendor_select\').style.display = \'block\';
            document.querySelector(\'#vendor_select select\').required = true;
        } else if (category === \'Salaries\') {
            document.getElementById(\'employee_select\').style.display = \'block\';
            document.querySelector(\'#employee_select select\').required = true;
        } else if (category === \'Client Payment\') {
            document.getElementById(\'client_select\').style.display = \'block\';
            document.querySelector(\'#client_select select\').required = true;
        }
    });
    </script>
';
$content = str_replace('</body>', $jsToggle . '</body>', $content);

file_put_contents($file, $content);
echo "add-transaction.php updated successfully!\n";
