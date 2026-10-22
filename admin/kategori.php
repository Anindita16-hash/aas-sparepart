<?php
/* ============================================================
   MASTER KATEGORI (CRUD) — hanya admin
   Fitur: tambah, edit, hapus kategori barang (label sederhana).
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('admin');

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $nama = trim($_POST['nama'] ?? '');
        if ($nama === '') {
            $pesan = ['danger', 'Nama kategori kosong.'];
        } elseif ($aksi === 'tambah') {
            db()->prepare('INSERT INTO kategori (nama) VALUES (?)')->execute([$nama]);
            $pesan = ['success', 'Kategori ditambahkan.'];
        } else {
            db()->prepare('UPDATE kategori SET nama=? WHERE id=?')
                 ->execute([$nama, (int)$_POST['id']]);
            $pesan = ['success', 'Kategori diubah.'];
        }
    }

    if ($aksi === 'hapus') {
        try {
            db()->prepare('DELETE FROM kategori WHERE id=?')->execute([(int)$_POST['id']]);
            $pesan = ['success', 'Kategori dihapus.'];
        } catch (PDOException $e) {
            $pesan = ['danger', 'Tidak bisa dihapus, masih dipakai barang.'];
        }
    }
}

/* edit? ambil data 1 kategori */
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM kategori WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

$daftar = db()->query('SELECT * FROM kategori ORDER BY id')->fetchAll();

$judul = 'Master Kategori';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Master Kategori</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<div class="card mb-4" style="max-width:420px">
  <div class="card-body">
    <form method="post" class="d-flex gap-2">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $edit ? 'edit' : 'tambah' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <input name="nama" class="form-control" placeholder="Nama kategori" required value="<?= e($edit['nama'] ?? '') ?>">
      <button class="btn btn-primary"><?= $edit ? 'Simpan' : 'Tambah' ?></button>
      <?php if ($edit): ?><a href="kategori.php" class="btn btn-secondary">Batal</a><?php endif; ?>
    </form>
  </div>
</div>

<table class="table table-hover bg-white" style="max-width:600px">
  <tr class="table-dark"><th>ID</th><th>Nama Kategori</th><th style="width:180px">Aksi</th></tr>
  <?php foreach ($daftar as $k): ?>
  <tr>
    <td><?= (int)$k['id'] ?></td>
    <td><?= e($k['nama']) ?></td>
    <td>
      <a class="btn btn-sm btn-outline-primary" href="kategori.php?edit=<?= (int)$k['id'] ?>">Edit</a>
      <form method="post" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="hapus">
        <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
        <button class="btn btn-sm btn-outline-danger">Hapus</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
