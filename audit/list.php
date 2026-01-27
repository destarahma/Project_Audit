<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Daftar Audit';
$currentUser = getCurrentUser();

$conn = getConnection();

// Get filter parameters
$filterTemplate = isset($_GET['template']) ? $_GET['template'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Define fixed template order
$templateOrder = [
    'Self Audit : Jual Beli Mix Oil',
    'Self Audit : Jual Barbes (Barang Bekas)',
    'Self Audit : Jual Aset',
    'PO Non OA',
    'PO Tagging OA'
];

// Get all active templates
$templatesQuery = "SELECT id, template_name, template_code FROM audit_templates WHERE is_active = 1 ORDER BY template_name ASC";
$templatesResult = $conn->query($templatesQuery);
$allTemplates = [];
while ($template = $templatesResult->fetch_assoc()) {
    $allTemplates[$template['template_name']] = $template;
}

// Build WHERE clause for filters
$whereConditions = [];
$params = [];
$types = '';

// Get all submissions for current user (or all if admin)
if (isAdmin()) {
    // Filter by template
    if (!empty($filterTemplate)) {
        $whereConditions[] = "t.template_name = ?";
        $params[] = $filterTemplate;
        $types .= 's';
    }
    
    // Filter by status
    if (!empty($filterStatus)) {
        $whereConditions[] = "s.status = ?";
        $params[] = $filterStatus;
        $types .= 's';
    }
    
    // Filter by date range
    if (!empty($filterDateFrom)) {
        $whereConditions[] = "s.submission_date >= ?";
        $params[] = $filterDateFrom;
        $types .= 's';
    }
    if (!empty($filterDateTo)) {
        $whereConditions[] = "s.submission_date <= ?";
        $params[] = $filterDateTo;
        $types .= 's';
    }
    
    // Search by audit number or vendor name
    if (!empty($searchQuery)) {
        $whereConditions[] = "(CONCAT(t.template_code, '-', LPAD(s.audit_number, 5, '0')) LIKE ? OR s.seller_name LIKE ?)";
        $searchParam = '%' . $searchQuery . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    $whereClause = '';
    if (count($whereConditions) > 0) {
        $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    }
    
    $query = "
        SELECT s.*, t.template_name, t.template_code, u.full_name as auditor_name
        FROM audit_submissions s 
        JOIN audit_templates t ON s.template_id = t.id 
        JOIN users u ON s.submitted_by = u.id
        $whereClause
        ORDER BY t.template_name ASC, s.audit_number ASC
    ";
    
    if (count($params) > 0) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
} else {
    $whereConditions[] = "s.submitted_by = ?";
    $params[] = $currentUser['id'];
    $types .= 'i';
    
    // Filter by template
    if (!empty($filterTemplate)) {
        $whereConditions[] = "t.template_name = ?";
        $params[] = $filterTemplate;
        $types .= 's';
    }
    
    // Filter by status
    if (!empty($filterStatus)) {
        $whereConditions[] = "s.status = ?";
        $params[] = $filterStatus;
        $types .= 's';
    }
    
    // Filter by date range
    if (!empty($filterDateFrom)) {
        $whereConditions[] = "s.submission_date >= ?";
        $params[] = $filterDateFrom;
        $types .= 's';
    }
    if (!empty($filterDateTo)) {
        $whereConditions[] = "s.submission_date <= ?";
        $params[] = $filterDateTo;
        $types .= 's';
    }
    
    // Search by audit number or vendor name
    if (!empty($searchQuery)) {
        $whereConditions[] = "(CONCAT(t.template_code, '-', LPAD(s.audit_number, 5, '0')) LIKE ? OR s.seller_name LIKE ?)";
        $searchParam = '%' . $searchQuery . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    $query = "
        SELECT s.*, t.template_name, t.template_code
        FROM audit_submissions s 
        JOIN audit_templates t ON s.template_id = t.id 
        $whereClause
        ORDER BY t.template_name ASC, s.audit_number ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Group submissions by template
$groupedSubmissions = [];
$totalFilteredCount = 0;
while ($row = $result->fetch_assoc()) {
    $templateName = $row['template_name'];
    if (!isset($groupedSubmissions[$templateName])) {
        $groupedSubmissions[$templateName] = [];
    }
    $groupedSubmissions[$templateName][] = $row;
    $totalFilteredCount++;
}

// Count total submissions (for filter counter)
if (isAdmin()) {
    $totalQuery = "SELECT COUNT(*) as total FROM audit_submissions";
} else {
    $totalQuery = "SELECT COUNT(*) as total FROM audit_submissions WHERE submitted_by = " . $currentUser['id'];
}
$totalResult = $conn->query($totalQuery);
$totalCount = $totalResult->fetch_assoc()['total'];

$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Daftar Audit</h1>
    <a href="create.php" class="btn btn-secondary"><i class="fas fa-plus"></i> Buat Audit Baru</a>
</div>

<!-- Search Card -->
<div class="card" style="margin-bottom: 20px; background: white;">
    <form method="GET" action="list.php" style="display: flex; gap: 10px; align-items: center;">
        <!-- Preserve filter parameters -->
        <?php if (!empty($filterTemplate)): ?>
        <input type="hidden" name="template" value="<?php echo htmlspecialchars($filterTemplate); ?>">
        <?php endif; ?>
        <?php if (!empty($filterStatus)): ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
        <?php endif; ?>
        <?php if (!empty($filterDateFrom)): ?>
        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
        <?php endif; ?>
        <?php if (!empty($filterDateTo)): ?>
        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
        <?php endif; ?>
        
        <div style="flex: 1; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
            <input type="text" 
                   name="search" 
                   class="form-control" 
                   placeholder="Cari berdasarkan Nomor Audit atau Nama Vendor..." 
                   value="<?php echo htmlspecialchars($searchQuery); ?>"
                   style="padding-left: 45px; height: 45px; font-size: 15px;">
        </div>
        <button type="submit" class="btn btn-primary" style="height: 45px; padding: 0 25px;">
            <i class="fas fa-search"></i> Cari
        </button>
        <?php if (!empty($searchQuery)): ?>
        <a href="list.php<?php 
            $params = [];
            if (!empty($filterTemplate)) $params[] = 'template=' . urlencode($filterTemplate);
            if (!empty($filterStatus)) $params[] = 'status=' . urlencode($filterStatus);
            if (!empty($filterDateFrom)) $params[] = 'date_from=' . urlencode($filterDateFrom);
            if (!empty($filterDateTo)) $params[] = 'date_to=' . urlencode($filterDateTo);
            echo !empty($params) ? '?' . implode('&', $params) : '';
        ?>" class="btn btn-secondary" style="height: 45px; padding: 0 25px;">
            <i class="fas fa-times"></i> Hapus Pencarian
        </a>
        <?php endif; ?>
    </form>
    
    <?php if (!empty($searchQuery)): ?>
    <div style="margin-top: 15px; padding: 12px; background: #f0f9ff; border-radius: 6px; border-left: 4px solid #3B82F6;">
        <i class="fas fa-search" style="color: #3B82F6;"></i>
        <span style="font-weight: 500; color: #1e40af;">Hasil pencarian untuk: "<?php echo htmlspecialchars($searchQuery); ?>"</span>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Card -->
<div class="card" style="margin-bottom: 25px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
    <h3 style="margin-bottom: 20px; color: #495057; font-size: 16px; font-weight: 600;">
        <i class="fas fa-filter"></i> Filter Audit
    </h3>
    
    <form method="GET" action="list.php" id="filterForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <!-- Filter Template -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #495057;">
                    <i class="fas fa-folder"></i> Template
                </label>
                <select name="template" class="form-control" style="width: 100%;">
                    <option value="">-- Semua Template --</option>
                    <?php foreach ($allTemplates as $templateName => $template): ?>
                    <option value="<?php echo htmlspecialchars($templateName); ?>" 
                            <?php echo ($filterTemplate === $templateName) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($templateName); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filter Status -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #495057;">
                    <i class="fas fa-tag"></i> Status
                </label>
                <select name="status" class="form-control" style="width: 100%;">
                    <option value="">-- Semua Status --</option>
                    <option value="draft" <?php echo ($filterStatus === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="submitted" <?php echo ($filterStatus === 'submitted') ? 'selected' : ''; ?>>Pending Review</option>
                    <option value="reviewed" <?php echo ($filterStatus === 'reviewed') ? 'selected' : ''; ?>>Direview</option>
                    <option value="approved" <?php echo ($filterStatus === 'approved') ? 'selected' : ''; ?>>Disetujui</option>
                    <option value="rejected" <?php echo ($filterStatus === 'rejected') ? 'selected' : ''; ?>>Ditolak</option>
                </select>
            </div>
            
            <!-- Filter Tanggal Dari -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #495057;">
                    <i class="fas fa-calendar"></i> Dari Tanggal
                </label>
                <input type="date" name="date_from" class="form-control" 
                       value="<?php echo htmlspecialchars($filterDateFrom); ?>" 
                       style="width: 100%;">
            </div>
            
            <!-- Filter Tanggal Sampai -->
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; color: #495057;">
                    <i class="fas fa-calendar"></i> Sampai Tanggal
                </label>
                <input type="date" name="date_to" class="form-control" 
                       value="<?php echo htmlspecialchars($filterDateTo); ?>" 
                       style="width: 100%;">
            </div>
        </div>
        
        <!-- Filter Actions -->
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="min-width: 120px;">
                <i class="fas fa-search"></i> Terapkan Filter
            </button>
            <a href="list.php" class="btn btn-secondary" style="min-width: 120px;">
                <i class="fas fa-redo"></i> Reset Filter
            </a>
        </div>
    </form>
    
    <!-- Filter Counter -->
    <?php if (!empty($filterTemplate) || !empty($filterStatus) || !empty($filterDateFrom) || !empty($filterDateTo)): ?>
    <div style="margin-top: 15px; padding: 12px; background: white; border-radius: 6px; border-left: 4px solid #3B82F6;">
        <i class="fas fa-info-circle" style="color: #3B82F6;"></i>
        <span style="font-weight: 500;">Menampilkan <?php echo $totalFilteredCount; ?> dari <?php echo $totalCount; ?> audit</span>
        <?php if (!empty($filterTemplate)): ?>
        <span class="badge" style="background: #3B82F6; color: white; margin-left: 10px;">
            Template: <?php echo htmlspecialchars($filterTemplate); ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($filterStatus)): ?>
        <span class="badge" style="background: #10B981; color: white; margin-left: 5px;">
            Status: <?php 
            $statusLabels = [
                'draft' => 'Draft',
                'submitted' => 'Pending Review',
                'reviewed' => 'Direview',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak'
            ];
            echo $statusLabels[$filterStatus] ?? $filterStatus;
            ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($filterDateFrom) || !empty($filterDateTo)): ?>
        <span class="badge" style="background: #F59E0B; color: white; margin-left: 5px;">
            Periode: <?php 
            if (!empty($filterDateFrom) && !empty($filterDateTo)) {
                echo formatDate($filterDateFrom) . ' - ' . formatDate($filterDateTo);
            } elseif (!empty($filterDateFrom)) {
                echo 'Dari ' . formatDate($filterDateFrom);
            } else {
                echo 'Sampai ' . formatDate($filterDateTo);
            }
            ?>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php 
// Check if any filter or search is active
$isFilterActive = !empty($filterTemplate) || !empty($filterStatus) || !empty($filterDateFrom) || !empty($filterDateTo) || !empty($searchQuery);

// Loop through all templates in fixed order
foreach ($templateOrder as $templateName): 
    // Check if template exists in database
    if (!isset($allTemplates[$templateName])) continue;
    
    $submissions = isset($groupedSubmissions[$templateName]) ? $groupedSubmissions[$templateName] : [];
    $auditCount = count($submissions);
    
    // If filter is active, only show templates with results
    if ($isFilterActive && $auditCount === 0) continue;
?>
<div class="card" style="margin-bottom: 30px;">
    <h3 style="background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: white; padding: 15px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; font-size: 18px;">
        <i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($templateName); ?>
        <span style="background: rgba(255,255,255,0.2); padding: 3px 12px; border-radius: 12px; font-size: 14px; margin-left: 10px;">
            <?php echo $auditCount; ?> audit
        </span>
    </h3>
    
    <?php if ($auditCount > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th width="120">Nomor Audit</th>
                <?php if (isAdmin()): ?>
                <th>Auditor</th>
                <?php endif; ?>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th width="200">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($submissions as $row): 
            ?>
            <tr>
                <td style="font-weight: 600; color: #3B82F6;"><?php 
                // Bersihkan template_code dari suffix _001 atau _nnn
                $shortCode = preg_replace('/_\d{3}$/', '', $row['template_code']);
                echo htmlspecialchars($shortCode) . '-' . str_pad($row['audit_number'], 5, '0', STR_PAD_LEFT); 
                ?></td>
                <?php if (isAdmin()): ?>
                <td><?php echo htmlspecialchars($row['auditor_name']); ?></td>
                <?php endif; ?>
                <td><?php echo formatDate($row['submission_date']); ?></td>
                <td>
                    <span class="badge badge-<?php echo $row['status']; ?>">
                        <?php 
                        $statusLabels = [
                            'draft' => 'Draft',
                            'submitted' => 'Pending Review',
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
                    <a href="delete.php?id=<?php echo $row['id']; ?>" 
                       class="btn btn-sm btn-delete" 
                       onclick="return confirm('Apakah Anda yakin ingin menghapus audit ini?')"
                       title="Hapus Audit">
                        <i class="fas fa-trash"></i> Hapus
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="padding: 40px; text-align: center; color: #6c757d; background: #f8f9fa; border-radius: 6px;">
        <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 15px;"></i>
        <p style="margin: 0; font-size: 15px;">Belum ada audit untuk kategori ini</p>
        <a href="select_type.php" class="btn btn-sm btn-primary" style="margin-top: 15px;">
            <i class="fas fa-plus"></i> Buat Audit Pertama
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php 
// Show message if filter/search is active but no results found
if ($isFilterActive && $totalFilteredCount === 0): 
?>
<div class="card" style="text-align: center; padding: 60px 40px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
    <i class="fas fa-search" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px;"></i>
    <h3 style="color: #64748b; margin-bottom: 10px; font-size: 20px;">Tidak Ada Hasil</h3>
    <p style="color: #94a3b8; margin-bottom: 25px; font-size: 15px;">
        <?php if (!empty($searchQuery)): ?>
        Tidak ditemukan audit dengan kata kunci "<?php echo htmlspecialchars($searchQuery); ?>".
        <?php else: ?>
        Tidak ditemukan audit yang sesuai dengan filter yang Anda terapkan.
        <?php endif; ?>
    </p>
    <a href="list.php" class="btn btn-primary">
        <i class="fas fa-redo"></i> <?php echo !empty($searchQuery) ? 'Hapus Pencarian & Lihat Semua' : 'Hapus Filter & Lihat Semua Audit'; ?>
    </a>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
