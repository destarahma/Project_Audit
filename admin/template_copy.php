<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    flashMessage('Akses ditolak', 'danger');
    redirect('index.php');
}

$pageTitle = 'Copy Template Audit';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceTemplateId = (int)$_POST['source_template_id'];
    $newTemplateName = sanitize($_POST['new_template_name']);
    $newTemplateCode = sanitize($_POST['new_template_code']);
    $newDescription = sanitize($_POST['new_description']);
    
    $conn = getConnection();
    $conn->begin_transaction();
    
    try {
        // Get source template
        $stmt = $conn->prepare("SELECT * FROM audit_templates WHERE id = ?");
        $stmt->bind_param("i", $sourceTemplateId);
        $stmt->execute();
        $sourceTemplate = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$sourceTemplate) {
            throw new Exception('Template sumber tidak ditemukan');
        }
        
        // Create new template
        $stmt = $conn->prepare("
            INSERT INTO audit_templates (template_name, template_code, audit_type, description, scoring_enabled, max_score, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssiii",
            $newTemplateName,
            $newTemplateCode,
            $sourceTemplate['audit_type'],
            $newDescription,
            $sourceTemplate['scoring_enabled'],
            $sourceTemplate['max_score'],
            $sourceTemplate['is_active'],
            $_SESSION['user_id']
        );
        $stmt->execute();
        $newTemplateId = $stmt->insert_id;
        $stmt->close();
        
        // Copy sections
        $stmt = $conn->prepare("SELECT * FROM template_sections WHERE template_id = ? ORDER BY section_order");
        $stmt->bind_param("i", $sourceTemplateId);
        $stmt->execute();
        $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($sections as $section) {
            // Insert new section
            $stmt = $conn->prepare("
                INSERT INTO template_sections (template_id, section_order, section_title)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $newTemplateId, $section['section_order'], $section['section_title']);
            $stmt->execute();
            $newSectionId = $stmt->insert_id;
            $stmt->close();
            
            // Copy items for this section
            $stmt = $conn->prepare("SELECT * FROM template_items WHERE section_id = ? ORDER BY item_order");
            $stmt->bind_param("i", $section['id']);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            foreach ($items as $item) {
                $stmt = $conn->prepare("
                    INSERT INTO template_items (section_id, item_order, item_text, field_type, field_options, score_value, is_required)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iisssii",
                    $newSectionId,
                    $item['item_order'],
                    $item['item_text'],
                    $item['field_type'],
                    $item['field_options'],
                    $item['score_value'],
                    $item['is_required']
                );
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // Copy approval rules if exists
        $stmt = $conn->prepare("SELECT * FROM approval_rules WHERE template_id = ?");
        $stmt->bind_param("i", $sourceTemplateId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $approvalRules = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($approvalRules as $rule) {
                $stmtRule = $conn->prepare("
                    INSERT INTO approval_rules (template_id, approval_level, approval_category, min_score, max_score, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmtRule->bind_param(
                    "iisdds",
                    $newTemplateId,
                    $rule['approval_level'],
                    $rule['approval_category'],
                    $rule['min_score'],
                    $rule['max_score'],
                    $rule['status']
                );
                $stmtRule->execute();
                $stmtRule->close();
            }
        }
        $stmt->close();
        
        $conn->commit();
        flashMessage('Template berhasil dicopy sebagai "' . $newTemplateName . '"', 'success');
        redirect('admin/template_edit.php?id=' . $newTemplateId);
        
    } catch (Exception $e) {
        $conn->rollback();
        flashMessage('Gagal copy template: ' . $e->getMessage(), 'danger');
    }
    
    $conn->close();
}

// Get all templates
$conn = getConnection();
$templates = $conn->query("SELECT * FROM audit_templates ORDER BY template_name");
$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Copy Template Audit</h1>
    <a href="templates.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<div class="card">
    <h3>Copy Template</h3>
    <form method="POST" action="">
        <div class="form-group">
            <label for="source_template_id">Template Sumber *</label>
            <select name="source_template_id" id="source_template_id" class="form-control" required>
                <option value="">-- Pilih Template --</option>
                <?php while ($template = $templates->fetch_assoc()): ?>
                <option value="<?php echo $template['id']; ?>" 
                    <?php echo (isset($_GET['from']) && $_GET['from'] == $template['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($template['template_name']); ?> (<?php echo $template['template_code']; ?>)
                </option>
                <?php endwhile; ?>
            </select>
            <small class="form-text">Pilih template yang akan dicopy strukturnya</small>
        </div>
        
        <div class="form-group">
            <label for="new_template_name">Nama Template Baru *</label>
            <input type="text" name="new_template_name" id="new_template_name" class="form-control" 
                   value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>" required>
            <small class="form-text">Masukkan nama untuk template baru</small>
        </div>
        
        <div class="form-group">
            <label for="new_template_code">Kode Template *</label>
            <input type="text" name="new_template_code" id="new_template_code" class="form-control" 
                   pattern="[A-Z0-9_]+" required>
            <small class="form-text">Contoh: BARBES_001 (gunakan huruf besar, angka, dan underscore)</small>
        </div>
        
        <div class="form-group">
            <label for="new_description">Deskripsi</label>
            <textarea name="new_description" id="new_description" class="form-control" rows="3"></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-copy"></i> Copy Template
            </button>
            <a href="templates.php" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<div class="card">
    <h3>Informasi</h3>
    <ul>
        <li>Script ini akan mengcopy seluruh struktur template termasuk sections, items, dan approval rules</li>
        <li>Template baru akan memiliki struktur yang sama persis dengan template sumber</li>
        <li>Anda bisa mengedit template baru setelah proses copy selesai</li>
        <li>Kode template harus unik dan belum digunakan</li>
    </ul>
</div>

<?php include '../includes/footer.php'; ?>
