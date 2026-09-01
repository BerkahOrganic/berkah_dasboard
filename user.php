<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_admin();

$menu_aktif = $_GET['menu'] ?? 'user';
require_once 'koneksi.php';

/* =====================================================
   KONFIGURASI NAMA KOLOM TABEL 'login' & 'karyawan'
   (disesuaikan dengan struktur database db_bekah_presensi)
   login: id_user, password, username, activity(on/off), nik, hak_akses, mac
   karyawan: nik, nama, status_aktif, id_unit, id_jabatan
   login.nik -> FOREIGN KEY ke karyawan.nik
===================================================== */
const KOLOM_ID       = 'id_user';
const KOLOM_USERNAME = 'username';
const KOLOM_PASSWORD = 'password';
const KOLOM_NIK       = 'nik';
const KOLOM_LEVEL    = 'hak_akses';
const KOLOM_MAC       = 'mac';
const KOLOM_ACTIVITY  = 'activity';

/* =====================================================
   FLASH MESSAGE (notifikasi sukses/gagal setelah redirect)
===================================================== */
function set_flash($tipe, $pesan)
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function ambil_flash()
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* Bangun ulang query string GET saat ini (untuk redirect balik ke filter/halaman yang sama) */
function redirect_kembali()
{
    $qs = $_GET;
    unset($qs['action']); // buang parameter aksi kalau ada di GET
    $url = 'user.php' . (count($qs) ? '?' . http_build_query($qs) : '');
    header('Location: ' . $url);
    exit;
}

