<?php
/* Halaman depan: langsung lempar sesuai status login */
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    /* Sudah login -> lempar ke dashboard sesuai role */
    $tujuan = [
        'admin'    => 'admin/dashboard.php',
        'pegawai'  => 'pegawai/kasir.php',
        'customer' => 'customer/katalog.php',
    ];
    header('Location: ' . BASE_URL . ($tujuan[$_SESSION['role']] ?? 'auth/login.php'));
} else {
    header('Location: ' . BASE_URL . 'auth/login.php');
}
exit;
