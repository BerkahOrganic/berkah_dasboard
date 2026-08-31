<?php
session_start();

$menu_aktif = $_GET['menu'] ?? 'user';
require_once 'koneksi.php';

$role_filter = $_GET['role'] ?? '';
$cari        = trim($_GET['cari'] ?? '');
$halaman     = max(1, (int) ($_GET['halaman'] ?? 1));
$per_halaman = 8; // Jumlah card per halaman

$data_user = [];

if ($koneksi) {
    $where = [];

    if (!empty($cari)) {
        $cari_esc = mysqli_real_escape_string($koneksi, $cari);
        $where[] = "(username LIKE '%$cari_esc%' OR nama_user LIKE '%$cari_esc%')";
    }
    if (!empty($role_filter)) {
        $role_esc = mysqli_real_escape_string($koneksi, $role_filter);
        $where[] = "level = '$role_esc'";
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Menyesuaikan query ke tabel 'login'
    $query_user = "SELECT * FROM login $where_sql ORDER BY username ASC";
    $result_user = mysqli_query($koneksi, $query_user);

    if ($result_user) {
        while ($row = mysqli_fetch_assoc($result_user)) {
            $data_user[] = $row;
        }
    }
}

// ================= PAGINATION =================
$total_data    = count($data_user);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_user, $offset, $per_halaman);

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
                    <p class="page-sub">Kelola akun akses sistem aplikasi Chicken Berkah</p>
                </div>
                <a href="user_tambah.php" class="btn btn-sm btn-success" style="background:var(--teal-mid); border:none; border-radius:10px; padding: 0.5rem 1rem;">
                    <i class="bi bi-plus-lg"></i> Tambah User
                </a>
            </div>

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
                        <option value="Admin" <?php echo $role_filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="User" <?php echo $role_filter === 'User' ? 'selected' : ''; ?>>User</option>
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
                    <?php foreach ($data_tampil as $row): ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card-user">
                                <div class="avatar-user">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div class="user-nama">
                                    <?php echo htmlspecialchars($row['nama_user'] ?? $row['username']); ?>
                                </div>
                                <div class="user-username">
                                    @<?php echo htmlspecialchars($row['username']); ?>
                                </div>
                                
                                <span class="badge-role">
                                    <i class="bi bi-shield-check me-1"></i>
                                    <?php echo htmlspecialchars($row['level'] ?? 'User'); ?>
                                </span>

                                <div class="card-actions">
                                    <a href="user_edit.php?id=<?php echo urlencode($row['id_user'] ?? $row['username']); ?>" class="btn btn-outline-success">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="user_hapus.php?id=<?php echo urlencode($row['id_user'] ?? $row['username']); ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus user ini?');">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>