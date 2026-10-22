<?php
/* ============================================================
   HEADER / TEMPLATE ATAS
   - dipakai semua halaman (require ke file ini)
   - variabel $judul = judul halaman
   - variabel $aktif  = link menu yang sedang dibuka (untuk highlight menu)
   - isi menu (navbar) berubah otomatis sesuai role yang login
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_login();
$toko = info_toko();
$u    = user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul ?? 'Toko Sparepart') ?> — <?= e($toko['nama_toko'] ?? '') ?></title>
<!-- Bootstrap 5 via CDN: library CSS tampilan siap pakai, untuk pemula -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">

<!-- navbar (menu atas) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= BASE_URL ?>">
      <?= e($toko['nama_toko'] ?? 'Toko Sparepart') ?>
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">

        <?php /* ----- MENU ADMIN ----- */ if ($u['role'] === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Master</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/users.php">User</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/kategori.php">Kategori</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/barang.php">Barang</a></li>
              <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/supplier.php">Supplier</a></li>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/laporan.php">Laporan</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/setting.php">Setting Toko</a></li>

        <?php /* ----- MENU PEGAWAI ----- */ elseif ($u['role'] === 'pegawai'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pegawai/kasir.php">Kasir</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pegawai/pembelian.php">Pembelian</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pegawai/po.php">Pesan-PO</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pegawai/verifikasi.php">Verifikasi Bayar</a></li>

        <?php /* ----- MENU CUSTOMER ----- */ else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>customer/katalog.php">Katalog</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>customer/keranjang.php">Keranjang</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>customer/riwayat.php">Riwayat & Bayar</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>customer/po.php">Buat PO</a></li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>auth/profil.php">
          <?= e($u['nama']) ?> (<?= e($u['role']) ?>)
        </a></li>
        <li class="nav-item"><a class="nav-link btn btn-outline-warning" href="<?= BASE_URL ?>auth/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">
