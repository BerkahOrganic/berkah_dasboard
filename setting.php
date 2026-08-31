<?php
session_start();

$menu_aktif = $_GET['menu'] ?? 'setting';
require_once 'koneksi.php';

$pesan = isset($_GET['pesan']) ? htmlspecialchars($_GET['pesan']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// ================= PROSES SIMPAN BATAS WAKTU - SEMUA KARYAWAN (UPDATE) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_batas_waktu') {
    $id_batas  = (int) ($_POST['id_batas'] ?? 0);
    $jam_batas = trim($_POST['jam_batas'] ?? '');

    if ($koneksi && $id_batas > 0 && $jam_batas !== '') {
        $jam_esc = mysqli_real_escape_string($koneksi, $jam_batas);
        // Menggunakan UPDATE, bukan INSERT, karena baris pengaturan "semua" sudah ada
        $query_update = "UPDATE batas_waktu_hadir SET jam_batas = '$jam_esc' WHERE id = $id_batas";

        if (mysqli_query($koneksi, $query_update)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Batas waktu hadir (semua karyawan) berhasil diperbarui.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal memperbarui batas waktu: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data batas waktu tidak lengkap.'));
        exit;
    }
}

// ================= PROSES TAMBAH ATURAN KHUSUS PER UNIT (INSERT) =================
// Insert dipakai di sini karena aturan untuk unit tersebut memang belum ada barisnya.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_batas_unit') {
    $id_unit   = trim($_POST['id_unit'] ?? '');
    $jam_batas = trim($_POST['jam_batas'] ?? '');

    if ($koneksi && $id_unit !== '' && $jam_batas !== '') {
        // Pastikan unit ini belum punya aturan supaya tidak dobel
        $cek_stmt = mysqli_prepare($koneksi, "SELECT id FROM batas_waktu_hadir WHERE scope_type = 'unit' AND scope_value = ? LIMIT 1");
        mysqli_stmt_bind_param($cek_stmt, 's', $id_unit);
        mysqli_stmt_execute($cek_stmt);
        $cek_hasil = mysqli_stmt_get_result($cek_stmt);

        if ($cek_hasil && mysqli_num_rows($cek_hasil) > 0) {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Unit ini sudah memiliki aturan batas waktu. Silakan edit aturan yang sudah ada.'));
            exit;
        }

        $stmt = mysqli_prepare($koneksi, "INSERT INTO batas_waktu_hadir (jam_batas, scope_type, scope_value, updated_at) VALUES (?, 'unit', ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'ss', $jam_batas, $id_unit);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Aturan batas waktu khusus unit berhasil ditambahkan.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menambahkan aturan unit: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Pilih unit dan isi jam batas terlebih dahulu.'));
        exit;
    }
}

// ================= PROSES UPDATE ATURAN KHUSUS PER UNIT (UPDATE) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'update_batas_unit') {
    $id_batas  = (int) ($_POST['id_batas'] ?? 0);
    $jam_batas = trim($_POST['jam_batas'] ?? '');

    if ($koneksi && $id_batas > 0 && $jam_batas !== '') {
        // Menggunakan UPDATE karena aturan unit ini sudah ada barisnya, hanya jamnya yang diubah
        $stmt = mysqli_prepare($koneksi, "UPDATE batas_waktu_hadir SET jam_batas = ? WHERE id = ? AND scope_type = 'unit'");
        mysqli_stmt_bind_param($stmt, 'si', $jam_batas, $id_batas);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Aturan batas waktu unit berhasil diperbarui.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal memperbarui aturan unit: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data aturan unit tidak lengkap.'));
        exit;
    }
}

// ================= PROSES HAPUS ATURAN KHUSUS PER UNIT (DELETE) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_batas_unit') {
    $id_batas = (int) ($_POST['id_batas'] ?? 0);

    if ($koneksi && $id_batas > 0) {
        $stmt = mysqli_prepare($koneksi, "DELETE FROM batas_waktu_hadir WHERE id = ? AND scope_type = 'unit'");
        mysqli_stmt_bind_param($stmt, 'i', $id_batas);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Aturan batas waktu unit berhasil dihapus, unit ini kembali mengikuti aturan semua karyawan.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menghapus aturan unit: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Aturan unit tidak ditemukan.'));
        exit;
    }
}

