<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) {
    redirect('audit/list.php');
}

$submissionId = (int)$_GET['id'];
$currentUser = getCurrentUser();

// Get submission details
$conn = getConnection();
$stmt = $conn->prepare("
    SELECT * FROM audit_submissions 
    WHERE id = ?
");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    flashMessage('Audit tidak ditemukan', 'danger');
    redirect('audit/list.php');
}

// Check permissions
// Admin can delete any audit
// Regular user can only delete their own audits
if (!isAdmin() && $submission['submitted_by'] != $currentUser['id']) {
    flashMessage('Anda tidak memiliki izin untuk menghapus audit ini', 'danger');
    redirect('audit/list.php');
}

// Delete submission and responses (cascade)
$stmt = $conn->prepare("DELETE FROM audit_submissions WHERE id = ?");
$stmt->bind_param("i", $submissionId);

if ($stmt->execute()) {
    flashMessage('Audit berhasil dihapus', 'success');
} else {
    flashMessage('Gagal menghapus audit', 'danger');
}

$stmt->close();
$conn->close();

redirect('audit/list.php');
?>
