<?php
/* ============================================================
   KONFIGURASI & FUNGSI BANTU
   File ini di-require oleh semua halaman. Isinya:
   - koneksi database (PDO)
   - session login
   - fungsi bantuan: eskape output, cek login/role, CSRF,
     upload gambar, dsb.
   ============================================================ */

// Jalankan session (dipakai untuk login, keranjang, token CSRF)
session_start();

/* ------------------------------------------------------------
   KONEKSI DATABASE
   ADVANCED:
   - PDO + prepared statement dipakai supaya aman dari SQL Injection
     (OWASP A03). Jangan pernah gabung input user langsung ke string
     query, contoh buruk: "SELECT * FROM barang WHERE id = $id"
     BENAR: query + bindParam (lihat contoh dibawah).
   - Sesuaikan username/password bila XAMPP kamu beda
     (default XAMPP: user "root", password kosong).
------------------------------------------------------------ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'toko_sparepart');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $DB = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage()
        . ' — pastikan database sudah diimpor lewat database.sql');
}

/* Fungsi akses koneksi dari halaman mana pun */
function db() {
    global $DB;
    return $DB;
}

/* ------------------------------------------------------------
   URL DASAR APLIKASI
   Dipakai untuk membuat link gambar/dll, contoh:
   http://localhost/web-uas/uploads/barang/abc.jpg
------------------------------------------------------------ */
define('ROOT_PATH', dirname(__DIR__)); // folder web-uas di disk
define('BASE_URL', rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/');

/* ------------------------------------------------------------
   Fungsi e() : amankan output sebelum ditampilkan (anti XSS / OWASP A03).
   Selalu pakai e() saat mencetak data dari database/input user.
------------------------------------------------------------ */
function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

/* Format angka jadi rupiah, mis. 55000 -> "Rp 55.000" */
function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/* ------------------------------------------------------------
   LOGIN & ROLE (mencegah Broken Access Control / OWASP A01)
   - cek_login()  : halaman hanya untuk yang sudah login
   - cek_role()   : halaman hanya untuk role tertentu
   Setiap halaman admin/pegawai/customer WAJIB memanggil ini di awal.
------------------------------------------------------------ */
function user() {
    return [
        'id'    => $_SESSION['user_id']   ?? null,
        'nama'  => $_SESSION['nama']      ?? null,
        'role'  => $_SESSION['role']      ?? null,
        'foto'  => $_SESSION['foto']      ?? null,
    ];
}

function cek_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

function cek_role($role_diizinkan) {
    cek_login();
    if (!in_array($_SESSION['role'], (array)$role_diizinkan, true)) {
        http_response_code(403);
        die('Akses ditolak. Halaman ini bukan untuk role kamu.');
    }
}

/* ------------------------------------------------------------
   CSRF TOKEN (OWASP - anti serangan CSRF)
   ADVANCED:
   Tiap form POST menyisipkan token rahasia. Saat form dikirim,
   token di form dicocokkan dengan token di session. Beda = tolak.
   Dipakai: <?= csrf_field() ?> di dalam <form method="post">
------------------------------------------------------------ */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function cek_csrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        die('Token salah! Form dikirim dua kali atau halaman terbuka terlalu lama, silakan muat ulang.');
    }
}

/* ------------------------------------------------------------
   UPLOAD GAMBAR/FOTO (OWASP: Unrestricted File Upload)
   ADVANCED:
   Yang dicek di sini supaya upload aman:
   1. Ekstensi whitelist saja (jpg/png/webp) - cek dari nama file
   2. MIME asli file dicek pakai finfo, bukan sekadar nama file
      (file .php yang ganti nama jadi .jpg akan tetap tertolak kalau
       isinya bukan gambar)
   3. Nama file dirandom supaya tidak bisa menebak/menimpa file lain
   4. Folder uploads tidak menjalankan PHP (lihat catatan .htaccess)
   $folder = "barang" | "profil" | "bukti"
   Return nama file yang tersimpan, atau false kalau gagal.
------------------------------------------------------------ */
function upload_gambar($file, $folder) {
    // Belum pilih file -> dianggap tidak upload, return false (bukan error)
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return false;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // 1. cek ekstensi
    $ekstensi = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $boleh    = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ekstensi, $boleh, true)) {
        return false;
    }

    // 2. cek tipe file asli (bukan hanya nama)
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $mime_boleh = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $mime_boleh, true)) {
        return false;
    }

    // 3. batasi ukuran 2 MB
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }

    // 4. subfolder uploads (buat otomatis kalau belum ada)
    $dir = ROOT_PATH . '/uploads/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    // 5. nama file random biar unik
    $nama_baru = $folder . '_' . bin2hex(random_bytes(8)) . '.' . $ekstensi;

    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $nama_baru)) {
        return 'uploads/' . $folder . '/' . $nama_baru; // disimpan relatif dari root
    }
    return false;
}

/* ------------------------------------------------------------
   Nomor dokumen otomatis: INV-20261022-172345 (dipakai invoice/faktur/PO)
------------------------------------------------------------ */
function nomor_dokumen($awalan, $id_terakhir) {
    $urut = str_pad(($id_terakhir + 1), 4, '0', STR_PAD_LEFT);
    return $awalan . '-' . date('Ymd') . '-' . $urut;
}

/* ------------------------------------------------------------
   SETTING TOKO
   Ambil semua pengaturan (nama toko, dll) dalam satu array.
   Contoh pakai: $toko['nama_toko']
------------------------------------------------------------ */
function info_toko() {
    static $hasil = null;
    if ($hasil === null) {
        $hasil = [];
        foreach (db()->query('SELECT kunci, nilai FROM setting_toko') as $r) {
            $hasil[$r['kunci']] = $r['nilai'];
        }
    }
    return $hasil;
}
