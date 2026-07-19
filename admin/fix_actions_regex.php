<?php
function updateActions($path, $editPage) {
    if (!file_exists($path)) {
        echo "File not found: $path\n";
        return;
    }
    $content = file_get_contents($path);
    
    // Regex to find the delete link within action-btns and insert edit before it
    // Looks for action-btns div and then follows with any characters until the delete link
    $pattern = '/(<div class="action-btns">)(\s*)(<!--.*?-->\s*)?(<a href="[^"]*?delete[^"]*?".*?<\/a>)/s';
    
    // Check if edit already exists
    if (strpos($content, $editPage) !== false) {
        echo "Already updated: $path\n";
        return;
    }

    $replacement = '$1$2<a href="' . $editPage . '?id=<?php echo $id_var; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>$2$4';
    
    // Special handling for variable name in each file
    $id_var = 'id';
    if (strpos($path, 'employees.php') !== false) $id_var = 'emp[\'id\']';
    if (strpos($path, 'packing.php') !== false) $id_var = 'op[\'id\']';
    if (strpos($path, 'pr.php') !== false) $id_var = 'pr[\'id\']';
    
    $finalReplacement = str_replace('$id_var', $id_var, $replacement);
    
    $newContent = preg_replace($pattern, $finalReplacement, $content);
    
    if ($newContent !== $content) {
        file_put_contents($path, $newContent);
        echo "Updated: $path\n";
    } else {
        echo "Failed to match pattern in: $path\n";
    }
}

updateActions('F:/wamp64/www/agrovise/admin/employees.php', 'edit-employee.php');
updateActions('F:/wamp64/www/agrovise/admin/packing.php', 'edit-packing.php');
updateActions('F:/wamp64/www/agrovise/admin/pr.php', 'edit-pr.php');
