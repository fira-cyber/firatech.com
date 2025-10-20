<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';
$car_info = null;
$fraud_analysis = null;
$transaction_history = [];
$current_fuel_level = 0;
$last_km_reading = 0;

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
            // Check if this is a new car (no transactions yet)
            $transaction_check = $pdo->prepare("SELECT COUNT(*) as transaction_count FROM transactions WHERE car_id = ?");
            $transaction_check->execute([$car_info['id']]);
            $transaction_count = $transaction_check->fetch()['transaction_count'];
            
            if ($transaction_count == 0) {
                // New car - redirect to fuel sales
                $_SESSION['new_car_scanner'] = $scanner_code;
                header("Location: fuel_sales.php?new_car=1");
                exit();
            }
            
            // Get the latest transaction for current fuel level and KM reading
            $latest_stmt = $pdo->prepare("
                SELECT * FROM transactions 
                WHERE car_id = ? 
                ORDER BY transaction_date DESC 
                LIMIT 1
            ");
            $latest_stmt->execute([$car_info['id']]);
            $latest_transaction = $latest_stmt->fetch();
            
            if ($latest_transaction) {
                $current_fuel_level = $latest_transaction['current_fuel_level'] ?? 0;
                $last_km_reading = $latest_transaction['last_km_reading'] ?? 0;
            }
            
            // Get transaction history for this car
            $history_stmt = $pdo->prepare("
                SELECT t.*, u.full_name as cashier_name 
                FROM transactions t 
                LEFT JOIN users u ON t.cashier_id = u.id 
                WHERE t.car_id = ? 
                ORDER BY t.transaction_date DESC 
                LIMIT 10
            ");
            $history_stmt->execute([$car_info['id']]);
            $transaction_history = $history_stmt->fetchAll();
            
            // Perform advanced fraud analysis with KM/L calculation
            $fraud_analysis = analyzeFuelFraudWithKMPerLiter($pdo, $car_info['id'], $car_info['model'], $transaction_history, $current_fuel_level, $last_km_reading);
        }
    } catch(PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}

/**
 * Advanced fuel fraud detection with KM/L calculation
 */
