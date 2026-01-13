<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

$pageTitle = 'Pilih Jenis Self Audit';

// Get all active templates grouped by audit_type
$conn = getConnection();
$templates = $conn->query("
    SELECT id, template_name, template_code, audit_type, description 
    FROM audit_templates 
    WHERE is_active = 1 
    ORDER BY audit_type, template_name
");

$auditTypes = [];
while ($template = $templates->fetch_assoc()) {
    $auditTypes[$template['audit_type']][] = $template;
}

$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Pilih Jenis Self Audit</h1>
    <a href="../index.php" class="btn btn-secondary">← Kembali</a>
</div>

<div class="audit-types-grid">
    <!-- Mix Oil Audit -->
    <div class="audit-type-card">
        <div class="audit-type-icon" style="background: linear-gradient(135deg, #C41E3A 0%, #E63950 100%);">
            <span>🛢️</span>
        </div>
        <h3>Audit Jual Beli Mix Oil</h3>
        <p>Self audit untuk proses penjualan dan pembelian mix oil</p>
        <div class="audit-type-templates">
            <?php if (isset($auditTypes['mix_oil'])): ?>
                <?php foreach ($auditTypes['mix_oil'] as $template): ?>
                    <a href="create.php?template_id=<?php echo $template['id']; ?>" class="template-link">
                        <span>📝</span> <?php echo htmlspecialchars($template['template_name']); ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-template">Belum ada template</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vendor Evaluation -->
    <div class="audit-type-card">
        <div class="audit-type-icon" style="background: linear-gradient(135deg, #1890ff 0%, #40a9ff 100%);">
            <span>⭐</span>
        </div>
        <h3>Evaluasi Vendor</h3>
        <p>Self audit untuk mengevaluasi performa vendor/supplier</p>
        <div class="audit-type-templates">
            <?php if (isset($auditTypes['vendor_evaluation'])): ?>
                <?php foreach ($auditTypes['vendor_evaluation'] as $template): ?>
                    <a href="create.php?template_id=<?php echo $template['id']; ?>" class="template-link">
                        <span>📝</span> <?php echo htmlspecialchars($template['template_name']); ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-template">Belum ada template</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Process Audit -->
    <div class="audit-type-card">
        <div class="audit-type-icon" style="background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);">
            <span>⚙️</span>
        </div>
        <h3>Audit Proses Operasional</h3>
        <p>Self audit untuk proses operasional dan SOP internal</p>
        <div class="audit-type-templates">
            <?php if (isset($auditTypes['process_audit'])): ?>
                <?php foreach ($auditTypes['process_audit'] as $template): ?>
                    <a href="create.php?template_id=<?php echo $template['id']; ?>" class="template-link">
                        <span>📝</span> <?php echo htmlspecialchars($template['template_name']); ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-template">Belum ada template</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Compliance Check -->
    <div class="audit-type-card">
        <div class="audit-type-icon" style="background: linear-gradient(135deg, #fa8c16 0%, #ffa940 100%);">
            <span>📋</span>
        </div>
        <h3>Kepatuhan Regulasi</h3>
        <p>Self audit untuk memastikan kepatuhan terhadap regulasi</p>
        <div class="audit-type-templates">
            <?php if (isset($auditTypes['compliance_check'])): ?>
                <?php foreach ($auditTypes['compliance_check'] as $template): ?>
                    <a href="create.php?template_id=<?php echo $template['id']; ?>" class="template-link">
                        <span>📝</span> <?php echo htmlspecialchars($template['template_name']); ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-template">Belum ada template</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.audit-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-top: 24px;
}

.audit-type-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 24px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.audit-type-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

.audit-type-icon {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    margin-bottom: 16px;
}

.audit-type-card h3 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-color);
}

.audit-type-card > p {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 16px;
    line-height: 1.6;
}

.audit-type-templates {
    border-top: 1px solid var(--border-color);
    padding-top: 16px;
}

.template-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: var(--light-bg);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-color);
    margin-bottom: 8px;
    transition: all 0.2s;
    font-size: 14px;
}

.template-link:hover {
    background: var(--primary-color);
    color: white;
    transform: translateX(4px);
}

.template-link span {
    font-size: 18px;
}

.no-template {
    color: var(--text-muted);
    font-size: 13px;
    font-style: italic;
}
</style>

<?php include '../includes/footer.php'; ?>
