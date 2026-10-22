<?php
/* ============================================================
   VERIFIKASI PEMBAYARAN — pegawai
   Fitur:
   - daftar transaksi yang menunggu diproses:
       * status_bayar "menunggu_verifikasi": customer upload bukti transfer,
         pegawai COBA bukti (lihat gambar) lalu KONFIRMASI -> lunas
       * status_bayar "belum": dari pembelian PO (customer belum upload bukti),
         pegawai bisa tunda sampai bukti diupload customer
   - kalau transaksi terhubung dengan PO: PO jadi "selesai"
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('pegawai');

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)$_POST['id'];

    if ($aksi === 'terima') {
        $stmt = db()->prepare(
            'SELECT n.*, po.id AS po_id FROM penjualan n
             LEFT JOIN pemesanan_po po ON po.id = n.pemesanan_id
             WHERE n.id = ?'
        );
        $stmt->execute([$id]);
        $n = $stmt->fetch();

        if (!$n) {
            $pesan = ['danger', 'Transaksi tidak ditemukan.'];
        } elseif ($n['status_bayar'] !== 'menunggu_verifikasi') {
            $pesan = ['danger', 'Transaksi ini belum ada bukti bayar (masih "belum").'];
        } else {
            // 1) pembayaran jadi lunas
            db()->prepare("UPDATE penjualan SET status_bayar = 'lunas' WHERE id = ?")->execute([$id]);

            // 2) kalau penjualan terhubung dengan PO -> PO selesai
            if ($n['po_id']) {
                db()->prepare("UPDATE pemesanan_po SET status = 'selesai' WHERE id = ?")->execute([$n['po_id']]);
            }
            $pesan = ['success', "Pembayaran $n[no_invoice] dikonfirmasi (LUNAS)."];
        }
    }
}

/* daftar transaksi menunggu verifikasi (punya bukti upload) */
$menunggu = db()->query(
    'SELECT n.*, u.nama AS customer
     FROM penjualan n LEFT JOIN users u ON u.id = n.pembeli_id
     WHERE n.status_bayar = "menunggu_verifikasi"
     ORDER BY n.tanggal'
)->fetchAll();

/* transaksi "belum" (PO menunggu pembayaran customer) */
$belum = db()->query(
    'SELECT n.*, po.kode_po, u.nama AS customer
     FROM penjualan n
     JOIN pemesanan_po po ON po.id = n.pemesanan_id
     LEFT JOIN users u ON u.id = n.pembeli_id
     WHERE n.status_bayar = "belum"
     ORDER BY n.tanggal'
)->fetchAll();

$judul = 'Verifikasi Pembayaran';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Verifikasi Pembayaran</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<h5>Bukti upload menunggu verifikasi</h5>
<?php if (!$menunggu): ?>
  <div class="alert alert-light border">Tidak ada bukti transfer menunggu verifikasi.</div>
<?php endif; ?>

<?php foreach ($menunggu as $n): ?>
<div class="card mb-3 bg-white" style="max-width:640px">
  <div class="card-body">
    <div class="row g-2 align-items-center">
      <div class="col-6">
        <strong><?= e($n['no_invoice']) ?></strong><br>
        <small>Pembeli: <?= e($n['customer'] ?? '-') ?><br>
        Total: <?= rupiah($n['total']) ?></small>
      </div>
      <div class="col-3">
        <?php if ($n['bukti_bayar']): ?>
          <a href="<?= BASE_URL . e($n['bukti_bayar']) ?>" target="_blank">
            <img src="<?= BASE_URL . e($n['bukti_bayar']) ?>" style="height:80px" class="img-thumbnail"></a>
        <?php else: ?>
            — bukti tidak ada —
        <?php endif; ?>
      </div>
      <div class="col-3 text-end">
        <!-- terima: status jadi lunas -->
        <form method="post" onsubmit="return confirm('Konfirmasi LUNAS untuk <?= e($n['no_invoice']) ?>?')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="terima">
          <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
          <button class="btn btn-success btn-sm"> ✔ Konfirmasi Lunas</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<h5 class="mt-4">Transaksi "belum" (alternatif menunggu bukti)</h5>
<table class="table table-hover bg-white">
  <tr class="table-dark"><th>Invoice</th><th>Kode PO</th><th>Customer</th><th>Total</th></tr>
  <?php foreach ($belum as $n): ?>
  <tr>
    <td><?= e($n['no_invoice']) ?></td>
    <td><?= e($n['kode_po']) ?></td>
    <td><?= e($n['customer'] ?? '-') ?></td>
    <td><?= rupiah($n['total']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$belum): ?>
    <tr><td colspan="4" class="text-center text-muted">Tidak ada.</td></tr>
  <?php endif; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
