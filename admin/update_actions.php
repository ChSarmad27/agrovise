<?php
function updateFile($path, $search, $replace) {
    if (!file_exists($path)) {
        echo "File not found: $path\n";
        return;
    }
    $content = file_get_contents($path);
    if (strpos($content, $replace) !== false) {
        echo "Already updated: $path\n";
        return;
    }
    $newContent = str_replace($search, $replace, $content);
    if ($newContent === $content) {
        // Try without exact whitespace
        $pattern = '/' . preg_quote($search, '/') . '/s';
        $newContent = preg_replace($pattern, $replace, $content);
    }
    
    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        echo "Updated: $path\n";
    } else {
        echo "Failed to match: $path\n";
    }
}

// 1. Employees
$searchEmp = '<div class="action-btns">
                                        <!-- Optional Edit -->
                                        <a href="?delete=<?php echo $emp[\'id\']; ?>" class="btn-icon delete"';
$replaceEmp = '<div class="action-btns">
                                        <a href="edit-employee.php?id=<?php echo $emp[\'id\']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $emp[\'id\']; ?>" class="btn-icon delete"';
updateFile('F:/wamp64/www/agrovise/admin/employees.php', $searchEmp, $replaceEmp);

// 2. Packing
$searchPack = '<div class="action-btns">
                                            <a href="delete-packing.php?id=<?php echo $op[\'id\']; ?>" class="btn-icon delete"';
$replacePack = '<div class="action-btns">
                                            <a href="edit-packing.php?id=<?php echo $op[\'id\']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="delete-packing.php?id=<?php echo $op[\'id\']; ?>" class="btn-icon delete"';
updateFile('F:/wamp64/www/agrovise/admin/packing.php', $searchPack, $replacePack);

// 3. PR Receipts
$searchPR = '<div class="action-btns">
                                        <a href="?delete=<?php echo $pr[\'id\']; ?>" class="btn-icon delete"';
$replacePR = '<div class="action-btns">
                                        <a href="edit-pr.php?id=<?php echo $pr[\'id\']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="?delete=<?php echo $pr[\'id\']; ?>" class="btn-icon delete"';
updateFile('F:/wamp64/www/agrovise/admin/pr.php', $searchPR, $replacePR);
