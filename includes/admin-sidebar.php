<?php
/**
 * AGROVISE - Shared admin sidebar (grouped drop-down menus)
 * Single source of truth for the panel navigation. Links render only when
 * the logged-in account holds the matching module permission (admins see all).
 * The group containing the current page opens automatically.
 */
if (!function_exists('hasPermission')) {
    return;
}
$__page = basename($_SERVER['PHP_SELF']);
$__active = function (array $files) use ($__page) {
    return in_array($__page, $files) ? 'class="active"' : '';
};
$__groupOpen = function (array $files) use ($__page) {
    return in_array($__page, $files) ? ' open' : '';
};
$__notifCount = unreadNotificationCount(getDBConnection());

// pages per group (for auto-open state)
$__gMySpace = ['my-profile.php', 'expenses.php'];
$__gCatalog = ['add-product.php', 'edit-product.php', 'publish-products.php'];
$__gSupply  = ['purchasing.php', 'add-purchase.php', 'edit-purchase.php', 'finished-products.php', 'vendors.php', 'add-vendor.php', 'edit-vendor.php', 'packing.php', 'add-packing.php', 'edit-packing.php', 'pack-sizes.php'];
$__gSales   = ['invoices.php', 'add-invoice.php', 'edit-invoice.php', 'pr.php', 'add-pr.php', 'edit-pr.php', 'clients.php', 'add-client.php', 'edit-client.php', 'policies.php', 'add-policy.php', 'edit-policy.php', 'policy-calculator.php'];
$__gFinance = ['banking.php', 'add-account.php', 'edit-account.php', 'add-transaction.php', 'edit-transaction.php', 'ledger.php', 'ledger-client.php', 'ledger-product.php', 'detailed-report.php', 'analytics.php'];
$__gCompany = ['employees.php', 'add-employee.php', 'edit-employee.php', 'designations.php', 'sales-targets.php', 'vehicles.php', 'add-vehicle.php', 'edit-vehicle.php', 'messaging.php'];
?>
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="<?php echo landingPage(); ?>" class="sidebar-word">AGRO<span>VISE</span></a>
                <p>The Back Office</p>
            </div>
            <ul class="sidebar-menu">
                <?php if (isSuperAdmin() || !empty($_SESSION['admin_permissions'])): ?>
                <li><a href="dashboard.php" <?php echo $__active(['dashboard.php']); ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <?php endif; ?>

                <li class="menu-group<?php echo $__groupOpen($__gMySpace); ?>">
                    <button type="button" class="menu-group-btn"><i class="fas fa-user-circle"></i> My Space <i class="fas fa-chevron-down caret"></i><?php if ($__notifCount): ?><span class="notif-badge"><?php echo $__notifCount; ?></span><?php endif; ?></button>
                    <ul class="menu-sub">
                        <li><a href="my-profile.php" <?php echo $__active(['my-profile.php']); ?>><i class="fas fa-id-card"></i> My Profile</a></li>
                        <li><a href="expenses.php" <?php echo $__active(['expenses.php']); ?>><i class="fas fa-hand-holding-dollar"></i> Expenses<?php if ($__notifCount): ?> <span class="notif-badge"><?php echo $__notifCount; ?></span><?php endif; ?></a></li>
                    </ul>
                </li>

                <?php if (hasPermission('products')): ?>
                <li class="menu-group<?php echo $__groupOpen($__gCatalog); ?>">
                    <button type="button" class="menu-group-btn"><i class="fas fa-box"></i> Catalog <i class="fas fa-chevron-down caret"></i></button>
                    <ul class="menu-sub">
                        <li><a href="add-product.php" <?php echo $__active(['add-product.php', 'edit-product.php']); ?>><i class="fas fa-plus-circle"></i> Add Product</a></li>
                        <li><a href="publish-products.php" <?php echo $__active(['publish-products.php']); ?>><i class="fas fa-globe"></i> Publish Products</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasPermission('purchasing') || hasPermission('vendors') || hasPermission('packing')): ?>
                <li class="menu-group<?php echo $__groupOpen($__gSupply); ?>">
                    <button type="button" class="menu-group-btn"><i class="fas fa-boxes-stacked"></i> Supply <i class="fas fa-chevron-down caret"></i></button>
                    <ul class="menu-sub">
                        <?php if (hasPermission('purchasing')): ?><li><a href="purchasing.php" <?php echo $__active(['purchasing.php', 'add-purchase.php', 'edit-purchase.php']); ?>><i class="fas fa-shopping-cart"></i> Purchasing</a></li><?php endif; ?>
                        <?php if (hasPermission('purchasing') || hasPermission('packing')): ?><li><a href="finished-products.php" <?php echo $__active(['finished-products.php']); ?>><i class="fas fa-box"></i> Finished Stock</a></li><?php endif; ?>
                        <?php if (hasPermission('vendors')): ?><li><a href="vendors.php" <?php echo $__active(['vendors.php', 'add-vendor.php', 'edit-vendor.php']); ?>><i class="fas fa-truck"></i> Vendors</a></li><?php endif; ?>
                        <?php if (hasPermission('packing')): ?><li><a href="packing.php" <?php echo $__active(['packing.php', 'add-packing.php', 'edit-packing.php']); ?>><i class="fas fa-box-open"></i> Packing</a></li><?php endif; ?>
                        <?php if (hasPermission('packing')): ?><li><a href="pack-sizes.php" <?php echo $__active(['pack-sizes.php']); ?>><i class="fas fa-boxes-packing"></i> Pack Sizes</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasPermission('invoices') || hasPermission('payments') || hasPermission('clients') || hasPermission('policies')): ?>
                <li class="menu-group<?php echo $__groupOpen($__gSales); ?>">
                    <button type="button" class="menu-group-btn"><i class="fas fa-chart-line"></i> Sales <i class="fas fa-chevron-down caret"></i></button>
                    <ul class="menu-sub">
                        <?php if (hasPermission('invoices')): ?><li><a href="invoices.php" <?php echo $__active(['invoices.php', 'add-invoice.php', 'edit-invoice.php']); ?>><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li><?php endif; ?>
                        <?php if (hasPermission('policies')): ?><li><a href="policies.php" <?php echo $__active(['policies.php', 'add-policy.php', 'edit-policy.php']); ?>><i class="fas fa-scroll"></i> Policies</a></li><?php endif; ?>
                        <?php if (hasPermission('policies')): ?><li><a href="policy-calculator.php" <?php echo $__active(['policy-calculator.php']); ?>><i class="fas fa-calculator"></i> Policy Calculator</a></li><?php endif; ?>
                        <?php if (hasPermission('payments')): ?><li><a href="pr.php" <?php echo $__active(['pr.php', 'add-pr.php', 'edit-pr.php']); ?>><i class="fas fa-receipt"></i> Payment Receipts</a></li><?php endif; ?>
                        <?php if (hasPermission('clients')): ?><li><a href="clients.php" <?php echo $__active(['clients.php', 'add-client.php', 'edit-client.php']); ?>><i class="fas fa-users"></i> Clients</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasPermission('banking') || hasPermission('ledgers')): ?>
                <li class="menu-group<?php echo $__groupOpen($__gFinance); ?>">
                    <button type="button" class="menu-group-btn"><i class="fas fa-coins"></i> Finance <i class="fas fa-chevron-down caret"></i></button>
                    <ul class="menu-sub">
                        <?php if (hasPermission('banking')): ?><li><a href="banking.php" <?php echo $__active(['banking.php', 'add-account.php', 'edit-account.php', 'add-transaction.php', 'edit-transaction.php']); ?>><i class="fas fa-university"></i> Banking</a></li><?php endif; ?>
                        <?php if (hasPermission('ledgers')): ?><li><a href="ledger.php" <?php echo $__active(['ledger.php', 'ledger-client.php', 'ledger-product.php', 'detailed-report.php', 'analytics.php']); ?>><i class="fas fa-book"></i> Ledgers</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasPermission('employees') || hasPermission('vehicles')): ?>
                <li class="menu-group<?php echo $__groupOpen($__gCompany); ?>">
                    <button type="button" class="menu-group-btn"><i class="fas fa-building"></i> Company <i class="fas fa-chevron-down caret"></i></button>
                    <ul class="menu-sub">
                        <?php if (hasPermission('employees')): ?><li><a href="employees.php" <?php echo $__active(['employees.php', 'add-employee.php', 'edit-employee.php']); ?>><i class="fas fa-id-badge"></i> Employees</a></li><?php endif; ?>
                        <?php if (hasPermission('employees')): ?><li><a href="designations.php" <?php echo $__active(['designations.php']); ?>><i class="fas fa-user-tag"></i> Designations</a></li><?php endif; ?>
                        <?php if (hasPermission('employees')): ?><li><a href="sales-targets.php" <?php echo $__active(['sales-targets.php']); ?>><i class="fas fa-bullseye"></i> Sales Targets</a></li><?php endif; ?>
                        <?php if (hasPermission('vehicles')): ?><li><a href="vehicles.php" <?php echo $__active(['vehicles.php', 'add-vehicle.php', 'edit-vehicle.php']); ?>><i class="fas fa-car"></i> Vehicles</a></li><?php endif; ?>
                        <?php if (hasPermission('employees')): ?><li><a href="messaging.php" <?php echo $__active(['messaging.php']); ?>><i class="fas fa-comment-sms"></i> Messaging</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasPermission('traffic')): ?>
                <li><a href="traffic.php" <?php echo $__active(['traffic.php']); ?>><i class="fas fa-chart-area"></i> Website Traffic</a></li>
                <?php endif; ?>

                <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Website</a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="logout.php" class="btn btn-outline" style="width: 100%;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>
        <script>
        document.querySelectorAll('.menu-group-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.parentElement.classList.toggle('open');
            });
        });
        </script>
