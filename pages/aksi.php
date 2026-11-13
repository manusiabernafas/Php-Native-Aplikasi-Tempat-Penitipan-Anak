<?php
// Semua perubahan harian dari pengasuh: datang, dijemput, izin/sakit, catatan, bangun tidur, hapus catatan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('hari-ini');
$aksi = (string) ($_POST['aksi'] ?? '');
$hariIni = date('Y-m-d');
$sekarang = date('H:i:s');
$anak = q('SELECT * FROM anak WHERE id = ? AND aktif = 1', [(int) ($_POST['anak_id'] ?? 0)])->fetch();
if (!$anak) { flash('Data anak tidak ditemukan.', 'error'); redirect('hari-ini'); }
$id = (int) $anak['id'];
$balik = fn(array $x = []) => redirect('harian', ['anak' => $id] + $x);
$hadir = q('SELECT * FROM kehadiran WHERE anak_id = ? AND tanggal = ?', [$id, $hariIni])->fetch();
$adaDiSini = $hadir && $hadir['masuk'] && !$hadir['pulang'];

if ($aksi === 'masuk') {
    $suhuIn = str_replace(',', '.', trim((string) ($_POST['suhu'] ?? '')));
    $suhu = $suhuIn === '' ? null : (float) $suhuIn;
    $diantar = mb_substr(trim((string) ($_POST['diantar'] ?? '')), 0, 100);
    if ($hadir && $hadir['masuk']) { flash($anak['panggilan'] . ' sudah tercatat datang pukul ' . jam($hadir['masuk']) . '.', 'error'); $balik(); }
    if ($suhu !== null && ($suhu < 34 || $suhu > 42)) { flash('Suhu tidak wajar. Isi dalam derajat Celsius, mis. 36,5.', 'error'); redirect('hari-ini'); }
    if ($diantar === '') $diantar = 'Orang tua';
    // Satu baris per anak per hari (kunci unik); baris izin/sakit berubah menjadi hadir
    q("INSERT INTO kehadiran (anak_id, tanggal, status, masuk, diantar, suhu) VALUES (?, ?, 'hadir', ?, ?, ?)
       ON DUPLICATE KEY UPDATE status = 'hadir', masuk = VALUES(masuk), diantar = VALUES(diantar), suhu = VALUES(suhu), keterangan = ''",
      [$id, $hariIni, $sekarang, $diantar, $suhu]);
    $demam = $suhu !== null && $suhu >= 37.5;
    flash($anak['panggilan'] . ' sudah datang pukul ' . substr($sekarang, 0, 5) . '.' . ($demam ? ' Suhu ' . str_replace('.', ',', (string) $suhu) . '°C, pantau dan kabari orang tua.' : ''), $demam ? 'info' : 'ok');
    redirect('hari-ini');
}

if ($aksi === 'pulang') {
    if (!$adaDiSini) { flash($anak['panggilan'] . ' tidak sedang di daycare.', 'error'); $balik(); }
    $jemput = trim((string) ($_POST['dijemput'] ?? ''));
    $sah = array_column(penjemput_sah($id, $hariIni), 'nama');
    // Keamanan anak: hanya orang tua, penjemput terdaftar, atau titipan dari portal orang tua hari ini
    if (!in_array($jemput, $sah, true)) { flash('Penjemput tidak terdaftar. Minta orang tua menitipkan penjemput lewat portal, atau hubungi orang tua.', 'error'); $balik(['jemput' => 1]); }
    $denda = hitung_denda($sekarang);
    db()->beginTransaction();
    $n = q('UPDATE kehadiran SET pulang = ?, dijemput = ?, denda = ? WHERE id = ? AND pulang IS NULL', [$sekarang, $jemput, $denda, $hadir['id']])->rowCount();
    q('UPDATE catatan SET selesai = ? WHERE anak_id = ? AND tanggal = ? AND jenis = ? AND selesai IS NULL', [$sekarang, $id, $hariIni, 'tidur']);
    db()->commit();
    if (!$n) { flash('Sudah dijemput sebelumnya.', 'error'); $balik(); }
    flash($anak['panggilan'] . ' dijemput ' . $jemput . ' pukul ' . substr($sekarang, 0, 5) . '.' . ($denda ? ' Terlambat jemput, denda ' . rupiah($denda) . ' masuk tagihan.' : ''), $denda ? 'info' : 'ok');
    redirect('hari-ini');
}

