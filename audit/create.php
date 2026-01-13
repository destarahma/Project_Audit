<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/business_logic.php';

requireLogin();

$pageTitle = 'Buat Audit Baru';
$currentUser = getCurrentUser();

// Get template_id from URL
$templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

$conn = getConnection();
$bl = getBusinessLogic();

// Get template info
if ($templateId > 0) {
    $stmt = $conn->prepare("SELECT * FROM audit_templates WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $templateId);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$template) {
        flashMessage('Template tidak ditemukan', 'danger');
        redirect('audit/select_type.php');
    }
}

// Load Excel CSS
echo '<link rel="stylesheet" href="../assets/css/excel-style.css">';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateId = $_POST['template_id'];
    $submissionDate = $_POST['submission_date'];
    $sellerName = sanitize($_POST['seller_name'] ?? '');
    $unitLocation = sanitize($_POST['unit_location'] ?? '');
    $quantity = sanitize($_POST['quantity'] ?? '');
    $unitPrice = sanitize($_POST['unit_price'] ?? '');
    $totalPrice = sanitize($_POST['total_price'] ?? '');
    $hasRefund = isset($_POST['has_refund']) ? 1 : 0;
    $notes = sanitize($_POST['notes'] ?? '');
    
    // ========================================
    // DIGITALISASI VALIDASI #0: Tanggal wajib jika "Ada"
    // Note: Validasi ini di-handle di client-side (JavaScript) untuk UX lebih baik
    // Backend akan menerima data tanggal sesuai dengan pilihan user
    // ========================================
    
    // ========================================
    // DIGITALISASI VALIDASI #1: DP Minimal 50%
    // ========================================
    if (isset($_POST['dp_amount']) && !empty($_POST['dp_amount']) && !empty($totalPrice)) {
        $dpValidation = $bl->validateDP($_POST['dp_amount'], $totalPrice);
        if (!$dpValidation['valid']) {
            $errors[] = $dpValidation['message'];
        }
    }
    
    // ========================================
    // DIGITALISASI VALIDASI #2: Pelunasan
    // ========================================
    if (isset($_POST['dp_amount2']) && !empty($totalPrice)) {
        $paymentValidation = $bl->validatePaymentComplete(
            $_POST['dp_amount'] ?? '0', 
            $_POST['dp_amount2'] ?? '0', 
            $totalPrice
        );
        if (!$paymentValidation['valid']) {
            $errors[] = $paymentValidation['message'];
        }
    }
    
    // ========================================
    // DIGITALISASI VALIDASI #3: Qty vs SPK
    // ========================================
    if (isset($_POST['actual_qty']) && isset($_POST['spk_qty'])) {
        $qtyValidation = $bl->validateQuantity($_POST['actual_qty'], $_POST['spk_qty']);
        if (!$qtyValidation['valid']) {
            $errors[] = $qtyValidation['message'];
        }
    }
    
    // If validation errors, show them
    if (!empty($errors)) {
        foreach ($errors as $error) {
            flashMessage($error, 'danger');
        }
    } else {
        // ========================================
        // DIGITALISASI LOGIKA BISNIS: Approval Otomatis
        // ========================================
        $approvalData = $bl->getRequiredApprovals($templateId, $totalPrice);
        $approvalText = $approvalData['approval_text'];
        $approvalLevel = $approvalData['level'];
        
        // Calculate score
        $totalScore = 0;
        $maxScore = 0;
        
        // Get template items with scores
        $itemsQuery = $conn->query("
            SELECT ti.id, ti.score_value, ti.field_type
            FROM template_items ti
            JOIN template_sections ts ON ti.section_id = ts.id
            WHERE ts.template_id = $templateId
        ");
        
        $items = [];
        while ($item = $itemsQuery->fetch_assoc()) {
            $items[$item['id']] = $item;
            $maxScore += $item['score_value'];
        }
    
    // Calculate score from responses
    if (isset($_POST['responses'])) {
        foreach ($_POST['responses'] as $itemId => $value) {
            if (isset($items[$itemId])) {
                $scoreValue = $items[$itemId]['score_value'];
                $fieldType = $items[$itemId]['field_type'];
                
                // Give score if answered positively
                if ($fieldType === 'checkbox' || $fieldType === 'radio') {
                    if ($value === 'ada' || $value === 'sesuai' || $value === 'ya') {
                        $totalScore += $scoreValue;
                    }
                }
            }
        }
    }
    
        // Calculate percentage
        $percentageScore = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        
        // Auto status based on score
        $autoStatus = '';
        if ($percentageScore >= 90) {
            $autoStatus = 'Sangat Baik';
        } elseif ($percentageScore >= 75) {
            $autoStatus = 'Baik';
        } elseif ($percentageScore >= 60) {
            $autoStatus = 'Cukup';
        } else {
            $autoStatus = 'Perlu Perbaikan';
        }
        
        // Insert submission with approval info
        $stmt = $conn->prepare("
            INSERT INTO audit_submissions 
            (template_id, submitted_by, submission_date, seller_name, unit_location, quantity, unit_price, total_price, 
             total_score, percentage_score, auto_status, required_approvals, approval_level, has_refund, status, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?)
        ");
        $stmt->bind_param("iissssssdissiis", 
            $templateId, $currentUser['id'], $submissionDate, $sellerName, $unitLocation,
            $quantity, $unitPrice, $totalPrice, $totalScore, $percentageScore, $autoStatus, 
            $approvalText, $approvalLevel, $hasRefund, $notes
        );
        
        if ($stmt->execute()) {
            $submissionId = $stmt->insert_id;
            
            // Save responses
            if (isset($_POST['responses'])) {
                $stmtResponse = $conn->prepare("INSERT INTO audit_responses (submission_id, item_id, response_value) VALUES (?, ?, ?)");
                
                foreach ($_POST['responses'] as $itemId => $value) {
                    $responseValue = is_array($value) ? $value['value'] : $value;
                    $stmtResponse->bind_param("iis", $submissionId, $itemId, $responseValue);
                    $stmtResponse->execute();
                }
                $stmtResponse->close();
            }
            
            // Add approval info to notes if exists
            if (!empty($approvalText)) {
                $approvalNote = "\n\n[APPROVAL DIPERLUKAN]: " . $approvalText;
                $conn->query("UPDATE audit_submissions SET notes = CONCAT(IFNULL(notes, ''), '$approvalNote') WHERE id = $submissionId");
            }
            
            flashMessage('Audit berhasil disimpan. Skor: ' . number_format($percentageScore, 1) . '% (' . $autoStatus . '). Approval: ' . $approvalText, 'success');
            redirect('audit/view.php?id=' . $submissionId);
        } else {
            flashMessage('Gagal menyimpan audit', 'danger');
        }
        
        $stmt->close();
    }
}

$conn->close();

include '../includes/header.php';
?>

<div class="page-header">
    <h1>Buat Audit Baru</h1>
    <a href="select_type.php" class="btn btn-secondary">← Kembali</a>
</div>

<?php if ($templateId > 0): ?>
<div class="card excel-form">
    <div class="excel-header">
        <h2><?php echo htmlspecialchars($template['template_name']); ?></h2>
    </div>
    
    <form method="POST" action="" id="auditForm">
        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
        
        <!-- Header Information Table -->
        <table class="excel-info-table">
            <tr>
                <td>Penjualan Mix Oil</td>
                <td colspan="3">
                    <input type="text" name="seller_name" placeholder="Nama penjual/customer">
                </td>
            </tr>
            <tr>
                <td>Unit / Lokasi</td>
                <td colspan="3">
                    <input type="text" name="unit_location" placeholder="Contoh: Jakarta, Surabaya, dll" required>
                </td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>
                    <input type="date" name="submission_date" value="<?php echo date('Y-m-d'); ?>" required>
                </td>
                <td width="120"><strong>Qty</strong></td>
                <td>
                    <input type="text" name="quantity" placeholder="Jumlah" required>
                </td>
            </tr>
            <tr>
                <td>Harga Satuan</td>
                <td>
                    <input type="text" name="unit_price" id="unit_price" placeholder="Rp" required>
                </td>
                <td><strong>Total Harga</strong></td>
                <td>
                    <input type="text" name="total_price" id="total_price" placeholder="Rp" readonly style="background: #f0f0f0;">
                </td>
            </tr>
            <tr>
                <td colspan="4" style="background: #fff3cd; padding: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="has_refund" id="has_refund" value="1">
                        <strong>Ada Pengembalian Dana?</strong> (Centang jika ada overpayment yang perlu dikembalikan)
                    </label>
                </td>
            </tr>
        </table>
        
        <div id="templateFields"></div>
        
        <div class="excel-actions">
            <button type="submit" class="btn btn-primary">💾 Simpan dan Submit</button>
            <a href="select_type.php" class="btn btn-secondary">✕ Batal</a>
        </div>
    </form>
</div>

<script>
// Load template on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTemplate(<?php echo $templateId; ?>);
});

