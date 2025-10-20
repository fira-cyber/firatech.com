<?php
require_once 'config.php';
requireLogin();

// Only admins can access user management
if ($_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';

// Get all users
try {
    $users_stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $users_stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error loading users: " . $e->getMessage();
}

// Delete user
if (isset($_GET['delete_user'])) {
    $user_id = $_GET['delete_user'];
    
    // Prevent deleting own account
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        try {
            $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->execute([$user_id]);
            $success = "User deleted successfully!";
            
            // Refresh users list
            $users_stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
            $users = $users_stmt->fetchAll();
        } catch(PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Toggle user status
if (isset($_GET['toggle_status'])) {
    $user_id = $_GET['toggle_status'];
    
    try {
        $user_stmt = $pdo->prepare("SELECT email_verified FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();
        
        $new_status = $user['email_verified'] ? 0 : 1;
        
        $update_stmt = $pdo->prepare("UPDATE users SET email_verified = ? WHERE id = ?");
        $update_stmt->execute([$new_status, $user_id]);
        
        $status_text = $new_status ? 'activated' : 'deactivated';
        $success = "User {$status_text} successfully!";
        
        // Refresh users list
        $users_stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        $users = $users_stmt->fetchAll();
    } catch(PDOException $e) {
        $error = "Error updating user status: " . $e->getMessage();
    }
}

// Count active users
$active_users = array_filter($users, function($user) { 
    return $user['email_verified']; 
});
$admin_users = array_filter($users, function($user) { 
    return $user['role'] === 'Admin'; 
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Smart Fuel Station</title>
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

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .role-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .role-admin {
            background: var(--info);
            color: white;
        }

        .role-cashier {
            background: var(--success);
            color: white;
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
                <a href="users.php" class="active">
                    <i class="fas fa-users"></i> User Management
                </a>
                <a href="inventory.php">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-users"></i> User Management</h1>
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

            <!-- User Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo count($users); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-number"><?php echo count($active_users); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="stat-number"><?php echo count($admin_users); ?></div>
                    <div class="stat-label">Admin Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-number"><?php echo count($users) - count($active_users); ?></div>
                    <div class="stat-label">Inactive Users</div>
                </div>
            </div>

            <!-- Users List -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users"></i>
                    <h2>System Users (<?php echo count($users); ?>)</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                <small style="color: #666;">@<?php echo htmlspecialchars($user['username']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['email']); ?><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($user['phone']); ?></small>
                            </td>
                            <td>
                                <span class="role-badge <?php echo $user['role'] === 'Admin' ? 'role-admin' : 'role-cashier'; ?>">
                                    <?php echo $user['role']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['email_verified'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['email_verified'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="?toggle_status=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm <?php echo $user['email_verified'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <i class="fas <?php echo $user['email_verified'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                        <?php echo $user['email_verified'] ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete_user=<?php echo $user['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this user?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                    <?php else: ?>
                                    <span class="btn btn-sm" style="background: #ccc; color: #666; cursor: not-allowed;">
                                        <i class="fas fa-lock"></i> Current User
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>