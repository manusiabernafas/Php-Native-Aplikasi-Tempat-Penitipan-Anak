<?php
$a = q('SELECT a.*, o.nama ortu_nama, o.hp ortu_hp FROM anak a JOIN ortu o ON o.id = a.ortu_id WHERE a.id = ?', [(int) ($_GET['anak'] ?? 0)])->fetch();
if (!$a) redirect('hari-ini');
$hariIni = date('Y-m-d');
$tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['tgl'] ?? '')) ? $_GET['tgl'] : $hariIni;
if ($tgl > $hariIni) $tgl = $hariIni;
$h = q('SELECT * FROM kehadiran WHERE anak_id = ? AND tanggal = ?', [$a['id'], $tgl])->fetch();
$adaDiSini = $tgl === $hariIni && $h && $h['masuk'] && !$h['pulang'];
$tidur = $adaDiSini ? q("SELECT * FROM catatan WHERE anak_id = ? AND tanggal = ? AND jenis = 'tidur' AND selesai IS NULL", [$a['id'], $tgl])->fetch() : null;
$penjemput = penjemput_sah((int) $a['id'], $hariIni);
$titip = q('SELECT * FROM titip_jemput WHERE anak_id = ? AND tanggal = ?', [$a['id'], $hariIni])->fetchAll();

$title = $a['nama'];
$kembali = [url('hari-ini'), 'Hari ini'];
$sub = e(umur($a['tgl_lahir'])) . ' · ' . e(KELOMPOK[$a['kelompok']]) . ' · ' . e($a['ortu_nama']) . ' (' . e($a['ortu_hp']) . ')';
$actions = '<a class="btn" href="' . url('anak.form', ['id' => $a['id']]) . '">' . icon('pena') . 'Data anak</a>';
require __DIR__ . '/../includes/header.php';
?>
<div class="profil-anak">
  <?= avatar((int) $a['id'], 84) ?>
  <div class="lencana">
    <?php if ($a['alergi']): ?><span class="chip merah"><?= icon('awas', 13) ?> Alergi: <?= e($a['alergi']) ?></span><?php endif; ?>
    <?php if ($a['catatan_kesehatan']): ?><span class="chip peach"><?= icon('plester', 13) ?> <?= e($a['catatan_kesehatan']) ?></span><?php endif; ?>
    <span class="chip langit">Paket <?= e(PAKET[$a['paket']]) ?></span>
  </div>
  <form class="pilih-tgl" method="get"><input type="hidden" name="page" value="harian"><input type="hidden" name="anak" value="<?= (int) $a['id'] ?>">
    <a class="ibtn" href="<?= url('harian', ['anak' => $a['id'], 'tgl' => date('Y-m-d', strtotime($tgl . ' -1 day'))]) ?>" aria-label="Hari sebelumnya"><?= icon('kiri') ?></a>
    <input type="date" name="tgl" value="<?= e($tgl) ?>" max="<?= $hariIni ?>" onchange="this.form.submit()" aria-label="Tanggal">
    <?php if ($tgl < $hariIni): ?><a class="ibtn" href="<?= url('harian', ['anak' => $a['id'], 'tgl' => date('Y-m-d', strtotime($tgl . ' +1 day'))]) ?>" aria-label="Hari berikutnya"><?= icon('kanan') ?></a><?php endif; ?>
  </form>
</div>

