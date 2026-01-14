<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Daftar Audit';
$currentUser = getCurrentUser();

$conn = getConnection();

// Get all submissions for current user (or all if admin)
if (isAdmin()) {
    $query = "
        SELECT s.*, t.template_name, u.full_name as auditor_name
        FROM audit_submissions s 
        JOIN audit_templates t ON s.template_id = t.id 
        JOIN users u ON s.submitted_by = u.id
        ORDER BY s.created_at DESC
    ";
    $submissions = $conn->query($query);
} else {
    $stmt = $conn->prepare("
        SELECT s.*, t.template_name 
        FROM audit_submissions s 
        JOIN audit_templates t ON s.template_id = t.id 
        WHERE s.submitted_by = ? 
        ORDER BY s.created_at DESC
    ");
    $stmt->bind_param("i", $currentUser['id']);
    $stmt->execute();
    $submissions = $stmt->get_result();
}

$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Daftar Audit</h1>
    <a href="create.php" class="btn btn-primary">➕ Buat Audit Baru</a>
</div>

<div class="card">
    <?php if ($submissions->num_rows > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Template</th>
                <?php if (isAdmin()): ?>
                <th>Auditor</th>
                <?php endif; ?>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while ($row = $submissions->fetch_assoc()): 
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($row['template_name']); ?></td>
                <?php if (isAdmin()): ?>
                <td><?php echo htmlspecialchars($row['auditor_name']); ?></td>
                <?php endif; ?>
                <td><?php echo formatDate($row['submission_date']); ?></td>
                <td>
                    <span class="badge badge-<?php echo $row['status']; ?>">
                        <?php 
                        $statusLabels = [
                            'draft' => 'Draft',
                            'submitted' => 'Disubmit',
                            'reviewed' => 'Direview',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak'
                        ];
                        echo $statusLabels[$row['status']] ?? $row['status'];
                        ?>
                    </span>
                </td>
                <td><?php echo formatDate($row['created_at']); ?></td>
                <td>
                    <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-view" title="Lihat Detail">
                        <i class="fas fa-eye"></i> Lihat
                    </a>
                    <?php if ($row['status'] === 'draft'): ?>
                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-edit" title="Edit Audit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php endif; ?>
                    <a href="delete.php?id=<?php echo $row['id']; ?>" 
                       class="btn btn-sm btn-delete" 
                       onclick="return confirm('Apakah Anda yakin ingin menghapus audit ini?')"
                       title="Hapus Audit">
                        <i class="fas fa-trash"></i> Hapus
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="no-data">Belum ada audit yang dibuat.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
