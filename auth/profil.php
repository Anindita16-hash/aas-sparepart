<?php
/* ============================================================
   PROFIL (semua role)
   Fitur:
   - lihat & ubah data diri: nama, no HP, alamat
   - upload / ganti FOTO PROFIL
   (Tabel users kolom `foto` menyimpan path file, contoh:
    "uploads/profil/profil_a1b2c3.png" yang diletakkan pada disk
    di folder: web-uas/uploads/profil/)
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_login();

$u     = user();
$error = '';
$ok    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    $nama    = trim($_POST['nama'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $foto    = null;

    /* Upload foto profil (boleh dikosongkan) */
    if (!empty($_FILES['foto']['name'])) {
        $foto = upload_gambar($_FILES['foto'], 'profil');
        if (!$foto) {
            $error = 'Gagal upload foto. Gunakan file JPG/PNG/WEBP maksimal 2 MB.';
        }
    }

    if (!$error && $nama !== '') {
        try {
            if ($foto) {
                // ganti foto juga
                $stmt = db()->prepare(
                    'UPDATE users SET nama=?, no_telp=?, alamat=?, foto=? WHERE id=?'
                );
                $stmt->execute([$nama, $no_telp, $alamat, $foto, $u['id']]);
                $_SESSION['foto'] = $foto; // perbarui data session
            } else {
                $stmt = db()->prepare(
                    'UPDATE users SET nama=?, no_telp=?, alamat=? WHERE id=?'
                );
                $stmt->execute([$nama, $no_telp, $alamat, $u['id']]);
            }
            $_SESSION['nama'] = $nama;
            $ok = 'Profil berhasil disimpan.';
            $u['foto'] = $_SESSION['foto'];
        } catch (PDOException $e) {
            // ADVANCED: pesan error generik. Jangan tampilkan detail error
            // database ke user (bisa malah bocor informasi sistem).
            $error = 'Terjadi kesalahan saat menyimpan.';
        }
    } elseif (!$error) {
        $error = 'Nama tidak boleh kosong.';
    }
}

// ambil data terbaru dari DB (alamat curent setelah tombol simpan)
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$u['id']]);
$data = $stmt->fetch();

$judul = 'Profil Saya';
require __DIR__ . '/../includes/header.php';
?>

<h3>Profil Saya</h3>

<?php if ($ok):    ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <div class="text-center mb-3">
      <?php if ($data['foto']): ?>
        <!-- tampilkan foto profil -->
        <img src="<?= BASE_URL . e($data['foto']) ?>" class="rounded-circle" style="width:110px;height:110px;object-fit:cover">
      <?php else: ?>
        <!-- belum punya foto -> tampil huruf pertama nama -->
        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center"
             style="width:110px;height:110px;font-size:44px">
          <?= e(mb_strtoupper(mb_substr($data['nama'], 0, 1))) ?>
        </div>
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() /* ADVANCED: semua form POST wajib berisi token ini */ ?>

      <div class="mb-2"><label class="form-label">Nama</label>
        <input name="nama" class="form-control" value="<?= e($data['nama']) ?>" required></div>

      <div class="mb-2"><label class="form-label">Email (tidak bisa diubah)</label>
        <input class="form-control" value="<?= e($data['email']) ?>" disabled></div>

      <div class="mb-2"><label class="form-label">No. HP</label>
        <input name="no_telp" class="form-control" value="<?= e($data['no_telp']) ?>"></div>

      <div class="mb-2"><label class="form-label">Alamat</label>
        <textarea name="alamat" class="form-control" rows="2"><?= e($data['alamat']) ?></textarea></div>

      <div class="mb-3"><label class="form-label">Ganti Foto Profil (JPG/PNG/WEBP maks 2 MB)</label>
        <input type="file" name="foto" accept="image/*" class="form-control"></div>

      <button class="btn btn-primary">Simpan</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
