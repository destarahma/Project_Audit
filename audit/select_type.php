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
    <!-- Card 1: Self Audit Jual Beli Mix Oil -->
    <a href="create.php?template_id=1" class="audit-type-card">
        <div class="audit-type-icon">
            <i class="fas fa-oil-can"></i>
        </div>
        <div class="audit-type-content">
            <h3>Self Audit : Jual Beli Mix Oil</h3>
            <p>Self audit untuk proses penjualan dan pembelian mix oil</p>
        </div>
    </a>

    <!-- Card 2: Self Audit Jual Barbes -->
    <a href="create.php?template_id=5" class="audit-type-card">
        <div class="audit-type-icon">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="audit-type-content">
            <h3>Self Audit : Jual Barbes</h3>
            <p>Self audit untuk proses penjualan Barbes</p>
        </div>
    </a>

    <!-- Card 3: Self Audit Jual Aset -->
    <a href="create.php?template_id=6" class="audit-type-card">
        <div class="audit-type-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="audit-type-content">
            <h3>Self Audit : Jual Aset</h3>
            <p>Self audit untuk proses penjualan aset</p>
        </div>
    </a>

    <!-- Card 4: PO Non OA -->
    <a href="create.php?template_id=10" class="audit-type-card">
        <div class="audit-type-icon">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div class="audit-type-content">
            <h3>PO Non OA</h3>
            <p>Purchase Order Non OA</p>
        </div>
    </a>

    <!-- Card 5: PO Tagging OA -->
    <a href="create.php?template_id=9" class="audit-type-card">
        <div class="audit-type-icon">
            <i class="fas fa-tags"></i>
        </div>
        <div class="audit-type-content">
            <h3>PO Tagging OA</h3>
            <p>Purchase Order dengan Tagging OA</p>
        </div>
    </a>
</div>

<style>
.audit-types-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
    margin-top: 24px;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

/* 3 kartu pertama - masing-masing 2 kolom dari 6 */
.audit-types-grid .audit-type-card:nth-child(1),
.audit-types-grid .audit-type-card:nth-child(2),
.audit-types-grid .audit-type-card:nth-child(3) {
    grid-column: span 2;
}

/* 2 kartu bawah - centered, masing-masing 2 kolom, mulai dari kolom 2 */
.audit-types-grid .audit-type-card:nth-child(4) {
    grid-column: 2 / 4;
}

.audit-types-grid .audit-type-card:nth-child(5) {
    grid-column: 4 / 6;
}

@media (max-width: 1024px) {
    .audit-types-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .audit-types-grid .audit-type-card:nth-child(1),
    .audit-types-grid .audit-type-card:nth-child(2),
    .audit-types-grid .audit-type-card:nth-child(3),
    .audit-types-grid .audit-type-card:nth-child(4),
    .audit-types-grid .audit-type-card:nth-child(5) {
        grid-column: span 1;
    }
}

@media (max-width: 768px) {
    .audit-types-grid {
        grid-template-columns: 1fr;
    }
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
