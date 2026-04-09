<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
    $blood_group = isset($_POST['blood_group']) ? trim($_POST['blood_group']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $national_id = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT donor_id FROM donors WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'This email is already registered.';
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert new donor
            $stmt = $conn->prepare("INSERT INTO donors (full_name, email, password, phone, gender, date_of_birth, blood_group, address, national_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $full_name, $email, $hashed_password, $phone, $gender, $date_of_birth, $blood_group, $address, $national_id);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! Please <a href="login.php">login here</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Blood Donation System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
        }
        
        .register-container h2 {
            color: #d32f2f;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2em;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #d32f2f;
            box-shadow: 0 0 5px rgba(211, 47, 47, 0.3);
        }
        
        .form-group select {
            cursor: pointer;
        }
        
        .form-group small {
            color: #999;
            display: block;
            margin-top: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background-color: #d32f2f;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn:hover {
            background-color: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #d32f2f;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: #d32f2f;
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            text-align: center;
            margin-top: 10px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .register-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>📋 Register as Donor</h2>
        
        <?php
        if (!empty($error)) {
            echo '<div class="error-message">' . htmlspecialchars($error) . '</div>';
        }
        if (!empty($success)) {
            echo '<div class="success-message">' . $success . '</div>';
        }
        ?>
        
        <form method="POST" action="register.php">
            <div class="form-row full">
                <div class="form-group">
                    <label for="full_name">Full Name: *</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
            </div>
            
            <div class="form-row full">
                <div class="form-group">
                    <label for="email">Email: *</label>
                    <input type="email" id="email" name="email" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password: *</label>
                    <input type="password" id="password" name="password" required>
                    <small>At least 6 characters</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password: *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                <div class="form-group">
                    <label for="gender">Gender:</label>
                    <select id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth:</label>
                    <input type="date" id="date_of_birth" name="date_of_birth">
                </div>
                <div class="form-group">
                    <label for="blood_group">Blood Group:</label>
                    <select id="blood_group" name="blood_group">
                        <option value="">Select Blood Group</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row full">
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address">
                </div>
            </div>
            
            <div class="form-row full">
                <div class="form-group">
                    <label for="national_id">National ID:</label>
                    <input type="text" id="national_id" name="national_id">
                </div>
            </div>
            
            <button type="submit" class="btn">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
        
        <div class="back-link">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>