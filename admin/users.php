<?php
/* ============================================================
   MASTER USER (CRUD) — hanya admin
   Fitur:
   - daftar semua user
   - tambah user (role bisa admin/pegawai/customer)
   - edit user (password opsional, role, dsb)
   - hapus user
   - upload foto profil user dari halaman admin
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('admin');

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';

    /* ---------------- TAMBAH USER ---------------- */
    if ($aksi === 'tambah') {
        $nama     = trim($_POST['nama'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';
        $no_telp  = trim($_POST['no_telp'] ?? '');
        $alamat   = trim($_POST['alamat'] ?? '');

        // role harus termasuk daftar yang sah, jadi input "role" tidak bisa
        // diisi sembarang nilai dari luar form
        $role_ok = ['admin', 'pegawai', 'customer'];
        $valid = $nama !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
              && strlen($password) >= 6 && in_array($role, $role_ok, true);

        if (!$valid) {
            $pesan = ['danger', 'Data tidak lengkap / email tidak valid / password < 6 karakter.'];
        } else {
            try {
                $foto = upload_gambar($_FILES['foto'] ?? null, 'profil');
                $stmt = db()->prepare(
                    'INSERT INTO users (nama,email,password,role,no_telp,alamat,foto)
                     VALUES (?,?,?,?,?,?,?)'
                );
                // password disimpan sebagai hash bcrypt
                $stmt->execute([$nama,$email,password_hash($password,PASSWORD_DEFAULT),
                                $role,$no_telp,$alamat,$foto]);
                $pesan = ['success', 'User baru berhasil ditambahkan.'];
            } catch (PDOException $e) {
                $pesan = ['danger', 'Gagal: email sudah dipakai.'];
            }
        }
    }

    /* ---------------- HAPUS USER ---------------- */
    if ($aksi === 'hapus') {
        try {
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([(int)$_POST['id']]);
            $pesan = ['success', 'User dihapus.'];
        } catch (PDOException $e) {
            $pesan = ['danger', 'Tidak bisa dihapus (mungkin dipakai transaksi).'];
        }
    }
}

/* ---------------- EDIT USER (via GET form) ---------------- */
$edit = null;
if (isset($_GET['edit'])) {
    // CAST int: pastikan nilai ?edit= benar-benar angka
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    cek_csrf();
    $id    = (int)$_POST['id'];
    $nama  = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $role  = $_POST['role'];
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_ok = ['admin','pegawai','customer'];

    if ($nama === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || !in_array($role,$role_ok,true)) {
        $pesan = ['danger', 'Data edit tidak valid.'];
    } else {
        try {
            $foto = upload_gambar($_FILES['foto'] ?? null, 'profil');

            $sql = 'UPDATE users SET nama=?, email=?, role=?, no_telp=?, alamat=?';
            $par = [$nama,$email,$role,$no_telp,$alamat];

            // password baru diisi -> ikut diupdate (hash ulang)
            if ($password !== '') {
                $sql .= ', password=?';
                $par[] = password_hash($password, PASSWORD_DEFAULT);
            }
            if ($foto) {
                $sql .= ', foto=?';
                $par[] = $foto;
            }
            $sql .= ' WHERE id=?';
            $par[] = $id;

            db()->prepare($sql)->execute($par);
            $pesan = ['success', 'User berhasil diubah.'];
            $edit = null;
        } catch (PDOException $e) {
            $pesan = ['danger', 'Gagal update: email mungkin sudah dipakai.'];
        }
    }
}

/* daftar user tampil */
$daftar = db()->query('SELECT * FROM users ORDER BY id')->fetchAll();

$judul = 'Master User';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Master User</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<!-- ---------------- FORM (tambah / edit) ---------------- -->
<div class="card mb-4">
  <div class="card-header">
    <?= $edit ? 'Edit User: ' . e($edit['nama']) : 'Tambah User Baru' ?>
  </div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $edit ? 'edit' : 'tambah' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

      <div class="col-md-4"><label class="form-label">Nama</label>
        <input name="nama" class="form-control" required value="<?= e($edit['nama'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required value="<?= e($edit['email'] ?? '') ?>"></div>
      <div class="col-md-2"><label class="form-label">Role</label>
        <select name="role" class="form-select">
          <?php foreach (['customer','pegawai','admin'] as $r): ?>
            <option value="<?= $r ?>" <?= ($edit['role'] ?? 'customer') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label">Password <?= $edit ? '(kosongkan kalau tidak diganti)' : '(min 6 karakter)' ?></label>
        <input type="password" name="password" class="form-control" <?= $edit ? '' : 'required minlength="6"' ?>></div>
      <div class="col-md-3"><label class="form-label">No. HP</label>
        <input name="no_telp" class="form-control" value="<?= e($edit['no_telp'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Alamat</label>
        <input name="alamat" class="form-control" value="<?= e($edit['alamat'] ?? '') ?>"></div>
      <div class="col-md-2"><label class="form-label">Foto (JPG/PNG maks 2MB)</label>
        <input type="file" name="foto" accept="image/*" class="form-control"></div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary me-1"><?= $edit ? 'Simpan' : 'Tambah' ?></button>
        <?php if ($edit): ?><a href="users.php" class="btn btn-secondary">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ---------------- TABEL DAFTAR USER ---------------- -->
<table class="table table-hover bg-white">
  <tr class="table-dark">
    <th>ID</th><th>Nama</th><th>Email</th><th>Role</th><th>HP</th><th>Foto</th><th>Aksi</th>
  </tr>
  <?php foreach ($daftar as $u): ?>
  <tr>
    <td><?= (int)$u['id'] ?></td>
    <td><?= e($u['nama']) ?></td>
    <td><?= e($u['email']) ?></td>
    <td><span class="badge bg-<?= $u['role']==='admin'?'danger':($u['role']==='pegawai'?'warning':'success') ?>">
        <?= e($u['role']) ?></span></td>
    <td><?= e($u['no_telp']) ?></td>
    <td><?php if ($u['foto']): ?>
        <img src="<?= BASE_URL . e($u['foto']) ?>" style="width:36px;height:36px;object-fit:cover" class="rounded-circle">
        <?php endif; ?></td>
    <td>
      <a class="btn btn-sm btn-outline-primary" href="users.php?edit=<?= (int)$u['id'] ?>">Edit</a>
      <!-- hapus pakai form+POST+csrf supaya tidak bisa dijalankan lewat link (GET) -->
      <form method="post" class="d-inline" onsubmit="return confirm('Yakin hapus <?= e($u['nama']) ?>?')">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="hapus">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <button class="btn btn-sm btn-outline-danger">Hapus</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/../includes/footer.php'; ?>
