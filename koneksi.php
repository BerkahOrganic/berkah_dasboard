<?php
    $host = 'localhost';
    $login = 'root';
    $password = '$Berkah_App#26#';
    $database = 'db_bekah_presensi';

        $koneksi = mysqli_connect($host, $login, $password, $database);

        if (!$koneksi) {
            die('gagal konek: ' . mysqli_connect_error());
        }
?>