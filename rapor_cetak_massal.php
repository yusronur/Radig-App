<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- SOLUSI ERROR WAKTU DAN MEMORI ---
ini_set('memory_limit', '512M'); // Naikkan batas memori
ini_set('max_execution_time', 300); // Naikkan batas waktu ke 300 detik (5 menit)
// --- AKHIR SOLUSI ---

session_start();
include 'koneksi.php';
require_once 'libs/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Ambil dan validasi data dari URL
$tipe_cetak = isset($_GET['tipe']) ? $_GET['tipe'] : '';
$ids_string = isset($_GET['ids']) ? $_GET['ids'] : '';

$allowed_types = ['sampul', 'identitas', 'rapor'];
if (empty($tipe_cetak) || empty($ids_string) || !in_array($tipe_cetak, $allowed_types)) {
    die("Error: Parameter tidak lengkap atau tidak valid.");
}

// 2. Ubah string ID menjadi array integer yang aman untuk query
$ids = array_map('intval', explode(',', $ids_string));
if (empty($ids)) {
    die("Error: Tidak ada siswa yang dipilih.");
}

// --- OPTIMASI: PINDAHKAN QUERY GLOBAL KE LUAR LOOP ---
// Ambil semua data yang SAMA untuk semua siswa HANYA SATU KALI.

// Mengambil data pengaturan (Tahun Ajaran, Semester, Tanggal, Opsi Rapor) dari DB
$pengaturan_pdf = [];
$query_pengaturan_pdf = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while($row_pdf = mysqli_fetch_assoc($query_pengaturan_pdf)){
    $pengaturan_pdf[$row_pdf['nama_pengaturan']] = $row_pdf['nilai_pengaturan'];
}

// Data Tahun Ajaran
$q_ta_pdf = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta_pdf = mysqli_fetch_assoc($q_ta_pdf);
$id_tahun_ajaran_pdf = $d_ta_pdf['id_tahun_ajaran'];
$tahun_ajaran_pdf = $d_ta_pdf['tahun_ajaran'];

// Data Semester
$semester_aktif_pdf = $pengaturan_pdf['semester_aktif'] ?? 1;
$semester_text_pdf = ($semester_aktif_pdf == 1) ? '1 (Ganjil)' : '2 (Genap)';

// Data Tanggal Rapor
$tanggal_rapor_db_pdf = $pengaturan_pdf['tanggal_rapor'] ?? date("Y-m-d");
$tanggal_rapor_pdf = date("d F Y", strtotime($tanggal_rapor_db_pdf));

// Data Sekolah
$q_sekolah_pdf = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah_pdf = mysqli_fetch_assoc($q_sekolah_pdf);

// [MODIFIKASI] Mengambil Ukuran Kertas dan Skema Warna
$ukuran_kertas_pdf = $pengaturan_pdf['rapor_ukuran_kertas'] ?? 'A4'; // Default A4
$skema_warna_pdf = $pengaturan_pdf['rapor_skema_warna'] ?? 'bw'; // Default Hitam Putih (bw)

// [MODIFIKASI] Logika Skema Warna Hemat Tinta
// Variabel ini akan di-pass ke rapor_pdf_body.php
$theme_color_bg = '#444444'; // Latar (Header Tabel)
$theme_color_text = '#FFFFFF'; // Teks (Header Tabel)
$theme_color_kop = '#000000'; // Warna Kop (Nama Sekolah)

switch ($skema_warna_pdf) {
    case 'light_blue':
        $theme_color_bg = '#E3F2FD';
        $theme_color_text = '#0D47A1';
        $theme_color_kop = '#0D47A1';
        break;
    case 'light_green':
        $theme_color_bg = '#E8F5E9';
        $theme_color_text = '#1B5E20';
        $theme_color_kop = '#1B5E20';
        break;
    case 'light_teal':
        $theme_color_bg = '#E0F2F1';
        $theme_color_text = '#004D40';
        $theme_color_kop = '#004D40';
        break;
    case 'light_purple':
        $theme_color_bg = '#EDE7F6';
        $theme_color_text = '#311B92';
        $theme_color_kop = '#311B92';
        break;
    case 'light_red':
        $theme_color_bg = '#FFEBEE';
        $theme_color_text = '#B71C1C';
        $theme_color_kop = '#B71C1C';
        break;
    case 'bw': // Hitam Putih (Default)
    default:
        $theme_color_bg = '#444444';
        $theme_color_text = '#FFFFFF';
        $theme_color_kop = '#000000';
        break;
}
// --- Akhir Modifikasi Warna ---


// --- OPTIMASI: PROSES GAMBAR BASE64 SATU KALI SAJA ---
// Proses Watermark (Satu Kali)
$watermark_base64 = '';
$watermark_filename_pdf = $pengaturan_pdf['watermark_file'] ?? null;
if (!empty($watermark_filename_pdf)) {
    $server_path_wm = 'uploads/' . $watermark_filename_pdf;
    if (file_exists($server_path_wm) && is_readable($server_path_wm)) {
        $type_pdf = pathinfo($server_path_wm, PATHINFO_EXTENSION);
        $data_pdf = file_get_contents($server_path_wm);
        $watermark_base64 = 'data:image/' . $type_pdf . ';base64,' . base64_encode($data_pdf);
    }
}

