<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_login();

$menu_aktif = $_GET['menu'] ?? 'dashboard';
require_once 'koneksi.php';
$jumlah_hadir = 0;
$jumlah_izin  = 0;
$jumlah_sakit = 0;

if ($koneksi) {
    $query_stat = "SELECT absensi, COUNT(*) as jumlah 
                   FROM absensi 
                   WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                   GROUP BY absensi";
    $result_stat = mysqli_query($koneksi, $query_stat);

    if ($result_stat) {
        while ($row = mysqli_fetch_assoc($result_stat)) {
            if ($row['absensi'] === 'H') $jumlah_hadir = (int) $row['jumlah'];
            if ($row['absensi'] === 'I') $jumlah_izin  = (int) $row['jumlah'];
            if ($row['absensi'] === 'S') $jumlah_sakit = (int) $row['jumlah'];
        }
    }
}
$jam_masuk_standar  = '08:00:00';
$jam_pulang_standar = '16:00:00';
$tahun_dipilih      = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

$label_bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$kategori_list = ['tepat_waktu', 'terlambat', 'pulang_cepat', 'tidak_absen'];

$data_bulanan = [];
for ($b = 1; $b <= 12; $b++) {
    $data_bulanan[$b] = array_fill_keys($kategori_list, 0);
}
$data_tahunan = array_fill_keys($kategori_list, 0);

if ($koneksi) {
    $query_chart = "SELECT tanggal, masuk, keluar, absensi 
                     FROM absensi 
                     WHERE YEAR(tanggal) = " . (int) $tahun_dipilih;
    $result_chart = mysqli_query($koneksi, $query_chart);

    if ($result_chart) {
        while ($row = mysqli_fetch_assoc($result_chart)) {
            $bulan = (int) date('n', strtotime($row['tanggal']));

            if ($row['absensi'] === 'TK') {
                $kategori = 'tidak_absen';
            } elseif ($row['absensi'] === 'H') {
                if (!empty($row['masuk']) && $row['masuk'] > $jam_masuk_standar) {
                    $kategori = 'terlambat';
                } elseif (!empty($row['keluar']) && $row['keluar'] < $jam_pulang_standar) {
                    $kategori = 'pulang_cepat';
                } else {
                    $kategori = 'tepat_waktu';
                }
            } else {
                continue;
            }

            $data_bulanan[$bulan][$kategori]++;
            $data_tahunan[$kategori]++;
        }
    }
}

