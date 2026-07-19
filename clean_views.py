import os

dashboard_path = r"f:\wamp64\www\agrovise\admin\dashboard.php"
with open(dashboard_path, "r", encoding="utf-8") as f:
    content = f.read()

# Replace headers
content = content.replace("<th>Price</th>", "<th>Packing</th>")
content = content.replace("<th>Stock</th>", "<th>Published</th>")

# Replace rows
# <td><?php echo (isset($product['price']) && $product['price'] > 0) ? 'Rs. '.number_format($product['price'], 2) : '-'; ?></td>
# <td>\n                                <?php $stock = $product['stock_quantity'] ?? 0; ?>\n                                <span style="color: <?php echo $stock > 0 ? 'green' : 'red'; ?>; font-weight: bold;">\n                                    <?php echo $stock; ?>\n                                </span>\n                            </td>

content = content.replace(
    """<td><?php echo (isset($product['price']) && $product['price'] > 0) ? 'Rs. '.number_format($product['price'], 2) : '-'; ?></td>
                            <td>
                                <?php $stock = $product['stock_quantity'] ?? 0; ?>
                                <span style="color: <?php echo $stock > 0 ? 'green' : 'red'; ?>; font-weight: bold;">
                                    <?php echo $stock; ?>
                                </span>
                            </td>""",
    """<td><span class="badge" style="background:#e8f5e9; color:#2e7d32;"><?php echo sanitize($product['packing_type'] ?? 'None'); ?></span></td>
                            <td>
                                <?php $is_pub = $product['is_published'] ?? 0; ?>
                                <span style="color: <?php echo $is_pub ? 'green' : 'gray'; ?>; font-weight: bold;">
                                    <?php echo $is_pub ? 'Yes' : 'No'; ?>
                                </span>
                            </td>"""
)

with open(dashboard_path, "w", encoding="utf-8") as f:
    f.write(content)

print("dashboard.php updated")

cat_dir = r"f:\wamp64\www\agrovise\categories"
for filename in os.listdir(cat_dir):
    if filename.endswith(".php"):
        filepath = os.path.join(cat_dir, filename)
        with open(filepath, "r", encoding="utf-8") as f:
            c = f.read()
        
        # Remove description
        # <p class="product-description"><?php echo sanitize($product['description']); ?></p>
        # And safe description
        # <?php if (isset($product['price']) && $product['price'] > 0): ?>
        # <p class="product-price" style="font-weight: bold; color: #2e7d32; margin-bottom: 5px;">Rs. <?php echo number_format($product['price'], 2); ?></p>
        # <?php endif; ?>
        
        c = c.replace("""<?php if (isset($product['price']) && $product['price'] > 0): ?>
                        <p class="product-price" style="font-weight: bold; color: #2e7d32; margin-bottom: 5px;">Rs. <?php echo number_format($product['price'], 2); ?></p>
                        <?php endif; ?>
                        <p class="product-description"><?php echo sanitize($product['description']); ?></p>""", 
                        """<p class="product-description" style="margin-top:10px;"><strong style="color: #666;">Packing:</strong> <?php echo sanitize($product['packing_type'] ?? 'None'); ?><br><strong style="color: #666;">Packs/Carton:</strong> <?php echo sanitize($product['avg_packs_per_carton'] ?? '0'); ?></p>""")
                        
        c = c.replace("""<?php if (isset($product['price']) && $product['price'] > 0): ?>
                        <p class="product-price" style="font-weight: bold; color: #2e7d32; margin-bottom: 5px;">Rs. <?php echo number_format($product['price'], 2); ?></p>
                        <?php endif; ?>
                        <p class="product-description"><?php echo isset($product['description']) ? sanitize($product['description']) : ''; ?></p>""", 
                        """<p class="product-description" style="margin-top:10px;"><strong style="color: #666;">Packing:</strong> <?php echo sanitize($product['packing_type'] ?? 'None'); ?><br><strong style="color: #666;">Packs/Carton:</strong> <?php echo sanitize($product['avg_packs_per_carton'] ?? '0'); ?></p>""")

        with open(filepath, "w", encoding="utf-8") as f:
            f.write(c)
        print("Updated", filename)
