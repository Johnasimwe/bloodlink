<?php
$page_title = 'Contacts - Admin - Blood Donation System';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$admin_id = (int)$_SESSION['admin_id'];

// Handle reply / status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $contact_id = (int)($_POST['contact_id'] ?? 0);
    $admin_reply = trim($_POST['admin_reply'] ?? '');
    $new_status = trim($_POST['new_status'] ?? 'Replied');

    if ($contact_id <= 0) {
        redirectWithMessage($_SERVER['REQUEST_URI'], 'Invalid contact selected.', 'error');
        exit();
    }
    if ($admin_reply === '' || strlen($admin_reply) < 2) {
        redirectWithMessage($_SERVER['REQUEST_URI'] . '&view=' . $contact_id, 'Reply message is required.', 'error');
        exit();
    }
    if (!in_array($new_status, ['Replied', 'Closed'], true)) {
        $new_status = 'Replied';
    }

    $stmt = $conn->prepare("
        UPDATE contacts 
        SET admin_reply = ?, status = ?, replied_by = ?, replied_at = NOW()
        WHERE contact_id = ?
    ");
    $stmt->bind_param("ssii", $admin_reply, $new_status, $admin_id, $contact_id);

    if ($stmt->execute()) {
        $stmt->close();
        redirectWithMessage('contacts.php?status=' . urlencode($new_status), 'Reply saved successfully.', 'success');
        exit();
    }
    $stmt->close();
    redirectWithMessage($_SERVER['REQUEST_URI'], 'Failed to save reply.', 'error');
    exit();
}

// Filters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'New';
if (!in_array($status, ['New', 'Replied', 'Closed'], true)) $status = 'New';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Totals
$new_count = (int)$conn->query("SELECT COUNT(*) AS c FROM contacts WHERE status='New'")->fetch_assoc()['c'];
$replied_count = (int)$conn->query("SELECT COUNT(*) AS c FROM contacts WHERE status='Replied'")->fetch_assoc()['c'];
$closed_count = (int)$conn->query("SELECT COUNT(*) AS c FROM contacts WHERE status='Closed'")->fetch_assoc()['c'];

// Pagination totals
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM contacts WHERE status = ?");
$stmt->bind_param("s", $status);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$total_pages = (int)ceil($total / $per_page);

