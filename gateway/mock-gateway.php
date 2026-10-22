<?php
/* ============================================================
   PAYMENT GATEWAY — HALAMAN MOCKUP (FAKE)
   ============================================================
   Tujuan file ini: SIMULASI alur payment gateway nyata
   (contoh gateway nyata: Midtrans Snap, DOKU, Xendit)
   supaya kamu bisa menambah integrasinya belakangan.

   = ALUR MOCKUP =============================================
   1. Customer membuka "Riwayat & Bayar" (customer/riwayat.php)
      -> halaman itu sudah menyediakan tempat tombol
         "Bayar via Payment Gateway" (lihat komentar di sana).
   2. Tombol itu memanggil: mock-gateway.php?invoice=INV-xxxx
   3. Halaman ini MENIRU website pihak ketiga (di dunia nyata,
      ini benar-benar server bank/gateway, bukan aplikasi kita).
   4. Saat user mengklik "Bayar", kita REDIRECT ke
      callback.php?invoice=INV-xxxx&ref=MOCK-xxxx&status=success
   5. callback.php memvalidasi lalu meng-update status pembayaran
      invoice di database kita (gateway_ref terisi).
   ============================================================

   ADVANCED (penting untuk pembelajaran): di gateway NYATA, langkah
   nomor 5 tidak pernah sesederhana ini. Gateway memberi "secret key",
   dan callback selalu diikuti SIGNATURE digital (HMAC/SHA256) yang
   harus kamu verifikasi sebelum mempercayai data. Tanpa itu, orang
   bisa memanggil URL callback secara manual supaya invoice-nya jadi
   lunas padahal tidak benar-benar membayar!
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('customer');

$no_invoice = $_GET['invoice'] ?? '';
$stmt = db()->prepare(
    'SELECT * FROM penjualan WHERE no_invoice = ? AND pembeli_id = ?'
);
$stmt->execute([$no_invoice, user()['id']]);
$inv = $stmt->fetch();

if (!$inv) {
    die('Invoice tidak ditemukan / bukan milik kamu.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>PaymentGateway MOCKUP</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark">
<div class="container py-5" style="max-width:480px">
  <!-- ini "berpura-pura" menjadi website gateway pihak ketiga -->
  <div class="card text-bg-light">
    <div class="card-header text-bg-primary">
      <strong>PayGatewaySandbox</strong> <span class="badge bg-warning text-dark">MOCKUP</span>
    </div>
    <div class="card-body">
      <dl class="row small">
        <dt class="col-5">Invoice</dt><dd class="col-7"><?= e($inv['no_invoice']) ?></dd>
        <dt class="col-5">Total tagihan</dt><dd class="col-7"><?= rupiah($inv['total']) ?></dd>
        <dt class="col-5">Metode</dt><dd class="col-7">e-wallet (simulation)</dd>
      </dl>
      <div class="d-grid gap-2">
        <a class="btn btn-success"
           href="callback.php?invoice=<?= urlencode($inv['no_invoice']) ?>&ref=MOCK<?= strtoupper(bin2hex(random_bytes(4))) ?>&status=success">
           ✔ Bayar sekarang (simulasi sukses)
        </a>
        <a class="btn btn-outline-dark" href="<?= BASE_URL ?>customer/riwayat.php">Batal, kembali ke toko</a>
      </div>
      <hr>
      <p class="small text-muted mb-0">
        Di dunia nyata di sini user mengisi PIN / scan QR / login e-wallet.
        Setelah sukses, GATEWAY (bukan toko) yang mengirim callback ke toko.
      </p>
    </div>
  </div>
</div>
</body>
</html>
