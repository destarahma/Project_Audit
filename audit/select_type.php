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
    <a href="../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<div class="audit-types-grid">
    <!-- Mix Oil Audit -->
    <?php if (isset($auditTypes['mix_oil'])): ?>
        <?php foreach ($auditTypes['mix_oil'] as $template): ?>
        <a href="create.php?template_id=<?php echo $template['id']; ?>" class="audit-type-card">
            <div class="audit-type-icon">
                <i class="fas fa-oil-can"></i>
            </div>
            <div class="audit-type-content">
                <h3>Audit Jual Beli Mix Oil</h3>
                <p>Self audit untuk proses penjualan dan pembelian mix oil</p>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Vendor Evaluation -->
    <?php if (isset($auditTypes['vendor_evaluation'])): ?>
        <?php foreach ($auditTypes['vendor_evaluation'] as $template): ?>
        <a href="create.php?template_id=<?php echo $template['id']; ?>" class="audit-type-card">
            <div class="audit-type-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="audit-type-content">
                <h3>Evaluasi Vendor</h3>
                <p>Self audit untuk mengevaluasi performa vendor/supplier</p>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Process Audit -->
    <?php if (isset($auditTypes['process_audit'])): ?>
        <?php foreach ($auditTypes['process_audit'] as $template): ?>
        <a href="create.php?template_id=<?php echo $template['id']; ?>" class="audit-type-card">
            <div class="audit-type-icon">
                <i class="fas fa-cogs"></i>
            </div>
            <div class="audit-type-content">
                <h3>Audit Proses Operasional</h3>
                <p>Self audit untuk proses operasional dan SOP internal</p>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Compliance Check -->
    <?php if (isset($auditTypes['compliance_check'])): ?>
        <?php foreach ($auditTypes['compliance_check'] as $template): ?>
        <a href="create.php?template_id=<?php echo $template['id']; ?>" class="audit-type-card">
            <div class="audit-type-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="audit-type-content">
                <h3>Kepatuhan Regulasi</h3>
                <p>Self audit untuk memastikan kepatuhan terhadap regulasi</p>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.audit-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
    margin-top: 24px;
}

.audit-type-card {
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 28px 24px;
    text-decoration: none;
    color: var(--text-color);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: flex-start;
    gap: 18px;
    position: relative;
    overflow: hidden;
}

.audit-type-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #597ef7;
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.audit-type-card:hover::before {
    transform: scaleY(1);
}

.audit-type-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(89, 126, 247, 0.2);
    border-color: rgba(89, 126, 247, 0.4);
}

.audit-type-icon {
    width: 60px;
    height: 60px;
    min-width: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    background: linear-gradient(135deg, #f0f5ff 0%, #d6e4ff 100%);
    color: #597ef7;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.audit-type-card:hover .audit-type-icon {
    transform: scale(1.1) rotate(5deg);
}

.audit-type-content {
    flex: 1;
}

.audit-type-content h3 {
    font-size: 17px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text-color);
}

.audit-type-content p {
    font-size: 14px;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.5;
}
</style>

<?php include '../includes/footer.php'; ?>
