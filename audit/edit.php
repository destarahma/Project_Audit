<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/business_logic.php';

requireLogin();

$pageTitle = 'Edit Audit';
$currentUser = getCurrentUser();

// Get submission ID from URL
$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($submissionId === 0) {
    flashMessage('Audit tidak ditemukan', 'danger');
    redirect('audit/list.php');
}

$conn = getConnection();
$bl = getBusinessLogic();

// Get submission data
$stmt = $conn->prepare("
    SELECT s.*, t.template_name, t.id as template_id, t.template_code
    FROM audit_submissions s
    JOIN audit_templates t ON s.template_id = t.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$submission) {
    flashMessage('Audit tidak ditemukan', 'danger');
    redirect('audit/list.php');
}

// Check permission - only draft can be edited and only by owner or admin
if ($submission['status'] !== 'draft') {
    flashMessage('Hanya audit dengan status Draft yang dapat diedit', 'danger');
    redirect('audit/view.php?id=' . $submissionId);
}

if (!isAdmin() && $submission['submitted_by'] != $currentUser['id']) {
    flashMessage('Anda tidak memiliki akses untuk mengedit audit ini', 'danger');
    redirect('audit/list.php');
}

$templateId = $submission['template_id'];

// Get existing responses
$stmt = $conn->prepare("SELECT item_id, response_value FROM audit_responses WHERE submission_id = ?");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$result = $stmt->get_result();
$existingResponses = [];
while ($row = $result->fetch_assoc()) {
    $existingResponses[$row['item_id']] = $row['response_value'];
}
$stmt->close();

