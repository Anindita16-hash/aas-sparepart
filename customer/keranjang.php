<?php
/* ============================================================
   KERANJANG & CHECKOUT — customer
   Keranjang disimpan di SESSION (hilang kalau tutup browser).
   Fitur:
   - tambah barang (dipanggil dari halaman katalog)
   - lihat isi keranjang, ubah qty, hapus
   - tombol "Checkout": membuat PENJUALAN metode transfer,
     STOK LANGSUNG DIKURANGI di sini, status_bayar "belum".
     Pembayaran diselesaikan lewat halaman "Riwayat & Bayar".

   ADVANCED (transaksi): checkout membungkus 3 langkah
   (cari stok, insert penjualan+detail, update stok) dalam
   satu TRANSACTION supaya pengurangan stok konsisten
   (jika stok kurang, seluruh checkout dibatalkan).
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('customer');

$_SESSION['keranjang'] = $_SESSION['keranjang'] ?? [];
$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';

    /* ---------- TAMBAH ke keranjang (dari katalog) ---------- */
    if ($aksi === 'tambah') {
        $id  = (int)$_POST['id'];
        $qty = max(1, (int)$_POST['qty']);

        // stok dicek saat tambah: tidak boleh melebihi stok yang ada
        $stmt = db()->prepare('SELECT stok, nama FROM barang WHERE id = ?');
        $stmt->execute([$id]);
        $b = $stmt->fetch();

        if (!$b) {
            $pesan = ['danger', 'Barang tidak ada.'];
        } else {
            $sudah   = $_SESSION['keranjang'][$id] ?? 0;
            $maks    = $b['stok'];
            if ($sudah + $qty > $maks) {
                $pesan = ['danger', "Stok \"{$b['nama']}\" tersisa $maks."];
                $qty = max(0, $maks - $sudah);
            }
            if ($qty > 0) {
                $_SESSION['keranjang'][$id] = $sudah + $qty;
                $pesan = ['success', "\"{$b['nama']}\" masuk keranjang."];
            }
        }
    }

    /* ---------- UBAH qty / HAPUS ---------- */
    if ($aksi === 'ubah') {
        $id  = (int)$_POST['id'];
        $qty = (int)$_POST['qty'];
        if ($qty <= 0) {
            unset($_SESSION['keranjang'][$id]);
        } else {
            $_SESSION['keranjang'][$id] = $qty;
        }
        $pesan = ['success', 'Keranjang diperbarui.'];
    }

    /* ---------- CHECKOUT ---------- */
    if ($aksi === 'checkout') {
        if (!$_SESSION['keranjang']) {
            $pesan = ['danger', 'Keranjang kosong.'];
        } else {
            try {
                $db = db();
                $db->beginTransaction(); /* ADVANCED: transaction */

                $total = 0;
                $rows  = [];
                foreach ($_SESSION['keranjang'] as $id => $qty) {
                    $stmt = $db->prepare('SELECT * FROM barang WHERE id = ? FOR UPDATE');
                    $stmt->execute([$id]);
                    $b = $stmt->fetch();
                    if (!$b || $b['stok'] < $qty) {
                        throw new Exception('Stok barang berubah / kurang. Periksa keranjang lagi.');
                    }
                    $subtotal = $b['harga_jual'] * $qty;
                    $total += $subtotal;
                    $rows[] = ['id' => $id, 'qty' => $qty, 'harga' => $b['harga_jual'], 'subtotal' => $subtotal];
                }

                // nomor invoice otomatis
                $nomor = nomor_dokumen('INV',
                    (int)db()->query('SELECT COALESCE(MAX(id),0) AS i FROM penjualan')->fetch()['i']);

                // pembayaran metode TRANSFER, awal status "belum"
                // (customer upload bukti lewat "Riwayat & Bayar")
                $db->prepare(
                    'INSERT INTO penjualan (no_invoice, pembeli_id, total, metode, status_bayar)
                     VALUES (?, ?, ?, "transfer", "belum")'
                )->execute([$nomor, user()['id'], $total]);
                $penjualan_id = $db->lastInsertId();

                foreach ($rows as $r) {
                    $db->prepare('INSERT INTO penjualan_detail (penjualan_id,barang_id,qty,harga_satuan,subtotal) VALUES (?,?,?,?,?)')
                       ->execute([$penjualan_id, $r['id'], $r['qty'], $r['harga'], $r['subtotal']]);
                    $db->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')
                       ->execute([$r['qty'], $r['id']]);
                }

                $db->commit();

                $_SESSION['keranjang'] = []; // kosongkan keranjang
                $_SESSION['flash'] = "Checkout sukses! Invoice $nomor. Silakan bayar di menu Riwayat & Bayar.";
                header('Location: ' . BASE_URL . 'customer/riwayat.php');
                exit;

            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) $db->rollBack();
                $pesan = ['danger', 'Checkout ditolak: ' . $e->getMessage()];
            }
        }
    }
}

/* ambil isi keranjang + data barang (1 query dengan IN) */
$keranjang = $_SESSION['keranjang'];
$isi = [];
if ($keranjang) {
    // ADVANCED: query memakai IN + prepared statement, aman dari SQL Injection
    $in          = implode(',', array_fill(0, count($keranjang), '?'));
    $stmt        = db()->prepare("SELECT * FROM barang WHERE id IN ($in)");
    $stmt->execute(array_keys($keranjang));
    foreach ($stmt->fetchAll() as $b) {
        $b['qty'] = $keranjang[$b['id']];
        $b['subtotal'] = $b['harga_jual'] * $b['qty'];
        $isi[] = $b;
    }
}
$total = array_sum(array_column($isi, 'subtotal'));

$judul = 'Keranjang';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Keranjang Belanja</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<?php if (!$isi): ?>
  <div class="alert alert-light border">Keranjang masih kosong. <a href="katalog.php">Belanja sekarang</a>.</div>
<?php else: ?>
<table class="table table-hover bg-white align-middle">
  <tr class="table-dark"><th>Barang</th><th style="width:120px">Qty</th><th class="text-end">Subtotal</th><th style="width:90px"></th></tr>
  <?php foreach ($isi as $b): ?>
  <tr>
    <td><?= e($b['nama']) ?><div class="small text-muted"><?= rupiah($b['harga_jual']) ?> / item</div></td>
    <td>
      <form method="post" class="d-flex gap-1">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="ubah">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <input type="number" name="qty" class="form-control form-control-sm" value="<?= (int)$b['qty'] ?>" min="0" onchange="this.form.submit()">
      </form>
    </td>
    <td class="text-end"><?= rupiah($b['subtotal']) ?></td>
    <td>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="ubah">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="qty" value="0">
        <button class="btn btn-sm btn-outline-danger">Hapus</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <tr>
    <td colspan="2" class="text-end fw-bold">TOTAL</td>
    <td class="text-end fw-bold"><?= rupiah($total) ?></td>
    <td></td>
  </tr>
</table>

<form method="post" class="text-end">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="checkout">
  <button class="btn btn-success btn-lg">Checkout (bayar transfer)</button>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
