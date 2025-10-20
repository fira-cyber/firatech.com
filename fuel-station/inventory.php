<?php
require_once 'config.php';
requireLogin();

// Only admins can access inventory management
if ($_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';

// Get fuel tanks data
try {
    $tanks_stmt = $pdo->query("SELECT * FROM fuel_tanks ORDER BY tank_name");
    $tanks = $tanks_stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading inventory: " . $e->getMessage();
}

// Update fuel levels
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_levels'])) {
    try {
        foreach ($_POST['current_level'] as $tank_id => $level) {
            $update_stmt = $pdo->prepare("UPDATE fuel_tanks SET current_level = ? WHERE id = ?");
            $update_stmt->execute([$level, $tank_id]);
        }
        $success = "Fuel levels updated successfully!";
        
        // Refresh tanks data
        $tanks_stmt = $pdo->query("SELECT * FROM fuel_tanks ORDER BY tank_name");
        $tanks = $tanks_stmt->fetchAll();
    } catch(PDOException $e) {
        $error = "Error updating fuel levels: " . $e->getMessage();
    }
}

// Update fuel prices
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_prices'])) {
    try {
        // First, set all current prices to not current
        $pdo->exec("UPDATE fuel_prices SET is_current = 0");
        
        // Insert new prices
        foreach ($_POST['prices'] as $fuel_type => $price) {
            $insert_stmt = $pdo->prepare("
                INSERT INTO fuel_prices (fuel_type, price_per_liter, effective_date, is_current) 
                VALUES (?, ?, CURDATE(), 1)
            ");
            $insert_stmt->execute([$fuel_type, $price]);
        }
        $success = "Fuel prices updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating fuel prices: " . $e->getMessage();
    }
}

// Get current fuel prices
try {
    $prices_stmt = $pdo->query("SELECT * FROM fuel_prices WHERE is_current = 1");
    $current_prices = $prices_stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading fuel prices: " . $e->getMessage();
}

// Calculate inventory statistics
$total_capacity = 0;
$total_current = 0;
$low_tanks = 0;

foreach ($tanks as $tank) {
    $total_capacity += $tank['capacity'];
    $total_current += $tank['current_level'];
    if ($tank['current_level'] < $tank['min_threshold']) {
        $low_tanks++;
    }
}