/* =====================================================
   PROSES AKSI: TAMBAH / EDIT / HAPUS (via POST)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $koneksi) {

    $aksi = $_POST['aksi'] ?? '';

    // ---------------- TAMBAH USER ----------------
    if ($aksi === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $nik      = trim($_POST['nik'] ?? '');
        $level    = $_POST['hak_akses'] ?? 'user';
        $mac      = trim($_POST['mac'] ?? '');
        $mac      = $mac === '' ? null : $mac;

        if ($username === '' || $password === '' || $nik === '') {
            set_flash('danger', 'Username, password, dan Karyawan (NIK) wajib diisi.');
            redirect_kembali();
        }

        // Pastikan NIK terdaftar di tabel karyawan (menjaga FOREIGN KEY fk_login_nik)
        $cek_nik = mysqli_prepare($koneksi, "SELECT nik FROM karyawan WHERE nik = ?");
        mysqli_stmt_bind_param($cek_nik, 's', $nik);
        mysqli_stmt_execute($cek_nik);
        mysqli_stmt_store_result($cek_nik);
        if (mysqli_stmt_num_rows($cek_nik) === 0) {
            set_flash('danger', 'Karyawan yang dipilih tidak ditemukan.');
            mysqli_stmt_close($cek_nik);
            redirect_kembali();
        }
        mysqli_stmt_close($cek_nik);

        // Cek username sudah dipakai atau belum
        $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID . " FROM login WHERE " . KOLOM_USERNAME . " = ?");
        mysqli_stmt_bind_param($cek, 's', $username);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            set_flash('danger', 'Username sudah digunakan, silakan pilih username lain.');
            mysqli_stmt_close($cek);
            redirect_kembali();
        }
        mysqli_stmt_close($cek);

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO login (" . KOLOM_USERNAME . ", " . KOLOM_PASSWORD . ", " . KOLOM_NIK . ", " . KOLOM_LEVEL . ", " . KOLOM_MAC . ", " . KOLOM_ACTIVITY . ") VALUES (?, ?, ?, ?, ?, 'off')"
        );
        mysqli_stmt_bind_param($stmt, 'sssss', $username, $password_hash, $nik, $level, $mac);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'User baru berhasil ditambahkan.');
        } else {
            set_flash('danger', 'Gagal menambahkan user: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }

    // ---------------- EDIT USER ----------------
    if ($aksi === 'edit') {
        $id       = $_POST['id_user'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // kosong = tidak ganti password
        $nik      = trim($_POST['nik'] ?? '');
        $level    = $_POST['hak_akses'] ?? 'user';
        $mac      = trim($_POST['mac'] ?? '');
        $mac      = $mac === '' ? null : $mac;

        if ($id === '' || $username === '' || $nik === '') {
            set_flash('danger', 'Data tidak lengkap untuk mengubah user.');
            redirect_kembali();
        }

        // Pastikan NIK terdaftar di tabel karyawan
        $cek_nik = mysqli_prepare($koneksi, "SELECT nik FROM karyawan WHERE nik = ?");
        mysqli_stmt_bind_param($cek_nik, 's', $nik);
        mysqli_stmt_execute($cek_nik);
        mysqli_stmt_store_result($cek_nik);
        if (mysqli_stmt_num_rows($cek_nik) === 0) {
            set_flash('danger', 'Karyawan yang dipilih tidak ditemukan.');
            mysqli_stmt_close($cek_nik);
            redirect_kembali();
        }
        mysqli_stmt_close($cek_nik);

        // Cek username dipakai user lain
        $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID . " FROM login WHERE " . KOLOM_USERNAME . " = ? AND " . KOLOM_ID . " != ?");
        mysqli_stmt_bind_param($cek, 'ss', $username, $id);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            set_flash('danger', 'Username sudah dipakai user lain.');
            mysqli_stmt_close($cek);
            redirect_kembali();
        }
        mysqli_stmt_close($cek);

        if ($password !== '') {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE login SET " . KOLOM_USERNAME . " = ?, " . KOLOM_PASSWORD . " = ?, " . KOLOM_NIK . " = ?, " . KOLOM_LEVEL . " = ?, " . KOLOM_MAC . " = ? WHERE " . KOLOM_ID . " = ?"
            );
            mysqli_stmt_bind_param($stmt, 'ssssss', $username, $password_hash, $nik, $level, $mac, $id);
        } else {
            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE login SET " . KOLOM_USERNAME . " = ?, " . KOLOM_NIK . " = ?, " . KOLOM_LEVEL . " = ?, " . KOLOM_MAC . " = ? WHERE " . KOLOM_ID . " = ?"
            );
            mysqli_stmt_bind_param($stmt, 'sssss', $username, $nik, $level, $mac, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Data user berhasil diperbarui.');
        } else {
            set_flash('danger', 'Gagal memperbarui user: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }

    // ---------------- HAPUS USER ----------------
    if ($aksi === 'hapus') {
        $id = $_POST['id_user'] ?? '';

        if ($id === '') {
            set_flash('danger', 'ID user tidak valid.');
            redirect_kembali();
        }

        $stmt = mysqli_prepare($koneksi, "DELETE FROM login WHERE " . KOLOM_ID . " = ?");
        mysqli_stmt_bind_param($stmt, 's', $id);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'User berhasil dihapus.');
        } else {
            set_flash('danger', 'Gagal menghapus user: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }
}

$flash = ambil_flash();

/* =====================================================
   AMBIL DATA UNTUK DITAMPILKAN (GET, filter, pagination)
===================================================== */
$role_filter = $_GET['role'] ?? '';
$cari        = trim($_GET['cari'] ?? '');
$halaman     = max(1, (int) ($_GET['halaman'] ?? 1));
$per_halaman = 8; // Jumlah card per halaman

$data_user = [];

if ($koneksi) {
    $where  = [];
    $tipe   = '';
    $params = [];

    if (!empty($cari)) {
        $where[] = "(l." . KOLOM_USERNAME . " LIKE CONCAT('%', ?, '%') OR k.nama LIKE CONCAT('%', ?, '%'))";
        $tipe .= 'ss';
        $params[] = &$cari;
        $params[] = &$cari;
    }
    if (!empty($role_filter)) {
        $where[] = "l." . KOLOM_LEVEL . " = ?";
        $tipe .= 's';
        $params[] = &$role_filter;
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // JOIN ke tabel karyawan untuk mengambil nama, unit, dan jabatan
    $query_user = "SELECT l.*, k.nama AS nama_user
                   FROM login l
                   LEFT JOIN karyawan k ON k." . KOLOM_NIK . " = l." . KOLOM_NIK . "
                   $where_sql
                   ORDER BY l." . KOLOM_USERNAME . " ASC";
    $stmt_user  = mysqli_prepare($koneksi, $query_user);

    if ($stmt_user) {
        if ($tipe !== '') {
            array_unshift($params, $tipe);
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_user], $params));
        }
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);

        if ($result_user) {
            while ($row = mysqli_fetch_assoc($result_user)) {
                $data_user[] = $row;
            }
        }
        mysqli_stmt_close($stmt_user);
    }
}

