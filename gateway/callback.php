<?php
/* ============================================================
   PAYMENT GATEWAY — CALLBACK (MOCKUP)
   ============================================================
   Callback = notifikasi dari gateway ke aplikasi toko:
   "invoice INV-xxx sudah dibayar, ref transaksi MOCK-xxxx".

   Alur dalam file ini (VERSI MOCKUP, disederhanakan):
   1. gateway (dalam hal ini halaman pratinjau) memanggil:
      callback.php?invoice=INV-xxxx&ref=MOCKxxxx&status=success
   2. file ini mencocokkan invoice, lalu:
      - status_bayar  -> "menunggu_verifikasi"
      - metode        -> "gateway"
      - gateway_ref   -> MOCKxxxx
   3. Pegawai mengeceknya di menu "Verifikasi Pembayaran"
      dan menetapkan status jadi LUNAS/bersambung.

   ============================================================
   ADVANCED — WAJIB dipahami saat upgrade ke gateway nyata:
   1. Callback di sini TERBUKA dan TIDAK ADA VERIFIKASI SIGNATURE.
      Artinya siapa pun bisa membukanya lewat browser. Ini sengaja
      dibuat sederhana untuk mockup, TIDAK BOLEH untuk produksi.
   2. Untuk produksi, tambahkan:
      - verifikasi signature (HMAC SHA256 dengan secret key), atau
      - cek status transaksi langsung ke server gateway
        ("verify transaction" / "capture") sebelum menandai paid.
   3. Gunakan HTTPS, dan cukup tanggapi "200 OK" setelah update database
      — gateway nyata akan MENGULANG callback jika responmu gagal.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('customer'); // mockup: hanya user milik sendiri yang bisa diproses

$no_invoice = $_GET['invoice'] ?? '';
$ref        = $_GET['ref']     ?? '';
$status     = $_GET['status']  ?? '';

/* validasi input sederhana */
if ($no_invoice === '' || $ref === '') {
    die('Parameter callback tidak lengkap.');
}

/* pastikan invoice milik customer yang login & statusnya belum dibayar
   ADVANCED (OWASP A01): pengecekan kepemilikan dalam query, bukan UI saja */
$stmt = db()->prepare(
    'SELECT id, status_bayar FROM penjualan
     WHERE no_invoice = ? AND pembeli_id = ?'
);
$stmt->execute([$no_invoice, user()['id']]);
$inv = $stmt->fetch();

if (!$inv) {
    die('Invoice tidak ada / bukan milikmu.');
}

if ($status === 'success' && $inv['status_bayar'] !== 'lunas') {
    // update pembayaran: jadi metode gateway + menunggu verifikasi
    db()->prepare(
        'UPDATE penjualan
         SET status_bayar = "menunggu_verifikasi",
             metode = "gateway",
             gateway_ref = ?
         WHERE id = ?'
    )->execute([$ref, $inv['id']]);
}

/* selesai -> kembali ke aplikasi toko */
header('Location: ' . BASE_URL . 'customer/riwayat.php');
exit;
