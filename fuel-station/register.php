<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $city = $_POST['city'];
    $address = $_POST['address'];
    $role = $_POST['role'];
    $verification_code = rand(100000, 999999);
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Username or email already exists!";
        } else {
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, email, phone, gender, city, address, role, verification_code) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $username, $password, $email, $phone, $gender, $city, $address, $role, $verification_code]);
            
            sendVerificationCode($email, $verification_code);
            $success = "Registration successful! Your verification code: <strong>$verification_code</strong>";
        }
    } catch(PDOException $e) {
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Fuel Station</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            margin: 20px auto;
            background: rgba(255,255,255,0.95);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
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
        .success { 
            background: #e8f5e9; 
            color: #2e7d32; 
            padding: 10px; 
            border-radius: 5px; 
            margin-bottom: 1rem;
            text-align: center;
        }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input, select, textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 16px;
        }
        textarea { resize: vertical; height: 80px; }
        .gender-options { 
            display: flex; 
            gap: 20px; 
            margin: 10px 0;
        }
        .gender-options label { font-weight: normal; }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #4caf50; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover { background: #45a049; }
        .login-link { 
            text-align: center; 
            margin-top: 1rem; 
            color: #666;
        }
        .login-link a { color: #2196f3; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📝 Create Account</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Choose a username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a password" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="Enter phone number" required>
            </div>
            
            <div class="form-group">
                <label>Gender</label>
                <div class="gender-options">
                    <label><input type="radio" name="gender" value="Male" required> Male</label>
                    <label><input type="radio" name="gender" value="Female"> Female</label>
                </div>
            </div>
            
            <div class="form-group">
                <label>City</label>
                <select name="city" required>
                    <option value="">Select City</option>
                    <option value="Addis Ababa">Addis Ababa</option>
                    <option value="Dire Dawa">Dire Dawa</option>
                    <option value="Mekelle">Mekelle</option>
                    <option value="Hawassa">Hawassa</option>
                    <option value="Bahir Dar">Bahir Dar</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" placeholder="Enter your address" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="Cashier">Cashier</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>
            
            <button type="submit">Register</button>
        </form>
        
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>