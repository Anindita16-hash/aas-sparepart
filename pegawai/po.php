<?php
/* ============================================================
   PROSES PO (PESANAN) — pegawai
   Fitur:
   - lihat semua PO customer
   - aksi:       pending -> dikonfirmasi   (konfirmasi ke customer)
                 pending -> ditolak
   Catatan alurnya:
     1. PO dibuat customer (status pending)
     2. Pegawai konfirmasi DI HALAMAN INI -> "dikonfirmasi"
     3. Pegawai buat PEMBELIAN/RESTOCK di menu Pembelian dengan
        memilih PO ini -> stok bertambah, PO jadi "siap"
     4. Customer bayar -> pegawai verifikasi -> PO "selesai"
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('pegawai');

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)$_POST['id'];

    // aksi yang sah: konfirmasi/tolak (hanya PO masih pending)
    if ($aksi === 'konfirmasi' || $aksi === 'tolak') {
        $stmt = db()->prepare('SELECT status FROM pemesanan_po WHERE id = ?');
        $stmt->execute([$id]);
        $po = $stmt->fetch();

        if (!$po || $po['status'] !== 'pending') {
            $pesan = ['danger', 'PO tidak ditemukan atau sudah diproses.'];
        } else {
            $status = $aksi === 'konfirmasi' ? 'dikonfirmasi' : 'ditolak';
            db()->prepare('UPDATE pemesanan_po SET status = ? WHERE id = ?')->execute([$status, $id]);
            $pesan = ['success', "PO #{$id} di-$status. " .
                ($status === 'dikonfirmasi' ? 'Lanjut ke menu Pembelian untuk restock.' : '')];
        }
    }
}

/* daftar PO + detail barang */
$daftar = db()->query(
    'SELECT po.*, u.nama AS customer
     FROM pemesanan_po po JOIN users u ON u.id = po.users_id
     ORDER BY po.id DESC'
)->fetchAll();

/* ambil detail per PO dalam 1 query saja, lalu dikelompokkan di PHP */
$rows = db()->query(
    'SELECT d.*, b.nama FROM po_detail d JOIN barang b ON b.id = d.barang_id ORDER BY d.pemesanan_id'
)->fetchAll();
$detail = []; // [po_id] = [ [nama, qty, harga], ... ]
foreach ($rows as $r) {
    $detail[$r['pemesanan_id']][] = $r;
}

$judul = 'Pesan-PO Customer';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Pesan-PO Customer</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<table class="table table-hover bg-white align-middle">
  <tr class="table-dark">
    <th>Kode PO</th><th>Customer</th><th>Isi PO</th><th>Status</th><th style="width:200px">Aksi</th>
  </tr>
  <?php foreach ($daftar as $po): ?>
  <tr>
    <td><strong><?= e($po['kode_po']) ?></strong></td>
    <td><?= e($po['customer']) ?></td>
    <td>
      <ul class="mb-0 small pl-3">
        <?php foreach ($detail[$po['id']] ?? [] as $d): ?>
          <li><?= e($d['nama']) ?> x<?= (int)$d['qty'] ?> (<?= rupiah($d['harga_satuan']) ?>)</li>
        <?php endforeach; ?>
      </ul>
      <?php if ($po['catatan']): ?><div class="small text-muted">Catatan: <?= e($po['catatan']) ?></div><?php endif; ?>
    </td>
    <td><span class="badge bg-<?= ['pending'=>'secondary','dikonfirmasi'=>'info','diproses'=>'info','siap'=>'warning','selesai'=>'success','ditolak'=>'danger'][$po['status']] ?>">
        <?= e($po['status']) ?></span></td>
    <td>
      <?php if ($po['status'] === 'pending'): ?>
        <form method="post" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="konfirmasi">
          <input type="hidden" name="id" value="<?= (int)$po['id'] ?>">
          <button class="btn btn-sm btn-success">Konfirmasi</button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm('Tolak PO ini?')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="tolak">
          <input type="hidden" name="id" value="<?= (int)$po['id'] ?>">
          <button class="btn btn-sm btn-outline-danger">Tolak</button>
        </form>
      <?php elseif ($po['status'] === 'dikonfirmasi'): ?>
        <a class="btn btn-sm btn-outline-primary" href="pembelian.php">Restock (menu Pembelian)</a>
      <?php elseif ($po['status'] === 'siap'): ?>
        <span class="small text-muted">Menunggu customer bayar</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
