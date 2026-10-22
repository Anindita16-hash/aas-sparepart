<?php /* FOOTER / TEMPLATE BAWAH: menutup container <div> yang dibuka header.php, lalu JS Bootstrap */ ?>
</div><!-- /.container -->

<footer class="text-center text-muted pb-4 small">
  <?= e(info_toko()['footer_struk'] ?? '') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php /* $script = JS tambahan dari halaman (kalau ada), dipanggil terakhir biar element HTML sudah ada */ ?>
<?= $script ?? '' ?>
</body>
</html>
