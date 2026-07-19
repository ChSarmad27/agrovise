<?php
/**
 * AGROVISE - Delete Packing Operation
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('packing');

$conn = getDBConnection();

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $conn->beginTransaction();

        // 1. Get operation details
        $stmt = $conn->prepare("SELECT * FROM packing_operations WHERE id = ?");
        $stmt->execute([$id]);
        $op = $stmt->fetch();

        if (!$op) throw new Exception("Operation not found.");

        // 2. Check if the finished product has been sold
        $checkSold = $conn->prepare("SELECT COUNT(*) FROM invoice_items WHERE purchasing_id = ?");
        $checkSold->execute([$op['finished_purchase_id']]);
        if ($checkSold->fetchColumn() > 0) {
            throw new Exception("Cannot delete: Finished product from this batch has already been sold in invoices.");
        }

        // 3. Restore Bulk Stock
        $updBulk = $conn->prepare("UPDATE purchasing SET quantity = quantity + ? WHERE id = ?");
        $updBulk->execute([$op['quantity_used'], $op['bulk_purchase_id']]);

        // 4. Restore Packing Materials
        $matsStmt = $conn->prepare("SELECT * FROM packing_materials_used WHERE packing_operation_id = ?");
        $matsStmt->execute([$id]);
        $mats = $matsStmt->fetchAll();

        $updMat = $conn->prepare("UPDATE purchasing SET quantity = quantity + ? WHERE id = ?");
        foreach ($mats as $m) {
            $updMat->execute([$m['quantity_used'], $m['material_purchase_id']]);
        }

        // 5. Delete Records
        // Note: Cascade on packing_materials_used handles that
        $delOp = $conn->prepare("DELETE FROM packing_operations WHERE id = ?");
        $delOp->execute([$id]);

        // 6. Delete the Finished Product Entry in purchasing
        $delFin = $conn->prepare("DELETE FROM purchasing WHERE id = ?");
        $delFin->execute([$op['finished_purchase_id']]);

        $conn->commit();
        setFlashMessage('success', 'Packing operation deleted and stock restored.');
    } catch (Exception $e) {
        $conn->rollBack();
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
}

header('Location: packing.php');
exit;
