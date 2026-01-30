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
    
    // Tentukan status berdasarkan tombol yang diklik
    $saveStatus = isset($_POST['save_as_draft']) ? 'draft' : 'submitted';
    
    // Handle photo upload (optional)
    $photoFiles = '';
    if (isset($_FILES['photo_upload']) && $_FILES['photo_upload']['error'] != UPLOAD_ERR_NO_FILE) {
        $uploadDir = '../uploads/photos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $uploadedFiles = [];
        
        // Check if multiple files
        if (is_array($_FILES['photo_upload']['name'])) {
            $totalFiles = count($_FILES['photo_upload']['name']);
            
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['photo_upload']['error'][$i] == UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['photo_upload']['tmp_name'][$i];
                    $fileName = time() . '_' . $i . '_' . basename($_FILES['photo_upload']['name'][$i]);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $uploadedFiles[] = $fileName;
                    }
                }
            }
        } else {
            // Single file
            if ($_FILES['photo_upload']['error'] == UPLOAD_ERR_OK) {
                $tmpName = $_FILES['photo_upload']['tmp_name'];
                $fileName = time() . '_' . basename($_FILES['photo_upload']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $uploadedFiles[] = $fileName;
                }
            }
        }
        
        $photoFiles = implode(',', $uploadedFiles);
    }
    
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
        
        // Auto status based on unified criteria for all templates
        $autoStatus = '';
        if ($percentageScore >= 80) {
            $autoStatus = 'Lengkap';
        } elseif ($percentageScore >= 60) {
            $autoStatus = 'Perlu Dilengkapi';
        } else {
            $autoStatus = 'Dalam Proses';
        }
        
        // Generate audit number untuk template ini
        $auditNumber = getNextAuditNumber($templateId);
        
        // Insert submission with approval info
        $stmt = $conn->prepare("
            INSERT INTO audit_submissions 
            (template_id, audit_number, submitted_by, submission_date, seller_name, unit_location, quantity, unit_price, total_price, 
             total_score, percentage_score, auto_status, required_approvals, approval_level, has_refund, status, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiissssssdsssiiss", 
            $templateId, $auditNumber, $currentUser['id'], $submissionDate, $sellerName, $unitLocation,
            $quantity, $unitPrice, $totalPrice, $totalScore, $percentageScore, $autoStatus, 
            $approvalText, $approvalLevel, $hasRefund, $saveStatus, $notes
        );
        
        if ($stmt->execute()) {
            $submissionId = $stmt->insert_id;
            
            // Save responses
            if (isset($_POST['responses'])) {
                $stmtResponse = $conn->prepare("INSERT INTO audit_responses (submission_id, item_id, response_value) VALUES (?, ?, ?)");
                
                foreach ($_POST['responses'] as $itemId => $value) {
                    // Skip empty values, kecuali untuk field tertentu yang allowed kosong
                    // Check field type to determine if empty is allowed
                    $checkField = $conn->query("SELECT field_type FROM template_items WHERE id = $itemId");
                    $fieldType = '';
                    if ($checkField && $fieldData = $checkField->fetch_assoc()) {
                        $fieldType = $fieldData['field_type'] ?? '';
                    }
                    
                    // For date, text, and file fields, allow empty values (will be saved as empty string or filename)
                    // For other fields, skip if truly empty
                    if (empty($value) && $value !== '0' && !in_array($fieldType, ['date', 'text', 'textarea', 'file'])) continue;
                    
                    $responseValue = is_array($value) ? $value['value'] : $value;
                    
                    // Skip if responseValue is still empty after processing (except for allowed types)
                    if (empty($responseValue) && $responseValue !== '0' && !in_array($fieldType, ['date', 'text', 'textarea', 'file'])) continue;
                    
                    // Special handling untuk photo field - simpan nama file yang diupload
                    if (!empty($photoFiles)) {
                        // Cek apakah ini field photo (item_order 5 di section 4 atau field_type file)
                        $checkPhoto = $conn->query("SELECT ti.item_order, ti.field_type, ts.section_order FROM template_items ti JOIN template_sections ts ON ti.section_id = ts.id WHERE ti.id = $itemId");
                        if ($checkPhoto && $row = $checkPhoto->fetch_assoc()) {
                            if ($row['field_type'] == 'file' || ($row['section_order'] == 4 && $row['item_order'] == 5)) {
                                $responseValue = $photoFiles;
                            }
                        }
                    }
                    
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
            
            if ($saveStatus === 'draft') {
                flashMessage('Audit berhasil disimpan sebagai Draft. Anda dapat melanjutkan mengisi nanti.', 'success');
            } else {
                flashMessage('Audit berhasil disimpan dan disubmit. Skor: ' . number_format($percentageScore, 1) . '% (' . $autoStatus . '). Approval: ' . $approvalText, 'success');
            }
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

<style>
.is-invalid {
    border: 2px solid #dc3545 !important;
    background-color: #fff5f5 !important;
}

.is-invalid:focus {
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}
</style>

<div class="page-header">
    <h1>Buat Audit Baru</h1>
    <a href="select_type.php" class="btn btn-secondary">← Kembali</a>
</div>

<?php if ($templateId > 0): ?>
<div class="card excel-form">
    <div class="excel-header">
        <h2><?php echo htmlspecialchars($template['template_name']); ?></h2>
    </div>
    
    <form method="POST" action="" id="auditForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
        
        <!-- Header Information Table - Hide for PO Tagging OA and PO Non OA -->
        <?php if ($template['id'] != 9 && $template['id'] != 10): ?>
        <table class="excel-info-table">
            <tr>
                <td><?php echo ($template['id'] == 5) ? 'Penjualan Barbes (Barang Bekas)' : (($template['id'] == 6) ? 'Penjualan Aset' : 'Penjualan Mix Oil'); ?></td>
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
            <?php if ($template['id'] == 5): ?>
            <tr>
                <td>Deskripsi</td>
                <td colspan="3">
                    <textarea name="description" rows="2" placeholder="Deskripsi barang bekas" style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;"></textarea>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($template['id'] == 6): ?>
            <tr>
                <td>Deskripsi</td>
                <td colspan="3">
                    <textarea name="description" rows="2" placeholder="Deskripsi aset" style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;"></textarea>
                </td>
            </tr>
            <?php endif; ?>
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
        <?php else: ?>
        <!-- For PO Tagging OA, header info is part of template fields -->
        <input type="hidden" name="submission_date" value="<?php echo date('Y-m-d'); ?>">
        <?php endif; ?>
        
        <div id="templateFields"></div>
        
        <div class="excel-actions">
            <button type="submit" name="save_as_draft" value="1" class="btn btn-secondary" style="background: #6c757d; color: white;">
                📝 Simpan sebagai Draft
            </button>
            <button type="submit" name="save_and_submit" value="1" class="btn btn-primary">
                💾 Simpan dan Submit
            </button>
            <a href="select_type.php" class="btn btn-secondary">✕ Batal</a>
        </div>
    </form>
</div>

<script>
// Fungsi untuk format Rupiah otomatis (global) - Support hingga Triliun
function formatRupiah(angka) {
    if (!angka) return '';
    
    // Remove all non-numeric characters
    const number_string = angka.toString().replace(/[^0-9]/g, '');
    
    if (number_string === '') return '';
    
    // Split into groups of 3 from right to left
    const split = number_string.split(',');
    const sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    const ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    
    if (ribuan) {
        const separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    
    return rupiah;
}

// Parse Rupiah string to number
function parseRupiah(rupiah) {
    if (!rupiah) return 0;
    return parseInt(rupiah.toString().replace(/[^0-9]/g, '')) || 0;
}

// Auto-apply format Rupiah ke semua input dengan placeholder atau name yang mengandung 'Rp', 'price', 'harga', 'jumlah'
function setupRupiahFormatting() {
    const rupiahInputs = document.querySelectorAll('input[type="text"], input[type="number"]');
    
    rupiahInputs.forEach(input => {
        // Skip if already has listener
        if (input.dataset.rupiahFormatted) return;
        
        const name = input.name.toLowerCase();
        const placeholder = (input.placeholder || '').toLowerCase();
        
        // Check if field is related to money
        if (name.includes('price') || name.includes('harga') || 
            placeholder.includes('rp') || placeholder.includes('harga') ||
            name.includes('jumlah') || placeholder.includes('jumlah')) {
            
            // Skip if it's total_price (readonly) or po_total_price
            if (name === 'total_price' || input.id === 'po_total_price' || input.id === 'total_price' || input.readOnly) {
                return;
            }
            
            // Change to text type to support large numbers
            if (input.type === 'number') {
                input.type = 'text';
            }
            
            input.dataset.rupiahFormatted = 'true';
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('pattern', '[0-9.]*');
            
            input.addEventListener('keyup', function(e) {
                let value = this.value;
                // Remove existing formatting
                value = value.replace(/[^0-9]/g, '');
                // Format and set value
                this.value = formatRupiah(value);
            });
            
            // Prevent non-numeric input
            input.addEventListener('keypress', function(e) {
                const charCode = e.which ? e.which : e.keyCode;
                // Allow: backspace, delete, tab, escape, enter, and .
                if (charCode === 8 || charCode === 9 || charCode === 27 || charCode === 13 || charCode === 46) {
                    return;
                }
                // Ensure that it is a number and stop the keypress
                if (charCode < 48 || charCode > 57) {
                    e.preventDefault();
                }
            });
        }
    });
}

// Load template on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTemplate(<?php echo $templateId; ?>);
});

function loadTemplate(templateId) {
    fetch('../api/get_template.php?id=' + templateId)
        .then(response => response.json())
        .then(data => {
            let html = '';
            
            // Special handling untuk PO Tagging OA (template_id 9)
            if (templateId === 9) {
                renderPOTaggingOATemplate(data);
                return;
            }
            
            // Special handling untuk PO Non OA (template_id 10)
            if (templateId === 10) {
                renderPONonOATemplate(data);
                return;
            }
            
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
                    // Exception: 1) item 11 untuk template Jual Aset section 2
                    //           2) item 24 dan 25 untuk template Jual Aset section 3
                    if (item.item_order > 10 && item.item_order < 100) {
                        if (!(templateId === 6 && section.section_order === 2 && item.item_order === 11) &&
                            !(templateId === 6 && section.section_order === 3 && (item.item_order === 24 || item.item_order === 25))) {
                            return;
                        }
                    }
                    
                    // Skip textarea catatan dan field tanggal helper (item_order > 1000)
                    if (item.item_order > 1000) {
                        return;
                    }
                    
                    // Skip field harga dan tanggal yang sudah di-handle (item_order 11-109)
                    // Exception: 1) item 11 untuk template Jual Aset section 2
                    //           2) item 24 dan 25 untuk template Jual Aset section 3
                    if (item.item_order > 10 && item.item_order < 110 && item.item_order !== 100) {
                        if (!(templateId === 6 && section.section_order === 2 && item.item_order === 11) &&
                            !(templateId === 6 && section.section_order === 3 && (item.item_order === 24 || item.item_order === 25))) {
                            return;
                        }
                    }
                    
                    html += `<tr>`;
                    
                    // Special handling untuk Section 2 - Template Mix Oil (ID 1)
                    if (section.section_order === 2 && templateId === 1) {
                        // Item 1-3: Penawaran harga (2 kolom: nama vendor + harga)
                        if (item.item_order >= 1 && item.item_order <= 3) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            
                            // Cari field harga yang sesuai (item_order 11, 21, 31)
                            const hargaItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            html += `<td colspan="3">`;
                            html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">`;
                            html += `<input type="text" name="responses[${item.id}]" placeholder="Masukkan nilai/nama" style="width:100%;">`;
                            if (hargaItem) {
                                html += `<input type="number" name="responses[${hargaItem.id}]" placeholder="Harga (Rp)" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                            }
                            html += `</div>`;
                            html += `</td>`;
                        }
                        // Item 4: Approval QCF (menampilkan auto approval routing untuk Mix Oil)
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
                            
                            // Cari field tanggal yang sesuai
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                    }
                    // Special handling untuk Section 2 - Template Jual Aset (ID 6)
                    else if (section.section_order === 2 && templateId === 6) {
                        // Item 1-3: Penawaran harga (2 kolom: nama vendor + harga)
                        if (item.item_order >= 1 && item.item_order <= 3 && item.field_type === 'text' && item.item_text.includes('Penawaran harga')) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            
                            // Cari field harga yang sesuai (item_order 12, 22, 32)
                            const hargaItem = section.items.find(i => i.item_order === (item.item_order * 10 + 2) && i.field_type === 'number');
                            
                            html += `<td colspan="3">`;
                            html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">`;
                            html += `<input type="text" name="responses[${item.id}]" placeholder="Masukkan nama vendor" style="width:100%;">`;
                            if (hargaItem) {
                                html += `<input type="number" name="responses[${hargaItem.id}]" placeholder="Harga (Rp)" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                            }
                            html += `</div>`;
                            html += `</td>`;
                        }
                        // Item 4: Approval QCF (menampilkan tabel approval routing untuk Jual Aset)
                        else if (item.item_order === 4) {
                            html += `<td><span class="item-number">${item.item_order}.</span> Approval QCF</td>`;
                            html += `<td colspan="3">`;
                            html += `<div id="approvalRouting" style="padding:10px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;">`;
                            html += `<strong>Authorized Parties:</strong><br>`;
                            html += `<table style="width:100%;margin-top:8px;font-size:11px;border-collapse:collapse;" border="1" cellpadding="6">`;
                            html += `<tr style="background:#e9ecef;font-weight:bold;">`;
                            html += `<th></th>`;
                            html += `<th>≤ Rp. 100Jt</th>`;
                            html += `<th>> Rp 100Jt - ≤ Rp 1M</th>`;
                            html += `<th>> Rp 1M - ≤ 10M</th>`;
                            html += `<th>> Rp 10 M</th>`;
                            html += `</tr>`;
                            html += `<tr>`;
                            html += `<td style="font-weight:bold;background:#f8f9fa;">Procurement</td>`;
                            html += `<td>Local DS Mgr</td>`;
                            html += `<td>BP DS Agri & Food</td>`;
                            html += `<td>Head of BP US & DS Proc</td>`;
                            html += `<td>Head of BP US & DS Proc + CPO</td>`;
                            html += `</tr>`;
                            html += `<tr>`;
                            html += `<td style="font-weight:bold;background:#f8f9fa;">Ceklist</td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_proc_100jt" class="approval-checkbox-jual-aset"></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_proc_1m" class="approval-checkbox-jual-aset"></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_proc_10m" class="approval-checkbox-jual-aset"></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_proc_10m_1" class="approval-checkbox-jual-aset"> <input type="checkbox" name="approval_proc_10m_2" class="approval-checkbox-jual-aset"></td>`;
                            html += `</tr>`;
                            html += `<tr>`;
                            html += `<td style="font-weight:bold;background:#f8f9fa;">Finance</td>`;
                            html += `<td>Na</td>`;
                            html += `<td>Na</td>`;
                            html += `<td>Head of Ops Controller</td>`;
                            html += `<td>DS BU CFO</td>`;
                            html += `</tr>`;
                            html += `<tr>`;
                            html += `<td style="font-weight:bold;background:#f8f9fa;">Ceklist</td>`;
                            html += `<td></td>`;
                            html += `<td></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_fin_10m" class="approval-checkbox-jual-aset"></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_fin_10m_plus" class="approval-checkbox-jual-aset"></td>`;
                            html += `</tr>`;
                            html += `<tr>`;
                            html += `<td style="font-weight:bold;background:#f8f9fa;">Executive</td>`;
                            html += `<td>Na</td>`;
                            html += `<td>Related Head</td>`;
                            html += `<td>DS BU CEO</td>`;
                            html += `<td>DS BU CEO/MD DSI + CFO DS + DS COO</td>`;
                            html += `</tr>`;
                            html += `<tr>`;
                            html += `<td style="font-weight:bold;background:#f8f9fa;">Ceklist</td>`;
                            html += `<td></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_exec_1m" class="approval-checkbox-jual-aset"></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_exec_10m" class="approval-checkbox-jual-aset"></td>`;
                            html += `<td style="text-align:center;"><input type="checkbox" name="approval_exec_10m_1" class="approval-checkbox-jual-aset"> <input type="checkbox" name="approval_exec_10m_2" class="approval-checkbox-jual-aset"> <input type="checkbox" name="approval_exec_10m_3" class="approval-checkbox-jual-aset"></td>`;
                            html += `</tr>`;
                            html += `</table>`;
                            html += `</div>`;
                            html += `<input type="hidden" name="responses[${item.id}]" id="approvalValue" value="">`;
                            html += `</td>`;
                        }
                        // Item 5-11: Radio button dengan kolom tanggal
                        else if (item.item_order >= 5 && item.item_order <= 11) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal yang sesuai (item_order 51, 61, 71, 81, 91, 101, 111)
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            // Semua item 5-11 menggunakan tanggal
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                    }
                    // Special handling untuk Section 2 - untuk template Mixed Oil (ID 1)
                    else if (section.section_order === 2 && templateId === 1) {
                        // Item 1-3: Penawaran harga (2 kolom: nama vendor + harga)
                        if (item.item_order >= 1 && item.item_order <= 3) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            
                            // Cari field harga yang sesuai (item_order 11, 21, 31)
                            const hargaItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            html += `<td colspan="3">`;
                            html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">`;
                            html += `<input type="text" name="responses[${item.id}]" placeholder="Masukkan nilai/nama" style="width:100%;">`;
                            if (hargaItem) {
                                html += `<input type="number" name="responses[${hargaItem.id}]" placeholder="Harga (Rp)" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                            }
                            html += `</div>`;
                            html += `</td>`;
                        }
                        // Item 4: Approval QCF - Struktur sederhana seperti Approval Kaber di Barbes
                        else if (item.item_order === 4) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal (item_order 41)
                            const dateItem = section.items.find(i => i.item_order === 41);
                            
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                        // Item 5-6: Radio button dengan kolom tanggal (QCF dan Vega)
                        else if (item.item_order >= 5 && item.item_order <= 6) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal yang sesuai
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                        // Item 7: Periode QCF - Special handling untuk field date langsung (tanpa radio terpisah)
                        else if (item.item_order === 7 && item.field_type === 'date') {
                            html += `<td><span class="item-number">7.</span> Periode QCF</td>`;
                            html += `<td colspan="2" class="excel-cell-gray"></td>`;
                            html += `<td><input type="date" name="responses[${item.id}]" id="periode-qcf-date" class="date-field" style="width:100%;"></td>`;
                        }
                        // Item 8-10: Radio button dengan kolom tanggal (SPK/PJB, Approval SPK, Kirim email)
                        else if (item.item_order >= 8 && item.item_order <= 10) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal yang sesuai
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                    }
                    // Special handling untuk Section 2 - untuk template Barbes (ID 5)
                    else if (section.section_order === 2 && templateId === 5) {
                        // Item 1-3: Penawaran harga (2 kolom: nama vendor + harga)
                        if (item.item_order >= 1 && item.item_order <= 3) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            
                            // Cari field harga yang sesuai (item_order 11, 21, 31)
                            const hargaItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            html += `<td colspan="3">`;
                            html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">`;
                            html += `<input type="text" name="responses[${item.id}]" placeholder="Masukkan nama vendor" style="width:100%;">`;
                            if (hargaItem) {
                                html += `<input type="number" name="responses[${hargaItem.id}]" placeholder="Harga (Rp)" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
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
                        // Item 5-10: Radio button dengan kolom tanggal (untuk Barbes)
                        else if (item.item_order >= 5 && item.item_order <= 10) {
                            html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal yang sesuai (item_order 51, 61, 71, 81, 91, 101)
                            const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                            
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                        // Item 110: Approval Kaber (nomor 11 untuk template Barbes)
                        else if (item.item_order === 110) {
                            html += `<td><span class="item-number">11.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            
                            // Cari field tanggal (item_order 1101)
                            const dateItem = section.items.find(i => i.item_order === 1101);
                            
                            if (dateItem) {
                                html += `<td><input type="date" name="responses[${dateItem.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                            } else {
                                html += `<td></td>`;
                            }
                        }
                    }
                    // Special handling untuk Section 3: Penerimaan Pembayaran
                    else if (section.section_order === 3) {
                        // Khusus untuk template Jual Aset (ID 6)
                        if (templateId === 6) {
                            // Item 1: Konfirmasi Qty - Radio + Label "Brdsk Qty SPK" + Input Harga
                            if (item.item_order === 1) {
                                html += `<td><span class="item-number">1.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                const ket22 = section.items.find(i => i.item_order === 22);
                                html += `<td>`;
                                html += `<div style="display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:center;">`;
                                html += `<span style="font-weight:500;color:#495057;">Brdsk Qty SPK</span>`;
                                html += `<input type="number" name="responses[${ket22?.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                                html += `</div></td>`;
                            }
                            // Item 2: Bukti Transfer I - Radio Ada/Tidak ada + Tanggal
                            else if (item.item_order === 2) {
                                html += `<td><span class="item-number">2.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                const ket23 = section.items.find(i => i.item_order === 23);
                                html += `<td><input type="date" name="responses[${ket23?.id}]" style="width:100%;"></td>`;
                                html += `</tr>`;
                                
                                // Langsung render sub-items 24 dan 25 setelah item 2
                                const item24 = section.items.find(i => i.item_order === 24);
                                const item25 = section.items.find(i => i.item_order === 25);
                                
                                // Sub-item 24: Nilai transfer I
                                if (item24) {
                                    html += `<tr>`;
                                    html += `<td style="padding-left:30px;">${item24.item_text}</td>`;
                                    html += `<td colspan="3">`;
                                    html += `<input type="number" name="responses[${item24.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                                    html += `</td>`;
                                    html += `</tr>`;
                                }
                                
                                // Sub-item 25: Info konfirmasi
                                if (item25) {
                                    html += `<tr>`;
                                    html += `<td style="padding-left:30px;">${item25.item_text}</td>`;
                                    html += `<td colspan="3" style="text-align:center;">`;
                                    html += `<div style="display:flex;gap:30px;justify-content:center;">`;
                                    html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                                    html += `<input type="radio" name="responses[${item25.id}]" value="sesuai">`;
                                    html += `<span>Sesuai</span>`;
                                    html += `</label>`;
                                    html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                                    html += `<input type="radio" name="responses[${item25.id}]" value="tidak_sesuai">`;
                                    html += `<span>Tidak Sesuai</span>`;
                                    html += `</label>`;
                                    html += `</div>`;
                                    html += `</td>`;
                                }
                            }
                            // Item 24 dan 25 sudah dirender di atas, skip
                            else if (item.item_order === 24 || item.item_order === 25) {
                                return; // Skip, sudah dirender setelah item 2
                            }
                            // Item 3: Email instruksi - Radio Ada/Tidak ada + Tanggal
                            else if (item.item_order === 3) {
                                html += `<td><span class="item-number">3.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                const ket31 = section.items.find(i => i.item_order === 31);
                                html += `<td><input type="date" name="responses[${ket31?.id}]" style="width:100%;"></td>`;
                            }
                        }
                        // Untuk template lain (Barbes ID 5, Mix Oil ID 1)
                        else {
                        // Item 1: Konfirmasi Qty - Radio + Label "Brdsk qty" + Input Harga
                        if (item.item_order === 1) {
                            html += `<td><span class="item-number">1.</span> ${item.item_text}</td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                            const ket22 = section.items.find(i => i.item_order === 22);
                            html += `<td>`;
                            html += `<div style="display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:center;">`;
                            html += `<span style="font-weight:500;color:#495057;">Brdsk Qty SPK</span>`;
                            html += `<input type="number" name="responses[${ket22?.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                            html += `</div></td>`;
                        }
                        // Item 2: Bukti Transfer I - HANYA Tanggal (tanpa radio)
                        else if (item.item_order === 2) {
                            html += `<td><span class="item-number">2.</span> ${item.item_text}</td>`;
                            html += `<td colspan="3"><input type="date" name="responses[${item.id}]" style="width:100%;" ${item.is_required ? 'required' : ''}></td>`;
                        }
                        // Item 3: Nilai transfer I - TANPA NOMOR, Rp saja (tanpa border hitam)
                        else if (item.item_order === 3) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            html += `<td colspan="3">`;
                            html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;" ${item.is_required ? 'required' : ''}>`;
                            html += `</td>`;
                        }
                        // Item 4: Info konfirmasi I - TANPA NOMOR, Radio Sesuai/Tidak Sesuai (tanpa kolom tanggal)
                        else if (item.item_order === 4) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            html += `<td colspan="3" style="text-align:center;">`;
                            html += `<div style="display:flex;gap:30px;justify-content:center;">`;
                            html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                            html += `<input type="radio" name="responses[${item.id}]" value="sesuai">`;
                            html += `<span>Sesuai</span>`;
                            html += `</label>`;
                            html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                            html += `<input type="radio" name="responses[${item.id}]" value="tidak_sesuai">`;
                            html += `<span>Tidak Sesuai</span>`;
                            html += `</label>`;
                            html += `</div>`;
                            html += `</td>`;
                        }
                        // Item 5: Bukti Transfer II (Nomor 3) - HANYA Tanggal (tanpa radio)
                        else if (item.item_order === 5) {
                            html += `<td><span class="item-number">3.</span> ${item.item_text}</td>`;
                            html += `<td colspan="3"><input type="date" name="responses[${item.id}]" style="width:100%;" ${item.is_required ? 'required' : ''}></td>`;
                        }
                        // Item 6: Nilai transfer II - TANPA NOMOR, Rp saja (tanpa border hitam)
                        else if (item.item_order === 6) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            html += `<td colspan="3">`;
                            html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;" ${item.is_required ? 'required' : ''}>`;
                            html += `</td>`;
                        }
                        // Item 7: Info konfirmasi II - TANPA NOMOR, Radio Sesuai/Tidak Sesuai (tanpa kolom tanggal)
                        else if (item.item_order === 7) {
                            html += `<td style="padding-left:30px;">${item.item_text}</td>`;
                            html += `<td colspan="3" style="text-align:center;">`;
                            html += `<div style="display:flex;gap:30px;justify-content:center;">`;
                            html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                            html += `<input type="radio" name="responses[${item.id}]" value="sesuai">`;
                            html += `<span>Sesuai</span>`;
                            html += `</label>`;
                            html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                            html += `<input type="radio" name="responses[${item.id}]" value="tidak_sesuai">`;
                            html += `<span>Tidak Sesuai</span>`;
                            html += `</label>`;
                            html += `</div>`;
                            html += `</td>`;
                        }
                        // Item 8: Sisa - Nomor 4, HANYA Rp saja (tanpa radio)
                        else if (item.item_order === 8) {
                            html += `<td><span class="item-number">4.</span> ${item.item_text}</td>`;
                            html += `<td colspan="3">`;
                            html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                            html += `</td>`;
                        }
                        // Item 9: Email instruksi - Nomor 5, HANYA Tanggal (tanpa radio)
                        else if (item.item_order === 9) {
                            html += `<td><span class="item-number">5.</span> ${item.item_text}</td>`;
                            html += `<td colspan="3"><input type="date" name="responses[${item.id}]" placeholder="Tanggal" style="width:100%;"></td>`;
                        }
                        }
                    }
                    // Special handling untuk Section 4: Mengeluarkan Barang
                    else if (section.section_order === 4) {
                        // Khusus untuk template Jual Aset (ID 6) - struktur baru
                        if (templateId === 6) {
                            // Item 1-4: Radio + Tanggal untuk Jual Aset
                            if (item.item_order >= 1 && item.item_order <= 4) {
                                html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                
                                // Cari field tanggal (item_order x 10 + 1)
                                const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                                html += `<td><input type="date" name="responses[${dateItem?.id}]" placeholder="Tanggal" style="width:100%;"></td>`;
                            }
                            // Item 5: Dokumen Foto (file upload)
                            else if (item.item_order === 5) {
                                html += `<td><span class="item-number">5.</span> ${item.item_text}</td>`;
                                html += `<td colspan="3">`;
                                html += `<input type="file" name="photo_upload[]" accept="image/*" multiple style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;">`;
                                html += `<input type="hidden" name="responses[${item.id}]" id="photo_field_${item.id}" value="">`;
                                html += `<small style="color:#6c757d;display:block;margin-top:5px;">Upload foto (opsional, bisa lebih dari 1 file)</small>`;
                                html += `</td>`;
                            }
                        }
                        // Untuk template Mix Oil (ID 1) - struktur khusus
                        else if (templateId === 1) {
                            // Item 1-3: Radio + Tanggal
                            if (item.item_order >= 1 && item.item_order <= 3) {
                                html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                
                                // Cari field tanggal (item_order x 10 + 1)
                                const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                                html += `<td><input type="date" name="responses[${dateItem?.id}]" placeholder="Tanggal" style="width:100%;"></td>`;
                            }
                            // Item 4: Radio + Tanggal + Oleh
                            else if (item.item_order === 4) {
                                html += `<td><span class="item-number">4.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                
                                const dateItem = section.items.find(i => i.item_order === 41);
                                const olehItem = section.items.find(i => i.item_order === 42);
                                html += `<td>`;
                                html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;">`;
                                html += `<input type="date" name="responses[${dateItem?.id}]" placeholder="Tanggal" style="width:100%;">`;
                                html += `<input type="text" name="responses[${olehItem?.id}]" placeholder="Oleh" style="width:100%;">`;
                                html += `</div>`;
                                html += `</td>`;
                            }
                            // Item 5: Qty Mix oil tidak melebihi SPK/PJB (Sesuai/Tidak Sesuai tanpa tanggal)
                            else if (item.item_order === 5) {
                                html += `<td><span class="item-number">5.</span> Qty Mix oil tidak melebihi SPK/PJB</td>`;
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
                            // Item 6 dan 7 di-skip untuk Mix Oil (tidak ditampilkan)
                        }
                        // Untuk template Barbes (ID 5) - struktur dengan file upload
                        else if (templateId === 5) {
                            // Item 1-3: Radio + Tanggal
                            if (item.item_order >= 1 && item.item_order <= 3) {
                                html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                
                                // Cari field tanggal (item_order x 10 + 1)
                                const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                                html += `<td><input type="date" name="responses[${dateItem?.id}]" placeholder="Tanggal" style="width:100%;"></td>`;
                            }
                            // Item 4: Radio + Tanggal + Oleh
                            else if (item.item_order === 4) {
                                html += `<td><span class="item-number">4.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada"></td>`;
                                
                                const dateItem = section.items.find(i => i.item_order === 41);
                                const olehItem = section.items.find(i => i.item_order === 42);
                                html += `<td>`;
                                html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;">`;
                                html += `<input type="date" name="responses[${dateItem?.id}]" placeholder="Tanggal" style="width:100%;">`;
                                html += `<input type="text" name="responses[${olehItem?.id}]" placeholder="Oleh" style="width:100%;">`;
                                html += `</div>`;
                                html += `</td>`;
                            }
                            // Item 5: Dokumen Foto (file upload untuk Barbes)
                            else if (item.item_order === 5) {
                                html += `<td><span class="item-number">5.</span> ${item.item_text}</td>`;
                                html += `<td colspan="3">`;
                                html += `<input type="file" name="photo_upload[]" accept="image/*" multiple style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;">`;
                                html += `<input type="hidden" name="responses[${item.id}]" id="photo_field_${item.id}" value="">`;
                                html += `<small style="color:#6c757d;display:block;margin-top:5px;">Upload foto (opsional, bisa lebih dari 1 file)</small>`;
                                html += `</td>`;
                            }
                            // Item 6: Qty Barbes tidak melebihi SPK/PJB (Sesuai/Tidak Sesuai tanpa tanggal)
                            else if (item.item_order === 6) {
                                const tidakSesuaiItem = section.items.find(i => i.item_order === 7);
                                html += `<td><span class="item-number">6.</span> Qty Barbes tidak melebihi SPK/PJB</td>`;
                                html += `<td class="excel-cell-center">`;
                                html += `<div style="display:flex;flex-direction:column;align-items:center;gap:3px;">`;
                                html += `<input type="radio" name="responses[${item.id}]" value="sesuai" ${item.is_required ? 'required' : ''}>`;
                                html += `<span style="font-size:11px;color:#495057;">Sesuai</span>`;
                                html += `</div>`;
                                html += `</td>`;
                                html += `<td class="excel-cell-center">`;
                                html += `<div style="display:flex;flex-direction:column;align-items:center;gap:3px;">`;
                                html += `<input type="radio" name="responses[${tidakSesuaiItem?.id}]" value="tidak_sesuai">`;
                                html += `<span style="font-size:11px;color:#495057;">Tidak Sesuai</span>`;
                                html += `</div>`;
                                html += `</td>`;
                                html += `<td></td>`;
                            }
                        }
                    }
                    // Special handling untuk Section 5: Pengembalian Dana
                    else if (section.section_order === 5) {
                        // Untuk template Mix Oil (ID 1)
                        if (templateId === 1) {
                            // Item 1: Surat Permohonan pengembalian dana dari Customer (Radio + Tanggal)
                            if (item.item_order === 1) {
                                html += `<td><span class="item-number">1.</span> Surat permohonan pengembalian dana dr Customer</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                                
                                // Cari field tanggal (item_order 11)
                                const dateItem = section.items.find(i => i.item_order === 11);
                                html += `<td><input type="date" name="responses[${dateItem?.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" placeholder="Tanggal" style="width:100%;"></td>`;
                            }
                            // Item 2: Bukti transfer dari SIP (Radio + Tanggal)
                            else if (item.item_order === 2) {
                                html += `<td><span class="item-number">2.</span> Bukti transfer dari SIP</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}"></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                                
                                // Cari field tanggal (item_order 21)
                                const dateItem = section.items.find(i => i.item_order === 21);
                                html += `<td><input type="date" name="responses[${dateItem?.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" placeholder="Tanggal" style="width:100%;"></td>`;
                            }
                            // Item 3: Jumlah (Input Rp + Radio Sesuai/Tidak Sesuai)
                            else if (item.item_order === 3) {
                                const kesesuaianItem = section.items.find(i => i.item_order === 31);
                                html += `<td><span class="item-number">3.</span> Jumlah</td>`;
                                html += `<td colspan="2">`;
                                html += `<input type="text" name="responses[${item.id}]" placeholder="Rp" style="width:100%;padding:8px 4px;">`;
                                html += `</td>`;
                                html += `<td>`;
                                html += `<div style="display:flex;gap:15px;justify-content:center;align-items:center;">`;
                                html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                                html += `<input type="radio" name="responses[${kesesuaianItem?.id}]" value="sesuai">`;
                                html += `<span style="font-size:13px;">Sesuai</span>`;
                                html += `</label>`;
                                html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                                html += `<input type="radio" name="responses[${kesesuaianItem?.id}]" value="tidak_sesuai">`;
                                html += `<span style="font-size:13px;">Tidak Sesuai</span>`;
                                html += `</label>`;
                                html += `</div>`;
                                html += `</td>`;
                            }
                        }
                        // Untuk template lain (Barbes, Jual Aset, dll)
                        else {
                            // Item 1 & 2: Radio + Tanggal
                            if (item.item_order === 1 || item.item_order === 2) {
                                html += `<td><span class="item-number">${item.item_order}.</span> ${item.item_text}</td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                                html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                                
                                // Cari field tanggal yang sesuai (item_order x 10 + 1)
                                const dateItem = section.items.find(i => i.item_order === (item.item_order * 10 + 1));
                                html += `<td><input type="date" name="responses[${dateItem?.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" placeholder="Tanggal" style="width:100%;"></td>`;
                            }
                            // Item 3 (Jumlah): Input Rp + Radio Sesuai/Tidak Sesuai
                            else if (item.item_order === 3) {
                                html += `<td><span class="item-number">3.</span> ${item.item_text}</td>`;
                                html += `<td colspan="2">`;
                                html += `<input type="number" name="responses[${item.id}]" placeholder="Rp" style="width:100%;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                                html += `</td>`;
                                html += `<td>`;
                                html += `<div style="display:flex;gap:15px;justify-content:center;align-items:center;">`;
                                html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                                html += `<input type="radio" name="responses_kesesuaian_${item.id}" value="sesuai">`;
                                html += `<span style="font-size:13px;">Sesuai</span>`;
                                html += `</label>`;
                                html += `<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">`;
                                html += `<input type="radio" name="responses_kesesuaian_${item.id}" value="tidak_sesuai">`;
                                html += `<span style="font-size:13px;">Tidak Sesuai</span>`;
                                html += `</label>`;
                                html += `</div>`;
                                html += `</td>`;
                            }
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
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="ada" class="radio-ada" data-item-id="${item.id}" ${item.is_required ? 'required' : ''}></td>`;
                            html += `<td class="excel-cell-center"><input type="radio" name="responses[${item.id}]" value="tidak_ada" class="radio-tidak-ada" data-item-id="${item.id}"></td>`;
                            html += `<td><input type="date" name="responses_date[${item.id}]" id="date-${item.id}" class="date-field" data-radio-id="${item.id}" style="width:100%;"></td>`;
                        }
                    }
                    
                    html += `</tr>`;
                });
                
                html += `</tbody></table>`;
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
            
            // Setup Rupiah formatting untuk dynamic fields
            setTimeout(function() {
                setupRupiahFormatting();
            }, 100);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat template');
        });
}

// Render khusus untuk PO Non OA Template
function renderPONonOATemplate(data) {
    let html = '';
    
    data.sections.forEach((section, sectionIndex) => {
        html += `<div class="excel-section">`;
        html += `<h3 class="excel-section-header">${section.section_title}</h3>`;
        
        // Section 1: Informasi PO Non OA - header fields  
        if (section.section_order === 1) {
            html += `<table class="excel-info-table">`;
            section.items.forEach((item, idx) => {
                if (idx === 0) {
                    // Pembelian PO Non OA
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><input type="text" name="responses[${item.id}]" placeholder="Masukkan informasi"></td>`;
                    html += `</tr>`;
                } else if (idx === 1) {
                    // Tanggal
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><input type="date" name="responses[${item.id}]" value="<?php echo date('Y-m-d'); ?>"></td>`;
                    html += `</tr>`;
                } else if (idx === 2) {
                    // Deskripsi
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><textarea name="responses[${item.id}]" rows="2" placeholder="Masukkan deskripsi" style="width:100%;padding:6px;"></textarea></td>`;
                    html += `</tr>`;
                } else if (idx === 3) {
                    // Qty
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td><input type="number" name="responses[${item.id}]" id="po_non_oa_qty" placeholder="0"></td>`;
                    
                    // Harga satuan di kolom berikutnya
                    const hargaSatuanItem = section.items[4];
                    html += `<td width="120"><strong>${hargaSatuanItem.item_text}</strong></td>`;
                    html += `<td><input type="number" name="responses[${hargaSatuanItem.id}]" id="po_non_oa_unit_price" placeholder="Rp"></td>`;
                    html += `</tr>`;
                } else if (idx === 5) {
                    // Total harga
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><input type="text" name="responses[${item.id}]" id="po_non_oa_total_price" placeholder="Rp" readonly style="background: #f0f0f0;"></td>`;
                    html += `</tr>`;
                }
            });
            html += `</table>`;
        }
        // Section 2: Pengajuan Pembelian (Ada | Tidak ada | Tanggal)
        else if (section.section_order === 2) {
            html += `<table class="excel-table">`;
            html += `<thead><tr>`;
            html += `<th width="50%">Item</th>`;
            html += `<th width="15%">Ada</th>`;
            html += `<th width="15%">Tidak ada</th>`;
            html += `<th width="20%">Tanggal</th>`;
            html += `</tr></thead>`;
            html += `<tbody>`;
            
            const itemNames = ['Pre PR', 'RAP', 'Drawing / Gambar', 'Approval Spec', 'PR fully approved'];
            
            for (let i = 0; i < 5; i++) {
                const baseIdx = i * 3;
                if (section.items[baseIdx]) {
                    html += `<tr>`;
                    html += `<td><span class="item-number">${i + 1}.</span> ${itemNames[i]}</td>`;
                    html += `<td class="excel-cell-center"><input type="radio" name="responses[${section.items[baseIdx].id}]" value="ada"></td>`;
                    html += `<td class="excel-cell-center"><input type="radio" name="responses[${section.items[baseIdx + 1].id}]" value="tidak_ada"></td>`;
                    html += `<td><input type="date" name="responses[${section.items[baseIdx + 2].id}]" style="width:100%;"></td>`;
                    html += `</tr>`;
                }
            }
            
            html += `</tbody></table>`;
        }
        // Section 3: Pelaksanaan Pembelian
        else if (section.section_order === 3) {
            html += `<table class="excel-table">`;
            html += `<thead><tr>`;
            html += `<th width="40%">Item</th>`;
            html += `<th width="15%">Ada</th>`;
            html += `<th width="15%">Tidak ada</th>`;
            html += `<th width="30%">Tanggal</th>`;
            html += `</tr></thead>`;
            html += `<tbody>`;
            
            let itemNumber = 1;
            
            // Item 1-3: Penawaran harga (nama vendor + harga)
            for (let i = 0; i < 3; i++) {
                const namaIdx = i * 2;
                const hargaIdx = i * 2 + 1;
                if (section.items[namaIdx]) {
                    html += `<tr>`;
                    html += `<td><span class="item-number">${itemNumber}.</span> ${section.items[namaIdx].item_text}</td>`;
                    html += `<td colspan="3">`;
                    html += `<div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;">`;
                    html += `<input type="text" name="responses[${section.items[namaIdx].id}]" placeholder="Nama vendor" style="width:100%;">`;
                    html += `<div style="display:flex;align-items:center;gap:5px;">`;
                    html += `<span style="font-weight:500;white-space:nowrap;">Harga:</span>`;
                    html += `<input type="number" name="responses[${section.items[hargaIdx].id}]" placeholder="Rp" style="width:150px;border:none;border-bottom:1px solid #dee2e6;border-radius:0;padding:8px 4px;">`;
                    html += `</div>`;
                    html += `</div>`;
                    html += `</td>`;
                    html += `</tr>`;
                    itemNumber++;
                }
            }
            
            // Item 4: Approval QCF/Bid - Authorized Parties
            const approvalItem = section.items[6];
            const approvalSelularItem = section.items.find(i => i.item_text === 'Approval Selular');
            if (approvalItem) {
                html += `<tr>`;
                html += `<td><span class="item-number">${itemNumber}.</span> Approval QCF / Bid</td>`;
                html += `<td colspan="3">`;
                html += `<div style="padding:10px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;">`;
                html += `<strong>Authorized Parties:</strong><br>`;
                html += `<table style="width:100%;margin-top:8px;font-size:11px;" border="1" cellpadding="4">`;
                html += `<tr style="background:#e9ecef;"><th></th><th>< Rp. 100Jt</th><th>> Rp 100Jt</th></tr>`;
                html += `<tr><td><strong>Procurement</strong></td><td style="text-align:center;">Local DS Mgr</td><td style="text-align:center;">QCF will be managed By CM</td></tr>`;
                html += `<tr><td><strong>Selular</strong></td><td style="text-align:center;"><input type="checkbox" name="approval_selular_low" value="<100jt" class="approval-checkbox"></td><td style="text-align:center;"><input type="checkbox" name="approval_selular_high" value=">100jt" class="approval-checkbox"></td></tr>`;
                html += `<input type="hidden" name="responses[${approvalSelularItem?.id}]" class="approval-combined" value="">`;
                html += `</table>`;
                html += `</div>`;
                html += `<input type="hidden" name="responses[${approvalItem.id}]" value="authorized_parties_displayed">`;
                html += `</td>`;
                html += `</tr>`;
                itemNumber++;
            }
            
            // Item 5: QCF/Bid (Ada/Tidak ada/Tanggal)
            const qcfAdaItem = section.items.find(item => item.item_text === 'QCF / Bid - Ada');
            const qcfTidakItem = section.items.find(item => item.item_text === 'QCF / Bid - Tidak ada');
            const qcfDateItem = section.items.find(item => item.item_text === 'QCF / Bid - Tanggal');
            if (qcfAdaItem && qcfTidakItem && qcfDateItem) {
                html += `<tr>`;
                html += `<td><span class="item-number">${itemNumber}.</span> QCF / Bid</td>`;
                html += `<td class="excel-cell-center"><input type="radio" name="qcf_bid_group" data-ada-id="${qcfAdaItem.id}" data-tidak-id="${qcfTidakItem.id}" value="ada"></td>`;
                html += `<td class="excel-cell-center"><input type="radio" name="qcf_bid_group" data-ada-id="${qcfAdaItem.id}" data-tidak-id="${qcfTidakItem.id}" value="tidak_ada"></td>`;
                html += `<td><input type="date" name="responses[${qcfDateItem.id}]" style="width:100%;"></td>`;
                html += `<input type="hidden" name="responses[${qcfAdaItem.id}]" class="qcf-ada-hidden" value="">`;
                html += `<input type="hidden" name="responses[${qcfTidakItem.id}]" class="qcf-tidak-hidden" value="">`;
                html += `</tr>`;
                itemNumber++;
            }
            
            // Item 6: Nego (Ada/Tidak ada/Tanggal)
            const negoAdaItem = section.items.find(item => item.item_text === 'Nego - Ada');
            const negoTidakItem = section.items.find(item => item.item_text === 'Nego - Tidak ada');
            const negoDateItem = section.items.find(item => item.item_text === 'Nego - Tanggal');
            if (negoAdaItem && negoTidakItem && negoDateItem) {
                html += `<tr>`;
                html += `<td><span class="item-number">${itemNumber}.</span> Nego</td>`;
                html += `<td class="excel-cell-center"><input type="radio" name="responses[${negoAdaItem.id}]" value="ada"></td>`;
                html += `<td class="excel-cell-center"><input type="radio" name="responses[${negoTidakItem.id}]" value="tidak_ada"></td>`;
                html += `<td><input type="date" name="responses[${negoDateItem.id}]" style="width:100%;"></td>`;
                html += `</tr>`;
                itemNumber++;
            }
            
            html += `</tbody></table>`;
        }
        // Section 4: PO
        else if (section.section_order === 4) {
            html += `<table class="excel-table">`;
            html += `<thead><tr>`;
            html += `<th width="60%">Item</th>`;
            html += `<th width="20%">Sesuai</th>`;
            html += `<th width="20%">Tidak</th>`;
            html += `</tr></thead>`;
            html += `<tbody>`;
            
            let itemNumber = 1;
            
            // Items dengan pola: Sesuai, Tidak (8 items)
            const itemLabels = [
                'Cek Nama Vendor',
                'Cek Kembali ke RAP/Spec yang disetujui User',
                'Cek Kembali ke Penawaran',
                'Cek Tax Code',
                'Cek TOP',
                'Cek DD',
                'Input Note Tambahan PO',
                'Kirim PO ke Vendor'
            ];
            
            for (let i = 0; i < itemLabels.length; i++) {
                const labelPrefix = itemLabels[i];
                
                // Cari item berdasarkan item_text
                const sesuaiItem = section.items.find(item => item.item_text && item.item_text.includes(labelPrefix + ' - Sesuai'));
                const tidakItem = section.items.find(item => item.item_text && item.item_text.includes(labelPrefix + ' - Tidak'));
                
                if (sesuaiItem && tidakItem) {
                    html += `<tr>`;
                    html += `<td><span class="item-number">${itemNumber}.</span> ${labelPrefix}</td>`;
                    html += `<td class="excel-cell-center"><input type="radio" name="po_non_oa_item_${i}" data-sesuai-id="${sesuaiItem.id}" data-tidak-id="${tidakItem.id}" value="sesuai"></td>`;
                    html += `<td class="excel-cell-center"><input type="radio" name="po_non_oa_item_${i}" data-sesuai-id="${sesuaiItem.id}" data-tidak-id="${tidakItem.id}" value="tidak"></td>`;
                    html += `<input type="hidden" name="responses[${sesuaiItem.id}]" class="po-sesuai-${i}" value="">`;
                    html += `<input type="hidden" name="responses[${tidakItem.id}]" class="po-tidak-${i}" value="">`;
                    html += `</tr>`;
                    itemNumber++;
                }
            }
            
            html += `</tbody></table>`;
        }
        
        html += `</div>`;
    });
    
    // Add notes field
    html += `<table class="excel-info-table">`;
    html += `<tr><td>Catatan Tambahan</td><td colspan="3"><textarea name="notes" rows="3" placeholder="Catatan atau temuan lainnya..." style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;"></textarea></td></tr>`;
    html += `</table>`;
    
    document.getElementById('templateFields').innerHTML = html;
    
    // Setup auto calculation untuk Qty x Harga Satuan
    const qtyInput = document.getElementById('po_non_oa_qty');
    const unitPriceInput = document.getElementById('po_non_oa_unit_price');
    const totalPriceInput = document.getElementById('po_non_oa_total_price');
    
    function calculatePONonOATotal() {
        const qty = parseRupiah(qtyInput.value);
        const unitPrice = parseRupiah(unitPriceInput.value);
        const total = qty * unitPrice;
        
        if (total > 0) {
            totalPriceInput.value = 'Rp ' + formatRupiah(total);
        } else {
            totalPriceInput.value = '';
        }
    }
    
    if (qtyInput && unitPriceInput) {
        qtyInput.addEventListener('input', calculatePONonOATotal);
        unitPriceInput.addEventListener('input', calculatePONonOATotal);
    }
}

// Render khusus untuk PO Tagging OA Template
function renderPOTaggingOATemplate(data) {
    let html = '';
    
    data.sections.forEach((section, sectionIndex) => {
        html += `<div class="excel-section">`;
        html += `<h3 class="excel-section-header">${section.section_title}</h3>`;
        
        // Section 1: Informasi PO Tagging OA - header fields  
        if (section.section_order === 1) {
            html += `<table class="excel-info-table">`;
            section.items.forEach((item, idx) => {
                if (idx === 0) {
                    // Pembelian PO tagging OA
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><input type="text" name="responses[${item.id}]" placeholder="Masukkan informasi"></td>`;
                    html += `</tr>`;
                } else if (idx === 1) {
                    // Tanggal
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><input type="date" name="responses[${item.id}]" value="<?php echo date('Y-m-d'); ?>"></td>`;
                    html += `</tr>`;
                } else if (idx === 2) {
                    // Deskripsi
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><textarea name="responses[${item.id}]" rows="2" placeholder="Masukkan deskripsi" style="width:100%;padding:6px;"></textarea></td>`;
                    html += `</tr>`;
                } else if (idx === 3) {
                    // Qty
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td><input type="number" name="responses[${item.id}]" id="po_qty" placeholder="0"></td>`;
                    
                    // Harga satuan di kolom berikutnya
                    const hargaSatuanItem = section.items[4];
                    html += `<td width="120"><strong>${hargaSatuanItem.item_text}</strong></td>`;
                    html += `<td><input type="number" name="responses[${hargaSatuanItem.id}]" id="po_unit_price" placeholder="Rp"></td>`;
                    html += `</tr>`;
                } else if (idx === 5) {
                    // Total harga
                    html += `<tr>`;
                    html += `<td>${item.item_text}</td>`;
                    html += `<td colspan="3"><input type="text" name="responses[${item.id}]" id="po_total_price" placeholder="Rp" readonly style="background: #f0f0f0;"></td>`;
                    html += `</tr>`;
                }
            });
            html += `</table>`;
        }
        // Section 2: Pengurusan Pembelian
        else if (section.section_order === 2) {
            html += `<table class="excel-table">`;
            html += `<thead><tr>`;
            html += `<th width="50%">Item</th>`;
            html += `<th width="15%">Ada</th>`;
            html += `<th width="15%">Tidak ada</th>`;
            html += `<th width="20%">Tanggal</th>`;
            html += `</tr></thead>`;
            html += `<tbody>`;
            
            // Group items by 3 (text, Ada radio, Tidak ada radio, date)
            const itemGroups = [];
            const itemNames = ['RAP', 'Approval RAP', 'Drawing / Layout', 'PR fully approved'];
            
            for (let i = 0; i < 4; i++) {
                const baseIdx = i * 3;
                if (section.items[baseIdx]) {
                    itemGroups.push({
                        name: itemNames[i],
                        adaItem: section.items[baseIdx],
                        tidakAdaItem: section.items[baseIdx + 1],
                        dateItem: section.items[baseIdx + 2]
                    });
                }
            }
            
            itemGroups.forEach((group, idx) => {
                html += `<tr>`;
                html += `<td><span class="item-number">${idx + 1}.</span> ${group.name}</td>`;
                html += `<td class="excel-cell-center"><input type="radio" name="responses[${group.adaItem.id}]" value="ada"></td>`;
                html += `<td class="excel-cell-center"><input type="radio" name="responses[${group.tidakAdaItem.id}]" value="tidak_ada"></td>`;
                html += `<td><input type="date" name="responses[${group.dateItem.id}]" style="width:100%;"></td>`;
                html += `</tr>`;
            });
            
            html += `</tbody></table>`;
        }
        // Section 3: PO
        else if (section.section_order === 3) {
            html += `<table class="excel-table">`;
            html += `<thead><tr>`;
            html += `<th width="45%">Item</th>`;
            html += `<th width="15%">Ada</th>`;
            html += `<th width="15%">Tidak ada</th>`;
            html += `<th width="25%">Tanggal</th>`;
            html += `</tr></thead>`;
            html += `<tbody>`;
            
            let itemNumber = 1;
            
            // Section 3 menggunakan item_text untuk matching, bukan index
            // Sekarang dengan field tanggal terpisah untuk setiap item
            const items16Labels = ['Cek DD', 'Cek kondisi Vendor', 'Cek material/item', 'Cek payment term', 'Cek harga', 'Cek qty'];
            
            for (let i = 0; i < 6; i++) {
                const labelPrefix = items16Labels[i];
                
                // Cari item berdasarkan item_text
                const sesuaiItem = section.items.find(item => item.item_text && item.item_text.includes(labelPrefix + ' - Sesuai'));
                const tidakItem = section.items.find(item => item.item_text && item.item_text.includes(labelPrefix + ' - Tidak'));
                const dateItem = section.items.find(item => item.item_text && item.item_text.includes(labelPrefix + ' - Tanggal'));
                
                if (sesuaiItem && tidakItem) {
                    html += `<tr>`;
                    html += `<td><span class="item-number">${itemNumber}.</span> ${labelPrefix}</td>`;
                    html += `<td class="excel-cell-center"><input type="radio" name="responses[${sesuaiItem.id}]" value="ada"></td>`;
                    html += `<td class="excel-cell-center"><input type="radio" name="responses[${tidakItem.id}]" value="tidak_ada"></td>`;
                    // Tambahkan field tanggal terpisah jika ada
                    if (dateItem) {
                        html += `<td><input type="date" name="responses[${dateItem.id}]" style="width:100%;"></td>`;
                    } else {
                        html += `<td></td>`;
                    }
                    html += `</tr>`;
                    itemNumber++;
                }
            }
            
            // Item 7 & 8: Textarea items
            const textareaItems = section.items.filter(item => item.field_type === 'textarea');
            const textareaLabels = ['Input note pembelian PO', 'Kirim PO'];
            
            textareaItems.forEach((item, idx) => {
                if (idx < 2) {
                    html += `<tr>`;
                    html += `<td><span class="item-number">${itemNumber}.</span> ${textareaLabels[idx]}</td>`;
                    html += `<td colspan="3"><textarea name="responses[${item.id}]" rows="2" placeholder="Masukkan keterangan" style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;"></textarea></td>`;
                    html += `</tr>`;
                    itemNumber++;
                }
            });
            
            html += `</tbody></table>`;
        }
        
        html += `</div>`;
    });
    
    // Add notes field
    html += `<table class="excel-info-table">`;
    html += `<tr><td>Catatan Tambahan</td><td colspan="3"><textarea name="notes" rows="3" placeholder="Catatan atau temuan lainnya..." style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:3px;"></textarea></td></tr>`;
    html += `</table>`;
    
    document.getElementById('templateFields').innerHTML = html;
    
    // Setup auto calculation untuk Qty x Harga Satuan
    const qtyInput = document.getElementById('po_qty');
    const unitPriceInput = document.getElementById('po_unit_price');
    const totalPriceInput = document.getElementById('po_total_price');
    
    function calculatePOTotal() {
        const qty = parseRupiah(qtyInput.value);
        const unitPrice = parseRupiah(unitPriceInput.value);
        const total = qty * unitPrice;
        
        if (total > 0) {
            totalPriceInput.value = 'Rp ' + formatRupiah(total);
        } else {
            totalPriceInput.value = '';
        }
    }
    
    if (qtyInput && unitPriceInput) {
        qtyInput.addEventListener('input', calculatePOTotal);
        unitPriceInput.addEventListener('input', calculatePOTotal);
    }
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
                    dateField.setAttribute('data-required', 'true');
                    dateField.style.borderColor = '';
                    // Remove invalid state if any
                    dateField.classList.remove('is-invalid');
                } else if (this.value === 'tidak_ada' && this.classList.contains('radio-tidak-ada')) {
                    // Jika pilih "Tidak Ada", tanggal tidak wajib
                    dateField.removeAttribute('data-required');
                    dateField.value = ''; // Clear the date
                    dateField.style.borderColor = '#ced4da';
                    // Remove invalid state if any
                    dateField.classList.remove('is-invalid');
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
        const totalPrice = parseRupiah(this.value);
        
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
        const qty = parseRupiah(qtyInput.value);
        const unitPrice = parseRupiah(unitPriceInput.value);
        const total = qty * unitPrice;
        
        if (total > 0) {
            totalPriceInput.value = 'Rp ' + formatRupiah(total);
        } else {
            totalPriceInput.value = '';
        }
        
        // Trigger approval routing update
        totalPriceInput.dispatchEvent(new Event('input'));
    }
    
    if (qtyInput && unitPriceInput) {
        qtyInput.addEventListener('input', calculateTotal);
        unitPriceInput.addEventListener('input', calculateTotal);
        
        // Apply formatting to unit_price
        unitPriceInput.addEventListener('keyup', function(e) {
            let value = this.value.replace(/[^0-9]/g, '');
            this.value = formatRupiah(value);
            calculateTotal();
        });
    }
    
    // Setup formatting for all Rupiah fields after template loaded
    setTimeout(setupRupiahFormatting, 500);
    
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
    
    // Handle Authorized Parties checkboxes - combine values
    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('approval-checkbox')) {
            const lowCheckbox = document.querySelector('input[name="approval_selular_low"]');
            const highCheckbox = document.querySelector('input[name="approval_selular_high"]');
            const hiddenInput = document.querySelector('input.approval-combined');
            
            if (lowCheckbox && highCheckbox && hiddenInput) {
                let values = [];
                if (lowCheckbox.checked) values.push('<100jt');
                if (highCheckbox.checked) values.push('>100jt');
                hiddenInput.value = values.join(',');
            }
        }
        
        // Handle Jual Aset Authorized Parties checkboxes
        if (e.target.classList && e.target.classList.contains('approval-checkbox-jual-aset')) {
            const approvalHidden = document.getElementById('approvalValue');
            if (approvalHidden) {
                const checkboxes = document.querySelectorAll('.approval-checkbox-jual-aset:checked');
                const values = Array.from(checkboxes).map(cb => cb.name);
                approvalHidden.value = values.join(',');
            }
        }
        
        // Handle PO Non OA Section 4 radio buttons
        if (e.target.type === 'radio' && e.target.name.startsWith('po_non_oa_item_')) {
            const itemIndex = e.target.name.replace('po_non_oa_item_', '');
            const sesuaiId = e.target.getAttribute('data-sesuai-id');
            const tidakId = e.target.getAttribute('data-tidak-id');
            const sesuaiHidden = document.querySelector(`.po-sesuai-${itemIndex}`);
            const tidakHidden = document.querySelector(`.po-tidak-${itemIndex}`);
            
            if (sesuaiHidden && tidakHidden) {
                if (e.target.value === 'sesuai') {
                    sesuaiHidden.value = 'sesuai';
                    tidakHidden.value = '';
                } else if (e.target.value === 'tidak') {
                    sesuaiHidden.value = '';
                    tidakHidden.value = 'tidak';
                }
            }
        }
        
        // Handle QCF/Bid radio buttons
        if (e.target.type === 'radio' && e.target.name === 'qcf_bid_group') {
            const adaHidden = document.querySelector('.qcf-ada-hidden');
            const tidakHidden = document.querySelector('.qcf-tidak-hidden');
            
            if (adaHidden && tidakHidden) {
                if (e.target.value === 'ada') {
                    adaHidden.value = 'ada';
                    tidakHidden.value = '';
                } else if (e.target.value === 'tidak_ada') {
                    adaHidden.value = '';
                    tidakHidden.value = 'tidak_ada';
                }
            }
        }
    });
    
    // Allow checkboxes to be unchecked on double-click (for Authorized Parties only)
    let lastClickTime = 0;
    let lastClickTarget = null;
    
    document.addEventListener('click', function(e) {
        // Handle checkboxes (Authorized Parties)
        if (e.target.type === 'checkbox' && 
            (e.target.classList.contains('approval-checkbox') || 
             e.target.classList.contains('approval-checkbox-jual-aset'))) {
            const currentTime = new Date().getTime();
            const timeDiff = currentTime - lastClickTime;
            
            // If double-click (within 300ms) on same checkbox
            if (timeDiff < 300 && lastClickTarget === e.target && e.target.checked) {
                e.target.checked = false;
                // Trigger change event to update hidden input
                e.target.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            lastClickTime = currentTime;
            lastClickTarget = e.target;
        }
        // Handle radio buttons (regular items - toggle on single click)
        // Skip PO Non OA Section 4 radios dan QCF/Bid yang menggunakan hidden inputs
        else if (e.target.type === 'radio' && 
                 !e.target.name.startsWith('po_non_oa_item_') && 
                 e.target.name !== 'qcf_bid_group') {
            // Store the current state before the click event completes
            const radioName = e.target.name;
            const wasChecked = e.target.hasAttribute('data-was-checked');
            
            // Get all radios with same name
            const radios = document.querySelectorAll(`input[name="${radioName}"]`);
            
            // If this radio was already checked, uncheck it
            if (wasChecked) {
                e.target.checked = false;
                e.target.removeAttribute('data-was-checked');
                
                // Clear date field if this was a radio-ada button (tapi jangan disable)
                if (e.target.classList.contains('radio-ada')) {
                    const itemId = e.target.getAttribute('data-item-id');
                    if (itemId) {
                        const dateField = document.getElementById(`date-${itemId}`);
                        if (dateField) {
                            dateField.value = '';
                            // Date field tetap enabled agar bisa diedit
                        }
                    }
                }
            } else {
                // Remove checked state from all radios in this group
                radios.forEach(r => r.removeAttribute('data-was-checked'));
                // Mark this one as checked
                e.target.setAttribute('data-was-checked', 'true');
            }
        }
    });
    
    // Initialize the checked state for all radio buttons
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        if (radio.checked) {
            radio.setAttribute('data-was-checked', 'true');
        }
        
        // Update data-was-checked on change
        radio.addEventListener('change', function() {
            if (this.checked) {
                // Remove from all radios with same name
                document.querySelectorAll(`input[name="${this.name}"]`).forEach(r => {
                    r.removeAttribute('data-was-checked');
                });
                // Set on this one
                this.setAttribute('data-was-checked', 'true');
            }
        });
    });
    
    // Custom form validation to prevent auto-scroll to price field
    const auditForm = document.getElementById('auditForm');
    if (auditForm) {
        auditForm.addEventListener('submit', function(e) {
            // Skip validation jika simpan sebagai draft
            const isDraft = e.submitter && e.submitter.name === 'save_as_draft';
            if (isDraft) {
                return true; // Izinkan submit tanpa validasi
            }
            
            // Get all required fields
            const requiredFields = auditForm.querySelectorAll('[required]');
            let firstInvalidField = null;
            let invalidFields = [];
            
            // Check each required field
            requiredFields.forEach(field => {
                // Skip date fields that will be validated separately
                if (field.type === 'date' && field.classList.contains('date-field')) {
                    return;
                }
                
                if (!field.value || field.value.trim() === '') {
                    field.classList.add('is-invalid');
                    field.style.borderColor = '#dc3545';
                    invalidFields.push(field);
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                } else {
                    field.classList.remove('is-invalid');
                    field.style.borderColor = '';
                }
            });
            
            // Special validation for date fields with "Ada" selection
            const radioAda = document.querySelectorAll('input[type="radio"].radio-ada:checked');
            radioAda.forEach(radio => {
                const itemId = radio.getAttribute('data-item-id');
                if (itemId) {
                    const dateField = document.getElementById(`date-${itemId}`);
                    if (dateField && !dateField.value) {
                        dateField.classList.add('is-invalid');
                        dateField.style.borderColor = '#dc3545';
                        invalidFields.push(dateField);
                        if (!firstInvalidField) {
                            firstInvalidField = dateField;
                        }
                    }
                }
            });
            
            // If there are invalid fields, prevent submission and show message
            if (invalidFields.length > 0) {
                e.preventDefault();
                
                // Create or update error message
                let errorMsg = document.getElementById('form-error-message');
                if (!errorMsg) {
                    errorMsg = document.createElement('div');
                    errorMsg.id = 'form-error-message';
                    errorMsg.className = 'alert alert-danger';
                    errorMsg.style.position = 'fixed';
                    errorMsg.style.top = '20px';
                    errorMsg.style.left = '50%';
                    errorMsg.style.transform = 'translateX(-50%)';
                    errorMsg.style.zIndex = '9999';
                    errorMsg.style.maxWidth = '600px';
                    errorMsg.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                    document.body.appendChild(errorMsg);
                }
                
                errorMsg.innerHTML = `
                    <strong>⚠️ Form belum lengkap!</strong><br>
                    Harap lengkapi ${invalidFields.length} field yang masih kosong sebelum submit.
                `;
                errorMsg.style.display = 'block';
                
                // Auto hide after 5 seconds
                setTimeout(() => {
                    errorMsg.style.display = 'none';
                }, 5000);
                
                // Scroll to first invalid field smoothly
                if (firstInvalidField) {
                    firstInvalidField.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    
                    // Focus after scroll animation completes
                    setTimeout(() => {
                        firstInvalidField.focus();
                    }, 500);
                }
                
                return false;
            }
            
            // Remove error styling from all fields if validation passes
            requiredFields.forEach(field => {
                field.classList.remove('is-invalid');
                field.style.borderColor = '';
            });
        });
        
        // Remove error styling on input
        auditForm.addEventListener('input', function(e) {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
                e.target.style.borderColor = '';
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

