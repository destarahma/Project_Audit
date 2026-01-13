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

// Get approval rules for this template
$approvalRules = [];
$stmt = $conn->prepare("SELECT * FROM approval_rules WHERE template_id = ? ORDER BY approval_level, approval_category");
$stmt->bind_param("i", $templateId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $approvalRules[] = $row;
}
$stmt->close();

// Get sections and items
$sections = [];
$stmt = $conn->prepare("
    SELECT ts.*, 
           (SELECT COUNT(*) FROM template_items WHERE section_id = ts.id) as item_count
    FROM template_sections ts
    WHERE ts.template_id = ?
    ORDER BY ts.section_order
");
$stmt->bind_param("i", $templateId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_basic') {
        // Update basic template info
        $templateName = sanitize($_POST['template_name']);
        $description = sanitize($_POST['description']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE audit_templates SET template_name = ?, description = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssii", $templateName, $description, $isActive, $templateId);
        
        if ($stmt->execute()) {
            flashMessage('Template berhasil diupdate', 'success');
        } else {
            flashMessage('Gagal update template', 'danger');
        }
        $stmt->close();
        redirect('admin/template_edit.php?id=' . $templateId);
    }
    
    if ($_POST['action'] === 'update_approval') {
        // Delete existing rules
        $stmt = $conn->prepare("DELETE FROM approval_rules WHERE template_id = ?");
        $stmt->bind_param("i", $templateId);
        $stmt->execute();
        $stmt->close();
        
        // Insert new rules
        if (isset($_POST['approval_rules']) && is_array($_POST['approval_rules'])) {
            $stmt = $conn->prepare("
                INSERT INTO approval_rules 
                (template_id, rule_name, condition_operator, condition_value, required_approval, approval_category, approval_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['approval_rules'] as $rule) {
                $stmt->bind_param(
                    "isssssi",
                    $templateId,
                    $rule['rule_name'],
                    $rule['condition_operator'],
                    $rule['condition_value'],
                    $rule['required_approval'],
                    $rule['approval_category'],
                    $rule['approval_level']
                );
                $stmt->execute();
            }
            $stmt->close();
        }
        
        flashMessage('Approval rules berhasil diupdate', 'success');
        redirect('admin/template_edit.php?id=' . $templateId);
    }
}

$pageTitle = 'Edit Template: ' . $template['template_name'];
include '../includes/header.php';
?>

<div class="page-header">
    <h1>Edit Template Audit</h1>
    <div>
        <a href="templates.php" class="btn btn-secondary">← Kembali</a>
        <a href="template_view.php?id=<?php echo $templateId; ?>" class="btn">👁️ Preview Template</a>
    </div>
</div>

<!-- Basic Information -->
<div class="card">
    <h3>📋 Informasi Dasar</h3>
    <form method="POST">
        <input type="hidden" name="action" value="update_basic">
        
        <div class="form-group">
            <label>Nama Template <span style="color: red;">*</span></label>
            <input type="text" name="template_name" class="form-control" 
                   value="<?php echo htmlspecialchars($template['template_name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Kode Template</label>
            <input type="text" class="form-control" 
                   value="<?php echo htmlspecialchars($template['template_code']); ?>" disabled>
            <small class="form-text">Kode tidak dapat diubah</small>
        </div>
        
        <div class="form-group">
            <label>Deskripsi</label>
            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($template['description']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?php echo $template['is_active'] ? 'checked' : ''; ?>>
                Template Aktif
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
    </form>
</div>

<!-- Sections Overview -->
<div class="card">
    <h3>📑 Section & Items</h3>
    <?php if (count($sections) > 0): ?>
    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Section</th>
                <th>Jumlah Item</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sections as $section): ?>
            <tr>
                <td><?php echo $section['section_order']; ?></td>
                <td><?php echo htmlspecialchars($section['section_title']); ?></td>
                <td><span class="badge"><?php echo $section['item_count']; ?> items</span></td>
                <td>
                    <a href="section_edit.php?id=<?php echo $section['id']; ?>" class="btn btn-sm btn-primary">✏️ Edit Section</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="section_add.php?template_id=<?php echo $templateId; ?>" class="btn btn-primary">➕ Tambah Section Baru</a>
    <?php else: ?>
    <p class="no-data">Belum ada section.</p>
    <a href="section_add.php?template_id=<?php echo $templateId; ?>" class="btn btn-primary">➕ Tambah Section Pertama</a>
    <?php endif; ?>
</div>

<!-- Approval Rules -->
<div class="card">
    <h3>🔐 Approval Rules</h3>
    <p style="color: #6c757d; font-size: 14px; margin-bottom: 20px;">
        Atur approval routing otomatis berdasarkan nilai transaksi. Setiap level memerlukan approval dari Procurement dan Finance.
    </p>
    
    <form method="POST" id="approvalForm">
        <input type="hidden" name="action" value="update_approval">
        
        <div id="approvalRulesContainer">
            <?php 
            // Group by level
            $rulesByLevel = [];
            foreach ($approvalRules as $rule) {
                $level = $rule['approval_level'];
                if (!isset($rulesByLevel[$level])) {
                    $rulesByLevel[$level] = [];
                }
                $rulesByLevel[$level][] = $rule;
            }
            
            // If no rules, create default
            if (empty($rulesByLevel)) {
                $rulesByLevel = [
                    1 => [
                        ['rule_name' => 'Procurement <= 50 juta', 'condition_operator' => '<=', 'condition_value' => '50000000', 'required_approval' => 'Local DS Mgr', 'approval_category' => 'Procurement', 'approval_level' => 1],
                        ['rule_name' => 'Finance <= 50 juta', 'condition_operator' => '<=', 'condition_value' => '50000000', 'required_approval' => 'Ref Con', 'approval_category' => 'Finance', 'approval_level' => 1]
                    ],
                    2 => [
                        ['rule_name' => 'Procurement 50-300 juta', 'condition_operator' => 'between', 'condition_value' => '50000000-300000000', 'required_approval' => 'BP DS Agri & Food', 'approval_category' => 'Procurement', 'approval_level' => 2],
                        ['rule_name' => 'Finance 50-300 juta', 'condition_operator' => 'between', 'condition_value' => '50000000-300000000', 'required_approval' => 'Head of Ops Controller', 'approval_category' => 'Finance', 'approval_level' => 2]
                    ],
                    3 => [
                        ['rule_name' => 'Procurement > 300 juta', 'condition_operator' => '>', 'condition_value' => '300000000', 'required_approval' => 'Head of BP US & DS Proc', 'approval_category' => 'Procurement', 'approval_level' => 3],
                        ['rule_name' => 'Finance > 300 juta', 'condition_operator' => '>', 'condition_value' => '300000000', 'required_approval' => 'DS BU CFO', 'approval_category' => 'Finance', 'approval_level' => 3]
                    ]
                ];
            }
            
            $levelIndex = 0;
            foreach ($rulesByLevel as $level => $rules):
                $procRule = null;
                $finRule = null;
                
                foreach ($rules as $rule) {
                    if ($rule['approval_category'] == 'Procurement') $procRule = $rule;
                    if ($rule['approval_category'] == 'Finance') $finRule = $rule;
                }
            ?>
            
            <div class="approval-level" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid <?php echo $level == 1 ? '#17a2b8' : ($level == 2 ? '#ffc107' : '#dc3545'); ?>;">
                <h4 style="color: <?php echo $level == 1 ? '#17a2b8' : ($level == 2 ? '#ffc107' : '#dc3545'); ?>; margin-bottom: 15px;">
                    Level <?php echo $level; ?>
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <!-- Procurement -->
                    <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
                        <h5 style="color: #495057; margin-bottom: 10px;">🏢 Procurement</h5>
                        
                        <input type="hidden" name="approval_rules[<?php echo $levelIndex; ?>][approval_category]" value="Procurement">
                        <input type="hidden" name="approval_rules[<?php echo $levelIndex; ?>][approval_level]" value="<?php echo $level; ?>">
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Nama Rule</label>
                            <input type="text" name="approval_rules[<?php echo $levelIndex; ?>][rule_name]" class="form-control" 
                                   value="<?php echo htmlspecialchars($procRule['rule_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Operator</label>
                            <select name="approval_rules[<?php echo $levelIndex; ?>][condition_operator]" class="form-control" required>
                                <option value="<=" <?php echo ($procRule['condition_operator'] ?? '') == '<=' ? 'selected' : ''; ?>><=</option>
                                <option value="<" <?php echo ($procRule['condition_operator'] ?? '') == '<' ? 'selected' : ''; ?>><</option>
                                <option value="between" <?php echo ($procRule['condition_operator'] ?? '') == 'between' ? 'selected' : ''; ?>>Between</option>
                                <option value=">" <?php echo ($procRule['condition_operator'] ?? '') == '>' ? 'selected' : ''; ?>>></option>
                                <option value=">=" <?php echo ($procRule['condition_operator'] ?? '') == '>=' ? 'selected' : ''; ?>>>=</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Nilai (Rp)</label>
                            <input type="text" name="approval_rules[<?php echo $levelIndex; ?>][condition_value]" class="form-control" 
                                   value="<?php echo htmlspecialchars($procRule['condition_value'] ?? ''); ?>" 
                                   placeholder="50000000 atau 50000000-300000000" required>
                            <small class="form-text">Untuk between, gunakan format: min-max</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Approval By</label>
                            <input type="text" name="approval_rules[<?php echo $levelIndex; ?>][required_approval]" class="form-control" 
                                   value="<?php echo htmlspecialchars($procRule['required_approval'] ?? ''); ?>" 
                                   placeholder="Nama/Jabatan Approver" required>
                        </div>
                    </div>
                    
                    <?php $levelIndex++; ?>
                    
                    <!-- Finance -->
                    <div style="background: white; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6;">
                        <h5 style="color: #495057; margin-bottom: 10px;">💰 Finance</h5>
                        
                        <input type="hidden" name="approval_rules[<?php echo $levelIndex; ?>][approval_category]" value="Finance">
                        <input type="hidden" name="approval_rules[<?php echo $levelIndex; ?>][approval_level]" value="<?php echo $level; ?>">
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Nama Rule</label>
                            <input type="text" name="approval_rules[<?php echo $levelIndex; ?>][rule_name]" class="form-control" 
                                   value="<?php echo htmlspecialchars($finRule['rule_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Operator</label>
                            <select name="approval_rules[<?php echo $levelIndex; ?>][condition_operator]" class="form-control" required>
                                <option value="<=" <?php echo ($finRule['condition_operator'] ?? '') == '<=' ? 'selected' : ''; ?>><=</option>
                                <option value="<" <?php echo ($finRule['condition_operator'] ?? '') == '<' ? 'selected' : ''; ?>><</option>
                                <option value="between" <?php echo ($finRule['condition_operator'] ?? '') == 'between' ? 'selected' : ''; ?>>Between</option>
                                <option value=">" <?php echo ($finRule['condition_operator'] ?? '') == '>' ? 'selected' : ''; ?>>></option>
                                <option value=">=" <?php echo ($finRule['condition_operator'] ?? '') == '>=' ? 'selected' : ''; ?>>>=</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Nilai (Rp)</label>
                            <input type="text" name="approval_rules[<?php echo $levelIndex; ?>][condition_value]" class="form-control" 
                                   value="<?php echo htmlspecialchars($finRule['condition_value'] ?? ''); ?>" 
                                   placeholder="50000000 atau 50000000-300000000" required>
                            <small class="form-text">Untuk between, gunakan format: min-max</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 12px; color: #6c757d;">Approval By</label>
                            <input type="text" name="approval_rules[<?php echo $levelIndex; ?>][required_approval]" class="form-control" 
                                   value="<?php echo htmlspecialchars($finRule['required_approval'] ?? ''); ?>" 
                                   placeholder="Nama/Jabatan Approver" required>
                        </div>
                    </div>
                    
                    <?php $levelIndex++; ?>
                </div>
            </div>
            
            <?php endforeach; ?>
        </div>
        
        <button type="submit" class="btn btn-success">💾 Simpan Approval Rules</button>
    </form>
</div>

<div class="card">
    <h3>ℹ️ Informasi</h3>
    <ul>
        <li><strong>Informasi Dasar:</strong> Nama dan status template</li>
        <li><strong>Sections & Items:</strong> Checklist yang akan muncul di form audit</li>
        <li><strong>Approval Rules:</strong> Aturan otomatis untuk menentukan siapa yang harus approve berdasarkan nilai transaksi</li>
        <li>Setiap level memerlukan 2 approval: Procurement dan Finance</li>
    </ul>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>
