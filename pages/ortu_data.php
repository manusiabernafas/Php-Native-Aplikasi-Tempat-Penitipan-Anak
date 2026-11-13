<?php
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['aksi'] ?? '') === 'pin') {
        // PIN baru dibuat acak dan hanya ditampilkan sekali; di database tersimpan sebagai hash
        $pin = (string) random_int(100000, 999999);
        $o = q('SELECT nama FROM ortu WHERE id = ?', [$id])->fetch();
        if ($o) {
            q('UPDATE ortu SET pin = ? WHERE id = ?', [password_hash($pin, PASSWORD_DEFAULT), $id]);
            flash('PIN baru untuk ' . $o['nama'] . ': ' . $pin . '. Sampaikan langsung ke orang tua; PIN ini tidak bisa dilihat lagi.', 'info');
        }
        redirect('ortu-data');
    }
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $hp = preg_replace('/\D/', '', (string) ($_POST['hp'] ?? ''));
    if (str_starts_with($hp, '62')) $hp = '0' . substr($hp, 2);
    $alamat = mb_substr(trim((string) ($_POST['alamat'] ?? '')), 0, 200);
    $aktif = empty($_POST['aktif']) ? 0 : 1;
    if ($nama === '' || mb_strlen($nama) > 100) $err = 'Nama orang tua wajib diisi.';
    elseif (!preg_match('/^08\d{8,12}$/', $hp)) $err = 'Nomor HP harus diawali 08 (10-14 angka). Nomor ini dipakai untuk masuk portal.';
    elseif (q('SELECT id FROM ortu WHERE hp = ? AND id <> ?', [$hp, $id])->fetch()) $err = 'Nomor HP sudah terdaftar untuk orang tua lain.';
    else {
        if ($id) {
            q('UPDATE ortu SET nama = ?, hp = ?, alamat = ?, aktif = ? WHERE id = ?', [$nama, $hp, $alamat, $aktif, $id]);
            flash('Data ' . $nama . ' disimpan.');
        } else {
            $pin = (string) random_int(100000, 999999);
            q('INSERT INTO ortu (nama, hp, pin, alamat, aktif) VALUES (?, ?, ?, ?, ?)', [$nama, $hp, password_hash($pin, PASSWORD_DEFAULT), $alamat, $aktif]);
            $id = (int) db()->lastInsertId();
            flash($nama . ' ditambahkan. PIN portal: ' . $pin . ' (sampaikan ke orang tua, tidak bisa dilihat lagi). Sekarang tambahkan data anaknya.', 'info');
            redirect('anak.form', ['ortu' => $id]);
        }
        redirect('ortu-data');
    }
}
$rows = q("SELECT o.*, GROUP_CONCAT(a.panggilan ORDER BY a.panggilan SEPARATOR ', ') anak FROM ortu o LEFT JOIN anak a ON a.ortu_id = o.id AND a.aktif = 1 GROUP BY o.id ORDER BY o.nama")->fetchAll();
$ubah = isset($_GET['ubah']) ? q('SELECT * FROM ortu WHERE id = ?', [(int) $_GET['ubah']])->fetch() : null;
$title = 'Orang tua';
$sub = 'Orang tua masuk ke portal dengan nomor HP + PIN 6 angka untuk melihat kegiatan anak, mengabari izin, dan menitipkan penjemput.';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($err): ?><div class="pesan error"><?= icon('awas') ?><span><?= e($err) ?></span></div><?php endif; ?>
<div class="grid dua lebar-kiri">
  <section class="kartu rata">
    <div class="tabel-wrap">
    <table class="tabel">
      <thead><tr><th>Orang tua</th><th>Anak</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $o): ?>
        <tr><td><b><?= e($o['nama']) ?></b><div class="kecil muted"><?= e($o['hp']) ?></div></td><td><?= e($o['anak'] ?? '-') ?></td>
          <td><?= $o['aktif'] ? chip('mint', 'aktif') : chip('abu', 'nonaktif') ?></td>
          <td class="kanan nowrap">
            <form method="post" class="inline" data-yakin="Buat PIN baru untuk <?= e($o['nama']) ?>? PIN lama tidak berlaku lagi."><?= csrf_field() ?><input type="hidden" name="aksi" value="pin"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><button class="btn kecil"><?= icon('kunci', 14) ?>PIN baru</button></form>
            <a class="ibtn" href="<?= url('ortu-data', ['ubah' => $o['id']]) ?>" aria-label="Ubah <?= e($o['nama']) ?>"><?= icon('pena', 16) ?></a></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
  <form method="post" class="kartu">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($ubah['id'] ?? 0) ?>">
    <h2><?= $ubah ? 'Ubah orang tua' : 'Orang tua baru' ?></h2>
    <label class="f"><span>Nama</span><input name="nama" value="<?= e($ubah['nama'] ?? ($_POST['nama'] ?? '')) ?>" required maxlength="100"></label>
    <label class="f"><span>Nomor HP (WhatsApp)</span><input name="hp" value="<?= e($ubah['hp'] ?? ($_POST['hp'] ?? '')) ?>" required inputmode="tel" placeholder="0812xxxxxxxx"></label>
    <label class="f"><span>Alamat</span><input name="alamat" value="<?= e($ubah['alamat'] ?? ($_POST['alamat'] ?? '')) ?>" maxlength="200"></label>
    <label class="cek"><input type="checkbox" name="aktif" value="1" <?= !$ubah || $ubah['aktif'] ? 'checked' : '' ?>> Boleh masuk portal</label>
    <button class="btn utama"><?= icon('centang') ?>Simpan</button>
    <?php if ($ubah): ?><a class="btn" href="<?= url('ortu-data') ?>">Batal</a><?php else: ?><p class="kecil muted">PIN 6 angka dibuat otomatis setelah disimpan.</p><?php endif; ?>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
