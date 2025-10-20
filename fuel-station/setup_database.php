<?php
// setup_database.php - Fixed version
try {
    $host = 'localhost';
    $dbname = 'fuel_station_db';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    echo "✅ Database created/selected successfully<br>";
    
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        gender ENUM('Male', 'Female'),
        city VARCHAR(50),
        address TEXT,
        role ENUM('Admin', 'Cashier') DEFAULT 'Cashier',
        email_verified BOOLEAN DEFAULT FALSE,
        verification_code VARCHAR(10),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS cars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_name VARCHAR(100) NOT NULL,
        model VARCHAR(100) NOT NULL,
        plate_number VARCHAR(20) UNIQUE NOT NULL,
        owner_name VARCHAR(100) NOT NULL,
        scanner_code VARCHAR(100) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS fuel_tanks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tank_name VARCHAR(50) NOT NULL,
        fuel_type ENUM('Gasoline', 'Diesel', 'Premium') NOT NULL,
        capacity DECIMAL(10,2) NOT NULL,
        current_level DECIMAL(10,2) NOT NULL,
        min_threshold DECIMAL(10,2) NOT NULL,
        status ENUM('Active', 'Inactive') DEFAULT 'Active'
    )",
    
    "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        car_id INT,
        fuel_type VARCHAR(50),
        liters DECIMAL(10,2),
        price_per_liter DECIMAL(10,2),
        total_amount DECIMAL(10,2),
        payment_method ENUM('Cash', 'Card', 'Mobile'),
        cashier_id INT,
        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS fuel_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fuel_type VARCHAR(50) NOT NULL,
        price_per_liter DECIMAL(10,2) NOT NULL,
        effective_date DATE NOT NULL,
        is_current BOOLEAN DEFAULT TRUE
    )"
];

foreach ($tables as $table) {
    try {
        $pdo->exec($table);
        echo "✅ Table created successfully<br>";
    } catch(PDOException $e) {
        echo "❌ Table creation failed: " . $e->getMessage() . "<br>";
    }
}

// Insert sample data
try {
    $pdo->exec("INSERT IGNORE INTO fuel_tanks (tank_name, fuel_type, capacity, current_level, min_threshold) VALUES
        ('Tank A', 'Gasoline', 10000, 7500, 1000),
        ('Tank B', 'Diesel', 15000, 12000, 1500),
        ('Tank C', 'Premium', 8000, 6000, 800)");
    echo "✅ Sample tank data inserted<br>";
} catch(PDOException $e) {
    echo "❌ Tank data insertion failed: " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("INSERT IGNORE INTO fuel_prices (fuel_type, price_per_liter, effective_date) VALUES
        ('Gasoline', 122.53, CURDATE()),
        ('Diesel', 115.75, CURDATE()),
        ('Premium', 135.25, CURDATE())");
    echo "✅ Fuel prices inserted<br>";
} catch(PDOException $e) {
    echo "❌ Fuel prices insertion failed: " . $e->getMessage() . "<br>";
}

// Create admin user with simple password for testing
try {
    // First, delete existing admin user to avoid conflicts
    $pdo->exec("DELETE FROM users WHERE username = 'admin'");
    
    // Create admin user with simple password
    $simple_password = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (full_name, username, password, email, role, email_verified) VALUES
        ('System Admin', 'admin', '$simple_password', 'admin@fuelstation.com', 'Admin', 1)");
    
    echo "✅ Admin user created successfully<br>";
    echo "👤 <strong>Login: admin / admin123</strong><br>";
    
    // Verify the password was set correctly
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user && password_verify('admin123', $user['password'])) {
        echo "✅ Password verification test: PASSED<br>";
    } else {
        echo "❌ Password verification test: FAILED<br>";
    }
    
} catch(PDOException $e) {
    echo "❌ Admin user creation failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>🎉 Setup Completed!</h3>";
echo "<a href='login.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
?>// Add to your existing setup_database.php after the other tables

// Car fuel consumption patterns table
$pdo->exec("CREATE TABLE IF NOT EXISTS car_consumption_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT,
    model VARCHAR(100),
    average_consumption DECIMAL(8,2), -- L/100km
    last_refuel_date DATETIME,
    last_refuel_liters DECIMAL(8,2),
    estimated_remaining_fuel DECIMAL(8,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (car_id) REFERENCES cars(id)
)");

// Fuel refill history table
$pdo->exec("CREATE TABLE IF NOT EXISTS fuel_refill_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT,
    liters DECIMAL(8,2),
    kilometers_since_last_refill DECIMAL(8,2),
    consumption_rate DECIMAL(8,2), -- L/100km
    refill_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    cashier_id INT,
    FOREIGN KEY (car_id) REFERENCES cars(id),
    FOREIGN KEY (cashier_id) REFERENCES users(id)
)");

// Insert default consumption patterns for common car models
$default_patterns = [
    ['Toyota Corolla', 7.5],
    ['Honda Civic', 7.2],
    ['Toyota Hilux', 8.5],
    ['Land Cruiser', 12.5],
    ['Mitsubishi Lancer', 8.0],
    ['Hyundai Accent', 6.8],
    ['BMW 3 Series', 9.2],
    ['Mercedes C-Class', 9.5],
    ['Ford Ranger', 9.0],
    ['Nissan Sunny', 7.0]
];

foreach ($default_patterns as $pattern) {
    $model = $pattern[0];
    $consumption = $pattern[1];
    
    // Update existing cars with this model
    $pdo->exec("UPDATE car_consumption_patterns 
                SET average_consumption = $consumption 
                WHERE model LIKE '%$model%'");
    
    // Insert if not exists
    $pdo->exec("INSERT IGNORE INTO car_consumption_patterns (model, average_consumption) 
                VALUES ('$model', $consumption)");
}

echo "✅ Fuel consumption tracking tables created<br>";