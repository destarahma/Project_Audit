<?php
// Helper functions

function isLoggedIn() {
    return true; // Always logged in (no login required)
}

function requireLogin() {
    // No login required - disabled
}

function getCurrentUser() {
    // Return default user since no login required
    return [
        'id' => 1,
        'username' => 'user',
        'full_name' => 'User',
        'email' => 'user@audit.com',
        'role' => 'admin'
    ];
}

function isAdmin() {
    return true; // Everyone is admin (no login required)
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
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
