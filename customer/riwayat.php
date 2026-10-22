<?php
/* ============================================================
   RIWAYAT TRANSAKSI & PEMBAYARAN — customer
   Fitur:
   - lihat semua invoice atas nama customer (untuk riwayat)
   - UPLOAD BUKTI TRANSFER untuk invoice berstatus "belum"
     Bukti masuk folder uploads/bukti/ (nama file disimpan
     ke kolom penjualan.bukti_bayar)
   - setelah upload, transaksi jadi "menunggu_verifikasi"
     untuk ditetapkan pegawai di menu Verifikasi Pembayaran.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('customer');

$pesan = '';

/* ---------------- UPLOAD BUKTI BAYAR ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    $id = (int)$_POST['id'];
    // invoice WAJIB milik customer yang login (bukan id untuk orang lain!)
    // ADVANCED (OWASP A01): selalu gabungkan pengecekan kepemilikan
    // dalam QUERY, tidak hanya "sembunyikan tombolnya" di UI.
    $stmt = db()->prepare(
        'SELECT * FROM penjualan WHERE id = ? AND pembeli_id = ?'
    );
    $stmt->execute([$id, user()['id']]);
    $inv = $stmt->fetch();

    if (!$inv) {
        $pesan = ['danger', 'Invoice tidak ditemukan / bukan kepemilikanmu.'];
    } elseif ($inv['status_bayar'] !== 'belum' && $inv['status_bayar'] !== 'menunggu_verifikasi') {
        $pesan = ['danger', 'Invoice ini sudah lunas.'];
    } else {
        $bukti = upload_gambar($_FILES['bukti'] ?? null, 'bukti');
        if (!$bukti) {
            $pesan = ['danger', 'Upload gagal. Gunakan JPG/PNG/WEBP maksimal 2 MB.'];
        } else {
            db()->prepare("UPDATE penjualan SET bukti_bayar = ?, status_bayar = 'menunggu_verifikasi' WHERE id = ?")
                 ->execute([$bukti, $id]);
            $pesan = ['success', 'Bukti transfer terkirim. Menunggu verifikasi pegawai.'];
        }
    }
}

/* riwayat semua invoice milik customer */
$stmt = db()->prepare(
    'SELECT n.*, po.kode_po FROM penjualan n
     LEFT JOIN pemesanan_po po ON po.id = n.pemesanan_id
     WHERE n.pembeli_id = ? ORDER BY n.id DESC'
);
$stmt->execute([user()['id']]);
$riwayat = $stmt->fetchAll();

/* detail barang tiap invoice */
$rows = db()->query(
    'SELECT d.*, b.nama FROM penjualan_detail d JOIN barang b ON b.id = d.barang_id'
)->fetchAll();
$detail = [];
foreach ($rows as $r) {
    $detail[$r['penjualan_id']][] = $r;
}

$judul = 'Riwayat Transaksi';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Riwayat Transaksi & Pembayaran</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<?php foreach ($riwayat as $n): ?>
<?php
/* petunjuk status untuk customer */
$warna = ['belum' => 'secondary', 'menunggu_verifikasi' => 'warning', 'lunas' => 'success'][$n['status_bayar']];
?>
<div class="card mb-3 bg-white">
  <div class="card-header d-flex justify-content-between bg-white">
    <div>
      <strong><?= e($n['no_invoice']) ?></strong>
      <?php if ($n['kode_po']): ?><span class="badge bg-info">PO <?= e($n['kode_po']) ?></span><?php endif; ?>
      <span class="badge bg-<?= $warna ?>"><?= e($n['status_bayar']) ?></span>
      <span class="badge bg-dark"><?= e($n['metode']) ?></span>
    </div>
    <div class="text-end small"><?= e(date('d-m-Y H:i', strtotime($n['tanggal']))) ?></div>
  </div>
  <div class="card-body">
    <div class="row">
      <!-- daftar item invoice -->
      <div class="col-md-7">
        <table class="table table-sm small">
          <?php foreach ($detail[$n['id']] ?? [] as $d): ?>
          <tr><td><?= e($d['nama']) ?> x<?= (int)$d['qty'] ?></td><td class="text-end"><?= rupiah($d['subtotal']) ?></td></tr>
          <?php endforeach; ?>
          <tr><td class="fw-bold">TOTAL</td><td class="text-end fw-bold"><?= rupiah($n['total']) ?></td></tr>
        </table>
      </div>

      <!-- kolom pembayaran -->
      <div class="col-md-5">
        <?php if ($n['status_bayar'] === 'lunas'): ?>
          <div class="text-success fw-bold">✔ Sudah dibayar</div>
          <?php if ($n['bukti_bayar']): ?>
            <a href="<?= BASE_URL . e($n['bukti_bayar']) ?>" target="_blank" class="small">bukti transfer</a>
          <?php endif; ?>
          <?php if ($n['gateway_ref']): ?>
            <div class="small text-muted">Ref gateway: <?= e($n['gateway_ref']) ?></div>
          <?php endif; ?>

        <?php else: ?>
          <div class="small mb-2">
            Transfer ke: <br><strong>BANK SANDI 1234-5678-90<br>a.n. Toko Sparepart Mobil</strong>
          </div>

          <!-- upload bukti (status belum / menunggu_verifikasi) -->
          <form method="post" enctype="multipart/form-data" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
            <div class="col-12">
              <?php if ($n['bukti_bayar']): ?>
                <div class="small text-muted">Bukti terakhir: <a href="<?= BASE_URL . e($n['bukti_bayar']) ?>" target="_blank">lihat</a> — bisa upload ulang.</div>
              <?php endif; ?>
              <input type="file" name="bukti" accept="image/*" class="form-control form-control-sm" required>
            </div>
            <div class="col-12">
              <button class="btn btn-primary btn-sm"><?= $n['status_bayar'] === 'belum' ? 'Upload Bukti Transfer' : 'Upload Ulang Bukti' ?></button>
            </div>
          </form>

          <!-- CATATAN INTEGRASI PAYMENT GATEWAY (mockup):
               Di sini nanti tombol "Bayar via Payment Gateway" ditambahkan.
               Alur: tombol membawa user ke gateway/mock-gateway.php
               (Halaman mockup) -> user "membayar" -> gateway memanggil
               gateway/callback.php -> callback menetapkan status invoice
               status_bayar=menunggu_verifikasi + mengisi kolom gateway_ref.
               Kolom `gateway_ref` sudah disiapkan untuk ini. -->
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if (!$riwayat): ?>
  <div class="alert alert-light border">Belum ada transaksi. <a href="katalog.php">Mulai belanja</a>.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
