<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (range(1, 5) as $h) {
        $v = array_map(fn($k) => mb_substr(trim((string) ($_POST[$k][$h] ?? '')), 0, 120), ['pagi', 'siang', 'sore']);
        q('INSERT INTO menu (hari, pagi, siang, sore) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE pagi = VALUES(pagi), siang = VALUES(siang), sore = VALUES(sore)', [$h, ...$v]);
    }
    flash('Menu minggu ini disimpan dan langsung tampil di portal orang tua.');
    redirect('menu');
}
$menu = q('SELECT * FROM menu')->fetchAll(PDO::FETCH_UNIQUE);
$title = 'Menu makan';
$sub = 'Menu mingguan tampil di papan hari ini dan portal orang tua.';
require __DIR__ . '/../includes/header.php';
?>
<form method="post" class="menu-minggu">
  <?= csrf_field() ?>
  <?php foreach (range(1, 5) as $h): $m = $menu[$h] ?? ['pagi' => '', 'siang' => '', 'sore' => '']; ?>
    <fieldset class="kartu <?= ['', 'peach-muda', 'mint-muda', 'ungu-muda', 'kuning-muda', 'langit-muda'][$h] ?> <?= (int) date('N') === $h ? 'hari-ini' : '' ?>">
      <legend><?= HARI[$h] ?></legend>
      <label class="f"><span>Snack pagi</span><input name="pagi[<?= $h ?>]" value="<?= e($m['pagi']) ?>" maxlength="120"></label>
      <label class="f"><span>Makan siang</span><input name="siang[<?= $h ?>]" value="<?= e($m['siang']) ?>" maxlength="120"></label>
      <label class="f"><span>Snack sore</span><input name="sore[<?= $h ?>]" value="<?= e($m['sore']) ?>" maxlength="120"></label>
    </fieldset>
  <?php endforeach; ?>
  <div class="aksi-bawah"><button class="btn utama besar"><?= icon('centang') ?>Simpan menu</button></div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
