# Toko Sparepart & Aksesoris Mobil — Web App (PHP + phpMyAdmin)

Aplikasi sederhana untuk latihan OWASP hardening. PHP + MySQL/phpMyAdmin, Bootstrap CDN.

## Cara Install

1. Jalankan **Apache + MySQL** (XAMPP).
2. Buka phpMyAdmin → **Import** → pilih file `database.sql`.
   - Otomatis membuat database `toko_sparepart`, tabel, dan data awal.
   - Kolom `kunci`/`nilai` di tabel `setting_toko` menyimpan pengaturan toko.
3. Letakkan folder ini di `htdocs` (ya, folder ini sendiri), lalu buka
   `http://localhost/web-uas/` (atau nama folder kamu).
4. Folder **`uploads/`** (barang, profil, bukti) dibuat otomatis saat upload pertama.
   Sudah disiapkan `.htaccess` untuk mematikan eksekusi PHP di dalam folder `uploads/`.

## Akun Awal (password disimpan sebagai hash bcrypt)

| Role | Email | Password |
|---|---|---|
| Administrator | admin@toko.com | admin123 |
| Pegawai | pegawai@toko.com | pegawai123 |
| Customer | daftar sendiri via menu **Register** | |

## Struktur

```
web-uas/
├── config/db.php      koneksi + fungsi bantuan (login, csrf, upload, dsb)
├── includes/          header (navbar per role) + footer
├── auth/              login, register, logout, profil (foto profile di sini)
├── admin/             dashboard, master User/Kategori/Barang/Supplier,
│                      Laporan penjualan (cetak PDF), Setting toko
├── pegawai/           kasir (tunai), pembelian/restock, proses PO, verifikasi bayar
├── customer/          katalog, keranjang+checkout, PO barang, riwayat+upload bukti bayar
├── gateway/           PAYMENT GATEWAY MOCKUP: mock-gateway.php + callback.php
└── uploads/           barang/, profil/, bukti/
```

## Alur Bisnis

1. Customer belanja di `customer/katalog.php` → keranjang → **Checkout** →
   stok berkurang, invoice status **belum** (metode transfer).
2. Customer upload bukti transfer lewat **Riwayat & Bayar** → status
   **menunggu_verifikasi**.
3. Pegawai menu **Verifikasi Pembayaran** → lihat bukti → **Konfirmasi Lunas**.
4. Stok kosong → customer buat **PO** → pegawai **Pesan-PO** klik *Konfirmasi* →
   pegawai menu **Pembelian** pilih PO tersebut → stok bertambah, PO **siap**,
   invoice customer otomatis dibuat → customer bayar seperti langkah 2–3 →
   PO **selesai**.
5. Admin menu **Laporan** → filter periode → **Cetak / Simpan PDF** (dialog print browser).

## Payment Gateway (mockup)

Jalur pembayaran "gateway" sudah disiapkan tapi belum dipasang di UI:

- Kolom `penjualan.gateway_ref`, metode `"gateway"` sudah ada di skema DB.
- `gateway/mock-gateway.php` = halaman pembayaran simulasi (pura-pura pihak ketiga).
- `gateway/callback.php` = notifikasi pembayaran, mengisi `gateway_ref` +
  status_bayar `menunggu_verifikasi`.
- Titik masuk di aplikasi: tabel riwayat customer (`customer/riwayat.php`) —
  cari komentar **CATATAN INTEGRASI PAYMENT GATEWAY** untuk menambah tombolnya:

```php
<!-- contoh tombol, taruh di form kolom pembayaran riwayat -->
<a class="btn btn-outline-primary btn-sm"
   href="<?= BASE_URL ?>gateway/mock-gateway.php?invoice=<?= urlencode($n['no_invoice']) ?>">
   Bayar via Payment Gateway
</a>
```

⚠️ VERSI MOCKUP tidak ada verifikasi signature — TIDAK untuk produksi.
Lihat komentar **ADVANCED** di `gateway/callback.php` untuk cara amannya.

## Catatan Keamanan yang sudah diterapkan (OWASP Top 10 2025)

- `A03 Injection` — semua query SQL pakai PDO **prepared statement**.
- `A03 Injection (XSS)` — output lewat fungsi `e()` (htmlspecialchars).
- `A07 Auth Failures` — password `password_hash()`/`password_verify()`,
  pesan error login identik (anti user-enumeration), session_regenerate_id.
- `A01 Broken Access Control` — `cek_login()`/`cek_role()` di tiap halaman,
  pengecekan kepemilikan data di query (tambah: customer hanya bisa lihat
  / upload untuk invoice miliknya).
- `A05 Security Misconfig / Upload` — validasi ekstensi + MIME asli (finfo),
  ukuran maks 2 MB, nama file random, PHP tidak boleh jalan di `uploads/`.
- `CSRF` — token di semua form POST (`csrf_field()` + `cek_csrf()`).
- **ADVANCED**: database transaction (`beginTransaction`) di kasir/checkout/pembelian
  supaya stok dan pembukuan selalu konsisten.
