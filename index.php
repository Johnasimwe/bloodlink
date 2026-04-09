<?php
session_start();
require_once 'config/database.php';

// Get system statistics
$total_donors = $conn->query("SELECT COUNT(*) as count FROM donors")->fetch_assoc()['count'];
$total_donations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Approved'")->fetch_assoc()['count'];
$pending_approvals = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Pending'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BloodLink | Donation Tracking System</title>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #1e293b;
        }
        
        header {
            background: linear-gradient(135deg, #991b1b 0%, #b91c1c 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        header h1 i {
            margin-right: 10px;
            font-size: 2.5rem;
        }
        
        header p {
            margin-top: 0.75rem;
            font-size: 1.1rem;
            font-weight: 300;
            opacity: 0.95;
        }
        
        nav {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        nav a {
            color: #475569;
            text-decoration: none;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s;
            border-radius: 0.375rem;
            font-size: 0.95rem;
        }
        
        nav a:hover {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        nav a i {
            margin-right: 6px;
            font-size: 0.9rem;
        }
        
        .container {
            flex: 1;
            max-width: 1280px;
            margin: 3rem auto;
            padding: 0 2rem;
            width: 100%;
        }
        
        .welcome-section {
            background: white;
            padding: 3rem;
            border-radius: 1.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .welcome-section h2 {
            color: #1e293b;
            margin-bottom: 1.25rem;
            font-size: 2.25rem;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .welcome-section h2 i {
            color: #b91c1c;
            margin-right: 10px;
        }
        
        .welcome-section p {
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin: 2.5rem 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            color: #b91c1c;
            margin-bottom: 1rem;
        }
        
        .stat-card h3 {
            color: #64748b;
            margin-bottom: 0.75rem;
            font-size: 1rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        
        .features {
            background: white;
            padding: 3rem;
            border-radius: 1.5rem;
            margin: 2.5rem 0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .features h2 {
            color: #1e293b;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .features h2 i {
            color: #b91c1c;
            margin-right: 10px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 2rem;
        }
        
        .feature-item {
            padding: 1.5rem;
            border-radius: 0.75rem;
            background: #f8fafc;
            transition: all 0.2s;
        }
        
        .feature-item:hover {
            background: #fee2e2;
        }
        
        .feature-item i {
            font-size: 2rem;
            color: #b91c1c;
            margin-bottom: 1rem;
        }
        
        .feature-item h3 {
            color: #1e293b;
            margin-bottom: 0.75rem;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .feature-item p {
            color: #64748b;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2.5rem;
        }
        
        .btn {
            padding: 0.875rem 2.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            gap: 0.5rem;
        }
        
        .btn i {
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #b91c1c;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(185, 28, 28, 0.2);
        }
        
        .btn-primary:hover {
            background: #991b1b;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(185, 28, 28, 0.3);
        }
        
        .btn-secondary {
            background: #475569;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(71, 85, 105, 0.2);
        }
        
        .btn-secondary:hover {
            background: #334155;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(71, 85, 105, 0.3);
        }
        
        footer {
            background: #1e293b;
            color: #94a3b8;
            text-align: center;
            padding: 2rem;
            margin-top: auto;
            border-top: 1px solid #334155;
        }
        
        footer p {
            font-size: 0.95rem;
        }
        
        footer i {
            color: #b91c1c;
            margin: 0 4px;
        }
        
        @media (max-width: 640px) {
            header h1 {
                font-size: 1.75rem;
            }
            
            .container {
                padding: 0 1rem;
                margin: 2rem auto;
            }
            
            .welcome-section,
            .features {
                padding: 2rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>
            <i class="fas fa-droplet"></i>
            BloodLink
        </h1>
        <p>Save Lives. Track Donations. Earn Recognition.</p>
    </header>
    
    <nav>
        <a href="index.php"><i class="fas fa-home"></i>Home</a>
        <a href="about.php"><i class="fas fa-info-circle"></i>About</a>
        <a href="contact.php"><i class="fas fa-envelope"></i>Contact</a>
        <?php
        if (isset($_SESSION['donor_id'])) {
            echo '<a href="donor/dashboard.php"><i class="fas fa-tachometer-alt"></i>Donor Dashboard</a>';
            echo '<a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>';
        } elseif (isset($_SESSION['admin_id'])) {
            echo '<a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i>Admin Dashboard</a>';
            echo '<a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>';
        } else {
            echo '<a href="login.php"><i class="fas fa-sign-in-alt"></i>Login</a>';
            echo '<a href="register.php"><i class="fas fa-user-plus"></i>Register</a>';
        }
        ?>
    </nav>
    
    <div class="container">
        <div class="welcome-section">
            <h2>
                <i class="fas fa-heart"></i>
                Making a Difference Together
            </h2>
            <p>
                Join thousands of compassionate donors who are saving lives every day. 
                Our platform makes it easy to track your donations, monitor your impact, 
                and receive recognition for your generous contributions.
            </p>
            
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3>Total Donors</h3>
                    <div class="number"><?php echo number_format($total_donors); ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <h3>Donations Approved</h3>
                    <div class="number"><?php echo number_format($total_donations); ?></div>
                </div>
               
            </div>
        </div>
        
        <div class="features">
            <h2>
                <i class="fas fa-star"></i>
                Why Donate Blood?
            </h2>
            <div class="features-grid">
                <div class="feature-item">
                    <i class="fas fa-heartbeat"></i>
                    <h3>Save Lives</h3>
                    <p>One donation can save up to three lives. Your contribution makes a real difference in your community.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Health Benefits</h3>
                    <p>Regular blood donation helps maintain healthy iron levels and may reduce cardiovascular risks.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <h3>Track Progress</h3>
                    <p>Monitor your donation history and earn recognition certificates at important milestones.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-globe"></i>
                    <h3>Community Impact</h3>
                    <p>Be part of a lifesaving network ensuring blood availability for emergencies and medical needs.</p>
                </div>
            </div>
        </div>
        
        <?php if (!isset($_SESSION['donor_id']) && !isset($_SESSION['admin_id'])): ?>
        <div class="cta-buttons">
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i>
                Register as Donor
            </a>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i>
                Login to Account
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>
            <i class="fas fa-copyright"></i> 2026 BloodLink. 
            Powered by <i class="fas fa-heart"></i> Red Cross. 
            All rights reserved.
        </p>
    </footer>
</body>
</html>