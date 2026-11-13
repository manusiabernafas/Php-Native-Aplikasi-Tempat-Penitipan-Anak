<?php
$cari = trim((string) ($_GET['cari'] ?? ''));
$kel = isset(KELOMPOK[$_GET['kelompok'] ?? '']) ? $_GET['kelompok'] : '';
$w = ['1=1']; $p = [];
if ($cari !== '') { $w[] = '(a.nama LIKE ? OR a.panggilan LIKE ? OR o.nama LIKE ?)'; array_push($p, "%$cari%", "%$cari%", "%$cari%"); }
if ($kel) { $w[] = 'a.kelompok = ?'; $p[] = $kel; }
if (empty($_GET['semua'])) $w[] = 'a.aktif = 1';
$rows = q("SELECT a.*, o.nama ortu_nama, o.hp, (SELECT COUNT(*) FROM penjemput j WHERE j.anak_id = a.id) n_jemput,
           (SELECT COUNT(*) FROM kehadiran k WHERE k.anak_id = a.id AND k.status = 'hadir' AND k.tanggal >= CURDATE() - INTERVAL 30 DAY) hadir30
           FROM anak a JOIN ortu o ON o.id = a.ortu_id WHERE " . implode(' AND ', $w) . ' ORDER BY a.panggilan', $p)->fetchAll();
$title = 'Anak';
$sub = count($rows) . ' anak' . (empty($_GET['semua']) ? ' aktif' : '');
$actions = '<a class="btn utama" href="' . url('anak.form') . '">' . icon('plus') . 'Tambah anak</a>';
require __DIR__ . '/../includes/header.php';
?>
<form class="filter" method="get">
  <input type="hidden" name="page" value="anak">
  <input name="cari" value="<?= e($cari) ?>" placeholder="Cari nama anak atau orang tua" aria-label="Cari">
  <select name="kelompok" aria-label="Kelompok" onchange="this.form.submit()"><option value="">Semua kelompok</option><?php foreach (KELOMPOK as $k => $l): ?><option value="<?= $k ?>" <?= $kel === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <label class="cek"><input type="checkbox" name="semua" value="1" <?= !empty($_GET['semua']) ? 'checked' : '' ?> onchange="this.form.submit()"> Tampilkan yang sudah keluar</label>
  <button class="btn"><?= icon('cari') ?>Cari</button>
</form>
<div class="grid-anak lebar">
  <?php foreach ($rows as $a): ?>
    <a class="kartu-anak tautan-kartu <?= $a['aktif'] ? '' : 'redup' ?>" href="<?= url('anak.form', ['id' => $a['id']]) ?>">
      <span class="kartu-kepala"><?= avatar((int) $a['id'], 56) ?><span><b><?= e($a['panggilan']) ?></b><small><?= e($a['nama']) ?></small></span></span>
      <span class="info-anak"><?= e(umur($a['tgl_lahir'])) ?> · <?= e(KELOMPOK[$a['kelompok']]) ?> · paket <?= e(strtolower(PAKET[$a['paket']])) ?></span>
      <span class="info-anak"><?= icon('telepon', 14) ?> <?= e($a['ortu_nama']) ?> · <?= (int) $a['n_jemput'] ?> penjemput lain</span>
      <span class="lencana"><?= $a['alergi'] ? chip('merah', 'Alergi ' . $a['alergi']) : '' ?><?= chip('mint', (int) $a['hadir30'] . ' hadir / 30 hari') ?><?= $a['aktif'] ? '' : chip('abu', 'Sudah keluar') ?></span>
    </a>
  <?php endforeach; if (!$rows): ?><p class="kosong-lucu"><?= avatar(2, 64) ?><span>Tidak ada anak yang cocok.</span></p><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
