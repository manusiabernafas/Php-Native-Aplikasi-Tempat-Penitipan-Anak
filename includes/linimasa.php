<?php
/** Linimasa satu hari: datang, semua catatan, dan pulang, terurut menurut jam. */
function linimasa(int $anakId, string $tgl, bool $kelola = false): string
{
    $h = q('SELECT * FROM kehadiran WHERE anak_id = ? AND tanggal = ?', [$anakId, $tgl])->fetch();
    $item = [];
    if ($h && $h['masuk']) $item[] = ['waktu' => $h['masuk'], 'jenis' => '_masuk', 'judul' => 'Datang', 'isi' => 'Diantar ' . $h['diantar'] . ($h['suhu'] ? ' · suhu ' . str_replace('.', ',', $h['suhu']) . '°C' : ''), 'warna' => 'mint', 'ikon' => 'masuk'];
    foreach (q('SELECT c.*, u.nama oleh FROM catatan c LEFT JOIN pengguna u ON u.id = c.user_id WHERE c.anak_id = ? AND c.tanggal = ? ORDER BY c.waktu, c.id', [$anakId, $tgl])->fetchAll() as $c) {
        [$label, $ikon, $warna, $pilihan] = JENIS[$c['jenis']] ?? ['Catatan', 'pena', 'abu', []];
        $nilai = $pilihan[$c['nilai']] ?? $c['nilai'];
        $isi = trim($nilai . ($c['detail'] !== '' ? ($nilai !== '' ? ' · ' : '') . $c['detail'] : ''));
        if ($c['jenis'] === 'tidur') {
            $isi = $c['selesai'] ? 'Sampai ' . jam($c['selesai']) . ' (' . (int) round((strtotime($c['selesai']) - strtotime($c['waktu'])) / 60) . ' menit)' : 'Masih tidur';
        }
        $item[] = ['waktu' => $c['waktu'], 'jenis' => $c['jenis'], 'judul' => $label, 'isi' => $isi, 'warna' => $warna, 'ikon' => $ikon, 'mood' => $c['jenis'] === 'mood' ? $c['nilai'] : null,
                   'id' => (int) $c['id'], 'oleh' => $c['oleh'], 'bisa' => $kelola && (is_admin() || ((int) $c['user_id'] === user()['id'] && $tgl === date('Y-m-d')))];
    }
    if ($h && $h['pulang']) $item[] = ['waktu' => $h['pulang'], 'jenis' => '_pulang', 'judul' => 'Pulang', 'isi' => 'Dijemput ' . $h['dijemput'], 'warna' => 'langit', 'ikon' => 'keluar'];
    usort($item, fn($a, $b) => strcmp($a['waktu'], $b['waktu']));
    if (!$item) return '<p class="kosong-lucu">' . avatar($anakId, 64) . '<span>' . ($h && $h['status'] !== 'hadir' ? 'Hari ini ' . e(strtolower(STATUS_HADIR[$h['status']][0])) . ($h['keterangan'] ? ': ' . e($h['keterangan']) : '') . '.' : 'Belum ada catatan di hari ini.') . '</span></p>';
    ob_start(); ?>
<ol class="linimasa">
  <?php foreach ($item as $i): ?>
    <li class="<?= e($i['warna']) ?>">
      <span class="lm-jam"><?= jam($i['waktu']) ?></span>
      <span class="lm-ikon"><?= !empty($i['mood']) ? wajah($i['mood'], 20) : icon($i['ikon'], 18) ?></span>
      <span class="lm-isi"><b><?= e($i['judul']) ?></b><?= $i['isi'] !== '' ? '<span>' . e($i['isi']) . '</span>' : '' ?><?= !empty($i['oleh']) && $kelola ? '<small>oleh ' . e(explode(' ', $i['oleh'])[0] . ' ' . (explode(' ', $i['oleh'])[1] ?? '')) . '</small>' : '' ?></span>
      <?php if (!empty($i['bisa'])): ?>
        <form method="post" action="<?= url('aksi') ?>" data-yakin="Hapus catatan ini?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="anak_id" value="<?= $anakId ?>"><input type="hidden" name="catatan_id" value="<?= $i['id'] ?>"><button class="ibtn kecil" aria-label="Hapus catatan"><?= icon('sampah', 15) ?></button></form>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>
<?php return ob_get_clean();
}
