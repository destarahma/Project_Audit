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
    // Get items for this section
    $itemStmt = $conn->prepare("
        SELECT * FROM template_items 
        WHERE section_id = ? 
        ORDER BY item_order
    ");
    $itemStmt->bind_param("i", $row['id']);
    $itemStmt->execute();
    $itemsResult = $itemStmt->get_result();
    $row['items'] = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $row['items'][] = $item;
    }
    $itemStmt->close();
    
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
    
    if ($_POST['action'] === 'update_item') {
        // Update item
        $itemId = (int)$_POST['item_id'];
        $itemText = sanitize($_POST['item_text']);
        $fieldType = sanitize($_POST['field_type']);
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $scoreValue = (int)$_POST['score_value'];
        
        $stmt = $conn->prepare("UPDATE template_items SET item_text = ?, field_type = ?, is_required = ?, score_value = ? WHERE id = ?");
        $stmt->bind_param("ssiii", $itemText, $fieldType, $isRequired, $scoreValue, $itemId);
        
        if ($stmt->execute()) {
            flashMessage('Item berhasil diupdate', 'success');
        } else {
            flashMessage('Gagal update item', 'danger');
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
    <h3>📑 Sections & Items</h3>
    <p style="color: #6c757d; font-size: 14px; margin-bottom: 20px;">
        Struktur checklist yang akan muncul di form audit. Klik section untuk melihat item-item di dalamnya.
    </p>
    
    <?php if (count($sections) > 0): ?>
    <div class="sections-container">
        <?php foreach ($sections as $section): ?>
        <div class="section-card" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 15px; overflow: hidden;">
            <div class="section-header" style="background: var(--primary-color); color: white; padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;"
                 onclick="toggleSection(<?php echo $section['id']; ?>)">
                <div>
                    <strong style="font-size: 16px;"><?php echo $section['section_order']; ?>. <?php echo htmlspecialchars($section['section_title']); ?></strong>
                    <span style="margin-left: 15px; opacity: 0.9; font-size: 13px;">
                        <i class="fas fa-list"></i> <?php echo $section['item_count']; ?> items
                    </span>
                </div>
                <i class="fas fa-chevron-down" id="icon-<?php echo $section['id']; ?>"></i>
            </div>
            
            <div class="section-content" id="section-<?php echo $section['id']; ?>" style="display: none; padding: 20px;">
                <?php if (count($section['items']) > 0): ?>
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th width="60px">Order</th>
                            <th>Item Text</th>
                            <th width="120px">Field Type</th>
                            <th width="80px">Required</th>
                            <th width="80px">Score</th>
                            <th width="100px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section['items'] as $item): ?>
                        <tr>
                            <td><?php echo $item['item_order']; ?></td>
                            <td><?php echo htmlspecialchars($item['item_text']); ?></td>
                            <td>
                                <span class="badge" style="background: 
                                    <?php 
                                    echo $item['field_type'] == 'radio' ? '#17a2b8' : 
                                        ($item['field_type'] == 'date' ? '#28a745' : 
                                        ($item['field_type'] == 'text' ? '#ffc107' : '#6c757d')); 
                                    ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                    <?php echo strtoupper($item['field_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($item['is_required']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle" style="color: #ccc;"></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['score_value']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="no-data">Belum ada item dalam section ini.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107;">
        <strong>ℹ️ Catatan:</strong> Untuk edit struktur sections & items, silakan edit langsung di database atau hubungi developer.
        Perubahan struktur template memerlukan penyesuaian di form audit.
    </div>
    
    <?php else: ?>
    <p class="no-data">Belum ada section dalam template ini.</p>
    <?php endif; ?>
</div>

<script>
function toggleSection(sectionId) {
    const content = document.getElementById('section-' + sectionId);
    const icon = document.getElementById('icon-' + sectionId);
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.className = 'fas fa-chevron-up';
    } else {
        content.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
    }
}

function editItem(item) {
    document.getElementById('editModal').style.display = 'block';
    document.getElementById('item_id').value = item.id;
    document.getElementById('item_order').value = item.item_order;
    document.getElementById('item_text').value = item.item_text;
    document.getElementById('field_type').value = item.field_type;
    document.getElementById('is_required').checked = item.is_required == 1;
    document.getElementById('score_value').value = item.score_value;
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<!-- Edit Item Modal -->
<div id="editModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: white; margin: 5% auto; padding: 0; width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="background: var(--primary-color); color: white; padding: 20px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="fas fa-edit"></i> Edit Item</h3>
            <button onclick="closeModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px;">&times;</button>
        </div>
        
        <form method="POST" style="padding: 25px;">
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="item_id" id="item_id">
            
            <div class="form-group">
                <label>Order</label>
                <input type="number" id="item_order" class="form-control" disabled style="background: #e9ecef;">
            </div>
            
            <div class="form-group">
                <label>Item Text <span style="color: red;">*</span></label>
                <textarea name="item_text" id="item_text" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Field Type <span style="color: red;">*</span></label>
                <select name="field_type" id="field_type" class="form-control" required>
                    <option value="radio">Radio (Ada/Tidak Ada)</option>
                    <option value="date">Date (Tanggal)</option>
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="textarea">Textarea</option>
                    <option value="checkbox">Checkbox</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Score Value</label>
                <input type="number" name="score_value" id="score_value" class="form-control" min="0" value="0">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_required" id="is_required" value="1">
                    Required (Wajib diisi)
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
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
