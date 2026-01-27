<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    flashMessage('Akses ditolak', 'danger');
    redirect('index.php');
}

$pageTitle = 'Tambah Template Audit';
include '../includes/header.php';
?>

<div class="page-header">
    <h1>Tambah Template Audit</h1>
    <a href="templates.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<div class="card" style="text-align: center; padding: 60px 40px;">
    <i class="fas fa-info-circle" style="font-size: 64px; color: #3B82F6; margin-bottom: 20px;"></i>
    <h3 style="color: #1e293b; margin-bottom: 15px;">Fitur Dalam Pengembangan</h3>
    <p style="color: #64748b; margin-bottom: 25px; font-size: 15px;">
        Fitur untuk membuat template audit baru dari awal sedang dalam tahap pengembangan.<br>
        Saat ini Anda dapat menggunakan fitur <strong>Copy Template</strong> untuk menduplikasi template yang sudah ada,<br>
        kemudian melakukan modifikasi sesuai kebutuhan.
    </p>
    
    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 20px; margin: 30px 0; text-align: left;">
        <h4 style="color: #0369a1; margin-bottom: 15px;">
            <i class="fas fa-lightbulb"></i> Alternatif: Gunakan Copy Template
        </h4>
        <ol style="color: #475569; line-height: 1.8;">
            <li>Kembali ke halaman <strong>Kelola Template Audit</strong></li>
            <li>Pilih template yang mirip dengan kebutuhan Anda</li>
            <li>Klik tombol <strong>"Copy"</strong> pada template tersebut</li>
            <li>Template akan diduplikasi dengan kode baru</li>
            <li>Edit template hasil copy sesuai kebutuhan</li>
        </ol>
    </div>
    
    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
        <a href="templates.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Template
        </a>
        <a href="template_copy.php" class="btn btn-secondary">
            <i class="fas fa-copy"></i> Lihat Template yang Bisa Dicopy
        </a>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-list-check"></i> Template yang Tersedia</h3>
    <p style="color: #64748b; margin-bottom: 20px;">
        Berikut adalah template yang sudah tersedia dan dapat dicopy:
    </p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
        <?php
        $conn = getConnection();
        $templates = $conn->query("SELECT * FROM audit_templates WHERE is_active = 1 ORDER BY template_name");
        while ($row = $templates->fetch_assoc()):
        ?>
        <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: white;">
            <h4 style="color: #3B82F6; margin-bottom: 10px; font-size: 16px;">
                <?php echo htmlspecialchars($row['template_name']); ?>
            </h4>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">
                <code><?php echo htmlspecialchars($row['template_code']); ?></code>
            </p>
            <a href="template_copy.php?from=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" style="width: 100%;">
                <i class="fas fa-copy"></i> Copy Template Ini
            </a>
        </div>
        <?php endwhile; $conn->close(); ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
