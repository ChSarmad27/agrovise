<?php
/**
 * AGROVISE - Add Employee
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('employees');

$conn = getDBConnection();
$errors = [];
$designationsList = $conn->query("SELECT title FROM designations ORDER BY title")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $salary = floatval($_POST['salary'] ?? 0);

    // System access (login account) fields
    $create_account = isset($_POST['create_account']);
    $acc_email    = strtolower(trim($_POST['acc_email'] ?? ''));
    $acc_username = strtolower(trim($_POST['acc_username'] ?? ''));
    $acc_password = trim($_POST['acc_password'] ?? '');
    $acc_role     = $_POST['acc_role'] ?? 'user';
    $acc_perms    = array_values(array_intersect(
        array_keys(adminModules()),
        (array)($_POST['perms'] ?? [])
    ));

    // Annual sales target (optional — typically for sales staff)
    $target_year   = intval($_POST['target_year'] ?? date('Y'));
    $target_amount = floatval($_POST['target_amount'] ?? 0);

    // Auto-insert dashes when the digit count is right (03001234567 -> 0300-1234567)
    $phone = normalizePhonePK($phone) ?? $phone;
    $cnic  = normalizeCNIC($cnic) ?? $cnic;

    // Validation
    if(empty($name)) $errors[] = "Name is required.";
    if(!preg_match('/^[0-9]{5}-[0-9]{7}-[0-9]$/', $cnic)) $errors[] = "CNIC must contain 13 digits (XXXXX-XXXXXXX-X).";
    if(!preg_match('/^\d{4}-\d{7}$/', $phone)) $errors[] = "Phone must contain 11 digits (0300-1234567).";
    if($salary < 0) $errors[] = "Salary cannot be negative.";
    if($target_amount < 0) $errors[] = "Sales target cannot be negative.";
    if($target_amount > 0 && ($target_year < intval(date('Y')) - 1 || $target_year > intval(date('Y')) + 1)) $errors[] = "Invalid target year.";

    if ($create_account) {
        if (!filter_var($acc_email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid account email is required.";
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $acc_username)) $errors[] = "Username must be 3-50 characters (letters, numbers, . _ -).";
        if (strlen($acc_password) < 6) $errors[] = "Account password must be at least 6 characters.";
        if (!in_array($acc_role, ['admin', 'user', 'sales'], true)) $errors[] = "Invalid account role.";
        if ($acc_role === 'user' && empty($acc_perms)) $errors[] = "Select at least one module for a User account (or make them a Sales Employee / Admin).";

        // Uniqueness check BEFORE inserting the employee (admins is MyISAM — no rollback)
        if (empty($errors)) {
            $chk = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR (email IS NOT NULL AND email <> '' AND email = ?)");
            $chk->execute([$acc_username, $acc_email]);
            if ($chk->fetchColumn() > 0) $errors[] = "That username or email is already taken.";
        }
    }

    if(empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO employees (name, cnic, phone, address, role, salary) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $cnic, $phone, $address, $role, $salary]);
            $employee_id = $conn->lastInsertId();

            if ($target_amount > 0) {
                $conn->prepare("INSERT INTO sales_targets (employee_id, target_year, target_amount) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE target_amount = VALUES(target_amount)")
                     ->execute([$employee_id, $target_year, $target_amount]);
            }

            if ($create_account) {
                // Sales Employee = a user with no modules (My Space + Expenses only)
                $storedRole  = ($acc_role === 'admin') ? 'admin' : 'user';
                $storedPerms = ($acc_role === 'admin') ? null
                             : (($acc_role === 'sales') ? json_encode([]) : json_encode($acc_perms));
                $roleLabel   = ($acc_role === 'admin') ? 'ADMIN'
                             : (($acc_role === 'sales') ? 'SALES EMPLOYEE (My Space + Expenses only)' : 'USER');

                $accStmt = $conn->prepare("INSERT INTO admins (username, email, password, role, permissions, employee_id) VALUES (?, ?, ?, ?, ?, ?)");
                $accStmt->execute([
                    $acc_username,
                    $acc_email,
                    password_hash($acc_password, PASSWORD_DEFAULT),
                    $storedRole,
                    $storedPerms,
                    $employee_id
                ]);
                setFlashMessage('success',
                    'Employee added with ' . $roleLabel . ' system access. Credentials — Email: '
                    . sanitize($acc_email) . ' &middot; Username: ' . sanitize($acc_username)
                    . ' &middot; Password: ' . sanitize($acc_password) . ' — save these now; the password is shown only once.');
            } else {
                setFlashMessage('success', 'Employee added successfully!');
            }
            header('Location: employees.php');
            exit;
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = "CNIC already registered to another employee (or duplicate account).";
            } else {
                $errors[] = dbError($e);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Employee - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header"><h1>Add Employee</h1></div>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                </ul>
            </div>
            <?php endif; ?>
            <div class="data-card" style="max-width: 600px;">
                <div style="padding: 20px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-input" required value="<?php echo sanitize($_POST['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">CNIC * (Format: XXXXX-XXXXXXX-X)</label>
                            <input type="text" name="cnic" class="form-input" required placeholder="12345-1234567-1" value="<?php echo sanitize($_POST['cnic'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone * (Format: 0300-1234567 — dash fills in automatically)</label>
                            <input type="text" name="phone" class="form-input" required placeholder="0300-1234567" value="<?php echo sanitize($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-textarea"><?php echo sanitize($_POST['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role / Designation *</label>
                            <select name="role" class="form-select" required>
                                <option value="">-- Select Designation --</option>
                                <?php foreach ($designationsList as $title): ?>
                                <option value="<?php echo sanitize($title); ?>" <?php echo (($_POST['role'] ?? '') === $title) ? 'selected' : ''; ?>><?php echo sanitize($title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color:#888;">Titles are managed by admins on the <a href="designations.php">Designations</a> page.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Salary (Rs)</label>
                            <input type="number" name="salary" class="form-input" step="0.01" required value="<?php echo sanitize($_POST['salary'] ?? ''); ?>">
                        </div>

                        <!-- ================= ANNUAL SALES TARGET ================= -->
                        <div style="border-top: 1px solid #e0e0e0; margin: 26px 0 20px; padding-top: 20px;">
                            <p style="font-weight:600; display:flex; align-items:center; gap:10px; margin:0;">
                                <i class="fas fa-bullseye" style="color: var(--primary-green);"></i> Annual Sales Target (optional)
                            </p>
                            <p style="font-size:0.8rem; color:#777; margin:6px 0 14px 28px;">Typically set for sales staff, but can be given to any employee. Progress shows on the Sales Targets page, the employee list, and the employee's own profile. Leave blank for no target.</p>
                            <div style="display:grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Target Year</label>
                                    <select name="target_year" class="form-select">
                                        <?php $yNow = intval(date('Y')); foreach ([$yNow - 1, $yNow, $yNow + 1] as $ty): ?>
                                        <option value="<?php echo $ty; ?>" <?php echo intval($_POST['target_year'] ?? $yNow) === $ty ? 'selected' : ''; ?>><?php echo $ty; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Target Amount (Rs)</label>
                                    <input type="number" name="target_amount" class="form-input" step="0.01" min="0" placeholder="e.g. 5000000" value="<?php echo sanitize($_POST['target_amount'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- ================= SYSTEM ACCESS ================= -->
                        <div style="border-top: 1px solid #e0e0e0; margin: 26px 0 20px; padding-top: 20px;">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600;">
                                <input type="checkbox" name="create_account" id="createAccount" <?php echo isset($_POST['create_account']) ? 'checked' : ''; ?>>
                                <i class="fas fa-key" style="color: var(--primary-green);"></i> Create system login for this employee
                            </label>
                            <p style="font-size:0.8rem; color:#777; margin:6px 0 0 28px;">Generates an email, username and password the employee can use to sign in to this panel.</p>
                        </div>

                        <div id="accountSection" style="display:none; background:#f7f9f6; border:1px solid #e0e6dd; border-radius:10px; padding:18px; margin-bottom:20px;">
                            <div class="form-group">
                                <label class="form-label">Account Email *</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="email" name="acc_email" id="accEmail" class="form-input" placeholder="name@agrovise.com" value="<?php echo sanitize($_POST['acc_email'] ?? ''); ?>">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="genEmail()" title="Generate from name"><i class="fas fa-wand-magic-sparkles"></i> Generate</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Username *</label>
                                <input type="text" name="acc_username" id="accUsername" class="form-input" placeholder="e.g. ali.khan" value="<?php echo sanitize($_POST['acc_username'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password * (min 6 chars)</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="text" name="acc_password" id="accPassword" class="form-input" autocomplete="new-password" value="<?php echo sanitize($_POST['acc_password'] ?? ''); ?>">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="genPassword()" title="Generate secure password"><i class="fas fa-dice"></i> Generate</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Access Level *</label>
                                <select name="acc_role" id="accRole" class="form-select">
                                    <option value="sales" <?php echo (($_POST['acc_role'] ?? '') === 'sales') ? 'selected' : ''; ?>>Sales Employee — My Space &amp; Expenses only</option>
                                    <option value="user" <?php echo (($_POST['acc_role'] ?? 'user') === 'user') ? 'selected' : ''; ?>>User — access only the modules selected below</option>
                                    <option value="admin" <?php echo (($_POST['acc_role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin — full access to everything</option>
                                </select>
                            </div>
                            <div id="permsSection">
                                <label class="form-label">Allowed Modules (for User accounts)</label>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                                    <?php $postedPerms = (array)($_POST['perms'] ?? []); ?>
                                    <?php foreach (adminModules() as $mkey => $mlabel): ?>
                                    <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; background:#fff; border:1px solid #e0e6dd; border-radius:8px; padding:9px 12px; cursor:pointer;">
                                        <input type="checkbox" name="perms[]" value="<?php echo $mkey; ?>" <?php echo in_array($mkey, $postedPerms) ? 'checked' : ''; ?>>
                                        <?php echo sanitize($mlabel); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <p style="font-size:0.78rem; color:#777; margin-top:8px;"><i class="fas fa-info-circle"></i> Employee &amp; account management is always admin-only and cannot be granted to Users.</p>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Employee</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    const createChk = document.getElementById('createAccount');
    const accSection = document.getElementById('accountSection');
    const roleSel = document.getElementById('accRole');
    const permsSection = document.getElementById('permsSection');

    function syncAccountUI() {
        accSection.style.display = createChk.checked ? 'block' : 'none';
        permsSection.style.display = roleSel.value === 'user' ? 'block' : 'none';
    }
    createChk.addEventListener('change', syncAccountUI);
    roleSel.addEventListener('change', syncAccountUI);
    syncAccountUI();

    function slugName() {
        const name = (document.querySelector('input[name="name"]').value || '').trim().toLowerCase();
        return name.replace(/[^a-z\s]/g, '').split(/\s+/).filter(Boolean).slice(0, 2).join('.');
    }

    function genEmail() {
        const slug = slugName();
        if (!slug) { alert('Enter the employee name first.'); return; }
        document.getElementById('accEmail').value = slug + '@agrovise.com';
        if (!document.getElementById('accUsername').value) {
            document.getElementById('accUsername').value = slug;
        }
    }

    function genPassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        let p = '';
        const buf = new Uint32Array(10);
        crypto.getRandomValues(buf);
        for (let i = 0; i < 10; i++) p += chars[buf[i] % chars.length];
        document.getElementById('accPassword').value = p;
    }
    </script>
    <script src="../assets/js/input-formats.js"></script>
</body>
</html>
