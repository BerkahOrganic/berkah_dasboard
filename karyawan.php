<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_admin();

$menu_aktif = $_GET['menu'] ?? 'karyawan';

require_once 'koneksi.php';

$pesan = '';
$tipe_pesan = '';

if (isset($_SESSION['flash_pesan'])) {
    $pesan = $_SESSION['flash_pesan'];
    $tipe_pesan = $_SESSION['flash_tipe'] ?? 'success';

    unset($_SESSION['flash_pesan']);
    unset($_SESSION['flash_tipe']);
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && $koneksi) {

    $aksi = $_POST['aksi'] ?? '';


    if ($aksi === 'tambah') {

        $nik          = trim($_POST['nik'] ?? '');
        $nama         = trim($_POST['nama'] ?? '');
        $status_aktif = $_POST['status_aktif'] ?? 'Aktif';
        $id_unit      = $_POST['id_unit'] ?? '';
        $id_jabatan   = $_POST['id_jabatan'] ?? '';

        if ($nik === '' || $nama === '' || $id_unit === '' || $id_jabatan === '') {

            $_SESSION['flash_pesan'] = 'Semua data wajib diisi.';
            $_SESSION['flash_tipe'] = 'danger';

        } else {

            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO karyawan
                (nik, nama, status_aktif, id_unit, id_jabatan)
                VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $nik,
                $nama,
                $status_aktif,
                $id_unit,
                $id_jabatan
            );

            if (mysqli_stmt_execute($stmt)) {

                $_SESSION['flash_pesan'] = 'Karyawan berhasil ditambahkan.';
                $_SESSION['flash_tipe'] = 'success';

            } else {

                if (mysqli_errno($koneksi) == 1062) {
                    $_SESSION['flash_pesan'] = 'NIK tersebut sudah digunakan.';
                } else {
                    $_SESSION['flash_pesan'] =
                        'Gagal menambahkan karyawan: ' . mysqli_error($koneksi);
                }

                $_SESSION['flash_tipe'] = 'danger';
            }

            mysqli_stmt_close($stmt);
        }

        header("Location: karyawan.php?menu=karyawan");
        exit;
    }
    if ($aksi === 'edit') {

        $nik_lama    = trim($_POST['nik_lama'] ?? '');
        $nama        = trim($_POST['nama'] ?? '');
        $status_aktif = $_POST['status_aktif'] ?? 'Aktif';
        $id_unit     = $_POST['id_unit'] ?? '';
        $id_jabatan  = $_POST['id_jabatan'] ?? '';

        if (
            $nik_lama === '' ||
            $nama === '' ||
            $id_unit === '' ||
            $id_jabatan === ''
        ) {

            $_SESSION['flash_pesan'] = 'Data edit belum lengkap.';
            $_SESSION['flash_tipe'] = 'danger';

        } else {

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE karyawan
                 SET nama = ?,
                     status_aktif = ?,
                     id_unit = ?,
                     id_jabatan = ?
                 WHERE nik = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $nama,
                $status_aktif,
                $id_unit,
                $id_jabatan,
                $nik_lama
            );

            if (mysqli_stmt_execute($stmt)) {

                $_SESSION['flash_pesan'] = 'Data karyawan berhasil diperbarui.';
                $_SESSION['flash_tipe'] = 'success';

            } else {

                $_SESSION['flash_pesan'] =
                    'Gagal memperbarui data: ' . mysqli_error($koneksi);

                $_SESSION['flash_tipe'] = 'danger';
            }

            mysqli_stmt_close($stmt);
        }

        header("Location: karyawan.php?menu=karyawan");
        exit;
    }
    if ($aksi === 'hapus') {

        $nik = trim($_POST['nik'] ?? '');

        if ($nik === '') {

            $_SESSION['flash_pesan'] = 'NIK tidak ditemukan.';
            $_SESSION['flash_tipe'] = 'danger';

        } else {

            $stmt = mysqli_prepare(
                $koneksi,
                "DELETE FROM karyawan WHERE nik = ?"
            );

            mysqli_stmt_bind_param($stmt, "s", $nik);

            if (mysqli_stmt_execute($stmt)) {

                $_SESSION['flash_pesan'] = 'Karyawan berhasil dihapus.';
                $_SESSION['flash_tipe'] = 'success';

            } else {
                if (mysqli_errno($koneksi) == 1451) {

                    $_SESSION['flash_pesan'] =
                        'Karyawan tidak dapat dihapus karena masih memiliki data absensi. ' .
                        'Silakan ubah status menjadi "Tidak Aktif".';

                } else {

                    $_SESSION['flash_pesan'] =
                        'Gagal menghapus karyawan: ' . mysqli_error($koneksi);
                }

                $_SESSION['flash_tipe'] = 'danger';
            }

            mysqli_stmt_close($stmt);
        }

        header("Location: karyawan.php?menu=karyawan");
        exit;
    }
}
$status_filter  = $_GET['status'] ?? '';
$cari           = trim($_GET['cari'] ?? '');
$jabatan_filter = $_GET['jabatan'] ?? '';
$unit_filter    = $_GET['unit'] ?? '';