function analyzeFuelFraudWithKMPerLiter($pdo, $car_id, $model, $transactions, $current_fuel_level, $last_km_reading) {
    $analysis = [
        'status' => 'clean',
        'confidence' => 100,
        'warnings' => [],
        'statistics' => [],
        'recommendation' => 'No fraud detected',
        'allowed_fuel_liters' => 0,
        'km_per_liter_analysis' => []
    ];
    
    if (count($transactions) < 2) {
        $analysis['warnings'][] = "Insufficient data for detailed analysis";
        $analysis['confidence'] = 70;
        return $analysis;
    }
    
    // Get expected fuel efficiency for this car model
    $expected_efficiency = getExpectedFuelEfficiency($model);
    $tank_capacity = getTankCapacity($model);
    
    // Calculate actual consumption from transaction history
    $consumption_data = calculateActualConsumption($transactions);
    
    $analysis['statistics'] = [
        'total_transactions' => count($transactions),
        'expected_efficiency' => $expected_efficiency['avg'] . ' km/L (' . $expected_efficiency['min'] . '-' . $expected_efficiency['max'] . ' km/L)',
        'tank_capacity' => $tank_capacity . ' liters',
        'current_fuel_level' => $current_fuel_level . ' liters',
        'last_km_reading' => number_format($last_km_reading) . ' km',
        'avg_actual_consumption' => $consumption_data['avg_consumption'] . ' km/L',
        'consumption_accuracy' => $consumption_data['accuracy'] . '%'
    ];
    
    $analysis['km_per_liter_analysis'] = $consumption_data['details'];
    
    // CRITICAL FRAUD DETECTION: Check if current refuel request makes sense
    
    // Get the proposed fuel purchase from form (if submitted)
    $proposed_liters = isset($_POST['fuel_liters']) ? floatval($_POST['fuel_liters']) : 0;
    
    if ($proposed_liters > 0) {
        // Calculate maximum allowed fuel based on expected consumption and last KM
        $max_allowed_fuel = calculateMaxAllowedFuel($current_fuel_level, $last_km_reading, $consumption_data, $tank_capacity, $expected_efficiency);
        $analysis['allowed_fuel_liters'] = $max_allowed_fuel;
        
        // Check if proposed fuel exceeds maximum allowed
        if ($proposed_liters > $max_allowed_fuel) {
            $analysis['warnings'][] = "🚨 CRITICAL FRAUD: Requested " . $proposed_liters . "L exceeds maximum allowed " . $max_allowed_fuel . "L";
            $analysis['confidence'] = 10;
            $analysis['status'] = 'high_risk';
            
            // Calculate expected distance
            $expected_distance_min = $proposed_liters * $expected_efficiency['min'];
            $expected_distance_max = $proposed_liters * $expected_efficiency['max'];
            $analysis['warnings'][] = "With " . $proposed_liters . "L, car should travel " . round($expected_distance_min) . "-" . round($expected_distance_max) . "km before next refuel";
        }
    }
    
    // Rule 1: Check consumption efficiency
    $efficiency_ratio = $consumption_data['avg_consumption'] / $expected_efficiency['avg'];
    if ($efficiency_ratio < 0.6) {
        $analysis['warnings'][] = "🚨 CRITICAL: Actual consumption (" . $consumption_data['avg_consumption'] . " km/L) is " . round((1-$efficiency_ratio)*100) . "% worse than expected (" . $expected_efficiency['avg'] . " km/L)";
        $analysis['confidence'] -= 40;
        $analysis['status'] = 'high_risk';
    } elseif ($efficiency_ratio < 0.8) {
        $analysis['warnings'][] = "⚠️ WARNING: Poor fuel efficiency (" . $consumption_data['avg_consumption'] . " km/L vs expected " . $expected_efficiency['avg'] . " km/L)";
        $analysis['confidence'] -= 20;
        if ($analysis['status'] == 'clean') $analysis['status'] = 'suspicious';
    }
    
    // Rule 2: Check for refueling more than tank capacity
    $max_refuel = 0;
    foreach ($transactions as $transaction) {
        if ($transaction['liters'] > $max_refuel) {
            $max_refuel = $transaction['liters'];
        }
    }
    
    if ($max_refuel > $tank_capacity) {
        $analysis['warnings'][] = "🚨 CRITICAL: Historical refuel of " . $max_refuel . "L exceeds tank capacity (" . $tank_capacity . "L)";
        $analysis['confidence'] -= 30;
        $analysis['status'] = 'high_risk';
    }
    
    // Rule 3: Check frequency patterns
    $frequency_analysis = analyzeRefuelFrequency($transactions);
    if ($frequency_analysis['suspicious']) {
        $analysis['warnings'][] = $frequency_analysis['message'];
        $analysis['confidence'] -= $frequency_analysis['penalty'];
        if ($analysis['status'] == 'clean') $analysis['status'] = 'suspicious';
    }
    
    // Set final recommendation
    if ($analysis['status'] == 'high_risk') {
        $analysis['recommendation'] = "🚨 REJECT TRANSACTION - High probability of fuel fraud";
    } elseif ($analysis['status'] == 'suspicious') {
        $analysis['recommendation'] = "⚠️ REVIEW REQUIRED - Suspicious patterns detected";
    } else {
        $analysis['recommendation'] = "✅ APPROVED - No significant fraud patterns detected";
    }
    
    $analysis['confidence'] = max(0, $analysis['confidence']);
    
    return $analysis;
}

/**
 * Calculate maximum allowed fuel based on consumption patterns
 */