function loadTemplate(templateId) {
    fetch('../api/get_template.php?id=' + templateId)
        .then(response => response.json())
        .then(data => {
            let html = '';
            
            data.sections.forEach(section => {
                html += `<div class="excel-section">`;
                html += `<h3 class="excel-section-header">${section.section_title}</h3>`;
                
                // Section 6: Dokumentasi - tanpa tabel header
                if (section.section_order === 6) {
                    html += `<div style="padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">`;
                    section.items.forEach(item => {
                        html += `<p style="margin: 0; font-size: 14px; color: #495057;">${item.item_text}</p>`;
                    });
                    html += `</div>`;
                } else {
                    // Section lain dengan tabel normal
                    html += `<table class="excel-table">`;
                    html += `<thead><tr>`;
                    html += `<th width="45%">Item</th>`;
                    html += `<th width="15%">Ada</th>`;
                    html += `<th width="15%">Tidak ada</th>`;
                    html += `<th width="25%">Tanggal</th>`;
                    html += `</tr></thead>`;
                    html += `<tbody>`;
                
                section.items.forEach((item, index) => {
                    // Skip items yang akan digabung (harga dan tanggal)
                    if (item.item_order > 10 && item.item_order < 100) {
                        return;
                    }
                    
                    // Skip textarea catatan (item_order >= 100)
                    if (item.item_order >= 100) {
                        return;
                    }
                    
                    html += `<tr>`;
                    
                    // Special handling untuk Section 2
                    if (section.section_order === 2) {
                        // Item 1-3: Penawaran harga (2 kolom: nama vendor + harga)
                        if (item.item_order >= 1 && item.item_order <= 3) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            
                            // Cari field harga yang sesuai (item_order 11, 21, 31)
                            const hargaItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            html += `<td colspan="3">`;
                            html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">`;
                            html += `<input type="text" name="responses[${item.id}]" placeholder="Masukkan nama vendor" style="width:100%;">`;
                            if (hargaItem) {
                                html += `<input type="number" name="responses[${hargaItem.id}]" placeholder="Harga (Rp)" style="width:100%;">`;
                            }
                            html += `</div>`;
                            html += `</td>`;
                        }
                        // Item 4: Approval QCF (menampilkan auto approval routing)
                        else if (item.item_order === 4) {
                            html += `<td><span class="item-number">${item.item_order}.</span> Approval QCF</td>`;
                            html += `<td colspan="3">`;
                            html += `<div id="approvalRouting" style="padding:10px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;">`;
                            html += `<strong>Authorized Parties:</strong><br>`;
                            html += `<div id="approvalPartiesDisplay" style="margin-top:8px;font-size:12px;color:#495057;">`;
                            html += `<em style="color:#6c757d;">Akan ditampilkan otomatis berdasarkan Total Harga</em>`;
                            html += `</div>`;
                            html += `</div>`;
                            html += `<input type="hidden" name="responses[${item.id}]" id="approvalValue" value="">`;
                            html += `</td>`;
                        }
                        // Item 5-10: Radio button dengan kolom tanggal
                        else if (item.item_order >= 5 && item.item_order <= 10) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal yang sesuai (item_order 51, 61, 71, 81, 91, 101)
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            // Semua item 5-10 menggunakan tanggal
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                    }
                    // Special handling untuk Section 3: Penerimaan Pembayaran
                    else if (section.section_order === 3) {
                        // Item 1: Konfirmasi Qty - Radio + Label "Brdsk qty" + Input Harga
                        if (item.item_order === 1) {
                            html += `<td><span class="item-number">1.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                            const ket22 = section.items.find(i => i.item_order === 22);
                            html += `<td>`;
                            html += `<div style="display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:center;">`;
                            html += `<span style="font-weight:500;color:#495057;">Brdsk Qty SPK</span>`;
                            html += `<input type="number" name="responses[${ket22?.id}]" placeholder="Rp" style="width:100%;">`;
                            html += `</div></td>`;
                        }
                        // Item 2: Bukti Transfer I - Radio + Tanggal saja
                        else if (item.item_order === 2) {
                            html += `<td><span class="item-number">2.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            const ket23 = section.items.find(i => i.item_order === 23);
                            html += `<td><input type="date" name="responses[${ket23?.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                        }
                        // Item 3: Nilai trasfer I - TANPA NOMOR, Rp saja (tanpa border hitam)
                        else if (item.item_order === 3) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            html += `<td colspan="3">`;
                            html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;" ${item.is_required ? 'required' : ''}>`;
                            html += `</td>`;
                        }
                        // Item 4: Info konfirmasi I - TANPA NOMOR, Label + Tanggal (dari item 3)
                        else if (item.item_order === 4) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            const date31 = section.items.find(i => i.item_order === 31);
                            html += `<td colspan="3">`;
                            html += `<input type="date" name="responses[${date31?.id}]" style="width:100%;">`;
                            html += `</td>`;
                        }
                        // Item 5: Bukti Transfer II (Nomor 3) - Radio Sesuai/Tidak Sesuai + Tanggal
                        else if (item.item_order === 5) {
                            html += `<td><span class="item-number">3.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="sesuai" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_sesuai" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            const date52 = section.items.find(i => i.item_order === 52);
                            html += `<td><input type="date" name="responses[${date52?.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" placeholder="Tanggal" style="width:100%;"></td>`;
                        }
                        // Item 6: Nilai trasfer II - TANPA NOMOR, Rp saja (tanpa border hitam)
                        else if (item.item_order === 6) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            html += `<td colspan="3">`;
                            html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;" ${item.is_required ? 'required' : ''}>`;
                            html += `</td>`;
                        }
                        // Item 7: Info konfirmasi II - TANPA NOMOR, Radio Sesuai/Tidak Sesuai
                        else if (item.item_order === 7) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="sesuai"></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_sesuai"></td>`;
                            const date61 = section.items.find(i => i.item_order === 61);
                            html += `<td><input type="date" name="responses[${date61?.id}]" style="width:100%;"></td>`;
                        }
                        // Item 8: Sisa - Nomor 4, Rp saja (tanpa border hitam)
                        else if (item.item_order === 8) {
                            html += `<td><span class="item-number">4.</span> ${item.item_text}</td>`;
                            html += `<td colspan="3">`;
                            html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                            html += `</td>`;
                        }
                        // Item 9: Email instruksi - Nomor 5
                        else if (item.item_order === 9) {
                            html += `<td><span class="item-number">5.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada"></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                            const date91 = section.items.find(i => i.item_order === 91);
                            html += `<td><input type="date" name="responses[${date91?.id}]" placeholder="Tanggal" style="width:100%;"></td>`;
                        }
                    }
                    // Special handling untuk Section 4: Mengeluarkan Barang
                    else if (section.section_order === 4) {
                        // Item 1-4: Radio + Tanggal
                        if (item.item_order >= 1 && item.item_order <= 4) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                            
                            // Cari field tanggal (item_order x 10 + 1)
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            html += `<td><input type="date" name="responses[${dateItem?.id}]" placeholder="Tanggal" style="width:100%;"></td>`;
                        }
                        // Item 5: Radio Sesuai/Tidak Sesuai + Tanpa Tanggal
                        else if (item.item_order === 5) {
                            html += `<td><span class="item-number">5.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center">`;
                            html += `<div style="display:flex;flex-direction:column;align-items:center;gap:3px;">`;
                            html += `<input type="radio" name="responses[${item.id}]" value="sesuai" ${item.is_required ? 'required' : ''}>`;
                            html += `<span style="font-size:11px;color:#495057;">Sesuai</span>`;
                            html += `</div>`;
                            html += `</td>`;
                            html += `<td class="excel-cell-center">`;
                            html += `<div style="display:flex;flex-direction:column;align-items:center;gap:3px;">`;
                            html += `<input type="radio" name="responses[${item.id}]" value="tidak_sesuai">`;
                            html += `<span style="font-size:11px;color:#495057;">Tidak Sesuai</span>`;
                            html += `</div>`;
                            html += `</td>`;
                            html += `<td></td>`;
                        }
                    }
                    // Special handling untuk Section 5: Pengembalian Dana
                    else if (section.section_order === 5) {
                        // Item 1 & 2: Radio + Tanggal (bukan keterangan)
                        if (item.item_order === 1 || item.item_order === 2) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal yang sesuai (item_order x 10 + 1)
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            html += `<td><input type="date" name="responses[${dateItem?.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" placeholder="Tanggal" style="width:100%;"></td>`;
                        }
                        // Item 3 (Jumlah): Tanpa checkbox, dengan Rp (tanpa border hitam) + Radio Sesuai/Tidak Sesuai
                        else if (item.item_order === 3) {
                            html += `<td><span class="item-number">3.</span> ${item.item_text}</td>`;
                            html += `<td colspan="2">`;
                            html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                            html += `</td>`;
                            html += `<td>`;
                            html += `<div style="display:flex;gap:15px;justify-content:center;">`;
                            html += `<label style="display:flex;align-items:center;gap:5px;">`;
                            html += `<input type="radio" name="responses_sesuai_${item.id}" value="sesuai">`;
                            html += `<span style="font-size:12px;">Sesuai</span>`;
                            html += `</label>`;
                            html += `<label style="display:flex;align-items:center;gap:5px;">`;
                            html += `<input type="radio" name="responses_sesuai_${item.id}" value="tidak_sesuai">`;
                            html += `<span style="font-size:12px;">Tidak Sesuai</span>`;
                            html += `</label>`;
                            html += `</div>`;
                            html += `</td>`;
                        }
                    }
                    // Default handling untuk section lain
                    else {
                        html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                        
                        if (item.field_type === 'radio') {
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal yang sesuai (item_order x 10 + 1)
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        } else if (item.field_type === 'date') {
                            html += `<td colspan="2" class="excel-cell-gray"><input type="date" name="responses[${item.id}]" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td></td>`;
                        } else if (item.field_type === 'text') {
                            html += `<td colspan="3"><input type="text" name="responses[${item.id}]" placeholder="Masukkan nilai/nama" ${item.is_required ? 'required' : ''}></td>`;
                        } else if (item.field_type === 'number') {
                            html += `<td colspan="3"><input type="number" name="responses[${item.id}]" placeholder="Masukkan nilai" ${item.is_required ? 'required' : ''}></td>`;
                        } else if (item.field_type === 'textarea') {
                            html += `<td colspan="3"><textarea name="responses[${item.id}]" placeholder="Masukkan keterangan" ${item.is_required ? 'required' : ''}></textarea></td>`;
                        } else if (item.field_type === 'checkbox') {
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada"></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                            html += `<td><input type="date" name="responses[${item.id}_date]" style="width:100%;"></td>`;
                        }
                    }
                    
                    html += `</tr>`;
                });
                
                html += `</tbody></table>`;
                }
                
                // Add note if exists
                if (section.section_order === 2) {
                    html += `<div class="excel-note">Note: dikirim ke Kaber jika Mix Oil yg akan dijual masuk area Kaber</div>`;
                }
                
                html += `</div>`;
            });
            
            html += `<table class="excel-info-table">`;
            html += `<tr><td>Catatan Tambahan</td><td colspan="3"><textarea name="notes" rows="3" placeholder="Catatan atau temuan lainnya..." style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;"></textarea></td></tr>`;
            html += `</table>`;
            
            document.getElementById('templateFields').innerHTML = html;
            
            // Setup conditional date validation
            setupConditionalDateValidation();
            
            // Setup auto approval routing display
            setupApprovalRouting();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat template');
        });
}

