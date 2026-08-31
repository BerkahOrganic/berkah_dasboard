<?php
session_start();

$menu_aktif = $_GET['menu'] ?? 'absensi';
require_once 'koneksi.php';

$jam_masuk_standar  = '08:00:00';
$jam_pulang_standar = '16:00:00';

// ================= FILTER & PAGINATION =================
$status_filter  = $_GET['status']  ?? '';
$cari           = trim($_GET['cari'] ?? '');
$tahun_filter   = $_GET['tahun']   ?? date('Y');
$halaman        = max(1, (int) ($_GET['halaman'] ?? 1));

// PERUBAHAN DI SINI: Dibuat 5 data per halaman
$per_halaman    = 5; 

// ================= AMBIL DATA JABATAN & UNIT =================
$daftar_jabatan = [];
if ($koneksi) {
    $q_jabatan = mysqli_query($koneksi, "SELECT id_jabatan, nm_jabatan FROM jabatan ORDER BY nm_jabatan ASC");
    if ($q_jabatan) {
        while ($r = mysqli_fetch_assoc($q_jabatan)) {
            $daftar_jabatan[] = $r;
        }
    }
}
$jabatan_filter = $_GET['jabatan'] ?? '';

$daftar_unit = [];
if ($koneksi) {
    $q_unit = mysqli_query($koneksi, "SELECT id_unit, nm_unit FROM unit ORDER BY nm_unit ASC");
    if ($q_unit) {
        while ($r = mysqli_fetch_assoc($q_unit)) {
            $daftar_unit[] = $r;
        }
    }
}
$unit_filter = $_GET['unit'] ?? '';

// ================= FUNGSI BADGE STATUS =================
function tentukan_status($absensi, $masuk, $keluar, $jam_masuk_standar, $jam_pulang_standar)
{
    switch ($absensi) {
        case 'H':
            if (!empty($masuk) && $masuk > $jam_masuk_standar) {
                return ['key' => 'terlambat',    'label' => 'Terlambat Masuk',          'class' => 'telat'];
            } elseif (!empty($keluar) && $keluar < $jam_pulang_standar) {
                return ['key' => 'pulang_cepat', 'label' => 'Pulang Sebelum Waktunya',  'class' => 'pulang'];
            } else {
                return ['key' => 'tepat_waktu',  'label' => 'tepat waktu',              'class' => 'tepat'];
            }
        case 'I':
            return ['key' => 'izin',  'label' => 'Izin',  'class' => 'izin'];
        case 'S':
            return ['key' => 'sakit', 'label' => 'Sakit', 'class' => 'sakit'];
        case 'C':
            return ['key' => 'cuti',  'label' => 'Cuti',  'class' => 'izin'];
        case 'OFF':
            return ['key' => 'off',   'label' => 'Libur', 'class' => 'off'];
        case 'TK':
        default:
            return ['key' => 'tidak_absen', 'label' => 'tidak absen', 'class' => 'tidak'];
    }
}

// ================= AMBIL DATA ABSENSI DARI DB =================
$data_absen = [];

if ($koneksi) {
    $where = [];

    if (!empty($tahun_filter)) {
        $tahun_esc = mysqli_real_escape_string($koneksi, $tahun_filter);
        $where[] = "YEAR(ab.tanggal) = '$tahun_esc'";
    }
    if (!empty($cari)) {
        $cari_esc = mysqli_real_escape_string($koneksi, $cari);
        $where[] = "(k.nama LIKE '%$cari_esc%' OR k.nik LIKE '%$cari_esc%')";
    }
    if (!empty($jabatan_filter)) {
        $jabatan_esc = mysqli_real_escape_string($koneksi, $jabatan_filter);
        $where[] = "ab.id_jabatan = '$jabatan_esc'";
    }
    if (!empty($unit_filter)) {
        $unit_esc = mysqli_real_escape_string($koneksi, $unit_filter);
        $where[] = "ab.id_unit = '$unit_esc'";
    }

    $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query_absen = "SELECT ab.id_absensi AS id, ab.tanggal, ab.masuk, ab.keluar, ab.absensi, ab.ket,
                           k.nik, k.nama,
                           j.nm_jabatan AS jabatan,
                           u.nm_unit AS unit
                    FROM absensi ab
                    JOIN karyawan k ON k.nik = ab.nik
                    LEFT JOIN jabatan j ON j.id_jabatan = ab.id_jabatan
                    LEFT JOIN unit u ON u.id_unit = ab.id_unit
                    $where_sql
                    ORDER BY ab.tanggal DESC, k.nama ASC";

    $result_absen = mysqli_query($koneksi, $query_absen);

    if ($result_absen) {
        while ($row = mysqli_fetch_assoc($result_absen)) {
            $status = tentukan_status($row['absensi'], $row['masuk'], $row['keluar'], $jam_masuk_standar, $jam_pulang_standar);
            $row['status'] = $status;

            if (!empty($status_filter) && $status['key'] !== $status_filter) {
                continue;
            }

            $data_absen[] = $row;
        }
    }
}

