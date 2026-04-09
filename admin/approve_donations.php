<?php
$page_title = 'Approve Donations - Blood Donation System';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

// Get admin info
$admin_id = $_SESSION['admin_id'];

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $donation_id = intval($_POST['donation_id']);
    $action = sanitizeInput($_POST['action']);
    $rejection_reason = isset($_POST['rejection_reason']) ? sanitizeInput($_POST['rejection_reason']) : '';
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE donations SET status='Approved', approved_by=?, approved_at=NOW() WHERE donation_id=?");
        $stmt->bind_param("ii", $admin_id, $donation_id);
        $message = 'Donation approved successfully!';
    } elseif ($action === 'reject') {
        if (empty($rejection_reason)) {
            redirectWithMessage($_SERVER['REQUEST_URI'], 'Please provide a rejection reason.', 'error');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE donations SET status='Rejected', approved_by=?, approved_at=NOW() WHERE donation_id=?");
        $stmt->bind_param("ii", $admin_id, $donation_id);
        $message = 'Donation rejected successfully!';
    }
    
    if ($stmt->execute()) {
        redirectWithMessage($_SERVER['REQUEST_URI'], $message, 'success');
    } else {
        redirectWithMessage($_SERVER['REQUEST_URI'], 'Error processing donation.', 'error');
    }
    $stmt->close();
}

// Filter by status
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'Pending';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Get total donations with filter
$total_result = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='$status_filter'");
$total_donations = $total_result->fetch_assoc()['count'];
$total_pages = ceil($total_donations / $items_per_page);

// Get donations with attachment info
$donations_query = "
    SELECT d.*, do.full_name, do.email, do.blood_group, do.phone, do.donor_id,
           COUNT(d2.donation_id) as donor_total_donations,
           (SELECT COUNT(*) FROM donation_attachments WHERE donation_id = d.donation_id) as attachment_count,
           (SELECT file_path FROM donation_attachments WHERE donation_id = d.donation_id LIMIT 1) as file_path
    FROM donations d
    JOIN donors do ON d.donor_id = do.donor_id
    LEFT JOIN donations d2 ON d.donor_id = d2.donor_id AND d2.status='Approved' AND d2.donation_id != d.donation_id
    WHERE d.status='$status_filter'
    GROUP BY d.donation_id
    ORDER BY d.requested_at DESC
    LIMIT $offset, $items_per_page
";
$donations = $conn->query($donations_query);

// Get stats
$pending_count = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Approved'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Rejected'")->fetch_assoc()['count'];

