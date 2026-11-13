<?php
$rows = q('SELECT t.*, a.panggilan FROM tagihan t JOIN anak a ON a.id = t.anak_id WHERE a.ortu_id = ? ORDER BY t.bulan DESC, a.panggilan LIMIT 36', [ortu()['id']])->fetchAll();
$title = 'Tagihan';
require __DIR__ . '/../../includes/ortu_header.php';
?>
<h1 class="sapa">Tagihan</h1>
<?php foreach ($rows as $t): ?>
  <section class="kartu tagihan-ortu <?= $t['status'] === 'lunas' ? 'mint-muda' : 'peach-muda' ?>">
    <div class="kartu-kepala"><?= avatar((int) $t['anak_id'], 48) ?><span><b><?= e($t['panggilan']) ?> · <?= e(BULAN_ID[(int) substr($t['bulan'], 5)] . ' ' . substr($t['bulan'], 0, 4)) ?></b><small><?= e(PAKET[$t['paket']]) ?><?= $t['paket'] === 'harian' ? ', ' . (int) $t['hari_hadir'] . ' hari' : '' ?></small></span></div>
    <dl class="rincian"><div><dt>Biaya</dt><dd><?= rupiah($t['biaya']) ?></dd></div><?php if ($t['denda']): ?><div><dt>Denda telat jemput</dt><dd><?= rupiah($t['denda']) ?></dd></div><?php endif; ?><div class="total"><dt>Total</dt><dd><?= rupiah($t['total']) ?></dd></div></dl>
    <p><?= $t['status'] === 'lunas' ? chip('mint', 'Lunas · ' . tgl($t['dibayar_at'])) : chip('peach', 'Belum dibayar') ?></p>
  </section>
<?php endforeach; if (!$rows): ?><p class="kosong-lucu"><?= avatar(8, 64) ?><span>Belum ada tagihan.</span></p><?php endif; ?>
<p class="kecil muted tengah">Pembayaran tunai atau transfer ke pengelola. Hubungi <?= e(setelan('telepon')) ?> bila ada pertanyaan.</p>
</main>
</body>
</html>
