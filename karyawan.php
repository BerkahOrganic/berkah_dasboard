<?php
session_start();

$menu_aktif = $_GET['menu'] ?? 'karyawan';
require_once 'koneksi.php';

$status_filter = $_GET['status']  ?? '';
$cari          = trim($_GET['cari'] ?? '');
$jabatan_filter= $_GET['jabatan'] ?? '';
$unit_filter   = $_GET['unit']    ?? '';
$halaman       = max(1, (int) ($_GET['halaman'] ?? 1));
$per_halaman   = 8;

$daftar_jabatan = [];
if ($koneksi) {
    $q_jabatan = mysqli_query($koneksi, "SELECT id_jabatan, nm_jabatan FROM jabatan ORDER BY nm_jabatan ASC");
    if ($q_jabatan) {
        while ($r = mysqli_fetch_assoc($q_jabatan)) {
            $daftar_jabatan[] = $r;
        }
    }
}

$daftar_unit = [];
if ($koneksi) {
    $q_unit = mysqli_query($koneksi, "SELECT id_unit, nm_unit FROM unit ORDER BY nm_unit ASC");
    if ($q_unit) {
        while ($r = mysqli_fetch_assoc($q_unit)) {
            $daftar_unit[] = $r;
        }
    }
}

// ================= AMBIL DATA KARYAWAN =================
$data_karyawan = [];

