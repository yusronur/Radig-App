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
include 'utils/tanggal.php';
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
$tanggal_rapor_db_pdf = $pengaturan_pdf['tanggal_rapor'] ?? date_id("Y-m-d");
$tanggal_rapor_pdf = date_id("d F Y", strtotime($tanggal_rapor_db_pdf));

// Data Sekolah
$q_sekolah_pdf = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah_pdf = mysqli_fetch_assoc($q_sekolah_pdf);

// [MODIFIKASI] Mengambil Ukuran Kertas dan Skema Warna
$ukuran_kertas_pdf = $pengaturan_pdf['rapor_ukuran_kertas'] ?? 'A4'; // Default A4
$skema_warna_pdf = $pengaturan_pdf['rapor_skema_warna'] ?? 'bw'; // Default Hitam Putih (bw)

// [MODIFIKASI] Logika Skema Warna Hemat Tinta
$theme_color_bg = '#444444'; $theme_color_text = '#FFFFFF'; $theme_color_kop = '#000000';
switch ($skema_warna_pdf) {
    case 'light_blue': $theme_color_bg = '#E3F2FD'; $theme_color_text = '#0D47A1'; $theme_color_kop = '#0D47A1'; break;
    case 'light_green': $theme_color_bg = '#E8F5E9'; $theme_color_text = '#1B5E20'; $theme_color_kop = '#1B5E20'; break;
    case 'light_teal': $theme_color_bg = '#E0F2F1'; $theme_color_text = '#004D40'; $theme_color_kop = '#004D40'; break;
    case 'light_purple': $theme_color_bg = '#EDE7F6'; $theme_color_text = '#311B92'; $theme_color_kop = '#311B92'; break;
    case 'light_red': $theme_color_bg = '#FFEBEE'; $theme_color_text = '#B71C1C'; $theme_color_kop = '#B71C1C'; break;
    case 'bw': default: $theme_color_bg = '#444444'; $theme_color_text = '#FFFFFF'; $theme_color_kop = '#000000'; break;
}

// --- OPTIMASI: PROSES GAMBAR BASE64 SATU KALI SAJA ---
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
$base64_kab_pdf = '';
$path_kab_pdf = 'uploads/logo_kabupaten.png';
if (file_exists($path_kab_pdf)) {
    $type_kab_pdf = pathinfo($path_kab_pdf, PATHINFO_EXTENSION);
    $data_kab_pdf = file_get_contents($path_kab_pdf);
    $base64_kab_pdf = 'data:image/' . $type_kab_pdf . ';base64,' . base64_encode($data_kab_pdf);
}
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

// ==================================================================
// --- [PERUBAHAN BESAR] MEMILIH CSS BERDASARKAN TIPE CETAK ---
// ==================================================================

$style_css = '';

