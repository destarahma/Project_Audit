<?php
// Business Logic Engine for Audit System

class BusinessLogic {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * DIGITALISASI LOGIKA BISNIS #1: Approval QCF Otomatis
     * Menentukan approval yang diperlukan berdasarkan nilai transaksi
     * Dengan kategori: Procurement dan Finance
     */
    public function getRequiredApprovals($templateId, $totalPrice) {
        $price = floatval(str_replace(['Rp', '.', ',', ' '], '', $totalPrice));
        
        $stmt = $this->conn->prepare("
            SELECT rule_name, required_approval, approval_category, condition_operator, condition_value, approval_level
            FROM approval_rules 
            WHERE template_id = ? AND is_active = 1
            ORDER BY approval_level ASC, approval_category ASC
        ");
        $stmt->bind_param("i", $templateId);
        $stmt->execute();
        $rules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $approvals = [
            'Procurement' => '',
            'Finance' => ''
        ];
        $approvalLevel = 1;
        $matched = false;
        
        foreach ($rules as $rule) {
            $matches = false;
            
            if ($rule['condition_operator'] == '<=' && $price <= floatval($rule['condition_value'])) {
                $matches = true;
            } elseif ($rule['condition_operator'] == '<' && $price < floatval($rule['condition_value'])) {
                $matches = true;
            } elseif ($rule['condition_operator'] == 'between' && strpos($rule['condition_value'], '-') !== false) {
                list($min, $max) = explode('-', $rule['condition_value']);
                if ($price > floatval($min) && $price <= floatval($max)) {
                    $matches = true;
                }
            } elseif ($rule['condition_operator'] == '>' && $price > floatval($rule['condition_value'])) {
                $matches = true;
            }
            
            if ($matches) {
                $category = $rule['approval_category'] ?? 'Other';
                $approvals[$category] = $rule['required_approval'];
                $approvalLevel = $rule['approval_level'];
                $matched = true;
            }
        }
        
        // Build approval text
        $approvalText = [];
        if (!empty($approvals['Procurement'])) {
            $approvalText[] = "Procurement: " . $approvals['Procurement'];
        }
        if (!empty($approvals['Finance'])) {
            $approvalText[] = "Finance: " . $approvals['Finance'];
        }
        
        return [
            'approvals' => $approvals,
            'approval_text' => implode(' | ', $approvalText),
            'level' => $approvalLevel,
            'price' => $price
        ];
    }
    
    /**
     * DIGITALISASI LOGIKA BISNIS #1.2: Get Dynamic Approval Items
     * Menampilkan hanya approval yang sesuai level
     */
    public function getApprovalItemsByLevel($templateId, $approvalLevel) {
        $stmt = $this->conn->prepare("
            SELECT id, item_name, item_order
            FROM approval_items 
            WHERE template_id = ? AND required_for_level = ? AND is_active = 1
            ORDER BY item_order ASC
        ");
        $stmt->bind_param("ii", $templateId, $approvalLevel);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * DIGITALISASI VALIDASI #1: DP Minimal 50%
     */
    public function validateDP($dpAmount, $totalPrice) {
        $dp = floatval(str_replace(['Rp', '.', ',', ' '], '', $dpAmount));
        $total = floatval(str_replace(['Rp', '.', ',', ' '], '', $totalPrice));
        
        if ($total <= 0) return ['valid' => false, 'message' => 'Total harga harus diisi'];
        
        $percentage = ($dp / $total) * 100;
        
        if ($percentage < 50) {
            return [
                'valid' => false, 
                'message' => "DP minimal 50% (Rp " . number_format($total * 0.5, 0, ',', '.') . "). Anda hanya membayar " . number_format($percentage, 1) . "%"
            ];
        }
        
        return ['valid' => true, 'percentage' => $percentage];
    }
    
    /**
     * DIGITALISASI VALIDASI #2: Pelunasan Sebelum Barang Keluar
     */
    public function validatePaymentComplete($dp1, $dp2, $totalPrice) {
        $payment1 = floatval(str_replace(['Rp', '.', ',', ' '], '', $dp1));
        $payment2 = floatval(str_replace(['Rp', '.', ',', ' '], '', $dp2));
        $total = floatval(str_replace(['Rp', '.', ',', ' '], '', $totalPrice));
        
        $totalPaid = $payment1 + $payment2;
        $remaining = $total - $totalPaid;
        
        if ($remaining > 0) {
            return [
                'valid' => false,
                'message' => 'Pembayaran belum lunas. Sisa: Rp ' . number_format($remaining, 0, ',', '.'),
                'remaining' => $remaining
            ];
        }
        
        return ['valid' => true, 'overpayment' => abs($remaining)];
    }
    
    /**
     * DIGITALISASI VALIDASI #3: Qty Tidak Melebihi SPK
     */
    public function validateQuantity($actualQty, $spkQty) {
        $actual = floatval($actualQty);
        $spk = floatval($spkQty);
        
        if ($actual > $spk) {
            return [
                'valid' => false,
                'message' => "Qty tidak boleh melebihi SPK/PJB. SPK: $spk, Actual: $actual"
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * DIGITALISASI PROSES: Cek Tahap yang Boleh Diakses
     */
    public function canAccessStage($submissionId, $stageNumber) {
        // Cek apakah tahap sebelumnya sudah completed
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as incomplete
            FROM workflow_stages ws
            LEFT JOIN audit_workflow_progress awp ON ws.id = awp.stage_id AND awp.submission_id = ?
            WHERE ws.template_id = (SELECT template_id FROM audit_submissions WHERE id = ?)
            AND ws.stage_number < ?
            AND ws.is_required = 1
            AND (awp.completed = 0 OR awp.completed IS NULL)
        ");
        $stmt->bind_param("iii", $submissionId, $submissionId, $stageNumber);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['incomplete'] == 0;
    }
    
    /**
     * DIGITALISASI PROSES: Validasi Section Wajib Lengkap
     * Tidak bisa lanjut jika section sebelumnya belum lengkap
     */
    public function validateSectionComplete($submissionId, $sectionId) {
        // Get all required items in this section
        $stmt = $this->conn->prepare("
            SELECT ti.id, ti.item_text, ti.is_required
            FROM template_items ti
            WHERE ti.section_id = ? AND ti.is_required = 1
        ");
        $stmt->bind_param("i", $sectionId);
        $stmt->execute();
        $requiredItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $incomplete = [];
        foreach ($requiredItems as $item) {
            // Check if this item has response
            $checkStmt = $this->conn->prepare("
                SELECT response_value FROM audit_responses 
                WHERE submission_id = ? AND item_id = ?
            ");
            $checkStmt->bind_param("ii", $submissionId, $item['id']);
            $checkStmt->execute();
            $response = $checkStmt->get_result()->fetch_assoc();
            
            if (!$response || empty($response['response_value'])) {
                $incomplete[] = $item['item_text'];
            }
        }
        
        return [
            'complete' => count($incomplete) == 0,
            'incomplete_items' => $incomplete
        ];
    }
    
    /**
     * DIGITALISASI VALIDASI: Field Tanggal Wajib jika Checklist "Ada"
     */
    public function validateDateIfChecked($responses) {
        $errors = [];
        
        foreach ($responses as $key => $value) {
            // Jika ada checkbox dengan value "ada"
            if ($value === 'ada' || $value === 'sesuai') {
                // Cek apakah ada field tanggal terkait
                $dateKey = $key . '_date';
                if (!isset($responses[$dateKey]) || empty($responses[$dateKey])) {
                    $errors[] = "Tanggal wajib diisi untuk item: " . $key;
                }
            }
        }
        
        return [
            'valid' => count($errors) == 0,
            'errors' => $errors
        ];
    }
    
    /**
     * DIGITALISASI PROSES: Tahap Opsional (Pengembalian Dana)
     */
    public function isStageRequired($submissionId, $stageNumber) {
        // Stage 5 (Pengembalian Dana) hanya muncul jika ada overpayment
        if ($stageNumber == 5) {
            $stmt = $this->conn->prepare("
                SELECT unit_price, quantity, total_price 
                FROM audit_submissions 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $submissionId);
            $stmt->execute();
            $submission = $stmt->get_result()->fetch_assoc();
            
            // Jika ada pembayaran lebih, stage ini required
            // Logic: cek dari audit_responses
            return false; // Default: opsional
        }
        
        return true; // Tahap lain wajib
    }
    
    /**
     * DIGITALISASI OUTPUT: Status Audit Otomatis
     */
    public function calculateAuditStatus($submissionId) {
        // Get all completed checklist
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN ar.response_value = 'ada' THEN 1 ELSE 0 END) as completed_items,
                SUM(ti.score_value) as max_score,
                SUM(CASE WHEN ar.response_value = 'ada' THEN ti.score_value ELSE 0 END) as total_score
            FROM template_items ti
            JOIN template_sections ts ON ti.section_id = ts.id
            LEFT JOIN audit_responses ar ON ti.id = ar.item_id AND ar.submission_id = ?
            WHERE ts.template_id = (SELECT template_id FROM audit_submissions WHERE id = ?)
        ");
        $stmt->bind_param("ii", $submissionId, $submissionId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $percentage = $result['max_score'] > 0 ? ($result['total_score'] / $result['max_score']) * 100 : 0;
        
        // Determine status
        if ($percentage >= 90) {
            $status = 'Sangat Baik';
            $color = 'success';
        } elseif ($percentage >= 75) {
            $status = 'Baik';
            $color = 'info';
        } elseif ($percentage >= 60) {
            $status = 'Cukup';
            $color = 'warning';
        } else {
            $status = 'Perlu Perbaikan';
            $color = 'danger';
        }
        
        return [
            'total_items' => $result['total_items'],
            'completed_items' => $result['completed_items'],
            'completion_rate' => ($result['completed_items'] / $result['total_items']) * 100,
            'total_score' => $result['total_score'],
            'max_score' => $result['max_score'],
            'percentage' => $percentage,
            'status' => $status,
            'color' => $color
        ];
    }
    
    /**
     * DIGITALISASI KONTROL: Field Wajib Dinamis
     */
    public function getConditionalRequiredFields($templateId, $responses) {
        $required = [];
        
        // Jika ada pembayaran, bukti transfer wajib
        if (!empty($responses['payment_amount'])) {
            $required[] = 'payment_proof';
        }
        
        // Jika pilih "Ada" untuk dokumen, tanggal wajib
        foreach ($responses as $key => $value) {
            if ($value === 'ada' && strpos($key, 'dokumen') !== false) {
                $required[] = $key . '_date';
            }
        }
        
        return $required;
    }
}

/**
 * Helper function to get business logic instance
 */
function getBusinessLogic() {
    static $bl = null;
    if ($bl === null) {
        $bl = new BusinessLogic(getConnection());
    }
    return $bl;
}
?>
