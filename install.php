<?php
/**
 * AGROVISE - Database Installation Script
 * Run this file once to set up the database and tables
 */

require_once 'includes/db.php';

try {
    echo "<h1>AGROVISE Database Installation</h1>";
    echo "<p>Setting up database...</p>";
    
    // Create connection without database
    $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✓ Database created successfully</p>";
    
    // Use database
    $conn->exec("USE " . DB_NAME);
    
    // Create products table
    $conn->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category ENUM('insecticides', 'weedicides', 'fungicides', 'granulars', 'micronutrients') NOT NULL,
        description TEXT NOT NULL,
        image VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p>✓ Products table created successfully</p>";
    
    // Create admin users table
    $conn->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✓ Admin users table created successfully</p>";
    
    // Insert default admin
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO admin_users (username, password) VALUES (?, ?)");
    $stmt->execute(['admin', $hashedPassword]);
    echo "<p>✓ Default admin user created (username: admin, password: admin123)</p>";
    
    // Insert sample products
    $sampleProducts = [
        ['name' => 'AgroKill Pro', 'category' => 'insecticides', 'description' => 'Broad-spectrum insecticide effective against aphids, whiteflies, and caterpillars. Safe for crops when used as directed.', 'image' => 'insecticide_1.jpg'],
        ['name' => 'BugShield Plus', 'category' => 'insecticides', 'description' => 'Systemic insecticide with long-lasting protection against sucking and chewing insects.', 'image' => 'insecticide_2.jpg'],
        ['name' => 'PestGuard Supreme', 'category' => 'insecticides', 'description' => 'Contact insecticide for immediate knockdown of flying and crawling pests.', 'image' => 'insecticide_3.jpg'],
        ['name' => 'InsectAway Ultra', 'category' => 'insecticides', 'description' => 'Organic-certified insecticide derived from natural botanical extracts.', 'image' => 'insecticide_4.jpg'],
        ['name' => 'WeedClear Max', 'category' => 'weedicides', 'description' => 'Selective herbicide targeting broadleaf weeds while preserving grass crops.', 'image' => 'weedicide_1.jpg'],
        ['name' => 'GrassGuard Pro', 'category' => 'weedicides', 'description' => 'Pre-emergent herbicide preventing weed germination for up to 3 months.', 'image' => 'weedicide_2.jpg'],
        ['name' => 'WeedFree Total', 'category' => 'weedicides', 'description' => 'Non-selective herbicide for complete weed control in non-crop areas.', 'image' => 'weedicide_3.jpg'],
        ['name' => 'Herbicide Xtra', 'category' => 'weedicides', 'description' => 'Fast-acting formula visible results within 24 hours of application.', 'image' => 'weedicide_4.jpg'],
        ['name' => 'FungiStop Pro', 'category' => 'fungicides', 'description' => 'Protective fungicide preventing fungal diseases in vegetables and fruits.', 'image' => 'fungicide_1.jpg'],
        ['name' => 'MoldGuard Supreme', 'category' => 'fungicides', 'description' => 'Systemic fungicide for treating powdery mildew and rust diseases.', 'image' => 'fungicide_2.jpg'],
        ['name' => 'CropShield Fungus', 'category' => 'fungicides', 'description' => 'Broad-spectrum fungicide effective against blight, rot, and leaf spot.', 'image' => 'fungicide_3.jpg'],
        ['name' => 'FungiClear Ultra', 'category' => 'fungicides', 'description' => 'Organic fungicide using beneficial microorganisms for disease control.', 'image' => 'fungicide_4.jpg'],
        ['name' => 'GrowGranules Plus', 'category' => 'granulars', 'description' => 'Slow-release granular fertilizer for sustained nutrient supply.', 'image' => 'granular_1.jpg'],
        ['name' => 'SoilBoost Granules', 'category' => 'granulars', 'description' => 'Soil conditioner granules improving water retention and aeration.', 'image' => 'granular_2.jpg'],
        ['name' => 'NutriGran Pro', 'category' => 'granulars', 'description' => 'Balanced NPK granules for all-purpose crop nutrition.', 'image' => 'granular_3.jpg'],
        ['name' => 'RootGuard Granules', 'category' => 'granulars', 'description' => 'Granular formulation promoting strong root development.', 'image' => 'granular_4.jpg'],
        ['name' => 'MicroMix Essential', 'category' => 'micronutrients', 'description' => 'Complete micronutrient blend containing zinc, iron, manganese, and boron.', 'image' => 'micro_1.jpg'],
        ['name' => 'ZincBoost Pro', 'category' => 'micronutrients', 'description' => 'High-concentration zinc fertilizer for deficiency correction.', 'image' => 'micro_2.jpg'],
        ['name' => 'IronGuard Plus', 'category' => 'micronutrients', 'description' => 'Chelated iron formula for rapid chlorosis treatment.', 'image' => 'micro_3.jpg'],
        ['name' => 'MultiMin Supreme', 'category' => 'micronutrients', 'description' => 'Premium micronutrient complex for optimal plant health.', 'image' => 'micro_4.jpg'],
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO products (name, category, description, image) VALUES (?, ?, ?, ?)");
    $count = 0;
    foreach ($sampleProducts as $product) {
        $stmt->execute([$product['name'], $product['category'], $product['description'], $product['image']]);
        $count++;
    }
    echo "<p>✓ $count sample products inserted successfully</p>";
    
    echo "<h2>Installation Complete!</h2>";
    echo "<p><a href='index.php'>Go to Homepage</a> | <a href='admin/login.php'>Go to Admin Login</a></p>";
    echo "<p><strong>Default Admin Credentials:</strong><br>Username: admin<br>Password: admin123</p>";
    echo "<p><strong>Important:</strong> Please delete this install.php file after installation for security reasons.</p>";
    
} catch(PDOException $e) {
    echo "<h2>Installation Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database credentials in includes/db.php</p>";
}
?>