$chart_tepat_waktu   = [];
$chart_terlambat     = [];
$chart_pulang_cepat  = [];
$chart_tidak_absen   = [];
for ($b = 1; $b <= 12; $b++) {
    $chart_tepat_waktu[]  = $data_bulanan[$b]['tepat_waktu'];
    $chart_terlambat[]    = $data_bulanan[$b]['terlambat'];
    $chart_pulang_cepat[] = $data_bulanan[$b]['pulang_cepat'];
    $chart_tidak_absen[]  = $data_bulanan[$b]['tidak_absen'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Absen Berkah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

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
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            transition: background 0.2s ease, color 0.2s ease;
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

        .sidebar-footer {
            margin-top: auto;
            padding-top: 1.5rem;
        }

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

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .main-content {
            flex: 1;
            padding: 1.75rem 2rem;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .topbar .search-box {
            flex: 1;
            max-width: 320px;
            position: relative;
        }

        .topbar .search-box input {
            width: 100%;
            border: none;
            background: #f1f5f5;
            border-radius: 30px;
            padding: 0.55rem 1rem 0.55rem 2.4rem;
            font-size: 0.85rem;
            outline: none;
        }

        .topbar .search-box i {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9aa5a5;
        }

        .btn-notif {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--teal-mid);
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .btn-notif .dot {
            position: absolute;
            top: 8px;
            right: 9px;
            width: 8px;
            height: 8px;
            background: #ff5b5b;
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .page-title {
            font-weight: 700;
            color: #2d3a3a;
            margin-bottom: 1rem;
        }

        .placeholder-card {
            background: #f6fafa;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
            border: 1px dashed #cfe4e2;
        }
        .stat-cards {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
        }

        .stat-card {
            flex: 1;
            min-width: 160px;
            background: #fff;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 6px 18px rgba(23, 161, 154, 0.12);
            border: 1px solid #eef4f3;
        }

        .stat-card .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #fff;
            flex-shrink: 0;
        }

        .stat-card.hadir .stat-icon { background: var(--teal-mid); }
        .stat-card.izin .stat-icon { background: #ffb020; }
        .stat-card.sakit .stat-icon { backgr ound: #ff5b5b; }

        .stat-card .stat-info .stat-number {
            font-size: 1.6rem;
            font-weight: 800;
            color: #2d3a3a;
            line-height: 1.1;
        }

        .stat-card .stat-info .stat-label {
            font-size: 0.8rem;
            color: #8a9797;
            font-weight: 600;
        }

        .chart-row {
            display: flex;
            gap: 1.25rem;
            flex-wrap: wrap;
        }

        .chart-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 6px 18px rgba(23, 161, 154, 0.12);
            border: 1px solid #eef4f3;
        }

        .chart-card.bulanan {
            flex: 2;
            min-width: 320px;
        }

        .chart-card.tahunan {
            flex: 1;
            min-width: 260px;
        }

        .chart-card h6 {
            font-weight: 700;
            color: #2d3a3a;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .chart-card canvas {
            max-height: 280px;
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
                    <a href="dashboard.php?menu=dashboard">
                        <i class="bi bi-grid-fill"></i> Dashboard
                    </a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'absensi' ? 'active' : ''; ?>">
                    <a href="absensi.php?menu=absensi">
                        <i class="bi bi-person-check-fill"></i> Absensi
                    </a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'karyawan' ? 'active' : ''; ?>">
                    <a href="karyawan.php?menu=karyawan">
                        <i class="bi bi-people-fill"></i> Karyawan
                    </a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'user' ? 'active' : ''; ?>">
                    <a href="user.php?menu=user">
                        <i class="bi bi-person-badge-fill"></i> User
                    </a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'jabatan' ? 'active' : ''; ?>">
                    <a href="jabatan.php?menu=jabatan">
                        <i class="bi bi-briefcase-fill"></i> Jabatan
                    </a>
                </li>
                <li class="menu-item <?php echo $menu_aktif === 'login_unit' ? 'active' : ''; ?>">
                    <a href="login_unit.php?menu=login_unit">
                        <i class="bi bi-building"></i> Login Unit
                    </a>
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
            <div class="topbar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Search">
                </div>
                <button class="btn-notif">
                    <i class="bi bi-bell-fill"></i>
                    <span class="dot"></span>
                </button>
            </div>

            <h5 class="page-title">Selamat datang kembali 👋</h5>

            <?php if ($menu_aktif === 'dashboard'): ?>
                <div class="stat-cards">
                    <div class="stat-card hadir">
                        <div class="stat-icon"><i class="bi bi-person-check-fill"></i></div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $jumlah_hadir; ?></div>
                            <div class="stat-label">Hadir (7 hari)</div>
                        </div>
                    </div>
                    <div class="stat-card izin">
                        <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $jumlah_izin; ?></div>
                            <div class="stat-label">Izin (7 hari)</div>
                        </div>
                    </div>
                    <div class="stat-card sakit">
                        <div class="stat-icon"><i class="bi bi-thermometer-half"></i></div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo $jumlah_sakit; ?></div>
                            <div class="stat-label">Sakit (7 hari)</div>
                        </div>
                    </div>
                </div>

                <div class="chart-row">
                    <div class="chart-card bulanan">
                        <h6>Presensi Perbulan</h6>
                        <canvas id="chartBulanan"></canvas>
                    </div>
                    <div class="chart-card tahunan">
                        <h6>Presensi Pertahun</h6>
                        <canvas id="chartTahunan"></canvas>
                    </div>
                </div>
            <?php else: ?>
                <div class="placeholder-card">
                    Konten halaman <strong><?php echo ucfirst($menu_aktif); ?></strong> ditampilkan di sini.
                </div>
            <?php endif; ?>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($menu_aktif === 'dashboard'): ?>
    <script>
        const labelBulan = <?php echo json_encode($label_bulan); ?>;

        const dataTepatWaktu  = <?php echo json_encode($chart_tepat_waktu); ?>;
        const dataTerlambat   = <?php echo json_encode($chart_terlambat); ?>;
        const dataPulangCepat = <?php echo json_encode($chart_pulang_cepat); ?>;
        const dataTidakAbsen  = <?php echo json_encode($chart_tidak_absen); ?>;

        const dataTahunan = <?php echo json_encode(array_values($data_tahunan)); ?>;

        new Chart(document.getElementById('chartBulanan'), {
            type: 'bar',
            data: {
                labels: labelBulan,
                datasets: [
                    {
                        label: 'Tepat Waktu',
                        data: dataTepatWaktu,
                        backgroundColor: '#8de5b0',
                        stack: 'presensi'
                    },
                    {
                        label: 'Terlambat Masuk',
                        data: dataTerlambat,
                        backgroundColor: '#f5d491',
                        stack: 'presensi'
                    },
                    {
                        label: 'Pulang Sebelum Waktunya',
                        data: dataPulangCepat,
                        backgroundColor: '#f5a15e',
                        stack: 'presensi'
                    },
                    {
                        label: 'Tidak Absen',
                        data: dataTidakAbsen,
                        backgroundColor: '#f16a6a',
                        stack: 'presensi'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });

        new Chart(document.getElementById('chartTahunan'), {
            type: 'pie',
            data: {
                labels: ['Tepat Waktu', 'Terlambat', 'Pulang Sebelum Waktunya', 'Tidak Absen'],
                datasets: [{
                    data: dataTahunan,
                    backgroundColor: ['#a78bfa', '#fde68a', '#fb923c', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>