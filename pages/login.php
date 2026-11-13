<?php
if (user()) redirect('hari-ini');
$err = null;
$username = strtolower(trim((string) ($_POST['username'] ?? '')));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kunci = 'login:' . ip();
    if (coba_terkunci($kunci, 5, 600)) {
        $err = 'Terlalu banyak percobaan masuk. Coba lagi 10 menit lagi.';
    } else {
        $u = q('SELECT * FROM pengguna WHERE username = ?', [$username])->fetch();
        if (!$u || !password_verify((string) ($_POST['password'] ?? ''), $u['password'])) {
            coba_gagal($kunci, 600);
            $err = 'Username atau kata sandi salah.';
        } elseif (!$u['aktif']) {
            $err = 'Akun ini dinonaktifkan.';
        } else {
            coba_reset($kunci);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role']];
            redirect('hari-ini');
        }
    }
}
$title = 'Masuk pengasuh';
require __DIR__ . '/../includes/ortu_header.php';
?>
<section class="kartu-masuk">
  <div class="hewan-baris" aria-hidden="true"><?= avatar(0, 54) . avatar(7, 54) . avatar(14, 54) . avatar(21, 54) ?></div>
  <h1>Halo, Kakak Pengasuh!</h1>
  <p class="muted">Masuk untuk mencatat antar-jemput dan kegiatan si kecil hari ini.</p>
  <?php if ($err): ?><p class="pesan error" role="alert"><?= icon('awas') ?><span><?= e($err) ?></span></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label class="f"><span>Username</span><input name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></label>
    <label class="f"><span>Kata sandi</span><input name="password" type="password" autocomplete="current-password" required></label>
    <button class="btn utama blok besar">Masuk</button>
  </form>
  <p class="kecil tengah"><a href="<?= url('ortu.masuk') ?>">Saya orang tua, masuk ke portal Ayah &amp; Bunda</a></p>
</section>
</main>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
