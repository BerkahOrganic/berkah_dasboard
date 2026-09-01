<?php
session_start();

$menu_aktif = $_GET['menu'] ?? 'jabatan';
require_once 'koneksi.php';

/* =====================================================
   KONFIGURASI NAMA KOLOM TABEL 'jabatan'
   id_jabatan varchar(10) PK (diisi manual, mis. J001)
   nm_jabatan varchar(35)
===================================================== */
const KOLOM_ID   = 'id_jabatan';
const KOLOM_NAMA = 'nm_jabatan';

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
    unset($qs['action']);
    $url = 'jabatan.php' . (count($qs) ? '?' . http_build_query($qs) : '');
    header('Location: ' . $url);
    exit;
}

/* =====================================================
   PROSES AKSI: TAMBAH / EDIT / HAPUS (via POST)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $koneksi) {

    $aksi = $_POST['aksi'] ?? '';

    // ---------------- TAMBAH JABATAN ----------------
    if ($aksi === 'tambah') {
        $id_jabatan = trim($_POST['id_jabatan'] ?? '');
        $nm_jabatan = trim($_POST['nm_jabatan'] ?? '');

        if ($id_jabatan === '' || $nm_jabatan === '') {
            set_flash('danger', 'ID Jabatan dan Nama Jabatan wajib diisi.');
            redirect_kembali();
        }

        // Cek ID Jabatan sudah dipakai atau belum (karena ini primary key manual)
        $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID . " FROM jabatan WHERE " . KOLOM_ID . " = ?");
        mysqli_stmt_bind_param($cek, 's', $id_jabatan);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            set_flash('danger', 'ID Jabatan "' . $id_jabatan . '" sudah digunakan, silakan pakai ID lain.');
            mysqli_stmt_close($cek);
            redirect_kembali();
        }
        mysqli_stmt_close($cek);

        $stmt = mysqli_prepare($koneksi, "INSERT INTO jabatan (" . KOLOM_ID . ", " . KOLOM_NAMA . ") VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $id_jabatan, $nm_jabatan);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Jabatan baru berhasil ditambahkan.');
        } else {
            set_flash('danger', 'Gagal menambahkan jabatan: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }

    // ---------------- EDIT JABATAN ----------------
    if ($aksi === 'edit') {
        $id_lama    = $_POST['id_lama'] ?? '';
        $id_jabatan = trim($_POST['id_jabatan'] ?? '');
        $nm_jabatan = trim($_POST['nm_jabatan'] ?? '');

        if ($id_lama === '' || $id_jabatan === '' || $nm_jabatan === '') {
            set_flash('danger', 'Data tidak lengkap untuk mengubah jabatan.');
            redirect_kembali();
        }

        // Kalau ID Jabatan diganti, cek dulu ID baru belum dipakai jabatan lain
        if ($id_jabatan !== $id_lama) {
            $cek = mysqli_prepare($koneksi, "SELECT " . KOLOM_ID . " FROM jabatan WHERE " . KOLOM_ID . " = ?");
            mysqli_stmt_bind_param($cek, 's', $id_jabatan);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) > 0) {
                set_flash('danger', 'ID Jabatan "' . $id_jabatan . '" sudah dipakai jabatan lain.');
                mysqli_stmt_close($cek);
                redirect_kembali();
            }
            mysqli_stmt_close($cek);
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE jabatan SET " . KOLOM_ID . " = ?, " . KOLOM_NAMA . " = ? WHERE " . KOLOM_ID . " = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $id_jabatan, $nm_jabatan, $id_lama);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Data jabatan berhasil diperbarui.');
        } else {
            // Kemungkinan gagal karena id_jabatan lama masih dipakai di tabel karyawan (FK)
            set_flash('danger', 'Gagal memperbarui jabatan. Pastikan ID baru tidak bentrok, atau jabatan ini sedang dipakai karyawan. (' . mysqli_error($koneksi) . ')');
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }

    // ---------------- HAPUS JABATAN ----------------
    if ($aksi === 'hapus') {
        $id_jabatan = $_POST['id_jabatan'] ?? '';

        if ($id_jabatan === '') {
            set_flash('danger', 'ID Jabatan tidak valid.');
            redirect_kembali();
        }

        // Cek dulu apakah jabatan ini masih dipakai oleh karyawan
        $cek_pakai = mysqli_prepare($koneksi, "SELECT COUNT(*) AS jumlah FROM karyawan WHERE id_jabatan = ?");
        mysqli_stmt_bind_param($cek_pakai, 's', $id_jabatan);
        mysqli_stmt_execute($cek_pakai);
        $hasil_cek = mysqli_stmt_get_result($cek_pakai);
        $jumlah_pakai = $hasil_cek ? (int) mysqli_fetch_assoc($hasil_cek)['jumlah'] : 0;
        mysqli_stmt_close($cek_pakai);

        if ($jumlah_pakai > 0) {
            set_flash('danger', 'Jabatan ini tidak bisa dihapus karena masih digunakan oleh ' . $jumlah_pakai . ' karyawan.');
            redirect_kembali();
        }

        $stmt = mysqli_prepare($koneksi, "DELETE FROM jabatan WHERE " . KOLOM_ID . " = ?");
        mysqli_stmt_bind_param($stmt, 's', $id_jabatan);

        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Jabatan berhasil dihapus.');
        } else {
            set_flash('danger', 'Gagal menghapus jabatan: ' . mysqli_error($koneksi));
        }
        mysqli_stmt_close($stmt);
        redirect_kembali();
    }
}

$flash = ambil_flash();

// ================= FILTER & SEARCH =================
$cari        = trim($_GET['cari'] ?? '');
$halaman     = max(1, (int) ($_GET['halaman'] ?? 1));
$per_halaman = 8; // Jumlah card per halaman

// ================= AMBIL DATA JABATAN DARI DATABASE =================
$data_jabatan = [];

if ($koneksi) {
    $where  = [];
    $tipe   = '';
    $params = [];

    if (!empty($cari)) {
        $where[] = "(" . KOLOM_NAMA . " LIKE CONCAT('%', ?, '%') OR " . KOLOM_ID . " LIKE CONCAT('%', ?, '%'))";
        $tipe .= 'ss';
        $params[] = &$cari;
        $params[] = &$cari;
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query_jabatan = "SELECT * FROM jabatan $where_sql ORDER BY " . KOLOM_NAMA . " ASC";
    $stmt_jabatan  = mysqli_prepare($koneksi, $query_jabatan);

    if ($stmt_jabatan) {
        if ($tipe !== '') {
            array_unshift($params, $tipe);
            call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_jabatan], $params));
        }
        mysqli_stmt_execute($stmt_jabatan);
        $result_jabatan = mysqli_stmt_get_result($stmt_jabatan);

        if ($result_jabatan) {
            while ($row = mysqli_fetch_assoc($result_jabatan)) {
                $data_jabatan[] = $row;
            }
        }
        mysqli_stmt_close($stmt_jabatan);
    }
}

// ================= PAGINATION =================
$total_data    = count($data_jabatan);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_jabatan, $offset, $per_halaman);

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
    <title>Data Jabatan - Berkah</title>

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

        .filter-form .form-control {
            border-radius: 10px;
            border-color: #e4ece4;
            background: #f8faf8;
            font-size: 0.85rem;
        }

        /* Card Jabatan Styles */
        .card-jabatan {
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

        .card-jabatan:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(23, 161, 154, 0.15);
        }

        .icon-jabatan {
            width: 65px;
            height: 65px;
            border-radius: 20px;
            background: #e4f7e9;
            color: var(--teal-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
        }

        .jabatan-nama { font-weight: 700; font-size: 1rem; color: #2d3a3a; margin-bottom: 0.2rem; }

        .badge-id {
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            background: #f0f4f1;
            color: #5c7068;
            letter-spacing: 0.05em;
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
                    <h5 class="page-title">Manajemen Jabatan</h5>
                    <p class="page-sub">Kelola struktur posisi & jabatan pegawai Berkah Global Business</p>
                </div>
                <button type="button" class="btn btn-sm btn-success" style="background:var(--teal-mid); border:none; border-radius:10px; padding: 0.5rem 1rem;" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-lg"></i> Tambah Jabatan
                </button>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['tipe']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash['pesan']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- FILTER FORM -->
            <form class="row g-2 align-items-end filter-form mb-4" method="GET" action="jabatan.php">
                <input type="hidden" name="menu" value="jabatan">

                <div class="col-12 col-md-10">
                    <label class="form-label">Cari Jabatan</label>
                    <input type="text" name="cari" class="form-control" placeholder="Nama atau ID Jabatan..." value="<?php echo htmlspecialchars($cari); ?>">
                </div>

                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-success" style="background:var(--teal-mid); border:none; border-radius:10px;">
                        <i class="bi bi-search"></i> Cari
                    </button>
                </div>
            </form>

            <!-- CARD JABATAN GRID -->
            <?php if (count($data_tampil) === 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-briefcase" style="font-size:2.5rem;"></i>
                    <p class="mt-2">Tidak ada data jabatan yang ditemukan.</p>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($data_tampil as $row):
                        $id_jabatan = $row[KOLOM_ID] ?? '';
                        $nm_jabatan = $row[KOLOM_NAMA] ?? '';
                    ?>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <div class="card-jabatan">
                                <div class="icon-jabatan">
                                    <i class="bi bi-briefcase-fill"></i>
                                </div>

                                <div class="jabatan-nama">
                                    <?php echo htmlspecialchars($nm_jabatan); ?>
                                </div>

                                <span class="badge-id">
                                    ID: <?php echo htmlspecialchars($id_jabatan); ?>
                                </span>

                                <div class="card-actions">
                                    <button type="button"
                                        class="btn btn-outline-success btn-edit-jabatan"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEdit"
                                        data-id="<?php echo htmlspecialchars($id_jabatan); ?>"
                                        data-nama="<?php echo htmlspecialchars($nm_jabatan); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>

                                    <form method="POST" action="jabatan.php" onsubmit="return confirm('Hapus jabatan &quot;<?php echo htmlspecialchars($nm_jabatan); ?>&quot; ?');">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="id_jabatan" value="<?php echo htmlspecialchars($id_jabatan); ?>">
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
                        Menampilkan <?php echo $offset + 1; ?>–<?php echo min($offset + $per_halaman, $total_data); ?> dari <?php echo $total_data; ?> jabatan
                    <?php else: ?>
                        0 jabatan
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

    <!-- ===================== MODAL TAMBAH JABATAN ===================== -->
    <div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="jabatan.php">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-briefcase-fill me-2"></i>Tambah Jabatan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ID Jabatan</label>
                            <input type="text" name="id_jabatan" class="form-control" placeholder="Contoh: J007" maxlength="10" required>
                            <div class="form-text">Kode unik jabatan, maksimal 10 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Jabatan</label>
                            <input type="text" name="nm_jabatan" class="form-control" placeholder="Contoh: Kepala Toko" maxlength="35" required>
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

    <!-- ===================== MODAL EDIT JABATAN ===================== -->
    <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <form method="POST" action="jabatan.php" id="formEdit">
                    <input type="hidden" name="aksi" value="edit">
                    <input type="hidden" name="id_lama" id="edit_id_lama">
                    <div class="modal-header" style="background:var(--teal-mid); color:#fff;">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Jabatan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">ID Jabatan</label>
                            <input type="text" name="id_jabatan" id="edit_id_jabatan" class="form-control" maxlength="10" required>
                            <div class="form-text">Hati-hati mengubah ID jika sudah dipakai di data karyawan.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Jabatan</label>
                            <input type="text" name="nm_jabatan" id="edit_nm_jabatan" class="form-control" maxlength="35" required>
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
        // Isi otomatis modal Edit dengan data jabatan yang diklik
        document.querySelectorAll('.btn-edit-jabatan').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('edit_id_lama').value    = btn.dataset.id;
                document.getElementById('edit_id_jabatan').value = btn.dataset.id;
                document.getElementById('edit_nm_jabatan').value = btn.dataset.nama;
            });
        });
    </script>
</body>
</html>