<?php
$id = (int) ($_GET['id'] ?? 0);
$a = $id ? q('SELECT * FROM anak WHERE id = ?', [$id])->fetch() : null;
if ($id && !$a) redirect('anak');
$d = $a ?: ['ortu_id' => (int) ($_GET['ortu'] ?? 0), 'nama' => '', 'panggilan' => '', 'jk' => 'P', 'tgl_lahir' => '', 'kelompok' => 'batita', 'paket' => 'bulanan', 'alergi' => '', 'catatan_kesehatan' => '', 'aktif' => 1];
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? 'simpan');
    if ($aksi === 'tambah_jemput' && $a) {
        $nm = trim((string) ($_POST['nama'] ?? '')); $hub = trim((string) ($_POST['hubungan'] ?? ''));
        if ($nm === '' || $hub === '' || mb_strlen($nm) > 100 || mb_strlen($hub) > 40) flash('Isi nama dan hubungan penjemput.', 'error');
        else { q('INSERT INTO penjemput (anak_id, nama, hubungan, hp) VALUES (?, ?, ?, ?)', [$id, $nm, $hub, mb_substr(preg_replace('/[^\d+ -]/', '', (string) ($_POST['hp'] ?? '')), 0, 20)]); flash($nm . ' ditambahkan sebagai penjemput.'); }
        redirect('anak.form', ['id' => $id]);
    }
    if ($aksi === 'hapus_jemput' && $a) {
        q('DELETE FROM penjemput WHERE id = ? AND anak_id = ?', [(int) $_POST['jemput_id'], $id]);
        flash('Penjemput dihapus.');
        redirect('anak.form', ['id' => $id]);
    }
    foreach (['nama', 'panggilan', 'jk', 'tgl_lahir', 'kelompok', 'paket', 'alergi', 'catatan_kesehatan'] as $k) $d[$k] = trim((string) ($_POST[$k] ?? ''));
    $d['ortu_id'] = (int) ($_POST['ortu_id'] ?? 0);
    $d['aktif'] = empty($_POST['aktif']) ? 0 : 1;
    if (!q('SELECT id FROM ortu WHERE id = ?', [$d['ortu_id']])->fetch()) $err[] = 'Pilih orang tua (tambahkan dulu di menu Orang tua bila belum ada).';
    if ($d['nama'] === '' || mb_strlen($d['nama']) > 100) $err[] = 'Nama lengkap wajib diisi.';
    if ($d['panggilan'] === '' || mb_strlen($d['panggilan']) > 30) $err[] = 'Nama panggilan wajib diisi (maks. 30 huruf).';
    $t = DateTime::createFromFormat('!Y-m-d', $d['tgl_lahir']);
    if (!$t || $t->format('Y-m-d') !== $d['tgl_lahir'] || $t > new DateTime('today') || $t < new DateTime('-8 years')) $err[] = 'Tanggal lahir tidak valid.';
    if (!in_array($d['jk'], ['L', 'P'], true)) $d['jk'] = 'P';
    if (!isset(KELOMPOK[$d['kelompok']])) $err[] = 'Pilih kelompok.';
    if (!isset(PAKET[$d['paket']])) $err[] = 'Pilih paket.';
    if (!$err) {
        $v = [$d['ortu_id'], $d['nama'], $d['panggilan'], $d['jk'], $d['tgl_lahir'], $d['kelompok'], $d['paket'], mb_substr($d['alergi'], 0, 200), mb_substr($d['catatan_kesehatan'], 0, 255), $d['aktif']];
        if ($id) q('UPDATE anak SET ortu_id=?, nama=?, panggilan=?, jk=?, tgl_lahir=?, kelompok=?, paket=?, alergi=?, catatan_kesehatan=?, aktif=? WHERE id = ?', [...$v, $id]);
        else { q('INSERT INTO anak (ortu_id, nama, panggilan, jk, tgl_lahir, kelompok, paket, alergi, catatan_kesehatan, aktif) VALUES (?,?,?,?,?,?,?,?,?,?)', $v); $id = (int) db()->lastInsertId(); }
        flash('Data ' . $d['panggilan'] . ' disimpan.');
        redirect('anak.form', ['id' => $id]);
    }
}
$ortuList = q('SELECT id, nama, hp FROM ortu WHERE aktif = 1 ORDER BY nama')->fetchAll();
$jemput = $a ? q('SELECT * FROM penjemput WHERE anak_id = ? ORDER BY nama', [$id])->fetchAll() : [];
$title = $a ? $a['panggilan'] : 'Tambah anak';
$kembali = [url('anak'), 'Daftar anak'];
if ($a) $actions = '<a class="btn" href="' . url('harian', ['anak' => $id]) . '">' . icon('hati') . 'Catatan harian</a>';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($err): ?><div class="pesan error"><?= icon('awas') ?><span><?= implode('<br>', array_map('e', $err)) ?></span></div><?php endif; ?>
<div class="grid dua lebar-kiri">
  <form method="post" class="kartu">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
    <div class="kepala-form"><?= avatar($id ?: 5, 64) ?><h2><?= $a ? 'Data si kecil' : 'Anak baru' ?></h2></div>
    <label class="f"><span>Orang tua</span><select name="ortu_id" required><option value="">- pilih -</option><?php foreach ($ortuList as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (int) $d['ortu_id'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nama']) ?> (<?= e($o['hp']) ?>)</option><?php endforeach; ?></select><small><a href="<?= url('ortu-data') ?>">Tambah orang tua baru</a></small></label>
    <div class="baris-f">
      <label class="f"><span>Nama lengkap</span><input name="nama" value="<?= e($d['nama']) ?>" required maxlength="100"></label>
      <label class="f"><span>Panggilan</span><input name="panggilan" value="<?= e($d['panggilan']) ?>" required maxlength="30"></label>
    </div>
    <div class="baris-f">
      <label class="f"><span>Tanggal lahir</span><input type="date" name="tgl_lahir" value="<?= e($d['tgl_lahir']) ?>" required></label>
      <label class="f"><span>Jenis kelamin</span><select name="jk"><option value="P">Perempuan</option><option value="L" <?= $d['jk'] === 'L' ? 'selected' : '' ?>>Laki-laki</option></select></label>
    </div>
    <div class="baris-f">
      <label class="f"><span>Kelompok</span><select name="kelompok"><?php foreach (KELOMPOK as $k => $l): ?><option value="<?= $k ?>" <?= $d['kelompok'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      <label class="f"><span>Paket</span><select name="paket"><?php foreach (PAKET as $k => $l): ?><option value="<?= $k ?>" <?= $d['paket'] === $k ? 'selected' : '' ?>><?= $l ?> (<?= rupiah(setelan($k === 'bulanan' ? 'tarif_bulanan' : 'tarif_harian')) ?><?= $k === 'harian' ? '/hari' : '/bulan' ?>)</option><?php endforeach; ?></select></label>
    </div>
    <label class="f"><span>Alergi</span><input name="alergi" value="<?= e($d['alergi']) ?>" maxlength="200" placeholder="kosongkan bila tidak ada"></label>
    <label class="f"><span>Catatan kesehatan</span><input name="catatan_kesehatan" value="<?= e($d['catatan_kesehatan']) ?>" maxlength="255" placeholder="mis. riwayat kejang demam, asma"></label>
    <label class="cek"><input type="checkbox" name="aktif" value="1" <?= $d['aktif'] ? 'checked' : '' ?>> Masih dititipkan (aktif)</label>
    <button class="btn utama"><?= icon('centang') ?>Simpan</button>
  </form>
  <?php if ($a): ?>
  <section class="kartu">
    <h2>Boleh menjemput</h2>
    <p class="kecil muted">Selain orang tua, hanya nama di daftar ini (atau titipan dari portal orang tua hari itu) yang bisa dipilih saat mencatat jemput.</p>
    <ul class="daftar-jemput">
      <li><?= icon('hati', 16) ?><span><b><?= e(q('SELECT nama FROM ortu WHERE id = ?', [$a['ortu_id']])->fetchColumn()) ?></b> · Orang tua</span></li>
      <?php foreach ($jemput as $j): ?>
        <li><?= icon('user', 16) ?><span><b><?= e($j['nama']) ?></b> · <?= e($j['hubungan']) ?><?= $j['hp'] ? ' · ' . e($j['hp']) : '' ?></span>
          <form method="post" data-yakin="Hapus <?= e($j['nama']) ?> dari daftar penjemput?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus_jemput"><input type="hidden" name="jemput_id" value="<?= (int) $j['id'] ?>"><button class="ibtn kecil" aria-label="Hapus <?= e($j['nama']) ?>"><?= icon('sampah', 15) ?></button></form></li>
      <?php endforeach; ?>
    </ul>
    <form method="post" class="tambah-jemput">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah_jemput">
      <div class="baris-f">
        <label class="f"><span>Nama</span><input name="nama" maxlength="100" required></label>
        <label class="f"><span>Hubungan</span><input name="hubungan" maxlength="40" required placeholder="Nenek, sopir, ART"></label>
      </div>
      <label class="f"><span>HP (opsional)</span><input name="hp" maxlength="20" inputmode="tel"></label>
      <button class="btn"><?= icon('plus') ?>Tambah penjemput</button>
    </form>
  </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
