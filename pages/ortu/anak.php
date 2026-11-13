<?php
$a = anak_ortu((int) ($_GET['anak'] ?? 0));
if (!$a) { flash('Data anak tidak ditemukan.', 'error'); redirect('ortu'); }
$hariIni = date('Y-m-d');
$tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['tgl'] ?? '')) ? $_GET['tgl'] : $hariIni;
if ($tgl > $hariIni || $tgl < date('Y-m-d', strtotime('-60 day'))) $tgl = $hariIni;
$riwayat = q("SELECT k.tanggal, k.status, k.masuk, k.pulang,
              (SELECT SUM(TIMESTAMPDIFF(MINUTE, c.waktu, c.selesai)) FROM catatan c WHERE c.anak_id = k.anak_id AND c.tanggal = k.tanggal AND c.jenis = 'tidur') tidur,
              (SELECT c.nilai FROM catatan c WHERE c.anak_id = k.anak_id AND c.tanggal = k.tanggal AND c.jenis = 'makan' AND c.detail LIKE 'Makan siang%' LIMIT 1) siang
              FROM kehadiran k WHERE k.anak_id = ? AND k.tanggal >= CURDATE() - INTERVAL 14 DAY ORDER BY k.tanggal DESC", [$a['id']])->fetchAll();
$title = $a['panggilan'];
require __DIR__ . '/../../includes/ortu_header.php';
?>
<a class="kembali" href="<?= url('ortu') ?>"><?= icon('kiri', 15) ?> Kembali</a>
<section class="kartu anak-ortu">
  <div class="kartu-kepala"><?= avatar((int) $a['id'], 64) ?><span><b><?= e($a['panggilan']) ?></b><small><?= e(hari_tgl($tgl)) ?></small></span></div>
  <form class="pilih-tgl" method="get"><input type="hidden" name="page" value="ortu.anak"><input type="hidden" name="anak" value="<?= (int) $a['id'] ?>">
    <input type="date" name="tgl" value="<?= e($tgl) ?>" max="<?= $hariIni ?>" min="<?= date('Y-m-d', strtotime('-60 day')) ?>" onchange="this.form.submit()" aria-label="Tanggal"></form>
  <?= linimasa((int) $a['id'], $tgl) ?>
</section>
<section class="kartu">
  <h2>14 hari terakhir</h2>
  <ul class="riwayat-ortu">
    <?php foreach ($riwayat as $r): ?>
      <li><a href="<?= url('ortu.anak', ['anak' => $a['id'], 'tgl' => $r['tanggal']]) ?>"><b><?= e(hari_tgl($r['tanggal'])) ?></b>
        <span><?= $r['status'] === 'hadir' ? jam($r['masuk']) . ' - ' . jam($r['pulang']) . ($r['tidur'] ? ' · tidur ' . (int) $r['tidur'] . ' mnt' : '') . ($r['siang'] ? ' · makan siang ' . e(strtolower(JENIS['makan'][3][$r['siang']] ?? '')) : '') : e(STATUS_HADIR[$r['status']][0]) ?></span></a></li>
    <?php endforeach; if (!$riwayat): ?><li class="muted">Belum ada riwayat.</li><?php endif; ?>
  </ul>
</section>
</main>
</body>
</html>
