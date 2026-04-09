<?php
$page_title = 'Contact - Blood Donation System';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($subject === '') $errors[] = 'Subject is required.';
    if ($message === '' || strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO contacts (full_name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'New', NOW())");
        $stmt->bind_param("ssss", $full_name, $email, $subject, $message);

        if ($stmt->execute()) {
            $success = 'Your message has been sent successfully. Our team will respond soon.';
            // Clear fields after success
            $full_name = $email = $subject = $message = '';
        } else {
            $errors[] = 'Failed to send message. Please try again.';
        }
        $stmt->close();
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2><i class="fa-solid fa-envelope-open-text"></i> Contact Us</h2>
    <p style="color:#666;">Send us a message and we’ll get back to you.</p>
</div>

<?php if (!empty($success)): ?>
    <div style="background:#e8f5e9; border:1px solid #bbf7d0; border-left:6px solid #22c55e; color:#14532d; padding:14px; border-radius:14px; margin-bottom:16px;">
        <div style="font-weight:800;"><i class="fa-solid fa-circle-check"></i> Sent</div>
        <div style="margin-top:6px;"><?php echo htmlspecialchars($success); ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div style="background:#ffebee; border:1px solid #fecaca; border-left:6px solid #ef4444; color:#7f1d1d; padding:14px; border-radius:14px; margin-bottom:16px;">
        <div style="font-weight:800;"><i class="fa-solid fa-triangle-exclamation"></i> Please fix the following:</div>
        <ul style="margin:10px 0 0 18px; line-height:1.7;">
            <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; box-shadow: 0 10px 30px rgba(17,24,39,0.06);">
        <h3 style="margin:0 0 14px 0; color:#111827;">
            <i class="fa-solid fa-paper-plane" style="color:#d32f2f;"></i> Send a Message
        </h3>

        <form method="POST" action="contact.php">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="font-weight:800; color:#111827; display:block; margin-bottom:6px;">
                        Full Name
                    </label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>"
                           style="width:100%; padding:12px 14px; border:1px solid #e5e7eb; border-radius:12px; outline:none;">
                </div>

                <div>
                    <label style="font-weight:800; color:#111827; display:block; margin-bottom:6px;">
                        Email
                    </label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>"
                           style="width:100%; padding:12px 14px; border:1px solid #e5e7eb; border-radius:12px; outline:none;">
                </div>
            </div>

            <div style="margin-top:12px;">
                <label style="font-weight:800; color:#111827; display:block; margin-bottom:6px;">
                    Subject
                </label>
                <input type="text" name="subject" value="<?php echo htmlspecialchars($subject ?? ''); ?>"
                       style="width:100%; padding:12px 14px; border:1px solid #e5e7eb; border-radius:12px; outline:none;">
            </div>

            <div style="margin-top:12px;">
                <label style="font-weight:800; color:#111827; display:block; margin-bottom:6px;">
                    Message
                </label>
                <textarea name="message" rows="6"
                          style="width:100%; padding:12px 14px; border:1px solid #e5e7eb; border-radius:12px; outline:none; resize:vertical;"><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                <small style="color:#6b7280;">Please include enough details so we can help you quickly.</small>
            </div>

            <button type="submit"
                    style="margin-top:14px; width:100%; background:#d32f2f; border:none; color:#fff; padding:12px 14px; border-radius:12px; font-weight:900; cursor:pointer;">
                <i class="fa-solid fa-paper-plane"></i> Submit
            </button>
        </form>
    </div>

    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; box-shadow: 0 10px 30px rgba(17,24,39,0.06);">
        <h3 style="margin:0 0 14px 0; color:#111827;">
            <i class="fa-solid fa-headset" style="color:#d32f2f;"></i> Support
        </h3>

        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px; margin-bottom:12px;">
            <div style="font-weight:800; color:#111827; margin-bottom:6px;">
                <i class="fa-solid fa-clock" style="color:#d32f2f;"></i> Response Time
            </div>
            <div style="color:#6b7280; line-height:1.7;">Typically within 24–48 hours.</div>
        </div>

        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
            <div style="font-weight:800; color:#111827; margin-bottom:6px;">
                <i class="fa-solid fa-shield-heart" style="color:#d32f2f;"></i> Privacy
            </div>
            <div style="color:#6b7280; line-height:1.7;">
                Your message is stored securely and only accessible to authorized staff.
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 900px){
  div[style*="grid-template-columns: 2fr 1fr"]{ grid-template-columns: 1fr !important; }
  div[style*="grid-template-columns: 1fr 1fr"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>