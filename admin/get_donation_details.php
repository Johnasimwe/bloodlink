<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

header('Content-Type: application/json');

if (isset($_POST['donation_id'])) {
    $donation_id = intval($_POST['donation_id']);
    
    $stmt = $conn->prepare("
        SELECT d.*, 
               do.full_name as donor_name, 
               do.email as donor_email, 
               do.phone as donor_phone,
               do.gender as donor_gender,
               do.national_id as donor_national_id,
               do.blood_group,
               COUNT(d2.donation_id) as donor_total_donations,
               (SELECT COUNT(*) FROM donation_attachments WHERE donation_id = d.donation_id) as attachment_count
        FROM donations d
        JOIN donors do ON d.donor_id = do.donor_id
        LEFT JOIN donations d2 ON d.donor_id = d2.donor_id AND d2.status='Approved' AND d2.donation_id != d.donation_id
        WHERE d.donation_id = ?
        GROUP BY d.donation_id
    ");
    
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $donation = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'donation' => $donation
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Donation not found'
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>