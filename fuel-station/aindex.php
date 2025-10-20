<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Fuel Station</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
        }
        
        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 3rem;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #00bfff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 1.1rem;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #009acd;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚗 Smart Fuel Station</h1>
        <p>Modern Fuel Management System</p>
        <a href="login.php" class="btn">Get Started</a>
    </div>
</body>
</html>