<?php
/**
 * ==========================================================
 *  auth.php - Guard akses login & hak akses (role)
 *  Wajib di-include SETELAH session_start() di setiap
 *  halaman yang butuh proteksi.
 * ==========================================================
 */

/**
 * Pastikan user sudah login.
 * Kalau belum, tendang balik ke halaman login.
 */
function require_login()
{
    if (empty($_SESSION['idUser'])) {
        header('Location: index.php?pesan=' . urlencode('Silakan login terlebih dahulu'));
        exit();
    }
}

/**
 * Pastikan user sudah login DAN hak_akses-nya termasuk
 * salah satu role yang diizinkan.
 *
 * @param array $allowed_roles contoh: ['admin'] atau ['admin','supervisor']
 */
function require_role(array $allowed_roles)
{
    require_login();

    $role = $_SESSION['roleUser'] ?? '';

    if (!in_array($role, $allowed_roles, true)) {
        header('Location: dashboard.php?pesan=' . urlencode('Anda tidak memiliki akses ke halaman ini'));
        echo '<script>alert("Anda tidak memiliki akses ke halaman ini"); window.location.href = "dashboard.php";</script>';
        exit();
    }
}

/**
 * Shortcut khusus admin.
 */
function require_admin()
{
    require_role(['admin']);
}
