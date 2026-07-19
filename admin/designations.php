<?php
/**
 * AGROVISE - Employee Designations (Titles)
 * Admin-only: the 'employees' module is never grantable to Users.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('employees');

$conn = getDBConnection();
$errors = [];

// Add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    if ($title === '' || strlen($title) > 100) {
        $errors[] = 'Enter a designation title (max 100 characters).';
    } else {
        try {
            $conn->prepare("INSERT INTO designations (title) VALUES (?)")->execute([$title]);
            setFlashMessage('success', 'Designation "' . sanitize($title) . '" added.');
            header('Location: designations.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000 ? 'That designation already exists.' : dbError($e);
        }
    }
}

// Delete (employees keep their stored title text; this only removes it from the dropdown)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $conn->prepare("DELETE FROM designations WHERE id = ?")->execute([intval($_GET['delete'])]);
    setFlashMessage('success', 'Designation removed from the list.');
    header('Location: designations.php');
    exit;
}

$designations = $conn->query("
    SELECT d.*, (SELECT COUNT(*) FROM employees e WHERE e.role = d.title) as in_use
    FROM designations d ORDER BY d.title
")->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Designations - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-user-tag"></i> Employee Designations</h1></div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><ul style="margin:0; padding-left:20px;"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul></div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: 1fr 1.4fr; gap:20px; align-items:start;">
                <div class="data-card">
                    <div class="data-card-header"><h2>Add Designation</h2></div>
                    <div style="padding:20px;">
                        <form method="POST">
                        <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label class="form-label">Title *</label>
                                <input type="text" name="title" class="form-input" required maxlength="100" placeholder="e.g. Zonal Sales Manager">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Title</button>
                        </form>
                        <p style="font-size:0.8rem; color:#777; margin-top:14px;"><i class="fas fa-info-circle"></i> These titles appear in the Role dropdown when adding or editing an employee. Only Admins can manage this list.</p>
                    </div>
                </div>

                <div class="data-card">
                    <div class="data-card-header"><h2>Current Titles</h2></div>
                    <div style="padding:20px;">
                        <table class="data-table">
                            <thead><tr><th>Title</th><th>Employees Holding It</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($designations as $d): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($d['title']); ?></strong></td>
                                    <td><?php echo intval($d['in_use']); ?></td>
                                    <td>
                                        <a href="?delete=<?php echo $d['id']; ?>" class="btn-icon delete" title="Remove"
                                           onclick="return confirm('Remove this title from the dropdown?<?php echo $d['in_use'] > 0 ? ' ' . intval($d['in_use']) . ' employee(s) currently hold it — they keep the title text.' : ''; ?>');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
