<?php
/* ============================================================
   DASHBOARD ADMIN
   Fitur: ringkasan singkat:
   - total penjualan bulan ini
   - barang yang stok < min_stok (perlu restock)
   - PO customer yang masih pending
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('admin');

/* Total penjualan (yang sudah lunas) bulan ini */
$q = db()->query(
    "SELECT COUNT(*) AS banyak, COALESCE(SUM(total),0) AS omzet
     FROM penjualan
     WHERE status_bayar = 'lunas'
       AND tanggal >= DATE_FORMAT(NOW(), '%Y-%m-01')"
);
$penjualan_bulan = $q->fetch();

/* Barang stok menipis */
$stok_kurang = db()->query(
    'SELECT nama, stok, min_stok FROM barang WHERE stok <= min_stok ORDER BY stok ASC'
)->fetchAll();

/* PO pending */
$po_pending = db()->query(
    "SELECT COUNT(*) AS c FROM pemesanan_po WHERE status = 'pending'"
)->fetch();

$judul = 'Dashboard Admin';
require __DIR__ . '/../includes/header.php';
?>

<h3>Dashboard Admin</h3>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card text-bg-primary p-3">
      <h6>Penjualan lunas bulan ini</h6>
      <h3><?= rupiah($penjualan_bulan['omzet']) ?></h3>
      <div class="small"><?= (int)$penjualan_bulan['banyak'] ?> transaksi</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-bg-warning p-3">
      <h6>PO pending</h6>
      <h3><?= (int)$po_pending['c'] ?></h3>
      <div class="small">perlu dikonfirmasi pegawai</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card text-bg-danger p-3">
      <h6>Stok menipis</h6>
      <h3><?= count($stok_kurang) ?></h3>
      <div class="small">barang <= min_stok</div>
    </div>
  </div>
</div>

<?php if ($stok_kurang): ?>
<div class="card mb-3">
  <div class="card-header">Daftar barang stok menipis</div>
  <table class="table mb-0 table-sm">
    <tr><th>Barang</th><th>Stok</th><th>Min</th></tr>
    <?php foreach ($stok_kurang as $b): ?>
      <tr>
        <td><?= e($b['nama']) ?></td>
        <td><?= (int)$b['stok'] ?></td>
        <td><?= (int)$b['min_stok'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
