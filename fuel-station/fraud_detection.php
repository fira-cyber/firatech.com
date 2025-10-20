<?php
require_once 'config.php';

// Start session and check login
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';
$car_info = null;
$fraud_analysis = null;
$is_new_car = false;
$red_light_active = false;

// Debug: Check if we're receiving POST data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("POST data received: " . print_r($_POST, true));
}

// Process scanner search
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['scan_car'])) {
    $scanner_code = trim($_POST['scanner_code']);
    
    try {
        // First check if car exists in cars table
        $car_stmt = $pdo->prepare("SELECT * FROM cars WHERE scanner_code = ?");
        $car_stmt->execute([$scanner_code]);
        $car_info = $car_stmt->fetch();
        
        if (!$car_info) {
            $error = "❌ Car not found! Please register the vehicle first in Car Registration.";
        } else {
            // Check if car has previous transactions
            $transaction_check = $pdo->prepare("SELECT COUNT(*) as transaction_count FROM transactions WHERE car_id = ?");
            $transaction_check->execute([$car_info['id']]);
            $transaction_count = $transaction_check->fetch()['transaction_count'];
            
            if ($transaction_count == 0) {
                $is_new_car = true;
                $success = "🚗 New vehicle detected! Redirecting to fuel sales...";
                $_SESSION['new_car_scanner'] = $scanner_code;
            } else {
                // Get the latest transaction
                $latest_stmt = $pdo->prepare("
                    SELECT *, 
                           COALESCE(current_fuel_level, 0) as display_fuel_level,
                           COALESCE(last_km_reading, 0) as display_km_reading
                    FROM transactions 
                    WHERE car_id = ? 
                    ORDER BY transaction_date DESC 
                    LIMIT 1
                ");
                $latest_stmt->execute([$car_info['id']]);
                $last_transaction = $latest_stmt->fetch();
                
                if ($last_transaction) {
                    // Calculate Last Fuel Level in Tank (remaining fuel + purchased fuel)
                    $last_fuel_level = $last_transaction['display_fuel_level'] + $last_transaction['liters'];
                    
                    // Display vehicle information
                    $fraud_analysis = [
                        'vehicle_info' => [
                            'last_fuel_level' => number_format($last_fuel_level, 1) . ' liters',
                            'last_fuel_purchased' => $last_transaction['liters'] . ' liters',
                            'last_km_reading' => number_format($last_transaction['display_km_reading']) . ' km',
                            'last_visit_date' => date('M j, Y H:i', strtotime($last_transaction['transaction_date'])),
                            'remaining_fuel' => number_format($last_transaction['display_fuel_level'], 1) . ' liters'
                        ],
                        'status' => 'pending_km'
                    ];
                } else {
                    $error = "No transaction data found for this vehicle.";
                }
            }
        }
    } catch(PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}

// Process KM reading and perform fraud check
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_km'])) {
    $scanner_code = $_POST['scanner_code'];
    $current_km = floatval($_POST['current_km_reading']);
    
    try {
        $car_stmt = $pdo->prepare("SELECT * FROM cars WHERE scanner_code = ?");
        $car_stmt->execute([$scanner_code]);
        $car_info = $car_stmt->fetch();
        
        if ($car_info) {
            // Get last transaction
            $latest_stmt = $pdo->prepare("
                SELECT *, 
                       COALESCE(current_fuel_level, 0) as display_fuel_level,
                       COALESCE(last_km_reading, 0) as display_km_reading
                FROM transactions 
                WHERE car_id = ? 
                ORDER BY transaction_date DESC 
                LIMIT 1
            ");
            $latest_stmt->execute([$car_info['id']]);
            $last_transaction = $latest_stmt->fetch();
            
            if ($last_transaction) {
                // Calculate Last Fuel Level in Tank
                $last_fuel_level = $last_transaction['display_fuel_level'] + $last_transaction['liters'];
                
                // Perform simple fraud analysis
                $fraud_analysis = [
                    'status' => 'clean',
                    'confidence' => 100,
                    'warnings' => [],
                    'vehicle_info' => [
                        'last_fuel_level' => number_format($last_fuel_level, 1) . ' liters',
                        'last_fuel_purchased' => $last_transaction['liters'] . ' liters',
                        'last_km_reading' => number_format($last_transaction['display_km_reading']) . ' km',
                        'current_km_reading' => number_format($current_km) . ' km',
                        'km_traveled' => number_format($current_km - $last_transaction['display_km_reading']) . ' km'
                    ],
                    'recommendation' => 'No fraud detected',
                    'red_light_active' => false
                ];
                
                // Simple fraud check
                $expected_efficiency = 10; // km/L
                $min_expected_km = $last_transaction['display_km_reading'] + ($last_fuel_level * 0.7 * $expected_efficiency);
                
                if ($current_km < $min_expected_km) {
                    $fraud_analysis['red_light_active'] = true;
                    $fraud_analysis['warnings'][] = "🚨 Vehicle returned before expected minimum distance!";
                    $fraud_analysis['status'] = 'high_risk';
                    $fraud_analysis['recommendation'] = "🚨 MANUAL INSPECTION REQUIRED";
                    $red_light_active = true;
                }
                
                if ($fraud_analysis['status'] != 'high_risk') {
                    $_SESSION['approved_car'] = [
                        'car_id' => $car_info['id'],
                        'scanner_code' => $scanner_code,
                        'current_km' => $current_km
                    ];
                    $success = "✅ Vehicle approved! Redirecting to fuel sales...";
                }
            }
        }
    } catch(PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}

// If new car, redirect after 2 seconds
if ($is_new_car) {
    header("refresh:2;url=fuel_sales.php?new_car=1");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Fraud Detection - Fuel Station</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .scanner-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .scanner-input {
            display: flex;
            gap: 15px;
            max-width: 500px;
            margin: 20px auto;
        }

        .scanner-input input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
        }

        .scanner-input input:focus {
            outline: none;
            border-color: #4361ee;
        }

        .btn {
            padding: 15px 30px;
            background: #4361ee;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #3a56d4;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #4cc9f0;
        }

        .btn-success:hover {
            background: #3aa8d8;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }

        .vehicle-info {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #4361ee;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
        }

        .km-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .km-input {
            display: flex;
            gap: 15px;
            max-width: 400px;
            margin: 20px auto;
        }

        .km-input input {
            flex: 1;
            padding: 15px;
            border: 2px solid #4caf50;
            border-radius: 10px;
            font-size: 16px;
            text-align: center;
        }

        .fraud-result {
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .fraud-clean {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 6px solid #28a745;
        }

        .fraud-high-risk {
            background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
            border-left: 6px solid #dc3545;
        }

        .red-light-active {
            animation: redPulse 2s infinite;
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
        }

        @keyframes redPulse {
            0%, 100% { box-shadow: 0 0 30px rgba(255, 0, 0, 0.4); }
            50% { box-shadow: 0 0 60px rgba(255, 0, 0, 0.8); }
        }

        .status-light {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .light-green {
            background: #28a745;
            box-shadow: 0 0 30px rgba(40, 167, 69, 0.6);
        }

        .light-red {
            background: #dc3545;
            box-shadow: 0 0 40px rgba(220, 53, 69, 0.8);
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .new-car-welcome {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px 20px;
            border-radius: 15px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .fuel-breakdown {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }

        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .breakdown-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .breakdown-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .breakdown-value {
            font-size: 16px;
            font-weight: 700;
            color: #2196f3;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Advanced Fraud Detection</h1>
            <p>Scan vehicles to detect potential fuel fraud</p>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Scanner Section -->
        <div class="scanner-section">
            <h2><i class="fas fa-qrcode"></i> Vehicle Scanner</h2>
            <p>Enter scanner code to analyze vehicle history</p>
            
            <form method="POST">
                <div class="scanner-input">
                    <input type="text" name="scanner_code" placeholder="Enter scanner code..." required 
                           value="<?php echo isset($_POST['scanner_code']) ? htmlspecialchars($_POST['scanner_code']) : ''; ?>">
                    <button type="submit" name="scan_car" class="btn">
                        <i class="fas fa-search"></i> Scan Vehicle
                    </button>
                </div>
            </form>
        </div>

        <?php if ($is_new_car): ?>
            <!-- New Car Welcome -->
            <div class="new-car-welcome">
                <i class="fas fa-car-side" style="font-size: 4rem; color: #ff9800; margin-bottom: 1rem;"></i>
                <h2>🚗 New Vehicle Detected!</h2>
                <p style="font-size: 1.2rem; margin: 1rem 0;">This vehicle has no previous transaction history.</p>
                <p style="color: #666;">Redirecting to fuel sales for first-time refueling...</p>
                <div style="margin-top: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($car_info && !$is_new_car && $fraud_analysis): ?>
            <!-- Vehicle Information -->
            <div class="vehicle-info">
                <h3><i class="fas fa-car"></i> Vehicle History & Information</h3>
                
                <!-- Fuel Breakdown -->
                <div class="fuel-breakdown">
                    <h4><i class="fas fa-gas-pump"></i> Fuel Calculation Breakdown</h4>
                    <div class="breakdown-grid">
                        <div class="breakdown-item">
                            <div class="breakdown-label">Remaining Fuel Before Refill</div>
                            <div class="breakdown-value"><?php echo $fraud_analysis['vehicle_info']['remaining_fuel']; ?></div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-label">Fuel Purchased</div>
                            <div class="breakdown-value">+ <?php echo $fraud_analysis['vehicle_info']['last_fuel_purchased']; ?></div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-label">Total Fuel After Refill</div>
                            <div class="breakdown-value">= <?php echo $fraud_analysis['vehicle_info']['last_fuel_level']; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Last Fuel Level in Tank</div>
                        <div class="info-value"><?php echo $fraud_analysis['vehicle_info']['last_fuel_level']; ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Fuel Purchased at Station</div>
                        <div class="info-value"><?php echo $fraud_analysis['vehicle_info']['last_fuel_purchased']; ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Last KM Reading at Station</div>
                        <div class="info-value"><?php echo $fraud_analysis['vehicle_info']['last_km_reading']; ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Last Visit Date & Time</div>
                        <div class="info-value"><?php echo $fraud_analysis['vehicle_info']['last_visit_date']; ?></div>
                    </div>
                </div>
            </div>

            <!-- KM Input Form -->
            <div class="km-form">
                <h3><i class="fas fa-tachometer-alt"></i> Enter Current KM Reading</h3>
                <p>Please enter the current odometer reading to perform fraud analysis</p>
                
                <form method="POST">
                    <input type="hidden" name="scanner_code" value="<?php echo htmlspecialchars($_POST['scanner_code']); ?>">
                    <div class="km-input">
                        <input type="number" name="current_km_reading" placeholder="Current KM Reading" required step="1" min="0">
                        <button type="submit" name="submit_km" class="btn btn-success">
                            <i class="fas fa-shield-alt"></i> Check for Fraud
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($fraud_analysis && isset($fraud_analysis['red_light_active'])): ?>
            <!-- Fraud Analysis Result -->
            <div class="fraud-result <?php echo $fraud_analysis['red_light_active'] ? 'fraud-high-risk red-light-active' : 'fraud-clean'; ?>">
                <div class="status-light <?php echo $fraud_analysis['red_light_active'] ? 'light-red' : 'light-green'; ?>">
                    <i class="fas <?php echo $fraud_analysis['red_light_active'] ? 'fa-exclamation-triangle' : 'fa-check'; ?>"></i>
                </div>
                
                <h2>
                    <?php if ($fraud_analysis['red_light_active']): ?>
                        🚨 RED LIGHT - FRAUD DETECTED
                    <?php else: ?>
                        ✅ VEHICLE CLEARED
                    <?php endif; ?>
                </h2>
                
                <p style="font-size: 1.2rem; margin: 1rem 0; font-weight: 600;">
                    <?php echo $fraud_analysis['recommendation']; ?>
                </p>

                <!-- Travel Information -->
                <div class="fuel-breakdown">
                    <h4><i class="fas fa-route"></i> Travel Analysis</h4>
                    <div class="breakdown-grid">
                        <div class="breakdown-item">
                            <div class="breakdown-label">KM Traveled</div>
                            <div class="breakdown-value"><?php echo $fraud_analysis['vehicle_info']['km_traveled']; ?></div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-label">Current KM</div>
                            <div class="breakdown-value"><?php echo $fraud_analysis['vehicle_info']['current_km_reading']; ?></div>
                        </div>
                        <div class="breakdown-item">
                            <div class="breakdown-label">Last KM</div>
                            <div class="breakdown-value"><?php echo $fraud_analysis['vehicle_info']['last_km_reading']; ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($fraud_analysis['warnings'])): ?>
                    <div style="margin-top: 1.5rem;">
                        <?php foreach ($fraud_analysis['warnings'] as $warning): ?>
                            <div style="background: rgba(255,255,255,0.9); padding: 1rem; border-radius: 8px; margin: 0.5rem 0; border-left: 4px solid #dc3545;">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $warning; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
                    <?php if ($fraud_analysis['red_light_active']): ?>
                        <button class="btn" style="background: #dc3545;" disabled>
                            <i class="fas fa-ban"></i> Transaction Blocked
                        </button>
                        <a href="fuel_sales.php" class="btn" style="background: #ff9800; text-decoration: none;">
                            <i class="fas fa-user-shield"></i> Manual Override
                        </a>
                    <?php else: ?>
                        <a href="fuel_sales.php" class="btn btn-success" style="text-decoration: none;">
                            <i class="fas fa-gas-pump"></i> Proceed to Fuel Sale
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Audio Alert for Red Light -->
    <?php if ($red_light_active): ?>
    <audio autoplay loop>
        <source src="https://assets.mixkit.co/active_storage/sfx/250/250-preview.mp3" type="audio/mpeg">
    </audio>
    <?php endif; ?>
</body>
</html>