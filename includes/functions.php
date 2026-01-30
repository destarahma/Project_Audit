<?php
// Helper functions

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Don't save redirect, just go to login
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'user',
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit();
}

function flashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return '-';
    
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp <= 0) return '-';
    
    return date('d/m/Y', $timestamp);
}

function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Generate audit number untuk template tertentu
 * Akan mencari nomor terkecil yang tersedia (untuk reuse nomor yang dihapus)
 * Jika tidak ada gap, akan menggunakan max + 1
 */
function getNextAuditNumber($templateId) {
    $conn = getConnection();
    
    // Cari gap dalam penomoran (nomor yang sudah dihapus)
    $query = "
        SELECT t1.audit_number + 1 AS gap_start
        FROM audit_submissions t1
        WHERE t1.template_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM audit_submissions t2 
            WHERE t2.template_id = ? 
            AND t2.audit_number = t1.audit_number + 1
        )
        AND t1.audit_number + 1 < (
            SELECT IFNULL(MAX(audit_number), 0) FROM audit_submissions WHERE template_id = ?
        )
        ORDER BY gap_start ASC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $templateId, $templateId, $templateId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Ada gap, gunakan nomor terkecil yang kosong
        $row = $result->fetch_assoc();
        return $row['gap_start'];
    } else {
        // Tidak ada gap, gunakan max + 1
        $maxQuery = $conn->prepare("SELECT IFNULL(MAX(audit_number), 0) + 1 AS next_number FROM audit_submissions WHERE template_id = ?");
        $maxQuery->bind_param("i", $templateId);
        $maxQuery->execute();
        $maxResult = $maxQuery->get_result();
        $maxRow = $maxResult->fetch_assoc();
        return $maxRow['next_number'];
    }
}
?>