if ($koneksi) {
    $where = [];

    if (!empty($cari)) {
        $cari_esc = mysqli_real_escape_string($koneksi, $cari);
        $where[] = "(k.nama LIKE '%$cari_esc%' OR k.nik LIKE '%$cari_esc%')";
    }
    if (!empty($status_filter)) {
        $status_esc = mysqli_real_escape_string($koneksi, $status_filter);
        $where[] = "k.status_aktif = '$status_esc'";
    }
    if (!empty($jabatan_filter)) {
        $jabatan_esc = mysqli_real_escape_string($koneksi, $jabatan_filter);
        $where[] = "k.id_jabatan = '$jabatan_esc'";
    }
    if (!empty($unit_filter)) {
        $unit_esc = mysqli_real_escape_string($koneksi, $unit_filter);
        $where[] = "k.id_unit = '$unit_esc'";
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query_karyawan = "SELECT k.nik, k.nama, k.status_aktif,
                             j.nm_jabatan AS jabatan,
                             u.nm_unit AS unit
                      FROM karyawan k
                      LEFT JOIN jabatan j ON j.id_jabatan = k.id_jabatan
                      LEFT JOIN unit u ON u.id_unit = k.id_unit
                      $where_sql
                      ORDER BY k.nama ASC";

    $result_karyawan = mysqli_query($koneksi, $query_karyawan);

    if ($result_karyawan) {
        while ($row = mysqli_fetch_assoc($result_karyawan)) {
            $data_karyawan[] = $row;
        }
    }
}

// ================= PAGINATION =================
$total_data    = count($data_karyawan);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_karyawan, $offset, $per_halaman);

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
    <title>Data Karyawan - Berkah</title>

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

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
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

        /* Card Karyawan Styles */
        .card-karyawan {
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

        .card-karyawan:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(23, 161, 154, 0.15);
        }

        .avatar-large {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #e4f7e9;
            color: var(--teal-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
        }

        .karyawan-nama { font-weight: 700; font-size: 0.95rem; color: #2d3a3a; margin-bottom: 0.2rem; }
        .karyawan-nik { font-size: 0.75rem; color: #8a9797; margin-bottom: 0.6rem; }
        
        .badge-status-karyawan {
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
        }
        .badge-status-karyawan.aktif { background: #e3f8ea; color: #1a7a3f; }
        .badge-status-karyawan.nonaktif { background: #fde3e3; color: #b12727; }

        .info-list {
            width: 100%;
            font-size: 0.78rem;
            color: #64706f;
            border-top: 1px border-bottom: 1px solid #f0f4f0;
            padding: 0.5rem 0;
            margin-bottom: 0.8rem;
            text-align: left;
        }

        .info-list-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.2rem;
        }

        .card-actions {
            margin-top: auto;
            display: flex;
            gap: 0.5rem;
            width: 100%;
        }

        .card-actions .btn {
            flex: 1;
            font-size: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .app-shell { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="app-shell">

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

        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="page-title">Direktori Karyawan</h5>
                    <p class="page-sub">Daftar profil dan informasi anggota tim Chicken Berkah</p>
                </div>
                <a href="karyawan_tambah.php" class="btn btn-sm btn-success" style="background:var(--teal-mid); border:none; border-radius:10px; padding: 0.5rem 1rem;">
                    <i class="bi bi-plus-lg"></i> Tambah Karyawan
                </a>
            </div>

            <!-- FILTER FORM -->
            <form class="row g-2 align-items-end filter-form mb-4" method="GET" action="karyawan.php">
                <input type="hidden" name="menu" value="karyawan">

                <div class="col-12 col-md-3">
                    <label class="form-label">Cari Karyawan</label>
                    <input type="text" name="cari" class="form-control" placeholder="Nama / NIK..." value="<?php echo htmlspecialchars($cari); ?>">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Jabatan</label>
                    <select name="jabatan" class="form-select">
                        <option value="">Semua Jabatan</option>
                        <?php foreach ($daftar_jabatan as $jb): ?>
                            <option value="<?php echo htmlspecialchars($jb['id_jabatan']); ?>" <?php echo $jabatan_filter === $jb['id_jabatan'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($jb['nm_jabatan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Unit</label>
                    <select name="unit" class="form-select">
                        <option value="">Semua Unit</option>
                        <?php foreach ($daftar_unit as $un): ?>
                            <option value="<?php echo htmlspecialchars($un['id_unit']); ?>" <?php echo $unit_filter === $un['id_unit'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($un['nm_unit']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?php echo $status_filter === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="Non-Aktif" <?php echo $status_filter === 'Non-Aktif' ? 'selected' : ''; ?>>Non-Aktif</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 d-grid">
                    <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none; border-radius:10px;">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </form>

            <!-- CARD GRID -->
            <?php if (count($data_tampil) === 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people" style="font-size:2.5rem;"></i>
                    <p class="mt-2">Tidak ada data karyawan yang sesuai dengan filter.</p>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($data_tampil as $row): ?>
                        <?php
                            $inisial = strtoupper(substr($row['nama'], 0, 1) . (strpos($row['nama'], ' ') ? substr(strrchr($row['nama'], ' '), 1, 1) : ''));
                            $is_aktif = strtolower($row['status_aktif'] ?? '') === 'aktif';
                        ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card-karyawan">
                                <div class="avatar-large"><?php echo htmlspecialchars($inisial); ?></div>
                                <div class="karyawan-nama"><?php echo htmlspecialchars($row['nama']); ?></div>
                                <div class="karyawan-nik">NIK: <?php echo htmlspecialchars($row['nik']); ?></div>
                                
                                <span class="badge-status-karyawan <?php echo $is_aktif ? 'aktif' : 'nonaktif'; ?>">
                                    ● <?php echo htmlspecialchars($row['status_aktif'] ?? 'Aktif'); ?>
                                </span>

                                <div class="info-list">
                                    <div class="info-list-item">
                                        <span>Jabatan:</span>
                                        <strong><?php echo htmlspecialchars($row['jabatan'] ?? '-'); ?></strong>
                                    </div>
                                    <div class="info-list-item">
                                        <span>Unit:</span>
                                        <strong><?php echo htmlspecialchars($row['unit'] ?? '-'); ?></strong>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <a href="karyawan_edit.php?nik=<?php echo urlencode($row['nik']); ?>" class="btn btn-outline-success">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="karyawan_hapus.php?nik=<?php echo urlencode($row['nik']); ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus karyawan ini?');">
                                        <i class="bi bi-trash"></i> Hapus
                                    </a>
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
                        Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $per_halaman, $total_data); ?> dari <?php echo $total_data; ?> karyawan
                    <?php else: ?>
                        0 karyawan
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>