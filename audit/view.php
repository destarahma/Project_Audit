<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) {
    redirect('audit/list.php');
}

$submissionId = (int)$_GET['id'];
$currentUser = getCurrentUser();

$conn = getConnection();

// Get submission details
$stmt = $conn->prepare("
    SELECT s.*, t.template_name, t.template_code, t.max_score, u.full_name as auditor_name
    FROM audit_submissions s
    JOIN audit_templates t ON s.template_id = t.id
    JOIN users u ON s.submitted_by = u.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    flashMessage('Audit tidak ditemukan', 'danger');
    redirect('audit/list.php');
}

// Check permission
if (!isAdmin() && $submission['submitted_by'] != $currentUser['id']) {
    flashMessage('Anda tidak memiliki akses ke audit ini', 'danger');
    redirect('audit/list.php');
}

// Get template sections and items with responses
$stmt = $conn->prepare("
    SELECT 
        ts.id as section_id, 
        ts.section_order, 
        ts.section_title,
        ti.id as item_id,
        ti.item_order,
        ti.item_text,
        ti.field_type,
        ti.score_value,
        ar.response_value
    FROM template_sections ts
    JOIN template_items ti ON ts.id = ti.section_id
    LEFT JOIN audit_responses ar ON ti.id = ar.item_id AND ar.submission_id = ?
    WHERE ts.template_id = ?
    ORDER BY ts.section_order, ti.item_order
");
$stmt->bind_param("ii", $submissionId, $submission['template_id']);
$stmt->execute();
$result = $stmt->get_result();

$sections = [];
while ($row = $result->fetch_assoc()) {
    $sectionId = $row['section_id'];
    if (!isset($sections[$sectionId])) {
        $sections[$sectionId] = [
            'section_order' => $row['section_order'],
            'section_title' => $row['section_title'],
            'items' => []
        ];
    }
    $sections[$sectionId]['items'][] = $row;
}

$conn->close();

// Include Business Logic untuk validasi otomatis
require_once '../includes/business_logic.php';
$bl = getBusinessLogic();

// Hitung validasi otomatis (hanya untuk Mix Oil)
$businessValidation = null;
if ($submission['template_code'] === 'MIX_OIL') {
    $totalPrice = floatval($submission['total_price']);
    $quantity = floatval($submission['quantity']);
    
    // Get payment data dari audit_responses (jika ada)
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT response_value FROM audit_responses WHERE submission_id = ? AND item_id IN (SELECT id FROM template_items WHERE item_text LIKE '%DP%' OR item_text LIKE '%pembayaran%')");
    $stmt->bind_param("i", $submissionId);
    $stmt->execute();
    $paymentResult = $stmt->get_result();
    
    $dpAmount = 0;
    $totalPaid = 0;
    while ($payment = $paymentResult->fetch_assoc()) {
        $amount = floatval($payment['response_value']);
        if ($amount > 0) {
            if ($dpAmount == 0) {
                $dpAmount = $amount;
            }
            $totalPaid += $amount;
        }
    }
    $conn->close();
    
    // Validasi business logic
    $businessValidation = [
        'approval_required' => $bl->getRequiredApprovals($totalPrice, 1),
        'dp_valid' => $bl->validateDP($dpAmount, $totalPrice),
        'payment_complete' => $bl->validatePaymentComplete($totalPaid, $totalPrice),
        'dp_percentage' => $totalPrice > 0 ? round(($dpAmount / $totalPrice) * 100, 1) : 0,
        'payment_percentage' => $totalPrice > 0 ? round(($totalPaid / $totalPrice) * 100, 1) : 0,
        'total_price' => $totalPrice,
        'dp_amount' => $dpAmount,
        'total_paid' => $totalPaid
    ];
}

$pageTitle = 'Detail Audit - ' . $submission['template_name'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/excel-style.css">
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="page-header no-print">
    <h1>Detail Audit</h1>
    <div>
        <?php if ($submission['status'] === 'draft'): ?>
        <a href="edit.php?id=<?php echo $submissionId; ?>" class="btn btn-primary">✏️ Edit</a>
        <?php endif; ?>
        <a href="list.php" class="btn btn-secondary">← Kembali</a>
        <a href="download_pdf.php?id=<?php echo $submissionId; ?>" class="btn btn-success" target="_blank">📥 Download PDF</a>
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Cetak</button>
    </div>
</div>

<div class="card excel-form">
    <div class="excel-header">
        <h2><?php echo htmlspecialchars($submission['template_name']); ?></h2>
        <div style="margin-top: 10px; font-size: 14px; opacity: 0.9;">
            Nomor: AUD-<?php echo str_pad($submission['id'], 5, '0', STR_PAD_LEFT); ?> | 
            Status: <?php 
            $statusLabels = [
                'draft' => 'Draft',
                'submitted' => 'Disubmit',
                'reviewed' => 'Direview',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak'
            ];
            echo $statusLabels[$submission['status']] ?? $submission['status'];
            ?>
        </div>
    </div>
    
    <!-- Score Card -->
    <?php if ($submission['total_score'] > 0 || $submission['percentage_score'] > 0): ?>
    <div class="excel-score-card">
        <div class="excel-score-item">
            <div class="excel-score-label">TOTAL SKOR</div>
            <div class="excel-score-value"><?php echo $submission['total_score']; ?> / <?php echo $submission['max_score']; ?></div>
            <div style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:5px;">
                (Compliance Checklist Score)
            </div>
        </div>
        <div class="excel-score-item">
            <div class="excel-score-label">PERSENTASE</div>
            <div class="excel-score-value"><?php echo number_format($submission['percentage_score'], 1); ?>%</div>
            <div style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:5px;">
                (Item Completion Rate)
            </div>
        </div>
        <div class="excel-score-item">
            <div class="excel-score-label">STATUS AUDIT</div>
            <?php 
            // Status berdasarkan business logic, bukan hanya persentase
            $isBusinessValid = $businessValidation && 
                               $businessValidation['dp_valid'] && 
                               $businessValidation['payment_complete'];
            
            if ($isBusinessValid) {
                $displayStatus = 'APPROVED';
                $statusClass = 'status-approved';
            } elseif ($submission['percentage_score'] >= 80) {
                $displayStatus = 'Baik - Perlu Review';
                $statusClass = 'status-baik';
            } elseif ($submission['percentage_score'] >= 60) {
                $displayStatus = 'Cukup';
                $statusClass = 'status-cukup';
            } else {
                $displayStatus = 'Perlu Perbaikan';
                $statusClass = 'status-perlu-perbaikan';
            }
            ?>
            <div class="excel-score-status <?php echo $statusClass; ?>"><?php echo $displayStatus; ?></div>
            <?php if ($isBusinessValid): ?>
            <div style="font-size:11px;color:rgba(255,255,255,0.9);margin-top:8px;">
                ✓ Business Logic Validated
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Business Logic Validation Panel (untuk Mix Oil) -->
    <?php if ($businessValidation): ?>
    <div class="business-validation-panel">
        <div class="validation-header">
            <h3>📋 Validasi Otomatis Business Logic</h3>
        </div>
        
        <div class="validation-grid">
            <!-- Approval Routing -->
            <div class="validation-card">
                <div class="validation-title">
                    <span class="icon">🔐</span>
                    <strong>Approval QCF Required</strong>
                </div>
                <div class="validation-content">
                    <?php 
                    $totalPrice = $businessValidation['total_price'];
                    
                    // Determine approval level and badge
                    if ($totalPrice < 50000000) {
                        $badgeClass = 'badge-blue';
                        $level = 'UNIT (< Rp 50 JT)';
                    } elseif ($totalPrice <= 300000000) {
                        $badgeClass = 'badge-yellow';
                        $level = 'HO (Rp 50 JT - 300 JT)';
                    } else {
                        $badgeClass = 'badge-red';
                        $level = 'HO (> Rp 300 JT)';
                    }
                    ?>
                    <div class="approval-level <?php echo $badgeClass; ?>"><?php echo $level; ?></div>
                    
                    <div class="approval-checklist-table">
                        <table class="approval-table">
                            <thead>
                                <tr>
                                    <th>Authorized Parties</th>
                                    <th width="80">UNIT</th>
                                    <th width="80">HO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Procurement (< Rp. 50JT) -->
                                <tr>
                                    <td>Procurement</td>
                                    <td class="check-cell">
                                        <?php if ($totalPrice < 50000000): ?>
                                        <span class="check-mark">✓</span>
                                        <?php else: ?>
                                        <span class="check-empty">○</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="check-cell">
                                        <span class="label-price">&lt; Rp 50JT</span>
                                    </td>
                                </tr>
                                
                                <!-- Local DS Mgr -->
                                <tr>
                                    <td>Local DS Mgr</td>
                                    <td class="check-cell">
                                        <span class="check-empty">○</span>
                                    </td>
                                    <td class="check-cell">
                                        <span class="check-empty">○</span>
                                    </td>
                                </tr>
                                
                                <!-- Cellist -->
                                <tr>
                                    <td>Cellist</td>
                                    <td class="check-cell">
                                        <span class="check-empty">○</span>
                                    </td>
                                    <td class="check-cell">
                                        <span class="check-empty">○</span>
                                    </td>
                                </tr>
                                
                                <!-- Finance (Ref Con) -->
                                <tr>
                                    <td>Finance (Ref Con)</td>
                                    <td class="check-cell">
                                        <span class="check-empty">○</span>
                                    </td>
                                    <td class="check-cell">
                                        <?php if ($totalPrice >= 50000000): ?>
                                        <span class="check-mark">✓</span>
                                        <?php else: ?>
                                        <span class="check-empty">○</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Cellist -->
                                <tr>
                                    <td>Cellist</td>
                                    <td class="check-cell">
                                        <span class="check-empty">○</span>
                                    </td>
                                    <td class="check-cell">
                                        <span class="check-empty">○</span>
                                    </td>
                                </tr>
                                
                                <!-- BP DS Agri & Food (Rp 50JT - 300JT) -->
                                <tr class="range-row">
                                    <td colspan="3" style="background:#f8f9fa;padding:8px;font-weight:600;border-top:2px solid #dee2e6;">
                                        <span class="range-label">HO Range: Rp 50 JT - Rp 300 JT</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>BP DS Agri & Food</td>
                                    <td class="check-cell">-</td>
                                    <td class="check-cell">
                                        <?php if ($totalPrice >= 50000000 && $totalPrice <= 300000000): ?>
                                        <span class="check-mark">✓</span>
                                        <?php else: ?>
                                        <span class="check-empty">○</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Head of BP US & DS Proc -->
                                <tr>
                                    <td>Head of BP US & DS Proc</td>
                                    <td class="check-cell">-</td>
                                    <td class="check-cell">
                                        <?php if ($totalPrice >= 50000000 && $totalPrice <= 300000000): ?>
                                        <span class="check-mark">✓</span>
                                        <?php else: ?>
                                        <span class="check-empty">○</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- HO Range > Rp 300 JT -->
                                <tr class="range-row">
                                    <td colspan="3" style="background:#f8f9fa;padding:8px;font-weight:600;border-top:2px solid #dee2e6;">
                                        <span class="range-label">HO Range: > Rp 300 JT</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Head of Ops Controller</td>
                                    <td class="check-cell">-</td>
                                    <td class="check-cell">
                                        <?php if ($totalPrice > 300000000): ?>
                                        <span class="check-mark">✓</span>
                                        <?php else: ?>
                                        <span class="check-empty">○</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- DS BU CFO -->
                                <tr>
                                    <td>DS BU CFO</td>
                                    <td class="check-cell">-</td>
                                    <td class="check-cell">
                                        <?php if ($totalPrice > 300000000): ?>
                                        <span class="check-mark">✓</span>
                                        <?php else: ?>
                                        <span class="check-empty">○</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="validation-detail" style="margin-top:12px;padding-top:12px;border-top:1px solid #dee2e6;">
                        <strong>Nilai Transaksi:</strong> Rp <?php echo number_format($totalPrice, 0, ',', '.'); ?>
                    </div>
                </div>
            </div>
            
            <!-- DP Validation -->
            <div class="validation-card">
                <div class="validation-title">
                    <span class="icon">💰</span>
                    <strong>Validasi Down Payment (DP)</strong>
                </div>
                <div class="validation-content">
                    <?php if ($businessValidation['dp_valid']): ?>
                        <div class="validation-status status-pass">
                            <span class="check">✓</span> Valid - DP ≥ 50%
                        </div>
                    <?php else: ?>
                        <div class="validation-status status-fail">
                            <span class="cross">✗</span> Tidak Valid - DP < 50%
                        </div>
                    <?php endif; ?>
                    <div class="validation-detail">
                        DP: Rp <?php echo number_format($businessValidation['dp_amount'], 0, ',', '.'); ?> 
                        (<?php echo $businessValidation['dp_percentage']; ?>%)
                    </div>
                    <div class="validation-progress">
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $businessValidation['dp_valid'] ? 'bg-green' : 'bg-red'; ?>" 
                                 style="width: <?php echo min($businessValidation['dp_percentage'], 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Complete Validation -->
            <div class="validation-card">
                <div class="validation-title">
                    <span class="icon">💳</span>
                    <strong>Validasi Pembayaran Lunas</strong>
                </div>
                <div class="validation-content">
                    <?php if ($businessValidation['payment_complete']): ?>
                        <div class="validation-status status-pass">
                            <span class="check">✓</span> Pembayaran Lunas
                        </div>
                    <?php else: ?>
                        <div class="validation-status status-warning">
                            <span class="warning">⚠</span> Belum Lunas
                        </div>
                    <?php endif; ?>
                    <div class="validation-detail">
                        Total Dibayar: Rp <?php echo number_format($businessValidation['total_paid'], 0, ',', '.'); ?> 
                        (<?php echo $businessValidation['payment_percentage']; ?>%)
                    </div>
                    <div class="validation-detail">
                        Sisa: Rp <?php echo number_format(max(0, $businessValidation['total_price'] - $businessValidation['total_paid']), 0, ',', '.'); ?>
                    </div>
                    <div class="validation-progress">
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $businessValidation['payment_complete'] ? 'bg-green' : 'bg-yellow'; ?>" 
                                 style="width: <?php echo min($businessValidation['payment_percentage'], 100); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Auto Decision -->
            <div class="validation-card validation-decision">
                <div class="validation-title">
                    <span class="icon">🎯</span>
                    <strong>Hasil Validasi Business Logic</strong>
                </div>
                <div class="validation-content">
                    <?php 
                    $allValid = $businessValidation['dp_valid'] && 
                                $businessValidation['payment_complete'];
                    ?>
                    <?php if ($allValid): ?>
                        <div class="decision-badge decision-approved">
                            <span class="icon-large">✓</span>
                            <div class="decision-text">
                                <strong>AUDIT APPROVED</strong>
                                <p>Semua kriteria business logic terpenuhi</p>
                            </div>
                        </div>
                        <div class="approval-note">
                            <strong>📌 Catatan Penting:</strong>
                            <p>Persentase checklist <?php echo number_format($submission['percentage_score'], 1); ?>% bukan merupakan kriteria utama approval. 
                            Audit ini <strong>APPROVED</strong> karena memenuhi <strong>Business Logic Validation</strong>:</p>
                            <ul class="validation-criteria">
                                <li>✓ DP minimal 50% terpenuhi</li>
                                <li>✓ Pembayaran telah lunas</li>
                                <li>✓ Approval routing sesuai nilai transaksi</li>
                            </ul>
                            <p style="margin-top:10px;font-style:italic;color:#6c757d;">
                                Persentase hanya menunjukkan kelengkapan dokumentasi checklist, 
                                bukan menentukan approval audit.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="decision-badge decision-review">
                            <span class="icon-large">⚠</span>
                            <div class="decision-text">
                                <strong>PERLU REVIEW</strong>
                                <p>Ada kriteria business logic yang belum terpenuhi</p>
                            </div>
                        </div>
                        <ul class="issue-list">
                            <?php if (!$businessValidation['dp_valid']): ?>
                            <li>⚠ DP kurang dari 50% (saat ini: <?php echo $businessValidation['dp_percentage']; ?>%)</li>
                            <?php endif; ?>
                            <?php if (!$businessValidation['payment_complete']): ?>
                            <li>⚠ Pembayaran belum lunas (terbayar: <?php echo $businessValidation['payment_percentage']; ?>%)</li>
                            <?php endif; ?>
                        </ul>
                        <div class="approval-note approval-note-warning">
                            <strong>📌 Catatan:</strong>
                            <p>Audit akan <strong>APPROVED</strong> jika semua kriteria business logic terpenuhi, 
                            terlepas dari persentase checklist.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Info Table -->
    <table class="excel-info-table">
        <tr>
            <td>Auditor</td>
            <td colspan="3"><?php echo htmlspecialchars($submission['auditor_name']); ?></td>
        </tr>
        <tr>
            <td>Penjualan Mix Oil</td>
            <td colspan="3"><?php echo htmlspecialchars($submission['seller_name'] ?: '-'); ?></td>
        </tr>
        <tr>
            <td>Unit / Lokasi</td>
            <td colspan="3"><?php echo htmlspecialchars($submission['unit_location'] ?: '-'); ?></td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td><?php echo formatDate($submission['submission_date']); ?></td>
            <td width="120"><strong>Qty</strong></td>
            <td><?php echo htmlspecialchars($submission['quantity'] ?: '-'); ?></td>
        </tr>
        <tr>
            <td>Harga Satuan</td>
            <td><?php echo htmlspecialchars($submission['unit_price'] ?: '-'); ?></td>
            <td><strong>Total Harga</strong></td>
            <td><?php echo htmlspecialchars($submission['total_price'] ?: '-'); ?></td>
        </tr>
        <?php if (!empty($submission['required_approvals'])): ?>
        <tr>
            <td colspan="4" style="background: #fff3cd; padding: 12px;">
                <strong>🔐 Approval Routing (Level <?php echo $submission['approval_level'] ?? 1; ?>):</strong><br>
                <span style="font-size: 13px; color: #856404;">
                    <?php echo htmlspecialchars($submission['required_approvals']); ?>
                </span>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($submission['has_refund'] == 1): ?>
        <tr>
            <td colspan="4" style="background: #d1ecf1; padding: 10px;">
                <strong>💰 Status:</strong> <span style="color: #0c5460;">Ada Pengembalian Dana</span>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    
    <!-- Checklist Results -->
    <?php foreach ($sections as $section): ?>
    <div class="excel-section">
        <h3 class="excel-section-header"><?php echo htmlspecialchars($section['section_title']); ?></h3>
        
        <table class="excel-table">
            <thead>
                <tr>
                    <th width="45%">Item</th>
                    <th width="10%">Bobot</th>
                    <th width="15%">Ada</th>
                    <th width="15%">Tidak ada</th>
                    <th width="15%">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($section['items'] as $item): ?>
                <tr>
                    <td>
                        <span class="item-number"><?php echo $item['item_order']; ?>.</span> 
                        <?php echo htmlspecialchars($item['item_text']); ?>
                    </td>
                    <td class="excel-cell-center">
                        <?php echo $item['score_value'] > 0 ? $item['score_value'] : '-'; ?>
                    </td>
                    <?php if ($item['field_type'] === 'checkbox' || $item['field_type'] === 'radio'): ?>
                        <td class="excel-cell-center">
                            <?php if ($item['response_value'] === 'ada' || $item['response_value'] === 'sesuai'): ?>
                            <span class="excel-result-check yes">✓</span>
                            <?php endif; ?>
                        </td>
                        <td class="excel-cell-center">
                            <?php if ($item['response_value'] === 'tidak_ada' || $item['response_value'] === 'tidak_sesuai'): ?>
                            <span class="excel-result-check no">✗</span>
                            <?php endif; ?>
                        </td>
                        <td class="excel-cell-center">-</td>
                    <?php elseif ($item['field_type'] === 'date'): ?>
                        <td colspan="3" class="excel-result-text">
                            <?php echo $item['response_value'] ? formatDate($item['response_value']) : '-'; ?>
                        </td>
                    <?php else: ?>
                        <td colspan="3" class="excel-result-text">
                            <?php echo htmlspecialchars($item['response_value'] ?: '-'); ?>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($section['section_order'] === 2): ?>
        <div class="excel-note">Note: dikirim ke Kaber jika Mix Oil yg akan dijual masuk area Kaber</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <?php if ($submission['notes']): ?>
    <table class="excel-info-table">
        <tr>
            <td width="200">Catatan Tambahan</td>
            <td><?php echo nl2br(htmlspecialchars($submission['notes'])); ?></td>
        </tr>
    </table>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