// ================= PROSES TAMBAH VERSI APLIKASI (INSERT) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah_versi') {
    $version_code = (int) ($_POST['version_code'] ?? 0);
    $version_name = trim($_POST['version_name'] ?? '');
    $apk_url      = trim($_POST['apk_url'] ?? '');
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    $changelog    = trim($_POST['changelog'] ?? '');

    if ($koneksi && $version_code > 0 && $version_name !== '' && $apk_url !== '') {
        $query_insert = "INSERT INTO app_version (version_code, version_name, apk_url, is_mandatory, changelog, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($koneksi, $query_insert);
        mysqli_stmt_bind_param($stmt, 'issis', $version_code, $version_name, $apk_url, $is_mandatory, $changelog);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: setting.php?menu=setting&status=sukses&pesan=' . urlencode('Versi aplikasi baru berhasil ditambahkan.'));
            exit;
        } else {
            header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Gagal menambahkan versi: ' . mysqli_error($koneksi)));
            exit;
        }
    } else {
        header('Location: setting.php?menu=setting&status=gagal&pesan=' . urlencode('Data versi aplikasi tidak lengkap.'));
        exit;
    }
}

// ================= AMBIL DATA BATAS WAKTU HADIR (SCOPE: SEMUA) =================
$data_batas_waktu = null;

if ($koneksi) {
    $query_batas = "SELECT * FROM batas_waktu_hadir WHERE scope_type = 'semua' ORDER BY id ASC LIMIT 1";
    $result_batas = mysqli_query($koneksi, $query_batas);
    if ($result_batas && mysqli_num_rows($result_batas) > 0) {
        $data_batas_waktu = mysqli_fetch_assoc($result_batas);
    }
}

// ================= AMBIL DATA BATAS WAKTU HADIR PER UNIT (SCOPE: UNIT) =================
$data_batas_unit = [];

if ($koneksi) {
    // COLLATE ditambahkan karena tabel unit (utf8mb4_0900_ai_ci) dan batas_waktu_hadir
    // (utf8mb4_unicode_ci) memakai collation database yang berbeda
    $query_batas_unit = "SELECT b.*, u.nm_unit
                          FROM batas_waktu_hadir b
                          LEFT JOIN unit u ON b.scope_value = u.id_unit COLLATE utf8mb4_unicode_ci
                          WHERE b.scope_type = 'unit'
                          ORDER BY u.nm_unit ASC";
    $result_batas_unit = mysqli_query($koneksi, $query_batas_unit);
    if ($result_batas_unit) {
        while ($row = mysqli_fetch_assoc($result_batas_unit)) {
            $data_batas_unit[] = $row;
        }
    }
}

// ================= AMBIL DAFTAR UNIT YANG BELUM PUNYA ATURAN KHUSUS =================
$daftar_unit_tersedia = [];

if ($koneksi) {
    $query_unit_tersedia = "SELECT id_unit, nm_unit FROM unit
                             WHERE id_unit COLLATE utf8mb4_unicode_ci NOT IN (
                                 SELECT scope_value FROM batas_waktu_hadir
                                 WHERE scope_type = 'unit' AND scope_value IS NOT NULL
                             )
                             ORDER BY nm_unit ASC";
    $result_unit_tersedia = mysqli_query($koneksi, $query_unit_tersedia);
    if ($result_unit_tersedia) {
        while ($row = mysqli_fetch_assoc($result_unit_tersedia)) {
            $daftar_unit_tersedia[] = $row;
        }
    }
}

// ================= AMBIL DATA VERSI APLIKASI =================
$data_versi = [];
$versi_terbaru = null;

