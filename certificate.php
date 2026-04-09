<?php
/**
 * Minimal Text-Only Donor Certificate Generator
 * Online Blood Donation Record Tracking System
 *
 * - Eligibility: 10 approved donations
 * - Generates downloadable PDF certificate (text only, no images/signatures/QR/logos/decorations)
 * - Stores a certificate row in `certificates` if not already created
 */

$page_title = 'My Certificate - Blood Donation System';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireDonor();
require_once __DIR__ . '/../vendor/autoload.php'; // TCPDF via Composer

$donor_id = (int)$_SESSION['donor_id'];
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'view'; // view|download

// Settings
$goal_donations = 10;
$org_name = 'Blood Donation Program';

// Fetch donor info
$stmt = $conn->prepare("SELECT donor_id, full_name, email, blood_group, registration_date FROM donors WHERE donor_id = ?");
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$donor) {
    redirectWithMessage(__DIR__ . '/../login.php', 'Donor not found.', 'error');
    exit();
}

// Count approved donations
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM donations WHERE donor_id = ? AND status = 'Approved'");
$stmt->bind_param("i", $donor_id);
$stmt->execute();
$approved_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$eligible = $approved_count >= $goal_donations;

// Fetch or create certificate
$certificate = null;
if ($eligible) {
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE donor_id = ? AND donation_count >= ? ORDER BY issue_date DESC LIMIT 1");
    $stmt->bind_param("ii", $donor_id, $goal_donations);
    $stmt->execute();
    $certificate = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$certificate) {
        $verification_code = strtoupper(bin2hex(random_bytes(6))) . '-' . $donor_id;
        $issue_date = date('Y-m-d H:i:s');
        $qr_code = null;

        $stmt = $conn->prepare("
            INSERT INTO certificates (donor_id, verification_code, qr_code, issue_date, donation_count)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssi", $donor_id, $verification_code, $qr_code, $issue_date, $goal_donations);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("SELECT * FROM certificates WHERE donor_id = ? ORDER BY issue_date DESC LIMIT 1");
        $stmt->bind_param("i", $donor_id);
        $stmt->execute();
        $certificate = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Download PDF (PURE TEXT ONLY - NO IMAGES, NO SIGNATURES, NO QR CODES, NO DECORATIONS)
if ($action === 'download') {
    if (!$eligible || !$certificate) {
        redirectWithMessage('certificate.php', 'You are not yet eligible to download a certificate. Reach 10 approved donations.', 'error');
        exit();
    }

    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator($org_name);
    $pdf->SetAuthor($org_name);
    $pdf->SetTitle('Blood Donation Certificate');
    $pdf->SetSubject('Blood Donation Certificate');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(25, 25, 25);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $issue_date_fmt = date('F d, Y', strtotime($certificate['issue_date']));
    $full_name = $donor['full_name']; // Not converting to uppercase to keep it natural
    $blood_group = !empty($donor['blood_group']) ? $donor['blood_group'] : 'Not Specified';
    $donation_count = (int)$approved_count;
    $verification_code = $certificate['verification_code'];

    // PURE TEXT CERTIFICATE - NO DECORATIVE ELEMENTS WHATSOEVER
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'CERTIFICATE OF BLOOD DONATION', 0, 1, 'L');
    $pdf->Ln(4);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 6, 'Date: ' . $issue_date_fmt, 0, 1, 'L');
    $pdf->Ln(8);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Write(0, 'This certificate is awarded to:');
    $pdf->Ln(6);
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Write(0, $full_name);
    $pdf->Ln(8);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Write(0, 'Blood Group: ' . $blood_group);
    $pdf->Ln(8);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Write(0, 'In recognition of your voluntary blood donations. According to official records,');
    $pdf->Ln(5);
    $pdf->Write(0, 'you have successfully completed ' . $donation_count . ' blood donations.');
    $pdf->Ln(8);
    
    $pdf->Write(0, 'Your contribution helps save lives and supports patients in need.');
    $pdf->Ln(8);
    
    $pdf->Write(0, 'Verification Code: ' . $verification_code);
    $pdf->Ln(12);
    
    $pdf->Write(0, 'Authorized by:');
    $pdf->Ln(5);
    $pdf->Write(0, 'Blood Donation Program Administrator');
    $pdf->Ln(12);
    
    $pdf->Write(0, 'Thank you for being a blood donor.');

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $donor['full_name']);
    $pdf_name = "Blood_Donation_Certificate_{$safeName}.pdf";
    $pdf->Output($pdf_name, 'D');
    exit();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2>Certificate</h2>
    <p style="color:#666;">Download your text-only recognition certificate after reaching <strong><?php echo $goal_donations; ?></strong> approved donations.</p>
</div>

<?php displayMessage(); ?>

<div style="background:#fff; padding:25px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.08); max-width:900px;">
    <h3 style="margin-top:0; color:#333;">Certificate Status</h3>

    <div style="background-color: <?php echo $eligible ? '#e8f5e9' : '#fff3e0'; ?>; padding:15px; border-radius:8px; border-left:4px solid <?php echo $eligible ? '#4caf50' : '#ff9800'; ?>; margin-bottom:15px;">
        <?php if ($eligible): ?>
            <p style="margin:0; color:#2e7d32; font-weight:bold;">Eligible</p>
            <p style="margin:6px 0 0 0; color:#1b5e20;">
                You have <strong><?php echo $approved_count; ?></strong> approved donations. You can download your certificate now.
            </p>
        <?php else: ?>
            <p style="margin:0; color:#e65100; font-weight:bold;">Not yet eligible</p>
            <p style="margin:6px 0 0 0; color:#bf360c;">
                You have <strong><?php echo $approved_count; ?></strong> approved donations.
                You need <strong><?php echo max(0, $goal_donations - $approved_count); ?></strong> more approved donation(s).
            </p>
        <?php endif; ?>
    </div>

    <?php if ($eligible): ?>
        <a href="certificate.php?action=download"
           style="display:inline-block; padding:12px 18px; background:#4caf50; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">
            Download Certificate (PDF)
        </a>
    <?php else: ?>
        <a href="progress.php"
           style="display:inline-block; padding:12px 18px; background:#667eea; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;">
            View Progress
        </a>
    <?php endif; ?>
    
    <?php if ($eligible && $certificate): ?>
        <div style="margin-top:20px; padding:15px; background:#f9f9f9; border-radius:6px; border:1px solid #eee;">
            <p style="margin:0 0 8px 0; font-weight:bold; color:#333;">Certificate Information:</p>
            <p style="margin:0; color:#555;">Issue Date: <?php echo date('F d, Y', strtotime($certificate['issue_date'])); ?></p>
            <p style="margin:5px 0 0 0; color:#555;">Verification Code: <?php echo $certificate['verification_code']; ?></p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>