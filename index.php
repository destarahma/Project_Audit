<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Require login to access dashboard
requireLogin();

$pageTitle = 'Dashboard - ' . SITE_NAME;

// Get current user
$currentUser = getCurrentUser();
$isAdmin = isAdmin();

// Get statistics
$conn = getConnection();

// Filter berdasarkan role: admin melihat semua, auditor hanya punya sendiri
if ($isAdmin) {
    // Total submissions (admin: semua)
    $result = $conn->query("SELECT COUNT(*) as total FROM audit_submissions");
    $totalSubmissions = $result->fetch_assoc()['total'];

    // Approved submissions (admin: semua)
    $result = $conn->query("SELECT COUNT(*) as total FROM audit_submissions WHERE status = 'approved'");
    $approvedSubmissions = $result->fetch_assoc()['total'];

    // Submitted (pending review) submissions (admin: semua)
    $result = $conn->query("SELECT COUNT(*) as total FROM audit_submissions WHERE status = 'submitted'");
    $submittedSubmissions = $result->fetch_assoc()['total'];

    // Recent submissions (admin: semua)
    $stmt = $conn->prepare("
        SELECT s.*, t.template_name, u.full_name as auditor_name
        FROM audit_submissions s 
        JOIN audit_templates t ON s.template_id = t.id 
        JOIN users u ON s.submitted_by = u.id
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentSubmissions = $stmt->get_result();
} else {
    // Total submissions (auditor/viewer: hanya punya sendiri)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_submissions WHERE submitted_by = ?");
    $stmt->bind_param("i", $currentUser['id']);
    $stmt->execute();
    $totalSubmissions = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Approved submissions (auditor/viewer: hanya punya sendiri)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_submissions WHERE submitted_by = ? AND status = 'approved'");
    $stmt->bind_param("i", $currentUser['id']);
    $stmt->execute();
    $approvedSubmissions = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Submitted (pending review) submissions (auditor/viewer: hanya punya sendiri)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_submissions WHERE submitted_by = ? AND status = 'submitted'");
    $stmt->bind_param("i", $currentUser['id']);
    $stmt->execute();
    $submittedSubmissions = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Recent submissions (auditor/viewer: hanya punya sendiri)
    $stmt = $conn->prepare("
        SELECT s.*, t.template_name 
        FROM audit_submissions s 
        JOIN audit_templates t ON s.template_id = t.id 
        WHERE s.submitted_by = ?
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt->bind_param("i", $currentUser['id']);
    $stmt->execute();
    $recentSubmissions = $stmt->get_result();
}

$conn->close();

include 'includes/header.php';
?>

<div class="dashboard">
    <!-- Greeting Card -->
    <div class="greeting-card">
        <div class="greeting-content">
            <div style="font-size: 14px; font-weight: 500; opacity: 0.95; margin-bottom: 10px; letter-spacing: 1.5px; text-transform: uppercase;">Departemen Procurement</div>
            <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
            <p>Kelola semua audit dengan mudah dan efisien</p>
        </div>
    </div>
    
    <!-- Saldo Card -->
    <div class="saldo-card">
        <p class="saldo-label"><i class="fas fa-clipboard-list"></i> Total Audit</p>
        <h2 class="saldo-amount"><?php echo $totalSubmissions; ?></h2>
        <p class="saldo-info"><span style="color: var(--success-color);"><i class="fas fa-check-circle"></i> <?php echo $approvedSubmissions; ?> Approved</span> | <span style="color: #ffc107;"><i class="fas fa-clock"></i> <?php echo $submittedSubmissions; ?> Pending Review</span></p>
    </div>
    
    <!-- Menu Utama -->
    <div class="menu-utama">
        <h3><i class="fas fa-th-large"></i> Menu Utama</h3>
        <div class="menu-grid">
            <a href="audit/select_type.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #fff5f7 0%, #ffd1d9 100%); color: #C41E3A;">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="menu-content">
                    <h4>Buat Audit</h4>
                    <p>Buat audit baru dengan template yang tersedia</p>
                </div>
            </a>
            
            <a href="audit/list.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #fff5f7 0%, #ffd1d9 100%); color: #C41E3A;">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="menu-content">
                    <h4>Daftar Audit</h4>
                    <p>Lihat dan kelola semua audit yang ada</p>
                </div>
            </a>
            
            <a href="admin/templates.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #fff5f7 0%, #ffd1d9 100%); color: #C41E3A;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="menu-content">
                    <h4>Template Audit</h4>
                    <p>Kelola template dan approval rules</p>
                </div>
            </a>
            
            <?php if (isAdmin()): ?>
            <a href="admin/users.php" class="menu-card">
                <div class="menu-icon" style="background: linear-gradient(135deg, #fff5f7 0%, #ffd1d9 100%); color: #C41E3A;">
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
                    <p>
                        <i class="far fa-calendar"></i> <?php echo formatDate($row['submission_date']); ?>
                        <?php if ($isAdmin && isset($row['auditor_name'])): ?>
                        <span style="margin-left: 10px; color: #64748b;">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($row['auditor_name']); ?>
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="aktivitas-amount">
                    <span class="amount <?php echo $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : ''); ?>">
                        <?php echo ucfirst($row['status']); ?>
                    </span>
                    <span class="status-badge">
                        <?php 
                        $statusLabels = [
                            'draft' => 'Draft',
                            'submitted' => 'Pending Review',
                            'reviewed' => 'Direview',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak'
                        ];
                        echo $statusLabels[$row['status']] ?? ucfirst($row['status']);
                        ?>
                    </span>
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
