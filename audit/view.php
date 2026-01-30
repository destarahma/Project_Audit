<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

// Function to clean Rupiah format to number
function cleanRupiah($value) {
    if (empty($value)) return 0;
    // Remove "Rp", spaces, dots (thousand separator)
    $cleaned = preg_replace('/[^0-9]/', '', $value);
    return floatval($cleaned);
}

// Function to format number untuk display (terima string dengan titik sebagai thousand separator)
function formatHarga($value) {
    if (empty($value)) return '-';
    
    // Kalau sudah ada "Rp", return as is
    if (stripos($value, 'Rp') !== false) {
        return $value;
    }
    
    // Remove dots and parse to number
    $cleaned = str_replace('.', '', $value);
    $number = floatval($cleaned);
    
    if ($number > 0) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }
    
    return '-';
}

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
$allItems = []; // Store all items indexed by item_id for easy lookup
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
    $allItems[$row['item_id']] = $row;
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
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Cetak</button>
    </div>
</div>

<div class="card excel-form">
    <div class="excel-header">
        <div style="font-size: 13px; opacity: 0.85; margin-bottom: 8px; letter-spacing: 1px;">Departemen Procurement</div>
        <h2><?php echo htmlspecialchars($submission['template_name']); ?></h2>
        <div style="margin-top: 10px; font-size: 14px; opacity: 0.9;">
            Nomor: <?php 
            // Bersihkan template_code dari suffix _001 atau _nnn
            $shortCode = preg_replace('/_\d{3}$/', '', $submission['template_code']);
            echo htmlspecialchars($shortCode) . '-' . str_pad($submission['audit_number'], 5, '0', STR_PAD_LEFT); 
            ?> | 
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
    <div class="excel-score-card">
        <div class="excel-score-item">
            <div class="excel-score-label">STATUS AUDIT</div>
            <?php 
            // Gunakan status dari business logic yang sudah diperbaiki
            $statusData = $bl->calculateAuditStatus($submissionId);
            $displayStatus = $statusData['status'];
            
            // Mapping status ke class CSS
            $statusClassMap = [
                'Lengkap' => 'status-approved',
                'Compliant' => 'status-approved',
                'Sangat Baik' => 'status-approved',
                'Baik' => 'status-baik',
                'Perlu Dilengkapi' => 'status-warning',
                'Cukup' => 'status-cukup',
                'Dalam Proses' => 'status-cukup',
                'Perlu Perbaikan' => 'status-warning'
            ];
            
            $statusClass = $statusClassMap[$displayStatus] ?? 'status-cukup';
            
            // Additional validation untuk Mix Oil
            $isBusinessValid = $businessValidation && 
                               $businessValidation['dp_valid'] && 
                               $businessValidation['payment_complete'];
            ?>
            <div class="excel-score-status <?php echo $statusClass; ?>"><?php echo $displayStatus; ?></div>
            <?php if ($isBusinessValid): ?>
            <div style="font-size:11px;color:rgba(255,255,255,0.9);margin-top:8px;">
                ✓ Business Logic Validated
            </div>
            <?php endif; ?>
        </div>
    </div>
    
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
                        $badgeClass = 'badge-red';
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
    <?php if ($submission['template_id'] == 9 || $submission['template_id'] == 10): ?>
    <!-- PO Tagging OA / PO Non OA: Get info from first section responses -->
    <table class="excel-info-table">
        <tr>
            <td>Auditor</td>
            <td colspan="3"><?php echo htmlspecialchars($submission['auditor_name']); ?></td>
        </tr>
        <?php
        // Get data from section 1 (Informasi PO)
        $infoSection = null;
        foreach ($sections as $sec) {
            if ($sec['section_order'] == 1) {
                $infoSection = $sec;
                break;
            }
        }
        
        if ($infoSection) {
            $infoData = [];
            foreach ($infoSection['items'] as $item) {
                $infoData[$item['item_order']] = $item['response_value'] ?? '';
            }
            
            // Display info based on item_order
            if (!empty($infoData[1])) {
                echo '<tr><td>' . ($submission['template_id'] == 9 ? 'Pembelian PO tagging OA' : 'Pembelian PO Non OA') . '</td>';
                echo '<td colspan="3">' . htmlspecialchars($infoData[1]) . '</td></tr>';
            }
            if (!empty($infoData[2])) {
                echo '<tr><td>Tanggal</td>';
                echo '<td colspan="3">' . formatDate($infoData[2]) . '</td></tr>';
            }
            if (!empty($infoData[3])) {
                echo '<tr><td>Deskripsi</td>';
                echo '<td colspan="3">' . nl2br(htmlspecialchars($infoData[3])) . '</td></tr>';
            }
            
            $qty = $infoData[4] ?? '';
            $hargaSatuan = $infoData[5] ?? '';
            $totalHargaRaw = $infoData[6] ?? '';
            
            // Clean harga satuan - remove dots and non-numeric chars except digits
            if (!empty($hargaSatuan)) {
                $hargaSatuanClean = preg_replace('/[^0-9]/', '', $hargaSatuan);
            } else {
                $hargaSatuanClean = '';
            }
            
            // Parse Total Harga jika berbentuk "Rp 5.000.000"
            if (!empty($totalHargaRaw)) {
                // Remove "Rp" and all non-numeric characters except digits
                $totalHarga = preg_replace('/[^0-9]/', '', $totalHargaRaw);
            } else if (!empty($qty) && !empty($hargaSatuanClean)) {
                // Kalau total harga kosong, hitung otomatis dari qty × harga satuan
                $totalHarga = floatval($qty) * floatval($hargaSatuanClean);
            } else {
                $totalHarga = '';
            }
            
            if (!empty($qty) || !empty($hargaSatuan)) {
                echo '<tr>';
                echo '<td>Qty</td>';
                echo '<td>' . htmlspecialchars($qty ?: '-') . '</td>';
                echo '<td width="120"><strong>Harga Satuan</strong></td>';
                echo '<td><strong>Rp ' . (!empty($hargaSatuanClean) ? number_format(floatval($hargaSatuanClean), 0, ',', '.') : '-') . '</strong></td>';
                echo '</tr>';
            }
            
            // Tampilkan Total Harga (selalu tampilkan jika ada data)
            if (!empty($totalHarga)) {
                echo '<tr><td><strong>Total Harga</strong></td>';
                echo '<td colspan="3"><strong>Rp ' . number_format(floatval($totalHarga), 0, ',', '.') . '</strong></td></tr>';
            }
        }
        ?>
    </table>
    <?php else: ?>
    <!-- Template lainnya (Mix Oil, Barbes, Jual Aset) -->
    <table class="excel-info-table">
        <tr>
            <td>Auditor</td>
            <td colspan="3"><?php echo htmlspecialchars($submission['auditor_name']); ?></td>
        </tr>
        <tr>
            <td><?php 
                // Dynamic label based on template
                if ($submission['template_id'] == 1) {
                    echo 'Penjualan Mix Oil';
                } elseif ($submission['template_id'] == 5) {
                    echo 'Self Audit : Barbes';
                } elseif ($submission['template_id'] == 6) {
                    echo 'Self Audit : Jual Aset';
                } else {
                    echo 'Nama Item';
                }
            ?></td>
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
            <td><strong>Rp <?php echo $submission['unit_price'] ? number_format(cleanRupiah($submission['unit_price']), 0, ',', '.') : '-'; ?></strong></td>
            <td><strong>Total Harga</strong></td>
            <td><strong>Rp <?php echo $submission['total_price'] ? number_format(cleanRupiah($submission['total_price']), 0, ',', '.') : '-'; ?></strong></td>
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
    <?php endif; ?>
    
    <!-- Checklist Results -->
    <?php 
    // Function to render items based on template type
    function renderTemplateItems($templateId, $sections, $submission) {
        foreach ($sections as $section):
            // Skip section 1 untuk PO Tagging OA dan PO Non OA (sudah di info table)
            if (($templateId == 9 || $templateId == 10) && $section['section_order'] == 1) {
                continue;
            }
    ?>
    <div class="excel-section">
        <h3 class="excel-section-header"><?php echo htmlspecialchars($section['section_title']); ?></h3>
        
        <?php if ($section['section_order'] == 6): ?>
            <!-- Section 6: Dokumentasi (khusus tanpa tabel) -->
            <div style="padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">
                <?php foreach ($section['items'] as $item): ?>
                    <p style="margin: 0; font-size: 14px; color: #495057;"><?php echo htmlspecialchars($item['item_text']); ?></p>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <table class="excel-table">
                <thead>
                    <tr>
                        <?php if ($templateId == 10 && $section['section_order'] == 4): ?>
                            <!-- Header khusus untuk PO Non OA Section 4 -->
                            <th width="60%">Item</th>
                            <th width="20%">Sesuai</th>
                            <th width="20%">Tidak</th>
                        <?php else: ?>
                            <!-- Header standar -->
                            <th width="50%">Item</th>
                            <th width="15%">Ada</th>
                            <th width="15%">Tidak ada</th>
                            <th width="20%">Tanggal</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $displayOrder = 1;
                    
                    // Render berdasarkan template
                    if ($templateId == 9) {
                        // PO TAGGING OA
                        renderPOTaggingItems($section, $displayOrder);
                    } else if ($templateId == 10) {
                        // PO NON OA
                        renderPONonOAItems($section, $displayOrder);
                    } else if ($templateId == 1) {
                        // MIX OIL
                        renderMixOilItems($section, $displayOrder);
                    } else if ($templateId == 5) {
                        // BARBES
                        renderBarbesItems($section, $displayOrder);
                    } else if ($templateId == 6) {
                        // JUAL ASET
                        renderJualAsetItems($section, $displayOrder);
                    }
                    ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if ($section['section_order'] == 2 && $templateId == 1): ?>
        <div class="excel-note">Note: dikirim ke Kaber jika Mix Oil yg akan dijual masuk area Kaber</div>
        <?php endif; ?>
    </div>
    <?php 
        endforeach;
    }
    
    // RENDER FUNCTION UNTUK PO TAGGING OA/NON OA
    function renderPOTaggingItems($section, &$displayOrder) {
        $items = $section['items'];
        
        // Section 2: Pengurusan Pembelian
        if ($section['section_order'] == 2) {
            $labels = ['RAP', 'Approval RAP', 'Drawing / Layout', 'PR fully approved'];
            $baseOrders = [1, 4, 7, 10]; // item_order untuk Ada
            
            foreach ($labels as $idx => $label) {
                $baseOrder = $baseOrders[$idx];
                $adaItem = null;
                $tidakItem = null;
                $dateItem = null;
                
                foreach ($items as $item) {
                    if ($item['item_order'] == $baseOrder) $adaItem = $item;
                    if ($item['item_order'] == $baseOrder + 1) $tidakItem = $item;
                    if ($item['item_order'] == $baseOrder + 2) $dateItem = $item;
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
                echo '<td class="excel-cell-center">';
                echo ($adaItem && isset($adaItem['response_value']) && $adaItem['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($tidakItem && isset($tidakItem['response_value']) && $tidakItem['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
        // Section 3: PO
        else if ($section['section_order'] == 3) {
            // Items 1-6: Berdasarkan item_text, dengan pola: Sesuai, Tidak, Tanggal
            $labels = ['Cek DD', 'Cek kondisi Vendor', 'Cek material/item', 'Cek payment term', 'Cek harga', 'Cek qty'];
            
            foreach ($labels as $idx => $label) {
                // Cari item berdasarkan item_text
                $sesuaiItem = null;
                $tidakItem = null;
                $dateItem = null;
                
                foreach ($items as $item) {
                    if (stripos($item['item_text'], $label . ' - Sesuai') !== false) {
                        $sesuaiItem = $item;
                    }
                    if (stripos($item['item_text'], $label . ' - Tidak') !== false) {
                        $tidakItem = $item;
                    }
                    if (stripos($item['item_text'], $label . ' - Tanggal') !== false) {
                        $dateItem = $item;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
                echo '<td class="excel-cell-center">';
                // Cek apakah ada response dengan value 'ada' atau 'sesuai'
                if ($sesuaiItem && isset($sesuaiItem['response_value']) && ($sesuaiItem['response_value'] == 'ada' || $sesuaiItem['response_value'] == 'sesuai')) {
                    echo '<span class="excel-result-check yes">✓</span>';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '<td class="excel-cell-center">';
                // Cek apakah ada response dengan value 'tidak_ada' atau 'tidak'
                if ($tidakItem && isset($tidakItem['response_value']) && ($tidakItem['response_value'] == 'tidak_ada' || $tidakItem['response_value'] == 'tidak')) {
                    echo '<span class="excel-result-check no">✗</span>';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '<td class="excel-cell-center">';
                // Tampilkan tanggal dari field tanggal
                echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            
            // Items 7-8: Textarea (Input note pembelian PO, Kirim PO)
            $textareaLabels = ['Input note pembelian PO', 'Kirim PO'];
            
            foreach ($textareaLabels as $textLabel) {
                $textItem = null;
                foreach ($items as $item) {
                    if ($item['field_type'] == 'textarea' && stripos($item['item_text'], $textLabel) !== false) {
                        $textItem = $item;
                        break;
                    }
                }
                
                if ($textItem) {
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($textLabel) . '</td>';
                    echo '<td colspan="3" class="excel-result-text">';
                    echo (isset($textItem['response_value']) && $textItem['response_value']) ? nl2br(htmlspecialchars($textItem['response_value'])) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
            }
        }
    }
    
    // RENDER FUNCTION UNTUK MIX OIL
    function renderMixOilItems($section, &$displayOrder) {
        $items = $section['items'];
        $processed = [];
        
        foreach ($items as $item) {
            if (in_array($item['item_id'], $processed)) continue;
            
            // Skip related items untuk section selain 3, 4, 5
            // Section 3, 4, 5 punya struktur berbeda dengan pola item_order * 10 + 1 untuk related items
            if (!in_array($section['section_order'], [3, 4, 5])) {
                // Skip harga: 11,21,31 dan tanggal: 51,61,71,81,91,101
                if ($item['item_order'] > 10) continue;
                // Skip date/number kecuali item 7 (Periode QCF)
                if (($item['field_type'] == 'date' || $item['field_type'] == 'number') && $item['item_order'] != 7) continue;
            } else {
                // Untuk Section 3, 4, 5: skip item dengan order > 10, kecuali untuk Section 5
                if ($section['section_order'] != 5 && $item['item_order'] > 10) continue;
                // Untuk Section 5: jangan skip item 3 (Jumlah) meskipun field_type = number
                if ($section['section_order'] == 5 && $item['field_type'] == 'number' && $item['item_order'] != 3) continue;
            }
            
            $processed[] = $item['item_id'];
            
            // Section 2: Penawaran harga (nama vendor + harga)
            if ($section['section_order'] == 2 && $item['item_order'] >= 1 && $item['item_order'] <= 3) {
                $hargaItem = null;
                foreach ($items as $hi) {
                    if ($hi['item_order'] == ($item['item_order'] * 10 + 1)) {
                        $hargaItem = $hi;
                        $processed[] = $hi['item_id'];
                        break;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Penawaran harga ' . $displayOrder . ' (nama Vendor)</td>';
                echo '<td colspan="3" class="excel-result-text">';
                if (isset($item['response_value']) && $item['response_value']) {
                    echo htmlspecialchars($item['response_value']);
                    if ($hargaItem && isset($hargaItem['response_value']) && $hargaItem['response_value']) {
                        echo '<br><strong>' . formatHarga($hargaItem['response_value']) . '</strong>';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 2: Approval QCF (special)
            else if ($section['section_order'] == 2 && $item['item_order'] == 4) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Approval QCF</td>';
                echo '<td colspan="3" class="excel-result-text">';
                echo isset($item['response_value']) && $item['response_value'] ? htmlspecialchars($item['response_value']) : 'Akan ditampilkan otomatis berdasarkan Total Harga';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 2: Periode QCF (item 7) - Special handling for date field
            else if ($section['section_order'] == 2 && $item['item_order'] == 7) {
                $hasDate = isset($item['response_value']) && !empty($item['response_value']) && 
                           $item['response_value'] != '0000-00-00' && $item['response_value'] != '0000-00-00 00:00:00';
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Periode QCF</td>';
                echo '<td colspan="2" class="excel-cell-gray">&nbsp;</td>';
                echo '<td class="excel-cell-center">';
                echo $hasDate ? formatDate($item['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 3: Penerimaan Pembayaran - Special handling (sesuai template persis)
            else if ($section['section_order'] == 3) {
                // Item 1: Konfirmasi Qty (checkbox dengan info di kolom Tanggal)
                if ($item['item_order'] == 1) {
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                    echo '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                    echo '</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
                // Item 2: Bukti Transfer I - tampilkan tanggal di kolom Tanggal
                if ($item['item_order'] == 2) {
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="2" class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value']) ? formatDate($item['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
                // Item 3: Nilai trasfer I (sub-item indented)
                if ($item['item_order'] == 3) {
                    echo '<tr>';
                    echo '<td style="padding-left:30px;">' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="3">';
                    echo (isset($item['response_value']) && $item['response_value']) ? formatHarga($item['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                }
                // Item 4: Info konfirmasi I (sub-item indented, radio)
                if ($item['item_order'] == 4) {
                    echo '<tr>';
                    echo '<td style="padding-left:30px;">' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="3" style="text-align:center;">';
                    if (isset($item['response_value'])) {
                        if ($item['response_value'] == 'sesuai') {
                            echo '<span class="excel-result-check yes">✓ Sesuai</span>';
                        } else if ($item['response_value'] == 'tidak_sesuai') {
                            echo '<span class="excel-result-check no">✗ Tidak Sesuai</span>';
                        } else {
                            echo '-';
                        }
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                // Item 5: Bukti Transfer II - tampilkan tanggal di kolom Tanggal
                if ($item['item_order'] == 5) {
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="2" class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value']) ? formatDate($item['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
                // Item 6: Nilai trasfer II (sub-item indented)
                if ($item['item_order'] == 6) {
                    echo '<tr>';
                    echo '<td style="padding-left:30px;">' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="3">';
                    echo (isset($item['response_value']) && $item['response_value']) ? formatHarga($item['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                }
                // Item 7: Info konfirmasi II (sub-item indented, radio)
                if ($item['item_order'] == 7) {
                    echo '<tr>';
                    echo '<td style="padding-left:30px;">' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="3" style="text-align:center;">';
                    if (isset($item['response_value'])) {
                        if ($item['response_value'] == 'sesuai') {
                            echo '<span class="excel-result-check yes">✓ Sesuai</span>';
                        } else if ($item['response_value'] == 'tidak_sesuai') {
                            echo '<span class="excel-result-check no">✗ Tidak Sesuai</span>';
                        } else {
                            echo '-';
                        }
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                // Item 8: Sisa (dengan numbering)
                if ($item['item_order'] == 8) {
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="3">';
                    echo (isset($item['response_value']) && $item['response_value']) ? formatHarga($item['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
                // Item 9: Email instruksi - tampilkan tanggal di kolom Tanggal
                if ($item['item_order'] == 9) {
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="2" class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value']) ? formatDate($item['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
            }
            // Section 4 item 5: Qty Mix oil tidak melebihi SPK/PJB (Sesuai/Tidak Sesuai)
            else if ($section['section_order'] == 4 && $item['item_order'] == 5) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'sesuai') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'tidak_sesuai') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">-</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 5 item 3: Jumlah (number di kolom Ada/Tidak Ada + radio Sesuai/Tidak Sesuai di kolom Tanggal)
            else if ($section['section_order'] == 5 && $item['item_order'] == 3) {
                $kesesuaianItem = null;
                foreach ($items as $ki) {
                    if ($ki['item_order'] == 31 && $ki['field_type'] == 'radio') {
                        $kesesuaianItem = $ki;
                        $processed[] = $ki['item_id'];
                        break;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td colspan="2" class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value']) ? formatHarga($item['response_value']) : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                if ($kesesuaianItem && isset($kesesuaianItem['response_value']) && $kesesuaianItem['response_value']) {
                    if ($kesesuaianItem['response_value'] == 'sesuai') {
                        echo '<span class="excel-result-check yes">✓</span> Sesuai';
                    } else if ($kesesuaianItem['response_value'] == 'tidak_sesuai') {
                        echo '<span class="excel-result-check no">✗</span> Tidak Sesuai';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Regular items: Ada/Tidak ada + Tanggal
            else if ($item['field_type'] == 'radio' || $item['field_type'] == 'checkbox') {
                $dateItem = null;
                $olehItem = null;
                
                foreach ($items as $di) {
                    if ($di['item_order'] == ($item['item_order'] * 10 + 1) && $di['field_type'] == 'date') {
                        $dateItem = $di;
                        $processed[] = $di['item_id'];
                    }
                    if ($di['item_order'] == ($item['item_order'] * 10 + 2) && $di['field_type'] == 'text') {
                        $olehItem = $di;
                        $processed[] = $di['item_id'];
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                // Hanya tampilkan tanggal jika user memilih "Ada"
                if (isset($item['response_value']) && $item['response_value'] == 'ada' && $dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) {
                    echo formatDate($dateItem['response_value']);
                    if ($olehItem && isset($olehItem['response_value']) && $olehItem['response_value']) {
                        echo '<br><small style="color:#6c757d;">Oleh: ' . htmlspecialchars($olehItem['response_value']) . '</small>';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
    }
    
    // RENDER FUNCTION UNTUK BARBES & JUAL ASET
    function renderBarbesItems($section, &$displayOrder) {
        $items = $section['items'];
        $processed = [];
        
        foreach ($items as $item) {
            if (in_array($item['item_id'], $processed)) continue;
            
            $processed[] = $item['item_id'];
            
            // Section 2: Penawaran harga (nama vendor + harga)
            if ($section['section_order'] == 2 && $item['item_order'] >= 1 && $item['item_order'] <= 3 && $item['field_type'] == 'text') {
                $hargaItem = null;
                foreach ($items as $hi) {
                    if ($hi['item_order'] == ($item['item_order'] * 10 + 1) && $hi['field_type'] == 'number') {
                        $hargaItem = $hi;
                        $processed[] = $hi['item_id'];
                        break;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td colspan="3" class="excel-result-text">';
                if (isset($item['response_value']) && $item['response_value']) {
                    echo htmlspecialchars($item['response_value']);
                    if ($hargaItem && isset($hargaItem['response_value']) && $hargaItem['response_value']) {
                        echo '<br><strong>' . formatHarga($hargaItem['response_value']) . '</strong>';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 2: Approval QCF (item_order 4) - show Authorized Parties
            else if ($section['section_order'] == 2 && $item['item_order'] == 4) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Approval QCF</td>';
                echo '<td colspan="3" class="excel-result-text">';
                echo '<div style="font-style:italic;color:#6c757d;">Authorized Parties:</div>';
                echo '<div style="margin-top:5px;">';
                echo isset($item['response_value']) && $item['response_value'] ? htmlspecialchars($item['response_value']) : 'Akan ditampilkan otomatis berdasarkan Total Harga';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 2: Items dengan radio Ada/Tidak + Tanggal (item 5-10 dan 110)
            else if ($section['section_order'] == 2 && $item['field_type'] == 'radio' && ($item['item_order'] >= 5 && $item['item_order'] <= 10 || $item['item_order'] == 110)) {
                $dateItem = null;
                foreach ($items as $di) {
                    if ($di['item_order'] == ($item['item_order'] * 10 + 1) && $di['field_type'] == 'date') {
                        $dateItem = $di;
                        $processed[] = $di['item_id'];
                        break;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 4: Items 1-4 dengan radio Ada/Tidak + Tanggal
            else if ($section['section_order'] == 4 && $item['field_type'] == 'radio' && $item['item_order'] >= 1 && $item['item_order'] <= 4) {
                $dateItem = null;
                $olehItem = null;
                
                // Cari tanggal items (item_order x 10 + 1)
                foreach ($items as $di) {
                    if ($di['item_order'] == ($item['item_order'] * 10 + 1) && $di['field_type'] == 'date') {
                        $dateItem = $di;
                        $processed[] = $di['item_id'];
                    }
                    // Item 4 punya field "Oleh" (item_order 42)
                    if ($item['item_order'] == 4 && $di['item_order'] == 42 && $di['field_type'] == 'text') {
                        $olehItem = $di;
                        $processed[] = $di['item_id'];
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                if ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) {
                    echo formatDate($dateItem['response_value']);
                    if ($olehItem && isset($olehItem['response_value']) && $olehItem['response_value']) {
                        echo '<br><small style="color:#6c757d;">Oleh: ' . htmlspecialchars($olehItem['response_value']) . '</small>';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 4: Item 5 - Dokumen Foto (file upload)
            else if ($section['section_order'] == 4 && $item['item_order'] == 5 && $item['field_type'] == 'file') {
                echo '<tr>';
                echo '<td colspan="4" style="padding:15px;">';
                echo '<strong><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</strong>';
                
                if (isset($item['response_value']) && $item['response_value']) {
                    $photos = explode(',', $item['response_value']);
                    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:15px;margin-top:10px;">';
                    foreach ($photos as $photo) {
                        $photo = trim($photo);
                        if ($photo) {
                            $photoPath = '../uploads/photos/' . $photo;
                            echo '<div style="text-align:center;">';
                            echo '<img src="' . htmlspecialchars($photoPath) . '" style="width:100%;max-width:150px;height:150px;object-fit:cover;border:1px solid #dee2e6;border-radius:4px;" alt="Foto">';
                            echo '<div style="font-size:11px;color:#6c757d;margin-top:5px;word-break:break-all;">' . htmlspecialchars($photo) . '</div>';
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                } else {
                    echo '<div style="padding:20px;text-align:center;color:#6c757d;background:#f8f9fa;border:1px dashed #dee2e6;border-radius:4px;margin-top:10px;">Tidak ada foto</div>';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 4: Item 6 - Qty Barbes (Sesuai/Tidak Sesuai)
            else if ($section['section_order'] == 4 && $item['item_order'] == 6 && stripos($item['item_text'], 'Qty Barbes') !== false) {
                $tidakSesuaiItem = null;
                foreach ($items as $ti) {
                    if ($ti['item_order'] == 7) {
                        $tidakSesuaiItem = $ti;
                        $processed[] = $ti['item_id'];
                        break;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Qty Barbes tidak melebihi SPK/PJB</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && ($item['response_value'] == 'sesuai' || $item['response_value'] == 'ada')) ? '<span class="excel-result-check yes">✓</span><br><small>Sesuai</small>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($tidakSesuaiItem && isset($tidakSesuaiItem['response_value']) && ($tidakSesuaiItem['response_value'] == 'tidak_sesuai' || $tidakSesuaiItem['response_value'] == 'tidak_ada')) ? '<span class="excel-result-check no">✗</span><br><small>Tidak Sesuai</small>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">-</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 3: Special handling untuk Penerimaan Pembayaran
            else if ($section['section_order'] == 3) {
                // Item 1: Konfirmasi Qty (radio Ada/Tidak)
                if ($item['item_order'] == 1) {
                    echo '<tr>';
                    echo '<td><span class="item-number">1.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                    echo '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                    echo '</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
                // Item 2: Bukti Transfer I (hanya menampilkan tanggal)
                else if ($item['item_order'] == 2) {
                    // Cari tanggal untuk item 2 (item_order 21)
                    $dateItem = null;
                    foreach ($items as $di) {
                        if ($di['item_order'] == 21 && $di['field_type'] == 'date') {
                            $dateItem = $di;
                            $processed[] = $di['item_id'];
                            break;
                        }
                    }
                    
                    echo '<tr>';
                    echo '<td><span class="item-number">2.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">';
                    echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                    
                    // Sub-items untuk Bukti Transfer I
                    $nilaiItem = null;
                    $infoItem = null;
                    foreach ($items as $si) {
                        if ($si['item_order'] == 3) {
                            $nilaiItem = $si;
                            $processed[] = $si['item_id'];
                        }
                        if ($si['item_order'] == 4) {
                            $infoItem = $si;
                            $processed[] = $si['item_id'];
                        }
                    }
                    
                    // Nilai transfer I (no numbering)
                    if ($nilaiItem) {
                        echo '<tr>';
                        echo '<td style="padding-left:30px;">' . htmlspecialchars($nilaiItem['item_text']) . '</td>';
                        echo '<td colspan="3">';
                        echo (isset($nilaiItem['response_value']) && $nilaiItem['response_value']) ? formatHarga($nilaiItem['response_value']) : 'Rp';
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    // Info konfirmasi I (radio Sesuai/Tidak Sesuai)
                    if ($infoItem) {
                        echo '<tr>';
                        echo '<td style="padding-left:30px;">' . htmlspecialchars($infoItem['item_text']) . '</td>';
                        echo '<td colspan="3" style="text-align:center;">';
                        if (isset($infoItem['response_value'])) {
                            if ($infoItem['response_value'] == 'sesuai') {
                                echo '<span class="excel-result-check yes">✓ Sesuai</span>';
                            } else if ($infoItem['response_value'] == 'tidak_sesuai') {
                                echo '<span class="excel-result-check no">✗ Tidak Sesuai</span>';
                            } else {
                                echo '-';
                            }
                        } else {
                            echo '-';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                }
                // Item 3 dan 4 sudah diproses sebagai sub-items di atas
                else if ($item['item_order'] == 3 || $item['item_order'] == 4) {
                    // Skip, sudah diproses
                }
                // Item 5: Bukti Transfer II (hanya menampilkan tanggal)
                else if ($item['item_order'] == 5) {
                    // Cari tanggal untuk item 5 (item_order 51)
                    $dateItem = null;
                    foreach ($items as $di) {
                        if ($di['item_order'] == 51 && $di['field_type'] == 'date') {
                            $dateItem = $di;
                            $processed[] = $di['item_id'];
                            break;
                        }
                    }
                    
                    echo '<tr>';
                    echo '<td><span class="item-number">3.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">';
                    echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                    
                    // Sub-items untuk Bukti Transfer II
                    $nilaiItem = null;
                    $infoItem = null;
                    foreach ($items as $si) {
                        if ($si['item_order'] == 6) {
                            $nilaiItem = $si;
                            $processed[] = $si['item_id'];
                        }
                        if ($si['item_order'] == 7) {
                            $infoItem = $si;
                            $processed[] = $si['item_id'];
                        }
                    }
                    
                    // Nilai transfer II
                    if ($nilaiItem) {
                        echo '<tr>';
                        echo '<td style="padding-left:30px;">' . htmlspecialchars($nilaiItem['item_text']) . '</td>';
                        echo '<td colspan="3">';
                        echo (isset($nilaiItem['response_value']) && $nilaiItem['response_value']) ? formatHarga($nilaiItem['response_value']) : 'Rp';
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    // Info konfirmasi II
                    if ($infoItem) {
                        echo '<tr>';
                        echo '<td style="padding-left:30px;">' . htmlspecialchars($infoItem['item_text']) . '</td>';
                        echo '<td colspan="3" style="text-align:center;">';
                        if (isset($infoItem['response_value'])) {
                            if ($infoItem['response_value'] == 'sesuai') {
                                echo '<span class="excel-result-check yes">✓ Sesuai</span>';
                            } else if ($infoItem['response_value'] == 'tidak_sesuai') {
                                echo '<span class="excel-result-check no">✗ Tidak Sesuai</span>';
                            } else {
                                echo '-';
                            }
                        } else {
                            echo '-';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                }
                // Item 6 dan 7 sudah diproses sebagai sub-items di atas
                else if ($item['item_order'] == 6 || $item['item_order'] == 7) {
                    // Skip, sudah diproses
                }
                // Item 8: Sisa (number only, no radio)
                else if ($item['item_order'] == 8) {
                    echo '<tr>';
                    echo '<td><span class="item-number">4.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td colspan="3">';
                    echo (isset($item['response_value']) && $item['response_value']) ? formatHarga($item['response_value']) : 'Rp';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
                // Item 9: Email instruksi (hanya menampilkan tanggal)
                else if ($item['item_order'] == 9) {
                    // Cari tanggal untuk item 9 (item_order 91)
                    $dateItem = null;
                    foreach ($items as $di) {
                        if ($di['item_order'] == 91 && $di['field_type'] == 'date') {
                            $dateItem = $di;
                            $processed[] = $di['item_id'];
                            break;
                        }
                    }
                    
                    echo '<tr>';
                    echo '<td><span class="item-number">5.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">-</td>';
                    echo '<td class="excel-cell-center">';
                    echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
            }
            // Regular items untuk section lain (bukan section 2 dan 3)
            else if ($item['field_type'] == 'radio' && !in_array($section['section_order'], [2, 3])) {
                $dateItem = null;
                
                // Cari tanggal items
                foreach ($items as $di) {
                    if ($di['item_order'] == ($item['item_order'] * 10 + 1) && $di['field_type'] == 'date') {
                        $dateItem = $di;
                        $processed[] = $di['item_id'];
                        break;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
    }
    
    function renderJualAsetItems($section, &$displayOrder) {
        $items = $section['items'];
        $processed = [];
        
        foreach ($items as $item) {
            if (in_array($item['item_id'], $processed)) continue;
            
            $processed[] = $item['item_id'];
            
            // Section 2: Penawaran harga (nama vendor + harga)
            if ($section['section_order'] == 2 && $item['item_order'] >= 1 && $item['item_order'] <= 3 && $item['field_type'] == 'text' && strpos($item['item_text'], 'Penawaran harga') !== false) {
                $hargaItem = null;
                foreach ($items as $hi) {
                    if ($hi['item_order'] == ($item['item_order'] * 10 + 2) && $hi['field_type'] == 'number') {
                        $hargaItem = $hi;
                        $processed[] = $hi['item_id'];
                        break;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td colspan="3" class="excel-result-text">';
                if (isset($item['response_value']) && $item['response_value']) {
                    echo htmlspecialchars($item['response_value']);
                    if ($hargaItem && isset($hargaItem['response_value']) && $hargaItem['response_value']) {
                        echo '<br><strong>' . formatHarga($hargaItem['response_value']) . '</strong>';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 2: Approval QCF (item_order 4) - show authorization table
            else if ($section['section_order'] == 2 && $item['item_order'] == 4 && $item['field_type'] == 'text') {
                // Parse checkbox values
                $checkedValues = isset($item['response_value']) ? explode(',', $item['response_value']) : [];
                
                echo '<tr>';
                echo '<td><span class="item-number">4.</span> Approval QCF</td>';
                echo '<td colspan="3" class="excel-result-text">';
                echo '<div style="padding:10px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;">';
                echo '<strong>Authorized Parties:</strong><br>';
                echo '<table style="width:100%;margin-top:8px;font-size:11px;border-collapse:collapse;" border="1" cellpadding="6">';
                echo '<tr style="background:#e9ecef;font-weight:bold;">';
                echo '<th></th><th>≤ Rp. 100Jt</th><th>> Rp 100Jt - ≤ Rp 1M</th><th>> Rp 1M - ≤ 10M</th><th>> Rp 10 M</th>';
                echo '</tr>';
                
                // Procurement row
                echo '<tr><td style="font-weight:bold;background:#f8f9fa;">Procurement</td>';
                echo '<td>Local DS Mgr</td><td>BP DS Agri & Food</td><td>Head of BP US & DS Proc</td><td>Head of BP US & DS Proc + CPO</td>';
                echo '</tr>';
                echo '<tr><td style="font-weight:bold;background:#f8f9fa;">Ceklist</td>';
                echo '<td style="text-align:center;">' . (in_array('approval_proc_100jt', $checkedValues) ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '<td style="text-align:center;">' . (in_array('approval_proc_1m', $checkedValues) ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '<td style="text-align:center;">' . (in_array('approval_proc_10m', $checkedValues) ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '<td style="text-align:center;">';
                if (in_array('approval_proc_10m_1', $checkedValues)) echo '<span class="excel-result-check yes">✓</span> ';
                if (in_array('approval_proc_10m_2', $checkedValues)) echo '<span class="excel-result-check yes">✓</span>';
                if (!in_array('approval_proc_10m_1', $checkedValues) && !in_array('approval_proc_10m_2', $checkedValues)) echo '-';
                echo '</td>';
                echo '</tr>';
                
                // Finance row
                echo '<tr><td style="font-weight:bold;background:#f8f9fa;">Finance</td>';
                echo '<td>Na</td><td>Na</td><td>Head of Ops Controller</td><td>DS BU CFO</td>';
                echo '</tr>';
                echo '<tr><td style="font-weight:bold;background:#f8f9fa;">Ceklist</td>';
                echo '<td></td><td></td>';
                echo '<td style="text-align:center;">' . (in_array('approval_fin_10m', $checkedValues) ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '<td style="text-align:center;">' . (in_array('approval_fin_10m_plus', $checkedValues) ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '</tr>';
                
                // Executive row
                echo '<tr><td style="font-weight:bold;background:#f8f9fa;">Executive</td>';
                echo '<td>Na</td><td>Related Head</td><td>DS BU CEO</td><td>DS BU CEO/MD DSI + CFO DS + DS COO</td>';
                echo '</tr>';
                echo '<tr><td style="font-weight:bold;background:#f8f9fa;">Ceklist</td>';
                echo '<td></td>';
                echo '<td style="text-align:center;">' . (in_array('approval_exec_1m', $checkedValues) ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '<td style="text-align:center;">' . (in_array('approval_exec_10m', $checkedValues) ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '<td style="text-align:center;">';
                if (in_array('approval_exec_10m_1', $checkedValues)) echo '<span class="excel-result-check yes">✓</span> ';
                if (in_array('approval_exec_10m_2', $checkedValues)) echo '<span class="excel-result-check yes">✓</span> ';
                if (in_array('approval_exec_10m_3', $checkedValues)) echo '<span class="excel-result-check yes">✓</span>';
                if (!in_array('approval_exec_10m_1', $checkedValues) && !in_array('approval_exec_10m_2', $checkedValues) && !in_array('approval_exec_10m_3', $checkedValues)) echo '-';
                echo '</td>';
                echo '</tr>';
                
                echo '</table>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Section 4: Dokumen Foto (item_order 5)
            else if ($section['section_order'] == 4 && $item['item_order'] == 5 && $item['field_type'] == 'file') {
                echo '<tr>';
                echo '<td><span class="item-number">5.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                echo '<td colspan="3">';
                
                if (isset($item['response_value']) && !empty($item['response_value'])) {
                    $photos = explode(',', $item['response_value']);
                    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-top:10px;">';
                    foreach ($photos as $photo) {
                        $photo = trim($photo);
                        if (!empty($photo)) {
                            $photoPath = '../uploads/photos/' . $photo;
                            echo '<div style="border:1px solid #dee2e6;border-radius:4px;overflow:hidden;">';
                            echo '<img src="' . htmlspecialchars($photoPath) . '" alt="Dokumen Foto" style="width:100%;height:150px;object-fit:cover;">';
                            echo '<div style="padding:5px;background:#f8f9fa;font-size:11px;text-align:center;word-break:break-all;">' . htmlspecialchars($photo) . '</div>';
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                } else {
                    echo '<em style="color:#6c757d;">Tidak ada foto</em>';
                }
                
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            // Regular items: Ada/Tidak ada + Tanggal
            else if ($item['field_type'] == 'radio') {
                // Special handling untuk Section 3
                if ($section['section_order'] == 3) {
                    // Item 1: Konfirmasi Qty + Brdsk Qty SPK
                    if ($item['item_order'] == 1) {
                        $qtyItem = null;
                        foreach ($items as $qi) {
                            if ($qi['item_order'] == 22 && $qi['field_type'] == 'number') {
                                $qtyItem = $qi;
                                $processed[] = $qi['item_id'];
                                break;
                            }
                        }
                        
                        echo '<tr>';
                        echo '<td><span class="item-number">1.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                        echo '<td class="excel-cell-center">';
                        echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                        echo '</td>';
                        echo '<td class="excel-cell-center">';
                        echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                        echo '</td>';
                        echo '<td>';
                        echo '<strong>Brdsk Qty SPK</strong> ';
                        echo ($qtyItem && isset($qtyItem['response_value']) && $qtyItem['response_value']) ? formatHarga($qtyItem['response_value']) : 'Rp';
                        echo '</td>';
                        echo '</tr>';
                        $displayOrder++;
                    }
                    // Item 2: Bukti Transfer I + Tanggal
                    else if ($item['item_order'] == 2) {
                        $dateItem = null;
                        foreach ($items as $di) {
                            if ($di['item_order'] == 23 && $di['field_type'] == 'date') {
                                $dateItem = $di;
                                $processed[] = $di['item_id'];
                                break;
                            }
                        }
                        
                        echo '<tr>';
                        echo '<td><span class="item-number">2.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                        echo '<td class="excel-cell-center">';
                        echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                        echo '</td>';
                        echo '<td class="excel-cell-center">';
                        echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                        echo '</td>';
                        echo '<td class="excel-cell-center">';
                        echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                        echo '</td>';
                        echo '</tr>';
                        $displayOrder++;
                        
                        // Sub-items: Nilai transfer I dan Info konfirmasi
                        $nilaiItem = null;
                        $infoItem = null;
                        foreach ($items as $ni) {
                            if ($ni['item_order'] == 24) {
                                $nilaiItem = $ni;
                                $processed[] = $ni['item_id'];
                            }
                            if ($ni['item_order'] == 25) {
                                $infoItem = $ni;
                                $processed[] = $ni['item_id'];
                            }
                        }
                        
                        // Nilai transfer I
                        if ($nilaiItem) {
                            echo '<tr>';
                            echo '<td style="padding-left:30px;">' . htmlspecialchars($nilaiItem['item_text']) . '</td>';
                            echo '<td colspan="3">';
                            echo (isset($nilaiItem['response_value']) && $nilaiItem['response_value']) ? formatHarga($nilaiItem['response_value']) : 'Rp';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        // Info konfirmasi
                        if ($infoItem) {
                            echo '<tr>';
                            echo '<td style="padding-left:30px;">' . htmlspecialchars($infoItem['item_text']) . '</td>';
                            echo '<td colspan="3" style="text-align:center;">';
                            if (isset($infoItem['response_value'])) {
                                if ($infoItem['response_value'] == 'sesuai') {
                                    echo '<span class="excel-result-check yes">✓ Sesuai</span>';
                                } else if ($infoItem['response_value'] == 'tidak_sesuai') {
                                    echo '<span class="excel-result-check no">✗ Tidak Sesuai</span>';
                                } else {
                                    echo '-';
                                }
                            } else {
                                echo '-';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    }
                    // Item 3: Email instruksi + Tanggal
                    else if ($item['item_order'] == 3) {
                        $dateItem = null;
                        foreach ($items as $di) {
                            if ($di['item_order'] == 31 && $di['field_type'] == 'date') {
                                $dateItem = $di;
                                $processed[] = $di['item_id'];
                                break;
                            }
                        }
                        
                        echo '<tr>';
                        echo '<td><span class="item-number">3.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                        echo '<td class="excel-cell-center">';
                        echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                        echo '</td>';
                        echo '<td class="excel-cell-center">';
                        echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                        echo '</td>';
                        echo '<td class="excel-cell-center">';
                        echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                        echo '</td>';
                        echo '</tr>';
                        $displayOrder++;
                    }
                }
                // Default rendering untuk section lain
                else {
                    $dateItem = null;
                    
                    // Cari tanggal items
                    foreach ($items as $di) {
                        if ($di['item_order'] == ($item['item_order'] * 10 + 1) && $di['field_type'] == 'date') {
                            $dateItem = $di;
                            $processed[] = $di['item_id'];
                            break;
                        }
                    }
                    
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($item['item_text']) . '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                    echo '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($item['response_value']) && $item['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                    echo '</td>';
                    echo '<td class="excel-cell-center">';
                    echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
            }
        }
    }
    
    // RENDER FUNCTION UNTUK PO NON OA
    function renderPONonOAItems($section, &$displayOrder) {
        $items = $section['items'];
        
        // Section 2: Pengajuan Pembelian (Ada/Tidak ada/Tanggal)
        if ($section['section_order'] == 2) {
            $labels = ['Pre PR', 'RAP', 'Drawing / Gambar', 'Approval Spec', 'PR fully approved'];
            $baseOrders = [1, 4, 7, 10, 13];
            
            foreach ($labels as $idx => $label) {
                $baseOrder = $baseOrders[$idx];
                $adaItem = null;
                $tidakItem = null;
                $dateItem = null;
                
                foreach ($items as $item) {
                    if ($item['item_order'] == $baseOrder) $adaItem = $item;
                    if ($item['item_order'] == $baseOrder + 1) $tidakItem = $item;
                    if ($item['item_order'] == $baseOrder + 2) $dateItem = $item;
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
                echo '<td class="excel-cell-center">';
                echo ($adaItem && isset($adaItem['response_value']) && $adaItem['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($tidakItem && isset($tidakItem['response_value']) && $tidakItem['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($dateItem && isset($dateItem['response_value']) && $dateItem['response_value']) ? formatDate($dateItem['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
        // Section 3: Pelaksanaan Pembelian (Penawaran harga)
        else if ($section['section_order'] == 3) {
            // Items 1-3: Penawaran harga (nama vendor + harga)
            for ($i = 1; $i <= 3; $i++) {
                $namaItem = null;
                $hargaItem = null;
                
                foreach ($items as $item) {
                    if ($item['item_order'] == ($i * 2 - 1) && $item['field_type'] == 'text') {
                        $namaItem = $item;
                    }
                    if ($item['item_order'] == ($i * 2) && $item['field_type'] == 'number') {
                        $hargaItem = $item;
                    }
                }
                
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Penawaran harga ' . $i . ' (nama Vendor)</td>';
                echo '<td colspan="3" class="excel-result-text">';
                if (isset($namaItem['response_value']) && $namaItem['response_value']) {
                    echo htmlspecialchars($namaItem['response_value']);
                    if ($hargaItem && isset($hargaItem['response_value']) && $hargaItem['response_value']) {
                        echo '<br><strong>' . formatHarga($hargaItem['response_value']) . '</strong>';
                    }
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            
            // Item 4: Approval QCF/Bid - tampilkan tabel authorized parties
            $approvalItem = null;
            $approvalSelularItem = null;
            foreach ($items as $item) {
                if ($item['item_order'] == 7 && $item['field_type'] == 'text') {
                    $approvalItem = $item;
                }
                if (stripos($item['item_text'], 'Approval Selular') !== false) {
                    $approvalSelularItem = $item;
                }
            }
            
            if ($approvalItem) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Approval QCF / Bid</td>';
                echo '<td colspan="3" class="excel-result-text">';
                echo '<div style="padding:10px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;">';
                echo '<strong>Authorized Parties:</strong><br>';
                echo '<table style="width:100%;margin-top:8px;font-size:11px;border-collapse:collapse;" border="1" cellpadding="4">';
                echo '<tr style="background:#e9ecef;"><th></th><th>< Rp. 100Jt</th><th>> Rp 100Jt</th></tr>';
                echo '<tr><td><strong>Procurement</strong></td><td>Local DS Mgr</td><td>QCF will be managed By CM</td></tr>';
                
                // Row Selular dengan checkmark - support multiple selections
                $selularValue = $approvalSelularItem['response_value'] ?? '';
                $hasLow = (strpos($selularValue, '<100jt') !== false);
                $hasHigh = (strpos($selularValue, '>100jt') !== false);
                echo '<tr><td><strong>Selular</strong></td>';
                echo '<td style="text-align:center;">' . ($hasLow ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '<td style="text-align:center;">' . ($hasHigh ? '<span class="excel-result-check yes">✓</span>' : '-') . '</td>';
                echo '</tr>';
                
                echo '</table>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            
            // Item 5: QCF/Bid (Ada/Tidak ada)
            $qcfAdaItem = null;
            $qcfTidakItem = null;
            $qcfDateItem = null;
            foreach ($items as $item) {
                if ($item['item_text'] === 'QCF / Bid - Ada') $qcfAdaItem = $item;
                if ($item['item_text'] === 'QCF / Bid - Tidak ada') $qcfTidakItem = $item;
                if ($item['item_text'] === 'QCF / Bid - Tanggal') $qcfDateItem = $item;
            }
            
            if ($qcfAdaItem) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> QCF / Bid</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($qcfAdaItem['response_value']) && $qcfAdaItem['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($qcfTidakItem && isset($qcfTidakItem['response_value']) && $qcfTidakItem['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($qcfDateItem && isset($qcfDateItem['response_value']) && $qcfDateItem['response_value']) ? formatDate($qcfDateItem['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
            
            // Item 6: Nego (Ada/Tidak ada/Tanggal)
            $negoAdaItem = null;
            $negoTidakItem = null;
            $negoDateItem = null;
            foreach ($items as $item) {
                if ($item['item_text'] === 'Nego - Ada') $negoAdaItem = $item;
                if ($item['item_text'] === 'Nego - Tidak ada') $negoTidakItem = $item;
                if ($item['item_text'] === 'Nego - Tanggal') $negoDateItem = $item;
            }
            
            if ($negoAdaItem) {
                echo '<tr>';
                echo '<td><span class="item-number">' . $displayOrder . '.</span> Nego</td>';
                echo '<td class="excel-cell-center">';
                echo (isset($negoAdaItem['response_value']) && $negoAdaItem['response_value'] == 'ada') ? '<span class="excel-result-check yes">✓</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($negoTidakItem && isset($negoTidakItem['response_value']) && $negoTidakItem['response_value'] == 'tidak_ada') ? '<span class="excel-result-check no">✗</span>' : '-';
                echo '</td>';
                echo '<td class="excel-cell-center">';
                echo ($negoDateItem && isset($negoDateItem['response_value']) && $negoDateItem['response_value']) ? formatDate($negoDateItem['response_value']) : '-';
                echo '</td>';
                echo '</tr>';
                $displayOrder++;
            }
        }
        // Section 4: PO (Sesuai/Tidak)
        else if ($section['section_order'] == 4) {
            // Items 1-8: dengan pola Sesuai/Tidak
            $labels = [
                'Cek Nama Vendor',
                'Cek Kembali ke RAP/Spec yang disetujui User',
                'Cek Kembali ke Penawaran',
                'Cek Tax Code',
                'Cek TOP',
                'Cek DD',
                'Input Note Tambahan PO',
                'Kirim PO ke Vendor'
            ];
            
            foreach ($labels as $idx => $label) {
                // Cari item berdasarkan item_text
                $sesuaiItem = null;
                $tidakItem = null;
                
                foreach ($items as $item) {
                    if (stripos($item['item_text'], $label . ' - Sesuai') !== false) {
                        $sesuaiItem = $item;
                    }
                    if (stripos($item['item_text'], $label . ' - Tidak') !== false) {
                        $tidakItem = $item;
                    }
                }
                
                if ($sesuaiItem && $tidakItem) {
                    echo '<tr>';
                    echo '<td><span class="item-number">' . $displayOrder . '.</span> ' . htmlspecialchars($label) . '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($sesuaiItem['response_value']) && $sesuaiItem['response_value'] == 'sesuai') ? '<span class="excel-result-check yes">✓</span>' : '-';
                    echo '</td>';
                    echo '<td class="excel-cell-center">';
                    echo (isset($tidakItem['response_value']) && $tidakItem['response_value'] == 'tidak') ? '<span class="excel-result-check no">✗</span>' : '-';
                    echo '</td>';
                    echo '</tr>';
                    $displayOrder++;
                }
            }
        }
    }
    
    // Execute render
    renderTemplateItems($submission['template_id'], $sections, $submission);
    ?>
    
    <?php if ($submission['notes']): ?>
    <table class="excel-info-table">
        <tr>
            <td width="200">Catatan Tambahan</td>
            <td><?php echo nl2br(htmlspecialchars($submission['notes'])); ?></td>
        </tr>
    </table>
    <?php endif; ?>
    
    <!-- Kriteria Penilaian Audit -->
    <div class="card" style="margin-top: 20px; background: #f8f9fa; border-left: 4px solid #007bff;">
        <h4 style="color: #007bff; margin-bottom: 15px;">📊 Kriteria Penilaian Status Audit</h4>
        
        <!-- Kriteria Standar untuk Semua Template -->
        <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
            <strong style="color: #28a745;">✓ Lengkap</strong>
            <ul style="margin: 8px 0 0 20px; font-size: 14px;">
                <li>Item wajib minimal 80% terpenuhi</li>
                <li>Kelengkapan dokumen minimal 60%</li>
                <li>Informasi transaksi sudah lengkap</li>
            </ul>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
            <strong style="color: #ffc107;">⚠ Perlu Dilengkapi</strong>
            <ul style="margin: 8px 0 0 20px; font-size: 14px;">
                <li>Item wajib 60-79% terpenuhi</li>
                <li>Kelengkapan dokumen 40-59%</li>
                <li>Beberapa dokumen masih kurang</li>
            </ul>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px;">
            <strong style="color: #17a2b8;">ℹ Dalam Proses</strong>
            <ul style="margin: 8px 0 0 20px; font-size: 14px;">
                <li>Item wajib kurang dari 60%</li>
                <li>Dokumen masih dalam proses pengisian</li>
                <li>Dapat dilanjutkan kemudian</li>
            </ul>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