// ================= PAGINATION LOGIC =================
$total_data    = count($data_absen);
$total_halaman = max(1, (int) ceil($total_data / $per_halaman));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_halaman;
$data_tampil   = array_slice($data_absen, $offset, $per_halaman);

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
    <title>Absensi - Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
      :root {
            --teal-dark: #1a5d0e;
            --teal-mid: #2e861e;
            --teal-light: #4ed137;
            --bg-page: #a7eb9b;
            --text-muted: #c9f3c2;
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
            background: #f4f7f6;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
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
            user-select: none;
            pointer-events: none; 
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
        }

        
        .menu-nav .menu-item a i {
            font-size: 1.05rem;
        }

        .menu-nav .menu-item a:hover {
            background: rgba(255, 255, 255, 0.15);
        }


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

        .main-content {
            flex: 1;
            padding: 1.75rem 2rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: 640px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        .custom-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
            border: 1px solid #eef4f3;
        }

        .card-title-custom {
            font-size: 1rem;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 1rem;
        }

        .table-ranking {
            width: 100%;
            font-size: 0.85rem;
        }

        .table-ranking th {
            color: #2d3748;
            font-weight: 700;
            padding: 0.6rem 0.4rem;
            border-bottom: 2px solid #edf2f7;
        }

        .table-ranking td {
            padding: 0.6rem 0.4rem;
            border-bottom: 1px solid #f7fafc;
            color: #4a5568;
        }

        table.tabel-absen {
            width: 100%;
            font-size: 0.85rem;
            border-collapse: separate;
            border-spacing: 0;
        }

        table.tabel-absen thead th {
            color: #2d3748;
            font-weight: 700;
            padding: 0.75rem 0.5rem;
            border-bottom: 2px solid #edf2f7;
        }

        table.tabel-absen tbody td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #f0f4f0;
            color: #4a5568;
            vertical-align: middle;
        }

        .badge-status {
            display: inline-block;
            padding: 0.25rem 0.85rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-status.tepat { background: #28a745; color: #fff; }
        .badge-status.tidak { background: #dc3545; color: #fff; }
        .badge-status.telat { background: #ffc107; color: #212529; }
        .badge-status.pulang { background: #fd7e14; color: #fff; }
        .badge-status.izin { background: #0d6efd; color: #fff; }
        .badge-status.sakit { background: #6f42c1; color: #fff; }
        .badge-status.off { background: #6c757d; color: #fff; }

        .btn-aksi {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #e4ece4;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #8a9797;
            text-decoration: none;
        }

        .btn-xlsx {
            border: 1px solid #cbd5e0;
            background: #fff;
            color: #2d3748;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            text-decoration: none;
        }

        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.2rem;
            font-size: 0.8rem;
            color: #718096;
        }

        @media (max-width: 992px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .app-shell { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="app-shell">

        <!-- ================= SIDEBAR ================= -->
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
            </ul>

            <div class="sidebar-footer">
                <form action="logout.php" method="POST">
                    <button type="submit" class="btn-logout">
                        <i class="bi bi-box-arrow-right"></i> Go Out
                    </button>
                </form>
            </div>
        </aside>

        <!-- ================= MAIN CONTENT ================= -->
        <main class="main-content">
            <div class="dashboard-grid">

                <!-- ================= CARD KIRI: PRESENSI TEPAT WAKTU ================= -->
                <div class="custom-card">
                    <h6 class="card-title-custom">Presensi Tepat Waktu</h6>

                    <table class="table-ranking">
                        <thead>
                            <tr>
                                <th style="width: 15%;">No</th>
                                <th>Nama</th>
                                <th style="text-align: right;">Tepat Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($koneksi) {
                                $q_top = mysqli_query($koneksi, "
                                    SELECT k.nama, COUNT(ab.id_absensi) as total_tepat
                                    FROM absensi ab
                                    JOIN karyawan k ON k.nik = ab.nik
                                    WHERE ab.absensi = 'H' 
                                      AND (ab.masuk <= '$jam_masuk_standar' OR ab.masuk IS NULL)
                                      AND YEAR(ab.tanggal) = '$tahun_filter'
                                    GROUP BY ab.nik
                                    ORDER BY total_tepat DESC
                                    LIMIT 5
                                ");
                                $no_top = 1;
                                if ($q_top && mysqli_num_rows($q_top) > 0) {
                                    while ($top = mysqli_fetch_assoc($q_top)) {
                                        echo "<tr>
                                                <td>{$no_top}</td>
                                                <td>" . htmlspecialchars($top['nama']) . "</td>
                                                <td style='text-align: right;'>{$top['total_tepat']}</td>
                                              </tr>";
                                        $no_top++;
                                    }
                                } else {
                                    echo "<tr><td colspan='3' class='text-center text-muted py-3'>Belum ada data</td></tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>

                    <p class="text-muted mt-4 mb-0" style="font-size: 0.72rem; line-height: 1.4;">
                        *) Tepat waktu adalah jumlah presensi masuk dan pulang tepat waktu tahun <?php echo htmlspecialchars($tahun_filter); ?>
                    </p>
                </div>

                <!-- ================= CARD KANAN: PRESENSI TERBARU ================= -->
                <div class="custom-card">
                    
                    <!-- Header & Select Tahun -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title-custom mb-0">Presensi Terbaru</h6>
                        
                        <form method="GET" action="absensi.php" id="formTahun">
                            <input type="hidden" name="menu" value="absensi">
                            <select name="tahun" class="form-select form-select-sm" onchange="document.getElementById('formTahun').submit()" style="width: auto; border-radius: 8px;">
                                <option value="2026" <?php echo $tahun_filter === '2026' ? 'selected' : ''; ?>>2026</option>
                                <option value="2025" <?php echo $tahun_filter === '2025' ? 'selected' : ''; ?>>2025</option>
                                <option value="2024" <?php echo $tahun_filter === '2024' ? 'selected' : ''; ?>>2024</option>
                            </select>
                        </form>
                    </div>

                    <!-- Filter Bar & Search -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <a href="export_excel.php" class="btn-xlsx">
                            <i class="bi bi-file-earmark-excel"></i> XLSX
                        </a>

                        <form method="GET" action="absensi.php" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="menu" value="absensi">
                            <input type="hidden" name="tahun" value="<?php echo htmlspecialchars($tahun_filter); ?>">
                            <label class="form-label mb-0" style="font-size:0.8rem; color:#718096;">Search:</label>
                            <input type="text" name="cari" class="form-control form-control-sm" value="<?php echo htmlspecialchars($cari); ?>" style="border-radius: 6px; width: 160px;">
                        </form>
                    </div>

                    <!-- Tabel Absensi -->
                    <div class="table-responsive">
                        <table class="tabel-absen">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th>Nama Pegawai</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th style="text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($data_tampil) === 0): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            Tidak ada data absensi untuk filter ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no_row = $offset + 1;
                                    foreach ($data_tampil as $row): 
                                        $tgl_tampil = date('Y-m-d', strtotime($row['tanggal']));
                                        $jam_waktu  = !empty($row['masuk']) ? substr($row['masuk'], 0, 8) : (!empty($row['keluar']) ? substr($row['keluar'], 0, 8) : '—');
                                        $jenis_absen = !empty($row['keluar']) && empty($row['masuk']) ? 'pulang' : 'masuk';
                                    ?>
                                        <tr>
                                            <td><?php echo $no_row++; ?></td>
                                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                            <td><?php echo $tgl_tampil; ?></td>
                                            <td><?php echo $jam_waktu; ?></td>
                                            <td><?php echo $jenis_absen; ?></td>
                                            <td>
                                                <span class="badge-status <?php echo $row['status']['class']; ?>">
                                                    <?php echo $row['status']['label']; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <div class="d-inline-flex gap-1">
                                                    <a href="absensi_edit.php?id=<?php echo (int) $row['id']; ?>" class="btn-aksi edit" title="Edit">
                                                        <i class="bi bi-pencil-fill" style="font-size:0.7rem;"></i>
                                                    </a>
                                                    <a href="absensi_hapus.php?id=<?php echo (int) $row['id']; ?>" class="btn-aksi hapus" title="Hapus" onclick="return confirm('Hapus data absensi ini?');">
                                                        <i class="bi bi-trash-fill" style="font-size:0.7rem;"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer / Pagination (Menampilkan "Showing 1 to 5 of 11 entries" dst) -->
                    <div class="table-footer">
                        <div>
                            Showing <?php echo $total_data > 0 ? ($offset + 1) : 0; ?> to <?php echo min($offset + $per_halaman, $total_data); ?> of <?php echo $total_data; ?> entries
                        </div>

                        <?php if ($total_halaman > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $halaman <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_query(['halaman' => $halaman - 1]); ?>">Previous</a>
                                </li>
                                <?php for ($p = 1; $p <= $total_halaman; $p++): ?>
                                    <li class="page-item <?php echo $p === $halaman ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo build_query(['halaman' => $p]); ?>"><?php echo $p; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $halaman >= $total_halaman ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_query(['halaman' => $halaman + 1]); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>