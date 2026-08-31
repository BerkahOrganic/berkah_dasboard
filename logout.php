<?php
session_start();
 
// Hapus semua data session
$_SESSION = array();
 
// Hapus session cookie di browser (kalau ada)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
 
// Hancurkan session di server
session_destroy();
 
// Arahkan kembali ke halaman login dengan pesan sukses
header("Location: index.php?pesan=" . urlencode("Logout berhasil"));
exit();
?>