function calculateMaxAllowedFuel($current_fuel_level, $last_km_reading, $consumption_data, $tank_capacity, $expected_efficiency) {
    // If we have consumption history, use it; otherwise use expected efficiency
    $effective_consumption = $consumption_data['avg_consumption'] > 0 ? $consumption_data['avg_consumption'] : $expected_efficiency['avg'];
    
    // Conservative calculation: assume car should use at least 70% of current fuel before refueling
    $min_fuel_used = $current_fuel_level * 0.7;
    
    // Calculate minimum distance that should be traveled with current fuel
    $min_expected_distance = $min_fuel_used * $effective_consumption;
    
    // Maximum allowed fuel = Tank capacity - (current fuel - reasonable consumption)
    $max_allowed = $tank_capacity - ($current_fuel_level - $min_fuel_used);
    
    // Ensure minimum of 5 liters and maximum of tank capacity
    return max(5, min($max_allowed, $tank_capacity));
}

/**
 * Calculate actual fuel consumption from transaction history
 */
function calculateActualConsumption($transactions) {
    $consumption_details = [];
    $total_consumption = 0;
    $valid_records = 0;
    
    for ($i = 0; $i < count($transactions) - 1; $i++) {
        $current = $transactions[$i];
        $previous = $transactions[$i + 1];
        
        // Check if we have KM readings and fuel data
        if (isset($current['last_km_reading']) && isset($previous['last_km_reading']) && 
            isset($current['liters']) && $current['liters'] > 0) {
            
            $distance = $current['last_km_reading'] - $previous['last_km_reading'];
            $fuel_used = $current['liters'];
            
            if ($distance > 0 && $fuel_used > 0) {
                $km_per_liter = $distance / $fuel_used;
                $consumption_details[] = [
                    'distance' => $distance,
                    'fuel_used' => $fuel_used,
                    'km_per_liter' => round($km_per_liter, 2),
                    'date' => $current['transaction_date']
                ];
                $total_consumption += $km_per_liter;
                $valid_records++;
            }
        }
    }
    
    $avg_consumption = $valid_records > 0 ? round($total_consumption / $valid_records, 2) : 0;
    $accuracy = $valid_records > 0 ? min(100, round(($valid_records / (count($transactions) - 1)) * 100)) : 0;
    
    return [
        'avg_consumption' => $avg_consumption,
        'accuracy' => $accuracy,
        'details' => $consumption_details
    ];
}

/**
 * Analyze refuel frequency patterns
 */
function analyzeRefuelFrequency($transactions) {
    $result = ['suspicious' => false, 'message' => '', 'penalty' => 0];
    $time_between_refuels = [];
    
    for ($i = 0; $i < count($transactions) - 1; $i++) {
        $current_time = strtotime($transactions[$i]['transaction_date']);
        $next_time = strtotime($transactions[$i + 1]['transaction_date']);
        $hours_between = ($current_time - $next_time) / 3600;
        
        if ($hours_between > 0) {
            $time_between_refuels[] = $hours_between;
        }
    }
    
    if (count($time_between_refuels) > 0) {
        $avg_hours = array_sum($time_between_refuels) / count($time_between_refuels);
        
        if ($avg_hours < 2) {
            $result['suspicious'] = true;
            $result['message'] = "🚨 CRITICAL: Excessive refueling frequency (every " . round($avg_hours, 1) . " hours)";
            $result['penalty'] = 40;
        } elseif ($avg_hours < 6) {
            $result['suspicious'] = true;
            $result['message'] = "⚠️ WARNING: High refueling frequency (every " . round($avg_hours, 1) . " hours)";
            $result['penalty'] = 20;
        }
    }
    
    return $result;
}

/**
 * Get expected fuel efficiency based on car model
 */
function getExpectedFuelEfficiency($model) {
    $model = strtolower($model);
    $efficiency_map = [
        'corolla' => ['min' => 12, 'max' => 14, 'avg' => 13],
        'camry' => ['min' => 10, 'max' => 12, 'avg' => 11],
        'civic' => ['min' => 13, 'max' => 15, 'avg' => 14],
        'hilux' => ['min' => 8, 'max' => 10, 'avg' => 9],
        'hiace' => ['min' => 7, 'max' => 9, 'avg' => 8],
        'land cruiser' => ['min' => 5, 'max' => 7, 'avg' => 6],
        'rav4' => ['min' => 11, 'max' => 13, 'avg' => 12],
        'lancer' => ['min' => 10, 'max' => 12, 'avg' => 11],
        'accent' => ['min' => 12, 'max' => 14, 'avg' => 13],
        'sunny' => ['min' => 11, 'max' => 13, 'avg' => 12],
        'bmw' => ['min' => 9, 'max' => 11, 'avg' => 10],
        'mercedes' => ['min' => 8, 'max' => 10, 'avg' => 9]
    ];
    
    foreach ($efficiency_map as $key => $value) {
        if (strpos($model, $key) !== false) {
            return $value;
        }
    }
    
    return ['min' => 10, 'max' => 12, 'avg' => 11]; // Default
}

