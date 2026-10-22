<?php
/* ============================================================
   MASTER SUPPLIER (CRUD) — admin
   Fitur: daftar supplier yang bisa dipilih pegawai saat pembelian.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('admin');

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nama    = trim($_POST['nama'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');

        if ($nama === '') {
            $pesan = ['danger', 'Nama supplier wajib diisi.'];
        } elseif ($aksi === 'tambah') {
            db()->prepare('INSERT INTO supplier (nama,no_telp,alamat) VALUES (?,?,?)')
                 ->execute([$nama, $no_telp, $alamat]);
            $pesan = ['success', 'Supplier ditambahkan.'];
        } else {
            db()->prepare('UPDATE supplier SET nama=?, no_telp=?, alamat=? WHERE id=?')
                 ->execute([$nama, $no_telp, $alamat, (int)$_POST['id']]);
            $pesan = ['success', 'Supplier diubah.'];
        }
    }

    if ($aksi === 'hapus') {
        try {
            db()->prepare('DELETE FROM supplier WHERE id = ?')->execute([(int)$_POST['id']]);
            $pesan = ['success', 'Supplier dihapus.'];
        } catch (PDOException $e) {
            $pesan = ['danger', 'Tidak bisa dihapus, masih dipakai pembelian.'];
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM supplier WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

$daftar = db()->query('SELECT * FROM supplier ORDER BY id')->fetchAll();

$judul = 'Supplier';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Master Supplier</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-body">
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $edit ? 'edit' : 'tambah' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <div class="col-md-3"><input name="nama" class="form-control" placeholder="Nama supplier" required value="<?= e($edit['nama'] ?? '') ?>"></div>
      <div class="col-md-3"><input name="no_telp" class="form-control" placeholder="No. HP" value="<?= e($edit['no_telp'] ?? '') ?>"></div>
      <div class="col-md-4"><input name="alamat" class="form-control" placeholder="Alamat" value="<?= e($edit['alamat'] ?? '') ?>"></div>
      <div class="col-md-2">
        <button class="btn btn-primary me-1"><?= $edit ? 'Simpan' : 'Tambah' ?></button>
        <?php if ($edit): ?><a href="supplier.php" class="btn btn-secondary">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<table class="table table-hover bg-white">
  <tr class="table-dark"><th>ID</th><th>Nama</th><th>No. HP</th><th>Alamat</th><th style="width:170px">Aksi</th></tr>
  <?php foreach ($daftar as $s): ?>
  <tr>
    <td><?= (int)$s['id'] ?></td>
    <td><?= e($s['nama']) ?></td>
    <td><?= e($s['no_telp']) ?></td>
    <td><?= e($s['alamat']) ?></td>
    <td>
      <a class="btn btn-sm btn-outline-primary" href="supplier.php?edit=<?= (int)$s['id'] ?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Hapus supplier ini?')">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="hapus">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn btn-sm btn-outline-danger">Hapus</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
