import os
import re

sidebar_content = """<ul class="sidebar-menu">
                <li><a href="dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="add-product.php" <?php echo basename($_SERVER['PHP_SELF']) == 'add-product.php' ? 'class="active"' : ''; ?>><i class="fas fa-plus-circle"></i> Add Product</a></li>
                <li><a href="publish-products.php" <?php echo basename($_SERVER['PHP_SELF']) == 'publish-products.php' ? 'class="active"' : ''; ?>><i class="fas fa-globe"></i> Publish Products</a></li>
                <li><a href="purchasing.php" <?php echo basename($_SERVER['PHP_SELF']) == 'purchasing.php' ? 'class="active"' : ''; ?>><i class="fas fa-shopping-cart"></i> Purchasing</a></li>
                <li><a href="packing.php" <?php echo basename($_SERVER['PHP_SELF']) == 'packing.php' ? 'class="active"' : ''; ?>><i class="fas fa-box-open"></i> Packing</a></li>
                <li><a href="invoices.php" <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'class="active"' : ''; ?>><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li>
                <li><a href="clients.php" <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'class="active"' : ''; ?>><i class="fas fa-users"></i> Clients</a></li>
                <li><a href="employees.php" <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'class="active"' : ''; ?>><i class="fas fa-id-badge"></i> Employees</a></li>
                <li><a href="banking.php" <?php echo basename($_SERVER['PHP_SELF']) == 'banking.php' ? 'class="active"' : ''; ?>><i class="fas fa-university"></i> Banking</a></li>
                <li><a href="ledger.php" <?php echo basename($_SERVER['PHP_SELF']) == 'ledger.php' ? 'class="active"' : ''; ?>><i class="fas fa-book"></i> Ledgers</a></li>
                <li><a href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Website</a></li>
            </ul>"""

admin_dir = r"f:\wamp64\www\agrovise\admin"

# Regex to match the sidebar block
pattern = re.compile(r'<ul class="sidebar-menu">.*?</ul>', re.DOTALL)

updated_files = []

for filename in os.listdir(admin_dir):
    if filename.endswith(".php"):
        filepath = os.path.join(admin_dir, filename)
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
            
        if '<ul class="sidebar-menu">' in content:
            new_content = pattern.sub(sidebar_content, content)
            if new_content != content:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                updated_files.append(filename)

print("Updated sidebar in:", updated_files)
