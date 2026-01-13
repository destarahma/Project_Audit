<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    flashMessage('Akses ditolak', 'danger');
    redirect('index.php');
}

$pageTitle = 'Kelola Template Audit';

$conn = getConnection();
$templates = $conn->query("SELECT * FROM audit_templates ORDER BY template_name");
$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Kelola Template Audit</h1>
    <a href="template_create.php" class="btn btn-primary">➕ Tambah Template Baru</a>
</div>

<div class="card">
    <?php if ($templates->num_rows > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Template</th>
                <th>Kode</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while ($row = $templates->fetch_assoc()): 
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($row['template_name']); ?></td>
                <td><code><?php echo htmlspecialchars($row['template_code']); ?></code></td>
                <td>
                    <span class="badge badge-<?php echo $row['is_active'] ? 'approved' : 'rejected'; ?>">
                        <?php echo $row['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                    </span>
                </td>
                <td><?php echo formatDate($row['created_at']); ?></td>
                <td>
                    <a href="template_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">✏️ Edit</a>
                    <a href="template_view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm">👁️ Lihat</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="no-data">Belum ada template.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Panduan Penggunaan</h3>
    <ul>
        <li>Template audit adalah formulir yang dapat digunakan berulang kali</li>
        <li>Setiap template memiliki beberapa section (bagian)</li>
        <li>Setiap section berisi checklist items yang perlu diaudit</li>
        <li>Template dapat diedit untuk menyesuaikan dengan kebutuhan</li>
    </ul>
</div>

<?php include '../includes/footer.php'; ?>