$utilization_rate = $total_capacity > 0 ? ($total_current / $total_capacity) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Smart Fuel Station</title>
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

        .tank-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
            border-left: 4px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tank-low {
            border-left-color: var(--danger);
            background: #fff5f5;
        }

        .tank-ok {
            border-left-color: var(--success);
            background: #f0fff4;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .progress-fill {
            height: 100%;
            background: var(--success);
            transition: width 0.3s ease;
        }

        .progress-low {
            background: var(--danger);
        }

        .progress-warning {
            background: var(--warning);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--gradient);
            color: white;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin: 0.5rem 0;
            background: white;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
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
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="users.php">
                    <i class="fas fa-users"></i> User Management
                </a>
                <a href="inventory.php" class="active">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
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

            <!-- Inventory Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-gas-pump"></i></div>
                    <div class="stat-number"><?php echo count($tanks); ?></div>
                    <div class="stat-label">Fuel Tanks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-oil-can"></i></div>
                    <div class="stat-number"><?php echo number_format($total_current, 0); ?>L</div>
                    <div class="stat-label">Current Stock</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                    <div class="stat-number"><?php echo number_format($utilization_rate, 1); ?>%</div>
                    <div class="stat-label">Utilization Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-number"><?php echo $low_tanks; ?></div>
                    <div class="stat-label">Low Tanks</div>
                </div>
            </div>

            <div class="grid-2">
                <!-- Fuel Tank Levels -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-oil-can"></i>
                        <h2>Fuel Tank Management</h2>
                    </div>
                    
                    <form method="POST">
                        <?php foreach ($tanks as $tank): 
                            $percentage = ($tank['current_level'] / $tank['capacity']) * 100;
                            $is_low = $tank['current_level'] < $tank['min_threshold'];
                        ?>
                        <div class="tank-card <?php echo $is_low ? 'tank-low' : 'tank-ok'; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <h3 style="color: var(--dark);">
                                    <?php echo $tank['tank_name']; ?> 
                                    <small style="color: #666;">(<?php echo $tank['fuel_type']; ?>)</small>
                                </h3>
                                <?php if ($is_low): ?>
                                <span style="background: var(--danger); color: white; padding: 0.3rem 0.6rem; border-radius: 12px; font-size: 0.8rem;">
                                    <i class="fas fa-exclamation-circle"></i> LOW
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill <?php echo $percentage < 20 ? 'progress-low' : ($percentage < 30 ? 'progress-warning' : ''); ?>" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                                <span><?php echo number_format($tank['current_level'], 0); ?>L / <?php echo number_format($tank['capacity'], 0); ?>L</span>
                                <span><?php echo number_format($percentage, 1); ?>%</span>
                            </div>
                            
                            <div class="form-group">
                                <label>Update Current Level (Liters)</label>
                                <input type="number" name="current_level[<?php echo $tank['id']; ?>]" 
                                       class="form-control" 
                                       value="<?php echo $tank['current_level']; ?>"
                                       min="0" max="<?php echo $tank['capacity']; ?>"
                                       step="1">
                                <small style="color: #666;">Min. threshold: <?php echo number_format($tank['min_threshold'], 0); ?>L</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" name="update_levels" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Fuel Levels
                        </button>
                    </form>
                </div>

                <!-- Fuel Price Management -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tags"></i>
                        <h2>Fuel Price Management</h2>
                    </div>
                    
                    <form method="POST">
                        <?php 
                        $fuel_types = ['Gasoline', 'Diesel', 'Premium'];
                        foreach ($fuel_types as $fuel_type): 
                            $current_price = 0;
                            foreach ($current_prices as $price) {
                                if ($price['fuel_type'] === $fuel_type) {
                                    $current_price = $price['price_per_liter'];
                                    break;
                                }
                            }
                        ?>
                        <div class="price-item">
                            <div>
                                <strong>⛽ <?php echo $fuel_type; ?></strong>
                                <div style="color: #666; font-size: 0.9rem;">Current: ETB <?php echo number_format($current_price, 2); ?>/L</div>
                            </div>
                            <div class="form-group" style="margin: 0; width: 150px;">
                                <input type="number" name="prices[<?php echo $fuel_type; ?>]" 
                                       class="form-control" 
                                       value="<?php echo $current_price; ?>"
                                       min="0" step="0.01"
                                       placeholder="New price" required>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" name="update_prices" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-sync-alt"></i> Update Fuel Prices
                        </button>
                    </form>
                    
                    <!-- Current Prices Display -->
                    <div style="margin-top: 2rem; padding-top: 1rem; border-top: 2px solid #e9ecef;">
                        <h3 style="margin-bottom: 1rem; color: var(--dark);">
                            <i class="fas fa-info-circle"></i> Current Fuel Prices
                        </h3>
                        <?php foreach ($current_prices as $price): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                            <span>⛽ <?php echo $price['fuel_type']; ?></span>
                            <span style="font-weight: 600; color: var(--primary);">
                                ETB <?php echo number_format($price['price_per_liter'], 2); ?> / liter
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Inventory Alerts -->
            <?php if ($low_tanks > 0): ?>
            <div class="card" style="border-left: 6px solid var(--danger);">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2 style="color: var(--danger);">Inventory Alerts</h2>
                </div>
                <div style="color: var(--danger);">
                    <p><strong>Warning:</strong> <?php echo $low_tanks; ?> fuel tank(s) are below minimum threshold and need refilling.</p>
                    <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                        <?php foreach ($tanks as $tank): 
                            if ($tank['current_level'] < $tank['min_threshold']): ?>
                        <li>
                            <strong><?php echo $tank['tank_name']; ?></strong> (<?php echo $tank['fuel_type']; ?>): 
                            <?php echo number_format($tank['current_level'], 0); ?>L remaining 
                            (min: <?php echo number_format($tank['min_threshold'], 0); ?>L)
                        </li>
                        <?php endif; endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>