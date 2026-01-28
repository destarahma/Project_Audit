<?php
/**
 * Download PDF - Tampilan sama persis dengan view.php untuk print/PDF
 * File ini akan merender output yang sama dengan view.php tapi dalam mode print
 */

// Set flag bahwa ini adalah mode PDF
define('PDF_MODE', true);

// Load semua requirements dari view.php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

// Function to clean Rupiah format to number
function cleanRupiah($value) {
    if (empty($value)) return 0;
    $cleaned = preg_replace('/[^0-9]/', '', $value);
    return floatval($cleaned);
}

// Function to format number untuk display
function formatHarga($value) {
    if (empty($value)) return '-';
    if (stripos($value, 'Rp') !== false) {
        return $value;
    }
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
    die('Audit tidak ditemukan');
}

// Check permission
if (!isAdmin() && $submission['submitted_by'] != $currentUser['id']) {
    die('Anda tidak memiliki akses ke audit ini');
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
$allItems = [];
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

$pageTitle = 'Detail Audit - ' . $submission['template_name'];

// Load business logic
require_once '../includes/business_logic.php';
$bl = new BusinessLogic($conn);

// Load render functions
require_once 'view_render_functions.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/excel-style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
        }
        .no-print {
            text-align: right;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .no-print button {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        .btn-print {
            background: #C41E3A;
            color: white;
        }
        .btn-print:hover {
            background: #a01729;
        }
        .btn-close {
            background: #6c757d;
            color: white;
        }
        .btn-close:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn-print">
        🖨️ Cetak / Simpan PDF
    </button>
    <button onclick="window.close()" class="btn-close">
        ✕ Tutup
    </button>
</div>

<div class="card excel-form">
    <div class="excel-header">
        <div style="font-size: 13px; opacity: 0.85; margin-bottom: 8px; letter-spacing: 1px;">Departemen Procurement</div>
        <h2><?php echo htmlspecialchars($submission['template_name']); ?></h2>
        <div style="margin-top: 10px; font-size: 14px; opacity: 0.9;">
            Nomor: <?php 
            $shortCode = preg_replace('/_\d{3}$/', '', $submission['template_code']);
            echo htmlspecialchars($shortCode) . '_' . str_pad($submission['audit_number'], 5, '0', STR_PAD_LEFT); 
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
    <?php if ($submission['percentage_score'] > 0): ?>
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
            ?>
            <div class="excel-score-status <?php echo $statusClass; ?>"><?php echo $displayStatus; ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Info Table -->
    <?php if ($submission['template_id'] == 9 || $submission['template_id'] == 10): ?>
    <!-- PO Tagging OA / PO Non OA -->
    <table class="excel-info-table">
        <tr>
            <td>Auditor</td>
            <td colspan="3"><?php echo htmlspecialchars($submission['auditor_name']); ?></td>
        </tr>
        <?php
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
            
            if (!empty($hargaSatuan)) {
                $hargaSatuanClean = preg_replace('/[^0-9]/', '', $hargaSatuan);
            } else {
                $hargaSatuanClean = '';
            }
            
            if (!empty($totalHargaRaw)) {
                $totalHarga = preg_replace('/[^0-9]/', '', $totalHargaRaw);
            } else if (!empty($qty) && !empty($hargaSatuanClean)) {
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
    </table>
    <?php endif; ?>
    
    <!-- Checklist Results -->
    <?php
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
</div>

<script>
window.onload = function() {
    // Auto print setelah halaman dimuat
    setTimeout(function() {
        window.print();
    }, 500);
};
</script>

</body>
</html>
<?php
// Tutup koneksi database setelah semua rendering selesai
if (isset($conn) && $conn) {
    $conn->close();
}
?>
