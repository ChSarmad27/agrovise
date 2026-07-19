-- 1. Drop old tables that are entirely replaced
DROP TABLE IF EXISTS vendors;

-- 2. Modify products table
ALTER TABLE products DROP COLUMN price;
ALTER TABLE products DROP COLUMN stock_quantity;
ALTER TABLE products DROP COLUMN description;
ALTER TABLE products DROP COLUMN is_vendor_product;
ALTER TABLE products ADD COLUMN date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE products ADD COLUMN packing_type ENUM('Bottle', 'Bag', 'None') DEFAULT 'None';
ALTER TABLE products ADD COLUMN avg_packs_per_carton INT DEFAULT 0;
ALTER TABLE products ADD COLUMN is_published BOOLEAN DEFAULT FALSE;

-- 3. Create Purchasing table
CREATE TABLE IF NOT EXISTS purchasing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    batch_number VARCHAR(50) NOT NULL,
    type ENUM('BULK', 'PACKING', 'FINISHED', 'OTHERS') NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 4. Create Employees table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    cnic VARCHAR(20) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    role VARCHAR(100) NOT NULL,
    salary DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Create Transactions table (Banking)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sr_no VARCHAR(50) NOT NULL UNIQUE,
    type VARCHAR(50) NOT NULL, -- Purchasing, Salaries, Office Expenses, Custom
    name VARCHAR(255) NOT NULL, -- Vendor name, Custom name, etc.
    amount DECIMAL(10,2) NOT NULL,
    payment_source ENUM('Cash', 'Bank') NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Transaction Employees mapping table
CREATE TABLE IF NOT EXISTS transaction_employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    employee_id INT NOT NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 6. Create Packing Module Tables
CREATE TABLE IF NOT EXISTS packing_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulk_purchase_id INT NOT NULL,
    quantity_used DECIMAL(10,2) NOT NULL,
    bulk_cost DECIMAL(10,2) NOT NULL,
    finished_purchase_id INT NOT NULL, -- Links to the new 'FINISHED' row in purchasing
    total_material_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    packing_cost DECIMAL(10,2) NOT NULL, -- Diff between raw purchase and final production cost
    selling_price DECIMAL(10,2) DEFAULT NULL, -- Optional planned selling price (informational)
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bulk_purchase_id) REFERENCES purchasing(id) ON DELETE RESTRICT,
    FOREIGN KEY (finished_purchase_id) REFERENCES purchasing(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS packing_materials_used (
    id INT AUTO_INCREMENT PRIMARY KEY,
    packing_operation_id INT NOT NULL,
    material_purchase_id INT NOT NULL,
    quantity_used DECIMAL(10,2) NOT NULL,
    material_cost DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (packing_operation_id) REFERENCES packing_operations(id) ON DELETE CASCADE,
    FOREIGN KEY (material_purchase_id) REFERENCES purchasing(id) ON DELETE RESTRICT
);

-- 7. Modify Invoices and Invoice Items
-- We need to link invoice items to the specific batch/purchase in stock to deduct it properly.
ALTER TABLE invoice_items ADD COLUMN purchasing_id INT NOT NULL;
ALTER TABLE invoice_items ADD CONSTRAINT fk_ii_purchasing FOREIGN KEY (purchasing_id) REFERENCES purchasing(id) ON DELETE RESTRICT;
ALTER TABLE invoice_items ADD COLUMN packs_per_carton INT DEFAULT 0;
