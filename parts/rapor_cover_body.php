<?php
// File ini dipanggil oleh rapor_cetak_massal.php
// Variabel $koneksi, $id_siswa, dan $is_last_page sudah tersedia.

// Ambil data siswa yang diperlukan
$q_siswa_cover = mysqli_prepare($koneksi, "SELECT nama_lengkap, nis, nisn FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa_cover, "i", $id_siswa);
mysqli_stmt_execute($q_siswa_cover);
$siswa_cover_result = mysqli_stmt_get_result($q_siswa_cover);

if (!$siswa_cover_result || mysqli_num_rows($siswa_cover_result) === 0) {
    echo "<p>Data siswa dengan ID $id_siswa tidak ditemukan.</p>";
    return;
}
$siswa_cover = mysqli_fetch_assoc($siswa_cover_result);

// --- Ambil data sekolah (termasuk jenjang dan logo) ---
$q_sekolah_cover = mysqli_query($koneksi, "SELECT jenjang, logo_sekolah FROM sekolah WHERE id_sekolah = 1");
$sekolah_cover = mysqli_fetch_assoc($q_sekolah_cover);

$jenjang_sekolah = strtoupper($sekolah_cover['jenjang'] ?? 'SD'); // Default ke SD
$nama_jenjang_lengkap = ($jenjang_sekolah == 'SD') ? 'SEKOLAH DASAR' : 'SEKOLAH MENENGAH PERTAMA';
$nama_jenjang_singkat = ($jenjang_sekolah == 'SD') ? 'SD' : 'SMP';
// --- AKHIR PENGAMBILAN DATA SEKOLAH ---

// Fungsi untuk mengubah gambar menjadi base64
if (!function_exists('toBase64')) {
    function toBase64($path) {
        if (!@file_exists($path) || !@is_readable($path)) return '';
        try {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            if(!in_array(strtolower($type), ['png', 'jpg', 'jpeg', 'gif'])) return '';
            $data = @file_get_contents($path);
            if ($data === false) return '';
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        } catch (\Exception $e) {
            error_log("Error converting image to base64: " . $e->getMessage());
            return '';
        }
    }
}

// --- Path logo dinamis ---
$logo_kabupaten = toBase64('uploads/logo_kabupaten.png');
$logo_sekolah_path = !empty($sekolah_cover['logo_sekolah']) ? 'uploads/' . $sekolah_cover['logo_sekolah'] : 'uploads/logosatap.png'; // Fallback
$logo_sekolah = toBase64($logo_sekolah_path);
?>

<!-- KONTEN HTML DIMULAI (TIDAK ADA <html>, <head>, dll.) -->
<!-- [PERBAIKAN] 'style' yang menyebabkan page-break ganda DIHAPUS dari div di bawah ini -->
<div class="container">
    
    <!-- Bagian Atas: Logo dan Judul -->
    <div>
        <?php if ($logo_kabupaten): ?>
            <img src="<?php echo $logo_kabupaten; ?>" class="logo-kabupaten" alt="Logo Kabupaten">
        <?php else: ?>
             <div style="height: 100px; margin-bottom: 15px;"></div> <!-- Disesuaikan ke 60px -->
        <?php endif; ?>

        <h1>RAPOR</h1>
        
        <h2><?php echo $nama_jenjang_lengkap; ?> <br> (<?php echo $nama_jenjang_singkat; ?>) </h2>
        
        <?php if ($logo_sekolah): ?>
            <img src="<?php echo $logo_sekolah; ?>" class="logo-sekolah" alt="Logo Sekolah">
        <?php else: ?>
             <div style="height: 90px; margin-bottom: 15px;"></div> <!-- Disesuaikan ke 50px -->
        <?php endif; ?>
    </div>

    <!-- Bagian Tengah: Info Siswa -->
    <div>
        <div class="nama-siswa-container">
            <!-- [PERBAIKAN] Inline style font-size dihapus, diatur oleh CSS master -->
            <p>Nama Peserta Didik:</p>
            <div class="nama-siswa-box">
                <h3><?php echo strtoupper(htmlspecialchars($siswa_cover['nama_lengkap'])); ?></h3>
            </div>
        </div>

        <div class="nisn-container">
            <p class="nisn-label">NIS / NISN:</p>
            <p class="nisn-value"><?php echo htmlspecialchars($siswa_cover['nis'] ?? '-'); ?> / <?php echo htmlspecialchars($siswa_cover['nisn']); ?></p>
        </div>
    </div>

    <!-- Bagian Bawah: Footer -->
    <div class="footer-text">
        <p>KEMENTERIAN PENDIDIKAN DASAR DAN MENENGAH</p>
        <p>REPUBLIK INDONESIA</p>
    </div>
    
</div>
<!-- KONTEN HTML BERAKHIR -->