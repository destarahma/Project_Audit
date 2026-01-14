<?php
session_start();
session_unset();
session_destroy();
session_write_close();
setcookie(session_name(),'',0,'/');
session_regenerate_id(true);

echo "Session telah dihapus! <br>";
echo "Silakan <a href='index.php'>kembali ke halaman utama</a> untuk login.";
?>