// Setup conditional date validation - tanggal tidak wajib jika pilih "Tidak Ada"
function setupConditionalDateValidation() {
    // Get all radio buttons
    const radioButtons = document.querySelectorAll('.radio-ada, .radio-tidak-ada');
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            const itemId = this.getAttribute('data-item-id');
            const dateField = document.getElementById(`date-${itemId}`);
            
            if (dateField) {
                if (this.value === 'ada' && this.classList.contains('radio-ada')) {
                    // Jika pilih "Ada", tanggal wajib diisi
                    dateField.required = true;
                    dateField.style.borderColor = '';
                } else if (this.value === 'tidak_ada' && this.classList.contains('radio-tidak-ada')) {
                    // Jika pilih "Tidak Ada", tanggal tidak wajib
                    dateField.required = false;
                    dateField.value = ''; // Clear the date
                    dateField.style.borderColor = '#ced4da';
                }
            }
        });
    });
}

// Setup auto approval routing berdasarkan Total Harga
function setupApprovalRouting() {
    const totalPriceInput = document.querySelector('input[name="total_price"]');
    const approvalDisplay = document.getElementById('approvalPartiesDisplay');
    const approvalValue = document.getElementById('approvalValue');
    
    if (!totalPriceInput || !approvalDisplay) return;
    
    totalPriceInput.addEventListener('input', function() {
        const totalPrice = parseFloat(this.value.replace(/[^0-9]/g, '')) || 0;
        
        let approvalText = '';
        let approvalParties = '';
        let bgColor = '#f8f9fa';
        
        if (totalPrice === 0) {
            approvalText = '<em style="color:#6c757d;">Masukkan Total Harga untuk melihat approval yang diperlukan</em>';
        } else if (totalPrice <= 50000000) {
            // <= 50 Juta
            approvalParties = 'Procurement: Local DS Mgr | Finance: Ref Con';
            approvalText = `
                <div style="background:#d1ecf1;border-left:3px solid #0c5460;padding:10px;margin-top:5px;">
                    <strong style="color:#0c5460;">📋 Level 1: <= Rp 50 Juta</strong><br>
                    <div style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div style="background:white;padding:8px;border-radius:4px;">
                            <strong style="font-size:11px;color:#0c5460;">🏢 Procurement</strong><br>
                            <span style="font-size:12px;color:#495057;">Local DS Mgr</span>
                        </div>
                        <div style="background:white;padding:8px;border-radius:4px;">
                            <strong style="font-size:11px;color:#0c5460;">💰 Finance</strong><br>
                            <span style="font-size:12px;color:#495057;">Ref Con</span>
                        </div>
                    </div>
                </div>
            `;
            bgColor = '#d1ecf1';
        } else if (totalPrice > 50000000 && totalPrice <= 300000000) {
            // 50 - 300 Juta
            approvalParties = 'Procurement: BP DS Agri & Food | Finance: Head of Ops Controller';
            approvalText = `
                <div style="background:#fff3cd;border-left:3px solid #856404;padding:10px;margin-top:5px;">
                    <strong style="color:#856404;">👥 Level 2: Rp 50 Juta - Rp 300 Juta</strong><br>
                    <div style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div style="background:white;padding:8px;border-radius:4px;">
                            <strong style="font-size:11px;color:#856404;">🏢 Procurement</strong><br>
                            <span style="font-size:12px;color:#495057;">BP DS Agri & Food</span>
                        </div>
                        <div style="background:white;padding:8px;border-radius:4px;">
                            <strong style="font-size:11px;color:#856404;">💰 Finance</strong><br>
                            <span style="font-size:12px;color:#495057;">Head of Ops Controller</span>
                        </div>
                    </div>
                </div>
            `;
            bgColor = '#fff3cd';
        } else {
            // > 300 Juta
            approvalParties = 'Procurement: Head of BP US & DS Proc | Finance: DS BU CFO';
            approvalText = `
                <div style="background:#f8d7da;border-left:3px solid #721c24;padding:10px;margin-top:5px;">
                    <strong style="color:#721c24;">🔒 Level 3: > Rp 300 Juta</strong><br>
                    <div style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div style="background:white;padding:8px;border-radius:4px;">
                            <strong style="font-size:11px;color:#721c24;">🏢 Procurement</strong><br>
                            <span style="font-size:12px;color:#495057;">Head of BP US & DS Proc</span>
                        </div>
                        <div style="background:white;padding:8px;border-radius:4px;">
                            <strong style="font-size:11px;color:#721c24;">💰 Finance</strong><br>
                            <span style="font-size:12px;color:#495057;">DS BU CFO</span>
                        </div>
                    </div>
                </div>
            `;
            bgColor = '#f8d7da';
        }
        
        approvalDisplay.innerHTML = approvalText;
        approvalValue.value = approvalParties;
        document.getElementById('approvalRouting').style.background = bgColor;
    });
}