if ($koneksi) {
    $query_versi = "SELECT * FROM app_version ORDER BY version_code DESC";
    $result_versi = mysqli_query($koneksi, $query_versi);
    if ($result_versi) {
        while ($row = mysqli_fetch_assoc($result_versi)) {
            $data_versi[] = $row;
        }
    }
    if (count($data_versi) > 0) {
        $versi_terbaru = $data_versi[0];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setting - Berkah</title>

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
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(23, 161, 154, 0.25);
            min-height: 640px;
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
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand {
            height: 40px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-logo {
            width: 100px;
            height: 100px;
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

        .alert-info-custom {
            border-radius: 12px;
            font-size: 0.85rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
        }

        .setting-card {
            background: #fff;
            border: 1px solid #eef4f3;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            margin-bottom: 1.5rem;
        }

        .setting-card-title {
            font-weight: 700;
            font-size: 1rem;
            color: #2d3a3a;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.3rem;
        }

        .setting-card-title i {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: #e4f7e9;
            color: var(--teal-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .setting-card-sub {
            color: #8a9797;
            font-size: 0.8rem;
            margin-bottom: 1.25rem;
        }

        .form-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #8a9797;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border-color: #e4ece4;
            background: #f8faf8;
            font-size: 0.88rem;
        }

        .btn-simpan {
            background: var(--teal-mid);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 0.55rem 1.4rem;
            color: #fff;
        }

        .btn-simpan:hover { background: var(--teal-dark); color: #fff; }

        .versi-terbaru-box {
            background: #e4f7e9;
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .versi-terbaru-box .label-kecil {
            font-size: 0.7rem;
            font-weight: 700;
            color: #1a5d0e;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .versi-terbaru-box .versi-angka {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1a5d0e;
        }

        .badge-wajib {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
        }

        .table-versi {
            font-size: 0.83rem;
        }

        .table-versi th {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #8a9797;
            font-weight: 700;
            border-bottom-width: 1px;
        }

        .table-versi td { vertical-align: middle; }

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
            <div class="mb-3">
                <h5 class="page-title">Pengaturan Sistem</h5>
                <p class="page-sub">Kelola batas waktu kehadiran & versi aplikasi Berkah Presensi</p>
            </div>

            <?php if ($pesan): ?>
                <div class="alert-info-custom <?php echo $status === 'sukses' ? 'alert alert-success' : 'alert alert-danger'; ?>">
                    <i class="bi <?php echo $status === 'sukses' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-1"></i>
                    <?php echo $pesan; ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <!-- ================= CARD: BATAS WAKTU HADIR - SEMUA KARYAWAN ================= -->
                <div class="col-12 col-lg-6">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-clock-fill"></i>
                            Batas Waktu Hadir - Semua Karyawan
                        </div>
                        <div class="setting-card-sub">
                            Jam batas keterlambatan default untuk seluruh karyawan. Unit yang punya aturan khusus di bawah akan memakai jamnya sendiri, bukan jam ini.
                        </div>

                        <?php if ($data_batas_waktu): ?>
                            <form method="POST" action="setting.php">
                                <input type="hidden" name="aksi" value="simpan_batas_waktu">
                                <input type="hidden" name="id_batas" value="<?php echo (int) $data_batas_waktu['id']; ?>">

                                <div class="mb-3">
                                    <label class="form-label">Jam Batas Hadir</label>
                                    <input type="time" name="jam_batas" class="form-control" step="1"
                                           value="<?php echo htmlspecialchars($data_batas_waktu['jam_batas']); ?>" required>
                                </div>

                                <div class="mb-3 text-muted" style="font-size:0.78rem;">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Terakhir diperbarui: <?php echo htmlspecialchars($data_batas_waktu['updated_at']); ?>
                                </div>

                                <button type="submit" class="btn btn-simpan">
                                    <i class="bi bi-save2 me-1"></i> Simpan Perubahan
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-clock-history" style="font-size:2rem;"></i>
                                <p class="mt-2 mb-0">Data batas waktu (scope: semua) belum tersedia di database.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ================= CARD: BATAS WAKTU HADIR - PER UNIT ================= -->
                <div class="col-12 col-lg-6">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-diagram-3-fill"></i>
                            Batas Waktu Hadir - Per Unit
                        </div>
                        <div class="setting-card-sub">
                            Buat aturan jam batas khusus untuk unit tertentu, di luar jam default di atas.
                        </div>

                        <?php if (count($data_batas_unit) > 0): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-versi align-middle">
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Jam Batas</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data_batas_unit as $bu): ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <?php echo htmlspecialchars($bu['nm_unit'] ?? $bu['scope_value']); ?>
                                                </td>
                                                <td style="min-width:130px;">
                                                    <form method="POST" action="setting.php" class="d-flex gap-1">
                                                        <input type="hidden" name="aksi" value="update_batas_unit">
                                                        <input type="hidden" name="id_batas" value="<?php echo (int) $bu['id']; ?>">
                                                        <input type="time" name="jam_batas" class="form-control form-control-sm" step="1"
                                                               value="<?php echo htmlspecialchars($bu['jam_batas']); ?>" required>
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Simpan">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td style="width:36px;">
                                                    <form method="POST" action="setting.php" onsubmit="return confirm('Hapus aturan khusus unit ini? Unit akan kembali memakai jam default.');">
                                                        <input type="hidden" name="aksi" value="hapus_batas_unit">
                                                        <input type="hidden" name="id_batas" value="<?php echo (int) $bu['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-diagram-3" style="font-size:1.8rem;"></i>
                                <p class="mt-2 mb-0" style="font-size:0.85rem;">Belum ada aturan khusus per unit. Semua unit memakai jam default.</p>
                            </div>
                        <?php endif; ?>

                        <?php if (count($daftar_unit_tersedia) > 0): ?>
                            <hr>
                            <form method="POST" action="setting.php">
                                <input type="hidden" name="aksi" value="tambah_batas_unit">

                                <div class="row g-2 align-items-end">
                                    <div class="col-6">
                                        <label class="form-label">Pilih Unit</label>
                                        <select name="id_unit" class="form-select" required>
                                            <option value="" disabled selected>-- Pilih Unit --</option>
                                            <?php foreach ($daftar_unit_tersedia as $u): ?>
                                                <option value="<?php echo htmlspecialchars($u['id_unit']); ?>">
                                                    <?php echo htmlspecialchars($u['nm_unit']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label">Jam Batas</label>
                                        <input type="time" name="jam_batas" class="form-control" step="1" required>
                                    </div>
                                    <div class="col-2 d-grid">
                                        <button type="submit" class="btn btn-simpan" title="Tambah Aturan">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <hr>
                            <p class="text-muted mb-0" style="font-size:0.78rem;">
                                <i class="bi bi-check-circle me-1"></i>
                                Semua unit sudah memiliki aturan batas waktu masing-masing.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="row g-3">
                <!-- ================= CARD: APP VERSION ================= -->
                <div class="col-12">
                    <div class="setting-card h-100">
                        <div class="setting-card-title">
                            <i class="bi bi-phone-fill"></i>
                            Versi Aplikasi
                        </div>
                        <div class="setting-card-sub">
                            Tambahkan rilis versi baru aplikasi mobile Berkah Presensi
                        </div>

                        <?php if ($versi_terbaru): ?>
                            <div class="versi-terbaru-box">
                                <div>
                                    <div class="label-kecil">Versi Aktif Saat Ini</div>
                                    <div class="versi-angka">
                                        v<?php echo htmlspecialchars($versi_terbaru['version_name']); ?>
                                        <span class="text-muted" style="font-weight:600; font-size:0.8rem;">
                                            (code <?php echo (int) $versi_terbaru['version_code']; ?>)
                                        </span>
                                    </div>
                                </div>
                                <?php if ((int) $versi_terbaru['is_mandatory'] === 1): ?>
                                    <span class="badge bg-danger badge-wajib">Update Wajib</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-wajib">Opsional</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="setting.php">
                            <input type="hidden" name="aksi" value="tambah_versi">

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Version Code</label>
                                    <input type="number" name="version_code" class="form-control"
                                           min="1" placeholder="cth: 3" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Version Name</label>
                                    <input type="text" name="version_name" class="form-control"
                                           placeholder="cth: 1.0.2" required>
                                </div>
                            </div>

                            <div class="mb-3 mt-2">
                                <label class="form-label">URL File APK</label>
                                <input type="url" name="apk_url" class="form-control"
                                       placeholder="https://..." required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Changelog</label>
                                <textarea name="changelog" class="form-control" rows="2" placeholder="Catatan perubahan pada versi ini..."></textarea>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_mandatory" id="is_mandatory" value="1">
                                <label class="form-check-label" for="is_mandatory" style="font-size:0.85rem;">
                                    Jadikan update wajib (mandatory)
                                </label>
                            </div>

                            <button type="submit" class="btn btn-simpan">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Versi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ================= RIWAYAT VERSI APLIKASI ================= -->
            <div class="setting-card">
                <div class="setting-card-title">
                    <i class="bi bi-clock-history"></i>
                    Riwayat Versi Aplikasi
                </div>
                <div class="setting-card-sub">Daftar seluruh rilis versi aplikasi, terbaru di atas</div>

                <?php if (count($data_versi) === 0): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-box-seam" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">Belum ada data versi aplikasi.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-versi align-middle">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Versi</th>
                                    <th>Status</th>
                                    <th>Changelog</th>
                                    <th>Dirilis</th>
                                    <th>APK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_versi as $v): ?>
                                    <tr>
                                        <td><?php echo (int) $v['version_code']; ?></td>
                                        <td class="fw-semibold">v<?php echo htmlspecialchars($v['version_name']); ?></td>
                                        <td>
                                            <?php if ((int) $v['is_mandatory'] === 1): ?>
                                                <span class="badge bg-danger badge-wajib">Wajib</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary badge-wajib">Opsional</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($v['changelog'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($v['created_at']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($v['apk_url']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