// Proses Logo Kabupaten (Satu Kali)
$base64_kab_pdf = '';
$path_kab_pdf = 'uploads/logo_kabupaten.png';
if (file_exists($path_kab_pdf)) {
    $type_kab_pdf = pathinfo($path_kab_pdf, PATHINFO_EXTENSION);
    $data_kab_pdf = file_get_contents($path_kab_pdf);
    $base64_kab_pdf = 'data:image/' . $type_kab_pdf . ';base64,' . base64_encode($data_kab_pdf);
}

// Proses Logo Sekolah (Satu Kali)
$base64_sekolah_pdf = '';
if (!empty($sekolah_pdf['logo_sekolah'])) {
    $path_sekolah_pdf = 'uploads/' . $sekolah_pdf['logo_sekolah'];
    if (file_exists($path_sekolah_pdf)) {
        $type_sekolah_pdf = pathinfo($path_sekolah_pdf, PATHINFO_EXTENSION);
        $data_sekolah_pdf = file_get_contents($path_sekolah_pdf);
        $base64_sekolah_pdf = 'data:image/' . $type_sekolah_pdf . ';base64,' . base64_encode($data_sekolah_pdf);
    }
}
// --- AKHIR OPTIMASI ---


// Mulai menangkap output HTML untuk digabungkan
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Massal - <?php echo ucfirst($tipe_cetak); ?></title>
    
    <style>
        /* [MODIFIKASI] Margin atas disesuaikan untuk KOP baru */
        @page { margin: 150px 30px 40px 30px; } 
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 10pt; 
            color: #333; 
        }
        
        .rapor-page-container {
            /* Kontainer ini mengisolasi setiap siswa */
        }
        
        header { 
            position: fixed; 
            top: -150px; /* Sesuaikan dengan margin atas @page */
            left: 0px; 
            right: 0px; 
        }

        /* [MODIFIKASI] Mengadopsi CSS KOP dari rapor_pdf.php */
        .header-table {
            width: 100%;
            border-bottom: 3px solid #000;
            padding-bottom: 5px;
            margin-top: 10px;
        }
        .header-table .logo-left { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .logo-right { width: 90px; text-align: center; vertical-align: middle; }
        .header-table .kop-text { text-align: center; vertical-align: middle; }
        .header-table h4, .header-table h3, .header-table p { margin: 0; line-height: 1.2; }
        .header-table h4 { font-size: 14pt; }
        .header-table .dinas-text { font-size: 13pt; margin-top: 2px; }
        /* [MODIFIKASI] Warna KOP dinamis */
        .header-table .school-name { font-size: 20pt; font-weight: bold; margin: 5px 0; color: <?php echo $theme_color_kop; ?>; }
        .header-table .school-info { font-size: 9pt; line-height: 1.3; }
        /* --- AKHIR KOP CSS --- */
        
        main { 
            margin-top: 20px;
        }

        .section-header-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
            margin-top: 20px;
        }
        /* [MODIFIKASI] Warna header tabel dinamis */
        .section-header-table th {
            border: 1px solid #000;
            background-color: <?php echo $theme_color_bg; ?>;
            color: <?php echo $theme_color_text; ?>; 
            padding: 6px;
            font-size: 11pt;
            text-align: left;
            font-weight: bold;
        }
        .section-header-table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }
        .info-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 15px; margin-bottom: 20px;}
        .info-table td { padding: 3px 5px; }
        
        /* ====================================================== */
        /* ### [PERBAIKAN] CSS PAGE BREAK UNTUK CETAK MASSAL ### */
        /* ====================================================== */
        .content-table { 
            width: 100%; 
            border-collapse: collapse; 
            page-break-inside: auto; /* [FIX] Izinkan tabel terpotong */
        }
        .content-table th, .content-table td { 
            border: 1px solid #000; 
            padding: 6px; 
            text-align: left; 
            vertical-align: top;
            page-break-inside: auto; /* [FIX] Izinkan sel terpotong */
        }
        .content-table tr { 
            page-break-inside: auto; /* [FIX] Izinkan baris terpotong */
            page-break-after: auto; 
        }
        .content-table thead { 
            display: table-header-group; /* [FIX] Ulangi header di halaman baru */
        }
        .content-table tbody { 
            display: table-row-group; 
        }
        /* ====================================================== */
        /* ### AKHIR PERBAIKAN ### */
        /* ====================================================== */
        
        /* [MODIFIKASI] Warna header tabel dinamis */
        .content-table th { 
            background-color: <?php echo $theme_color_bg; ?>; 
            color: <?php echo $theme_color_text; ?>; 
            font-weight: bold; 
            text-align: center; 
            vertical-align: middle;
        }
        .section-title { font-weight: bold; font-size: 11pt; margin-top: 20px; margin-bottom: 8px; }
        .text-center { text-align: center; }
        .capaian { font-size: 9pt; line-height: 1.4; white-space: pre-wrap; }
        .signature-table { width: 100%; margin-top: 40px; font-size: 10pt; page-break-inside: avoid; }
        .signature-table td { width: 33.33%; text-align: center; }
        .signature-space { height: 60px; }
        
        .page-break { 
            page-break-after: always; 
        }
        
        .watermark {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1000;
            text-align: center;
        }
        .watermark img {
            opacity: 0.1;
            width: 100%;
            margin-top: -10%;
        }

        /* [MODIFIKASI] Footer CSS baru yang identik dengan rapor_pdf.php */
        footer {
            position: fixed; 
            bottom: -30px; 
            left: 30px; 
            right: 30px; 
            height: 20px; 
            font-size: 8pt;
            font-style: italic;
            color: #555;
        }
        .footer-table {
            width: 100%;
        }
        .footer-table .nama-sekolah {
            text-align: left;
            width: 45%;
        }
        .footer-table .nama-siswa {
            text-align: center;
            width: 30%;
        }
        .footer-table .halaman {
            text-align: right;
            width: 25%;
        }
        /* Script PHP akan mengisi 'content' untuk .page-number */
        .footer-table .halaman .page-number:after {
            content: counter(page); /* Hanya nomor halaman saja, 'dari' akan di-loop */
        }
    </style>
