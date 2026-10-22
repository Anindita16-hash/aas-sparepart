<?php
/* ============================================================
   PO (PRE-ORDER) BARANG — customer
   Fitur:
   - buat PO untuk barang (dipakai saat stok habis dari katalog,
     atau bisa pilih manual dari daftar bawah)
   - daftar PO milik customer beserta status:
     pending -> dikonfirmasi -> diproses -> siap -> selesai / ditolak
   Pegawai menindaklanjuti dari menu "Pesan-PO" mereka.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('customer');

$pesan = '';

/* ---------------- BUAT PO (bisa dengan beberapa barang) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    $barang_id = (int)$_POST['barang_id'];
    $qty       = max(1, (int)$_POST['qty']);
    $catatan   = trim($_POST['catatan'] ?? '');

    $stmt = db()->prepare('SELECT * FROM barang WHERE id = ?');
    $stmt->execute([$barang_id]);
    $b = $stmt->fetch();

    if (!$b) {
        $pesan = ['danger', 'Barang tidak ditemukan.'];
    } else {
        try {
            $db = db();
            /* ADVANCED: TRANSACTION — PO + detail dibuat dalam satu
               kesatuan: keduanya sukses atau dibatalkan bersama-sama */
            $db->beginTransaction();

            $kode = nomor_dokumen('PO',
                (int)db()->query('SELECT COALESCE(MAX(id),0) AS i FROM pemesanan_po')->fetch()['i']);

            $db->prepare('INSERT INTO pemesanan_po (kode_po, users_id, status, catatan) VALUES (?, ?, "pending", ?)')
               ->execute([$kode, user()['id'], $catatan ?: null]);
            $po_id = $db->lastInsertId();

            $db->prepare('INSERT INTO po_detail (pemesanan_id, barang_id, qty, harga_satuan) VALUES (?,?,?,?)')
               ->execute([$po_id, $b['id'], $qty, $b['harga_jual']]);

            $db->commit();
            $pesan = ['success', "PO berhasil dibuat dengan kode $kode. Menunggu konfirmasi toko."];
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $pesan = ['danger', 'Gagal membuat PO.'];
        }
    }
}

$barang = db()->query('SELECT * FROM barang ORDER BY nama')->fetchAll();

/* daftar PO milik customer ini (dengan kepemilikan dicek di query) */
$stmt = db()->prepare(
    'SELECT po.*, (SELECT COALESCE(SUM(qty*harga_satuan),0) FROM po_detail WHERE pemesanan_id = po.id) AS total
     FROM pemesanan_po po
     WHERE po.users_id = ?
     ORDER BY po.id DESC'
);
$stmt->execute([user()['id']]);
$daftar = $stmt->fetchAll();

/* detail PO */
$rows = db()->query('SELECT d.*, b.nama FROM po_detail d JOIN barang b ON b.id = d.barang_id')->fetchAll();
$detail = [];
foreach ($rows as $r) { $detail[$r['pemesanan_id']][] = $r; }

$judul = 'PO Barang';
require __DIR__ . '/../includes/header.php';
?>

<div class="row">
  <div class="col-md-5">
    <h3>Buat PO (Pre-Order)</h3>

    <?php if ($pesan): ?>
      <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
    <?php endif; ?>

    <div class="card bg-white mb-4">
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-2"><label class="form-label">Barang</label>
            <select name="barang_id" class="form-select" required>
              <option value="">- pilih barang -</option>
              <?php foreach ($barang as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= (int)($_GET['barang_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                  <?= e($b['nama']) ?> — <?= rupiah($b['harga_jual']) ?>
                  (stok <?= (int)$b['stok'] ?><?= $b['stok'] <= 0 ? ', HABIS → layak PO' : '' ?>)
                </option>
              <?php endforeach; ?>
            </select></div>
          <div class="mb-2"><label class="form-label">Qty</label>
            <input type="number" name="qty" class="form-control" value="1" min="1" required></div>
          <div class="mb-3"><label class="form-label">Catatan (opsional)</label>
            <textarea name="catatan" class="form-control" rows="2"></textarea></div>
          <button class="btn btn-warning">Kirim PO</button>
        </form>
        <div class="small text-muted mt-2">
          Alur PO: kamu kirim PO → toko konfirmasi → toko restock ke supplier →
          barang datang, stok bertambah → PO "siap diambil/dikirim" → kamu bayar → pegawai verifikasi.
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <h3>PO Saya</h3>
    <?php if (!$daftar): ?>
      <div class="alert alert-light border">Belum ada PO. Buat di form di samping.</div>
    <?php endif; ?>

    <?php foreach ($daftar as $po): ?>
    <div class="card mb-2 bg-white">
      <div class="card-body py-2">
        <div class="d-flex justify-content-between">
          <strong><?= e($po['kode_po']) ?></strong>
          <div>
            <span class="badge bg-<?= ['pending'=>'secondary','dikonfirmasi'=>'info','diproses'=>'info','siap'=>'warning','selesai'=>'success','ditolak'=>'danger'][$po['status']] ?>">
              <?= e($po['status']) ?>
            </span>
            <strong class="ms-2"><?= rupiah($po['total']) ?></strong>
          </div>
        </div>
        <ul class="small mb-0">
          <?php foreach ($detail[$po['id']] ?? [] as $d): ?>
            <li><?= e($d['nama']) ?> x<?= (int)$d['qty'] ?> (<?= rupiah($d['harga_satuan']) ?>)</li>
          <?php endforeach; ?>
        </ul>
        <?php if ($po['status'] === 'siap'): ?>
          <div class="small text-warning mt-1">✔ Barang siap! Bayar lewat menu <a href="riwayat.php">Riwayat & Bayar</a></div>
        <?php elseif ($po['status'] === 'ditolak'): ?>
          <div class="small text-danger mt-1">PO ini ditolak toko.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
