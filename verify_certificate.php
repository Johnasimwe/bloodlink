<?php
/**
 * Public Certificate Verification Page
 * Online Blood Donation Record Tracking System
 *
 * Usage:
 *  - verify_certificate.php?code=YOUR_VERIFICATION_CODE
 *
 * Looks up certificates.verification_code and displays certificate + donor details.
 */

session_start();
require_once __DIR__ . '/config/database.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$error = '';
$certificate = null;

if ($code !== '') {
    // Lookup certificate + donor
    $stmt = $conn->prepare("
        SELECT 
            c.certificate_id,
            c.verification_code,
            c.issue_date,
            c.donation_count,
            d.donor_id,
            d.full_name,
            d.email,
            d.blood_group,
            d.registration_date
        FROM certificates c
        JOIN donors d ON c.donor_id = d.donor_id
        WHERE c.verification_code = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $certificate = $res->fetch_assoc();
    $stmt->close();

    if (!$certificate) {
        $error = "No certificate was found for the provided verification code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify Certificate | BloodLink</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
            color: #1e293b;
            line-height: 1.5;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            margin: 0;
            color: #1e293b;
            font-size: 2.25rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .header h1 i {
            color: #b91c1c;
            margin-right: 0.75rem;
        }
        
        .header p {
            margin: 0.75rem 0 0 0;
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .form-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        
        .form-row input[type="text"] {
            flex: 1;
            min-width: 250px;
            padding: 1rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        
        .form-row input[type="text"]:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.1);
        }
        
        .form-row input[type="text"]::placeholder {
            color: #94a3b8;
        }
        
        .form-row button {
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.75rem;
            background: #b91c1c;
            color: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(185, 28, 28, 0.2);
        }
        
        .form-row button:hover {
            background: #991b1b;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(185, 28, 28, 0.3);
        }
        
        .form-row button i {
            font-size: 1rem;
        }
        
        .alert {
            padding: 1.25rem;
            border-radius: 1rem;
            margin-top: 1.5rem;
            border-left: 5px solid;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: #b91c1c;
        }
        
        .alert.success {
            background: #f0fdf4;
            color: #166534;
            border-left-color: #16a34a;
        }
        
        .alert.info {
            background: #eff6ff;
            color: #1e40af;
            border-left-color: #2563eb;
        }
        
        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .alert-title {
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-message {
            margin-top: 0.5rem;
            color: #475569;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }
        
        .badge.valid {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        
        .badge.invalid {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin: 2rem 0;
        }
        
        .info-card {
            background: #f8fafc;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .info-card:hover {
            border-color: #b91c1c;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .info-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .info-label i {
            color: #b91c1c;
            font-size: 1rem;
        }
        
        .info-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
        }
        
        .info-value.code {
            font-family: 'Monaco', 'Menlo', monospace;
            background: #e2e8f0;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            display: inline-block;
            font-size: 1rem;
        }
        
        .milestone-badge {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .milestone-badge i {
            font-size: 1.25rem;
        }
        
        .notice {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .notice-title {
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notice-text {
            color: #7f1d1d;
            line-height: 1.6;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px dashed #e2e8f0;
        }
        
        .actions a {
            text-decoration: none;
            padding: 0.875rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.95rem;
        }
        
        .actions .btn-home {
            background: #1e293b;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .actions .btn-home:hover {
            background: #0f172a;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .actions .btn-try {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        
        .actions .btn-try:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        footer {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        footer i {
            color: #fecaca;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .card {
                padding: 1.5rem;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 1.75rem;
            }
            
            .form-row button {
                width: 100%;
                justify-content: center;
            }
        }
        
        .example-hint {
            margin-top: 0.75rem;
            font-size: 0.9rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .example-hint i {
            color: #b91c1c;
        }
        
        .example-hint code {
            background: #e2e8f0;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1>
                <i class="fas fa-certificate"></i>
                Verify Certificate
            </h1>
            <p>Enter the verification code to confirm certificate authenticity</p>
        </div>

        <form method="GET" action="verify_certificate.php">
            <div class="form-row">
                <input 
                    type="text" 
                    name="code" 
                    placeholder="Enter verification code (e.g., A1B2C3D4E5F6)" 
                    value="<?php echo htmlspecialchars($code); ?>" 
                    required 
                />
                <button type="submit">
                    <i class="fas fa-shield-alt"></i>
                    Verify Now
                </button>
            </div>
            <?php if ($code === ''): ?>
            <div class="example-hint">
                <i class="fas fa-info-circle"></i>
                Example: <code>verify_certificate.php?code=YOUR_CODE_HERE</code>
            </div>
            <?php endif; ?>
        </form>

        <?php if ($code === ''): ?>
            <div class="alert info">
                <div class="alert-header">
                    <div class="alert-title">
                        <i class="fas fa-lightbulb"></i>
                        Ready to Verify
                    </div>
                </div>
                <div class="alert-message">
                    Enter a verification code above to check the authenticity of a certificate.
                </div>
            </div>
        <?php elseif (!empty($error)): ?>
            <div class="alert error">
                <div class="alert-header">
                    <div class="alert-title">
                        <i class="fas fa-times-circle"></i>
                        Invalid Certificate
                    </div>
                    <span class="badge invalid">
                        <i class="fas fa-ban"></i>
                        NOT VERIFIED
                    </span>
                </div>
                <div class="alert-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert success">
                <div class="alert-header">
                    <div class="alert-title">
                        <i class="fas fa-check-circle"></i>
                        Certificate Verified Successfully
                    </div>
                    <span class="badge valid">
                        <i class="fas fa-check"></i>
                        VERIFIED
                    </span>
                </div>
                <div class="alert-message">
                    This certificate is authentic and was issued by BloodLink.
                </div>
            </div>

            <div class="grid">
                <div class="info-card">
                    <div class="info-label">
                        <i class="fas fa-qrcode"></i>
                        Verification Code
                    </div>
                    <div class="info-value code">
                        <?php echo htmlspecialchars($certificate['verification_code']); ?>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">
                        <i class="fas fa-calendar-alt"></i>
                        Issue Date
                    </div>
                    <div class="info-value">
                        <?php echo date('F d, Y', strtotime($certificate['issue_date'])); ?>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-label">
                        <i class="fas fa-user"></i>
                        Donor Name
                    </div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($certificate['full_name']); ?>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">
                        <i class="fas fa-tint"></i>
                        Blood Group
                    </div>
                    <div class="info-value">
                        <span class="milestone-badge" style="background: #b91c1c; padding: 0.375rem 1rem; font-size: 1rem;">
                            <?php echo htmlspecialchars($certificate['blood_group']); ?>
                        </span>
                    </div>
                </div>

                <div class="info-card" style="grid-column: span 2;">
                    <div class="info-label">
                        <i class="fas fa-trophy"></i>
                        Milestone Achievement
                    </div>
                    <div class="info-value">
                        <span class="milestone-badge">
                            <i class="fas fa-heart"></i>
                            <?php echo (int)$certificate['donation_count']; ?> Approved Donation<?php echo (int)$certificate['donation_count'] !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="notice">
                <div class="notice-title">
                    <i class="fas fa-shield-alt"></i>
                    Verification Statement
                </div>
                <div class="notice-text">
                    This verification confirms that the certificate code exists in the BloodLink system and is associated with the donor shown above. The certificate is valid and recognized by our tracking system. For additional verification or inquiries, please contact the issuing organization.
                </div>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a class="btn-home" href="index.php">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
            <a class="btn-try" href="verify_certificate.php">
                <i class="fas fa-redo-alt"></i>
                Verify Another
            </a>
        </div>
    </div>

    <footer>
        <i class="fas fa-copyright"></i> <?php echo date('Y'); ?> BloodLink • Secure Certificate Verification
        <i class="fas fa-shield-alt"></i>
    </footer>
</div>
</body>
</html>