<div class="grid dua lebar-kiri">
  <section class="kartu">
    <div class="kartu-kepala-teks"><h2><?= $tgl === $hariIni ? 'Kegiatan hari ini' : e(hari_tgl($tgl)) ?></h2>
      <?php if ($h): ?><?= chip(STATUS_HADIR[$h['status']][1], STATUS_HADIR[$h['status']][0]) ?><?php endif; ?></div>
    <?= linimasa((int) $a['id'], $tgl, true) ?>
  </section>

  <div class="tumpuk">
  <?php if ($tgl === $hariIni): ?>
    <?php if (!$h || !$h['masuk']): ?>
      <form method="post" action="<?= url('aksi') ?>" class="kartu mint-muda">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="masuk"><input type="hidden" name="anak_id" value="<?= (int) $a['id'] ?>">
        <h2>Si kecil datang</h2>
        <div class="baris-f">
          <label class="f"><span>Diantar oleh</span><input name="diantar" value="<?= e($a['ortu_nama']) ?>" maxlength="100"></label>
          <label class="f"><span>Suhu (°C)</span><input name="suhu" inputmode="decimal" placeholder="36,5"></label>
        </div>
        <button class="btn mint"><?= icon('masuk') ?>Catat datang</button>
      </form>
      <form method="post" action="<?= url('aksi') ?>" class="kartu">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="absen"><input type="hidden" name="anak_id" value="<?= (int) $a['id'] ?>">
        <h2>Tidak masuk hari ini</h2>
        <div class="baris-f">
          <label class="f"><span>Status</span><select name="status"><option value="izin">Izin</option><option value="sakit">Sakit</option></select></label>
          <label class="f"><span>Keterangan</span><input name="keterangan" maxlength="255" placeholder="Demam, kontrol ke dokter"></label>
        </div>
        <button class="btn"><?= icon('centang') ?>Simpan</button>
      </form>
    <?php elseif ($adaDiSini): ?>
      <form method="post" action="<?= url('aksi') ?>" class="kartu catat-cepat">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="catat"><input type="hidden" name="anak_id" value="<?= (int) $a['id'] ?>">
        <h2>Catat kegiatan</h2>
        <div class="pilih-jenis" role="radiogroup" aria-label="Jenis catatan">
          <?php foreach (JENIS as $k => [$l, $ik, $w]): if ($k === 'tidur' && $tidur) continue; ?>
            <label class="<?= $w ?>"><input type="radio" name="jenis" value="<?= $k ?>" required <?= $k === 'makan' ? 'checked' : '' ?>><span><?= icon($ik, 20) ?><?= e($l) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?php foreach (JENIS as $k => [$l, , , $pil]): if (!$pil) continue; ?>
          <div class="nilai-pilihan" data-untuk="<?= $k ?>">
            <?php foreach ($pil as $v => $vl): ?><label><input type="radio" name="nilai" value="<?= $v ?>"><span><?= $k === 'mood' ? wajah($v, 18) : '' ?><?= e($vl) ?></span></label><?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <label class="f" data-untuk="susu obat"><span data-label-susu="Jumlah susu (ml)" data-label-obat="Nama obat & dosis">Nilai</span><input name="nilai" disabled maxlength="30"></label>
        <label class="f"><span>Keterangan</span><input name="detail" maxlength="255" placeholder="mis. menu, nama permainan, pesan untuk orang tua"></label>
        <div class="baris-f">
          <label class="f"><span>Jam</span><input type="time" name="waktu" value="<?= date('H:i') ?>"></label>
          <div class="f"><span>&nbsp;</span><button class="btn utama"><?= icon('centang') ?>Simpan catatan</button></div>
        </div>
      </form>
      <?php if ($tidur): ?>
        <form method="post" action="<?= url('aksi') ?>" class="kartu ungu-muda tidur-kartu">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="bangun"><input type="hidden" name="anak_id" value="<?= (int) $a['id'] ?>">
          <span><?= icon('bulan', 22) ?> <?= e($a['panggilan']) ?> tidur sejak <b><?= jam($tidur['waktu']) ?></b></span>
          <button class="btn ungu"><?= icon('senyum') ?>Bangun</button>
        </form>
      <?php endif; ?>
      <form method="post" action="<?= url('aksi') ?>" class="kartu peach-muda" id="jemput">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="pulang"><input type="hidden" name="anak_id" value="<?= (int) $a['id'] ?>">
        <h2>Dijemput</h2>
        <label class="f"><span>Penjemput</span><select name="dijemput" required><option value="">- pilih penjemput terdaftar -</option><?php foreach ($penjemput as $p): ?><option value="<?= e($p['nama']) ?>"><?= e($p['nama']) ?> (<?= e($p['ket']) ?>)</option><?php endforeach; ?></select>
          <small>Hanya orang tua, penjemput terdaftar, atau titipan dari portal orang tua hari ini. Cocokkan wajah / KTP.</small></label>
        <?php $d = hitung_denda(date('H:i:s')); if ($d): ?><p class="pesan info"><?= icon('jam') ?>Lewat jam tutup, denda <?= rupiah($d) ?> akan masuk tagihan.</p><?php endif; ?>
        <button class="btn peach"><?= icon('keluar') ?>Catat dijemput</button>
      </form>
    <?php else: ?>
      <section class="kartu"><h2>Ringkasan</h2><p><?= $h['status'] === 'hadir' ? 'Datang ' . jam($h['masuk']) . ', pulang ' . jam($h['pulang']) . ' dijemput ' . e($h['dijemput']) . ($h['denda'] ? '. Denda telat ' . rupiah($h['denda']) : '') : 'Hari ini ' . e(strtolower(STATUS_HADIR[$h['status']][0])) . ($h['keterangan'] ? ': ' . e($h['keterangan']) : '') ?>.</p></section>
    <?php endif; ?>
  <?php endif; ?>
    <?php if ($titip): ?><section class="kartu ungu-muda"><h2>Titipan penjemput hari ini</h2><?php foreach ($titip as $t): ?><p><b><?= e($t['nama']) ?></b> · <?= e($t['hubungan']) ?> <small class="muted">dikirim <?= date('H.i', strtotime($t['dibuat_at'])) ?></small></p><?php endforeach; ?></section><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
