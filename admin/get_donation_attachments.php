<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

if (!isset($_GET['donation_id'])) {
    http_response_code(400);
    echo '<p style="color: #f44336;">Invalid donation ID.</p>';
    exit;
}

$donation_id = intval($_GET['donation_id']);

// Get attachments for this donation
$stmt = $conn->prepare("SELECT * FROM donation_attachments WHERE donation_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $donation_id);
$stmt->execute();
$result = $stmt->get_result();
$attachments = [];
while ($row = $result->fetch_assoc()) {
    $attachments[] = $row;
}
$stmt->close();

if (count($attachments) === 0) {
    echo '<p style="color: #666; text-align: center; padding: 40px;">No proof documents found for this donation.</p>';
    exit;
}

?>

<div class="attachment-gallery">
    <?php foreach ($attachments as $attachment): ?>
        <?php 
        $file_path = $attachment['file_path'];
        $file_type = strtoupper($attachment['file_type']);
        $uploaded_date = date('M d, Y g:i A', strtotime($attachment['uploaded_at']));
        $file_name = basename($file_path);
        
        // Determine icon based on file type
        if ($file_type === 'PDF') {
            $icon = '📄';
        } elseif (in_array($file_type, ['JPG', 'JPEG', 'PNG'])) {
            $icon = '🖼️';
        } else {
            $icon = '📎';
        }
        ?>
        
        <div class="attachment-item">
            <div class="attachment-icon"><?php echo $icon; ?></div>
            <div class="attachment-name"><?php echo htmlspecialchars($file_name); ?></div>
            <div class="attachment-info">
                <p style="margin: 5px 0;">📦 <?php echo $file_type; ?></p>
                <p style="margin: 5px 0;">📅 <?php echo $uploaded_date; ?></p>
            </div>
            <div class="attachment-buttons">
                <button class="view-btn" onclick="viewAttachment('<?php echo htmlspecialchars($file_path); ?>', '<?php echo htmlspecialchars($file_name); ?>')">
                    👁️ View
                </button>
                <button class="download-btn" onclick="downloadAttachment('<?php echo htmlspecialchars($file_path); ?>', '<?php echo htmlspecialchars($file_name); ?>')">
                    ⬇️ Download
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
if (count($attachments) > 0) {
    echo '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center;"><p style="color: #666; font-size: 0.9em;">Total: ' . count($attachments) . ' document(s)</p></div>';
}
?>