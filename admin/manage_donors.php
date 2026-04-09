<?php
$page_title = 'Manage Donors - Blood Donation System';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$message = '';
$error = '';

// Handle donor status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $donor_id = intval($_POST['donor_id']);
        $status = sanitizeInput($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE donors SET status = ? WHERE donor_id = ?");
        $stmt->bind_param("si", $status, $donor_id);
        
        if ($stmt->execute()) {
            $message = "Donor status updated successfully!";
        } else {
            $error = "Error updating donor status.";
        }
        $stmt->close();
    }
    
    if ($_POST['action'] == 'delete_donor') {
        $donor_id = intval($_POST['donor_id']);
        
        // Check if donor has donations
        $check = $conn->query("SELECT COUNT(*) as count FROM donations WHERE donor_id = $donor_id")->fetch_assoc()['count'];
        
        if ($check > 0) {
            $error = "Cannot delete donor with existing donations. Please delete donations first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM donors WHERE donor_id = ?");
            $stmt->bind_param("i", $donor_id);
            
            if ($stmt->execute()) {
                $message = "Donor deleted successfully!";
            } else {
                $error = "Error deleting donor.";
            }
            $stmt->close();
        }
    }
}

// Get filter parameters
$blood_group_filter = isset($_GET['blood_group']) ? sanitizeInput($_GET['blood_group']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query
$query = "
    SELECT d.*, 
           (SELECT COUNT(*) FROM donations WHERE donor_id = d.donor_id AND status='Approved') as donation_count,
           (SELECT SUM(amount_donated) FROM donations WHERE donor_id = d.donor_id AND status='Approved') as total_blood
    FROM donors d
    WHERE 1=1
";

if (!empty($blood_group_filter)) {
    $query .= " AND d.blood_group = '$blood_group_filter'";
}
if (!empty($status_filter)) {
    $query .= " AND d.status = '$status_filter'";
}
if (!empty($search)) {
    $query .= " AND (d.full_name LIKE '%$search%' OR d.email LIKE '%$search%' OR d.national_id LIKE '%$search%')";
}

$query .= " ORDER BY d.registration_date DESC";

$donors = $conn->query($query);

// Get statistics
$total_donors = $conn->query("SELECT COUNT(*) as count FROM donors")->fetch_assoc()['count'];
$active_donors = $conn->query("SELECT COUNT(*) as count FROM donors WHERE status='Active'")->fetch_assoc()['count'];
$inactive_donors = $conn->query("SELECT COUNT(*) as count FROM donors WHERE status='Inactive'")->fetch_assoc()['count'];
$total_donations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Approved'")->fetch_assoc()['count'];

// Get blood group distribution for chart
$blood_groups = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
$blood_group_stats = [];
foreach ($blood_groups as $bg) {
    $count = $conn->query("SELECT COUNT(*) as count FROM donors WHERE blood_group='$bg'")->fetch_assoc()['count'];
    $blood_group_stats[$bg] = $count;
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    .stat-card.active .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .stat-card.inactive .stat-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .stat-card.donations .stat-icon { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

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

    /* Search and Filter Section */
    .search-section {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .search-title {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #1e293b;
        margin-bottom: 15px;
        font-weight: 600;
    }

    .search-title i {
        color: #8b5cf6;
    }

    .search-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 15px;
        align-items: end;
    }

    @media (max-width: 768px) {
        .search-grid {
            grid-template-columns: 1fr;
        }
    }

    .search-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .search-group label {
        color: #64748b;
        font-size: 0.9em;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .search-group label i {
        color: #3b82f6;
    }

    .search-input {
        padding: 12px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95em;
        transition: all 0.3s;
        width: 100%;
    }

    .search-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-search {
        padding: 12px 25px;
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
        height: fit-content;
    }

    .btn-search:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }

    .btn-reset {
        padding: 12px 25px;
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
        height: fit-content;
    }

    .btn-reset:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    /* Donors Table */
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

    .donor-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.1em;
    }

    .donor-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .donor-details {
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

    .donor-email {
        font-size: 0.85em;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 5px;
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

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.inactive {
        background: #fee2e2;
        color: #b91c1c;
    }

    .donation-stats {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .donation-count {
        font-weight: 600;
        color: #059669;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .blood-amount {
        font-size: 0.85em;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 5px;
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

    .btn-action.view {
        background: #dbeafe;
        color: #1e40af;
    }

    .btn-action.view:hover {
        background: #bfdbfe;
    }

    .btn-action.edit {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-action.edit:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .btn-action.delete {
        background: #fee2e2;
        color: #b91c1c;
    }

    .btn-action.delete:hover {
        background: #fecaca;
    }

    /* Chart Card */
    .chart-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .chart-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #1e293b;
        font-size: 1.1em;
        font-weight: 600;
    }

    .chart-title i {
        color: #8b5cf6;
    }

    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
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
</style>

<div class="manage-page">
    <div class="page-header">
        <h2>
            <i class="fas fa-users"></i>
            Manage Donors
        </h2>
        <p>
            <i class="fas fa-sliders-h"></i>
            View, filter, and manage all blood donors
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
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Donors</h3>
                <div class="value"><?php echo number_format($total_donors); ?></div>
                <div class="label">Registered donors</div>
            </div>
        </div>

        <div class="stat-card active">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Active Donors</h3>
                <div class="value"><?php echo number_format($active_donors); ?></div>
                <div class="label">Currently active</div>
            </div>
        </div>

        <div class="stat-card inactive">
            <div class="stat-icon">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Inactive Donors</h3>
                <div class="value"><?php echo number_format($inactive_donors); ?></div>
                <div class="label">Temporarily inactive</div>
            </div>
        </div>

        <div class="stat-card donations">
            <div class="stat-icon">
                <i class="fas fa-tint"></i>
            </div>
            <div class="stat-info">
                <h3>Total Donations</h3>
                <div class="value"><?php echo number_format($total_donations); ?></div>
                <div class="label">Approved donations</div>
            </div>
        </div>
    </div>

    <!-- Blood Group Distribution Chart -->
    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">
                <i class="fas fa-chart-pie"></i>
                Blood Group Distribution
            </div>
            <div class="chart-period">Donor Demographics</div>
        </div>
        <div class="chart-container">
            <canvas id="bloodGroupChart"></canvas>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="search-section">
        <div class="search-title">
            <i class="fas fa-search"></i>
            Search Donors
        </div>
        
        <form method="GET" action="">
            <div class="search-grid">
                <div class="search-group">
                    <label>
                        <i class="fas fa-user"></i>
                        Search
                    </label>
                    <input type="text" name="search" class="search-input" placeholder="Name, email, or national ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="search-group">
                    <label>
                        <i class="fas fa-tint"></i>
                        Blood Group
                    </label>
                    <select name="blood_group" class="search-input">
                        <option value="">All Blood Groups</option>
                        <?php foreach ($blood_groups as $bg): ?>
                            <option value="<?php echo $bg; ?>" <?php echo $blood_group_filter === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-group">
                    <label>
                        <i class="fas fa-tag"></i>
                        Status
                    </label>
                    <select name="status" class="search-input">
                        <option value="">All Status</option>
                        <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $status_filter === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <a href="manage_donors.php" class="btn-reset">
                        <i class="fas fa-undo"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Donors Table -->
    <div class="table-section">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list"></i>
                Donors List
            </div>
            <a href="add_donor.php" class="btn-add">
                <i class="fas fa-plus-circle"></i>
                Add New Donor
            </a>
        </div>

        <?php if ($donors->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Donor</th>
                            <th><i class="fas fa-tint"></i> Blood Group</th>
                            <th><i class="fas fa-phone"></i> Contact</th>
                            <th><i class="fas fa-calendar"></i> Registered</th>
                            <th><i class="fas fa-chart-bar"></i> Donations</th>
                            <th><i class="fas fa-tag"></i> Status</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $donors->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="donor-info">
                                        <div class="donor-avatar">
                                            <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                        </div>
                                        <div class="donor-details">
                                            <a href="view_donor.php?id=<?php echo $row['donor_id']; ?>" class="donor-name">
                                                <?php echo htmlspecialchars($row['full_name']); ?>
                                            </a>
                                            <div class="donor-email">
                                                <i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($row['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="blood-badge"><?php echo htmlspecialchars($row['blood_group']); ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 3px;">
                                        <div><i class="fas fa-phone" style="color: #64748b; width: 20px;"></i> <?php echo htmlspecialchars($row['phone']); ?></div>
                                        <div><i class="fas fa-id-card" style="color: #64748b; width: 20px;"></i> <?php echo htmlspecialchars($row['national_id']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <i class="far fa-calendar-alt" style="color: #64748b; margin-right: 5px;"></i>
                                    <?php echo date('M d, Y', strtotime($row['registration_date'])); ?>
                                </td>
                                <td>
                                    <div class="donation-stats">
                                        <span class="donation-count">
                                            <i class="fas fa-tint" style="color: #EF4444;"></i>
                                            <?php echo $row['donation_count']; ?> donations
                                        </span>
                                        <span class="blood-amount">
                                            <i class="fas fa-flask"></i>
                                            <?php echo number_format($row['total_blood'] ?? 0, 2); ?> units
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($row['status'] ?? 'active'); ?>">
                                        <i class="fas <?php echo ($row['status'] ?? 'active') === 'Active' ? 'fa-check-circle' : 'fa-pause-circle'; ?>"></i>
                                        <?php echo $row['status'] ?? 'Active'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (($row['status'] ?? 'active') === 'Inactive'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="donor_id" value="<?php echo $row['donor_id']; ?>">
                                                <input type="hidden" name="status" value="Active">
                                                <button type="submit" class="btn-action view" title="Activate">
                                                    <i class="fas fa-play"></i>
                                                    Activate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="donor_id" value="<?php echo $row['donor_id']; ?>">
                                                <input type="hidden" name="status" value="Inactive">
                                                <button type="submit" class="btn-action view" title="Deactivate">
                                                    <i class="fas fa-pause"></i>
                                                    Deactivate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="edit_donor.php?id=<?php echo $row['donor_id']; ?>" class="btn-action edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                        
                                        <button class="btn-action delete" onclick="showDeleteModal(<?php echo $row['donor_id']; ?>)" title="Delete">
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
                <i class="fas fa-users"></i>
                <p>No donors found</p>
                <a href="add_donor.php" class="btn-add" style="display: inline-flex; width: auto;">
                    <i class="fas fa-plus-circle"></i>
                    Add Your First Donor
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
                <div class="modal-title">Delete Donor</div>
                <div class="modal-text">Are you sure you want to delete this donor? This action cannot be undone.</div>
            </div>
        </div>
        
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete_donor">
            <input type="hidden" name="donor_id" id="deleteDonorId">
            
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
    // Blood Group Distribution Chart
    const bloodGroupCtx = document.getElementById('bloodGroupChart').getContext('2d');
    new Chart(bloodGroupCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_keys($blood_group_stats)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($blood_group_stats)); ?>,
                backgroundColor: ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#6366F1', '#14B8A6'],
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        color: '#1e293b',
                        font: {
                            size: 11,
                            weight: '500'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f1f5f9',
                    bodyColor: '#cbd5e1',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.raw / total) * 100).toFixed(1);
                            return `${context.label}: ${context.raw} donors (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    function showDeleteModal(donorId) {
        document.getElementById('deleteDonorId').value = donorId;
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