if ($aksi === 'absen') {
    $st = in_array($_POST['status'] ?? '', ['izin', 'sakit'], true) ? $_POST['status'] : 'izin';
    if ($hadir && $hadir['masuk']) { flash('Anak sudah datang hari ini.', 'error'); $balik(); }
    q('INSERT INTO kehadiran (anak_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan)',
      [$id, $hariIni, $st, mb_substr(trim((string) ($_POST['keterangan'] ?? '')), 0, 255)]);
    flash($anak['panggilan'] . ' dicatat ' . $st . ' hari ini.');
    redirect('hari-ini');
}

if ($aksi === 'catat') {
    $jenis = (string) ($_POST['jenis'] ?? '');
    if (!isset(JENIS[$jenis])) { flash('Pilih jenis catatan.', 'error'); $balik(); }
    if (!$adaDiSini) { flash('Catatan hanya bisa diisi saat anak sedang di daycare.', 'error'); $balik(); }
    $waktu = (string) ($_POST['waktu'] ?? '') ?: substr($sekarang, 0, 5);
    // Dibandingkan per menit: catatan di menit yang sama dengan jam datang tetap sah
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $waktu) || $waktu > substr($sekarang, 0, 5) || $waktu < substr($hadir['masuk'], 0, 5)) {
        flash('Jam catatan harus di antara jam datang (' . jam($hadir['masuk']) . ') dan sekarang.', 'error'); $balik();
    }
    $nilai = trim((string) ($_POST['nilai'] ?? ''));
    $detail = mb_substr(trim((string) ($_POST['detail'] ?? '')), 0, 255);
    $pilihan = JENIS[$jenis][3];
    if ($pilihan && !isset($pilihan[$nilai])) { flash('Pilih ' . strtolower(JENIS[$jenis][0]) . ' dari pilihan yang ada.', 'error'); $balik(); }
    if ($jenis === 'susu') {
        $ml = (int) $nilai;
        if ($ml < 10 || $ml > 500) { flash('Isi jumlah susu dalam ml (10-500).', 'error'); $balik(); }
        $nilai = $ml . ' ml';
    }
    if (in_array($jenis, ['aktivitas', 'catatan'], true) && $detail === '') { flash('Tulis isi ' . strtolower(JENIS[$jenis][0]) . '.', 'error'); $balik(); }
    if ($jenis === 'obat' && ($nilai === '' || $detail === '')) { flash('Obat wajib diisi nama + dosis dan keterangan (titipan siapa / alasan).', 'error'); $balik(); }
    if ($jenis === 'tidur' && q('SELECT id FROM catatan WHERE anak_id = ? AND tanggal = ? AND jenis = ? AND selesai IS NULL', [$id, $hariIni, 'tidur'])->fetch()) {
        flash($anak['panggilan'] . ' masih tercatat tidur. Tekan Bangun dulu.', 'error'); $balik();
    }
    q('INSERT INTO catatan (anak_id, tanggal, waktu, jenis, nilai, detail, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [$id, $hariIni, $waktu . ':00', $jenis, mb_substr($nilai, 0, 30), $detail, user()['id']]);
    flash(JENIS[$jenis][0] . ' dicatat.');
    $balik();
}

if ($aksi === 'bangun') {
    $n = q('UPDATE catatan SET selesai = ? WHERE anak_id = ? AND tanggal = ? AND jenis = ? AND selesai IS NULL', [$sekarang, $id, $hariIni, 'tidur'])->rowCount();
    flash($n ? $anak['panggilan'] . ' bangun pukul ' . substr($sekarang, 0, 5) . '.' : 'Tidak ada catatan tidur yang terbuka.', $n ? 'ok' : 'error');
    $balik();
}

if ($aksi === 'hapus') {
    // Pengasuh hanya boleh menghapus catatannya sendiri di hari yang sama; admin boleh semua
    $c = q('SELECT * FROM catatan WHERE id = ? AND anak_id = ?', [(int) ($_POST['catatan_id'] ?? 0), $id])->fetch();
    if ($c && (is_admin() || ((int) $c['user_id'] === user()['id'] && $c['tanggal'] === $hariIni))) {
        q('DELETE FROM catatan WHERE id = ?', [$c['id']]);
        flash('Catatan dihapus.');
    } else {
        flash('Catatan ini tidak bisa dihapus.', 'error');
    }
    $balik(['tgl' => $c['tanggal'] ?? $hariIni]);
}

redirect('hari-ini');
