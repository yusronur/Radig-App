<?php
// File: koneksi.php
// Konfigurasi untuk terhubung ke database MySQL

// --- SESUAIKAN DENGAN PENGATURAN HOSTING ANDA ---
$host = "db"; // Biasanya 'localhost' jika web dan database di server yang sama
$user = "root"; // Ganti dengan username database yang Anda buat
$pass = "N4n4n4h0"; // Ganti dengan password database
$db   = "rapor"; // Ganti dengan nama database yang Anda buat
// --------------------------------------------------

// Membuat koneksi
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Memeriksa koneksi
if (!$koneksi) {
    // Jika koneksi gagal, hentikan script dan tampilkan pesan error
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// Mengatur zona waktu default ke Waktu Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// --- [BARU] Pengaturan Versi Aplikasi ---
// Definisikan versi aplikasi Anda di sini.
// Anda bisa mengubah "1.0.0" ini kapan saja saat ada pembaruan.
$APP_VERSION = "v2.0.1";
// ----------------------------------------

?>