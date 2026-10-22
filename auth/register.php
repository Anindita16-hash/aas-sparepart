<?php
/* ============================================================
   REGISTER (khusus customer)
   Fitur: daftar akun baru. Role selalu "customer" — tidak ada
   pilihan role di form, supaya orang luar tidak bisa mendaftar
   sebagai admin/pegawai (OWASP A01).
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf(); /* ADVANCED: proteksi CSRF */

    $nama     = trim($_POST['nama'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $no_telp  = trim($_POST['no_telp'] ?? '');
    $alamat   = trim($_POST['alamat'] ?? '');

    // validasi sederhana
    if ($nama === '' || $email === '' || strlen($password) < 6) {
        $error = 'Nama, email wajib diisi dan password minimal 6 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // cek email sudah dipakai?
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar.';
        } else {
            /* ADVANCED (OWASP A07): password DISIMPAN sebagai hash
               bcrypt, prinsipnya: tidak pernah menyimpan password asli
               di database. */
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = db()->prepare(
                'INSERT INTO users (nama, email, password, role, no_telp, alamat)
                 VALUES (?, ?, ?, "customer", ?, ?)'
            );
            $stmt->execute([$nama, $email, $hash, $no_telp, $alamat]);

            // sukses -> langsung login
            $_SESSION['user_id'] = db()->lastInsertId();
            $_SESSION['nama']    = $nama;
            $_SESSION['role']    = 'customer';
            $_SESSION['foto']    = null;
            session_regenerate_id(true);

            header('Location: ' . BASE_URL . 'customer/katalog.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container" style="max-width:480px" >
  <div class="card mt-5">
    <div class="card-body p-4">
      <h4 class="mb-3">Daftar Akun Customer</h4>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-2"><label class="form-label">Nama <span class="text-danger">*</span></label>
          <input name="nama" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Password (min 6 karakter) <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" required minlength="6"></div>
        <div class="mb-2"><label class="form-label">No. HP</label>
          <input name="no_telp" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Alamat</label>
          <textarea name="alamat" class="form-control" rows="2"></textarea></div>
        <button class="btn btn-success w-100">Daftar</button>
      </form>

      <p class="text-center mt-3 mb-0 small">
        Sudah punya akun? <a href="login.php">Login</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
