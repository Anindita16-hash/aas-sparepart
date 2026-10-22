<?php
/* ============================================================
   KATALOG BARANG — customer (+ admin/pegawai boleh lihat)
   Fitur:
   - daftar barang dengan foto, harga jual, stok
   - filter kategori & pencarian
   - tombol "+ Keranjang" (hanya customer)
   - tombol "PO Barang" untuk barang limit stok kosong
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('customer'); // FITUR katalog utama memang untuk customer

$kategori_aktif = (int)($_GET['kategori'] ?? 0);
$cari           = trim($_GET['cari'] ?? '');

$sql    = 'SELECT b.*, k.nama AS nama_kategori FROM barang b
           JOIN kategori k ON k.id = b.kategori_id
           WHERE 1=1';
$params = [];
if ($kategori_aktif) { $sql .= ' AND b.kategori_id = ?'; $params[] = $kategori_aktif; }
if ($cari !== '')    { $sql .= ' AND b.nama LIKE ?';    $params[] = "%$cari%"; }
$sql .= ' ORDER BY b.nama';
$stmt = db()->prepare($sql); $stmt->execute($params);
$daftar = $stmt->fetchAll();

$kategori_all = db()->query('SELECT * FROM kategori ORDER BY nama')->fetchAll();

/* keranjang: disimpan di session, format [id_barang => qty] */
$keranjang = $_SESSION['keranjang'] ?? [];

/* pesan feedback add-to-keranjang / beli habis */
$pesan_flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$judul = 'Katalog';
require __DIR__ . '/../includes/header.php';
?>

<h3>Katalog Barang</h3>

<?php if ($pesan_flash): ?><div class="alert alert-info"><?= e($pesan_flash) ?></div><?php endif; ?>

<form method="get" class="d-flex gap-1 mb-3" style="max-width:420px">
  <select name="kategori" class="form-select" onchange="this.form.submit()">
    <option value="0">Semua kategori</option>
    <?php foreach ($kategori_all as $k): ?>
      <option value="<?= (int)$k['id'] ?>" <?= $kategori_aktif === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
    <?php endforeach; ?>
  </select>
  <input name="cari" class="form-control" placeholder="Cari barang…" value="<?= e($cari) ?>">
  <button class="btn btn-dark">Cari</button>
</form>

<div class="row g-3">
  <?php foreach ($daftar as $b): ?>
  <div class="col-md-4 col-sm-6">
    <div class="card h-100 bg-white">
      <!-- gambar barang: kalau tidak ada -> tampil foto "pengganti" (placeholder) -->
      <?php if ($b['gambar']): ?>
        <img src="<?= BASE_URL . e($b['gambar']) ?>" class="card-img-top" style="height:180px;object-fit:cover">
      <?php else: ?>
        <div class="text-center text-muted py-5">tanpa foto</div>
      <?php endif; ?>
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <h5 class="mb-1"><?= e($b['nama']) ?></h5>
          <span class="badge bg-secondary align-self-start"><?= e($b['nama_kategori']) ?></span>
        </div>
        <div class="small text-muted"><?= e($b['merk']) ?></div>
        <h5 class="text-primary my-2"><?= rupiah($b['harga_jual']) ?></h5>
        <p class="small mb-2"><?= e($b['deskripsi']) ?></p>
      </div>
      <div class="card-footer bg-white">
        <?php if ($b['stok'] > 0): ?>
          <span class="small <?= $b['stok'] <= $b['min_stok'] ? 'text-warning' : 'text-success' ?>">
            Stok: <?= (int)$b['stok'] ?><?= $b['stok'] <= $b['min_stok'] ? ' (menipis)' : '' ?>
          </span>
          <!-- add to cart: kirim POST ke keranjang.php -->
          <form method="post" action="keranjang.php" class="mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <input type="number" name="qty" class="form-control form-control-sm mb-1" min="1" max="<?= (int)$b['stok'] ?>" value="1">
            <button class="btn btn-primary btn-sm w-100">+ Keranjang</button>
          </form>
        <?php else: ?>
          <!-- stok kosong -> alur bisnis: customer BUAT PO -->
          <span class="badge bg-danger mb-1">Stok Habis</span>
          <a href="po.php?barang_id=<?= (int)$b['id'] ?>" class="btn btn-warning btn-sm w-100">PO Barang</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$daftar): ?>
    <div class="text-center text-muted">Tidak ada barang.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
