-- ==============================================================
-- TOKO SPAREPART & AKSESORIS MOBIL - Skema Database
-- Cara pakai: buka phpMyAdmin -> klik "Import" -> pilih file ini
-- Database yang akan dibuat: toko_sparepart
--
-- CATATAN UNTUK PEMBAYARAN GATEWAY (mockup, opsional):
--   Tabel `penjualan` sudah disiapkan kolom `gateway_ref`.
--   Kolom ini dipakai nanti untuk menyimpan nomor transaksi
--   dari payment gateway (misal Midtrans) supaya status pembayaran
--   bisa di-update otomatis lewat "callback" gateway.
-- ==============================================================

CREATE DATABASE IF NOT EXISTS toko_sparepart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE toko_sparepart;

-- ---------------------------------------------------------------
-- 1. users : semua akun (admin, pegawai, customer)
--    - password TIDAK boleh disimpan teks biasa, pakai password_hash()
--      (hash di bawah didapat dari password, lihat seeder di bawah)
--    - foto = nama file foto profil, disimpan di folder uploads/profil/
-- ---------------------------------------------------------------
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','pegawai','customer') NOT NULL DEFAULT 'customer',
  no_telp VARCHAR(20),
  alamat TEXT,
  foto VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------
-- 2. kategori : master kategori barang
-- ---------------------------------------------------------------
CREATE TABLE kategori (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL
);

-- ---------------------------------------------------------------
-- 3. barang : master barang (kode otomatis pakai kolom `id`)
--    - gambar = nama file gambar, disimpan di folder uploads/barang/
--    - min_stok = batas minimal, kalau stok di bawah ini tampil "stok menipis"
-- ---------------------------------------------------------------
CREATE TABLE barang (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kategori_id INT,
  nama VARCHAR(150) NOT NULL,
  merk VARCHAR(100),
  harga_beli DECIMAL(12,2) DEFAULT 0,   -- modal beli dari supplier
  harga_jual DECIMAL(12,2) DEFAULT 0,   -- harga untuk customer
  stok INT DEFAULT 0,
  min_stok INT DEFAULT 5,
  gambar VARCHAR(255),
  deskripsi TEXT,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id)
);

-- ---------------------------------------------------------------
-- 4. supplier : data supplier untuk pembelian / restock
-- ---------------------------------------------------------------
CREATE TABLE supplier (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  no_telp VARCHAR(20),
  alamat TEXT
);

-- ---------------------------------------------------------------
-- 5. pemesanan_po : PO barang yang stoknya kosong, dibuat CUSTOMER
--    Alur status: pending -> dikonfirmasi -> (barang datang) -> siap -> selesai
-- ---------------------------------------------------------------
CREATE TABLE pemesanan_po (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode_po VARCHAR(30) NOT NULL,          -- contoh: PO-20260101-0001
  users_id INT NOT NULL,                 -- customer yang membuat PO
  status ENUM('pending','dikonfirmasi','diproses','siap','selesai','ditolak') DEFAULT 'pending',
  catatan TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (users_id) REFERENCES users(id)
);

-- detail barang-barang yang di-PO
CREATE TABLE po_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pemesanan_id INT NOT NULL,
  barang_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  harga_satuan DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (pemesanan_id) REFERENCES pemesanan_po(id),
  FOREIGN KEY (barang_id) REFERENCES barang(id)
);

-- ---------------------------------------------------------------
-- 6. pembelian : restock barang dari supplier, diinput PEGAWAI
--    - po_id diisi kalau pembelian ini untuk memenuhi PO customer
--    - setelah disimpan, stok barang otomatis bertambah
-- ---------------------------------------------------------------
CREATE TABLE pembelian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_faktur VARCHAR(30) NOT NULL,        -- contoh: FAK-20260101-0001
  users_id INT,                          -- pegawai yang menginput
  supplier_id INT,
  po_id INT,                             -- opsional: pembelian dari PO customer
  total DECIMAL(12,2) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (users_id) REFERENCES users(id),
  FOREIGN KEY (supplier_id) REFERENCES supplier(id)
);

CREATE TABLE pembelian_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pembelian_id INT NOT NULL,
  barang_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (pembelian_id) REFERENCES pembelian(id),
  FOREIGN KEY (barang_id) REFERENCES barang(id)
);

