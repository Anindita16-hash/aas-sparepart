<?php
/* ============================================================
   SETTING TOKO — admin
   Fitur: nama toko, alamat, telepon, footer yang tampil di struk.
   Disimpan di tabel setting_toko (pasangan kunci -> nilai).
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('admin');

$pesan = '';
$kunci = ['nama_toko', 'alamat', 'telepon', 'footer_struk'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    /* simpan tiap setting: kalau barisnya sudah ada -> update */
    $stmt_cek  = db()->prepare('SELECT kunci FROM setting_toko WHERE kunci = ?');
    $stmt_up   = db()->prepare('UPDATE setting_toko SET nilai = ? WHERE kunci = ?');
    $stmt_ins  = db()->prepare('INSERT INTO setting_toko (kunci, nilai) VALUES (?, ?)');

    foreach ($kunci as $k) {
        $nilai = trim($_POST[$k] ?? '');
        $stmt_cek->execute([$k]);
        if ($stmt_cek->fetch()) {
            $stmt_up->execute([$nilai, $k]);
        } else {
            $stmt_ins->execute([$k, $nilai]);
        }
    }
    $pesan = 'Setting toko tersimpan.';
}

$toko = info_toko();
$judul = 'Setting Toko';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Setting Toko</h3>

<?php if ($pesan): ?><div class="alert alert-success"><?= e($pesan) ?></div><?php endif; ?>

<div class="card" style="max-width:640px">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label">Nama Toko</label>
        <input name="nama_toko" class="form-control" value="<?= e($toko['nama_toko'] ?? '') ?>" required></div>
      <div class="mb-2"><label class="form-label">Alamat</label>
        <input name="alamat" class="form-control" value="<?= e($toko['alamat'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label">Telepon</label>
        <input name="telepon" class="form-control" value="<?= e($toko['telepon'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Kalimat di struk / footer</label>
        <textarea name="footer_struk" class="form-control" rows="2"><?= e($toko['footer_struk'] ?? '') ?></textarea></div>
      <button class="btn btn-primary">Simpan</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
