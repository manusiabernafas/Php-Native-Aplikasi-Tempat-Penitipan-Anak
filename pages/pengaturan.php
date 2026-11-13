<?php
require_admin();
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [];
    foreach (['nama', 'alamat', 'telepon', 'jam_buka', 'jam_tutup', 'pesan_ortu'] as $k) $d[$k] = trim((string) ($_POST[$k] ?? ''));
    foreach (['toleransi', 'denda_15', 'tarif_harian', 'tarif_bulanan'] as $k) $d[$k] = (string) max(0, (int) preg_replace('/\D/', '', (string) ($_POST[$k] ?? '0')));
    if ($d['nama'] === '' || mb_strlen($d['nama']) > 80) $err = 'Nama daycare wajib diisi.';
    elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d['jam_buka']) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d['jam_tutup']) || $d['jam_tutup'] <= $d['jam_buka']) $err = 'Jam tutup harus setelah jam buka.';
    elseif ((int) $d['toleransi'] > 120) $err = 'Toleransi maksimal 120 menit.';
    else {
        $d['pesan_ortu'] = mb_substr($d['pesan_ortu'], 0, 500);
        simpan_setelan($d);
        flash('Pengaturan disimpan.');
        redirect('pengaturan');
    }
}
$s = array_merge(setelan(), $_SERVER['REQUEST_METHOD'] === 'POST' ? array_intersect_key($_POST, PENGATURAN_AWAL) : []);
$title = 'Pengaturan';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($err): ?><div class="pesan error"><?= icon('awas') ?><span><?= e($err) ?></span></div><?php endif; ?>
<form method="post" class="grid dua">
  <?= csrf_field() ?>
  <section class="kartu">
    <h2>Daycare</h2>
    <label class="f"><span>Nama</span><input name="nama" value="<?= e($s['nama']) ?>" required maxlength="80"></label>
    <label class="f"><span>Alamat</span><input name="alamat" value="<?= e($s['alamat']) ?>" maxlength="200"></label>
    <label class="f"><span>Telepon / WhatsApp</span><input name="telepon" value="<?= e($s['telepon']) ?>" maxlength="30"></label>
    <label class="f"><span>Pesan di portal orang tua</span><textarea name="pesan_ortu" rows="3" maxlength="500"><?= e($s['pesan_ortu']) ?></textarea></label>
  </section>
  <section class="kartu">
    <h2>Jam & biaya</h2>
    <div class="baris-f">
      <label class="f"><span>Jam buka</span><input type="time" name="jam_buka" value="<?= e($s['jam_buka']) ?>" required></label>
      <label class="f"><span>Jam tutup</span><input type="time" name="jam_tutup" value="<?= e($s['jam_tutup']) ?>" required></label>
    </div>
    <div class="baris-f">
      <label class="f"><span>Toleransi jemput (menit)</span><input name="toleransi" value="<?= e($s['toleransi']) ?>" inputmode="numeric"></label>
      <label class="f"><span>Denda per 15 menit</span><input name="denda_15" value="<?= e($s['denda_15']) ?>" inputmode="numeric"></label>
    </div>
    <div class="baris-f">
      <label class="f"><span>Tarif bulanan</span><input name="tarif_bulanan" value="<?= e($s['tarif_bulanan']) ?>" inputmode="numeric"></label>
      <label class="f"><span>Tarif harian</span><input name="tarif_harian" value="<?= e($s['tarif_harian']) ?>" inputmode="numeric"></label>
    </div>
    <p class="kecil muted">Contoh: tutup <?= e($s['jam_tutup']) ?>, toleransi <?= (int) $s['toleransi'] ?> menit. Dijemput 20 menit setelah batas toleransi = 2 x <?= rupiah($s['denda_15']) ?>.</p>
  </section>
  <div class="aksi-bawah"><button class="btn utama besar"><?= icon('centang') ?>Simpan pengaturan</button></div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
