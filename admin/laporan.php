<?php
/* ============================================================
   LAPORAN PENJUALAN — admin
   Fitur:
   - filter periode (tanggal awal - tanggal akhir)
   - tabel semua penjualan pada periode tsb + total
   - tombol "Cetak / PDF" -> memakai dialog print browser (CTRL+P),
     pilih "Save as PDF" untuk menyimpan sebagai PDF.
     ADVANCED: kalau mau PDF dibuat di server (otomatis tersimpan,
     tidak tergantung browser), pakai library dompdf
     (https://github.com/dompdf/dompdf): buat halaman khusus
     yang render HTML ini lalu $dompdf->stream(). Butuh Composer.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('admin');

/* default periode: awal bulan s/d hari ini */
$tgl_awal  = $_GET['awal']  ?? date('Y-m-01');
$tgl_akhir = $_GET['akhir'] ?? date('Y-m-d');

/* ADVANCED: filter tanggal di sisi server tetap lewat prepared statement
   supaya aman dari SQL Injection. Rentang: >= awal 00:00 s/d < akhir+1 00:00 */
$sql  = 'SELECT p.*, u.nama AS kasir, c.nama AS pembeli
         FROM penjualan p
         LEFT JOIN users u ON u.id = p.users_id
         LEFT JOIN users c ON c.id = p.pembeli_id
         WHERE p.tanggal >= ? AND p.tanggal < ?
         ORDER BY p.tanggal ASC';
$stmt = db()->prepare($sql);
$stmt->execute([$tgl_awal . ' 00:00:00', date('Y-m-d', strtotime($tgl_akhir . ' +1 day')) . ' 00:00:00']);
$daftar = $stmt->fetchAll();

$total = 0;
$jumlah = 0;
foreach ($daftar as $d) {
    if ($d['status_bayar'] === 'lunas') {
        $total += (float)$d['total'];
        $jumlah++;
    }
}

$judul = 'Laporan Penjualan';
$cetak = true; // tanda: halaman ini dipakai nge-print
require __DIR__ . '/../includes/header.php';
?>

<h3>Laporan Penjualan</h3>

<!-- Filter periode -->
<form method="get" class="row g-2 align-items-end mb-3 no-print">
  <div class="col-md-3"><label class="form-label">Dari</label>
    <input type="date" name="awal" class="form-control" value="<?= e($tgl_awal) ?>"></div>
  <div class="col-md-3"><label class="form-label">Sampai</label>
    <input type="date" name="akhir" class="form-control" value="<?= e($tgl_akhir) ?>"></div>
  <div class="col-md-2"><button class="btn btn-dark">Tampilkan</button></div>
</form>

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>
      Laporan <?= e($tgl_awal) ?> s/d <?= e($tgl_akhir) ?>
      (<?= count($daftar) ?> transaksi, <?= $jumlah ?> lunas)
    </strong>
    <!-- window.print() menampilkan dialog print; pilih Save as PDF -->
    <button class="btn btn-danger no-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  </div>
  <table class="table table-sm table-hover mb-0 align-middle">
    <tr class="table-dark">
      <th>Invoice</th><th>Tanggal</th><th>Pembeli</th><th>Kasir</th>
      <th>Metode</th><th>Status</th><th class="text-end">Total</th>
    </tr>
    <?php foreach ($daftar as $d): ?>
    <tr>
      <td><?= e($d['no_invoice']) ?></td>
      <td><?= e(date('d-m-Y H:i', strtotime($d['tanggal']))) ?></td>
      <td><?= e($d['pembeli'] ?? '-') ?></td>
      <td><?= e($d['kasir'] ?? '-') ?></td>
      <td><?= e($d['metode']) ?></td>
      <td>
        <span class="badge bg-<?= $d['status_bayar']==='lunas'?'success':($d['status_bayar']==='belum'?'secondary':'warning') ?>">
          <?= e($d['status_bayar']) ?>
        </span>
      </td>
      <td class="text-end"><?= rupiah($d['total']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="table-light">
      <td colspan="6" class="text-end fw-bold">Total omzet (lunas):</td>
      <td class="text-end fw-bold"><?= rupiah($total) ?></td>
    </tr>
  </table>
</div>

<?php
$script = '
<style>
/* @media print = CSS hanya saat dicetak (dialog print browser) */
@media print {
  .no-print, nav, footer { display:none !important; }  /* sembunyikan menu & tombol */
  body { background:#fff !important; }
  .container { max-width:100% !important; }
}
</style>';
require __DIR__ . '/../includes/footer.php';
