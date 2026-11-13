<?php
$bulan = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['bulan'] ?? '')) ? $_GET['bulan'] : date('Y-m');
$awal = $bulan . '-01'; $akhir = date('Y-m-t', strtotime($awal));
$hari = [];
for ($t = strtotime($awal); $t <= strtotime($akhir); $t += 86400) if (date('N', $t) <= 5) $hari[] = date('Y-m-d', $t);
$data = [];
foreach (q('SELECT anak_id, tanggal, status, denda FROM kehadiran WHERE tanggal BETWEEN ? AND ?', [$awal, $akhir])->fetchAll() as $r) $data[$r['anak_id']][$r['tanggal']] = $r;
$anak = q('SELECT id, panggilan, paket FROM anak WHERE aktif = 1 OR id IN (SELECT anak_id FROM kehadiran WHERE tanggal BETWEEN ? AND ?) ORDER BY panggilan', [$awal, $akhir])->fetchAll();
$KODE = ['hadir' => ['H', 'mint'], 'izin' => ['I', 'langit'], 'sakit' => ['S', 'peach']];
if (($_GET['unduh'] ?? '') === 'csv') {
    $out = [array_merge(['anak'], array_map(fn($d) => date('j', strtotime($d)), $hari), ['hadir', 'izin', 'sakit', 'denda'])];
    foreach ($anak as $a) {
        $r = $data[$a['id']] ?? []; $n = ['hadir' => 0, 'izin' => 0, 'sakit' => 0]; $dn = 0;
        foreach ($r as $x) { $n[$x['status']]++; $dn += $x['denda']; }
        $out[] = array_merge([$a['panggilan']], array_map(fn($d) => isset($r[$d]) ? $KODE[$r[$d]['status']][0] : '', $hari), [$n['hadir'], $n['izin'], $n['sakit'], $dn]);
    }
    kirim_csv("kehadiran-$bulan.csv", $out);
}
$title = 'Kehadiran';
$sub = 'Rekap ' . e(BULAN_ID[(int) substr($bulan, 5)] . ' ' . substr($bulan, 0, 4)) . ' · H hadir, I izin, S sakit, titik merah = telat jemput';
$actions = '<a class="btn" href="' . url('kehadiran', ['bulan' => $bulan, 'unduh' => 'csv']) . '">' . icon('unduh') . 'CSV</a>';
require __DIR__ . '/../includes/header.php';
?>
<form class="filter" method="get"><input type="hidden" name="page" value="kehadiran">
  <a class="ibtn" href="<?= url('kehadiran', ['bulan' => date('Y-m', strtotime($awal . ' -1 month'))]) ?>" aria-label="Bulan sebelumnya"><?= icon('kiri') ?></a>
  <input type="month" name="bulan" value="<?= e($bulan) ?>" onchange="this.form.submit()" aria-label="Bulan">
  <a class="ibtn" href="<?= url('kehadiran', ['bulan' => date('Y-m', strtotime($awal . ' +1 month'))]) ?>" aria-label="Bulan berikutnya"><?= icon('kanan') ?></a>
</form>
<section class="kartu rata">
  <div class="tabel-wrap">
  <table class="tabel rekap">
    <thead><tr><th>Anak</th><?php foreach ($hari as $d): ?><th class="tengah"><?= date('j', strtotime($d)) ?><small><?= substr(HARI[(int) date('N', strtotime($d))], 0, 1) ?></small></th><?php endforeach; ?><th class="kanan">Hadir</th><th class="kanan">Denda</th></tr></thead>
    <tbody>
    <?php foreach ($anak as $a): $r = $data[$a['id']] ?? []; $h = 0; $dn = 0; ?>
      <tr><td class="nowrap"><a href="<?= url('harian', ['anak' => $a['id']]) ?>" class="nama-mini"><?= avatar((int) $a['id'], 26) ?><?= e($a['panggilan']) ?></a></td>
        <?php foreach ($hari as $d): $x = $r[$d] ?? null; if ($x && $x['status'] === 'hadir') $h++; if ($x) $dn += $x['denda']; ?>
          <td class="tengah"><?php if ($x): ?><a class="sel <?= $KODE[$x['status']][1] ?> <?= $x['denda'] ? 'telat' : '' ?>" href="<?= url('harian', ['anak' => $a['id'], 'tgl' => $d]) ?>" title="<?= e(hari_tgl($d)) ?>"><?= $KODE[$x['status']][0] ?></a><?php endif; ?></td>
        <?php endforeach; ?>
        <td class="kanan"><b><?= $h ?></b></td><td class="kanan nowrap"><?= $dn ? rupiah($dn) : '-' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