// ================= PAGINATION =================
$total_data    = count($data_user);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_user, $offset, $per_halaman);

/* =====================================================
   DAFTAR KARYAWAN AKTIF (untuk dropdown pilih NIK di modal)
===================================================== */
$daftar_karyawan = [];
if ($koneksi) {
    $q_karyawan = mysqli_query($koneksi, "SELECT nik, nama FROM karyawan WHERE status_aktif = 'Aktif' ORDER BY nama ASC");
    if ($q_karyawan) {
        while ($k = mysqli_fetch_assoc($q_karyawan)) {
            $daftar_karyawan[] = $k;
        }
    }
}

function build_query($override = [])
{
    $params = array_merge($_GET, $override);
    return htmlspecialchars('?' . http_build_query($params));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data User - Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --teal-dark: #1a5d0e;
            --teal-mid: #2e861e;
            --teal-light: #4ed137;
            --bg-page: #a7eb9b;
        }

        body {
            background-color: var(--bg-page);
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 2rem;
        }

        .app-shell {
            max-width: auto;
            margin: 50px 100px;
            display: flex;
            background: #f4f7f6;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: linear-gradient(160deg, var(--teal-dark) 0%, var(--teal-mid) 60%, var(--teal-light) 100%);
            padding: 1.75rem 1.25rem;
            display: flex;
            flex-direction: column;
            color: #fff;
        }
        .brand {
            padding-left: 20px;
            height: 40px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            flex-shrink: 0;
            margin-top: 0;
        }
        .brand .accent { color: #ffd23f; }

        .menu-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.65);
            margin: 0.5rem 0 0.9rem 0.75rem;
        }

        .menu-nav {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .menu-nav .menu-item a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 0.9rem;
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
            transition: background 0.2s ease, color 0.2s ease;
        }

         .menu-nav .menu-item a i {
            font-size: 1.05rem;
        }

        .menu-nav .menu-item a:hover { background: rgba(255, 255, 255, 0.15); }

        .menu-nav .menu-item.active a {
            background: #fff;
            color: var(--teal-dark);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
        }

        .sidebar-footer { margin-top: auto; padding-top: 1.5rem; }

        .btn-logout {
            width: 100%;
            background: rgba(255, 255, 255, 0.18);
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 0.6rem;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-logout:hover { background: rgba(255, 255, 255, 0.3); color: #fff; }

        .main-content {
            flex: 1;
            padding: 1.75rem 2rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: 640px;
        }

        .page-title { font-weight: 700; color: #2d3a3a; margin-bottom: 0.2rem; }
        .page-sub { color: #8a9797; font-size: 0.85rem; margin-bottom: 1.5rem; }

        .filter-form .form-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #8a9797;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .filter-form .form-control, .filter-form .form-select {
            border-radius: 10px;
            border-color: #e4ece4;
            background: #f8faf8;
            font-size: 0.85rem;
        }

        /* Card User Styles */
        .card-user {
            background: #fff;
            border: 1px solid #eef4f3;
            border-radius: 16px;
            padding: 1.25rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .card-user:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(23, 161, 154, 0.15);
        }

        .avatar-user {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #e4f7e9;
            color: var(--teal-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 0.8rem;
        }

        .user-nama { font-weight: 700; font-size: 0.95rem; color: #2d3a3a; margin-bottom: 0.1rem; }
        .user-username { font-size: 0.8rem; color: #8a9797; margin-bottom: 0.6rem; }

        .badge-role {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            background: #e3f8ea;
            color: #1a7a3f;
        }

        .card-actions {
            margin-top: auto;
            display: flex;
            gap: 0.5rem;
            width: 100%;
        }

        .card-actions .btn,
        .card-actions form {
            flex: 1;
        }

        .card-actions .btn {
            width: 100%;
            font-size: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            padding: 0.45rem 0.5rem;

        }

        @media (max-width: 768px) {
            .app-shell { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="app-shell">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="brand">
                <img src="bg-login/logo-berkah.png" alt="Logo Berkah" class="brand-logo">
                <span><span class="accent">B</span>erkah</span>
            </div>

            <div class="menu-label">Main Menu</div>

            <ul class="menu-nav">
                <li class="menu-item <?php echo $menu_aktif === 'dashboard' ? 'active' : ''; ?>">
                    <a href="dashboard.php?menu=dashboard"><i class="bi bi-grid-fill"></i> Dashboard</a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'absensi' ? 'active' : ''; ?>">
                    <a href="absensi.php?menu=absensi"><i class="bi bi-person-check-fill"></i> Absensi</a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'karyawan' ? 'active' : ''; ?>">
                    <a href="karyawan.php?menu=karyawan"><i class="bi bi-people-fill"></i> Karyawan</a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'user' ? 'active' : ''; ?>">
                    <a href="user.php?menu=user"><i class="bi bi-person-badge-fill"></i> User</a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'jabatan' ? 'active' : ''; ?>">
                    <a href="jabatan.php?menu=jabatan"><i class="bi bi-briefcase-fill"></i> Jabatan</a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'login_unit' ? 'active' : ''; ?>">
                    <a href="login_unit.php?menu=login_unit"><i class="bi bi-building"></i> Login Unit</a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'setting' ? 'active' : ''; ?>">
                    <a href="setting.php?menu=setting"><i class="bi bi-gear-fill"></i> Setting</a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <form action="logout.php" method="POST">
                    <button type="submit" class="btn-logout">
                        <i class="bi bi-box-arrow-right"></i> Go Out
                    </button>
                </form>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="page-title">Manajemen User</h5>
                    <p class="page-sub">Kelola akun akses sistem aplikasi Berkah Global Business</p>
                </div>
                <button type="button" class="btn btn-sm btn-success" style="background:var(--teal-mid); border:none; border-radius:10px; padding: 0.5rem 1rem;" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-lg"></i> Tambah User
                </button>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['tipe']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash['pesan']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- FILTER FORM -->
            <form class="row g-2 align-items-end filter-form mb-4" method="GET" action="user.php">
                <input type="hidden" name="menu" value="user">

                <div class="col-12 col-md-6">
                    <label class="form-label">Cari User</label>
                    <input type="text" name="cari" class="form-control" placeholder="Username / Nama User..." value="<?php echo htmlspecialchars($cari); ?>">
                </div>

                <div class="col-6 col-md-4">
                    <label class="form-label">Role / Level</label>
                    <select name="role" class="form-select">
                        <option value="">Semua Level</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="supervisor" <?php echo $role_filter === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 d-grid">
                    <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none; border-radius:10px;">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </form>

            <!-- CARD USER GRID -->
            <?php if (count($data_tampil) === 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-person-badge" style="font-size:2.5rem;"></i>
                    <p class="mt-2">Tidak ada data user yang ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($data_tampil as $row):
                        $id_user   = $row[KOLOM_ID] ?? '';
                        $username  = $row[KOLOM_USERNAME] ?? '';
                        $nik       = $row[KOLOM_NIK] ?? '';
                        $nama_user = $row['nama_user'] ?? $username;
                        $level     = $row[KOLOM_LEVEL] ?? 'user';
                        $mac       = $row[KOLOM_MAC] ?? '';
                        $activity  = $row[KOLOM_ACTIVITY] ?? 'off';
                    ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card-user">
                                <div class="avatar-user">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div class="user-nama">
                                    <?php echo htmlspecialchars($nama_user); ?>
                                    <?php if ($activity === 'on'): ?>
                                        <i class="bi bi-circle-fill text-success" style="font-size:0.5rem; vertical-align:middle;" title="Sedang aktif"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="user-username">
                                    @<?php echo htmlspecialchars($username); ?>
                                </div>

                                <span class="badge-role">
                                    <i class="bi bi-shield-check me-1"></i>
                                    <?php echo htmlspecialchars($level); ?>
                                </span>

                                <div class="card-actions">
                                    <button type="button"
                                        class="btn btn-outline-success btn-edit-user"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEdit"
                                        data-id="<?php echo htmlspecialchars($id_user); ?>"
                                        data-username="<?php echo htmlspecialchars($username); ?>"
                                        data-nik="<?php echo htmlspecialchars($nik); ?>"
                                        data-level="<?php echo htmlspecialchars($level); ?>"
                                        data-mac="<?php echo htmlspecialchars($mac); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <form method="POST" action="user.php" onsubmit="return confirm('Hapus user @<?php echo htmlspecialchars($username); ?> ?');">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id_user" value="<?php echo htmlspecialchars($id_user); ?>">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- FOOTER & PAGINATION -->
            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                <div class="text-muted" style="font-size: 0.8rem;">
                    <?php if ($total_data > 0): ?>
                        Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $per_halaman, $total_data); ?> dari <?php echo $total_data; ?> user
                    <?php else: ?>
                        0 user
                    <?php endif; ?>
                </div>

                <?php if ($total_halaman > 1): ?>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?php echo $halaman <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo build_query(['halaman' => $halaman - 1]); ?>">‹</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_halaman; $p++): ?>
                            <li class="page-item <?php echo $p === $halaman ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo build_query(['halaman' => $p]); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $halaman >= $total_halaman ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo build_query(['halaman' => $halaman + 1]); ?>">›</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </main>

    </div>

    <!-- ===================== MODAL TAMBAH USER ===================== -->
    <div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="user.php">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Tambah User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Karyawan</label>
                            <select name="nik" class="form-select" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($daftar_karyawan as $k): ?>
                                    <option value="<?php echo htmlspecialchars($k['nik']); ?>">
                                        <?php echo htmlspecialchars($k['nama']); ?> (<?php echo htmlspecialchars($k['nik']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role / Hak Akses</label>
                            <select name="hak_akses" class="form-select">
                                <option value="user">User</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">MAC Address <span class="text-muted">(opsional)</span></label>
                            <input type="text" name="mac" class="form-control" placeholder="Contoh: 00:1A:2B:3C:4D:5E">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL EDIT USER ===================== -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="user.php" id="formEdit">
                    <input type="hidden" name="aksi" value="edit">
                    <input type="hidden" name="id_user" id="edit_id_user">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Karyawan</label>
                            <select name="nik" id="edit_nik" class="form-select" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($daftar_karyawan as $k): ?>
                                    <option value="<?php echo htmlspecialchars($k['nik']); ?>">
                                        <?php echo htmlspecialchars($k['nama']); ?> (<?php echo htmlspecialchars($k['nik']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password" id="edit_password" class="form-control" minlength="6" placeholder="Kosongkan jika tidak ingin mengubah password">
                            <div class="form-text">Biarkan kosong jika tidak ingin mengganti password.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role / Hak Akses</label>
                            <select name="hak_akses" id="edit_level" class="form-select">
                                <option value="user">User</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">MAC Address <span class="text-muted">(opsional)</span></label>
                            <input type="text" name="mac" id="edit_mac" class="form-control" placeholder="Contoh: 00:1A:2B:3C:4D:5E">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none;">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Isi otomatis modal Edit dengan data user yang diklik
        document.querySelectorAll('.btn-edit-user').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_id_user').value  = btn.dataset.id;
                document.getElementById('edit_username').value = btn.dataset.username;
                document.getElementById('edit_nik').value      = btn.dataset.nik;
                document.getElementById('edit_level').value    = btn.dataset.level;
                document.getElementById('edit_mac').value      = btn.dataset.mac;
                document.getElementById('edit_password').value = '';
            });
        });
    </script>
</body>
</html>