/**
 * Get tank capacity based on car model
 */
function getTankCapacity($model) {
    $model = strtolower($model);
    $capacity_map = [
        'corolla' => 50, 'civic' => 47, 'hilux' => 80, 
        'land cruiser' => 110, 'lancer' => 59, 'accent' => 43,
        'camry' => 65, 'hiace' => 75, 'rav4' => 60,
        'bmw' => 60, 'mercedes' => 66, 'ranger' => 80, 'sunny' => 41
    ];
    
    foreach ($capacity_map as $key => $value) {
        if (strpos($model, $key) !== false) {
            return $value;
        }
    }
    
    return 55; // Default
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Fuel Fraud Detection - Smart Fuel Station</title>
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

        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem 0;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .logo {
            text-align: center;
            padding: 0 2rem 2rem;
            border-bottom: 2px solid var(--primary);
            margin-bottom: 1rem;
        }

        .logo h2 { color: var(--dark); font-size: 1.5rem; font-weight: 700; }
        .logo p { color: var(--primary); font-size: 0.9rem; }

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

        .nav-links a i { margin-right: 12px; font-size: 1.2rem; width: 24px; text-align: center; }
        .nav-links a:hover { background: var(--primary); color: white; transform: translateX(5px); }
        .nav-links a.active { background: var(--gradient); color: white; }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 { color: var(--dark); font-size: 2rem; font-weight: 700; }

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

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary);
        }

        .card-header i { font-size: 1.5rem; color: var(--primary); margin-right: 12px; }
        .card-header h2 { color: var(--dark); font-size: 1.5rem; font-weight: 600; }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

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
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .fraud-result {
            padding: 2rem;
            border-radius: 16px;
            margin: 1.5rem 0;
            border-left: 6px solid;
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

        .fraud-clean {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left-color: #28a745;
        }

        .fraud-suspicious {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left-color: #ffc107;
        }

        .fraud-high-risk {
            background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
            border-left-color: #dc3545;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .confidence-meter {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .confidence-fill {
            height: 100%;
            transition: width 0.5s ease;
        }

        .confidence-high { background: #28a745; }
        .confidence-medium { background: #ffc107; }
        .confidence-low { background: #dc3545; }

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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background: rgba(67, 97, 238, 0.05);
        }

        .warning-item {
            padding: 1rem;
            margin: 0.5rem 0;
            background: white;
            border-radius: 8px;
            border-left: 4px solid var(--warning);
        }

        .critical-item {
            padding: 1rem;
            margin: 0.5rem 0;
            background: #fff5f5;
            border-radius: 8px;
            border-left: 4px solid var(--danger);
        }

        .search-box {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .search-box .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .fuel-test-form {
            background: #e8f4fd;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
        }

        .consumption-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }

        .consumption-record {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .status-light {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: 10px auto;
            transition: all 0.3s;
        }

        .light-green {
            background: #28a745;
            box-shadow: 0 0 20px #28a745;
        }

        .light-red {
            background: #dc3545;
            box-shadow: 0 0 20px #dc3545;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="car_registration.php">
                    <i class="fas fa-car"></i> Car Registration
                </a>
                <a href="fuel_sales.php">
                    <i class="fas fa-gas-pump"></i> Fuel Sales
                </a>
                <a href="fraud_detection.php" class="active">
                    <i class="fas fa-shield-alt"></i> Fraud Detection
                </a>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <?php if ($_SESSION['role'] == 'Admin'): ?>
                <a href="users.php">
                    <i class="fas fa-users"></i> User Management
                </a>
                <a href="inventory.php">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-shield-alt"></i> Advanced Fuel Fraud Detection</h1>
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
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Scanner Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-qrcode"></i>
                    <h2>Vehicle Fraud Analysis Scanner</h2>
                </div>
                
                <form method="POST">
                    <div class="search-box">
                        <div class="form-group">
                            <label><i class="fas fa-barcode"></i> SCANNER CODE (RFID/Barcode)</label>
                            <input type="text" name="scanner_code" class="form-control" 
                                   placeholder="Enter vehicle scanner code" required 
                                   value="<?php echo isset($_POST['scanner_code']) ? htmlspecialchars($_POST['scanner_code']) : ''; ?>">
                        </div>
                        <button type="submit" name="scan_car" class="btn btn-primary">
                            <i class="fas fa-search"></i> Analyze Vehicle
                        </button>
                    </div>
                </form>

                <?php if ($car_info): ?>
                    <!-- Vehicle Information -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <i class="fas fa-car"></i>
                            <h2>Vehicle Information</h2>
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
                    </div>

                    <!-- Fuel Test Form -->
                    <div class="fuel-test-form">
                        <h3 style="margin-bottom: 1rem; color: var(--dark);">
                            <i class="fas fa-gas-pump"></i> Test Fuel Purchase
                        </h3>
                        <form method="POST">
                            <input type="hidden" name="scanner_code" value="<?php echo htmlspecialchars($_POST['scanner_code']); ?>">
                            <div class="search-box">
                                <div class="form-group">
                                    <label>Test Fuel Liters</label>
                                    <input type="number" name="fuel_liters" class="form-control" 
                                           step="0.1" min="1" max="100" 
                                           placeholder="Enter fuel liters to test" required>
                                </div>
                                <button type="submit" name="scan_car" class="btn btn-primary">
                                    <i class="fas fa-vial"></i> Test Purchase
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($fraud_analysis): ?>
                <!-- Fraud Analysis Results -->
                <div class="fraud-result <?php echo 'fraud-' . $fraud_analysis['status']; ?>">
                    <!-- Status Light -->
                    <div class="status-light <?php echo $fraud_analysis['status'] == 'high_risk' ? 'light-red' : 'light-green'; ?>"></div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2 style="color: var(--dark);">
                            <?php if ($fraud_analysis['status'] == 'high_risk'): ?>
                                <i class="fas fa-exclamation-triangle"></i> 🚨 TRANSACTION REJECTED - FRAUD DETECTED
                            <?php elseif ($fraud_analysis['status'] == 'suspicious'): ?>
                                <i class="fas fa-exclamation-circle"></i> ⚠️ MANUAL REVIEW REQUIRED
                            <?php else: ?>
                                <i class="fas fa-check-circle"></i> ✅ TRANSACTION APPROVED
                            <?php endif; ?>
                        </h2>
                        <div style="text-align: right;">
                            <div style="font-size: 2rem; font-weight: bold; color: var(--dark);">
                                <?php echo $fraud_analysis['confidence']; ?>%
                            </div>
                            <div style="color: #666; font-size: 0.9rem;">Confidence Score</div>
                        </div>
                    </div>

                    <!-- Confidence Meter -->
                    <div class="confidence-meter">
                        <div class="confidence-fill 
                            <?php 
                                if ($fraud_analysis['confidence'] >= 70) echo 'confidence-high';
                                elseif ($fraud_analysis['confidence'] >= 40) echo 'confidence-medium';
                                else echo 'confidence-low';
                            ?>" 
                            style="width: <?php echo $fraud_analysis['confidence']; ?>%">
                        </div>
                    </div>

                    <!-- Recommendation -->
                    <div style="background: white; padding: 1.5rem; border-radius: 12px; margin: 1rem 0;">
                        <h3 style="margin-bottom: 0.5rem; color: var(--dark);">
                            <i class="fas fa-clipboard-check"></i> System Decision
                        </h3>
                        <p style="font-size: 1.1rem; font-weight: 600;"><?php echo $fraud_analysis['recommendation']; ?></p>
                        
                        <?php if ($fraud_analysis['allowed_fuel_liters'] > 0): ?>
                            <div style="margin-top: 1rem; padding: 1rem; background: #e8f4fd; border-radius: 8px;">
                                <strong>Maximum Allowed Fuel:</strong> <?php echo $fraud_analysis['allowed_fuel_liters']; ?> liters
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Vehicle Statistics -->
                    <div class="stats-grid">
                        <?php foreach ($fraud_analysis['statistics'] as $key => $value): ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $value; ?></div>
                                <div class="stat-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- KM/L Consumption Details -->
                    <?php if (!empty($fraud_analysis['km_per_liter_analysis'])): ?>
                        <div class="consumption-details">
                            <h4 style="margin-bottom: 1rem; color: var(--dark);">
                                <i class="fas fa-chart-line"></i> Fuel Consumption History (KM/L)
                            </h4>
                            <?php foreach ($fraud_analysis['km_per_liter_analysis'] as $record): ?>
                                <div class="consumption-record">
                                    <span><?php echo date('M j, Y', strtotime($record['date'])); ?></span>
                                    <span><?php echo $record['distance']; ?> km / <?php echo $record['fuel_used']; ?> L</span>
                                    <span><strong><?php echo $record['km_per_liter']; ?> km/L</strong></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Warnings -->
                    <?php if (!empty($fraud_analysis['warnings'])): ?>
                        <div style="margin-top: 1.5rem;">
                            <h3 style="margin-bottom: 1rem; color: var(--dark);">
                                <i class="fas fa-exclamation-triangle"></i> Detection Warnings
                            </h3>
                            <?php foreach ($fraud_analysis['warnings'] as $warning): ?>
                                <div class="<?php echo strpos($warning, 'CRITICAL') !== false ? 'critical-item' : 'warning-item'; ?>">
                                    <?php echo $warning; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <?php if ($fraud_analysis['status'] == 'high_risk'): ?>
                            <button class="btn btn-danger" disabled>
                                <i class="fas fa-ban"></i> Transaction Blocked
                            </button>
                            <a href="fuel_sales.php" class="btn btn-primary">
                                <i class="fas fa-gas-pump"></i> Manual Override
                            </a>
                        <?php elseif ($fraud_analysis['status'] == 'suspicious'): ?>
                            <a href="fuel_sales.php" class="btn btn-warning">
                                <i class="fas fa-eye"></i> Review Transaction
                            </a>
                        <?php else: ?>
                            <a href="fuel_sales.php" class="btn btn-success">
                                <i class="fas fa-check"></i> Proceed to Fuel Sale
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Transaction History -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h2>Recent Transaction History</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Fuel Type</th>
                                <th>Liters</th>
                                <th>Last KM</th>
                                <th>Fuel Level</th>
                                <th>Amount</th>
                                <th>Cashier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaction_history as $transaction): ?>
                                <tr>
                                    <td><?php echo date('M j, Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                    <td>⛽ <?php echo htmlspecialchars($transaction['fuel_type']); ?></td>
                                    <td><?php echo number_format($transaction['liters'], 2); ?> L</td>
                                    <td><?php echo isset($transaction['last_km_reading']) ? number_format($transaction['last_km_reading']) : 'N/A'; ?> km</td>
                                    <td><?php echo isset($transaction['current_fuel_level']) ? number_format($transaction['current_fuel_level'], 2) : 'N/A'; ?> L</td>
                                    <td>ETB <?php echo number_format($transaction['total_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['cashier_name'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Audio Alert for Critical Fraud -->
    <?php if ($fraud_analysis && $fraud_analysis['status'] == 'high_risk'): ?>
    <audio autoplay loop>
        <source src="https://assets.mixkit.co/active_storage/sfx/250/250-preview.mp3" type="audio/mpeg">
    </audio>
    <?php endif; ?>
</body>
</html>