// Load Excel CSS
echo '<link rel="stylesheet" href="../assets/css/excel-style.css">';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Validations (only if submitting, not for draft)
    if ($saveStatus === 'submitted') {
        if (isset($_POST['dp_amount']) && !empty($_POST['dp_amount']) && !empty($totalPrice)) {
            $dpValidation = $bl->validateDP($_POST['dp_amount'], $totalPrice);
            if (!$dpValidation['valid']) {
                $errors[] = $dpValidation['message'];
            }
        }
        
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
        
        if (isset($_POST['actual_qty']) && isset($_POST['spk_qty'])) {
            $qtyValidation = $bl->validateQuantity($_POST['actual_qty'], $_POST['spk_qty']);
            if (!$qtyValidation['valid']) {
                $errors[] = $qtyValidation['message'];
            }
        }
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            flashMessage($error, 'danger');
        }
    } else {
        // Get approval data
        $approvalData = $bl->getRequiredApprovals($templateId, $totalPrice);
        $approvalText = $approvalData['approval_text'];
        $approvalLevel = $approvalData['level'];
        
        // Calculate score
        $totalScore = 0;
        $maxScore = 0;
        
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
    
        if (isset($_POST['responses'])) {
            foreach ($_POST['responses'] as $itemId => $value) {
                if (isset($items[$itemId])) {
                    $scoreValue = $items[$itemId]['score_value'];
                    $fieldType = $items[$itemId]['field_type'];
                    
                    if ($fieldType === 'checkbox' || $fieldType === 'radio') {
                        if ($value === 'ada' || $value === 'sesuai' || $value === 'ya') {
                            $totalScore += $scoreValue;
                        }
                    }
                }
            }
        }
        
        $percentageScore = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        
        $autoStatus = '';
        if ($percentageScore >= 80) {
            $autoStatus = 'Lengkap';
        } elseif ($percentageScore >= 60) {
            $autoStatus = 'Perlu Dilengkapi';
        } else {
            $autoStatus = 'Dalam Proses';
        }
        
        // Update submission
        $stmt = $conn->prepare("
            UPDATE audit_submissions 
            SET submission_date = ?, seller_name = ?, unit_location = ?, quantity = ?, unit_price = ?, total_price = ?, 
                total_score = ?, percentage_score = ?, auto_status = ?, required_approvals = ?, approval_level = ?, 
                has_refund = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssdsssiissi", 
            $submissionDate, $sellerName, $unitLocation, $quantity, $unitPrice, $totalPrice, 
            $totalScore, $percentageScore, $autoStatus, $approvalText, $approvalLevel, 
            $hasRefund, $saveStatus, $notes, $submissionId
        );
        
        if ($stmt->execute()) {
            // Delete old responses
            $conn->query("DELETE FROM audit_responses WHERE submission_id = $submissionId");
            
            // Save new responses
            if (isset($_POST['responses'])) {
                $stmtResponse = $conn->prepare("INSERT INTO audit_responses (submission_id, item_id, response_value) VALUES (?, ?, ?)");
                
                foreach ($_POST['responses'] as $itemId => $value) {
                    $checkField = $conn->query("SELECT field_type FROM template_items WHERE id = $itemId");
                    $fieldType = '';
                    if ($checkField && $fieldData = $checkField->fetch_assoc()) {
                        $fieldType = $fieldData['field_type'] ?? '';
                    }
                    
                    if (empty($value) && $value !== '0' && !in_array($fieldType, ['date', 'text', 'textarea', 'file'])) continue;
                    
                    $responseValue = is_array($value) ? $value['value'] : $value;
                    
                    if (empty($responseValue) && $responseValue !== '0' && !in_array($fieldType, ['date', 'text', 'textarea', 'file'])) continue;
                    
                    if (!empty($photoFiles)) {
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
            
            if ($saveStatus === 'draft') {
                flashMessage('Audit berhasil diupdate sebagai Draft.', 'success');
            } else {
                flashMessage('Audit berhasil diupdate dan disubmit. Skor: ' . number_format($percentageScore, 1) . '% (' . $autoStatus . ').', 'success');
            }
            redirect('audit/view.php?id=' . $submissionId);
        } else {
            flashMessage('Gagal mengupdate audit', 'danger');
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
    <h1>Edit Audit - <?php echo htmlspecialchars($submission['template_name']); ?></h1>
    <a href="view.php?id=<?php echo $submissionId; ?>" class="btn btn-secondary">← Kembali</a>
</div>

<div class="card excel-form">
    <div class="excel-header">
        <h2><?php echo htmlspecialchars($submission['template_name']); ?></h2>
        <div style="margin-top: 10px; font-size: 14px; opacity: 0.9;">
            Status: <span class="badge badge-draft">Draft</span>
        </div>
    </div>
    
    <form method="POST" action="" id="auditForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="template_id" value="<?php echo $templateId; ?>">
        
        <?php if ($templateId != 9 && $templateId != 10): ?>
        <!-- Header Info (non-PO templates) -->
        <table class="excel-info-table">
            <tr>
                <td width="200"><?php 
                    if ($templateId == 1) {
                        echo 'Penjualan Mix Oil';
                    } elseif ($templateId == 5) {
                        echo 'Self Audit : Barbes';
                    } elseif ($templateId == 6) {
                        echo 'Self Audit : Jual Aset';
                    } else {
                        echo 'Nama Item';
                    }
                ?></td>
                <td><input type="text" name="seller_name" class="form-control" value="<?php echo htmlspecialchars($submission['seller_name']); ?>" required></td>
            </tr>
            <tr>
                <td>Unit / Lokasi</td>
                <td><input type="text" name="unit_location" class="form-control" value="<?php echo htmlspecialchars($submission['unit_location']); ?>"></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>
                    <input type="date" name="submission_date" class="form-control" value="<?php echo htmlspecialchars($submission['submission_date']); ?>" required>
                </td>
            </tr>
            <tr>
                <td>Qty</td>
                <td><input type="text" name="quantity" class="form-control" value="<?php echo htmlspecialchars($submission['quantity']); ?>"></td>
            </tr>
            <tr>
                <td>Harga Satuan</td>
                <td><input type="text" name="unit_price" class="form-control rupiah-input" value="<?php echo htmlspecialchars($submission['unit_price']); ?>"></td>
            </tr>
            <tr>
                <td>Total Harga</td>
                <td><input type="text" name="total_price" class="form-control rupiah-input" value="<?php echo htmlspecialchars($submission['total_price']); ?>" readonly></td>
            </tr>
        </table>
        <?php else: ?>
        <!-- For PO templates, header info is part of template fields -->
        <input type="hidden" name="submission_date" value="<?php echo htmlspecialchars($submission['submission_date']); ?>">
        <?php endif; ?>
        
        <div id="templateFields"></div>
        
        <div class="excel-actions">
            <button type="submit" name="save_as_draft" value="1" class="btn btn-secondary" style="background: #6c757d; color: white;">
                📝 Simpan sebagai Draft
            </button>
            <button type="submit" name="save_and_submit" value="1" class="btn btn-primary">
                💾 Simpan dan Submit
            </button>
            <a href="view.php?id=<?php echo $submissionId; ?>" class="btn btn-secondary">✕ Batal</a>
        </div>
    </form>
</div>

<script>
<?php
// Load JavaScript dari create.php dan replace PHP variables
$createContent = file_get_contents(__DIR__ . '/create.php');
if (preg_match('/<script>(.*?)<\/script>/s', $createContent, $matches)) {
    $jsContent = $matches[1];
    // Replace semua PHP templateId dengan nilai actual
    $jsContent = str_replace('<?php echo $templateId; ?>', $templateId, $jsContent);
    // Hapus DOMContentLoaded yang conflict
    $jsContent = preg_replace('/document\.addEventListener\([\'"]DOMContentLoaded[\'"],\s*function\(\)\s*\{[^}]*loadTemplate[^}]*\}\);/s', '', $jsContent);
    echo $jsContent;
}
?>

// Edit mode: FORCE load template sekarang!
const existingResponses = <?php echo json_encode($existingResponses); ?>;
const editTemplateId = <?php echo $templateId; ?>;

// Panggil loadTemplate langsung
if (typeof loadTemplate === 'function') {
    loadTemplate(editTemplateId);
    
    // Populate data existing setelah 3 detik
    setTimeout(function() {
        console.log('Populating existing data...');
        for (const [itemId, value] of Object.entries(existingResponses)) {
            const radio = document.querySelector(`input[name="responses[${itemId}]"][value="${value}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            const input = document.querySelector(`input[name="responses[${itemId}]"]:not([type="radio"]):not([type="checkbox"]), textarea[name="responses[${itemId}]"], select[name="responses[${itemId}]"]`);
            if (input) {
                input.value = value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    }, 3000);
} else {
    console.error('loadTemplate not found!');
}
</script>

<?php include '../includes/footer.php'; ?>
