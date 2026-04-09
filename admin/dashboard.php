<?php
$page_title = 'Admin Dashboard - Blood Donation System';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

// Get statistics
$total_donors = $conn->query("SELECT COUNT(*) as count FROM donors")->fetch_assoc()['count'];
$total_donations = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Approved'")->fetch_assoc()['count'];
$pending_approvals = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='Pending'")->fetch_assoc()['count'];
$total_blood_units = $conn->query("SELECT SUM(amount_donated) as total FROM donations WHERE status='Approved'")->fetch_assoc()['total'] ?? 0;

// Get recent donations
$recent_donations_query = "
    SELECT d.donation_id, d.donation_date, d.status, d.amount_donated, 
           do.donor_id, do.full_name, do.blood_group
    FROM donations d
    JOIN donors do ON d.donor_id = do.donor_id
    ORDER BY d.donation_date DESC
    LIMIT 5
";
$recent_donations = $conn->query($recent_donations_query);

// Get monthly donation stats for chart
$monthly_stats_query = "
    SELECT DATE_FORMAT(donation_date, '%Y-%m') as month, COUNT(*) as count
    FROM donations
    WHERE status='Approved'
    GROUP BY DATE_FORMAT(donation_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$monthly_stats = $conn->query($monthly_stats_query);
$months = [];
$counts = [];
while ($row = $monthly_stats->fetch_assoc()) {
    $months[] = $row['month'];
    $counts[] = $row['count'];
}
$months = array_reverse($months);
$counts = array_reverse($counts);

// Calculate growth percentages
$current_month_count = $counts[count($counts)-1] ?? 0;
$previous_month_count = $counts[count($counts)-2] ?? 0;
$growth_percentage = $previous_month_count > 0 ? round((($current_month_count - $previous_month_count) / $previous_month_count) * 100, 1) : 0;

// Get top donors
$top_donors_query = "
    SELECT do.donor_id, do.full_name, do.blood_group, COUNT(d.donation_id) as donation_count
    FROM donors do
    LEFT JOIN donations d ON do.donor_id = d.donor_id AND d.status='Approved'
    GROUP BY do.donor_id
    ORDER BY donation_count DESC
    LIMIT 5
";
$top_donors = $conn->query($top_donors_query);

// Get blood group distribution
$blood_groups = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
$blood_group_data = [];
$blood_group_colors = [
    '#EF4444', '#F59E0B', '#10B981', '#3B82F6', 
    '#8B5CF6', '#EC4899', '#6366F1', '#14B8A6'
];

foreach ($blood_groups as $index => $bg) {
    $count = $conn->query("SELECT COUNT(*) as count FROM donors WHERE blood_group='$bg'")->fetch_assoc()['count'];
    $blood_group_data[] = [
        'group' => $bg,
        'count' => $count,
        'color' => $blood_group_colors[$index]
    ];
}

// Get recent activity stats
$today_donations = $conn->query("
    SELECT COUNT(*) as count FROM donations 
    WHERE DATE(donation_date) = CURDATE()
")->fetch_assoc()['count'];

$week_donations = $conn->query("
    SELECT COUNT(*) as count FROM donations 
    WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['count'];
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

    .admin-dashboard {
        padding: 20px;
        background-color: #f8fafc;
        min-height: 100vh;
    }

    /* Header Styles */
    .dashboard-header {
        margin-bottom: 30px;
    }

    .dashboard-header h2 {
        font-size: 2em;
        color: #1e293b;
        margin-bottom: 10px;
    }

    .dashboard-header h2 i {
        color: #8b5cf6;
        margin-right: 10px;
    }

    .dashboard-header p {
        color: #64748b;
        font-size: 1.1em;
    }

    .dashboard-header p i {
        margin-right: 8px;
        color: #3b82f6;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, currentColor, transparent);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .stat-header h3 {
        color: #64748b;
        font-size: 0.95em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
    }

    .stat-card.donors .stat-icon { background: rgba(239, 68, 68, 0.1); color: #EF4444; }
    .stat-card.donations .stat-icon { background: rgba(16, 185, 129, 0.1); color: #10B981; }
    .stat-card.pending .stat-icon { background: rgba(245, 158, 11, 0.1); color: #F59E0B; }
    .stat-card.units .stat-icon { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }

    .stat-value {
        font-size: 2.5em;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .stat-trend {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #64748b;
        font-size: 0.9em;
    }

    .trend-up { color: #10B981; }
    .trend-down { color: #EF4444; }

    /* Charts Grid */
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    @media (max-width: 768px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }

    .chart-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
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

    .chart-period {
        background: #f1f5f9;
        padding: 5px 12px;
        border-radius: 20px;
        color: #64748b;
        font-size: 0.85em;
    }

    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    /* Recent Donations Table */
    .recent-section {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #1e293b;
        font-size: 1.2em;
        font-weight: 600;
    }

    .section-title i {
        color: #8b5cf6;
    }

    .view-all {
        color: #3b82f6;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 500;
        padding: 8px 15px;
        border-radius: 8px;
        background: #f1f5f9;
        transition: all 0.3s;
    }

    .view-all:hover {
        background: #3b82f6;
        color: white;
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

    .donor-link {
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .donor-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }

    .blood-group-badge {
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

    .donation-amount {
        font-weight: 600;
        color: #059669;
    }

    /* Top Donors Grid */
    .top-donors-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .donor-card {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        transition: transform 0.3s;
        border: 2px solid transparent;
    }

    .donor-card:hover {
        transform: translateY(-5px);
        border-color: #8b5cf6;
    }

    .donor-rank {
        width: 40px;
        height: 40px;
        background: #8b5cf6;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin: 0 auto 15px;
        font-size: 1.2em;
    }

    .donor-avatar {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        color: white;
        font-size: 2em;
    }

    .donor-name {
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .donor-blood {
        display: inline-block;
        padding: 4px 12px;
        background: white;
        border-radius: 20px;
        font-size: 0.9em;
        font-weight: 600;
        color: #dc2626;
        margin-bottom: 10px;
    }

    .donor-stats {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: #64748b;
        font-size: 0.9em;
    }

    .donor-stats i {
        color: #fbbf24;
    }

    /* Activity Summary */
    .activity-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }

    .activity-item {
        background: #f8fafc;
        padding: 15px;
        border-radius: 12px;
        text-align: center;
    }

    .activity-value {
        font-size: 1.5em;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }

    .activity-label {
        color: #64748b;
        font-size: 0.85em;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    /* Quick Actions */
    .quick-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 30px;
    }

    .action-btn {
        padding: 12px 25px;
        background: white;
        border-radius: 12px;
        text-decoration: none;
        color: #1e293b;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.3s;
        border: 1px solid #e2e8f0;
    }

    .action-btn:hover {
        background: #8b5cf6;
        color: white;
        border-color: #8b5cf6;
        transform: translateY(-2px);
    }

    .action-btn i {
        font-size: 1.1em;
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
</style>

<div class="admin-dashboard">
    <div class="dashboard-header">
        <h2>
            <i class="fas fa-tachometer-alt"></i>
            Admin Dashboard
        </h2>
        <p>
            <i class="fas fa-chart-pie"></i>
            Welcome back! Here's an overview of your blood donation system.
        </p>
    </div>

    <?php displayMessage(); ?>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="manage_donors.php" class="action-btn">
            <i class="fas fa-users"></i>
            Manage Donors
        </a>
        <a href="manage_donations.php" class="action-btn">
            <i class="fas fa-tint"></i>
            Manage Donations
        </a>
        <a href="reports.php" class="action-btn">
            <i class="fas fa-chart-bar"></i>
            Generate Reports
        </a>
        <a href="certificates.php" class="action-btn">
            <i class="fas fa-certificate"></i>
            Certificates
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card donors">
            <div class="stat-header">
                <h3>Total Donors</h3>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_donors); ?></div>
            <div class="stat-trend">
                <i class="fas fa-user-plus trend-up"></i>
                <span>Registered donors in system</span>
            </div>
        </div>

        <div class="stat-card donations">
            <div class="stat-header">
                <h3>Total Donations</h3>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_donations); ?></div>
            <div class="stat-trend">
                <i class="fas fa-calendar-week"></i>
                <span><?php echo $week_donations; ?> this week</span>
            </div>
        </div>

        <div class="stat-card pending">
            <div class="stat-header">
                <h3>Pending Approvals</h3>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($pending_approvals); ?></div>
            <div class="stat-trend">
                <i class="fas fa-hourglass-half"></i>
                <span>Awaiting review</span>
            </div>
        </div>

        <div class="stat-card units">
            <div class="stat-header">
                <h3>Blood Units</h3>
                <div class="stat-icon">
                    <i class="fas fa-flask"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_blood_units, 2); ?></div>
            <div class="stat-trend">
                <i class="fas fa-tint"></i>
                <span>Total units collected</span>
            </div>
        </div>
    </div>

    <!-- Activity Summary -->
    <div class="activity-summary">
        <div class="activity-item">
            <div class="activity-value"><?php echo $today_donations; ?></div>
            <div class="activity-label">
                <i class="fas fa-calendar-day"></i>
                Today's Donations
            </div>
        </div>
        <div class="activity-item">
            <div class="activity-value"><?php echo $week_donations; ?></div>
            <div class="activity-label">
                <i class="fas fa-calendar-week"></i>
                This Week
            </div>
        </div>
        <div class="activity-item">
            <div class="activity-value <?php echo $growth_percentage >= 0 ? 'trend-up' : 'trend-down'; ?>">
                <?php echo $growth_percentage >= 0 ? '+' : ''; ?><?php echo $growth_percentage; ?>%
            </div>
            <div class="activity-label">
                <i class="fas fa-chart-line"></i>
                vs Last Month
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="charts-grid">
        <!-- Monthly Donations Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-chart-line"></i>
                    Donation Trends
                </div>
                <div class="chart-period">Last 12 Months</div>
            </div>
            <div class="chart-container">
                <canvas id="donationChart"></canvas>
            </div>
        </div>
        
        <!-- Blood Group Distribution -->
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Blood Group Distribution
                </div>
                <div class="chart-period">Donors</div>
            </div>
            <div class="chart-container">
                <canvas id="bloodGroupChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Donations -->
    <div class="recent-section">
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-history"></i>
                Recent Donations
            </div>
            <a href="manage_donations.php" class="view-all">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if ($recent_donations->num_rows > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-user"></i> Donor</th>
                            <th><i class="fas fa-tint"></i> Blood Group</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-flask"></i> Amount</th>
                            <th><i class="fas fa-tag"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recent_donations->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['donation_id']; ?></td>
                                <td>
                                    <a href="manage_donors.php?view=<?php echo $row['donor_id']; ?>" class="donor-link">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($row['full_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="blood-group-badge">
                                                        <?php echo htmlspecialchars($row['blood_group']); ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="far fa-calendar-alt" style="color: #64748b; margin-right: 5px;"></i>
                                    <?php echo date('M d, Y', strtotime($row['donation_date'])); ?>
                                </td>
                                <td>
                                    <span class="donation-amount">
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
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No recent donations found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Donors -->
    <div class="recent-section">
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-trophy"></i>
                Top Donors
            </div>
            <a href="manage_donors.php" class="view-all">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if ($top_donors->num_rows > 0): ?>
            <div class="top-donors-grid">
                <?php 
                $rank = 1;
                while ($row = $top_donors->fetch_assoc()): 
                ?>
                    <div class="donor-card">
                        <div class="donor-rank">#<?php echo $rank; ?></div>
                        <div class="donor-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="donor-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                        <div class="donor-blood"><?php echo htmlspecialchars($row['blood_group']); ?></div>
                        <div class="donor-stats">
                            <i class="fas fa-tint"></i>
                            <span><?php echo $row['donation_count']; ?> donations</span>
                        </div>
                    </div>
                <?php 
                $rank++;
                endwhile; 
                ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>No donors found</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Donation Trend Chart - Modern Line Chart
    const donationCtx = document.getElementById('donationChart').getContext('2d');
    new Chart(donationCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($month) {
                return date('M Y', strtotime($month . '-01'));
            }, $months)); ?>,
            datasets: [{
                label: 'Donations',
                data: <?php echo json_encode($counts); ?>,
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 8,
                pointBackgroundColor: '#EF4444',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointHoverBackgroundColor: '#B91C1C',
                pointHoverBorderColor: 'white',
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f1f5f9',
                    bodyColor: '#cbd5e1',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return `Donations: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e2e8f0',
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1,
                        color: '#64748b',
                        callback: function(value) {
                            return value + ' donations';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            },
            layout: {
                padding: {
                    left: 10,
                    right: 10,
                    top: 20,
                    bottom: 10
                }
            }
        }
    });
    
    // Blood Group Distribution Chart - Modern Doughnut
    const bloodGroupCtx = document.getElementById('bloodGroupChart').getContext('2d');
    new Chart(bloodGroupCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($blood_group_data, 'group')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($blood_group_data, 'count')); ?>,
                backgroundColor: <?php echo json_encode(array_column($blood_group_data, 'color')); ?>,
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
            },
            layout: {
                padding: {
                    bottom: 10
                }
            }
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>