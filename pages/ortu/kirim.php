<?php
$anak = q('SELECT * FROM anak WHERE ortu_id = ? AND aktif = 1 ORDER BY tgl_lahir', [ortu()['id']])->fetchAll();
$hariIni = date('Y-m-d');
$batas = date('Y-m-d', strtotime('+14 day'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = anak_ortu((int) ($_POST['anak_id'] ?? 0));
    $tgl = (string) ($_POST['tanggal'] ?? '');
    $okTgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) && $tgl >= $hariIni && $tgl <= $batas;
    if (!$a) flash('Pilih anak.', 'error');
    elseif (!$okTgl) flash('Tanggal harus hari ini sampai 14 hari ke depan.', 'error');
    elseif (($_POST['aksi'] ?? '') === 'absen') {
        $st = ($_POST['status'] ?? '') === 'sakit' ? 'sakit' : 'izin';
        $ket = mb_substr(trim((string) ($_POST['keterangan'] ?? '')), 0, 255);
        $h = q('SELECT masuk FROM kehadiran WHERE anak_id = ? AND tanggal = ?', [$a['id'], $tgl])->fetch();
        if ($h && $h['masuk']) flash($a['panggilan'] . ' sudah datang di tanggal itu.', 'error');
        else {
            q('INSERT INTO kehadiran (anak_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)', [$a['id'], $tgl, $st, 'Dari orang tua: ' . $ket]);
            flash('Terima kasih, pengasuh sudah menerima kabar ' . $a['panggilan'] . ' ' . $st . ' pada ' . hari_tgl($tgl) . '.');
            redirect('ortu');
        }
    } else {
        $nama = trim((string) ($_POST['nama'] ?? '')); $hub = trim((string) ($_POST['hubungan'] ?? ''));
        $jml = (int) q('SELECT COUNT(*) FROM titip_jemput WHERE anak_id = ? AND tanggal = ?', [$a['id'], $tgl])->fetchColumn();
        if ($nama === '' || $hub === '' || mb_strlen($nama) > 100 || mb_strlen($hub) > 40) flash('Isi nama penjemput dan hubungannya.', 'error');
        elseif ($jml >= 3) flash('Maksimal 3 titipan penjemput per hari.', 'error');
        else {
            q('INSERT INTO titip_jemput (anak_id, tanggal, nama, hubungan) VALUES (?, ?, ?, ?)', [$a['id'], $tgl, $nama, $hub]);
            flash($nama . ' boleh menjemput ' . $a['panggilan'] . ' pada ' . hari_tgl($tgl) . '. Minta beliau membawa KTP ya.');
            redirect('ortu');
        }
    }
    redirect('ortu.kirim');
}
$titip = q('SELECT t.*, a.panggilan FROM titip_jemput t JOIN anak a ON a.id = t.anak_id WHERE a.ortu_id = ? AND t.tanggal >= ? ORDER BY t.tanggal', [ortu()['id'], $hariIni])->fetchAll();
$title = 'Kabari pengasuh';
require __DIR__ . '/../../includes/ortu_header.php';
$pilihAnak = fn() => implode('', array_map(fn($a) => '<option value="' . (int) $a['id'] . '">' . e($a['panggilan']) . '</option>', $anak));
?>
<h1 class="sapa">Kabari pengasuh</h1>
<form method="post" class="kartu peach-muda">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="absen">
  <h2><?= icon('plester', 20) ?> Si kecil tidak masuk</h2>
  <div class="baris-f">
    <label class="f"><span>Anak</span><select name="anak_id" required><?= $pilihAnak() ?></select></label>
    <label class="f"><span>Tanggal</span><input type="date" name="tanggal" value="<?= $hariIni ?>" min="<?= $hariIni ?>" max="<?= $batas ?>" required></label>
  </div>
  <div class="pilih-status"><label><input type="radio" name="status" value="sakit" checked><span>Sakit</span></label><label><input type="radio" name="status" value="izin"><span>Izin</span></label></div>
  <label class="f"><span>Keterangan</span><input name="keterangan" maxlength="200" placeholder="mis. demam sejak semalam"></label>
  <button class="btn peach blok"><?= icon('kirim') ?>Kirim kabar</button>
</form>
<form method="post" class="kartu ungu-muda">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="titip">
  <h2><?= icon('user', 20) ?> Dijemput orang lain</h2>
  <p class="kecil muted">Demi keamanan, pengasuh hanya menyerahkan anak ke orang tua, penjemput terdaftar, atau nama yang dititipkan di sini.</p>
  <div class="baris-f">
    <label class="f"><span>Anak</span><select name="anak_id" required><?= $pilihAnak() ?></select></label>
    <label class="f"><span>Tanggal</span><input type="date" name="tanggal" value="<?= $hariIni ?>" min="<?= $hariIni ?>" max="<?= $batas ?>" required></label>
  </div>
  <div class="baris-f">
    <label class="f"><span>Nama penjemput</span><input name="nama" maxlength="100" required placeholder="sesuai KTP"></label>
    <label class="f"><span>Hubungan</span><input name="hubungan" maxlength="40" required placeholder="Om, tetangga, teman kantor"></label>
  </div>
  <button class="btn ungu blok"><?= icon('kirim') ?>Titipkan penjemput</button>
</form>
<?php if ($titip): ?><section class="kartu"><h2>Titipan penjemput</h2><ul class="riwayat-ortu"><?php foreach ($titip as $t): ?><li><b><?= e(hari_tgl($t['tanggal'])) ?></b><span><?= e($t['nama']) ?> (<?= e($t['hubungan']) ?>) menjemput <?= e($t['panggilan']) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
</main>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
