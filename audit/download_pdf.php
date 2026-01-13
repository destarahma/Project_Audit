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
    SELECT s.*, t.template_name, t.template_code, t.max_score, t.audit_type, u.full_name as auditor_name
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

// Set headers for PDF download (browser will handle the printing)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Audit - <?php echo htmlspecialchars($submission['template_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #C41E3A;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #C41E3A;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 18px;
            color: #666;
            font-weight: normal;
        }
        
        .info-section {
            margin-bottom: 20px;
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-row label {
            font-weight: bold;
            width: 180px;
        }
        
        .score-section {
            background: #e6f7ff;
            border: 2px solid #1890ff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .score-item {
            flex: 1;
        }
        
        .score-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .score-value {
            font-size: 24px;
            font-weight: bold;
            color: #C41E3A;
        }
        
        .score-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-weight: bold;
            color: white;
            background: #52c41a;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section h3 {
            background: #C41E3A;
            color: white;
            padding: 10px;
            font-size: 14px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        table th {
            background: #f0f0f0;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            border: 1px solid #ddd;
        }
        
        table td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .check-yes {
            color: #52c41a;
            font-size: 18px;
            font-weight: bold;
        }
        
        .check-no {
            color: #ff4d4f;
            font-size: 18px;
            font-weight: bold;
        }
        
        .notes {
            background: #fffbeb;
            border-left: 4px solid #faad14;
            padding: 15px;
            margin-top: 20px;
        }
        
        .notes h4 {
            margin-bottom: 10px;
            color: #ad6800;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        
        .signature-box {
            width: 45%;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            @page {
                margin: 2cm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="background: #C41E3A; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
            🖨️ Cetak / Simpan PDF
        </button>
        <button onclick="window.close()" style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-left: 10px;">
            ✕ Tutup
        </button>
    </div>

    <div class="header">
        <h1>LAPORAN SELF AUDIT</h1>
        <h2><?php echo htmlspecialchars($submission['template_name']); ?></h2>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <label>Nomor Audit:</label>
            <span>AUD-<?php echo str_pad($submission['id'], 5, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="info-row">
            <label>Auditor:</label>
            <span><?php echo htmlspecialchars($submission['auditor_name']); ?></span>
        </div>
        <div class="info-row">
            <label>Tanggal Audit:</label>
            <span><?php echo date('d F Y', strtotime($submission['submission_date'])); ?></span>
        </div>
        <div class="info-row">
            <label>Tanggal Dibuat:</label>
            <span><?php echo date('d F Y H:i', strtotime($submission['created_at'])); ?></span>
        </div>
        <div class="info-row">
            <label>Status:</label>
            <span><strong><?php echo strtoupper($submission['status']); ?></strong></span>
        </div>
    </div>
    
    <?php if ($submission['total_score'] > 0 || $submission['percentage_score'] > 0): ?>
    <div class="score-section">
        <div class="score-item">
            <div class="score-label">Total Skor</div>
            <div class="score-value"><?php echo $submission['total_score']; ?> / <?php echo $submission['max_score']; ?></div>
        </div>
        <div class="score-item">
            <div class="score-label">Persentase</div>
            <div class="score-value" style="color: #1890ff;"><?php echo number_format($submission['percentage_score'], 1); ?>%</div>
        </div>
        <div class="score-item">
            <div class="score-label">Status Audit</div>
            <div class="score-status"><?php echo $submission['auto_status']; ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($submission['template_code'] === 'MIX_OIL_001' && $submission['seller_name']): ?>
    <div class="section">
        <h3>Informasi Umum</h3>
        <table>
            <tr>
                <td width="30%"><strong>Penjualan Mix Oil:</strong></td>
                <td><?php echo htmlspecialchars($submission['seller_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Qty:</strong></td>
                <td><?php echo htmlspecialchars($submission['quantity']); ?></td>
            </tr>
            <tr>
                <td><strong>Harga Satuan:</strong></td>
                <td><?php echo htmlspecialchars($submission['unit_price']); ?></td>
            </tr>
            <tr>
                <td><strong>Total Harga:</strong></td>
                <td><?php echo htmlspecialchars($submission['total_price']); ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
    
    <h3 style="margin: 20px 0 15px 0; font-size: 16px;">HASIL CHECKLIST AUDIT</h3>
    
    <?php foreach ($sections as $section): ?>
    <div class="section">
        <h3><?php echo $section['section_order']; ?>. <?php echo htmlspecialchars($section['section_title']); ?></h3>
        
        <table>
            <thead>
                <tr>
                    <th width="50%">Item</th>
                    <th width="10%">Bobot</th>
                    <th width="20%">Ya/Ada</th>
                    <th width="20%">Tidak</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($section['items'] as $item): ?>
                <tr>
                    <td><?php echo $item['item_order']; ?>. <?php echo htmlspecialchars($item['item_text']); ?></td>
                    <td class="text-center"><?php echo $item['score_value'] > 0 ? $item['score_value'] : '-'; ?></td>
                    <?php if ($item['field_type'] === 'checkbox' || $item['field_type'] === 'radio'): ?>
                        <td class="text-center">
                            <?php if ($item['response_value'] === 'ada' || $item['response_value'] === 'sesuai'): ?>
                            <span class="check-yes">✓</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($item['response_value'] === 'tidak_ada' || $item['response_value'] === 'tidak_sesuai'): ?>
                            <span class="check-no">✗</span>
                            <?php endif; ?>
                        </td>
                    <?php else: ?>
                        <td colspan="2">
                            <?php 
                            if ($item['field_type'] === 'date' && $item['response_value']) {
                                echo date('d F Y', strtotime($item['response_value']));
                            } else {
                                echo htmlspecialchars($item['response_value'] ?: '-');
                            }
                            ?>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
    
    <?php if ($submission['notes']): ?>
    <div class="notes">
        <h4>Catatan:</h4>
        <p><?php echo nl2br(htmlspecialchars($submission['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="signature-section">
        <div class="signature-box">
            <p>Dibuat oleh,</p>
            <div class="signature-line">
                <?php echo htmlspecialchars($submission['auditor_name']); ?>
            </div>
        </div>
        <div class="signature-box">
            <p>Disetujui oleh,</p>
            <div class="signature-line">
                ( _________________ )
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>Dokumen ini dihasilkan secara otomatis oleh Sistem Audit Management Digital</p>
        <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
    </div>
    
    <script>
        // Auto print when opened in new window
        // Uncomment the line below if you want auto-print
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
