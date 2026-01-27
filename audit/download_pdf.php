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

// Sertakan business logic dan fungsi render dari view.php
require_once '../includes/business_logic.php';
$bl = getBusinessLogic();

// Fungsi cleanRupiah dan formatHarga dari view.php
function cleanRupiah($value) {
    if (empty($value)) return 0;
    $cleaned = preg_replace('/[^0-9]/', '', $value);
    return floatval($cleaned);
}
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

// Business validation (khusus Mix Oil)
$businessValidation = null;
if ($submission['template_code'] === 'MIX_OIL') {
    $totalPrice = floatval($submission['total_price']);
    $quantity = floatval($submission['quantity']);
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

// Sertakan CSS utama
echo '<link rel="stylesheet" href="../assets/css/style.css">';
echo '<link rel="stylesheet" href="../assets/css/excel-style.css">';

// Blok render utama dari view.php (tanpa header/footer interaktif)
echo '<div class="card excel-form">';
include __DIR__ . '/view_render_block.php';
echo '</div>';

echo '<script>window.print();</script>';
exit;
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

    <div class="pdf-container">
        <div class="header">
            <h1>LAPORAN SELF AUDIT</h1>
            <h2>Self Audit : <?php echo htmlspecialchars($submission['template_name']); ?></h2>
        </div>
        <div class="info-section">
            <div class="info-block">
                <div class="info-label">Nomor Audit:</div>
                <div class="info-value">AUD-<?php echo str_pad($submission['id'], 5, '0', STR_PAD_LEFT); ?></div>
            </div>
            <div class="info-block">
                <div class="info-label">Auditor:</div>
                <div class="info-value"><?php echo htmlspecialchars($submission['auditor_name']); ?></div>
            </div>
            <div class="info-block">
                <div class="info-label">Tanggal Audit:</div>
                <div class="info-value"><?php echo date('d F Y', strtotime($submission['submission_date'])); ?></div>
            </div>
            <div class="info-block">
                <div class="info-label">Tanggal Dibuat:</div>
                <div class="info-value"><?php echo date('d F Y H:i', strtotime($submission['created_at'])); ?></div>
            </div>
            <div class="info-block">
                <div class="info-label">Status:</div>
                <div class="info-value"><strong><?php echo strtoupper($submission['status']); ?></strong></div>
            </div>
        </div>
    
    <?php if ($submission['total_score'] > 0 || $submission['percentage_score'] > 0): ?>
    <div class="score-section">
        <div class="score-item">
            <div class="score-label">TOTAL SKOR</div>
            <div class="score-value"><?php echo $submission['total_score']; ?> / <?php echo $submission['max_score']; ?></div>
        </div>
        <div class="score-item">
            <div class="score-label">PERSENTASE</div>
            <div class="score-value blue"><?php echo number_format($submission['percentage_score'], 1); ?>%</div>
        </div>
        <div class="score-item">
            <div class="score-label">STATUS AUDIT</div>
            <span class="score-status"><?php echo $submission['auto_status']; ?></span>
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
    
    <h3 style="margin: 20px 0 15px 0; font-size: 16px; color:#C41E3A; font-weight:700;">HASIL CHECKLIST AUDIT</h3>
    
    <?php foreach ($sections as $section): ?>
    <div class="section">
        <h3><?php echo $section['section_order']; ?>. <?php echo htmlspecialchars($section['section_title']); ?></h3>
        <table>
            <thead>
                <tr>
                    <th width="50%">Item</th>
                    <th width="10%">Bobot</th>
                    <th width="20%">Ada / Ya</th>
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
    </div>
    <script>
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
