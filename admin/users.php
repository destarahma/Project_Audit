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
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Kelola User</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addUserModal').style.display='block'">
        ➕ Tambah User
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
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
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

<?php include '../includes/footer.php'; ?>
