# Daycare Ceria: Aplikasi Penitipan Anak (PHP Native + MySQL)

Aplikasi untuk daycare / tempat penitipan anak: absen antar-jemput dengan penjemput terverifikasi, catatan harian si kecil (makan, susu, tidur, toilet, mood, aktivitas, obat), portal orang tua untuk memantau dari HP, tagihan bulanan + denda telat jemput, dan menu makan mingguan. Tampilan pastel yang lucu dengan avatar hewan bulat dan font Poppins.

## Isi folder
```
index.php               router
config/database.php     koneksi MySQL
includes/               fungsi bantu (avatar hewan, denda, penjemput sah), linimasa, header & footer
pages/                  halaman pengasuh: hari ini, harian anak, anak, orang tua, kehadiran, tagihan, menu, pengaturan
pages/ortu/             portal orang tua: kabar si kecil, kabari pengasuh, tagihan
database/daycare.sql    skema + data contoh 18 anak, riwayat 30 hari
```

## Instalasi (XAMPP / Laragon)
1. Salin folder `daycare` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL.
3. Buka `http://localhost/phpmyadmin` → **Import** → pilih `database/daycare.sql`. Riwayat contoh menyesuaikan tanggal impor.
4. Sesuaikan `config/database.php` bila user/password MySQL berbeda.
5. Buka `http://localhost/daycare`.

| Masuk sebagai | Alamat | Akun |
|---|---|---|
| Admin / pemilik | `/index.php?page=login` | `admin` / `admin123` |
| Pengasuh | `/index.php?page=login` | `dewi` / `pengasuh123` |
| Orang tua (portal) | `/index.php?page=ortu.masuk` | HP `081234560001` / PIN `123456` |

Semua orang tua contoh memakai PIN `123456`. Ganti kata sandi di menu **Akun**, dan buat PIN baru untuk orang tua di menu **Orang tua**.

## Alur harian
1. **Datang**: di papan *Hari ini*, isi suhu lalu tekan **Datang**.
2. **Catat kegiatan**: buka kartu anak, pilih jenis (makan + porsi, susu ml, tidur, toilet/popok, mood, aktivitas, obat, catatan). Tidur dicatat saat mulai lalu tekan **Bangun**.
3. **Dijemput**: pilih penjemput dari daftar. Hanya orang tua, penjemput terdaftar, atau nama yang dititipkan orang tua lewat portal hari itu yang bisa dipilih. Lewat jam tutup + toleransi, denda otomatis dihitung per 15 menit.
4. **Orang tua** memantau linimasa hari ini dari HP, mengabari izin/sakit, dan menitipkan penjemput lain.
5. **Akhir bulan**: buka **Tagihan**, tekan **Hitung tagihan**. Paket bulanan = tarif tetap, paket harian = tarif × hari hadir, ditambah denda telat jemput. Tagihan yang sudah lunas tidak berubah saat dihitung ulang. Ingatkan orang tua lewat tombol WhatsApp.

## Pengaturan
Nama daycare, alamat, jam buka/tutup, toleransi jemput, denda per 15 menit, tarif bulanan & harian, dan pesan yang tampil di portal orang tua.

## Keamanan
PDO prepared statement, CSRF di semua form, password_hash untuk akun pengasuh dan PIN orang tua, batas percobaan masuk per IP (database), orang tua hanya bisa melihat dan mengabari anaknya sendiri (dicek di server), pengasuh hanya bisa menghapus catatannya sendiri di hari yang sama, hanya admin yang bisa mencatat pembayaran, escape output, header keamanan, folder internal ditutup `.htaccess`, dan CSV aman dari formula injection.
