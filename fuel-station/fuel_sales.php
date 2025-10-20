<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';
$car_info = null;
$fraud_warning = '';
$max_allowed_liters = 0;

// Get current fuel prices
try {
    $price_stmt = $pdo->query("SELECT fuel_type, price_per_liter FROM fuel_prices WHERE is_current = 1");
    $fuel_prices = $price_stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading fuel prices: " . $e->getMessage();
}

// Process scanner input
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['scan_car'])) {
    $scanner_code = trim($_POST['scanner_code']);
    
    try {
        // Find car by scanner code
        $car_stmt = $pdo->prepare("SELECT * FROM cars WHERE scanner_code = ?");
        $car_stmt->execute([$scanner_code]);
        $car_info = $car_stmt->fetch();
        
        if (!$car_info) {
            $error = "❌ Car not found! Please check scanner code.";
        } else {
            // Check for potential fraud
            $fraud_check = checkFuelFraud($pdo, $car_info['id'], $car_info['model']);
            $fraud_warning = $fraud_check['warning'];
            $max_allowed_liters = $fraud_check['max_liters'];
        }
    } catch(PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}

// Process fuel sale
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_sale'])) {
    $car_id = $_POST['car_id'];
    $fuel_type = $_POST['fuel_type'];
    $liters = $_POST['liters'];
    $payment_method = $_POST['payment_method'];
    $cashier_id = $_SESSION['user_id'];
    
    // Fraud check before processing
    $fraud_check = checkFuelFraud($pdo, $car_id);
    if ($liters > $fraud_check['max_liters'] && $fraud_check['max_liters'] > 0) {
        $error = "🚨 FRAUD ALERT: " . $fraud_check['warning'] . " Maximum allowed: " . $fraud_check['max_liters'] . " liters";
    } else {
        try {
            // Get fuel price
            $price_stmt = $pdo->prepare("SELECT price_per_liter FROM fuel_prices WHERE fuel_type = ? AND is_current = 1");
            $price_stmt->execute([$fuel_type]);
            $price_info = $price_stmt->fetch();
            
            if ($price_info) {
                $price_per_liter = $price_info['price_per_liter'];
                $total_amount = $liters * $price_per_liter;
                
                // Insert transaction
                $stmt = $pdo->prepare("INSERT INTO transactions (car_id, fuel_type, liters, price_per_liter, total_amount, payment_method, cashier_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$car_id, $fuel_type, $liters, $price_per_liter, $total_amount, $payment_method, $cashier_id]);
                
                // Update fuel consumption pattern
                updateConsumptionPattern($pdo, $car_id, $liters, $car_info['model']);
                
                // Update fuel tank level
                $tank_stmt = $pdo->prepare("UPDATE fuel_tanks SET current_level = current_level - ? WHERE fuel_type = ?");
                $tank_stmt->execute([$liters, $fuel_type]);
                
                $success = "✅ Fuel sale processed successfully! Amount: ETB " . number_format($total_amount, 2);
                
                // Reset car info
                $car_info = null;
                $fraud_warning = '';
            } else {
                $error = "❌ Fuel price not found!";
            }
        } catch(PDOException $e) {
            $error = "❌ Database error: " . $e->getMessage();
        }
    }
}

/**
 * Check for potential fuel fraud based on consumption patterns and time gaps
 */
function checkFuelFraud($pdo, $car_id, $model = '') {
    $result = [
        'warning' => '',
        'max_liters' => 0,
        'time_since_last_refill' => 0,
        'estimated_consumption' => 0
    ];
    
    try {
        // Get car's last refill
        $last_refill_stmt = $pdo->prepare("
            SELECT liters, transaction_date 
            FROM transactions 
            WHERE car_id = ? 
            ORDER BY transaction_date DESC 
            LIMIT 1
        ");
        $last_refill_stmt->execute([$car_id]);
        $last_refill = $last_refill_stmt->fetch();
        
        // Get average consumption for this car model
        $consumption_stmt = $pdo->prepare("
            SELECT average_consumption 
            FROM car_consumption_patterns 
            WHERE model LIKE ? OR car_id = ?
            ORDER BY car_id DESC 
            LIMIT 1
        ");
        $consumption_stmt->execute(["%$model%", $car_id]);
        $consumption = $consumption_stmt->fetch();
        
        $avg_consumption = $consumption ? $consumption['average_consumption'] : 8.0; // Default 8L/100km
        
        if ($last_refill) {
            $last_refill_time = strtotime($last_refill['transaction_date']);
            $current_time = time();
            $hours_since_refill = ($current_time - $last_refill_time) / 3600;
            $days_since_refill = $hours_since_refill / 24;
            
            // Calculate maximum reasonable fuel based on time and consumption
            // Assumption: Average car drives 200km per day in commercial use
            $estimated_km_driven = min($days_since_refill * 200, 1000); // Max 1000km estimate
            $estimated_fuel_used = ($estimated_km_driven * $avg_consumption) / 100;
            
            // Tank capacity assumptions based on car type
            $tank_capacity = getTankCapacity($model);
            $max_reasonable_refill = $tank_capacity - $estimated_fuel_used;
            
            $result['time_since_last_refill'] = $hours_since_refill;
            $result['estimated_consumption'] = $estimated_fuel_used;
            $result['max_liters'] = max(10, $max_reasonable_refill); // Minimum 10 liters allowed
            
            // Generate warnings
            if ($hours_since_refill < 2) {
                $result['warning'] = "Car refueled " . round($hours_since_refill * 60) . " minutes ago. Possible duplicate refill!";
                $result['max_liters'] = min(5, $result['max_liters']); // Very strict limit
            } elseif ($hours_since_refill < 6) {
                $result['warning'] = "Car refueled " . round($hours_since_refill) . " hours ago. Unusually frequent refill!";
                $result['max_liters'] = min(15, $result['max_liters']);
            } elseif ($hours_since_refill < 12) {
                $result['warning'] = "Car refueled " . round($hours_since_refill) . " hours ago. Monitor this vehicle.";
            }
            
            // Additional check: if requesting more than tank capacity
            if ($max_reasonable_refill < 5) {
                $result['warning'] = "🚨 CRITICAL: Vehicle tank should still have fuel. Possible fuel diversion!";
                $result['max_liters'] = 0;
            }
        }
        
    } catch(PDOException $e) {
        // If error, set conservative limits
        $result['max_liters'] = 50;
        $result['warning'] = "System error in fraud detection. Proceed with caution.";
    }
    
    return $result;
}

/**
 * Estimate tank capacity based on car model
 */
function getTankCapacity($model) {
    $model = strtolower($model);
    $capacities = [
        'corolla' => 50,
        'civic' => 47,
        'hilux' => 80,
        'land cruiser' => 110,
        'lancer' => 59,
        'accent' => 43,
        'bmw' => 60,
        'mercedes' => 66,
        'ranger' => 80,
        'sunny' => 41
    ];
    
    foreach ($capacities as $key => $capacity) {
        if (strpos($model, $key) !== false) {
            return $capacity;
        }
    }
    
    return 55; // Default average tank capacity
}

/**
 * Update consumption patterns after successful refill
 */
function updateConsumptionPattern($pdo, $car_id, $liters, $model) {
    try {
        // Get or create consumption pattern
        $check_stmt = $pdo->prepare("SELECT id FROM car_consumption_patterns WHERE car_id = ?");
        $check_stmt->execute([$car_id]);
        
        if ($check_stmt->rowCount() == 0) {
            // Create new pattern
            $avg_consumption = getDefaultConsumption($model);
            $insert_stmt = $pdo->prepare("
                INSERT INTO car_consumption_patterns (car_id, model, average_consumption, last_refuel_date, last_refuel_liters) 
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $insert_stmt->execute([$car_id, $model, $avg_consumption, $liters]);
        } else {
            // Update existing pattern
            $update_stmt = $pdo->prepare("
                UPDATE car_consumption_patterns 
                SET last_refuel_date = NOW(), last_refuel_liters = ? 
                WHERE car_id = ?
            ");
            $update_stmt->execute([$liters, $car_id]);
        }
    } catch(PDOException $e) {
        // Silently fail - not critical
    }
}

/**
 * Get default consumption based on model
 */
function getDefaultConsumption($model) {
    $model = strtolower($model);
    $consumptions = [
        'corolla' => 7.5,
        'civic' => 7.2,
        'hilux' => 8.5,
        'land cruiser' => 12.5,
        'lancer' => 8.0,
        'accent' => 6.8,
        'bmw' => 9.2,
        'mercedes' => 9.5,
        'ranger' => 9.0,
        'sunny' => 7.0
    ];
    
    foreach ($consumptions as $key => $consumption) {
        if (strpos($model, $key) !== false) {
            return $consumption;
        }
    }
    
    return 8.0; // Default average
}

// Get today's sales summary
$today_sales = [
    'transactions' => 0,
    'total_liters' => 0,
    'total_revenue' => 0
];

try {
    $sales_stmt = $pdo->query("
        SELECT COUNT(*) as transactions, 
               COALESCE(SUM(liters), 0) as total_liters,
               COALESCE(SUM(total_amount), 0) as total_revenue
        FROM transactions 
        WHERE DATE(transaction_date) = CURDATE()
    ");
    $today_sales = $sales_stmt->fetch();
} catch(PDOException $e) {
    // Silently fail - not critical for display
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Sales - Smart Fuel Station</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            --blur: blur(10px);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: var(--blur);
            padding: 2rem 0;
            box-shadow: var(--shadow);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logo {
            text-align: center;
            padding: 0 2rem 2rem;
            border-bottom: 2px solid var(--primary);
            margin-bottom: 1rem;
        }

        .logo h2 {
            color: var(--dark);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo p {
            color: var(--primary);
            font-size: 0.9rem;
        }

        .nav-links {
            padding: 0 1.5rem;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            margin: 0.5rem 0;
            text-decoration: none;
            color: var(--dark);
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-links a i {
            margin-right: 12px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .nav-links a:hover {
            background: var(--primary);
            color: white;
            transform: translateX(5px);
        }

        .nav-links a.active {
            background: var(--gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: var(--blur);
            padding: 1.5rem 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--dark);
            font-size: 2rem;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: var(--blur);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary);
        }

        .card-header i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-right: 12px;
        }

        .card-header h2 {
            color: var(--dark);
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            background: white;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #4cc9f0 0%, #4895ef 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 201, 240, 0.4);
        }

        .btn-lg {
            padding: 1.2rem 2.5rem;
            font-size: 1.1rem;
        }

        /* Alerts */
        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left-color: #28a745;
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
            border-left-color: #dc3545;
            color: #721c24;
        }

        /* Car Info */
        .car-info {
            background: linear-gradient(135deg, #e8f4fd 0%, #d1ecf1 100%);
            padding: 2rem;
            border-radius: 16px;
            border-left: 4px solid var(--primary);
            margin: 1.5rem 0;
        }

        .car-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .car-icon {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .scanner-badge {
            background: var(--dark);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-family: monospace;
            font-size: 1.1rem;
            margin-top: 0.5rem;
            display: inline-block;
        }

        /* Fraud Alerts */
        .fraud-alert {
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            border: 2px solid #e17055;
            border-left: 6px solid #d63031;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
            animation: pulse 2s infinite;
        }

        .fraud-warning {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            border: 1px solid #fdcb6e;
            border-left: 4px solid #e17055;
            padding: 1.2rem;
            border-radius: 10px;
            margin: 1rem 0;
        }

        .fraud-info {
            background: linear-gradient(135deg, #a29bfe 0%, #6c5ce7 100%);
            color: white;
            padding: 1.2rem;
            border-radius: 10px;
            margin: 1rem 0;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(214, 48, 49, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(214, 48, 49, 0); }
            100% { box-shadow: 0 0 0 0 rgba(214, 48, 49, 0); }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0.5rem 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Price List */
        .price-list {
            background: linear-gradient(135deg, #dfe6e9 0%, #b2bec3 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1.5rem;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin: 0.5rem 0;
            background: white;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .price-item:hover {
            transform: translateX(5px);
        }

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .app-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h2>⛽ Fuel Station</h2>
                <p>Smart Management System</p>
            </div>
            <div class="nav-links">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="car_registration.php">
                    <i class="fas fa-car"></i>
                    Car Registration
                </a>
                <a href="fuel_sales.php" class="active">
                    <i class="fas fa-gas-pump"></i>
                    Fuel Sales
                </a>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
                <?php if ($_SESSION['role'] == 'Admin'): ?>
                <a href="users.php">
                    <i class="fas fa-users"></i>
                    User Management
                </a>
                <a href="inventory.php">
                    <i class="fas fa-warehouse"></i>
                    Inventory
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-gas-pump"></i> Fuel Sales System</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo $_SESSION['full_name']; ?></div>
                        <div style="color: #666; font-size: 0.9rem;"><?php echo $_SESSION['role']; ?></div>
                    </div>
                    <a href="logout.php" class="btn" style="background: var(--danger); color: white; margin-left: 1rem;">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="grid-2">
                <!-- Scanner Section -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-qrcode"></i>
                        <h2>Vehicle Scanner</h2>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-barcode"></i> SCANNER CODE</label>
                            <input type="text" name="scanner_code" class="form-control" 
                                   placeholder="Enter RFID/Barcode code" required 
                                   value="<?php echo isset($_POST['scanner_code']) ? htmlspecialchars($_POST['scanner_code']) : ''; ?>">
                        </div>
                        <button type="submit" name="scan_car" class="btn btn-primary btn-lg">
                            <i class="fas fa-search"></i> Scan Vehicle
                        </button>
                    </form>

                    <?php if ($car_info): ?>
                        <div class="car-info">
                            <div class="car-header">
                                <div class="car-icon">
                                    <i class="fas fa-car"></i>
                                </div>
                                <div>
                                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">✅ Vehicle Identified</h3>
                                    <div class="scanner-badge">
                                        <i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($car_info['scanner_code']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="fas fa-car-side"></i></div>
                                    <div class="stat-number"><?php echo htmlspecialchars($car_info['car_name']); ?></div>
                                    <div class="stat-label">Vehicle</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="fas fa-tag"></i></div>
                                    <div class="stat-number"><?php echo htmlspecialchars($car_info['model']); ?></div>
                                    <div class="stat-label">Model</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="fas fa-id-card"></i></div>
                                    <div class="stat-number"><?php echo htmlspecialchars($car_info['plate_number']); ?></div>
                                    <div class="stat-label">Plate No.</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon"><i class="fas fa-user"></i></div>
                                    <div class="stat-number"><?php echo htmlspecialchars($car_info['owner_name']); ?></div>
                                    <div class="stat-label">Owner</div>
                                </div>
                            </div>

                            <!-- Fraud Detection -->
                            <?php if ($fraud_warning): ?>
                                <div class="<?php echo strpos($fraud_warning, 'CRITICAL') !== false ? 'fraud-alert' : 'fraud-warning'; ?>">
                                    <h4 style="margin-bottom: 0.5rem;"><i class="fas fa-shield-alt"></i> Fraud Detection Alert</h4>
                                    <?php echo $fraud_warning; ?>
                                </div>
                            <?php else: ?>
                                <div class="fraud-info">
                                    <h4 style="margin-bottom: 0.5rem;"><i class="fas fa-check-shield"></i> Security Check Passed</h4>
                                    No suspicious activity detected. Vehicle cleared for refueling.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Fuel Sale Section -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-gas-pump"></i>
                        <h2>Fuel Transaction</h2>
                    </div>
                    
                    <?php if ($car_info): ?>
                        <form method="POST">
                            <input type="hidden" name="car_id" value="<?php echo $car_info['id']; ?>">
                            
                            <div class="form-group">
                                <label><i class="fas fa-oil-can"></i> FUEL TYPE</label>
                                <select name="fuel_type" class="form-control" required>
                                    <option value="">Select Fuel Type</option>
                                    <?php foreach ($fuel_prices as $price): ?>
                                        <option value="<?php echo $price['fuel_type']; ?>">
                                            ⛽ <?php echo $price['fuel_type']; ?> - ETB <?php echo number_format($price['price_per_liter'], 2); ?>/L
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-weight-hanging"></i> LITERS (Max: <?php echo number_format($max_allowed_liters, 1); ?>L)</label>
                                <input type="number" name="liters" class="form-control" step="0.1" min="0.1" 
                                       max="<?php echo $max_allowed_liters; ?>" placeholder="Enter liters" required>
                                <small style="color: #666; margin-top: 0.5rem; display: block;">
                                    <i class="fas fa-info-circle"></i> Maximum allowed: <?php echo number_format($max_allowed_liters, 1); ?> liters based on consumption analysis
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-credit-card"></i> PAYMENT METHOD</label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="Cash">💵 Cash Payment</option>
                                    <option value="Card">💳 Card Payment</option>
                                    <option value="Mobile">📱 Mobile Payment</option>
                                </select>
                            </div>
                            
                            <button type="submit" name="process_sale" class="btn btn-success btn-lg">
                                <i class="fas fa-bolt"></i> Process Fuel Sale
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem 2rem; color: #666;">
                            <i class="fas fa-car" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3 style="margin-bottom: 1rem;">Scan Vehicle First</h3>
                            <p>Use the scanner panel to identify a vehicle before processing fuel sale.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Current Prices -->
                    <div class="price-list">
                        <h4 style="margin-bottom: 1rem; color: var(--dark);">
                            <i class="fas fa-tags"></i> Current Fuel Prices
                        </h4>
                        <?php foreach ($fuel_prices as $price): ?>
                            <div class="price-item">
                                <span>⛽ <?php echo $price['fuel_type']; ?></span>
                                <span style="font-weight: 600; color: var(--primary);">
                                    ETB <?php echo number_format($price['price_per_liter'], 2); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Today's Summary -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <h2>Today's Performance</h2>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                        <div class="stat-number"><?php echo $today_sales['transactions']; ?></div>
                        <div class="stat