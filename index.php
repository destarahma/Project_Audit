<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Dashboard - ' . SITE_NAME;

// Get statistics
$conn = getConnection();

// Total submissions
$result = $conn->query("SELECT COUNT(*) as total FROM audit_submissions");
$totalSubmissions = $result->fetch_assoc()['total'];

// Pending submissions
$result = $conn->query("SELECT COUNT(*) as total FROM audit_submissions WHERE status = 'draft'");
$draftSubmissions = $result->fetch_assoc()['total'];

// Approved submissions
$result = $conn->query("SELECT COUNT(*) as total FROM audit_submissions WHERE status = 'approved'");
$approvedSubmissions = $result->fetch_assoc()['total'];

// Recent submissions
$stmt = $conn->prepare("
    SELECT s.*, t.template_name 
    FROM audit_submissions s 
    JOIN audit_templates t ON s.template_id = t.id 
    ORDER BY s.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recentSubmissions = $stmt->get_result();

$conn->close();

include 'includes/header.php';
?>

<div class="dashboard">
    <!-- Greeting Card -->
    <div class="greeting-card">
        <div class="greeting-content">
            <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
            <p>Kelola semua audit dengan mudah dan efisien</p>
        </div>
    </div>
    
    <!-- Saldo Card -->
    <div class="saldo-card">
        <p class="saldo-label"><i class="fas fa-clipboard-list"></i> Total Audit</p>
        <h2 class="saldo-amount"><?php echo $totalSubmissions; ?></h2>
        <p class="saldo-info"><span style="color: var(--success-color);"><i class="fas fa-check-circle"></i> <?php echo $approvedSubmissions; ?> Approved</span> | <i class="fas fa-file-alt"></i> Draft: <?php echo $draftSubmissions; ?></p>
    </div>
    
    <!-- Menu Utama -->
    <div class="menu-utama">
        <h3><i class="fas fa-th-large"></i> Menu Utama</h3>
        <div class="menu-grid">
            <a href="audit/select_type.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #fff4e6 0%, #ffe7ba 100%); color: var(--primary-color);">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="menu-content">
                    <h4>Buat Audit</h4>
                    <p>Buat audit baru dengan template yang tersedia</p>
                </div>
            </a>
            
            <a href="audit/list.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #e6f7ff 0%, #bae7ff 100%); color: #1890ff;">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="menu-content">
                    <h4>Daftar Audit</h4>
                    <p>Lihat dan kelola semua audit yang ada</p>
                </div>
            </a>
            
            <a href="admin/templates.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #f0f5ff 0%, #d6e4ff 100%); color: #597ef7;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="menu-content">
                    <h4>Template Audit</h4>
                    <p>Kelola template dan approval rules</p>
                </div>
            </a>
            
            <?php if (isAdmin()): ?>
            <a href="admin/users.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #fff7e6 0%, #ffd591 100%); color: #fa8c16;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="menu-content">
                    <h4>Kelola User</h4>
                    <p>Manajemen user dan hak akses</p>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Aktivitas Terbaru -->
    <div class="aktivitas-section">
        <div class="section-header">
            <h3><i class="fas fa-clock"></i> Aktivitas Terbaru</h3>
            <a href="audit/list.php" class="view-all">Lihat Semua <i class="fas fa-arrow-right"></i></a>
        </div>
        
        <?php if ($recentSubmissions->num_rows > 0): ?>
        <div class="aktivitas-list">
            <?php while ($row = $recentSubmissions->fetch_assoc()): ?>
            <a href="audit/view.php?id=<?php echo $row['id']; ?>" class="aktivitas-item">
                <div class="aktivitas-icon <?php echo $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'pending'); ?>">
                    <i class="fas fa-<?php echo $row['status'] === 'approved' ? 'check' : ($row['status'] === 'rejected' ? 'times' : 'clock'); ?>"></i>
                </div>
                <div class="aktivitas-content">
                    <h4><?php echo htmlspecialchars($row['template_name']); ?></h4>
                    <p><i class="far fa-calendar"></i> <?php echo formatDate($row['submission_date']); ?></p>
                </div>
                <div class="aktivitas-amount">
                    <span class="amount <?php echo $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : ''); ?>">
                        <?php echo ucfirst($row['status']); ?>
                    </span>
                    <span class="status-badge"><?php echo $row['status'] === 'draft' ? 'Draft' : ($row['status'] === 'submitted' ? 'Review' : $row['status']); ?></span>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <p class="no-data">Belum ada aktivitas audit.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
