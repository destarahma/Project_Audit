<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    flashMessage('Akses ditolak', 'danger');
    redirect('index.php');
}

$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($templateId === 0) {
    flashMessage('Template tidak ditemukan', 'danger');
    redirect('admin/templates.php');
}

$conn = getConnection();

// Get template details
$stmt = $conn->prepare("SELECT * FROM audit_templates WHERE id = ?");
$stmt->bind_param("i", $templateId);
$stmt->execute();
$template = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$template) {
    flashMessage('Template tidak ditemukan', 'danger');
    redirect('admin/templates.php');
}

// Get sections with items
$stmt = $conn->prepare("
    SELECT ts.*, 
           ti.id as item_id,
           ti.item_order,
           ti.item_text,
           ti.field_type,
           ti.score_value,
           ti.is_required
    FROM template_sections ts
    LEFT JOIN template_items ti ON ts.id = ti.section_id
    WHERE ts.template_id = ?
    ORDER BY ts.section_order, ti.item_order
");
$stmt->bind_param("i", $templateId);
$stmt->execute();
$result = $stmt->get_result();

$sections = [];
while ($row = $result->fetch_assoc()) {
    $sectionId = $row['id'];
    if (!isset($sections[$sectionId])) {
        $sections[$sectionId] = [
            'id' => $row['id'],
            'section_order' => $row['section_order'],
            'section_title' => $row['section_title'],
            'items' => []
        ];
    }
    if ($row['item_id']) {
        $sections[$sectionId]['items'][] = [
            'id' => $row['item_id'],
            'item_order' => $row['item_order'],
            'item_text' => $row['item_text'],
            'field_type' => $row['field_type'],
            'score_value' => $row['score_value'],
            'is_required' => $row['is_required']
        ];
    }
}
$stmt->close();

// Get approval rules
$stmt = $conn->prepare("SELECT * FROM approval_rules WHERE template_id = ? ORDER BY approval_level, approval_category");
$stmt->bind_param("i", $templateId);
$stmt->execute();
$result = $stmt->get_result();
$approvalRules = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$pageTitle = 'Preview Template: ' . $template['template_name'];
include '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/excel-style.css">

<div class="page-header">
    <h1>Preview Template Audit</h1>
    <div>
        <a href="templates.php" class="btn btn-secondary">← Kembali</a>
        <a href="template_edit.php?id=<?php echo $templateId; ?>" class="btn btn-primary">Edit Template</a>
    </div>
</div>

<!-- Template Info -->
<div class="card excel-form">
    <div class="excel-header">
        <h2><?php echo htmlspecialchars($template['template_name']); ?></h2>
        <div style="margin-top: 10px; font-size: 14px; opacity: 0.9;">
            Kode: <?php echo htmlspecialchars($template['template_code']); ?> | 
            Status: <span class="badge badge-<?php echo $template['is_active'] ? 'approved' : 'rejected'; ?>">
                <?php echo $template['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
            </span>
        </div>
    </div>
    
    <?php if ($template['description']): ?>
    <div style="padding: 15px; background: #f8f9fa; border-left: 3px solid #007bff; margin: 15px;">
        <strong>Deskripsi:</strong><br>
        <?php echo nl2br(htmlspecialchars($template['description'])); ?>
    </div>
    <?php endif; ?>
</div>

<!-- Approval Rules -->
<?php if (count($approvalRules) > 0): ?>
<div class="card">
    <h3>🔐 Approval Routing Rules</h3>
    <p style="color: #6c757d; margin-bottom: 20px;">
        Approval otomatis berdasarkan nilai transaksi. Setiap level memerlukan approval dari 2 departemen.
    </p>
    
    <?php
    $rulesByLevel = [];
    foreach ($approvalRules as $rule) {
        $level = $rule['approval_level'];
        if (!isset($rulesByLevel[$level])) {
            $rulesByLevel[$level] = [];
        }
        $rulesByLevel[$level][] = $rule;
    }
    
    foreach ($rulesByLevel as $level => $rules):
        $procRule = null;
        $finRule = null;
        
        foreach ($rules as $rule) {
            if ($rule['approval_category'] == 'Procurement') $procRule = $rule;
            if ($rule['approval_category'] == 'Finance') $finRule = $rule;
        }
        
        $bgColor = $level == 1 ? '#d1ecf1' : ($level == 2 ? '#fff3cd' : '#f8d7da');
        $borderColor = $level == 1 ? '#0c5460' : ($level == 2 ? '#856404' : '#721c24');
    ?>
    
    <div style="background: <?php echo $bgColor; ?>; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid <?php echo $borderColor; ?>;">
        <h4 style="color: <?php echo $borderColor; ?>; margin-bottom: 10px;">
            Level <?php echo $level; ?>: 
            <?php 
            $operator = $procRule['condition_operator'];
            $value = $procRule['condition_value'];
            
            if ($operator == '<=') {
                echo '<= Rp ' . number_format($value, 0, ',', '.');
            } elseif ($operator == 'between') {
                list($min, $max) = explode('-', $value);
                echo 'Rp ' . number_format($min, 0, ',', '.') . ' - Rp ' . number_format($max, 0, ',', '.');
            } elseif ($operator == '>') {
                echo '> Rp ' . number_format($value, 0, ',', '.');
            }
            ?>
        </h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <?php if ($procRule): ?>
            <div style="background: white; padding: 12px; border-radius: 6px;">
                <strong style="color: #495057;">🏢 Procurement</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($procRule['required_approval']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($finRule): ?>
            <div style="background: white; padding: 12px; border-radius: 6px;">
                <strong style="color: #495057;">💰 Finance</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($finRule['required_approval']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Sections Preview -->
<?php foreach ($sections as $section): ?>
<div class="card excel-form">
    <div class="excel-section">
        <h3 class="excel-section-header">
            Section <?php echo $section['section_order']; ?>: <?php echo htmlspecialchars($section['section_title']); ?>
        </h3>
        
        <?php if (count($section['items']) > 0): ?>
        <table class="excel-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="45%">Item</th>
                    <th width="15%">Tipe Field</th>
                    <th width="10%">Bobot</th>
                    <th width="10%">Wajib</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $itemNo = 1;
                foreach ($section['items'] as $item): 
                ?>
                <tr>
                    <td class="excel-cell-center"><?php echo $itemNo++; ?></td>
                    <td><?php echo htmlspecialchars($item['item_text']); ?></td>
                    <td>
                        <span class="badge" style="background: #6c757d;">
                            <?php echo strtoupper($item['field_type']); ?>
                        </span>
                    </td>
                    <td class="excel-cell-center"><?php echo $item['score_value']; ?></td>
                    <td class="excel-cell-center">
                        <?php if ($item['is_required']): ?>
                        <span style="color: #dc3545;">★</span>
                        <?php else: ?>
                        <span style="color: #ccc;">☆</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="no-data">Belum ada item di section ini.</p>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (count($sections) === 0): ?>
<div class="card">
    <p class="no-data">Template ini belum memiliki section. Silakan tambahkan section terlebih dahulu.</p>
    <a href="template_edit.php?id=<?php echo $templateId; ?>" class="btn btn-primary">➕ Tambah Section</a>
</div>
<?php endif; ?>

<div class="card">
    <h3>📊 Statistik Template</h3>
    <table class="table">
        <tr>
            <td><strong>Total Sections:</strong></td>
            <td><?php echo count($sections); ?> section</td>
        </tr>
        <tr>
            <td><strong>Total Items:</strong></td>
            <td>
                <?php 
                $totalItems = 0;
                foreach ($sections as $section) {
                    $totalItems += count($section['items']);
                }
                echo $totalItems . ' items';
                ?>
            </td>
        </tr>
        <tr>
            <td><strong>Approval Levels:</strong></td>
            <td><?php echo count($rulesByLevel ?? []); ?> level</td>
        </tr>
        <tr>
            <td><strong>Max Score:</strong></td>
            <td><?php echo $template['max_score']; ?> points</td>
        </tr>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