// List contacts
$stmt = $conn->prepare("
    SELECT contact_id, full_name, email, subject, status, created_at, replied_at
    FROM contacts
    WHERE status = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("sii", $status, $per_page, $offset);
$stmt->execute();
$list = $stmt->get_result();
$stmt->close();

// View single contact
$view = null;
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    $stmt = $conn->prepare("
        SELECT c.*, a.full_name AS admin_name
        FROM contacts c
        LEFT JOIN admins a ON c.replied_by = a.admin_id
        WHERE c.contact_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $view = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fa-solid fa-inbox"></i> Contact Messages</h2>
    <p style="color:#666;">Review and reply to messages submitted via the contact form.</p>
</div>

<?php displayMessage(); ?>

<!-- Status Tabs -->
<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
    <a href="contacts.php?status=New"
       style="text-decoration:none; padding:10px 14px; border-radius:12px; font-weight:900;
              background: <?php echo $status==='New' ? '#d32f2f' : '#e5e7eb'; ?>;
              color: <?php echo $status==='New' ? '#fff' : '#111827'; ?>;">
        <i class="fa-solid fa-bell"></i> New (<?php echo $new_count; ?>)
    </a>

    <a href="contacts.php?status=Replied"
       style="text-decoration:none; padding:10px 14px; border-radius:12px; font-weight:900;
              background: <?php echo $status==='Replied' ? '#16a34a' : '#e5e7eb'; ?>;
              color: <?php echo $status==='Replied' ? '#fff' : '#111827'; ?>;">
        <i class="fa-solid fa-reply"></i> Replied (<?php echo $replied_count; ?>)
    </a>

    <a href="contacts.php?status=Closed"
       style="text-decoration:none; padding:10px 14px; border-radius:12px; font-weight:900;
              background: <?php echo $status==='Closed' ? '#334155' : '#e5e7eb'; ?>;
              color: <?php echo $status==='Closed' ? '#fff' : '#111827'; ?>;">
        <i class="fa-solid fa-circle-xmark"></i> Closed (<?php echo $closed_count; ?>)
    </a>
</div>

<!-- List -->
<div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:18px; box-shadow: 0 10px 30px rgba(17,24,39,0.06);">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#f9fafb; border-bottom: 2px solid #e5e7eb;">
                    <th style="padding:12px; text-align:left;">ID</th>
                    <th style="padding:12px; text-align:left;">Sender</th>
                    <th style="padding:12px; text-align:left;">Subject</th>
                    <th style="padding:12px; text-align:left;">Created</th>
                    <th style="padding:12px; text-align:left;">Status</th>
                    <th style="padding:12px; text-align:left;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $list->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:12px; font-weight:900;">#<?php echo (int)$row['contact_id']; ?></td>
                        <td style="padding:12px;">
                            <div style="font-weight:900; color:#111827;"><?php echo htmlspecialchars($row['full_name']); ?></div>
                            <div style="color:#6b7280; font-size:0.9rem;"><?php echo htmlspecialchars($row['email']); ?></div>
                        </td>
                        <td style="padding:12px; color:#111827;"><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td style="padding:12px; color:#6b7280;"><?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?></td>
                        <td style="padding:12px;">
                            <?php
                            $badgeBg = $row['status']==='New' ? '#fff3e0' : ($row['status']==='Replied' ? '#e8f5e9' : '#e2e8f0');
                            $badgeFg = $row['status']==='New' ? '#e65100' : ($row['status']==='Replied' ? '#166534' : '#0f172a');
                            ?>
                            <span style="display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; font-weight:900; background:<?php echo $badgeBg; ?>; color:<?php echo $badgeFg; ?>;">
                                <?php if ($row['status']==='New'): ?><i class="fa-solid fa-bell"></i><?php endif; ?>
                                <?php if ($row['status']==='Replied'): ?><i class="fa-solid fa-reply"></i><?php endif; ?>
                                <?php if ($row['status']==='Closed'): ?><i class="fa-solid fa-circle-xmark"></i><?php endif; ?>
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                        <td style="padding:12px;">
                            <a href="contacts.php?status=<?php echo urlencode($status); ?>&view=<?php echo (int)$row['contact_id']; ?>"
                               style="display:inline-flex; align-items:center; gap:8px; text-decoration:none; background:#2563eb; color:#fff; padding:8px 10px; border-radius:12px; font-weight:900;">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <div style="display:flex; justify-content:center; gap:6px; flex-wrap:wrap; margin-top:14px;">
            <?php for ($i=1; $i<=$total_pages; $i++): ?>
                <a href="contacts.php?status=<?php echo urlencode($status); ?>&page=<?php echo $i; ?>"
                   style="text-decoration:none; padding:8px 12px; border-radius:12px;
                          background: <?php echo $i===$page ? '#d32f2f' : '#e5e7eb'; ?>;
                          color: <?php echo $i===$page ? '#fff' : '#111827'; ?>;
                          font-weight:900;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- View / Reply Panel -->
<?php if ($view): ?>
<div style="margin-top:16px; background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:18px; box-shadow: 0 10px 30px rgba(17,24,39,0.06);">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
            <h3 style="margin:0; color:#111827;">
                <i class="fa-solid fa-message" style="color:#d32f2f;"></i>
                Message #<?php echo (int)$view['contact_id']; ?>
            </h3>
            <div style="color:#6b7280; margin-top:6px;">
                From <strong><?php echo htmlspecialchars($view['full_name']); ?></strong> (<?php echo htmlspecialchars($view['email']); ?>)
                • <?php echo date('M d, Y g:i A', strtotime($view['created_at'])); ?>
            </div>
        </div>

        <a href="contacts.php?status=<?php echo urlencode($status); ?>"
           style="text-decoration:none; background:#e5e7eb; color:#111827; padding:10px 12px; border-radius:12px; font-weight:900;">
           <i class="fa-solid fa-xmark"></i> Close
        </a>
    </div>

    <div style="margin-top:14px; display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
            <div style="font-weight:900; color:#111827; margin-bottom:8px;">
                <i class="fa-solid fa-tag" style="color:#d32f2f;"></i> Subject
            </div>
            <div style="color:#111827;"><?php echo htmlspecialchars($view['subject']); ?></div>
        </div>

        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
            <div style="font-weight:900; color:#111827; margin-bottom:8px;">
                <i class="fa-solid fa-circle-info" style="color:#d32f2f;"></i> Status
            </div>
            <div style="font-weight:900;"><?php echo htmlspecialchars($view['status']); ?></div>
            <?php if (!empty($view['replied_at'])): ?>
                <div style="color:#6b7280; margin-top:6px;">
                    Replied by <?php echo htmlspecialchars($view['admin_name'] ?? 'Admin'); ?>
                    on <?php echo date('M d, Y g:i A', strtotime($view['replied_at'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-top:12px; background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
        <div style="font-weight:900; color:#111827; margin-bottom:8px;">
            <i class="fa-solid fa-quote-left" style="color:#d32f2f;"></i> Message
        </div>
        <div style="color:#374151; line-height:1.8; white-space:pre-wrap;"><?php echo htmlspecialchars($view['message']); ?></div>
    </div>

    <div style="margin-top:12px; display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div style="background:#f1f5f9; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
            <div style="font-weight:900; color:#111827; margin-bottom:8px;">
                <i class="fa-solid fa-reply" style="color:#16a34a;"></i> Admin Reply
            </div>
            <?php if (!empty($view['admin_reply'])): ?>
                <div style="white-space:pre-wrap; color:#334155; line-height:1.8;"><?php echo htmlspecialchars($view['admin_reply']); ?></div>
            <?php else: ?>
                <div style="color:#6b7280;">No reply yet.</div>
            <?php endif; ?>
        </div>

        <div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
            <div style="font-weight:900; color:#111827; margin-bottom:10px;">
                <i class="fa-solid fa-pen-to-square" style="color:#d32f2f;"></i> Write Reply
            </div>

            <form method="POST" action="contacts.php?status=<?php echo urlencode($status); ?>&view=<?php echo (int)$view['contact_id']; ?>">
                <input type="hidden" name="action" value="reply" />
                <input type="hidden" name="contact_id" value="<?php echo (int)$view['contact_id']; ?>" />

                <textarea name="admin_reply" rows="6" required
                          style="width:100%; padding:12px 14px; border:1px solid #e5e7eb; border-radius:12px; outline:none; resize:vertical;"
                          placeholder="Type your reply here..."><?php echo htmlspecialchars($view['admin_reply'] ?? ''); ?></textarea>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
                    <select name="new_status"
                            style="flex:1; min-width:180px; padding:10px 12px; border:1px solid #e5e7eb; border-radius:12px; outline:none; font-weight:800;">
                        <option value="Replied">Mark as Replied</option>
                        <option value="Closed">Mark as Closed</option>
                    </select>

                    <button type="submit"
                            style="flex:1; min-width:180px; background:#16a34a; border:none; color:#fff; padding:10px 12px; border-radius:12px; font-weight:900; cursor:pointer;">
                        <i class="fa-solid fa-paper-plane"></i> Save Reply
                    </button>
                </div>

                <div style="color:#6b7280; font-size:0.9rem; margin-top:10px; line-height:1.6;">
                    Note: This page stores your reply in the database. If you want to send emails automatically,
                    tell me and I’ll add SMTP (PHPMailer) integration.
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media (max-width: 900px){
  div[style*="grid-template-columns: 1fr 1fr"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>