<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'donor';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        if ($role == 'donor') {
            // Check in donors table
            $stmt = $conn->prepare("SELECT donor_id, email, password, full_name FROM donors WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    $_SESSION['donor_id'] = $user['donor_id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_type'] = 'donor';
                    
                    header('Location: donor/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'No account found with that email.';
            }
        } else {
            // Check in admins table
            $stmt = $conn->prepare("SELECT admin_id, username, password, full_name FROM admins WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['user_name'] = $admin['full_name'];
                    $_SESSION['user_email'] = $admin['email'];
                    $_SESSION['user_type'] = 'admin';
                    
                    header('Location: admin/dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'No admin account found with that email.';
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
    <title>Login - Blood Donation System</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .blood-cells {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .blood-cell {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }
        
        .blood-cell:nth-child(1) {
            width: 100px;
            height: 100px;
            left: 5%;
            top: 10%;
            animation-duration: 25s;
        }
        
        .blood-cell:nth-child(2) {
            width: 150px;
            height: 150px;
            right: 10%;
            top: 20%;
            animation-duration: 30s;
        }
        
        .blood-cell:nth-child(3) {
            width: 80px;
            height: 80px;
            left: 20%;
            bottom: 10%;
            animation-duration: 20s;
        }
        
        .blood-cell:nth-child(4) {
            width: 200px;
            height: 200px;
            right: 20%;
            bottom: 5%;
            animation-duration: 35s;
        }
        
        .blood-cell:nth-child(5) {
            width: 120px;
            height: 120px;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            animation-duration: 40s;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg) scale(1);
                opacity: 0.1;
            }
            50% {
                transform: translateY(-50px) rotate(180deg) scale(1.2);
                opacity: 0.15;
            }
            100% {
                transform: translateY(0) rotate(360deg) scale(1);
                opacity: 0.1;
            }
        }
        
        /* Main Container */
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
            }
            50% {
                box-shadow: 0 20px 30px rgba(220, 38, 38, 0.5);
            }
            100% {
                box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
            }
        }
        
        .logo-icon i {
            font-size: 40px;
            color: white;
        }
        
        .logo-section h2 {
            color: #1e293b;
            font-size: 2em;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .logo-section p {
            color: #64748b;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .logo-section p i {
            color: #dc2626;
        }
        
        /* Messages */
        .error-message {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #b91c1c;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .success-message {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95em;
        }
        
        .form-group label i {
            color: #dc2626;
            width: 18px;
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            color: #94a3b8;
            font-size: 1.1em;
        }
        
        .input-wrapper input,
        .input-wrapper select {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s;
            background: white;
            font-family: inherit;
        }
        
        .input-wrapper select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23475669' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
        }
        
        .input-wrapper input:focus,
        .input-wrapper select:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
        }
        
        .input-wrapper input:hover,
        .input-wrapper select:hover {
            border-color: #b91c1c;
        }
        
        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1.1em;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: #dc2626;
        }
        
        /* Options Row */
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 0.95em;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #dc2626;
        }
        
        .forgot-link {
            color: #dc2626;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }
        
        .forgot-link:hover {
            color: #b91c1c;
            text-decoration: underline;
        }
        
        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-login:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.4);
        }
        
        .btn-login i {
            font-size: 1.1em;
        }
        
        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: #94a3b8;
            font-size: 0.9em;
        }
        
        .divider-line {
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        
        /* Register Link */
        .register-section {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .register-link {
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 30px;
            transition: all 0.3s;
        }
        
        .register-link i {
            color: #dc2626;
            transition: transform 0.3s;
        }
        
        .register-link:hover {
            background: #f1f5f9;
        }
        
        .register-link:hover i {
            transform: translateX(5px);
        }
        
        .register-link span {
            color: #dc2626;
            font-weight: 600;
            margin-left: 5px;
        }
        
        /* Back Link */
        .back-link {
            text-align: center;
        }
        
        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.95em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }
        
        .back-link a:hover {
            color: #dc2626;
        }
        
        .back-link a i {
            transition: transform 0.3s;
        }
        
        .back-link a:hover i {
            transform: translateX(-5px);
        }
        
        /* Features */
        .features {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .feature {
            text-align: center;
        }
        
        .feature i {
            font-size: 1.2em;
            color: #dc2626;
            margin-bottom: 5px;
        }
        
        .feature span {
            display: block;
            color: #64748b;
            font-size: 0.85em;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .logo-icon {
                width: 60px;
                height: 60px;
            }
            
            .logo-icon i {
                font-size: 30px;
            }
            
            .logo-section h2 {
                font-size: 1.5em;
            }
            
            .options-row {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .features {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="background">
        <div class="blood-cells">
            <div class="blood-cell"></div>
            <div class="blood-cell"></div>
            <div class="blood-cell"></div>
            <div class="blood-cell"></div>
            <div class="blood-cell"></div>
        </div>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-droplet"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>
                    <i class="fas fa-hand-holding-heart"></i>
                    Sign in to save lives
                </p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label>
                        <i class="fas fa-user-tag"></i>
                        Login As
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-users input-icon"></i>
                        <select id="role" name="role" required>
                            <option value="donor" <?php echo (!isset($_POST['role']) || $_POST['role'] === 'donor') ? 'selected' : ''; ?>>Donor</option>
                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="Enter your email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                    </div>
                </div>
                
                <div class="options-row">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <i class="fas fa-check-square"></i>
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-link">
                        <i class="fas fa-question-circle"></i>
                        Forgot Password?
                    </a>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div class="divider">
                <span class="divider-line"></span>
                <span>New to Blood Donation System?</span>
                <span class="divider-line"></span>
            </div>
            
            <div class="register-section">
                <a href="register.php" class="register-link">
                    <i class="fas fa-user-plus"></i>
                    Create an account
                    <span>→</span>
                </a>
            </div>
            
            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure</span>
                </div>
                <div class="feature">
                    <i class="fas fa-bolt"></i>
                    <span>Fast</span>
                </div>
                <div class="feature">
                    <i class="fas fa-heart"></i>
                    <span>Life-saving</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
            }
        });
        
        // Add floating label effect
        const inputs = document.querySelectorAll('.input-wrapper input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
    </script>
</body>
</html>