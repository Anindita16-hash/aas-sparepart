<?php
/* ============================================================
   KASIR (PENJUALAN OFFLINE) — pegawai
   Fitur:
   - pilih barang beberapa kali (pakai JS kecil untuk baris tambahan)
   - kirim transaksi: stok berkurang otomatis
   - pembayaran TUNAI -> langsung status "lunas"
   - setelah simpan -> tampil struk yang bisa di-print

   ADVANCED (db transaksi):
   Penyimpanan penjualan + detail + pengurangan stok dibungkus dalam
   SATU database TRANSACTION. Prinsipnya: semua berhasil semua, atau
   semua dibatalkan (rollback). Contoh: stok ternyata kurang saat
   setengah data sudah masuk -> seluruh transaksi dibatalkan, tidak ada
   data setengah jadi. Penting supaya data tidak korup.
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('pegawai');

$pesan = '';
$struk = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    /* data dari form: barang[] = [ [id, qty], ... ] */
    $items = $_POST['barang'] ?? [];   // format: barang[0][id], barang[0][qty]

    $bersih = [];
    foreach ($items as $it) {
        $id  = (int)($it['id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        if ($id > 0 && $qty > 0) {
            $bersih[] = ['id' => $id, 'qty' => $qty];
        }
    }

    if (!$bersih) {
        $pesan = ['danger', 'Belum ada barang. Klik "+ Tambah Barang" dulu.'];
    } else {
        try {
            $db = db();
            /* ADVANCED: mulai TRANSACTION (lihat catatan di atas) */
            $db->beginTransaction();

            $total = 0;
            $detail_baru = [];

            foreach ($bersih as $it) {
                // ambil barang + kunci barisnya: FOR UPDATE supaya tidak
                // berubah data saat diproses (bagian dari DB transaction)
                $stmt = $db->prepare('SELECT * FROM barang WHERE id = ? FOR UPDATE');
                $stmt->execute([$it['id']]);
                $b = $stmt->fetch();

                if (!$b) {
                    throw new Exception("Barang id {$it['id']} tidak ditemukan.");
                }
                if ($b['stok'] < $it['qty']) {
                    throw new Exception("Stok barang \"{$b['nama']}\" tersisa {$b['stok']}, kurang dari {$it['qty']}.");
                }

                $subtotal = $b['harga_jual'] * $it['qty'];
                $total   += $subtotal;
                $detail_baru[] = [
                    'id' => $b['id'], 'nama' => $b['nama'],
                    'qty' => $it['qty'], 'harga' => $b['harga_jual'], 'subtotal' => $subtotal,
                ];
            }

            // simpan penjualan (kasir = pegawai yang login) — tunai -> langsung lunas
            $nomor = nomor_dokumen('INV', db_last_id('penjualan'));
            $stmt = $db->prepare(
                'INSERT INTO penjualan (no_invoice, users_id, total, metode, status_bayar)
                 VALUES (?, ?, ?, "tunai", "lunas")'
            );
            $stmt->execute([$nomor, user()['id'], $total]);
            $penjualan_id = $db->lastInsertId();

            // simpan detail + kurangi stok
            foreach ($detail_baru as $d) {
                $db->prepare('INSERT INTO penjualan_detail (penjualan_id,barang_id,qty,harga_satuan,subtotal) VALUES (?,?,?,?,?)')
                   ->execute([$penjualan_id, $d['id'], $d['qty'], $d['harga'], $d['subtotal']]);

                $db->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')
                   ->execute([$d['qty'], $d['id']]);
            }

            $db->commit(); // ADVANCED: semua berhasil -> dikunci permanen

            $struk = [
                'nomor'   => $nomor,
                'total'   => $total,
                'toko'    => info_toko(),
                'detail'  => $detail_baru,
                'tanggal' => date('d-m-Y H:i'),
            ];

        } catch (Exception $e) {
            // ADVANCED: kalau ada error di tengah -> batalkan semua (rollback)
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $pesan = ['danger', 'Transaksi ditolak: ' . $e->getMessage()];
        }
    }
}

