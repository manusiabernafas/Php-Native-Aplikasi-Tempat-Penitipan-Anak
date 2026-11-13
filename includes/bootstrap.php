<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_name('daycare');
session_start();
date_default_timezone_set('Asia/Jakarta');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/../config/database.php';
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET time_zone = '+07:00'"); // NOW() di MySQL ikut jam WIB
        } catch (PDOException $e) {
            die('<h3>Koneksi database gagal.</h3><p>Cek <b>config/database.php</b> dan pastikan database <b>'
                . htmlspecialchars($c['database']) . '</b> sudah di-import dari folder <b>database/</b>.</p>');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

const BULAN_ID = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function tgl($date, bool $panjang = true): string
{
    if (!$date) return '-';
    $t = strtotime($date);
    $b = BULAN_ID[(int) date('n', $t)];
    return date('j', $t) . ' ' . ($panjang ? $b : substr($b, 0, 3)) . ' ' . date('Y', $t);
}

function url(string $page = 'hari-ini', array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(403);
        die('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/** Baca CSV unggahan (pemisah ; atau , dideteksi otomatis, BOM dibuang). */
function baca_csv(string $path, int $maksBaris = 2000): array
{
    $isi = file_get_contents($path, false, null, 0, 2_000_000);
    if ($isi === false) return [];
    $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
    $baris1 = strtok($isi, "\n");
    $pisah = substr_count((string) $baris1, ';') >= substr_count((string) $baris1, ',') ? ';' : ',';
    $out = [];
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $isi);
    rewind($fh);
    while (($r = fgetcsv($fh, 0, $pisah, '"', '')) !== false && count($out) < $maksBaris) {
        if ($r === [null] || (count($r) === 1 && trim((string) $r[0]) === '')) continue;
        $out[] = array_map(fn($x) => trim((string) $x), $r);
    }
    fclose($fh);
    return $out;
}

function kirim_csv(string $nama, array $baris): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nama) . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    foreach ($baris as $r) {
        // Sel diawali = + - @ diberi kutip satu agar tidak dijalankan Excel sebagai rumus
        fputcsv($o, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $r), ';', '"', '');
    }
    fclose($o);
    exit;
}

// ---------------------------------------------------------------- akun & peran

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        flash('Silakan masuk terlebih dahulu.', 'error');
        redirect('login');
    }
    // Baca ulang dari database: perubahan peran / penonaktifan langsung berlaku
    $u = q('SELECT id, nama, username, role, aktif FROM pengguna WHERE id = ?', [user()['id']])->fetch();
    if (!$u || !$u['aktif']) {
        $_SESSION = [];
        flash('Akun Anda tidak aktif. Hubungi admin.', 'error');
        redirect('login');
    }
    $_SESSION['user'] = ['id' => (int) $u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role']];
}

function is_admin(): bool
{
    return (user()['role'] ?? '') === 'admin';
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('Menu itu hanya untuk admin.', 'error');
        redirect('hari-ini');
    }
}

const LABEL_ROLE = ['admin' => 'Admin / pemilik', 'operator' => 'Pengasuh'];

/**
 * Batas percobaan berbasis IP yang disimpan di database (bukan sesi),
 * sehingga tidak bisa diakali dengan menghapus cookie.
 */
function coba_terkunci(string $kunci, int $maks, int $detik): bool
{
    $r = q('SELECT n, mulai FROM batas_coba WHERE kunci = ?', [$kunci])->fetch();
    if (!$r) return false;
    if (strtotime($r['mulai']) < time() - $detik) {
        q('DELETE FROM batas_coba WHERE kunci = ?', [$kunci]);
        return false;
    }
    return (int) $r['n'] >= $maks;
}

