<?php
    $host = 'localhost';
    $login = 'root';
    $password = '';
    $database = 'db_bekah_presensi';

        $koneksi = mysqli_connect($host, $login, $password, $database);

        if (!$koneksi) {
            die('gagal konek: ' . mysqli_connect_error());
        }
?>