switch ($tipe_cetak) {
    case 'sampul':
        $style_css = "
            @page { margin: 0; }
            body { font-family: 'Times New Roman', Times, serif; text-align: center; padding: 30px; }
            .container { 
                padding: 40px; 
                border: 3px double black; 
                height: 90%;
            }
            .logo-kabupaten { width: 100px; margin-bottom: 15px; } /* Ukuran dikembalikan ke 100px */
            .logo-sekolah { width: 90px; margin-bottom: 15px; } /* Ukuran dikembalikan ke 90px */
            h1 { font-size: 26pt; margin: 20px 0 5px 0; }
            h2 { font-size: 18pt; margin: 0 0 25px 0; font-weight: normal; line-height: 1.3; }
            .nama-siswa-container { margin-top: 60px; margin-bottom: 10px;}
            .nama-siswa-container p { font-size: 14pt; margin: 0; }
            .nama-siswa-box {
                display: inline-block;
                border: 2px solid black;
                padding: 8px 15px;
                margin-top: 5px;
                min-width: 60%;
                max-width: 80%;
            }
            .nama-siswa-box h3 { font-weight: bold; margin:0; font-size: 16pt; word-wrap: break-word; }
            .nisn-container { margin-top: 30px; margin-bottom: 60px;}
            .nisn-label { font-size: 14pt; margin-bottom: 5px;}
            .nisn-value { font-size: 14pt; font-weight: bold; }
            .footer-text { margin-top: 60px; font-size: 12pt; font-weight: bold; line-height: 1.4; }
            .page-break { page-break-after: always; }
        ";
        break;

    case 'identitas':
        $style_css = "
            @page { margin: 2cm 1.5cm; } 
            body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; }
            .header { text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 14pt; text-decoration: underline; }
            .info-table { width: 100%; border-collapse: collapse; line-height: 1.5; }
            .info-table td { vertical-align: top; padding: 1px 4px;}
            .info-table td.num { width: 4%; }
            .info-table td.label { width: 30%; }
            .info-table td.separator { width: 2%; }
            .signature-block { margin-top: 30px; float: right; width: 40%; text-align: left;}
            .signature-space { height: 60px; }
            .photo-box {
                width: 3cm;
                height: 4cm;
                border: 1px solid #000;
                float: right;
                margin-left: 20px;
            }
            .clearfix::after { content: ''; clear: both; display: table; }
            .page-break { page-break-after: always; }
        ";
        break;

    case 'rapor':
    default:
        $style_css = "
            /* [PERBAIKAN] Margin atas dan bawah untuk KOP dan FOOTER */
            @page { margin: 150px 30px 40px 30px; } 
            
            body { font-family: 'Times New Roman', Times, serif; font-size: 10pt; color: #333; }
            .rapor-page-container { }
            
            /* [PERBAIKAN] KOP (Header) HANYA ADA SATU dan posisinya fixed */
            header { position: fixed; top: -150px; left: 0px; right: 0px; }
            .header-table { width: 100%; border-bottom: 3px solid #000; padding-bottom: 5px; margin-top: 10px; }
            .header-table .logo-left { width: 90px; text-align: center; vertical-align: middle; }
            .header-table .logo-right { width: 90px; text-align: center; vertical-align: middle; }
            .header-table .kop-text { text-align: center; vertical-align: middle; }
            .header-table h4, .header-table h3, .header-table p { margin: 0; line-height: 1.2; }
            .header-table h4 { font-size: 14pt; }
            .header-table .dinas-text { font-size: 13pt; margin-top: 2px; }
            .header-table .school-name { font-size: 20pt; font-weight: bold; margin: 5px 0; color: {$theme_color_kop}; }
            .header-table .school-info { font-size: 9pt; line-height: 1.3; }
            
            /* [PERBAIKAN] Main content tidak perlu margin-top lagi karena sudah diatur @page */
            main { margin-top: 0px; }
            
            .section-header-table { width: 100%; border-collapse: collapse; page-break-inside: avoid; margin-top: 20px; }
            .section-header-table th { border: 1px solid #000; background-color: {$theme_color_bg}; color: {$theme_color_text}; padding: 6px; font-size: 11pt; text-align: left; font-weight: bold; }
            .section-header-table td { border: 1px solid #000; padding: 6px; vertical-align: top; }
            .info-table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 15px; margin-bottom: 20px;}
            .info-table td { padding: 3px 5px; }
            
            .content-table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
            .content-table th, .content-table td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: top; page-break-inside: auto; }
            .content-table tr { page-break-inside: auto; page-break-after: auto; }
            .content-table thead { display: table-header-group; }
            .content-table tbody { display: table-row-group; }
            
            .content-table th { background-color: {$theme_color_bg}; color: {$theme_color_text}; font-weight: bold; text-align: center; vertical-align: middle; }
            .section-title { font-weight: bold; font-size: 11pt; margin-top: 20px; margin-bottom: 8px; }
            .text-center { text-align: center; }
            .capaian { font-size: 9pt; line-height: 1.4; white-space: pre-wrap; }
            .signature-table { width: 100%; margin-top: 40px; font-size: 10pt; page-break-inside: avoid; }
            .signature-table td { width: 33.33%; text-align: center; }
            .signature-space { height: 60px; }
            
            .page-break { page-break-after: always; }
            
            .watermark { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1000; text-align: center; }
            .watermark img { opacity: 0.1; width: 100%; margin-top: -10%; }

            /* [PERBAIKAN] FOOTER HANYA ADA SATU dan posisinya fixed */
            footer { position: fixed; bottom: -30px; left: 30px; right: 30px; height: 20px; font-size: 8pt; font-style: italic; color: #555; }
            .footer-table { width: 100%; }
            .footer-table .nama-sekolah { text-align: left; width: 45%; }
            .footer-table .nama-siswa { text-align: center; width: 30%; } /* Kolom nama siswa tetap ada, tapi akan kita kosongkan */
            .footer-table .halaman { text-align: right; width: 25%; }
            .footer-table .halaman .page-number:after { content: counter(page); }
        ";
        break;
}

// Mulai menangkap output HTML untuk digabungkan
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Massal - <?php echo ucfirst($tipe_cetak); ?></title>
    <style>
        <?php echo $style_css; // Menyuntikkan CSS yang tepat ?>
    </style>
</head>
<body>

    <?php if ($tipe_cetak == 'rapor'): // Watermark dan KOP hanya untuk rapor ?>
    <div class="watermark">
        <?php if (!empty($watermark_base64)): ?>
            <img src="<?php echo $watermark_base64; ?>" alt="Watermark">
        <?php endif; ?>
    </div>

    <!-- [PERBAIKAN] KOP DITARUH DI SINI, HANYA SATU KALI, DI LUAR LOOP -->
    <header>
        <table class="header-table">
            <tr>
                <td class="logo-left">
                    <?php 
                        if (!empty($base64_kab_pdf)) {
                            echo '<img src="' . $base64_kab_pdf . '" alt="Logo Kabupaten" style="width: 80px;">';
                        }
                    ?>
                </td>
                <td class="kop-text">
                    <h4>PEMERINTAH KABUPATEN <?php echo strtoupper(htmlspecialchars($sekolah_pdf['kabupaten_kota'] ?? '')); ?></h4>
                    <p class="dinas-text">DINAS PENDIDIKAN</p>
                    <h3 class="school-name"><?php echo strtoupper(htmlspecialchars($sekolah_pdf['nama_sekolah'])); ?></h3>
                    <p class="school-info">
                        <?php echo htmlspecialchars($sekolah_pdf['jalan'] ?? ''); ?>, Desa/Kel. <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan'] ?? ''); ?>, Kec. <?php echo htmlspecialchars($sekolah_pdf['kecamatan'] ?? ''); ?><br>
                        Telp: <?php echo htmlspecialchars($sekolah_pdf['telepon'] ?? '-'); ?> Email: <?php echo htmlspecialchars($sekolah_pdf['email'] ?? '-'); ?>
                    </p>
                </td>
                <td class="logo-right">
                    <?php 
                        if (!empty($base64_sekolah_pdf)):
                            echo '<img src="' . $base64_sekolah_pdf . '" alt="Logo Sekolah" style="width: 80px;">';
                        endif; 
                    ?>
                </td>
            </tr>
        </table>
    </header>
    <?php endif; // Akhir dari if($tipe_cetak == 'rapor') ?>


<?php
// 3. Loop melalui setiap ID siswa dan generate konten HTML-nya
$total_ids = count($ids);
$counter = 0;
foreach ($ids as $id_siswa) {
    $counter++;
    $is_last_page = ($counter == $total_ids); // Variabel untuk file sampul
    
    // Bungkus setiap halaman siswa dalam kontainer
    // Untuk 'rapor', div ini ada di dalam file body
    if ($tipe_cetak != 'rapor') {
         echo '<div class="rapor-page-container">';
    }
    
    // Opsi ini hanya relevan untuk 'rapor'
    $show_nilai_column_pdf = true;
    
    // [PERBAIKAN] Mengaktifkan semua include dari folder 'parts/'
    switch ($tipe_cetak) {
        case 'sampul':
            $file_sampul = 'parts/rapor_cover_body.php';
            if (file_exists($file_sampul)) {
                include $file_sampul;
            } else {
                echo "<h1>Error: File '$file_sampul' tidak ditemukan.</h1>";
            }
            break;
            
        case 'identitas':
            $file_identitas = 'parts/rapor_identitas_body.php';
            if (file_exists($file_identitas)) {
                include $file_identitas;
            } else {
                 echo "<h1>Error: File '$file_identitas' tidak ditemukan.</h1>";
            }
            break;
            
        case 'rapor':
            // Khusus untuk rapor utama, kita HANYA mengambil data rapor final
            $q_rapor = mysqli_prepare($koneksi, "SELECT * FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ? LIMIT 1");
            mysqli_stmt_bind_param($q_rapor, "iii", $id_siswa, $semester_aktif_pdf, $id_tahun_ajaran_pdf);
            mysqli_stmt_execute($q_rapor);
            $rapor_pdf = mysqli_fetch_assoc(mysqli_stmt_get_result($q_rapor));
            $id_rapor = $rapor_pdf['id_rapor'] ?? 0; 
            
            $file_rapor = 'parts/rapor_pdf_body.php';
            if (file_exists($file_rapor)) {
                // rapor_pdf_body.php sekarang HANYA berisi <main>
                include $file_rapor;
            } else {
                 echo "<h1>Error: File '$file_rapor' tidak ditemukan.</h1>";
            }
            break;
    }

    if ($tipe_cetak != 'rapor') {
         echo '</div>'; // Tutup .rapor-page-container
    }

    // Tambahkan page break, KECUALI untuk siswa yang terakhir di dalam loop
    if ($counter < $total_ids) {
        echo '<div class="page-break"></div>';
    }
}
?>

    <?php if ($tipe_cetak == 'rapor'): // Footer hanya untuk rapor ?>
    <!-- [PERBAIKAN] FOOTER DITARUH DI SINI, HANYA SATU KALI, DI LUAR LOOP -->
    <footer>
        <table class="footer-table">
            <tr>
                <td class="nama-sekolah"><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah']); ?></td>
                <!-- [PERBAIKAN] Nama siswa dikosongkan karena berada di luar loop -->
                <td class="nama-siswa"></td> 
                <td class="halaman">Halaman <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>
    <?php endif; // Akhir dari if($tipe_cetak == 'rapor') ?>

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
$options->set('isPhpEnabled', true); // Diperlukan untuk footer per halaman

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

// ======================================================
// ### [PERBAIKAN F4 DIMASUKKAN DI SINI] ###
// ======================================================
// [MODIFIKASI] Mengkonversi F4 string ke ukuran custom
switch ($ukuran_kertas_pdf) {
    case 'F4':
        // Ukuran F4 dalam mm (215 x 330 mm) dikonversi ke points (1 inch = 25.4 mm, 1 inch = 72 points)
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