-- ---------------------------------------------------------------
-- 7. penjualan : bisa dari kasir (pegawai), order customer, atau PO
--    - metode 'tunai'   = kasir, langsung lunas
--    - metode 'transfer'= customer upload bukti -> pegawai verifikasi
--    - metode 'gateway' = untuk payment gateway mockup (nanti)
--    - status_bayar: belum -> menunggu_verifikasi -> lunas
--    - bukti_bayar = nama file bukti transfer, di uploads/bukti/
--    - gateway_ref = nomor transaksi dari payment gateway (mockup)
-- ---------------------------------------------------------------
CREATE TABLE penjualan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_invoice VARCHAR(30) NOT NULL,       -- contoh: INV-20260101-0001
  users_id INT,                          -- pegawai kasir (NULL kalau dari customer)
  pembeli_id INT,                        -- customer (NULL kalau kasir tanpa member)
  pemesanan_id INT,                      -- opsional: penjualan dari PO
  total DECIMAL(12,2) DEFAULT 0,
  metode ENUM('tunai','transfer','gateway') DEFAULT 'tunai',
  status_bayar ENUM('belum','menunggu_verifikasi','lunas') DEFAULT 'belum',
  bukti_bayar VARCHAR(255),
  gateway_ref VARCHAR(100) DEFAULT NULL, -- dipakai payment gateway mockup
  tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (users_id) REFERENCES users(id),
  FOREIGN KEY (pembeli_id) REFERENCES users(id),
  FOREIGN KEY (pemesanan_id) REFERENCES pemesanan_po(id)
);

CREATE TABLE penjualan_detail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  penjualan_id INT NOT NULL,
  barang_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  harga_satuan DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (penjualan_id) REFERENCES penjualan(id),
  FOREIGN KEY (barang_id) REFERENCES barang(id)
);

-- ---------------------------------------------------------------
-- 8. setting_toko : pengaturan nama toko dll (dikelola admin)
-- ---------------------------------------------------------------
CREATE TABLE setting_toko (
  kunci VARCHAR(50) PRIMARY KEY,
  nilai TEXT
);

-- ==============================================================
-- DATA AWAL (SEED)
-- Password semua akun berisi hash bcrypt, BUKAN teks asli.
--   admin@toko.com    : admin123
--   pegawai@toko.com  : pegawai123
--   customer dibuat lewat menu Register.
-- ==============================================================

-- hash bcrypt dari "admin123"
-- (kompatibel dengan password_verify() di PHP)
-- ADVANCED: bcrypt = one-way hashing, gabisa dibalik. Bener standar minimal buat password.
INSERT INTO users (nama, email, password, role) VALUES
('Administrator', 'admin@toko.com',    '$2y$10$owp3SwbP084iyxgUqe8dMOwJ6/AutD9yClTx8z8ZF1CT2EwNJWLoy', 'admin'),
('Pegawai Toko',  'pegawai@toko.com',  '$2y$10$SzpEzzpHbSwRttYW5hbPaOFklqclJEukl6ca2yZ6emxdIu98YtZT.', 'pegawai');

INSERT INTO kategori (nama) VALUES
('Mesin'), ('Kelistrikan'), ('Ban & Velg'), ('Eksterior'), ('Interior'), ('Pelumas');

INSERT INTO supplier (nama, no_telp, alamat) VALUES
('PT Sumber Part Jaya', '021-5550101', 'Jl. Industri Raya 12, Jakarta'),
('CV Otomasi Kencana',  '022-5550102', 'Jl. Merdeka 45, Bandung');

INSERT INTO barang (kategori_id, nama, merk, harga_beli, harga_jual, stok, min_stok, deskripsi) VALUES
(1, 'Busi Iridium', 'NGK', 35000, 55000, 48, 10, 'Busi iridium tahan lama, cocok semua mobil jepang.'),
(2, 'Aki MF 12V 45Ah', 'Yushi', 480000, 620000, 12, 4, 'Aki bebas perawatan, garansi 6 bulan.'),
(3, 'Ban 195/65 R15', 'Dunlop', 620000, 780000, 16, 5, 'Ban radial pas untuk sedan keluarga.'),
(6, 'Oli Mesin 10W-40', 'Castrol', 85000, 125000, 40, 10, 'Oli semi sintetis 4 liter.'),
(4, 'Bumper Depan All New', 'AHKAP', 750000, 980000, 3, 2, 'Bumper fungsional pas untuk kijang modern.');

INSERT INTO setting_toko (kunci, nilai) VALUES
('nama_toko', 'Toko Sparepart Mobil Sejahtera'),
('alamat', 'Jl. Otowilangan No. 123, Yogyakarta'),
('telepon', '0274-8899-77'),
('footer_struk', 'Terima kasih sudah belanja. Barang yang sudah dibeli tidak bisa dikembalikan.');
