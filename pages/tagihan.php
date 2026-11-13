<?php
$bulan = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['bulan'] ?? $_POST['bulan'] ?? '')) ? ($_GET['bulan'] ?? $_POST['bulan']) : date('Y-m');
$namaBulan = BULAN_ID[(int) substr($bulan, 5)] . ' ' . substr($bulan, 0, 4);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    if ($aksi === 'hitung') {
        // Hitung ulang tagihan yang belum lunas: paket bulanan = tarif tetap, harian = tarif x hari hadir, ditambah denda telat jemput
        $s = setelan(); $n = 0;
        db()->beginTransaction();
        foreach (q("SELECT a.id, a.paket, COUNT(k.id) hadir, COALESCE(SUM(k.denda), 0) denda FROM anak a
                    LEFT JOIN kehadiran k ON k.anak_id = a.id AND k.status = 'hadir' AND DATE_FORMAT(k.tanggal, '%Y-%m') = ?
                    WHERE a.aktif = 1 OR k.id IS NOT NULL GROUP BY a.id", [$bulan])->fetchAll() as $r) {
            if ($r['paket'] === 'harian' && !$r['hadir'] && !$r['denda']) continue;
            $biaya = $r['paket'] === 'bulanan' ? (int) $s['tarif_bulanan'] : (int) $r['hadir'] * (int) $s['tarif_harian'];
            $n += q("INSERT INTO tagihan (anak_id, bulan, paket, hari_hadir, biaya, denda, total) VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE paket = IF(status = 'lunas', paket, VALUES(paket)), hari_hadir = IF(status = 'lunas', hari_hadir, VALUES(hari_hadir)),
                     biaya = IF(status = 'lunas', biaya, VALUES(biaya)), denda = IF(status = 'lunas', denda, VALUES(denda)), total = IF(status = 'lunas', total, VALUES(total))",
                     [$r['id'], $bulan, $r['paket'], $r['hadir'], $biaya, $r['denda'], $biaya + $r['denda']])->rowCount() ? 1 : 0;
        }
        db()->commit();
        flash("Tagihan $namaBulan dihitung ulang. Tagihan yang sudah lunas tidak diubah.");
    } elseif ($aksi === 'bayar' || $aksi === 'batal') {
        require_admin();
        $t = q('SELECT t.*, a.panggilan FROM tagihan t JOIN anak a ON a.id = t.anak_id WHERE t.id = ?', [(int) $_POST['id']])->fetch();
        if ($t && $aksi === 'bayar' && $t['status'] === 'belum') {
            $m = in_array($_POST['metode'] ?? '', ['tunai', 'transfer', 'qris'], true) ? $_POST['metode'] : 'tunai';
            q("UPDATE tagihan SET status = 'lunas', metode = ?, dibayar_at = NOW() WHERE id = ? AND status = 'belum'", [$m, $t['id']]);
            flash('Tagihan ' . $t['panggilan'] . ' ' . rupiah($t['total']) . ' lunas (' . $m . ').');
        } elseif ($t && $aksi === 'batal' && $t['status'] === 'lunas') {
            q("UPDATE tagihan SET status = 'belum', metode = '', dibayar_at = NULL WHERE id = ?", [$t['id']]);
            flash('Pembayaran ' . $t['panggilan'] . ' dibatalkan.');
        }
    }
    redirect('tagihan', ['bulan' => $bulan]);
}
$rows = q('SELECT t.*, a.panggilan, a.nama, o.nama ortu, o.hp FROM tagihan t JOIN anak a ON a.id = t.anak_id JOIN ortu o ON o.id = a.ortu_id WHERE t.bulan = ? ORDER BY t.status, a.panggilan', [$bulan])->fetchAll();
$sum = ['total' => 0, 'lunas' => 0, 'belum' => 0];
foreach ($rows as $r) { $sum['total'] += $r['total']; $sum[$r['status']] += $r['total']; }
$title = 'Tagihan';
$sub = 'Paket bulanan ' . rupiah(setelan('tarif_bulanan')) . ' · harian ' . rupiah(setelan('tarif_harian')) . '/hari · denda telat jemput ' . rupiah(setelan('denda_15')) . ' per 15 menit';
require __DIR__ . '/../includes/header.php';
?>
<div class="baris-alat">
  <form class="filter" method="get"><input type="hidden" name="page" value="tagihan"><input type="month" name="bulan" value="<?= e($bulan) ?>" onchange="this.form.submit()" aria-label="Bulan"></form>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="hitung"><input type="hidden" name="bulan" value="<?= e($bulan) ?>"><button class="btn utama"><?= icon('dompet') ?>Hitung tagihan <?= e($namaBulan) ?></button></form>
</div>
<div class="ringkas">
  <div class="gelembung langit"><b><?= rupiah($sum['total']) ?></b><span>total tagihan</span></div>
  <div class="gelembung mint"><b><?= rupiah($sum['lunas']) ?></b><span>sudah dibayar</span></div>
  <div class="gelembung peach"><b><?= rupiah($sum['belum']) ?></b><span>belum dibayar</span></div>
</div>
<section class="kartu rata">
  <div class="tabel-wrap">
  <table class="tabel">
    <thead><tr><th>Anak</th><th>Paket</th><th class="kanan">Biaya</th><th class="kanan">Denda</th><th class="kanan">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t):
      $wa = 'https://wa.me/' . preg_replace('/^0/', '62', $t['hp']) . '?text=' . rawurlencode("Halo Ayah/Bunda {$t['panggilan']}, tagihan " . setelan('nama') . " bulan $namaBulan sebesar " . rupiah($t['total']) . ($t['denda'] ? ' (termasuk denda telat jemput ' . rupiah($t['denda']) . ')' : '') . '. Terima kasih.'); ?>
      <tr>
        <td class="nowrap"><span class="nama-mini"><?= avatar((int) $t['anak_id'], 30) ?><span><b><?= e($t['panggilan']) ?></b><small class="muted"><?= e($t['ortu']) ?></small></span></span></td>
        <td><?= e(PAKET[$t['paket']]) ?><?= $t['paket'] === 'harian' ? ' · ' . (int) $t['hari_hadir'] . ' hari' : '' ?></td>
        <td class="kanan"><?= rupiah($t['biaya']) ?></td><td class="kanan"><?= $t['denda'] ? rupiah($t['denda']) : '-' ?></td><td class="kanan"><b><?= rupiah($t['total']) ?></b></td>
        <td><?= $t['status'] === 'lunas' ? chip('mint', 'Lunas ' . $t['metode']) : chip('peach', 'Belum') ?></td>
        <td class="kanan nowrap">
          <?php if ($t['status'] === 'belum'): ?>
            <a class="ibtn" href="<?= e($wa) ?>" target="_blank" rel="noopener" title="Ingatkan lewat WhatsApp" aria-label="Ingatkan <?= e($t['ortu']) ?>"><?= icon('kirim', 16) ?></a>
            <?php if (is_admin()): ?><form method="post" class="inline bayar"><?= csrf_field() ?><input type="hidden" name="aksi" value="bayar"><input type="hidden" name="bulan" value="<?= e($bulan) ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <select name="metode" aria-label="Metode"><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></select><button class="btn kecil mint">Lunas</button></form><?php endif; ?>
          <?php elseif (is_admin()): ?>
            <form method="post" class="inline" data-yakin="Batalkan pembayaran ini?"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="bulan" value="<?= e($bulan) ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="tautan kecil">batalkan</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="kosong">Belum ada tagihan bulan ini. Tekan <b>Hitung tagihan</b>.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