</head>
<body>

    <div class="watermark">
        <?php if (!empty($watermark_base64)): ?>
            <img src="<?php echo $watermark_base64; ?>" alt="Watermark">
        <?php endif; ?>
    </div>

    <?php
// 3. Loop melalui setiap ID siswa dan generate konten HTML-nya
$total_ids = count($ids);
$counter = 0;
foreach ($ids as $id_siswa) {
    $counter++;
    
    // Bungkus setiap halaman siswa dalam kontainer
    echo '<div class="rapor-page-container">';
    
    // [MODIFIKASI] Opsi tampilkan nilai fase A dihapus, default ke true (karena ini SMP/Fase D)
    $show_nilai_column_pdf = true;
    
    switch ($tipe_cetak) {
        case 'sampul':
            // TODO: Buat 'parts/rapor_cover_body.php' yang serupa
            // include 'parts/rapor_cover_body.php';
            echo "<h1>Halaman Sampul untuk Siswa ID: $id_siswa</h1>"; // Placeholder
            break;
        case 'identitas':
            // TODO: Buat 'parts/rapor_identitas_body.php' yang serupa
            // include 'parts/rapor_identitas_body.php';
             echo "<h1>Halaman Identitas untuk Siswa ID: $id_siswa</h1>"; // Placeholder
            break;
        case 'rapor':
            // Khusus untuk rapor utama, kita HANYA mengambil data rapor final
            $q_rapor = mysqli_prepare($koneksi, "SELECT * FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ? LIMIT 1");
            mysqli_stmt_bind_param($q_rapor, "iii", $id_siswa, $semester_aktif_pdf, $id_tahun_ajaran_pdf);
            mysqli_stmt_execute($q_rapor);
            $rapor_pdf = mysqli_fetch_assoc(mysqli_stmt_get_result($q_rapor)); // [MODIFIKASI] ganti nama variabel
            $id_rapor = $rapor_pdf['id_rapor'] ?? 0; // [MODIFIKASI] ganti nama variabel
            
            include 'parts/rapor_pdf_body.php';
            
            break;
    }

    echo '</div>'; // Tutup .rapor-page-container

    // Tambahkan page break, KECUALI untuk siswa yang terakhir di dalam loop
    if ($counter < $total_ids) {
        echo '<div class="page-break"></div>';
    }
}
?>
</body>
</html>
<?php
// 4. Ambil semua HTML yang sudah digabungkan dari buffer
$html = ob_get_clean();

// 5. Render gabungan HTML ke satu file PDF menggunakan Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', dirname(__FILE__)); // Menggunakan path dari file ini
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true); // [MODIFIKASI] DIAKTIFKAN KEMBALI untuk footer per halaman

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

// ======================================================
// ### [PERBAIKAN F4 DIMASUKKAN DI SINI] ###
// ======================================================
// [MODIFIKASI] Mengkonversi F4 string ke ukuran custom
switch ($ukuran_kertas_pdf) {
    case 'F4':
        $width_pt = (215 / 25.4) * 72;
        $height_pt = (330 / 25.4) * 72;
        $ukuran_kertas_pdf = array(0, 0, $width_pt, $height_pt);
        break;
}
// ======================================================
// ### AKHIR PERBAIKAN ###
// ======================================================

// [MODIFIKASI] Menggunakan Ukuran Kertas dari pengaturan
$dompdf->setPaper($ukuran_kertas_pdf, 'portrait');
$dompdf->render();

$filename = "Rapor Massal - " . ucfirst($tipe_cetak) . " - " . date('Y-m-d') . ".pdf";
$dompdf->stream($filename, ["Attachment" => 0]);
exit();

?>
