<?php
// Script untuk generate password hash yang benar
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\n";
echo "Untuk update database, jalankan query ini di phpMyAdmin:\n";
echo "UPDATE users SET password = '$hash' WHERE username = 'admin';\n";
?>
