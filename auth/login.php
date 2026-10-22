<?php
/* ============================================================
   LOGIN
   Fitur: form email+password, cocokkan ke tabel users dengan
   password_verify(). Setelah sukses, data login disimpan di session.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

// Sudah login? langsung lempar ke halaman utama.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADVANCED (OWASP A07): cek CSRF dulu, barulah proses login
    cek_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    /* ADVANCED (OWASP A03 - SQL Injection):
       Gunakan prepared statement. Input user otomatis diamankan oleh PDO,
       TIDAK digabung langsung ke dalam string SQL. */
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    /* ADVANCED (OWASP A07):
       - password diverifikasi pakai password_verify(), bukan dibandingkan
         sebagai teks biasa
       - pesan error dibuat sama persis untuk "email tidak ada" dan
         "password salah", supaya penyusup tidak bisa mengintip
         email mana yang terdaftar (user enumeration) */
    if (!$user || !password_verify($password, $user['password'])) {
        $error = 'Email atau password salah.';
    } else {
        // Login berhasil: simpan ke session
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['nama']     = $user['nama'];
        $_SESSION['role']     = $user['role'];
        $_SESSION['foto']     = $user['foto'];
        // ADVANCED: session_regenerate_id mencegah serangan "session fixation"
        session_regenerate_id(true);

        $tujuan = [
            'admin'    => 'admin/dashboard.php',
            'pegawai'  => 'pegawai/kasir.php',
            'customer' => 'customer/katalog.php',
        ];
        header('Location: ' . BASE_URL . ($tujuan[$user['role']] ?? 'index.php'));
        exit;
    }
}

$judul = 'Login';
require __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — <?= e(info_toko()['nama_toko'] ?? '') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center" style="min-height:100vh">
<div class="container" style="max-width:420px">
  <div class="card shadow">
    <div class="card-body p-4">
      <h4 class="text-center mb-3">Login <?= e(info_toko()['nama_toko'] ?? '') ?></h4>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() /* ADVANCED: token anti-CSRF */ ?>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Login</button>
      </form>

      <p class="text-center mt-3 mb-0 small">
        Belum punya akun? <a href="register.php">Daftar di sini</a> (untuk customer)
      </p>
    </div>
  </div>
</div>
</body>
</html>