/* helper kecil: ambil nilai MAX(id) tabel mana pun utk nomor dokumen */
function db_last_id($tabel) {
    return (int)db()->query("SELECT COALESCE(MAX(id),0) AS i FROM $tabel")->fetch()['i'];
}

$barang = db()->query('SELECT * FROM barang ORDER BY nama')->fetchAll();

$judul = 'Kasir';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Kasir — Penjualan Offline</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div id="daftar"></div>
  <button type="button" class="btn btn-sm btn-outline-primary mb-3 no-print" onclick="tambahBaris()">+ Tambah Barang</button>

  <div class="card p-3 bg-white mb-3">
    <button class="btn btn-success no-print">Simpan Penjualan (Tunai)</button>
  </div>
</form>

<?php if ($struk): ?>
<!-- --------- STRUK: tampil setelah transaksi sukses --------- -->
<div class="card struk" style="max-width:420px" >
  <div class="card-header text-center"><strong>Struk Pembayaran</strong></div>
  <div class="card-body">
    <div class="text-center mb-2">
      <div><strong><?= e($struk['toko']['nama_toko']) ?></strong></div>
      <div class="small"><?= e($struk['toko']['alamat'] ?? '') ?></div>
      <div class="small"><?= e($struk['toko']['telepon'] ?? '') ?></div>
    </div>
    <hr>
    <div class="small">No: <strong><?= e($struk['nomor']) ?></strong><br>
      Tanggal: <?= e($struk['tanggal']) ?><br>
      Kasir: <?= e(user()['nama']) ?></div>
    <hr>
    <table class="table table-sm small">
      <?php foreach ($struk['detail'] as $d): ?>
      <tr><td><?= e($d['nama']) ?> x<?= $d['qty'] ?></td><td class="text-end"><?= rupiah($d['subtotal']) ?></td></tr>
      <?php endforeach; ?>
      <tr><td><strong>TOTAL</strong></td><td class="text-end"><strong><?= rupiah($struk['total']) ?></strong></td></tr>
    </table>
    <hr>
    <div class="small text-center"><?= e($struk['toko']['footer_struk'] ?? '') ?></div>
    <button class="btn btn-outline-dark w-100 btn-sm mt-2 no-print" onclick="window.print()">🖨️ Cetak Struk</button>
  </div>
</div>
<?php endif; ?>

<?php
/* JS kecil: menambah baris pilihan barang di form kasir (vanilla, tanpa framework) */
$script = '
<script>
// stok data barang dikirim dari PHP ke JS (JSON)
var dataBarang = ' . json_encode(array_map(function($b){
    return ["id" => (int)$b["id"], "t" => $b["nama"] . " (" . number_format((float)$b["harga_jual"],0,",",".") . ")"];
}, $barang)) . ';

function tambahBaris() {
  var baris = document.createElement("div");
  baris.className = "row g-2 mb-1";
  var opsi = dataBarang.map(b =>
    `<option value="${b.id}">${b.t}</option>`).join("");
  baris.innerHTML =
    `<div class="col-md-7"><select name="barang[][id]" class="form-select" required>${opsi}</select></div>
     <div class="col-md-2"><input type="number" name="barang[][qty]" class="form-control" value="1" min="1"></div>
     <div class="col-md-2"><button type="button" class="btn btn-outline-danger" onclick="this.closest(\'.row\').remove()">x</button></div>`;
  document.getElementById("daftar").appendChild(baris);
}
tambahBaris(); // langsung tampil 1 baris di awal
</script>
<style>
@media print {
  nav, footer, .no-print { display:none !important; }
  body * { visibility: hidden; }          /* sembunyikan semua dulu */
  .struk, .struk * { visibility: visible; } /* lalu tampilkan struk saja */
  .struk { position:absolute; left:0; top:0; }
}
</style>';
require __DIR__ . '/../includes/footer.php';