$halaman     = max(1, (int)($_GET['halaman'] ?? 1));
$per_halaman = 8;

$daftar_jabatan = [];

$q_jabatan = mysqli_query(
    $koneksi,
    "SELECT id_jabatan, nm_jabatan
     FROM jabatan
     ORDER BY nm_jabatan ASC"
);

if ($q_jabatan) {
    while ($r = mysqli_fetch_assoc($q_jabatan)) {
        $daftar_jabatan[] = $r;
    }
}

$daftar_unit = [];

$q_unit = mysqli_query(
    $koneksi,
    "SELECT id_unit, nm_unit
     FROM unit
     ORDER BY nm_unit ASC"
);

if ($q_unit) {
    while ($r = mysqli_fetch_assoc($q_unit)) {
        $daftar_unit[] = $r;
    }
}


$data_karyawan = [];

$where = [];
if ($cari !== '') {

    $cari_esc = mysqli_real_escape_string($koneksi, $cari);

    $where[] =
        "(k.nama LIKE '%$cari_esc%'
        OR k.nik LIKE '%$cari_esc%')";
}
if ($status_filter !== '') {

    $status_esc = mysqli_real_escape_string(
        $koneksi,
        $status_filter
    );

    $where[] = "k.status_aktif = '$status_esc'";
}
if ($jabatan_filter !== '') {

    $jabatan_esc = mysqli_real_escape_string(
        $koneksi,
        $jabatan_filter
    );

    $where[] = "k.id_jabatan = '$jabatan_esc'";
}
if ($unit_filter !== '') {

    $unit_esc = mysqli_real_escape_string(
        $koneksi,
        $unit_filter
    );

    $where[] = "k.id_unit = '$unit_esc'";
}


$where_sql = count($where)
    ? 'WHERE ' . implode(' AND ', $where)
    : '';


$query_karyawan = "
    SELECT
        k.nik,
        k.nama,
        k.status_aktif,
        k.id_unit,
        k.id_jabatan,
        j.nm_jabatan AS jabatan,
        u.nm_unit AS unit
    FROM karyawan k

    LEFT JOIN jabatan j
        ON j.id_jabatan = k.id_jabatan

    LEFT JOIN unit u
        ON u.id_unit = k.id_unit

    $where_sql

    ORDER BY k.nama ASC
";


$result_karyawan = mysqli_query(
    $koneksi,
    $query_karyawan
);


if ($result_karyawan) {

    while ($row = mysqli_fetch_assoc($result_karyawan)) {
        $data_karyawan[] = $row;
    }
}


$total_data = count($data_karyawan);

$total_halaman = max(
    1,
    (int)ceil($total_data / $per_halaman)
);

$halaman = min(
    $halaman,
    $total_halaman
);

$offset = ($halaman - 1) * $per_halaman;

$data_tampil = array_slice(
    $data_karyawan,
    $offset,
    $per_halaman
);

