<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    flashMessage('Akses ditolak', 'danger');
    redirect('index.php');
}

$pageTitle = 'Kelola User';

$conn = getConnection();

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $username = sanitize($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $role = sanitize($_POST['role']);
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $fullName, $email, $role);
    
    if ($stmt->execute()) {
        flashMessage('User berhasil ditambahkan', 'success');
    } else {
        flashMessage('Gagal menambahkan user', 'danger');
    }
    $stmt->close();
    redirect('admin/users.php');
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $userId = intval($_POST['user_id']);
    $currentUser = getCurrentUser();
    
    // Prevent user from deleting themselves
    if ($userId === $currentUser['id']) {
        flashMessage('Anda tidak dapat menghapus akun Anda sendiri', 'danger');
        redirect('admin/users.php');
    }
    
    // Check if user has submitted audits
    $checkStmt = $conn->prepare("SELECT COUNT(*) as audit_count FROM audit_submissions WHERE submitted_by = ?");
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($row['audit_count'] > 0) {
        flashMessage('User tidak dapat dihapus karena memiliki ' . $row['audit_count'] . ' audit yang terkait. Hapus audit terlebih dahulu.', 'danger');
        redirect('admin/users.php');
    }
    
    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        flashMessage('User berhasil dihapus', 'success');
    } else {
        flashMessage('Gagal menghapus user', 'danger');
    }
    $stmt->close();
    redirect('admin/users.php');
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Kelola User</h1>
    <button class="btn btn-secondary" onclick="document.getElementById('addUserModal').style.display='block'">
        <i class="fas fa-plus"></i> Tambah User
    </button>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Username</th>
                <th>Nama Lengkap</th>
                <th>Email</th>
                <th>Role</th>
                <th>Terdaftar</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $currentUser = getCurrentUser();
            while ($row = $users->fetch_assoc()): 
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                <td>
                    <span class="badge badge-<?php echo $row['role'] === 'admin' ? 'approved' : 'submitted'; ?>">
                        <?php echo ucfirst($row['role']); ?>
                    </span>
                </td>
                <td><?php echo formatDate($row['created_at']); ?></td>
                <td>
                    <?php if ($row['id'] !== $currentUser['id']): ?>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>')">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                    <?php else: ?>
                    <span class="text-muted" style="font-size: 12px;">Akun Anda</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('addUserModal').style.display='none'">&times;</span>
        <h2>Tambah User Baru</h2>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="full_name">Nama Lengkap *</label>
                <input type="text" name="full_name" id="full_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="role">Role *</label>
                <select name="role" id="role" class="form-control" required>
                    <option value="auditor">Auditor</option>
                    <option value="admin">Admin</option>
                    <option value="viewer">Viewer</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addUserModal').style.display='none'">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteUserModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h2>Konfirmasi Hapus User</h2>
        <p>Apakah Anda yakin ingin menghapus user <strong id="deleteUsername"></strong>?</p>
        <p class="text-muted" style="font-size: 14px; margin-top: 10px;">
            <i class="fas fa-exclamation-triangle"></i> 
            User yang memiliki audit tidak dapat dihapus. Hapus audit terlebih dahulu.
        </p>
        
        <form method="POST" action="users.php" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="deleteUserId">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteUserModal').style.display='none'">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDelete(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUsername').textContent = username;
    document.getElementById('deleteUserModal').style.display = 'block';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addUserModal');
    const deleteModal = document.getElementById('deleteUserModal');
    if (event.target == addModal) {
        addModal.style.display = 'none';
    }
    if (event.target == deleteModal) {
        deleteModal.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
