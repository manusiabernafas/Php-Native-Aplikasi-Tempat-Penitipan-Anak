<?php
$_s = setelan();
$_menu = [['hari-ini', 'Hari ini', 'hati'], ['anak', 'Anak', 'users'], ['ortu-data', 'Orang tua', 'telepon'], ['kehadiran', 'Kehadiran', 'kalender'],
          ['tagihan', 'Tagihan', 'dompet'], ['menu', 'Menu makan', 'sendok']];
if (is_admin()) array_push($_menu, ['pengaturan', 'Pengaturan', 'setel'], ['pengguna', 'Pengasuh', 'kunci']);
$_cur = explode('.', $_GET['page'] ?? 'hari-ini')[0];
$_flash = flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Hari ini') ?> · <?= e($_s['nama']) ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body>
<header class="atas">
  <a class="merek" href="<?= url('hari-ini') ?>"><img src="assets/favicon.svg" alt="" width="42" height="42"><span><b><?= e($_s['nama']) ?></b><small>Ruang pengasuh</small></span></a>
  <nav class="menu-pil" aria-label="Menu utama">
    <?php foreach ($_menu as [$_k, $_l, $_i]): ?>
      <a href="<?= url($_k) ?>" class="<?= $_cur === $_k || ($_cur === 'harian' && $_k === 'hari-ini') ? 'aktif' : '' ?>"><?= icon($_i, 17) ?><span><?= e($_l) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="saya">
    <a class="saya-nama" href="<?= url('akun') ?>" title="Akun"><span class="av"><?= e(mb_strtoupper(mb_substr(user()['nama'], 0, 1))) ?></span><span><?= e(explode(' ', user()['nama'])[0] . ' ' . (explode(' ', user()['nama'])[1] ?? '')) ?></span></a>
    <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="ibtn" title="Keluar" aria-label="Keluar"><?= icon('keluar', 18) ?></button></form>
  </div>
</header>
<main class="isi">
  <?php if (empty($tanpaJudul)): ?>
  <div class="judul-hal">
    <div>
      <?php if (!empty($kembali)): ?><a class="kembali" href="<?= e($kembali[0]) ?>"><?= icon('kiri', 15) ?> <?= e($kembali[1]) ?></a><?php endif; ?>
      <h1><?= e($title ?? '') ?></h1>
      <?php if (!empty($sub)): ?><p><?= $sub ?></p><?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?><div class="aksi"><?= $actions ?></div><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($_flash): ?><div class="pesan <?= e($_flash['type']) ?>" role="status"><?= icon($_flash['type'] === 'error' ? 'awas' : ($_flash['type'] === 'info' ? 'info' : 'centang')) ?> <span><?= e($_flash['msg']) ?></span></div><?php endif; ?>
