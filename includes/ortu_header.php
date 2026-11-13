<?php $_s = setelan(); $_flash = flash(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Portal orang tua') ?> · <?= e($_s['nama']) ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body class="portal">
<header class="atas portal-atas">
  <a class="merek" href="<?= url(ortu() ? 'ortu' : 'ortu.masuk') ?>"><img src="assets/favicon.svg" alt="" width="40" height="40"><span><b><?= e($_s['nama']) ?></b><small>Portal Ayah &amp; Bunda</small></span></a>
  <?php if (ortu()): ?>
  <nav class="menu-pil" aria-label="Menu orang tua">
    <a href="<?= url('ortu') ?>" class="<?= ($_GET['page'] ?? '') === 'ortu' || ($_GET['page'] ?? '') === 'ortu.anak' ? 'aktif' : '' ?>"><?= icon('hati', 17) ?><span>Si kecil</span></a>
    <a href="<?= url('ortu.kirim') ?>" class="<?= ($_GET['page'] ?? '') === 'ortu.kirim' ? 'aktif' : '' ?>"><?= icon('kirim', 17) ?><span>Kabari</span></a>
    <a href="<?= url('ortu.tagihan') ?>" class="<?= ($_GET['page'] ?? '') === 'ortu.tagihan' ? 'aktif' : '' ?>"><?= icon('dompet', 17) ?><span>Tagihan</span></a>
  </nav>
  <form method="post" action="<?= url('ortu.keluar') ?>"><?= csrf_field() ?><button class="ibtn" title="Keluar" aria-label="Keluar"><?= icon('keluar', 18) ?></button></form>
  <?php endif; ?>
</header>
<main class="isi sempit">
  <?php if ($_flash): ?><div class="pesan <?= e($_flash['type']) ?>" role="status"><?= icon($_flash['type'] === 'error' ? 'awas' : 'centang') ?> <span><?= e($_flash['msg']) ?></span></div><?php endif; ?>
