<?php
if (ortu()) redirect('ortu');
$err = null;
$hp = preg_replace('/\D/', '', (string) ($_POST['hp'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PIN hanya 6 angka, jadi batasi percobaan per IP di database (tidak bisa diakali dengan hapus cookie)
    $kunci = 'ortu:' . ip();
    if (coba_terkunci($kunci, 5, 900)) {
        $err = 'Terlalu banyak percobaan. Coba lagi 15 menit lagi atau hubungi pengasuh.';
    } else {
        $o = q('SELECT * FROM ortu WHERE hp = ?', [$hp])->fetch();
        if (!$o || !$o['aktif'] || !password_verify((string) ($_POST['pin'] ?? ''), $o['pin'])) {
            coba_gagal($kunci, 900);
            $err = 'Nomor HP atau PIN tidak cocok.';
        } else {
            coba_reset($kunci);
            session_regenerate_id(true);
            $_SESSION['ortu'] = ['id' => (int) $o['id'], 'nama' => $o['nama']];
            redirect('ortu');
        }
    }
}
$title = 'Portal Ayah & Bunda';
require __DIR__ . '/../../includes/ortu_header.php';
?>
<section class="kartu-masuk">
  <div class="hewan-baris" aria-hidden="true"><?= avatar(3, 54) . avatar(10, 54) . avatar(17, 54) . avatar(4, 54) ?></div>
  <h1>Kabar si kecil hari ini</h1>
  <p class="muted">Lihat jam antar-jemput, makan, tidur, dan kegiatan anak langsung dari HP.</p>
  <?php if ($err): ?><p class="pesan error" role="alert"><?= icon('awas') ?><span><?= e($err) ?></span></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label class="f"><span>Nomor HP terdaftar</span><input name="hp" value="<?= e($hp) ?>" inputmode="tel" autocomplete="tel" required placeholder="0812xxxxxxxx"></label>
    <label class="f"><span>PIN 6 angka</span><input name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="current-password" required></label>
    <button class="btn utama blok besar">Lihat kabar si kecil</button>
  </form>
  <p class="kecil tengah muted">Lupa PIN? Minta pengasuh membuatkan PIN baru.<br><a href="<?= url('login') ?>">Masuk sebagai pengasuh</a></p>
</section>
</main>
</body>
</html>
