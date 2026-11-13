<?php
$hariIni = date('Y-m-d');
$anak = q("SELECT a.*, o.nama ortu_nama, k.status, k.masuk, k.pulang, k.suhu, k.denda,
           (SELECT COUNT(*) FROM catatan c WHERE c.anak_id = a.id AND c.tanggal = ?) n_catat,
           (SELECT CONCAT(c.jenis, '|', c.waktu) FROM catatan c WHERE c.anak_id = a.id AND c.tanggal = ? AND c.jenis = 'tidur' AND c.selesai IS NULL LIMIT 1) tidur,
           (SELECT c.nilai FROM catatan c WHERE c.anak_id = a.id AND c.tanggal = ? AND c.jenis = 'mood' ORDER BY c.waktu DESC LIMIT 1) mood,
           (SELECT COUNT(*) FROM titip_jemput t WHERE t.anak_id = a.id AND t.tanggal = ?) titip
           FROM anak a JOIN ortu o ON o.id = a.ortu_id LEFT JOIN kehadiran k ON k.anak_id = a.id AND k.tanggal = ?
           WHERE a.aktif = 1 ORDER BY a.panggilan", [$hariIni, $hariIni, $hariIni, $hariIni, $hariIni])->fetchAll();
$grup = ['di' => [], 'belum' => [], 'pulang' => [], 'absen' => []];
foreach ($anak as $a) {
    if ($a['status'] === 'izin' || $a['status'] === 'sakit') $grup['absen'][] = $a;
    elseif ($a['masuk'] && !$a['pulang']) $grup['di'][] = $a;
    elseif ($a['pulang']) $grup['pulang'][] = $a;
    else $grup['belum'][] = $a;
}
$menu = q('SELECT * FROM menu WHERE hari = ?', [(int) date('N')])->fetch();
$tidurSekarang = count(array_filter($grup['di'], fn($a) => $a['tidur']));
$title = 'Hari ini';
$sub = e(hari_tgl($hariIni)) . ' · buka ' . e(setelan('jam_buka')) . ' - ' . e(setelan('jam_tutup'));
require __DIR__ . '/../includes/header.php';

function kartu_anak(array $a, string $mode): string
{
    ob_start(); ?>
  <article class="kartu-anak <?= $mode ?>">
    <a class="kartu-kepala" href="<?= url('harian', ['anak' => $a['id']]) ?>">
      <?= avatar((int) $a['id'], 56) ?>
      <span><b><?= e($a['panggilan']) ?></b><small><?= e(umur($a['tgl_lahir'])) ?> · <?= e(KELOMPOK[$a['kelompok']]) ?></small></span>
    </a>
    <div class="lencana">
      <?php if ($a['alergi']): ?><span class="chip merah" title="Alergi"><?= icon('awas', 13) ?> <?= e($a['alergi']) ?></span><?php endif; ?>
      <?php if ($a['catatan_kesehatan']): ?><span class="chip peach" title="<?= e($a['catatan_kesehatan']) ?>"><?= icon('plester', 13) ?> Perhatian</span><?php endif; ?>
      <?php if ($a['titip']): ?><span class="chip ungu"><?= icon('info', 13) ?> Titip jemput</span><?php endif; ?>
    </div>
    <?php if ($mode === 'di'): ?>
      <p class="info-anak">Datang <b><?= jam($a['masuk']) ?></b><?= $a['suhu'] ? ' · ' . e(str_replace('.', ',', $a['suhu'])) . '°C' : '' ?><?= $a['mood'] ? ' · ' . wajah($a['mood'], 18) . ' ' . e(JENIS['mood'][3][$a['mood']] ?? '') : '' ?></p>
      <?php if ($a['tidur']): ?><p class="info-anak tidur"><?= icon('bulan', 15) ?> Sedang tidur sejak <?= jam(explode('|', $a['tidur'])[1]) ?></p><?php endif; ?>
      <div class="aksi-anak"><a class="btn kecil" href="<?= url('harian', ['anak' => $a['id']]) ?>"><?= icon('pena', 15) ?>Catat (<?= (int) $a['n_catat'] ?>)</a><a class="btn kecil peach" href="<?= url('harian', ['anak' => $a['id'], 'jemput' => 1]) ?>#jemput"><?= icon('keluar', 15) ?>Jemput</a></div>
    <?php elseif ($mode === 'belum'): ?>
      <form method="post" action="<?= url('aksi') ?>" class="antar">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="masuk"><input type="hidden" name="anak_id" value="<?= (int) $a['id'] ?>">
        <input type="hidden" name="diantar" value="<?= e($a['ortu_nama']) ?>">
        <label class="suhu"><?= icon('termometer', 15) ?><input name="suhu" inputmode="decimal" placeholder="36,5" aria-label="Suhu <?= e($a['panggilan']) ?>"><span>°C</span></label>
        <button class="btn kecil mint"><?= icon('masuk', 15) ?>Datang</button>
      </form>
    <?php elseif ($mode === 'pulang'): ?>
      <p class="info-anak"><?= jam($a['masuk']) ?> - <?= jam($a['pulang']) ?><?= $a['denda'] ? ' · <span class="teks-merah">denda ' . rupiah($a['denda']) . '</span>' : '' ?></p>
    <?php else: ?>
      <p class="info-anak"><?= chip(STATUS_HADIR[$a['status']][1], STATUS_HADIR[$a['status']][0]) ?></p>
    <?php endif; ?>
  </article>
<?php return ob_get_clean();
}
?>
<div class="ringkas">
  <div class="gelembung mint"><b><?= count($grup['di']) ?></b><span>di daycare</span></div>
  <div class="gelembung ungu"><b><?= $tidurSekarang ?></b><span>sedang tidur</span></div>
  <div class="gelembung kuning"><b><?= count($grup['belum']) ?></b><span>belum datang</span></div>
  <div class="gelembung langit"><b><?= count($grup['pulang']) ?></b><span>sudah pulang</span></div>
  <?php if ($menu): ?><div class="gelembung peach menu-hari"><?= icon('sendok', 18) ?><span><b>Menu siang</b><?= e($menu['siang']) ?></span></div><?php endif; ?>
</div>

<?php foreach (['di' => 'Sedang di daycare', 'belum' => 'Belum datang', 'pulang' => 'Sudah pulang', 'absen' => 'Izin / sakit'] as $k => $judul): if (!$grup[$k] && $k !== 'di') continue; ?>
  <section class="kelompok">
    <h2><?= e($judul) ?> <span class="hitung"><?= count($grup[$k]) ?></span></h2>
    <?php if (!$grup[$k]): ?><p class="kosong-lucu"><?= avatar(9, 64) ?><span>Belum ada anak yang datang. Tekan <b>Datang</b> saat si kecil diantar.</span></p><?php endif; ?>
    <div class="grid-anak"><?php foreach ($grup[$k] as $a) echo kartu_anak($a, $k); ?></div>
  </section>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
