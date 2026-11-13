<?php
$hariIni = date('Y-m-d');
$anak = q('SELECT a.*, k.status, k.masuk, k.pulang, k.dijemput FROM anak a LEFT JOIN kehadiran k ON k.anak_id = a.id AND k.tanggal = ? WHERE a.ortu_id = ? AND a.aktif = 1 ORDER BY a.tgl_lahir', [$hariIni, ortu()['id']])->fetchAll();
$menu = q('SELECT * FROM menu WHERE hari = ?', [(int) date('N')])->fetch();
$belum = (int) q("SELECT COUNT(*) FROM tagihan t JOIN anak a ON a.id = t.anak_id WHERE a.ortu_id = ? AND t.status = 'belum'", [ortu()['id']])->fetchColumn();
$title = 'Si kecil';
require __DIR__ . '/../../includes/ortu_header.php';
?>
<h1 class="sapa">Halo, <?= e(explode(' ', ortu()['nama'])[0]) ?>!</h1>
<p class="muted"><?= e(hari_tgl($hariIni)) ?></p>
<?php if (setelan('pesan_ortu')): ?><p class="pesan info"><?= icon('info') ?><span><?= e(setelan('pesan_ortu')) ?></span></p><?php endif; ?>
<?php if ($belum): ?><p class="pesan error"><?= icon('dompet') ?><span>Ada <?= $belum ?> tagihan yang belum dibayar. <a href="<?= url('ortu.tagihan') ?>">Lihat tagihan</a></span></p><?php endif; ?>
<?php foreach ($anak as $a):
    $status = $a['status'] === 'izin' || $a['status'] === 'sakit' ? STATUS_HADIR[$a['status']][0] : ($a['pulang'] ? 'Sudah dijemput ' . $a['dijemput'] . ' pukul ' . jam($a['pulang']) : ($a['masuk'] ? 'Di daycare sejak ' . jam($a['masuk']) : 'Belum datang'));
    $warna = $a['pulang'] ? 'langit' : ($a['masuk'] ? 'mint' : ($a['status'] ? 'peach' : 'kuning')); ?>
  <section class="kartu anak-ortu">
    <a class="kartu-kepala" href="<?= url('ortu.anak', ['anak' => $a['id']]) ?>"><?= avatar((int) $a['id'], 64) ?><span><b><?= e($a['panggilan']) ?></b><small><?= e(umur($a['tgl_lahir'])) ?> · <?= e(KELOMPOK[$a['kelompok']]) ?></small></span></a>
    <p><?= chip($warna, $status) ?></p>
    <?= linimasa((int) $a['id'], $hariIni) ?>
    <a class="btn blok" href="<?= url('ortu.anak', ['anak' => $a['id']]) ?>"><?= icon('kalender') ?>Lihat hari-hari sebelumnya</a>
  </section>
<?php endforeach; if (!$anak): ?><p class="kosong-lucu"><?= avatar(1, 64) ?><span>Belum ada data anak. Hubungi pengasuh.</span></p><?php endif; ?>
<?php if ($menu): ?>
  <section class="kartu kuning-muda"><h2><?= icon('sendok', 20) ?> Menu hari ini</h2>
    <dl class="menu-ortu"><div><dt>Snack pagi</dt><dd><?= e($menu['pagi']) ?></dd></div><div><dt>Makan siang</dt><dd><?= e($menu['siang']) ?></dd></div><div><dt>Snack sore</dt><dd><?= e($menu['sore']) ?></dd></div></dl>
  </section>
<?php endif; ?>
</main>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