function coba_gagal(string $kunci, int $detik): void
{
    q('INSERT INTO batas_coba (kunci, n, mulai) VALUES (?, 1, NOW())
       ON DUPLICATE KEY UPDATE n = IF(mulai < NOW() - INTERVAL ? SECOND, 1, n + 1),
                               mulai = IF(mulai < NOW() - INTERVAL ? SECOND, NOW(), mulai)', [$kunci, $detik, $detik]);
}

function coba_reset(string $kunci): void
{
    q('DELETE FROM batas_coba WHERE kunci = ?', [$kunci]);
}

function ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// ---------------------------------------------------------------- data daycare

const PENGATURAN_AWAL = [
    'nama' => 'Daycare Ceria', 'alamat' => '', 'telepon' => '', 'jam_buka' => '07:00', 'jam_tutup' => '17:00',
    'toleransi' => '15', 'denda_15' => '15000', 'tarif_harian' => '85000', 'tarif_bulanan' => '1500000', 'pesan_ortu' => '',
];

const KELOMPOK = ['bayi' => 'Bayi', 'batita' => 'Batita', 'balita' => 'Balita'];
const PAKET = ['bulanan' => 'Bulanan', 'harian' => 'Harian'];

/** Jenis catatan harian: label, ikon, warna, pilihan nilai (bila ada). */
const JENIS = [
    'makan'     => ['Makan', 'mangkuk', 'peach', ['habis' => 'Habis', 'setengah' => 'Setengah', 'sedikit' => 'Sedikit', 'tidak' => 'Tidak mau']],
    'susu'      => ['Susu', 'botol', 'langit', []],
    'tidur'     => ['Tidur', 'bulan', 'ungu', []],
    'toilet'    => ['Toilet / popok', 'tetes', 'mint', ['pipis' => 'Pipis', 'bab' => 'BAB', 'popok_basah' => 'Popok basah', 'popok_bab' => 'Popok BAB']],
    'mood'      => ['Mood', 'senyum', 'kuning', ['ceria' => 'Ceria', 'tenang' => 'Tenang', 'rewel' => 'Rewel', 'sedih' => 'Sedih', 'ngantuk' => 'Ngantuk']],
    'aktivitas' => ['Aktivitas', 'bintang', 'pink', []],
    'obat'      => ['Obat', 'plester', 'merah', []],
    'catatan'   => ['Catatan', 'pena', 'abu', []],
];

const STATUS_HADIR = ['hadir' => ['Hadir', 'mint'], 'izin' => ['Izin', 'langit'], 'sakit' => ['Sakit', 'peach']];

function setelan(?string $kunci = null)
{
    static $s = null;
    if ($s === null) {
        $s = PENGATURAN_AWAL;
        foreach (q('SELECT kunci, nilai FROM pengaturan')->fetchAll() as $r) $s[$r['kunci']] = (string) $r['nilai'];
    }
    return $kunci === null ? $s : ($s[$kunci] ?? '');
}

function simpan_setelan(array $data): void
{
    $st = db()->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
    foreach ($data as $k => $v) {
        if (array_key_exists($k, PENGATURAN_AWAL)) $st->execute([$k, (string) $v]);
    }
}

function rupiah($n): string
{
    return 'Rp' . number_format((int) $n, 0, ',', '.');
}

function jam($t): string
{
    return $t ? substr((string) $t, 0, 5) : '-';
}

const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

function hari_tgl(string $tgl): string
{
    return HARI[(int) date('N', strtotime($tgl))] . ', ' . tgl($tgl);
}

/** Umur ringkas: "1 th 4 bln" / "9 bln". */
function umur(string $lahir, ?string $pada = null): string
{
    $d = (new DateTime($lahir))->diff(new DateTime($pada ?? 'today'));
    return $d->y ? $d->y . ' th' . ($d->m ? ' ' . $d->m . ' bln' : '') : $d->m . ' bln';
}

function chip(string $warna, string $teks): string
{
    return '<span class="chip ' . e($warna) . '">' . e($teks) . '</span>';
}

/**
 * Avatar hewan bulat (beruang, kelinci, kucing, anak ayam) dari SVG datar.
 * Bentuk & warna dipilih dari id anak, jadi setiap anak punya "teman" yang tetap.
 */
function avatar(int $id, int $size = 48): string
{
    $warna = [['#ffd3c2', '#f07b5a'], ['#cfe8ff', '#3d8bd9'], ['#d9f2e6', '#2f9e7b'], ['#e7defc', '#7b61d6'], ['#ffe9a8', '#c9971a'], ['#ffd6e7', '#e05591']];
    [$muda, $tua] = $warna[$id % count($warna)];
    $hewan = intdiv($id, count($warna)) % 4;
    $telinga = match ($hewan) {
        0 => '<circle cx="14" cy="15" r="8" fill="' . $tua . '"/><circle cx="50" cy="15" r="8" fill="' . $tua . '"/><circle cx="14" cy="15" r="4" fill="' . $muda . '"/><circle cx="50" cy="15" r="4" fill="' . $muda . '"/>',
        1 => '<rect x="15" y="0" width="10" height="26" rx="5" fill="' . $tua . '"/><rect x="39" y="0" width="10" height="26" rx="5" fill="' . $tua . '"/><rect x="18" y="4" width="4" height="18" rx="2" fill="' . $muda . '"/><rect x="42" y="4" width="4" height="18" rx="2" fill="' . $muda . '"/>',
        2 => '<path d="M8 26 12 4l14 12z" fill="' . $tua . '"/><path d="M56 26 52 4 38 16z" fill="' . $tua . '"/>',
        default => '<path d="M28 8c2-6 6-6 8 0" stroke="' . $tua . '" stroke-width="3" fill="none" stroke-linecap="round"/>',
    };
    $paruh = $hewan === 3 ? '<path d="M28 40h8l-4 5z" fill="#f2a23a"/>' : '<path d="M28 41q4 4 8 0" stroke="#3b2f4a" stroke-width="2.2" fill="none" stroke-linecap="round"/>';
    return '<svg class="avatar" width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" aria-hidden="true">' . $telinga
        . '<circle cx="32" cy="36" r="24" fill="' . $tua . '"/><circle cx="32" cy="37" r="20" fill="' . $muda . '"/>'
        . '<circle cx="24" cy="34" r="3" fill="#3b2f4a"/><circle cx="40" cy="34" r="3" fill="#3b2f4a"/><circle cx="25" cy="33" r="1" fill="#fff"/><circle cx="41" cy="33" r="1" fill="#fff"/>'
        . '<ellipse cx="18" cy="41" rx="4" ry="2.5" fill="#ff9fb3"/><ellipse cx="46" cy="41" rx="4" ry="2.5" fill="#ff9fb3"/>' . $paruh . '</svg>';
}

/** Wajah mood sederhana (bukan emoji): garis mulut & mata berbeda per mood. */
function wajah(string $mood, int $size = 22): string
{
    $mulut = ['ceria' => 'M8 14q4 4 8 0', 'tenang' => 'M8.5 15h7', 'rewel' => 'M8 16q4-3 8 0', 'sedih' => 'M8 17q4-4 8 0', 'ngantuk' => 'M10 15.5h4'][$mood] ?? 'M8.5 15h7';
    $mata = $mood === 'ngantuk' ? '<path d="M7 10h3M14 10h3"/>' : '<circle cx="8.5" cy="10" r="1" fill="currentColor"/><circle cx="15.5" cy="10" r="1" fill="currentColor"/>';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/>' . $mata . '<path d="' . $mulut . '"/></svg>';
}

/** Denda telat jemput: per 15 menit (dibulatkan ke atas) setelah jam tutup + toleransi. */
function hitung_denda(string $jamPulang): int
{
    $batas = strtotime('2000-01-01 ' . setelan('jam_tutup')) + (int) setelan('toleransi') * 60;
    $lewat = strtotime('2000-01-01 ' . $jamPulang) - $batas;
    return $lewat > 0 ? (int) ceil($lewat / 900) * (int) setelan('denda_15') : 0;
}

/** Orang yang boleh menjemput anak pada tanggal tertentu: orang tua, penjemput terdaftar, dan titipan hari itu. */
function penjemput_sah(int $anakId, string $tgl): array
{
    $a = q('SELECT o.nama, o.hp FROM anak a JOIN ortu o ON o.id = a.ortu_id WHERE a.id = ?', [$anakId])->fetch();
    $daftar = $a ? [['nama' => $a['nama'], 'ket' => 'Orang tua']] : [];
    foreach (q('SELECT nama, hubungan FROM penjemput WHERE anak_id = ? ORDER BY nama', [$anakId])->fetchAll() as $p) $daftar[] = ['nama' => $p['nama'], 'ket' => $p['hubungan']];
    foreach (q('SELECT nama, hubungan FROM titip_jemput WHERE anak_id = ? AND tanggal = ?', [$anakId, $tgl])->fetchAll() as $p) $daftar[] = ['nama' => $p['nama'], 'ket' => $p['hubungan'] . ' (titipan hari ini)'];
    return $daftar;
}

function ortu(): ?array
{
    return $_SESSION['ortu'] ?? null;
}

function require_ortu(): void
{
    $o = ortu() ? q('SELECT id, nama, hp, aktif FROM ortu WHERE id = ?', [ortu()['id']])->fetch() : null;
    if (!$o || !$o['aktif']) {
        unset($_SESSION['ortu']);
        flash('Silakan masuk dengan nomor HP dan PIN.', 'error');
        redirect('ortu.masuk');
    }
}

/** Anak milik orang tua yang sedang masuk (selalu dicek di server, bukan dari parameter URL saja). */
function anak_ortu(int $anakId): ?array
{
    return q('SELECT * FROM anak WHERE id = ? AND ortu_id = ?', [$anakId, ortu()['id'] ?? 0])->fetch() ?: null;
}


// ---------------------------------------------------------------- ikon

function icon(string $name, int $size = 18): string
{
    // Ikon garis gaya Lucide, disematkan agar tetap jalan tanpa internet
    static $p = [
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'pena' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'grid' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',
        'buku' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
        'kelas' => '<path d="M2 20h20M5 20V9l7-5 7 5v11"/><path d="M10 20v-5h4v5"/>',
        'cetak' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
        'unduh' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'unggah' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'setel' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'keluar' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'plus' => '<path d="M5 12h14M12 5v14"/>',
        'centang' => '<path d="M20 6 9 17l-5-5"/>',
        'silang' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'cari' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'kunci' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'buka' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'kiri' => '<path d="m12 19-7-7 7-7M19 12H5"/>',
        'kanan' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'grafik' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
        'kalender' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'jam' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'kirim' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'bintang' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'awas' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sampah' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'mata' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'buku2' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'pindai' => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M11 8v8M14 8v8M17 8v8"/>',
        'pinjam' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
        'kembali2' => '<path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>',
        'kartu' => '<rect width="20" height="14" x="2" y="5" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M14 10h4M14 14h4"/>',
        'koin' => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.5-1-1.5-1.5-2.5-1.5-1.4 0-2.5.9-2.5 2s1.1 1.7 2.5 2 2.5.9 2.5 2-1.1 2-2.5 2c-1 0-2-.5-2.5-1.5M12 6.5v1.5M12 16v1.5"/>',
        'pintu' => '<path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.56v16.88a.5.5 0 0 1-.6.49l-6-1.2a.5.5 0 0 1-.4-.49V5.76a.5.5 0 0 1 .4-.49l6-1.2a.5.5 0 0 1 .6.49Z"/>',
        'label' => '<path d="M12.6 2.6a2 2 0 0 0-1.4-.6H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'toga' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/><path d="M22 10v6"/>',
        'perisai' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'bel' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'volume' => '<path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/>',
        'mic' => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/>',
        'daya' => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>',
        'putar' => '<path d="m6 3 14 9-14 9z"/>',
        'henti' => '<rect width="14" height="14" x="5" y="5" rx="1"/>',
        'musik' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'layar' => '<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'daftar' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'lapis' => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
        'mangkuk' => '<path d="M3 11h18a9 9 0 0 1-18 0Z"/><path d="M7 21h10M12 4c0 2-2 2-2 4M16 5c0 2-2 2-2 4"/>',
        'botol' => '<path d="M10 2h4M10 2v3l-2 3v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V8l-2-3V2"/><path d="M8 13h8"/>',
        'bulan' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'tetes' => '<path d="M12 2.7S5 10 5 15a7 7 0 0 0 14 0c0-5-7-12.3-7-12.3Z"/>',
        'senyum' => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
        'plester' => '<rect width="20" height="8" x="2" y="8" rx="4" transform="rotate(-45 12 12)"/><path d="M10 10h.01M14 14h.01M10 14h.01M14 10h.01"/>',
        'masuk' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5M15 12H3"/>',
        'dompet' => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        'sendok' => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
        'hati' => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/>',
        'telepon' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'termometer' => '<path d="M14 4v10.5a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z"/>',
        'salin' => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    ];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['info']) . '</svg>';
}
