<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $car_name = trim($_POST['car_name']);
    $model = trim($_POST['model']);
    $plate_number = trim($_POST['plate_number']);
    $owner_name = trim($_POST['owner_name']);
    $scanner_code = trim($_POST['scanner_code']);
    
    try {
        // Check if plate number or scanner code already exists
        $check_stmt = $pdo->prepare("SELECT id FROM cars WHERE plate_number = ? OR scanner_code = ?");
        $check_stmt->execute([$plate_number, $scanner_code]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "❌ Plate number or scanner code already exists!";
        } else {
            // Insert new car
            $stmt = $pdo->prepare("INSERT INTO cars (car_name, model, plate_number, owner_name, scanner_code) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$car_name, $model, $plate_number, $owner_name, $scanner_code]);
            
            $success = "✅ Car registered successfully! Scanner code: <strong>$scanner_code</strong>";
        }
    } catch(PDOException $e) {
        $error = "❌ Database error: " . $e->getMessage();
    }
}

// Get all registered cars for display
try {
    $cars_stmt = $pdo->query("SELECT * FROM cars ORDER BY created_at DESC");
    $cars = $cars_stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading cars: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Registration - Fuel Station</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: #f8f9fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar {
            width: 250px;
            background: #2c3e50;
            height: calc(100vh - 70px);
            position: fixed;
            padding: 20px 0;
        }
        
        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            margin: 5px 0;
            transition: background 0.3s;
        }
        
        .sidebar a:hover { 
            background: #34495e; 
            border-left: 4px solid #3498db;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .form-section, .list-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h2 { 
            color: #2c3e50; 
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 16px;
        }
        input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        button { 
            padding: 12px 25px; 
            background: #27ae60; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover { background: #219a52; }
        
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 12px; 
            border-radius: 5px; 
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }
        
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 12px; 
            border-radius: 5px; 
            margin-bottom: 1rem;
            border-left: 4px solid #dc3545;
        }
        
        .cars-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .cars-table th,
        .cars-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .cars-table th {
            background: #34495e;
            color: white;
        }
        
        .cars-table tr:hover {
            background: #f8f9fa;
        }
        
        .scanner-code {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover { background: #c0392b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚗 Car Registration System</h1>
        <div>
            Welcome, <strong><?php echo $_SESSION['full_name']; ?></strong> 
            (<?php echo $_SESSION['role']; ?>)
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="sidebar">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="car_registration.php" style="background: #34495e; border-left: 4px solid #3498db;">
            🚗 Car Registration
        </a>
        <a href="fuel_sales.php">⛽ Fuel Sales</a>
        <a href="reports.php">📈 Reports</a>
        <?php if ($_SESSION['role'] == 'Admin'): ?>
            <a href="users.php">👥 User Management</a>
            <a href="inventory.php">📦 Inventory</a>
        <?php endif; ?>
    </div>
    
    <div class="main-content">
        <div class="container">
            <!-- Registration Form -->
            <div class="form-section">
                <h2>Register New Car</h2>
                
                <?php if ($success): ?>
                    <div class="success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Car Name</label>
                        <input type="text" name="car_name" placeholder="e.g., Toyota Corolla" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" placeholder="e.g., 2023 GLI" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" placeholder="e.g., AA123BB" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" placeholder="e.g., John Smith" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Scanner Code (RFID/Barcode)</label>
                        <input type="text" name="scanner_code" placeholder="e.g., CAR001" required>
                        <small style="color: #666;">Unique code for scanning at fuel station</small>
                    </div>
                    
                    <button type="submit">🚗 Register Car</button>
                </form>
            </div>
            
            <!-- Registered Cars List -->
            <div class="list-section">
                <h2>Registered Cars (<?php echo count($cars); ?>)</h2>
                
                <?php if (count($cars) > 0): ?>
                    <table class="cars-table">
                        <thead>
                            <tr>
                                <th>Car Name</th>
                                <th>Plate No.</th>
                                <th>Owner</th>
                                <th>Scanner Code</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cars as $car): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($car['car_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($car['plate_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($car['owner_name']); ?></td>
                                    <td><code class="scanner-code"><?php echo htmlspecialchars($car['scanner_code']); ?></code></td>
                                    <td><?php echo date('M j, Y', strtotime($car['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No cars registered yet.</p>
                        <p>Register your first car using the form!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h3>💡 How It Works</h3>
            <ul style="line-height: 1.6; color: #555;">
                <li>Register all vehicles that will use your fuel station</li>
                <li>Each car gets a unique scanner code (RFID/barcode)</li>
                <li>Use the scanner code for quick fuel sales</li>
                <li>Prevents duplicate registrations and fraud</li>
                <li>Track fuel consumption by vehicle</li>
            </ul>
        </div>
    </div>
</body>
</html>