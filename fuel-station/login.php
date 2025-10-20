<?php
require_once 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND email_verified = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fuel Station</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
         background-image: url('.jpg');
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-box {
            background: rgba(255,255,255,0.95);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            width: 350px;
        }
        h2 { text-align: center; margin-bottom: 1.5rem; color: #333; }
        .error { 
            background: #ffebee; 
            color: #c62828; 
            padding: 10px; 
            border-radius: 5px; 
            margin-bottom: 1rem;
            text-align: center;
        }
        .demo-info {
            background: #e3f2fd;
            color: #1565c0;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }
        input { 
            width: 100%; 
            padding: 12px; 
            margin: 8px 0; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 16px;
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #2196f3; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover { background: #1976d2; }
        .links { 
            text-align: center; 
            margin-top: 1rem; 
            color: #666;
        }
        .links a { color: #2196f3; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔐 Fuel Station Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="demo-info">
            <strong>Demo Account:</strong><br>
            Username: <strong>admin</strong><br>
            Password: <strong>admin123</strong>
        </div>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" value="admin" required>
            <input type="password" name="password" placeholder="Password" value="admin123" required>
            <button type="submit">Login</button>
        </form>
        
        <div class="links">
            <p>Don't have an account? <a href="register.php">Sign up</a></p>
            <p><a href="setup_database.php">First time setup</a></p>
        </div>
    </div>
</body>
</html>