<?php
/* ============================================================
   MASTER BARANG (CRUD) — admin & pegawai
   Fitur:
   - daftar barang (dengan foto, kategori, harga beli/jual, stok)
   - tambah / edit / hapus barang
   - UPLOAD GAMBAR BARANG (disimpan di uploads/barang/)
   - filter kategori & pencarian nama (pakai prepared statement)
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role(['admin', 'pegawai']); // pegawai juga boleh lihat, admin boleh kelola

$isAdmin  = user()['role'] === 'admin';
$pesan    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';

    /* --------- TAMBAH / EDIT (diproses hanya admin) --------- */
    if ($isAdmin && ($aksi === 'tambah' || $aksi === 'edit')) {
        $kategori_id = (int)$_POST['kategori_id'];
        $nama        = trim($_POST['nama'] ?? '');
        $merk        = trim($_POST['merk'] ?? '');
        // harga di-cast float (angka), mis. input "55.000" jadi 55
        $harga_beli  = (float)str_replace('.', '', $_POST['harga_beli'] ?? '0');
        $harga_jual  = (float)str_replace('.', '', $_POST['harga_jual'] ?? '0');
        $stok        = (int)$_POST['stok'];
        $min_stok    = (int)$_POST['min_stok'];
        $deskripsi   = trim($_POST['deskripsi'] ?? '');
        $gambar      = upload_gambar($_FILES['gambar'] ?? null, 'barang');

        if ($nama === '') {
            $pesan = ['danger', 'Nama barang wajib diisi.'];
        } else {
            try {
                if ($aksi === 'tambah') {
                    $stmt = db()->prepare(
                        'INSERT INTO barang (kategori_id,nama,merk,harga_beli,harga_jual,stok,min_stok,gambar,deskripsi)
                         VALUES (?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([$kategori_id,$nama,$merk,$harga_beli,$harga_jual,
                                    $stok,$min_stok,$gambar,$deskripsi]);
                    $pesan = ['success', 'Barang baru ditambahkan.'];
                } else {
                    $sql = 'UPDATE barang SET kategori_id=?, nama=?, merk=?,
                            harga_beli=?, harga_jual=?, stok=?, min_stok=?, deskripsi=?';
                    $par = [$kategori_id,$nama,$merk,$harga_beli,$harga_jual,$stok,$min_stok,$deskripsi];

                    // gambar baru diupload -> REPLACE gambar lama
                    if ($gambar) {
                        $sql .= ', gambar=?';
                        $par[] = $gambar;
                    }
                    $sql .= ' WHERE id=?';
                    $par[] = (int)$_POST['id'];

                    db()->prepare($sql)->execute($par);
                    $pesan = ['success', 'Barang diubah.'];
                }
            } catch (PDOException $e) {
                $pesan = ['danger', 'Gagal menyimpan barang.'];
            }
        }
    }

    /* --------- HAPUS --------- */
    if ($isAdmin && $aksi === 'hapus') {
        try {
            db()->prepare('DELETE FROM barang WHERE id = ?')->execute([(int)$_POST['id']]);
            $pesan = ['success', 'Barang dihapus.'];
        } catch (PDOException $e) {
            $pesan = ['danger', 'Tidak bisa dihapus, masih dipakai transaksi.'];
        }
    }
}

/* edit? ambil data 1 barang */
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM barang WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

/* filter kategori & pencarian */
$kategori_aktif = (int)($_GET['kategori'] ?? 0);
$cari           = trim($_GET['cari'] ?? '');

$sql    = 'SELECT b.*, k.nama AS nama_kategori FROM barang b
           JOIN kategori k ON k.id = b.kategori_id
           WHERE 1=1';
$params = [];

if ($kategori_aktif) {
    $sql .= ' AND b.kategori_id = ?';
    $params[] = $kategori_aktif;
}
if ($cari !== '') {
    // ADVANCED: LIKE pun ikutan prepared statement, input user tetap aman
    $sql .= ' AND b.nama LIKE ?';
    $params[] = '%' . $cari . '%';
}
$sql .= ' ORDER BY b.id DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

$kategori_all = db()->query('SELECT * FROM kategori ORDER BY nama')->fetchAll();

$judul = 'Barang';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3>Barang</h3>
  <form method="get" class="d-flex gap-1" style="max-width:420px">
    <select name="kategori" class="form-select" onchange="this.form.submit()">
      <option value="0">Semua kategori</option>
      <?php foreach ($kategori_all as $k): ?>
        <option value="<?= (int)$k['id'] ?>" <?= $kategori_aktif === (int)$k['id'] ? 'selected' : '' ?>>
          <?= e($k['nama']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input name="cari" class="form-control" placeholder="Cari barang…" value="<?= e($cari) ?>">
    <button class="btn btn-dark">Cari</button>
  </form>
</div>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- --------- FORM TAMBAH/EDIT BARANG (hanya admin) --------- -->
<div class="card mb-4">
  <div class="card-header"><?= $edit ? 'Edit Barang: ' . e($edit['nama']) : 'Tambah Barang Baru' ?></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $edit ? 'edit' : 'tambah' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

      <div class="col-md-5"><label class="form-label">Nama Barang</label>
        <input name="nama" class="form-control" required value="<?= e($edit['nama'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Kategori</label>
        <select name="kategori_id" class="form-select" required>
          <option value="">- pilih -</option>
          <?php foreach ($kategori_all as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= (int)($edit['kategori_id'] ?? 0) === (int)$k['id'] ? 'selected' : '' ?>>
              <?= e($k['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-4"><label class="form-label">Merk</label>
        <input name="merk" class="form-control" value="<?= e($edit['merk'] ?? '') ?>"></div>

      <div class="col-md-3"><label class="form-label">Harga Beli</label>
        <input name="harga_beli" class="form-control" value="<?= e($edit['harga_beli'] ?? '') ?>" required></div>
      <div class="col-md-3"><label class="form-label">Harga Jual</label>
        <input name="harga_jual" class="form-control" value="<?= e($edit['harga_jual'] ?? '') ?>" required></div>
      <div class="col-md-3"><label class="form-label">Stok</label>
        <input type="number" name="stok" class="form-control" value="<?= (int)($edit['stok'] ?? 0) ?>"></div>
      <div class="col-md-3"><label class="form-label">Stok Minimal (peringatan)</label>
        <input type="number" name="min_stok" class="form-control" value="<?= (int)($edit['min_stok'] ?? 5) ?>"></div>

      <div class="col-md-12"><label class="form-label">Deskripsi</label>
        <textarea name="deskripsi" class="form-control" rows="2"><?= e($edit['deskripsi'] ?? '') ?></textarea></div>

      <div class="col-md-4"><label class="form-label">Gambar
        <?php if ($edit && $edit['gambar']): ?><div class="small text-muted">Ada gambar lama, biarkan kosong untuk memakai yang lama</div><?php endif; ?></label>
        <input type="file" name="gambar" accept="image/*" class="form-control"></div>
      <?php if ($edit && $edit['gambar']): ?>
        <div class="col-md-2">
          <img src="<?= BASE_URL . e($edit['gambar']) ?>" style="height:80px" class="img-thumbnail">
        </div>
      <?php endif; ?>

      <div class="col-md-4 d-flex align-items-end">
        <button class="btn btn-primary me-1"><?= $edit ? 'Simpan' : 'Tambah' ?></button>
        <?php if ($edit): ?><a href="barang.php" class="btn btn-secondary">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-info">Pegawai dapat melihat & pencarian barang. Kelola master barang dilakukan oleh admin (di menu Master → Barang admin).</div>
<?php endif; ?>

<!-- --------- TABEL DAFTAR BARANG --------- -->
<table class="table table-hover bg-white align-middle">
  <tr class="table-dark">
    <th>Foto</th><th>Nama</th><th>Kategori</th><th>Harga Beli</th><th>Harga Jual</th><th>Stok</th>
    <?php if ($isAdmin): ?><th style="width:170px">Aksi</th><?php endif; ?>
  </tr>
  <?php foreach ($daftar as $b): ?>
  <tr class="<?= $b['stok'] <= $b['min_stok'] ? 'table-warning' : '' ?>">
    <td>
      <?php if ($b['gambar']): ?>
        <img src="<?= BASE_URL . e($b['gambar']) ?>" style="width:56px;height:56px;object-fit:cover" class="rounded">
      <?php else: ?>
        <span class="text-muted small">tanpa foto</span>
      <?php endif; ?>
    </td>
    <td><?= e($b['nama']) ?><div class="small text-muted"><?= e($b['merk']) ?></div></td>
    <td><?= e($b['nama_kategori']) ?></td>
    <td><?= rupiah($b['harga_beli']) ?></td>
    <td><strong><?= rupiah($b['harga_jual']) ?></strong></td>
    <td>
      <?= (int)$b['stok'] ?>
      <?php if ($b['stok'] <= $b['min_stok']): ?>
        <span class="badge bg-warning text-dark">menipis</span>
        <?php if ($b['stok'] <= 0): ?><span class="badge bg-danger">habis</span><?php endif; ?>
      <?php endif; ?>
    </td>
    <?php if ($isAdmin): ?>
    <td>
      <a class="btn btn-sm btn-outline-primary" href="barang.php?edit=<?= (int)$b['id'] ?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Hapus <?= e($b['nama']) ?>?')">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="hapus">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <button class="btn btn-sm btn-outline-danger">Hapus</button>
      </form>
    </td>
    <?php endif; ?>
  </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
