<?php
/**
 * AGROVISE - Edit Employee
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('employees');

$conn = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$errors = [];

$designationsList = $conn->query("SELECT title FROM designations ORDER BY title")->fetchAll(PDO::FETCH_COLUMN);

$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: employees.php');
    exit;
}

// Linked system account (if any)
$accStmt = $conn->prepare("SELECT * FROM admins WHERE employee_id = ? LIMIT 1");
$accStmt->execute([$id]);
$account = $accStmt->fetch();

$adminCount = intval($conn->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'")->fetchColumn());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $cnic = trim($_POST['cnic'] ?? '');
    $salary = floatval($_POST['salary'] ?? 0);

    // Auto-insert dashes when the digit count is right (legacy values pass through unchanged)
    $phone = normalizePhonePK($phone) ?? $phone;
    $cnic  = normalizeCNIC($cnic) ?? $cnic;

    // Account management fields
    $create_account = isset($_POST['create_account']);
    $remove_account = isset($_POST['remove_account']);
    $acc_email    = strtolower(trim($_POST['acc_email'] ?? ''));
    $acc_username = strtolower(trim($_POST['acc_username'] ?? ''));
    $acc_password = trim($_POST['acc_password'] ?? '');   // new/reset password (optional on existing account)
    $acc_role     = $_POST['acc_role'] ?? 'user';
    $acc_perms    = array_values(array_intersect(
        array_keys(adminModules()),
        (array)($_POST['perms'] ?? [])
    ));
    // "Sales Employee" access level = a user with no modules (My Space + Expenses only)
    $storedRole     = ($acc_role === 'admin') ? 'admin' : 'user';
    $effectivePerms = ($acc_role === 'sales') ? [] : $acc_perms;

    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($role)) $errors[] = 'Role is required.';

    $isLastAdmin = $account && $account['role'] === 'admin' && $adminCount <= 1;

    if ($account) {
        if ($remove_account) {
            if ($isLastAdmin) $errors[] = 'Cannot remove this account: it is the only Admin account in the system.';
        } else {
            if (!filter_var($acc_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid account email is required.';
            if (!in_array($acc_role, ['admin', 'user', 'sales'], true)) $errors[] = 'Invalid account role.';
            if ($isLastAdmin && $acc_role !== 'admin') $errors[] = 'Cannot demote this account: it is the only Admin account in the system.';
            if ($acc_role === 'user' && empty($acc_perms)) $errors[] = 'Select at least one module for a User account (or make them a Sales Employee / Admin).';
            if ($acc_password !== '' && strlen($acc_password) < 6) $errors[] = 'New password must be at least 6 characters.';
            if (empty($errors)) {
                $chk = $conn->prepare("SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?");
                $chk->execute([$acc_email, $account['id']]);
                if ($chk->fetchColumn() > 0) $errors[] = 'That email is already used by another account.';
            }
        }
    } elseif ($create_account) {
        if (!filter_var($acc_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid account email is required.';
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $acc_username)) $errors[] = 'Username must be 3-50 characters (letters, numbers, . _ -).';
        if (strlen($acc_password) < 6) $errors[] = 'Account password must be at least 6 characters.';
        if (!in_array($acc_role, ['admin', 'user', 'sales'], true)) $errors[] = 'Invalid account role.';
        if ($acc_role === 'user' && empty($acc_perms)) $errors[] = 'Select at least one module for a User account (or make them a Sales Employee / Admin).';
        if (empty($errors)) {
            $chk = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR (email IS NOT NULL AND email <> '' AND email = ?)");
            $chk->execute([$acc_username, $acc_email]);
            if ($chk->fetchColumn() > 0) $errors[] = 'That username or email is already taken.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE employees SET name = ?, role = ?, phone = ?, cnic = ?, salary = ? WHERE id = ?");
            $stmt->execute([$name, $role, $phone, $cnic, $salary, $id]);

            $accMsg = '';
            if ($account && $remove_account) {
                $conn->prepare("DELETE FROM admins WHERE id = ?")->execute([$account['id']]);
                $accMsg = ' System access removed.';
            } elseif ($account) {
                $permsJson = ($storedRole === 'admin') ? null : json_encode($effectivePerms);
                if ($acc_password !== '') {
                    $u = $conn->prepare("UPDATE admins SET email = ?, role = ?, permissions = ?, password = ? WHERE id = ?");
                    $u->execute([$acc_email, $storedRole, $permsJson, password_hash($acc_password, PASSWORD_DEFAULT), $account['id']]);
                    $accMsg = ' Account updated; new password: ' . sanitize($acc_password) . ' (shown only once).';
                } else {
                    $u = $conn->prepare("UPDATE admins SET email = ?, role = ?, permissions = ? WHERE id = ?");
                    $u->execute([$acc_email, $storedRole, $permsJson, $account['id']]);
                    $accMsg = ' Account access updated.';
                }
                // If the edited account is the one logged in, refresh its session immediately
                if (intval($_SESSION['admin_id']) === intval($account['id'])) {
                    $_SESSION['admin_role'] = $storedRole;
                    $_SESSION['admin_permissions'] = ($storedRole === 'admin') ? [] : $effectivePerms;
                }
            } elseif ($create_account) {
                $ins = $conn->prepare("INSERT INTO admins (username, email, password, role, permissions, employee_id) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $acc_username, $acc_email,
                    password_hash($acc_password, PASSWORD_DEFAULT),
                    $storedRole,
                    ($storedRole === 'admin') ? null : json_encode($effectivePerms),
                    $id
                ]);
                $accMsg = ' Login created — Username: ' . sanitize($acc_username) . ' &middot; Password: ' . sanitize($acc_password) . ' (shown only once).';
            }

            setFlashMessage('success', 'Employee updated successfully!' . $accMsg);
            header('Location: employees.php');
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
    <title>Edit Employee - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <div class="admin-header"><h1>Edit Employee: <?php echo sanitize($employee['name']); ?></h1></div>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
            </div>
            <?php endif; ?>
            
            <div class="data-card" style="max-width: 600px;">
                <div style="padding: 25px;">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-input" required value="<?php echo sanitize($employee['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role / Designation *</label>
                            <select name="role" class="form-select" required>
                                <?php if ($employee['role'] !== '' && !in_array($employee['role'], $designationsList, true)): ?>
                                <option value="<?php echo sanitize($employee['role']); ?>" selected><?php echo sanitize($employee['role']); ?> (legacy title)</option>
                                <?php endif; ?>
                                <?php foreach ($designationsList as $title): ?>
                                <option value="<?php echo sanitize($title); ?>" <?php echo $employee['role'] === $title ? 'selected' : ''; ?>><?php echo sanitize($title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-input" value="<?php echo sanitize($employee['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">CNIC</label>
                                <input type="text" name="cnic" class="form-input" value="<?php echo sanitize($employee['cnic']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Salary (Monthly) Rs. *</label>
                            <input type="number" step="0.01" name="salary" class="form-input" required value="<?php echo $employee['salary']; ?>">
                        </div>

                        <!-- ================= SYSTEM ACCESS ================= -->
                        <div style="border-top: 1px solid #e0e0e0; margin: 26px 0 20px; padding-top: 20px;">
                            <?php if ($account): ?>
                                <p style="font-weight:600; display:flex; align-items:center; gap:10px;">
                                    <i class="fas fa-key" style="color: var(--primary-green);"></i>
                                    System access: <span style="color:<?php echo $account['role']==='admin' ? '#2e7d32' : '#1565c0'; ?>;"><?php echo strtoupper($account['role']); ?></span>
                                    <span style="font-weight:400; color:#777;">(username: <?php echo sanitize($account['username']); ?>)</span>
                                </p>
                            <?php else: ?>
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600;">
                                    <input type="checkbox" name="create_account" id="createAccount">
                                    <i class="fas fa-key" style="color: var(--primary-green);"></i> Create system login for this employee
                                </label>
                            <?php endif; ?>
                        </div>

                        <div id="accountSection" style="display:none; background:#f7f9f6; border:1px solid #e0e6dd; border-radius:10px; padding:18px; margin-bottom:20px;">
                            <div class="form-group">
                                <label class="form-label">Account Email *</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="email" name="acc_email" id="accEmail" class="form-input" placeholder="name@agrovise.com" value="<?php echo sanitize($account['email'] ?? ($_POST['acc_email'] ?? '')); ?>">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="genEmail()"><i class="fas fa-wand-magic-sparkles"></i> Generate</button>
                                </div>
                            </div>
                            <?php if (!$account): ?>
                            <div class="form-group">
                                <label class="form-label">Username *</label>
                                <input type="text" name="acc_username" id="accUsername" class="form-input" placeholder="e.g. ali.khan" value="<?php echo sanitize($_POST['acc_username'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <div class="form-group">
                                <label class="form-label"><?php echo $account ? 'Reset Password (leave blank to keep current)' : 'Password * (min 6 chars)'; ?></label>
                                <div style="display:flex; gap:8px;">
                                    <input type="text" name="acc_password" id="accPassword" class="form-input" autocomplete="new-password" value="">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="genPassword()"><i class="fas fa-dice"></i> Generate</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Access Level *</label>
                                <?php
                                    // Determine the current access level for the dropdown:
                                    // a 'user' account with no modules is a "Sales Employee".
                                    $curLevel = 'user';
                                    if ($account) {
                                        if ($account['role'] === 'admin') {
                                            $curLevel = 'admin';
                                        } else {
                                            $dec = json_decode($account['permissions'] ?? '', true);
                                            $curLevel = (is_array($dec) && count($dec) > 0) ? 'user' : 'sales';
                                        }
                                    }
                                    $accRoleSel = $_POST['acc_role'] ?? $curLevel;
                                ?>
                                <select name="acc_role" id="accRole" class="form-select">
                                    <option value="sales" <?php echo $accRoleSel === 'sales' ? 'selected' : ''; ?>>Sales Employee — My Space &amp; Expenses only</option>
                                    <option value="user" <?php echo $accRoleSel === 'user' ? 'selected' : ''; ?>>User — access only the modules selected below</option>
                                    <option value="admin" <?php echo $accRoleSel === 'admin' ? 'selected' : ''; ?>>Admin — full access to everything</option>
                                </select>
                            </div>
                            <div id="permsSection">
                                <label class="form-label">Allowed Modules (for User accounts)</label>
                                <?php
                                    $savedPerms = [];
                                    if ($account && !empty($account['permissions'])) {
                                        $decoded = json_decode($account['permissions'], true);
                                        if (is_array($decoded)) $savedPerms = $decoded;
                                    }
                                    if (!empty($_POST['perms'])) $savedPerms = (array)$_POST['perms'];
                                ?>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                                    <?php foreach (adminModules() as $mkey => $mlabel): ?>
                                    <label style="display:flex; align-items:center; gap:8px; font-size:0.85rem; background:#fff; border:1px solid #e0e6dd; border-radius:8px; padding:9px 12px; cursor:pointer;">
                                        <input type="checkbox" name="perms[]" value="<?php echo $mkey; ?>" <?php echo in_array($mkey, $savedPerms) ? 'checked' : ''; ?>>
                                        <?php echo sanitize($mlabel); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <p style="font-size:0.78rem; color:#777; margin-top:8px;"><i class="fas fa-info-circle"></i> Employee &amp; account management is always admin-only and cannot be granted to Users.</p>
                            </div>
                            <?php if ($account): ?>
                            <div style="border-top:1px dashed #d0d6cd; margin-top:16px; padding-top:14px;">
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; color:#b3261e; font-size:0.85rem;">
                                    <input type="checkbox" name="remove_account" onclick="return this.checked ? confirm('Remove this employee\'s login access?') : true;">
                                    <i class="fas fa-user-slash"></i> Remove system access for this employee
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            <a href="employees.php" class="btn btn-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    const hasAccount = <?php echo $account ? 'true' : 'false'; ?>;
    const createChk = document.getElementById('createAccount');
    const accSection = document.getElementById('accountSection');
    const roleSel = document.getElementById('accRole');
    const permsSection = document.getElementById('permsSection');

    function syncAccountUI() {
        accSection.style.display = (hasAccount || (createChk && createChk.checked)) ? 'block' : 'none';
        permsSection.style.display = roleSel.value === 'user' ? 'block' : 'none';
    }
    if (createChk) createChk.addEventListener('change', syncAccountUI);
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
        const u = document.getElementById('accUsername');
        if (u && !u.value) u.value = slug;
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