// Get detailed info for modal
$view_donation = null;
$attachments = null;
if (isset($_GET['view'])) {
    $view_donation_id = intval($_GET['view']);
    $view_stmt = $conn->prepare("
        SELECT d.*, do.full_name, do.email, do.blood_group, do.phone, do.gender, do.date_of_birth,
               do.address, do.profile_image
        FROM donations d
        JOIN donors do ON d.donor_id = do.donor_id
        WHERE d.donation_id = ?
    ");
    $view_stmt->bind_param("i", $view_donation_id);
    $view_stmt->execute();
    $view_donation = $view_stmt->get_result()->fetch_assoc();
    $view_stmt->close();
    
    // Get attachments
    $attach_stmt = $conn->prepare("SELECT * FROM donation_attachments WHERE donation_id = ? ORDER BY uploaded_at DESC");
    $attach_stmt->bind_param("i", $view_donation_id);
    $attach_stmt->execute();
    $attachments = $attach_stmt->get_result();
    $attach_stmt->close();
    
    // Get donor history
    $donor_history = $conn->query("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) as approved,
               SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
               SUM(amount_donated) as total_units
        FROM donations 
        WHERE donor_id = {$view_donation['donor_id']}
    ")->fetch_assoc();
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .approve-page {
        padding: 20px;
        background-color: #f8fafc;
        min-height: 100vh;
    }

    /* Header Styles */
    .page-header {
        margin-bottom: 30px;
    }

    .page-header h2 {
        font-size: 2em;
        color: #1e293b;
        margin-bottom: 10px;
    }

    .page-header h2 i {
        color: #8b5cf6;
        margin-right: 10px;
    }

    .page-header p {
        color: #64748b;
        font-size: 1.1em;
    }

    .page-header p i {
        margin-right: 8px;
        color: #3b82f6;
    }

    /* Message Styles */
    .success-message {
        background: #d1fae5;
        color: #065f46;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-left: 4px solid #059669;
        animation: slideIn 0.3s ease;
    }

    .error-message {
        background: #fee2e2;
        color: #b91c1c;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-left: 4px solid #dc2626;
        animation: slideIn 0.3s ease;
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

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
    }

    .stat-card.pending .stat-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .stat-card.approved .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-card.rejected .stat-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .stat-info h3 {
        color: #64748b;
        font-size: 0.9em;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .stat-info .value {
        font-size: 1.8em;
        font-weight: 700;
        color: #1e293b;
    }

    /* Status Tabs */
    .status-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .tab-link {
        padding: 12px 25px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
        background: white;
        color: #64748b;
        border: 2px solid #e2e8f0;
    }

    .tab-link i {
        font-size: 1.1em;
    }

    .tab-link:hover {
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .tab-link.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .tab-link.pending.active { background: #f59e0b; border-color: #f59e0b; }
    .tab-link.approved.active { background: #10b981; border-color: #10b981; }
    .tab-link.rejected.active { background: #ef4444; border-color: #ef4444; }

    .badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 0.8em;
        margin-left: 5px;
    }

    /* Table Section */
    .table-section {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .table-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #1e293b;
        font-size: 1.2em;
        font-weight: 600;
    }

    .table-title i {
        color: #8b5cf6;
    }

    .table-responsive {
        overflow-x: auto;
        border-radius: 12px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 15px;
        background: #f8fafc;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    th i {
        margin-right: 8px;
        color: #3b82f6;
    }

    td {
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
        color: #1e293b;
    }

    tr:hover td {
        background: #f8fafc;
    }

    .donor-info {
        display: flex;
        flex-direction: column;
    }

    .donor-name {
        font-weight: 600;
        color: #1e293b;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .donor-email {
        font-size: 0.85em;
        color: #64748b;
    }

    .blood-badge {
        display: inline-block;
        padding: 5px 12px;
        background: #f1f5f9;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9em;
        color: #475569;
    }

    .amount-badge {
        font-weight: 600;
        color: #059669;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .proof-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        background: #dbeafe;
        color: #1e40af;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.85em;
        transition: all 0.3s;
    }

    .proof-badge:hover {
        background: #bfdbfe;
    }

    .proof-badge.no-proof {
        background: #f1f5f9;
        color: #64748b;
        cursor: default;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .status-badge.pending {
        background: #fed7aa;
        color: #92400e;
    }

    .status-badge.approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .btn-review {
        background: #3b82f6;
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s;
    }

    .btn-review:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }

    .btn-review i {
        font-size: 0.9em;
    }

    .processed-text {
        color: #94a3b8;
        font-size: 0.9em;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .page-link {
        padding: 10px 15px;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        color: #64748b;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
        min-width: 40px;
        text-align: center;
    }

    .page-link:hover {
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .page-link.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    /* Modal Styles */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: white;
        border-radius: 20px;
        max-width: 1000px;
        width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        animation: slideUp 0.3s ease;
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

    .modal-header {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        color: white;
        padding: 25px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .modal-header h2 {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.5em;
        margin: 0;
    }

    .modal-header h2 i {
        font-size: 1.3em;
    }

    .close-btn {
        color: white;
        text-decoration: none;
        font-size: 1.5em;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.3s;
    }

    .close-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .modal-body {
        padding: 30px;
    }

    /* Info Grid */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    .info-section {
        background: #f8fafc;
        border-radius: 15px;
        padding: 20px;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #1e293b;
        font-size: 1.1em;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }

    .section-title i {
        color: #8b5cf6;
    }

    .info-item {
        display: flex;
        margin-bottom: 15px;
        padding: 10px;
        background: white;
        border-radius: 10px;
    }

    .info-label {
        width: 120px;
        color: #64748b;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .info-label i {
        color: #3b82f6;
        width: 20px;
    }

    .info-value {
        flex: 1;
        color: #1e293b;
        font-weight: 500;
    }

    .blood-value {
        font-size: 1.3em;
        font-weight: 700;
        color: #dc2626;
    }

    /* Attachments Grid */
    .attachments-grid {
        margin-bottom: 30px;
    }

    .attachments-title {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #1e293b;
        font-size: 1.1em;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .attachments-title i {
        color: #f59e0b;
    }

    .attachments-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .attachment-card {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s;
    }

    .attachment-card:hover {
        border-color: #3b82f6;
        transform: translateY(-5px);
    }

    .attachment-icon {
        width: 50px;
        height: 50px;
        background: #f1f5f9;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
        margin-bottom: 15px;
    }

    .attachment-icon.pdf { color: #dc2626; }
    .attachment-icon.image { color: #3b82f6; }
    .attachment-icon.doc { color: #2563eb; }

    .attachment-name {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 5px;
        word-break: break-all;
    }

    .attachment-date {
        color: #64748b;
        font-size: 0.85em;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .attachment-actions {
        display: flex;
        gap: 10px;
    }

    .btn-attachment {
        flex: 1;
        padding: 8px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        transition: all 0.3s;
    }

    .btn-attachment.view {
        background: #dbeafe;
        color: #1e40af;
    }

    .btn-attachment.view:hover {
        background: #bfdbfe;
    }

    .btn-attachment.download {
        background: #dcfce7;
        color: #166534;
    }

    .btn-attachment.download:hover {
        background: #bbf7d0;
    }

    .no-attachments {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        padding: 15px 20px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #92400e;
    }

    /* Donor History */
    .history-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 30px;
    }

    .history-card {
        padding: 20px;
        border-radius: 12px;
        text-align: center;
    }

    .history-card.approved {
        background: #d1fae5;
    }

    .history-card.pending {
        background: #fed7aa;
    }

    .history-card.total {
        background: #dbeafe;
    }

    .history-card.units {
        background: #fee2e2;
    }

    .history-value {
        font-size: 2em;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .history-label {
        font-size: 0.9em;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    /* Action Buttons */
    .action-section {
        background: #f8fafc;
        border-radius: 15px;
        padding: 25px;
        margin-top: 30px;
    }

    .action-title {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #1e293b;
        font-size: 1.1em;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .action-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    @media (max-width: 640px) {
        .action-buttons {
            grid-template-columns: 1fr;
        }
    }

    .approve-card {
        background: #d1fae5;
        border-radius: 12px;
        padding: 20px;
    }

    .reject-card {
        background: #fee2e2;
        border-radius: 12px;
        padding: 20px;
    }

    .action-card-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .action-card-title.approve { color: #065f46; }
    .action-card-title.reject { color: #b91c1c; }

    .action-description {
        color: #4b5563;
        font-size: 0.95em;
        margin-bottom: 15px;
    }

    .btn-action {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .btn-action.approve {
        background: #10b981;
        color: white;
    }

    .btn-action.approve:hover {
        background: #059669;
        transform: translateY(-2px);
    }

    .btn-action.reject {
        background: #ef4444;
        color: white;
    }

    .btn-action.reject:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }

    .rejection-input {
        width: 100%;
        padding: 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 15px;
        font-family: inherit;
        resize: vertical;
    }

    .rejection-input:focus {
        outline: none;
        border-color: #ef4444;
    }

    .processed-message {
        background: #f1f5f9;
        border-radius: 12px;
        padding: 25px;
        text-align: center;
    }

    .processed-message i {
        font-size: 3em;
        margin-bottom: 15px;
    }

    .processed-message.approved i { color: #10b981; }
    .processed-message.rejected i { color: #ef4444; }

    .processed-message p {
        color: #1e293b;
        font-size: 1.1em;
    }

    .processed-date {
        color: #64748b;
        font-size: 0.9em;
        margin-top: 10px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 4em;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state p {
        font-size: 1.1em;
        margin-bottom: 20px;
    }
</style>

<div class="approve-page">
    <div class="page-header">
        <h2>
            <i class="fas fa-check-circle"></i>
            Approve Donations
        </h2>
        <p>
            <i class="fas fa-clipboard-check"></i>
            Review and approve pending blood donation records with proof documents
        </p>
    </div>

    <?php displayMessage(); ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pending</h3>
                <div class="value"><?php echo number_format($pending_count); ?></div>
            </div>
        </div>

        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Approved</h3>
                <div class="value"><?php echo number_format($approved_count); ?></div>
            </div>
        </div>

        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Rejected</h3>
                <div class="value"><?php echo number_format($rejected_count); ?></div>
            </div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="status-tabs">
        <a href="?status=Pending" class="tab-link pending <?php echo $status_filter === 'Pending' ? 'active' : ''; ?>">
            <i class="fas fa-hourglass-half"></i>
            Pending
            <span class="badge"><?php echo $pending_count; ?></span>
        </a>
        <a href="?status=Approved" class="tab-link approved <?php echo $status_filter === 'Approved' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i>
            Approved
            <span class="badge"><?php echo $approved_count; ?></span>
        </a>
        <a href="?status=Rejected" class="tab-link rejected <?php echo $status_filter === 'Rejected' ? 'active' : ''; ?>">
            <i class="fas fa-times-circle"></i>
            Rejected
            <span class="badge"><?php echo $rejected_count; ?></span>
        </a>
    </div>

    <!-- Donations Table -->
    <div class="table-section">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list"></i>
                <?php echo ucfirst(strtolower($status_filter)); ?> Donations
            </div>
            <div class="table-info">
                <span style="color: #64748b;">
                    <i class="fas fa-database"></i>
                    <?php echo $total_donations; ?> records found
                </span>
            </div>
        </div>

        <?php if ($donations->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-user"></i> Donor</th>
                            <th><i class="fas fa-tint"></i> Blood Group</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-flask"></i> Amount</th>
                            <th><i class="fas fa-file"></i> Proof</th>
                            <th><i class="fas fa-clock"></i> Requested</th>
                            <th><i class="fas fa-tag"></i> Status</th>
                            <th><i class="fas fa-cog"></i> Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($donation = $donations->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $donation['donation_id']; ?></strong></td>
                                <td>
                                    <div class="donor-info">
                                        <span class="donor-name">
                                            <i class="fas fa-user-circle"></i>
                                            <?php echo htmlspecialchars($donation['full_name']); ?>
                                        </span>
                                        <span class="donor-email">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($donation['email']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="blood-badge"><?php echo htmlspecialchars($donation['blood_group']); ?></span>
                                </td>
                                <td>
                                    <i class="far fa-calendar-alt" style="color: #64748b; margin-right: 5px;"></i>
                                    <?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>
                                </td>
                                <td>
                                    <span class="amount-badge">
                                        <i class="fas fa-tint" style="color: #EF4444;"></i>
                                        <?php echo $donation['amount_donated']; ?> unit(s)
                                    </span>
                                </td>
                                <td>
                                    <?php if ($donation['attachment_count'] > 0): ?>
                                        <a href="?view=<?php echo $donation['donation_id']; ?>" class="proof-badge">
                                            <i class="fas fa-file-pdf"></i>
                                            <?php echo $donation['attachment_count']; ?> file(s)
                                        </a>
                                    <?php else: ?>
                                        <span class="proof-badge no-proof">
                                            <i class="fas fa-ban"></i>
                                            No proof
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 0.9em;">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('M d, g:i A', strtotime($donation['requested_at'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($donation['status']); ?>">
                                        <i class="fas 
                                            <?php 
                                            if ($donation['status'] === 'Approved') echo 'fa-check-circle';
                                            elseif ($donation['status'] === 'Pending') echo 'fa-hourglass-half';
                                            else echo 'fa-times-circle';
                                            ?>">
                                        </i>
                                        <?php echo $donation['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($status_filter === 'Pending'): ?>
                                        <a href="?view=<?php echo $donation['donation_id']; ?>" class="btn-review">
                                            <i class="fas fa-search"></i>
                                            Review
                                        </a>
                                    <?php else: ?>
                                        <span class="processed-text">
                                            <i class="fas fa-check"></i>
                                            Processed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page-1; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $i; ?>" 
                           class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $page+1; ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No <?php echo strtolower($status_filter); ?> donations found</p>
                <?php if ($status_filter !== 'Pending'): ?>
                    <a href="?status=Pending" class="btn-review" style="display: inline-flex;">
                        <i class="fas fa-clock"></i>
                        View Pending Donations
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detail View Modal -->
<?php if ($view_donation): ?>
<div class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>
                <i class="fas fa-clipboard-list"></i>
                Review Donation #<?php echo $view_donation['donation_id']; ?>
            </h2>
            <a href="?status=<?php echo urlencode($status_filter); ?>" class="close-btn">
                <i class="fas fa-times"></i>
            </a>
        </div>
        
        <div class="modal-body">
            <!-- Donor and Donation Info Grid -->
            <div class="info-grid">
                <!-- Donor Information -->
                <div class="info-section">
                    <div class="section-title">
                        <i class="fas fa-user-circle"></i>
                        Donor Information
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-user"></i>
                            Name:
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($view_donation['full_name']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-envelope"></i>
                            Email:
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($view_donation['email']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-phone"></i>
                            Phone:
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($view_donation['phone']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-venus-mars"></i>
                            Gender:
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($view_donation['gender']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-calendar-alt"></i>
                            DOB:
                        </span>
                        <span class="info-value">
                            <?php echo date('M d, Y', strtotime($view_donation['date_of_birth'])); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-map-marker-alt"></i>
                            Address:
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($view_donation['address']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-tint"></i>
                            Blood Group:
                        </span>
                        <span class="info-value blood-value">
                            <?php echo htmlspecialchars($view_donation['blood_group']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Donation Information -->
                <div class="info-section">
                    <div class="section-title">
                        <i class="fas fa-tint"></i>
                        Donation Information
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-calendar-check"></i>
                            Donation Date:
                        </span>
                        <span class="info-value">
                            <?php echo date('F d, Y', strtotime($view_donation['donation_date'])); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-map-pin"></i>
                            Location:
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($view_donation['donation_location']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-flask"></i>
                            Amount:
                        </span>
                        <span class="info-value blood-value">
                            <?php echo $view_donation['amount_donated']; ?> unit(s)
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-clock"></i>
                            Requested On:
                        </span>
                        <span class="info-value">
                            <?php echo date('F d, Y \a\t g:i A', strtotime($view_donation['requested_at'])); ?>
                        </span>
                    </div>
                    
                    <?php if ($view_donation['status'] !== 'Pending'): ?>
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-user-check"></i>
                                Processed By:
                            </span>
                            <span class="info-value">
                                Admin #<?php echo $view_donation['approved_by']; ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">
                                <i class="fas fa-calendar-check"></i>
                                Processed On:
                            </span>
                            <span class="info-value">
                                <?php echo date('F d, Y \a\t g:i A', strtotime($view_donation['approved_at'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Attached Documents -->
            <div class="attachments-grid">
                <div class="attachments-title">
                    <i class="fas fa-paperclip"></i>
                    Attached Documents (<?php echo $attachments ? $attachments->num_rows : 0; ?>)
                </div>
                
                <?php if ($attachments && $attachments->num_rows > 0): ?>
                    <div class="attachments-container">
                        <?php while ($attachment = $attachments->fetch_assoc()): 
                            $ext = strtolower(pathinfo($attachment['file_path'], PATHINFO_EXTENSION));
                            $icon_class = 'fa-file';
                            $icon_color = '';
                            
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                $icon_class = 'fa-file-image';
                                $icon_color = 'image';
                            } elseif ($ext === 'pdf') {
                                $icon_class = 'fa-file-pdf';
                                $icon_color = 'pdf';
                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                $icon_class = 'fa-file-word';
                                $icon_color = 'doc';
                            }
                        ?>
                            <div class="attachment-card">
                                <div class="attachment-icon <?php echo $icon_color; ?>">
                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="attachment-name">
                                    <?php echo basename($attachment['file_path']); ?>
                                </div>
                                <div class="attachment-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($attachment['uploaded_at'])); ?>
                                </div>
                                <div class="attachment-actions">
                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="btn-attachment view">
                                        <i class="fas fa-eye"></i>
                                        View
                                    </a>
                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" download class="btn-attachment download">
                                        <i class="fas fa-download"></i>
                                        Download
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-attachments">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>No attached documents</strong>
                            <p style="margin-top: 5px;">Donor did not upload any proof document. Review other details carefully before making a decision.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Donor History -->
            <div class="history-grid">
                <div class="history-card total">
                    <div class="history-value"><?php echo $donor_history['total'] ?? 0; ?></div>
                    <div class="history-label">
                        <i class="fas fa-tint"></i>
                        Total Donations
                    </div>
                </div>
                
                <div class="history-card approved">
                    <div class="history-value"><?php echo $donor_history['approved'] ?? 0; ?></div>
                    <div class="history-label">
                        <i class="fas fa-check-circle"></i>
                        Approved
                    </div>
                </div>
                
                <div class="history-card pending">
                    <div class="history-value"><?php echo $donor_history['pending'] ?? 0; ?></div>
                    <div class="history-label">
                        <i class="fas fa-hourglass-half"></i>
                        Pending
                    </div>
                </div>
                
                <div class="history-card units">
                    <div class="history-value"><?php echo number_format($donor_history['total_units'] ?? 0, 2); ?></div>
                    <div class="history-label">
                        <i class="fas fa-flask"></i>
                        Total Units
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <?php if ($view_donation['status'] === 'Pending'): ?>
                <div class="action-section">
                    <div class="action-title">
                        <i class="fas fa-gavel"></i>
                        Take Action
                    </div>
                    
                    <div class="action-buttons">
                        <!-- Approval Form -->
                        <div class="approve-card">
                            <div class="action-card-title approve">
                                <i class="fas fa-check-circle"></i>
                                Approve Donation
                            </div>
                            <p class="action-description">
                                Confirm that this donation is valid and meets all requirements.
                            </p>
                            <form method="POST" action="">
                                <input type="hidden" name="donation_id" value="<?php echo $view_donation['donation_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-action approve">
                                    <i class="fas fa-check"></i>
                                    Approve This Donation
                                </button>
                            </form>
                        </div>
                        
                        <!-- Rejection Form -->
                        <div class="reject-card">
                            <div class="action-card-title reject">
                                <i class="fas fa-times-circle"></i>
                                Reject Donation
                            </div>
                            <p class="action-description">
                                Reject this donation if it doesn't meet requirements.
                            </p>
                            <form method="POST" action="">
                                <input type="hidden" name="donation_id" value="<?php echo $view_donation['donation_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                
                                <textarea name="rejection_reason" class="rejection-input" 
                                          placeholder="Enter rejection reason (required)..." 
                                          required></textarea>
                                
                                <button type="submit" class="btn-action reject">
                                    <i class="fas fa-times"></i>
                                    Reject This Donation
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="processed-message <?php echo strtolower($view_donation['status']); ?>">
                    <i class="fas <?php echo $view_donation['status'] === 'Approved' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <p>
                        <strong>This donation has been <?php echo strtoupper($view_donation['status']); ?></strong>
                    </p>
                    <?php if (!empty($view_donation['approved_at'])): ?>
                        <div class="processed-date">
                            <i class="far fa-calendar-alt"></i>
                            Processed on <?php echo date('F d, Y \a\t g:i A', strtotime($view_donation['approved_at'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>