function build_query($override = [])
{
    $params = array_merge($_GET, $override);

    return htmlspecialchars(
        '?' . http_build_query($params)
    );
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Data Karyawan - Berkah</title>


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">


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

    background:
        linear-gradient(
            160deg,
            var(--teal-dark) 0%,
            var(--teal-mid) 60%,
            var(--teal-light) 100%
        );

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


.brand .accent {

    color: #ffd23f;
}


.menu-label {

    font-size: 0.72rem;

    font-weight: 700;

    letter-spacing: 0.12em;

    text-transform: uppercase;

    color: rgba(255,255,255,0.65);

    margin:
        0.5rem 0
        0.9rem
        0.75rem;
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

    color: rgba(255,255,255,0.9);

    text-decoration: none;

    font-weight: 600;

    font-size: 0.92rem;

    transition: 0.2s;
}


.menu-nav .menu-item a i {

    font-size: 1.05rem;
}


.menu-nav .menu-item a:hover {

    background:
        rgba(255,255,255,0.15);
}


.menu-nav .menu-item.active a {

    background: #fff;

    color: var(--teal-dark);

    box-shadow:
        0 6px 14px rgba(0,0,0,0.12);
}


.sidebar-footer {

    margin-top: auto;

    padding-top: 1.5rem;
}


.btn-logout {

    width: 100%;

    background:
        rgba(255,255,255,0.18);

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

.main-content {

    flex: 1;

    padding: 1.75rem 2rem;

    display: flex;

    flex-direction: column;

    overflow-y: auto;

    max-height: 640px;
}


.page-title {

    font-weight: 700;

    color: #2d3a3a;

    margin-bottom: 0.2rem;
}


.page-sub {

    color: #8a9797;

    font-size: 0.85rem;

    margin-bottom: 1.5rem;
}

.filter-form .form-label {

    font-size: 0.72rem;

    font-weight: 700;

    color: #8a9797;

    text-transform: uppercase;

    margin-bottom: 0.3rem;
}


.filter-form .form-control,
.filter-form .form-select {

    border-radius: 10px;

    border-color: #e4ece4;

    background: #f8faf8;

    font-size: 0.85rem;

    height: 38px;
}
.card-karyawan {

    background: #fff;

    border: 1px solid #eef4f3;

    border-radius: 16px;

    padding: 1.25rem;

    text-align: center;

    box-shadow:
        0 4px 12px rgba(0,0,0,0.03);

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;

    position: relative;

    height: 100%;

    display: flex;

    flex-direction: column;

    align-items: center;
}


.card-karyawan:hover {

    transform: translateY(-4px);

    box-shadow:
        0 8px 20px rgba(23,161,154,0.15);
}


.avatar-large {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: #e4f7e9;
    color: var(--teal-mid);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.4rem;
    margin-bottom: 0.6rem;
}


.karyawan-nama {

    font-weight: 700;

    font-size: 0.95rem;

    color: #2d3a3a;

    margin-bottom: 0.2rem;
}


.karyawan-nik {

    font-size: 0.75rem;

    color: #8a9797;

    margin-bottom: 0.6rem;
}


.badge-status-karyawan {

    padding: 0.25rem 0.6rem;

    border-radius: 20px;

    font-size: 0.7rem;

    font-weight: 700;

    margin-bottom: 0.8rem;
}


.badge-status-karyawan.aktif {

    background: #e3f8ea;

    color: #1a7a3f;
}


.badge-status-karyawan.nonaktif {

    background: #fde3e3;

    color: #b12727;
}


.info-list {

    width: 100%;

    font-size: 0.78rem;

    color: #64706f;

    border-top: 1px solid #f0f4f0;

    border-bottom: 1px solid #f0f4f0;

    padding: 0.5rem 0;

    margin-bottom: 0.8rem;

    text-align: left;
}


.info-list-item {

    display: flex;

    justify-content: space-between;

    gap: 8px;

    margin-bottom: 0.2rem;
}


.info-list-item strong {

    text-align: right;

    color: #394443;
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
    border-radius: 7px;
    font-weight: 600;
    padding: 0.45rem 0.5rem;
}



.modal-content {

    border: none;

    border-radius: 18px;

    overflow: hidden;
}


.modal-header {

    background:
        linear-gradient(
            135deg,
            var(--teal-dark),
            var(--teal-mid)
        );

    color: #fff;

    border: none;
}


.modal-header .btn-close {

    filter: brightness(0) invert(1);
}


.modal-body {

    padding: 1.5rem;
}


.form-control,
.form-select {

    border-radius: 10px;
}


.form-control:focus,
.form-select:focus {

    border-color: var(--teal-mid);

    box-shadow:
        0 0 0 0.2rem rgba(46,134,30,0.12);
}


.btn-save {

    background: var(--teal-mid);

    color: #fff;

    border: none;

    border-radius: 10px;

    padding: 0.55rem 1.2rem;

    font-weight: 600;
}


.btn-save:hover {

    background: var(--teal-dark);

    color: #fff;
}

@media (max-width: 768px) {

    body {
        padding: 0.5rem;
    }

    .app-shell {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
    }

    .main-content {
        max-height: none;
    }
}
</style>
</head>

<body>
<div class="app-shell">
<aside class="sidebar">

    <div class="brand">

        <img
            src="bg-login/logo-berkah.png"
            alt="Logo Berkah"
            class="brand-logo">

        <span>
            <span class="accent">B</span>erkah
        </span>

    </div>


    <div class="menu-label">
        Main Menu
    </div>


    <ul class="menu-nav">

        <li class="menu-item
            <?php echo $menu_aktif === 'dashboard' ? 'active' : ''; ?>">

            <a href="dashboard.php?menu=dashboard">

                <i class="bi bi-grid-fill"></i>
                Dashboard
            </a>
        </li>

        <li class="menu-item
            <?php echo $menu_aktif === 'absensi' ? 'active' : ''; ?>">
            <a href="absensi.php?menu=absensi">
                <i class="bi bi-person-check-fill"></i>
                Absensi
            </a>
        </li>


        <li class="menu-item
            <?php echo $menu_aktif === 'karyawan' ? 'active' : ''; ?>">
            <a href="karyawan.php?menu=karyawan">
                <i class="bi bi-people-fill"></i>
                Karyawan
            </a>
        </li>


        <li class="menu-item
            <?php echo $menu_aktif === 'user' ? 'active' : ''; ?>">
            <a href="user.php?menu=user">
                <i class="bi bi-person-badge-fill"></i>
                User
            </a>
        </li>


        <li class="menu-item
            <?php echo $menu_aktif === 'jabatan' ? 'active' : ''; ?>">
            <a href="jabatan.php?menu=jabatan">
                <i class="bi bi-briefcase-fill"></i>
                Jabatan
            </a>
        </li>


        <li class="menu-item
            <?php echo $menu_aktif === 'login_unit' ? 'active' : ''; ?>">
            <a href="login_unit.php?menu=login_unit">
                <i class="bi bi-building"></i>
                Login Unit
            </a>
        </li>

        <li class="menu-item <?php echo $menu_aktif === 'setting' ? 'active' : ''; ?>">
                    <a href="setting.php?menu=setting"><i class="bi bi-gear-fill"></i> Setting</a>
                </li>
    </ul>


    <div class="sidebar-footer">
        <form
            action="logout.php"
            method="POST">
            <button
                type="submit"
                class="btn-logout">
                <i class="bi bi-box-arrow-right"></i>
                Go Out
            </button>
        </form>
    </div>
</aside>




<main class="main-content">

<div class="d-flex justify-content-between align-items-center mb-2">

    <div>

        <h5 class="page-title">
            Direktori Karyawan
        </h5>

        <p class="page-sub">
            Daftar profil dan informasi anggota Berkah Global Business
        </p>

    </div>

    <button
        type="button"
        class="btn btn-success"
        data-bs-toggle="modal"
        data-bs-target="#modalTambah"
        style="
            background:var(--teal-mid);
            border:none;
            border-radius:10px;
            padding:0.5rem 1rem;
        ">
        <i class="bi bi-plus-lg"></i>
        Tambah Karyawan
    </button>
</div>

<?php if ($pesan !== ''): ?>

<div class="alert alert-<?php echo htmlspecialchars($tipe_pesan); ?>
            alert-dismissible fade show"
     role="alert">

    <i class="bi
        <?php echo $tipe_pesan === 'success'
            ? 'bi-check-circle-fill'
            : 'bi-exclamation-triangle-fill'; ?>">
    </i>

    <?php echo htmlspecialchars($pesan); ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert">
    </button>

</div>

<?php endif; ?>




<form
    class="row g-2 align-items-end filter-form mb-4"
    method="GET"
    action="karyawan.php">

    <input
        type="hidden"
        name="menu"
        value="karyawan">


    <div class="col-12 col-md-3">

        <label class="form-label">
            Cari Karyawan
        </label>

        <input
            type="text"
            name="cari"
            class="form-control"
            placeholder="Nama / NIK..."
            value="<?php echo htmlspecialchars($cari); ?>">

    </div>


    <div class="col-6 col-md-3">

        <label class="form-label">
            Jabatan
        </label>

        <select
            name="jabatan"
            class="form-select">

            <option value="">
                Semua Jabatan
            </option>

            <?php foreach ($daftar_jabatan as $jb): ?>

                <option
                    value="<?php echo htmlspecialchars($jb['id_jabatan']); ?>"
                    <?php
                    echo $jabatan_filter === $jb['id_jabatan']
                        ? 'selected'
                        : '';
                    ?>>

                    <?php
                    echo htmlspecialchars($jb['nm_jabatan']);
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="col-6 col-md-2">

        <label class="form-label">
            Unit
        </label>

        <select
            name="unit"
            class="form-select">

            <option value="">
                Semua Unit
            </option>

            <?php foreach ($daftar_unit as $un): ?>

                <option
                    value="<?php echo htmlspecialchars($un['id_unit']); ?>"
                    <?php
                    echo $unit_filter === $un['id_unit']
                        ? 'selected'
                        : '';
                    ?>>

                    <?php
                    echo htmlspecialchars($un['nm_unit']);
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="col-6 col-md-2">

        <label class="form-label">
            Status
        </label>

        <select
            name="status"
            class="form-select">

            <option value="">
                Semua Status
            </option>

            <option
                value="Aktif"
                <?php
                echo $status_filter === 'Aktif'
                    ? 'selected'
                    : '';
                ?>>

                Aktif

            </option>

            <option
                value="Tidak Aktif"
                <?php
                echo $status_filter === 'Tidak Aktif'
                    ? 'selected'
                    : '';
                ?>>

                Tidak Aktif

            </option>

        </select>

    </div>


    <div class="col-6 col-md-2 d-grid">

        <button
            type="submit"
            class="btn btn-success"
            style="
                background:var(--teal-mid);
                border:none;
                border-radius:10px;
            ">

            <i class="bi bi-search"></i>

            Filter

        </button>

    </div>

</form>




<?php if (count($data_tampil) === 0): ?>

<div class="text-center py-5 text-muted">

    <i
        class="bi bi-people"
        style="font-size:2.5rem;">
    </i>

    <p class="mt-2">
        Tidak ada data karyawan yang sesuai dengan filter.
    </p>

</div>

<?php else: ?>


<div class="row g-3 mb-4">


<?php foreach ($data_tampil as $row): ?>


<?php

$nama = trim($row['nama']);

$parts = preg_split(
    '/\s+/',
    $nama
);

if (count($parts) >= 2) {

    $inisial =
        strtoupper(
            substr($parts[0], 0, 1) .
            substr($parts[1], 0, 1)
        );

} else {

    $inisial =
        strtoupper(
            substr($nama, 0, 2)
        );
}


$is_aktif =
    strtolower(
        trim($row['status_aktif'] ?? '')
    ) === 'aktif';

?>


<div class="col-12 col-sm-6 col-md-4 col-lg-3">


<div class="card-karyawan">


<!-- AVATAR -->

<div class="avatar-large">

    <?php echo htmlspecialchars($inisial); ?>

</div>


<!-- NAMA -->

<div class="karyawan-nama">

    <?php
    echo htmlspecialchars($row['nama']);
    ?>

</div>


<!-- NIK -->

<div class="karyawan-nik">

    NIK:
    <?php
    echo htmlspecialchars($row['nik']);
    ?>

</div>


<!-- STATUS -->

<span class="badge-status-karyawan
    <?php echo $is_aktif ? 'aktif' : 'nonaktif'; ?>">

    ●

    <?php
    echo htmlspecialchars(
        $row['status_aktif']
    );
    ?>

</span>

<div class="info-list">


    <div class="info-list-item">

        <span>
            Jabatan:
        </span>

        <strong>

            <?php
            echo htmlspecialchars(
                $row['jabatan'] ?? '-'
            );
            ?>

        </strong>

    </div>


    <div class="info-list-item">

        <span>
            Unit:
        </span>

        <strong>

            <?php
            echo htmlspecialchars(
                $row['unit'] ?? '-'
            );
            ?>

        </strong>

    </div>


</div>

<div class="card-actions">

<button
    type="button"
    class="btn btn-outline-success"
    data-bs-toggle="modal"
    data-bs-target="#modalEdit<?php echo htmlspecialchars($row['nik']); ?>">

    <i class="bi bi-pencil"></i>

    Edit

</button>

<form
    method="POST"
    style="flex:1;"
    onsubmit="return confirm(
        'Yakin ingin menghapus karyawan ini?'
    );">

    <input
        type="hidden"
        name="aksi"
        value="hapus">

    <input
        type="hidden"
        name="nik"
        value="<?php
            echo htmlspecialchars($row['nik']);
        ?>">

    <button
        type="submit"
        class="btn btn-outline-danger w-100">
        <i class="bi bi-trash"></i>
        Hapus
    </button>
</form>
</div>
</div>
</div>

<div
    class="modal fade"
    id="modalEdit<?php echo htmlspecialchars($row['nik']); ?>"
    tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">

                    <i class="bi bi-pencil-square"></i>

                    Edit Karyawan

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <form method="POST">

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="aksi"
                        value="edit">

                    <input
                        type="hidden"
                        name="nik_lama"
                        value="<?php
                            echo htmlspecialchars($row['nik']);
                        ?>">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            NIK
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?php
                                echo htmlspecialchars($row['nik']);
                            ?>"
                            readonly>

                        <small class="text-muted">
                            NIK tidak dapat diubah.
                        </small>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Nama Karyawan
                        </label>

                        <input
                            type="text"
                            name="nama"
                            class="form-control"
                            required
                            value="<?php
                                echo htmlspecialchars($row['nama']);
                            ?>">

                    </div>



                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Status
                        </label>

                        <select
                            name="status_aktif"
                            class="form-select"
                            required>

                            <option
                                value="Aktif"
                                <?php
                                echo $row['status_aktif'] === 'Aktif'
                                    ? 'selected'
                                    : '';
                                ?>>

                                Aktif

                            </option>

                            <option
                                value="Tidak Aktif"
                                <?php
                                echo $row['status_aktif'] === 'Tidak Aktif'
                                    ? 'selected'
                                    : '';
                                ?>>

                                Tidak Aktif

                            </option>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Jabatan
                        </label>

                        <select
                            name="id_jabatan"
                            class="form-select"
                            required>

                            <?php foreach ($daftar_jabatan as $jb): ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $jb['id_jabatan']
                                        );
                                    ?>"
                                    <?php
                                    echo $row['id_jabatan'] === $jb['id_jabatan']
                                        ? 'selected'
                                        : '';
                                    ?>>

                                    <?php
                                    echo htmlspecialchars(
                                        $jb['nm_jabatan']
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Unit
                        </label>

                        <select
                            name="id_unit"
                            class="form-select"
                            required>

                            <?php foreach ($daftar_unit as $un): ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $un['id_unit']
                                        );
                                    ?>"
                                    <?php
                                    echo $row['id_unit'] === $un['id_unit']
                                        ? 'selected'
                                        : '';
                                    ?>>

                                    <?php
                                    echo htmlspecialchars(
                                        $un['nm_unit']
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Batal

                    </button>

                    <button
                        type="submit"
                        class="btn-save">

                        <i class="bi bi-save"></i>

                        Simpan Perubahan

                    </button>

                </div>

            </form>


        </div>

    </div>

</div>


<?php endforeach; ?>


</div>


<?php endif; ?>

<div
    class="d-flex justify-content-between
           align-items-center
           mt-auto
           pt-3
           border-top">


<div
    class="text-muted"
    style="font-size:0.8rem;">

    <?php if ($total_data > 0): ?>

        Menampilkan

        <?php echo $offset + 1; ?>

        –

        <?php
        echo min(
            $offset + $per_halaman,
            $total_data
        );
        ?>

        dari

        <?php echo $total_data; ?>

        karyawan

    <?php else: ?>

        0 karyawan

    <?php endif; ?>

</div>



<?php if ($total_halaman > 1): ?>


<nav>

<ul class="pagination mb-0">


<li
    class="page-item
    <?php echo $halaman <= 1 ? 'disabled' : ''; ?>">

    <a
        class="page-link"
        href="<?php
            echo build_query([
                'halaman' => $halaman - 1
            ]);
        ?>">

        ‹

    </a>

</li>


<?php for ($p = 1; $p <= $total_halaman; $p++): ?>

<li
    class="page-item
    <?php echo $p === $halaman ? 'active' : ''; ?>">

    <a
        class="page-link"
        href="<?php
            echo build_query([
                'halaman' => $p
            ]);
        ?>">

        <?php echo $p; ?>

    </a>

</li>

<?php endfor; ?>


<li
    class="page-item
    <?php echo $halaman >= $total_halaman ? 'disabled' : ''; ?>">

    <a
        class="page-link"
        href="<?php
            echo build_query([
                'halaman' => $halaman + 1
            ]);
        ?>">
        ›
    </a>
</li>
</ul>
</nav>
<?php endif; ?>
</div>
</main>
</div>

<div
    class="modal fade"
    id="modalTambah"
    tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">

                    <i class="bi bi-person-plus-fill"></i>

                    Tambah Karyawan

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <form method="POST">


                <input
                    type="hidden"
                    name="aksi"
                    value="tambah">


                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            NIK
                        </label>
                        <input
                            type="text"
                            name="nik"
                            class="form-control"
                            maxlength="20"
                            required
                            placeholder="Contoh: 1000000008">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Nama Karyawan
                        </label>
                        <input
                            type="text"
                            name="nama"
                            class="form-control"
                            maxlength="50"
                            required
                            placeholder="Masukkan nama lengkap">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Status
                        </label>
                        <select
                            name="status_aktif"
                            class="form-select"
                            required>
                            <option value="Aktif">
                                Aktif
                            </option>
                            <option value="Tidak Aktif">
                                Tidak Aktif
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Jabatan
                        </label>
                        <select
                            name="id_jabatan"
                            class="form-select"
                            required>
                            <option value="">
                                -- Pilih Jabatan --
                            </option>
                            <?php foreach ($daftar_jabatan as $jb): ?>
                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $jb['id_jabatan']
                                        );
                                    ?>">
                                    <?php
                                    echo htmlspecialchars(
                                        $jb['nm_jabatan']
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Unit
                        </label>
                        <select
                            name="id_unit"
                            class="form-select"
                            required>
                            <option value="">
                                -- Pilih Unit --
                            </option>

                            <?php foreach ($daftar_unit as $un): ?>
                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $un['id_unit']
                                        );
                                    ?>">

                                    <?php
                                    echo htmlspecialchars(
                                        $un['nm_unit']
                                    );
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="btn-save">
                        <i class="bi bi-person-plus"></i>
                        Simpan Karyawan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>