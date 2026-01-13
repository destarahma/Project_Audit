<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['id'])) {
    redirect('audit/list.php');
}

$submissionId = (int)$_GET['id'];
$currentUser = getCurrentUser();

// Only allow deletion of own drafts
$conn = getConnection();
$stmt = $conn->prepare("
    SELECT * FROM audit_submissions 
    WHERE id = ? AND submitted_by = ? AND status = 'draft'
");
$stmt->bind_param("ii", $submissionId, $currentUser['id']);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission && !isAdmin()) {
    flashMessage('Anda tidak dapat menghapus audit ini', 'danger');
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
