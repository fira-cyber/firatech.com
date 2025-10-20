<?php
require_once 'config.php';
requireLogin();

// Date range for reports
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Get report data
try {
    // Sales summary
    $sales_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            COALESCE(SUM(liters), 0) as total_liters,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            AVG(total_amount) as avg_transaction
        FROM transactions 
        WHERE DATE(transaction_date) BETWEEN ? AND ?
    ");
    $sales_stmt->execute([$start_date, $end_date]);
    $sales_summary = $sales_stmt->fetch();

    // Fuel type breakdown
    $fuel_stmt = $pdo->prepare("
        SELECT 
            fuel_type,
            COUNT(*) as transactions,
            SUM(liters) as liters,
            SUM(total_amount) as revenue
        FROM transactions 
        WHERE DATE(transaction_date) BETWEEN ? AND ?
        GROUP BY fuel_type
        ORDER BY revenue DESC
    ");
    $fuel_stmt->execute([$start_date, $end_date]);
    $fuel_breakdown = $fuel_stmt->fetchAll();

    // Daily sales trend
    $daily_stmt = $pdo->prepare("
        SELECT 
            DATE(transaction_date) as date,
            COUNT(*) as transactions,
            SUM(liters) as liters,
            SUM(total_amount) as revenue
        FROM transactions 
        WHERE DATE(transaction_date) BETWEEN ? AND ?
        GROUP BY DATE(transaction_date)
        ORDER BY date
    ");
    $daily_stmt->execute([$start_date, $end_date]);
    $daily_trend = $daily_stmt->fetchAll();

    // Top vehicles
    $vehicles_stmt = $pdo->prepare("
        SELECT 
            c.car_name,
            c.plate_number,
            COUNT(*) as refuels,
            SUM(t.liters) as total_liters,
            SUM(t.total_amount) as total_spent
        FROM transactions t
        JOIN cars c ON t.car_id = c.id
        WHERE DATE(t.transaction_date) BETWEEN ? AND ?
        GROUP BY t.car_id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $vehicles_stmt->execute([$start_date, $end_date]);
    $top_vehicles = $vehicles_stmt->fetchAll();

} catch(PDOException $e) {
    die("Error loading reports: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Smart Fuel Station</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 2rem; margin-bottom: 0.5rem; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--dark); margin: 0.5rem 0; }
        .stat-label { color: #666; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }

        .date-filter {
            background: rgba(255, 255, 255, 0.9);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--dark); font-weight: 600; }
        .form-control { width: 100%; padding: 0.75rem; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .btn-primary { background: var(--gradient); color: white; }

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

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
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
                <a href="reports.php" class="active">
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
                <h1><i class="fas fa-chart-bar"></i> Analytics & Reports</h1>
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

            <!-- Date Filter -->
            <div class="date-filter">
                <form method="GET" class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-start"></i> Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-end"></i> End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter Reports
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sales Summary -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <h2>Sales Summary (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</h2>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                        <div class="stat-number"><?php echo number_format($sales_summary['total_transactions']); ?></div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-gas-pump"></i></div>
                        <div class="stat-number"><?php echo number_format($sales_summary['total_liters'], 1); ?>L</div>
                        <div class="stat-label">Fuel Sold</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stat-number">ETB <?php echo number_format($sales_summary['total_revenue'], 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calculator"></i></div>
                        <div class="stat-number">ETB <?php echo number_format($sales_summary['avg_transaction'], 2); ?></div>
                        <div class="stat-label">Avg. Transaction</div>
                    </div>
                </div>
            </div>

            <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Fuel Type Breakdown -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-oil-can"></i>
                        <h2>Fuel Type Breakdown</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Fuel Type</th>
                                <th>Transactions</th>
                                <th>Liters</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fuel_breakdown as $fuel): ?>
                            <tr>
                                <td>⛽ <?php echo $fuel['fuel_type']; ?></td>
                                <td><?php echo number_format($fuel['transactions']); ?></td>
                                <td><?php echo number_format($fuel['liters'], 1); ?>L</td>
                                <td>ETB <?php echo number_format($fuel['revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Top Vehicles -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-car"></i>
                        <h2>Top Vehicles</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Refuels</th>
                                <th>Total Liters</th>
                                <th>Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_vehicles as $vehicle): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($vehicle['car_name']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($vehicle['plate_number']); ?></small>
                                </td>
                                <td><?php echo number_format($vehicle['refuels']); ?></td>
                                <td><?php echo number_format($vehicle['total_liters'], 1); ?>L</td>
                                <td>ETB <?php echo number_format($vehicle['total_spent'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Daily Sales Trend -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line"></i>
                    <h2>Daily Sales Trend</h2>
                </div>
                <div class="chart-container">
                    <canvas id="dailyChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Daily Sales Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($day) { return "'" . date('M j', strtotime($day['date'])) . "'"; }, $daily_trend)); ?>],
                datasets: [{
                    label: 'Daily Revenue (ETB)',
                    data: [<?php echo implode(',', array_column($daily_trend, 'revenue')); ?>],
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Revenue Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue (ETB)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>