<?php
/**
 * AGROVISE - Pack Sizes
 * Catalog of packing definitions used by the packing page: how much product
 * goes in one bottle (ml / L) or bag (gm / kg), and how many of those packs
 * fit in a carton. E.g. "100 ml Bottle — 50 per carton".
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('packing');

$conn = getDBConnection();
$errors = [];

$unitByContainer = ['Bottle' => ['ml', 'L'], 'Bag' => ['gm', 'kg']];

// Add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $container = $_POST['container'] ?? '';
    $size_value = floatval($_POST['size_value'] ?? 0);
    $size_unit = $_POST['size_unit'] ?? '';
    $ppc = intval($_POST['packs_per_carton'] ?? 0);

    if (!isset($unitByContainer[$container])) $errors[] = 'Choose Bottle or Bag.';
    elseif (!in_array($size_unit, $unitByContainer[$container], true)) $errors[] = 'Bottles are measured in ml / L, bags in gm / kg.';
    if ($size_value <= 0) $errors[] = 'Quantity per pack must be greater than zero.';
    if ($ppc <= 0) $errors[] = 'Packs per carton must be greater than zero.';

    if (empty($errors)) {
        try {
            $conn->prepare("INSERT INTO pack_sizes (container, size_value, size_unit, packs_per_carton) VALUES (?, ?, ?, ?)")
                 ->execute([$container, $size_value, $size_unit, $ppc]);
            setFlashMessage('success', 'Pack size added: ' . rtrim(rtrim(number_format($size_value, 2), '0'), '.') . " $size_unit $container — $ppc per carton.");
            header('Location: pack-sizes.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000 ? 'That pack size already exists.' : dbError($e);
        }
    }
}

// Delete (past packing operations keep their stored pack label snapshot)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $conn->prepare("DELETE FROM pack_sizes WHERE id = ?")->execute([intval($_GET['delete'])]);
    setFlashMessage('success', 'Pack size removed. Past packing operations keep their recorded pack details.');
    header('Location: pack-sizes.php');
    exit;
}

$packSizes = $conn->query("
    SELECT ps.*, (SELECT COUNT(*) FROM packing_operations po WHERE po.pack_size_id = ps.id) AS in_use
    FROM pack_sizes ps
    ORDER BY ps.container, ps.size_unit, ps.size_value
")->fetchAll();

$flash = getFlashMessage();
$fmt = fn($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pack Sizes - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-boxes-packing"></i> Pack Sizes</h1></div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><ul style="margin:0; padding-left:20px;"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul></div>
            <?php endif; ?>

            <div style="display:grid; grid-template-columns: 1fr 1.4fr; gap:20px; align-items:start;">
                <div class="data-card">
                    <div class="data-card-header"><h2>Add Pack Size</h2></div>
                    <div style="padding:20px;">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label class="form-label">Container *</label>
                                <select name="container" id="containerSel" class="form-select" required onchange="syncUnits()">
                                    <option value="Bottle" <?php echo ($_POST['container'] ?? '') === 'Bottle' ? 'selected' : ''; ?>>Bottle (liquid)</option>
                                    <option value="Bag" <?php echo ($_POST['container'] ?? '') === 'Bag' ? 'selected' : ''; ?>>Bag (solid)</option>
                                </select>
                            </div>
                            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:14px;">
                                <div class="form-group">
                                    <label class="form-label">Quantity per Pack *</label>
                                    <input type="number" name="size_value" class="form-input" step="0.01" min="0.01" required placeholder="e.g. 100" value="<?php echo sanitize($_POST['size_value'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Unit *</label>
                                    <select name="size_unit" id="unitSel" class="form-select" required></select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Packs per Carton *</label>
                                <input type="number" name="packs_per_carton" class="form-input" step="1" min="1" required placeholder="e.g. 50" value="<?php echo sanitize($_POST['packs_per_carton'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Pack Size</button>
                        </form>
                        <p style="font-size:0.8rem; color:#777; margin-top:14px;"><i class="fas fa-info-circle"></i> Example: a <strong>100 ml Bottle</strong> with <strong>50 per carton</strong> means packing 200 L of bulk product fills 2,000 bottles = 40 cartons. These options appear on the New Packing Operation page.</p>
                    </div>
                </div>

                <div class="data-card">
                    <div class="data-card-header"><h2>Defined Pack Sizes</h2></div>
                    <div style="padding:20px;">
                        <?php if (empty($packSizes)): ?>
                        <p style="color:#8a8f83; text-align:center; padding:20px 0;">No pack sizes defined yet — add the bottle and bag sizes you pack into.</p>
                        <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Pack</th>
                                    <th>Quantity per Pack</th>
                                    <th>Packs per Carton</th>
                                    <th>Used In</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packSizes as $ps): ?>
                                <tr>
                                    <td>
                                        <span class="badge" style="background:<?php echo $ps['container'] === 'Bottle' ? '#e3f2fd; color:#1565c0' : '#efebe9; color:#5d4037'; ?>;">
                                            <i class="fas fa-<?php echo $ps['container'] === 'Bottle' ? 'wine-bottle' : 'bag-shopping'; ?>"></i>
                                            <?php echo $ps['container']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $fmt($ps['size_value']); ?> <?php echo $ps['size_unit']; ?></strong></td>
                                    <td><?php echo intval($ps['packs_per_carton']); ?> / carton</td>
                                    <td><span class="badge" style="background:#f1f1f1; color:#555;"><?php echo intval($ps['in_use']); ?> operation<?php echo intval($ps['in_use']) == 1 ? '' : 's'; ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="?delete=<?php echo $ps['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Remove this pack size from the list? Past packing operations keep their recorded details.');"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    const unitByContainer = <?php echo json_encode($unitByContainer); ?>;
    const postedUnit = <?php echo json_encode($_POST['size_unit'] ?? ''); ?>;
    function syncUnits() {
        const container = document.getElementById('containerSel').value;
        const unitSel = document.getElementById('unitSel');
        unitSel.innerHTML = '';
        (unitByContainer[container] || []).forEach(u => {
            const o = document.createElement('option');
            o.value = u;
            o.textContent = u === 'ml' ? 'ml (millilitres)' : u === 'L' ? 'L (litres)' : u === 'gm' ? 'gm (grams)' : 'kg (kilograms)';
            if (u === postedUnit) o.selected = true;
            unitSel.appendChild(o);
        });
    }
    syncUnits();
    </script>
</body>
</html>