// Auto calculate Total Harga = Qty x Harga Satuan
document.addEventListener('DOMContentLoaded', function() {
    const qtyInput = document.querySelector('input[name="quantity"]');
    const unitPriceInput = document.getElementById('unit_price');
    const totalPriceInput = document.getElementById('total_price');
    
    function calculateTotal() {
        const qty = parseFloat(qtyInput.value) || 0;
        const unitPrice = parseFloat(unitPriceInput.value.replace(/[^0-9]/g, '')) || 0;
        const total = qty * unitPrice;
        
        if (total > 0) {
            totalPriceInput.value = 'Rp ' + total.toLocaleString('id-ID');
        } else {
            totalPriceInput.value = '';
        }
        
        // Trigger approval routing update
        totalPriceInput.dispatchEvent(new Event('input'));
    }
    
    if (qtyInput && unitPriceInput) {
        qtyInput.addEventListener('input', calculateTotal);
        unitPriceInput.addEventListener('input', calculateTotal);
    }
    
    // Conditional Section 5: Pengembalian Dana
    const hasRefundCheckbox = document.getElementById('has_refund');
    if (hasRefundCheckbox) {
        hasRefundCheckbox.addEventListener('change', function() {
            // Akan dihandle saat template dimuat - section 5 akan show/hide
            const section5 = document.querySelector('.excel-section:nth-child(5)');
            if (section5) {
                section5.style.display = this.checked ? 'block' : 'none';
            }
        });
    }
});
</script>

<?php else: ?>
<div class="card">
    <p class="text-center">Silakan pilih jenis audit terlebih dahulu.</p>
    <div class="text-center">
        <a href="select_type.php" class="btn btn-primary">Pilih Jenis Audit</a>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

