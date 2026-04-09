<?php
$page_title = 'Manage Donations - Blood Donation System';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$message = '';
$error = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $donation_id = intval($_POST['donation_id']);
        $status = sanitizeInput($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE donations SET status = ? WHERE donation_id = ?");
        $stmt->bind_param("si", $status, $donation_id);
        
        if ($stmt->execute()) {
            $message = "Donation status updated successfully!";
        } else {
            $error = "Error updating donation status.";
        }
        $stmt->close();
    }
    
    if ($_POST['action'] == 'delete_donation') {
        $donation_id = intval($_POST['donation_id']);
        
        $stmt = $conn->prepare("DELETE FROM donations WHERE donation_id = ?");
        $stmt->bind_param("i", $donation_id);
        
        if ($stmt->execute()) {
            $message = "Donation deleted successfully!";
        } else {
            $error = "Error deleting donation.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query
$query = "
    SELECT d.donation_id, d.donation_date, d.status, d.amount_donated, d.donation_location,
           do.donor_id, do.full_name, do.blood_group, do.email, do.phone
    FROM donations d
    JOIN donors do ON d.donor_id = do.donor_id
    WHERE 1=1
";

if (!empty($status_filter)) {
    $query .= " AND d.status = '$status_filter'";
}
if (!empty($date_from)) {
    $query .= " AND DATE(d.donation_date) >= '$date_from'";
}
if (!empty($date_to)) {
    $query .= " AND DATE(d.donation_date) <= '$date_to'";
}

$query .= " ORDER BY d.donation_date DESC";

$donations = $conn->query($query);

// Get statistics
$total_donations = $conn->query("SELECT COUNT(*) as count FROM donations")->fetch_assoc()['count'];
$approved_donations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Approved'")->fetch_assoc()['count'];
$pending_donations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Pending'")->fetch_assoc()['count'];
$total_blood = $conn->query("SELECT SUM(amount_donated) as total FROM donations WHERE status='Approved'")->fetch_assoc()['total'] ?? 0;
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

    .manage-page {
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

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8em;
    }

    .stat-card.total .stat-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .stat-card.approved .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-card.pending .stat-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .stat-card.blood .stat-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .stat-info h3 {
        color: #64748b;
        font-size: 0.9em;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .stat-info .value {
        font-size: 1.8em;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .stat-info .label {
        color: #94a3b8;
        font-size: 0.85em;
    }

    /* Filter Section */
    .filter-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .filter-title {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #1e293b;
        margin-bottom: 15px;
        font-weight: 600;
    }

    .filter-title i {
        color: #8b5cf6;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-group label {
        color: #64748b;
        font-size: 0.9em;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .filter-group label i {
        color: #3b82f6;
        font-size: 0.9em;
    }

    .filter-input {
        padding: 10px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95em;
        transition: all 0.3s;
    }

    .filter-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filter-actions {
        display: flex;
        gap: 10px;
    }

    .btn-filter {
        padding: 10px 20px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .btn-filter:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }

    .btn-reset {
        padding: 10px 20px;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.3s;
    }

    .btn-reset:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    /* Donations Table */
    .table-section {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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

    .btn-add {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        padding: 12px 25px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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

    .donor-name:hover {
        color: #3b82f6;
    }

    .donor-contact {
        font-size: 0.85em;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 3px;
    }

    .donor-contact i {
        font-size: 0.8em;
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

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .status-badge.approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.pending {
        background: #fed7aa;
        color: #92400e;
    }

    .status-badge.rejected {
        background: #fee2e2;
        color: #b91c1c;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 8px 12px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 0.85em;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s;
        text-decoration: none;
    }

    .btn-action.approve {
        background: #d1fae5;
        color: #065f46;
    }

    .btn-action.approve:hover {
        background: #a7f3d0;
    }

    .btn-action.reject {
        background: #fee2e2;
        color: #b91c1c;
    }

    .btn-action.reject:hover {
        background: #fecaca;
    }

    .btn-action.edit {
        background: #dbeafe;
        color: #1e40af;
    }

    .btn-action.edit:hover {
        background: #bfdbfe;
    }

    .btn-action.delete {
        background: #f1f5f9;
        color: #64748b;
    }

    .btn-action.delete:hover {
        background: #e2e8f0;
        color: #dc2626;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 20px;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }

    .modal-icon {
        width: 50px;
        height: 50px;
        background: #fee2e2;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dc2626;
        font-size: 1.5em;
    }

    .modal-title {
        font-size: 1.3em;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .modal-text {
        color: #64748b;
        margin-bottom: 20px;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .btn-modal {
        padding: 12px 25px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-modal.cancel {
        background: #f1f5f9;
        color: #64748b;
    }

    .btn-modal.cancel:hover {
        background: #e2e8f0;
    }

    .btn-modal.confirm {
        background: #dc2626;
        color: white;
    }

    .btn-modal.confirm:hover {
        background: #b91c1c;
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

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .page-link {
        padding: 8px 15px;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        color: #64748b;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
    }

    .page-link:hover,
    .page-link.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-actions {
            flex-direction: column;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .btn-action {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="manage-page">
    <div class="page-header">
        <h2>
            <i class="fas fa-tint"></i>
            Manage Donations
        </h2>
        <p>
            <i class="fas fa-sliders-h"></i>
            View, filter, and manage all blood donations
        </p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3>Total Donations</h3>
                <div class="value"><?php echo number_format($total_donations); ?></div>
                <div class="label">All time donations</div>
            </div>
        </div>

        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Approved</h3>
                <div class="value"><?php echo number_format($approved_donations); ?></div>
                <div class="label">Successfully processed</div>
            </div>
        </div>

        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pending</h3>
                <div class="value"><?php echo number_format($pending_donations); ?></div>
                <div class="label">Awaiting approval</div>
            </div>
        </div>

        <div class="stat-card blood">
            <div class="stat-icon">
                <i class="fas fa-flask"></i>
            </div>
            <div class="stat-info">
                <h3>Blood Units</h3>
                <div class="value"><?php echo number_format($total_blood, 2); ?></div>
                <div class="label">Total collected</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-title">
            <i class="fas fa-filter"></i>
            Filter Donations
        </div>
        
        <form method="GET" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>
                        <i class="fas fa-tag"></i>
                        Status
                    </label>
                    <select name="status" class="filter-input">
                        <option value="">All Status</option>
                        <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>
                        <i class="fas fa-calendar-alt"></i>
                        From Date
                    </label>
                    <input type="date" name="date_from" class="filter-input" value="<?php echo $date_from; ?>">
                </div>

                <div class="filter-group">
                    <label>
                        <i class="fas fa-calendar-alt"></i>
                        To Date
                    </label>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo $date_to; ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                    <a href="manage_donations.php" class="btn-reset">
                        <i class="fas fa-undo"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Donations Table -->
    <div class="table-section">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list"></i>
                Donations List
            </div>
            <a href="add_donation.php" class="btn-add">
                <i class="fas fa-plus-circle"></i>
                Add New Donation
            </a>
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
                            <th><i class="fas fa-map-marker-alt"></i> Location</th>
                            <th><i class="fas fa-flask"></i> Amount</th>
                            <th><i class="fas fa-tag"></i> Status</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $donations->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['donation_id']; ?></td>
                                <td>
                                    <div class="donor-info">
                                        <a href="manage_donors.php?view=<?php echo $row['donor_id']; ?>" class="donor-name">
                                            <i class="fas fa-user-circle"></i>
                                            <?php echo htmlspecialchars($row['full_name']); ?>
                                        </a>
                                        <div class="donor-contact">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($row['email']); ?>
                                        </div>
                                        <div class="donor-contact">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($row['phone']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="blood-badge"><?php echo htmlspecialchars($row['blood_group']); ?></span>
                                </td>
                                <td>
                                    <i class="far fa-calendar-alt" style="color: #64748b; margin-right: 5px;"></i>
                                    <?php echo date('M d, Y', strtotime($row['donation_date'])); ?>
                                    <br>
                                    <small style="color: #94a3b8;">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('h:i A', strtotime($row['donation_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <i class="fas fa-map-pin" style="color: #64748b; margin-right: 5px;"></i>
                                    <?php echo htmlspecialchars($row['donation_location']); ?>
                                </td>
                                <td>
                                    <span class="amount-badge">
                                        <i class="fas fa-tint" style="color: #EF4444;"></i>
                                        <?php echo $row['amount_donated']; ?> unit(s)
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                        <i class="fas 
                                            <?php 
                                            if ($row['status'] === 'Approved') echo 'fa-check-circle';
                                            elseif ($row['status'] === 'Pending') echo 'fa-hourglass-half';
                                            else echo 'fa-times-circle';
                                            ?>">
                                        </i>
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="donation_id" value="<?php echo $row['donation_id']; ?>">
                                                <input type="hidden" name="status" value="Approved">
                                                <button type="submit" class="btn-action approve" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                    Approve
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="donation_id" value="<?php echo $row['donation_id']; ?>">
                                                <input type="hidden" name="status" value="Rejected">
                                                <button type="submit" class="btn-action reject" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                    Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="edit_donation.php?id=<?php echo $row['donation_id']; ?>" class="btn-action edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                        
                                        <button class="btn-action delete" onclick="showDeleteModal(<?php echo $row['donation_id']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <a href="#" class="page-link"><i class="fas fa-chevron-left"></i></a>
                <a href="#" class="page-link active">1</a>
                <a href="#" class="page-link">2</a>
                <a href="#" class="page-link">3</a>
                <a href="#" class="page-link"><i class="fas fa-chevron-right"></i></a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No donations found</p>
                <a href="add_donation.php" class="btn-add" style="display: inline-flex; width: auto;">
                    <i class="fas fa-plus-circle"></i>
                    Add Your First Donation
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <div class="modal-title">Delete Donation</div>
                <div class="modal-text">Are you sure you want to delete this donation? This action cannot be undone.</div>
            </div>
        </div>
        
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete_donation">
            <input type="hidden" name="donation_id" id="deleteDonationId">
            
            <div class="modal-actions">
                <button type="button" class="btn-modal cancel" onclick="hideDeleteModal()">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button type="submit" class="btn-modal confirm">
                    <i class="fas fa-trash"></i>
                    Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showDeleteModal(donationId) {
        document.getElementById('deleteDonationId').value = donationId;
        document.getElementById('deleteModal').classList.add('active');
    }

    function hideDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target === modal) {
            modal.classList.remove('active');
        }
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>