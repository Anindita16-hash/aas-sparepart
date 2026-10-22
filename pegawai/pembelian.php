<?php
/* ============================================================
   PEMBELIAN (RESTOCK DARI SUPPLIER) — pegawai
   Fitur:
   - input pembelian: pilih supplier, isi daftar barang + qty + harga beli
   - pilih PO customer (opsional, PO berstatus "dikonfirmasi")
   - setelah disimpan: STOK BARANG OTOMATIS BERTAMBAH
   - kalau ini pembelian untuk memenuhi PO:
       * status PO menjadi "siap" (siap diambil/dikirim)
       * otomatis dibuat draf PENJUALAN untuk customer tsb
         (status bayar "belum", metode "transfer") — customer nanti
         membayar lewat halaman Riwayat & Bayar.
   - juga pakai TRANSACTION seperti kasir.php (lihat ADVANCED di sana)
   ============================================================ */
require_once __DIR__ . '/../config/db.php';
cek_role('pegawai');

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    $supplier_id = (int)$_POST['supplier_id'];
    $po_id       = (int)($_POST['po_id'] ?? 0); // 0 = tidak ada PO

    $items = [];
    foreach (($_POST['barang'] ?? []) as $it) {
        $id  = (int)($it['id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        // harga beli input user; satuan ribuan (.) dibuang dulu
        $h   = (float)str_replace('.', '', $it['harga'] ?? '0');
        if ($id > 0 && $qty > 0) {
            $items[] = ['id' => $id, 'qty' => $qty, 'harga' => $h];
        }
    }

    if (!$items) {
        $pesan = ['danger', 'Belum ada barang yang ditambah.'];
    } else {
        try {
            $db = db();
            /* ADVANCED: TRANSACTION — lihat penjelasan lengkap di kasir.php */
            $db->beginTransaction();
            $total = 0;

            // nomor faktur otomatis: FAK-YYYYMMDD-0001
            $nomor = nomor_dokumen('FAK', db_last_id('pembelian'));
            $db->prepare(
                'INSERT INTO pembelian (no_faktur, users_id, supplier_id, po_id, total)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute([$nomor, user()['id'], $supplier_id ?: null, $po_id ?: null]);
            $pembelian_id = $db->lastInsertId();

            foreach ($items as $it) {
                $stmt = $db->prepare('SELECT * FROM barang WHERE id = ? FOR UPDATE');
                $stmt->execute([$it['id']]);
                $b = $stmt->fetch();
                if (!$b) throw new Exception("Barang tidak ditemukan.");

                $subtotal = $it['harga'] * $it['qty'];
                $total += $subtotal;

                $db->prepare('INSERT INTO pembelian_detail (pembelian_id,barang_id,qty,harga_beli,subtotal) VALUES (?,?,?,?,?)')
                   ->execute([$pembelian_id, $it['id'], $it['qty'], $it['harga'], $subtotal]);

                // STOK BERTAMBAH di lokasi ini
                $db->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?')
                   ->execute([$it['qty'], $it['id']]);
            }

            // total faktur
            $db->prepare('UPDATE pembelian SET total = ? WHERE id = ?')
               ->execute([$total, $pembelian_id]);

            $penjualan_buatan = null;
            if ($po_id) {
                // ambil PO + detail + customer
                $stmt = $db->prepare(
                    'SELECT po.*, d.barang_id, d.qty, d.harga_satuan, b.nama
                     FROM pemesanan_po po
                     JOIN po_detail d ON d.pemesanan_id = po.id
                     JOIN barang b ON b.id = d.barang_id
                     WHERE po.id = ?'
                );
                $stmt->execute([$po_id]);
                $rows = $stmt->fetchAll();

                if ($rows) {
                    // total sesuai harga_jual barang (harga belanja customer)
                    $total_po = 0;
                    foreach ($rows as $r) {
                        $total_po += $r['harga_satuan'] * $r['qty'];
                    }

                    // bikin draf penjualan utk customer (belum dibayar, transfer)
                    $nomor_inv = nomor_dokumen('INV', db_last_id('penjualan'));
                    $db->prepare(
                        'INSERT INTO penjualan (no_invoice, pembeli_id, pemesanan_id, total, metode, status_bayar)
                         VALUES (?, ?, ?, ?, "transfer", "belum")'
                    )->execute([$nomor_inv, $rows[0]['users_id'], $po_id, $total_po]);

                    $penjualan_id_baru = $db->lastInsertId();
                    foreach ($rows as $r) {
                        $db->prepare('INSERT INTO penjualan_detail (penjualan_id,barang_id,qty,harga_satuan,subtotal) VALUES (?,?,?,?,?)')
                           ->execute([$penjualan_id_baru, $r['barang_id'], $r['qty'], $r['harga_satuan'], $r['harga_satuan'] * $r['qty']]);
                    }

                    // status PO -> siap diambil/dikirim
                    $db->prepare("UPDATE pemesanan_po SET status='siap' WHERE id=?")->execute([$po_id]);

                    $penjualan_buatan = $nomor_inv;
                }
            }

            $db->commit();

            $teks = "Pembelian tersimpan. Stok bertambah. Faktur: $nomor";
            if ($penjualan_buatan) {
                $teks .= ' — Invoice untuk customer PO: ' . $penjualan_buatan;
            }
            $pesan = ['success', $teks];

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $pesan = ['danger', 'Gagal: ' . $e->getMessage()];
        }
    }
}

/* ambil data utk form */
function db_last_id($tabel) {
    return (int)db()->query("SELECT COALESCE(MAX(id),0) AS i FROM $tabel")->fetch()['i'];
}

$supplier = db()->query('SELECT * FROM supplier ORDER BY nama')->fetchAll();
$barang   = db()->query('SELECT * FROM barang ORDER BY nama')->fetchAll();

/* POD yang bisa dipilih: status "dikonfirmasi" */
$po_buka = db()->query(
    "SELECT po.id, po.kode_po, u.nama AS customer
     FROM pemesanan_po po JOIN users u ON u.id = po.users_id
     WHERE po.status = 'dikonfirmasi'
     ORDER BY po.id"
)->fetchAll();

$judul = 'Pembelian / Restock';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-3">Pembelian / Restock Barang</h3>

<?php if ($pesan): ?>
  <div class="alert alert-<?= e($pesan[0]) ?>"><?= e($pesan[1]) ?></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>

  <div class="row g-2 mb-2">
    <div class="col-md-5"><label class="form-label">Supplier</label>
      <select name="supplier_id" class="form-select" required>
        <option value="">- pilih supplier -</option>
        <?php foreach ($supplier as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['nama']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-5"><label class="form-label">Untuk PO customer (opsional)</label>
      <select name="po_id" class="form-select">
        <option value="0">- tidak ada / restock biasa -</option>
        <?php foreach ($po_buka as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= e($p['kode_po'] . ' — ' . $p['customer']) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>

  <div id="daftar"></div>
  <button type="button" class="btn btn-sm btn-outline-primary my-2 no-print" onclick="tambahBaris()">+ Tambah Barang</button>

  <div class="card p-3 bg-white mb-3">
    <button class="btn btn-success">Simpan Pembelian</button>
  </div>
</form>

<?php
$script = '
<script>
var dataBarang = ' . json_encode($barang) . ';

function tambahBaris() {
  var baris = document.createElement("div");
  baris.className = "row g-2 mb-1";
  var opsi = dataBarang.map(b => `<option value="${b.id}">${b.nama} (sisa ${b.stok})</option>`).join("");
  baris.innerHTML =
    `<div class="col-md-5"><select name="barang[][id]" class="form-select" required>${opsi}</select></div>
     <div class="col-md-2"><input type="number" name="barang[][qty]" class="form-control" placeholder="Qty" min="1" value="1"></div>
     <div class="col-md-3"><input name="barang[][harga]" class="form-control" placeholder="Harga beli satuan (contoh: 55.000)"></div>
     <div class="col-md-1"><button type="button" class="btn btn-outline-danger" onclick="this.closest(\'.row\').remove()">x</button></div>`;
  document.getElementById("daftar").appendChild(baris);
}
tambahBaris();
</script>';
require __DIR__ . '/../includes/footer.php';
