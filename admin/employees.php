<?php
/**
 * AGROVISE - Employees
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('employees');

$conn = getDBConnection();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        // If the employee has a linked login, remove it too — unless it is the last Admin account.
        $acc = $conn->prepare("SELECT id, role FROM admins WHERE employee_id = ? LIMIT 1");
        $acc->execute([$id]);
        $account = $acc->fetch();
        if ($account && $account['role'] === 'admin') {
            $adminCount = intval($conn->query("SELECT COUNT(*) FROM admins WHERE role = 'admin'")->fetchColumn());
            if ($adminCount <= 1) {
                setFlashMessage('error', 'Cannot delete: this employee holds the only Admin account. Transfer admin access first.');
                header('Location: employees.php');
                exit;
            }
        }
        if ($account) {
            $conn->prepare("DELETE FROM notifications WHERE admin_id = ?")->execute([$account['id']]);
            $conn->prepare("DELETE FROM admins WHERE id = ?")->execute([$account['id']]);
        }
        // Fleet & expenses cleanup (employees is MyISAM, so no FK cascade)
        $conn->prepare("UPDATE vehicles SET assigned_employee_id = NULL WHERE assigned_employee_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM expense_claims WHERE employee_id = ?")->execute([$id]);
        $conn->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
        setFlashMessage('success', 'Employee deleted successfully.' . ($account ? ' Linked login removed.' : ''));
    } catch(PDOException $e) {
        setFlashMessage('error', 'Error deleting employee (check dependencies).');
    }
    header('Location: employees.php');
    exit;
}

$stmt = $conn->query("
    SELECT e.*, a.role as access_role, a.username as access_username
    FROM employees e
    LEFT JOIN admins a ON a.employee_id = e.id
    ORDER BY e.created_at DESC
");
$employees = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-id-badge"></i> HR / Employees</h1></div>
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            <div class="data-card">
                <div class="data-card-header">
                    <h2>Employee List</h2>
                    <a href="add-employee.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Employee</a>
                </div>
                <div style="padding: 20px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>CNIC</th>
                                <th>Salary (Rs)</th>
                                <th>System Access</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><strong><?php echo sanitize($emp['name']); ?></strong></td>
                                <td><span class="badge" style="background:#e9ecef; color:#333;"><?php echo sanitize($emp['role']); ?></span></td>
                                <td><?php echo sanitize($emp['phone']); ?></td>
                                <td><?php echo sanitize($emp['cnic']); ?></td>
                                <td><strong><?php echo number_format($emp['salary'], 2); ?></strong></td>
                                <td>
                                    <?php if ($emp['access_role'] === 'admin'): ?>
                                        <span class="badge" style="background:#e8f5e9; color:#2e7d32;" title="<?php echo sanitize($emp['access_username']); ?>"><i class="fas fa-shield-halved"></i> Admin</span>
                                    <?php elseif ($emp['access_role'] === 'user'): ?>
                                        <span class="badge" style="background:#e3f2fd; color:#1565c0;" title="<?php echo sanitize($emp['access_username']); ?>"><i class="fas fa-user"></i> User</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f1f1f1; color:#888;">No login</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit-employee.php?id=<?php echo $emp['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $emp['id']; ?>" class="btn-icon delete" onclick="return confirm('Delete employee records?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
