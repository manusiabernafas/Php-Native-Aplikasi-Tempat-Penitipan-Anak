<?php
require __DIR__ . '/includes/bootstrap.php';

// Halaman portal orang tua (login HP + PIN) dan halaman masuk tidak butuh akun pengasuh
$portal = ['ortu.masuk', 'ortu', 'ortu.anak', 'ortu.kirim', 'ortu.tagihan', 'ortu.keluar'];

$routes = [
    'login'        => 'pages/login.php',
    'logout'       => 'pages/logout.php',
    'hari-ini'     => 'pages/hari_ini.php',
    'harian'       => 'pages/harian.php',
    'aksi'         => 'pages/aksi.php',
    'anak'         => 'pages/anak.php',
    'anak.form'    => 'pages/anak_form.php',
    'ortu-data'    => 'pages/ortu_data.php',
    'kehadiran'    => 'pages/kehadiran.php',
    'tagihan'      => 'pages/tagihan.php',
    'menu'         => 'pages/menu.php',
    'pengaturan'   => 'pages/pengaturan.php',
    'pengguna'     => 'pages/pengguna.php',
    'akun'         => 'pages/akun.php',
    'ortu.masuk'   => 'pages/ortu/masuk.php',
    'ortu'         => 'pages/ortu/beranda.php',
    'ortu.anak'    => 'pages/ortu/anak.php',
    'ortu.kirim'   => 'pages/ortu/kirim.php',
    'ortu.tagihan' => 'pages/ortu/tagihan.php',
    'ortu.keluar'  => 'pages/ortu/keluar.php',
];

$page = $_GET['page'] ?? (ortu() && !user() ? 'ortu' : 'hari-ini');
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'hari-ini';
}
if (in_array($page, $portal, true)) {
    if (!in_array($page, ['ortu.masuk', 'ortu.keluar'], true)) require_ortu();
} elseif ($page !== 'login') {
    require_login();
}
verify_csrf();

require __DIR__ . '/includes/linimasa.php';
require __DIR__ . '/' . $routes[$page];
