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

// 1. Ambil ID Siswa
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) {
    die("Error: Parameter ID siswa tidak valid.");
}


// --- BAGIAN 1: PENGAMBILAN SEMUA DATA GLOBAL (STANDALONE) ---
// (Data TA, Semester, Tanggal, Sekolah, Opsi Rapor)
$pengaturan_pdf = [];
$query_pengaturan_pdf = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while($row_pdf = mysqli_fetch_assoc($query_pengaturan_pdf)){
    $pengaturan_pdf[$row_pdf['nama_pengaturan']] = $row_pdf['nilai_pengaturan'];
}

$q_ta_pdf = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta_pdf = mysqli_fetch_assoc($q_ta_pdf);
$id_tahun_ajaran_pdf = $d_ta_pdf['id_tahun_ajaran'];
$tahun_ajaran_pdf = $d_ta_pdf['tahun_ajaran'];

$semester_aktif_pdf = $pengaturan_pdf['semester_aktif'] ?? 1;
$semester_text_pdf = ($semester_aktif_pdf == 1) ? '1 (Ganjil)' : '2 (Genap)';

// Menggunakan tanggal rapor akhir semester
$tanggal_rapor_db_pdf = $pengaturan_pdf['tanggal_rapor'] ?? date_id("Y-m-d");
$tanggal_rapor_pdf = date_id("d F Y", strtotime($tanggal_rapor_db_pdf));

$q_sekolah_pdf = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah_pdf = mysqli_fetch_assoc($q_sekolah_pdf);

// Mengambil Ukuran Kertas dan Skema Warna
$ukuran_kertas_pdf = $pengaturan_pdf['rapor_ukuran_kertas'] ?? 'A4'; // Default A4
$skema_warna_pdf = $pengaturan_pdf['rapor_skema_warna'] ?? 'bw'; // Default Hitam Putih (bw)

// Logika Skema Warna Hemat Tinta
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

// --- PROSES GAMBAR BASE64 (STANDALONE) ---
// Proses Watermark
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

// Proses Logo Kabupaten
$base64_kab_pdf = '';
$path_kab_pdf = 'uploads/logo_kabupaten.png';
if (file_exists($path_kab_pdf)) {
    $type_kab_pdf = pathinfo($path_kab_pdf, PATHINFO_EXTENSION);
    $data_kab_pdf = file_get_contents($path_kab_pdf);
    $base64_kab_pdf = 'data:image/' . $type_kab_pdf . ';base64,' . base64_encode($data_kab_pdf);
}

// Proses Logo Sekolah
$base64_sekolah_pdf = '';
if (!empty($sekolah_pdf['logo_sekolah'])) {
    $path_sekolah_pdf = 'uploads/' . $sekolah_pdf['logo_sekolah'];
    if (file_exists($path_sekolah_pdf)) {
        $type_sekolah_pdf = pathinfo($path_sekolah_pdf, PATHINFO_EXTENSION);
        $data_sekolah_pdf = file_get_contents($path_sekolah_pdf);
        $base64_sekolah_pdf = 'data:image/' . $type_sekolah_pdf . ';base64,' . base64_encode($data_sekolah_pdf);
    }
}


// --- BAGIAN 2: PENGAMBILAN DATA SPESIFIK SISWA & MAPEL ---
$q_siswa_pdf = "SELECT s.nama_lengkap, s.nis, s.nisn, s.agama, s.id_kelas, k.nama_kelas, k.fase, g.nama_guru as nama_walikelas, g.nip as nip_walikelas
                FROM siswa s 
                LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
                LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru 
                WHERE s.id_siswa = ?";
$stmt_siswa_pdf = mysqli_prepare($koneksi, $q_siswa_pdf);
mysqli_stmt_bind_param($stmt_siswa_pdf, "i", $id_siswa);
mysqli_stmt_execute($stmt_siswa_pdf);
$siswa_pdf_result = mysqli_stmt_get_result($stmt_siswa_pdf);

if (mysqli_num_rows($siswa_pdf_result) == 0) {
    die("Error: Data siswa dengan ID $id_siswa tidak ditemukan.");
}
$siswa_pdf = mysqli_fetch_assoc($siswa_pdf_result);
$id_kelas_siswa = $siswa_pdf['id_kelas'] ?? 0; 

// Opsi tampilkan nilai fase A dihapus, default ke true
$show_nilai_column_pdf = true;

$q_rapor = mysqli_prepare($koneksi, "SELECT * FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ? LIMIT 1");
mysqli_stmt_bind_param($q_rapor, "iii", $id_siswa, $semester_aktif_pdf, $id_tahun_ajaran_pdf);
mysqli_stmt_execute($q_rapor);
$rapor_pdf = mysqli_fetch_assoc(mysqli_stmt_get_result($q_rapor));
$id_rapor = $rapor_pdf['id_rapor'] ?? 0; 


// --- [LOGIKA BARU] FILTER MAPEL OTOMATIS ---
// 1. Ambil semua mapel yang mengandung kata 'Agama' dari database
$q_mapel_agama_all = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel FROM mata_pelajaran WHERE nama_mapel LIKE '%Agama%'");
$list_id_semua_agama = [];
$id_mapel_agama_siswa_pdf = null;
$agama_siswa_string = trim($siswa_pdf['agama'] ?? ''); // misal 'Islam'

while ($row_agama = mysqli_fetch_assoc($q_mapel_agama_all)) {
    $id_mapel_db = $row_agama['id_mapel'];
    $nama_mapel_db = $row_agama['nama_mapel'];
    
    // Simpan ID ini sebagai 'ID mapel agama'
    $list_id_semua_agama[] = $id_mapel_db;

    // Cek apakah mapel ini cocok dengan agama siswa
    // Logika: Jika agama siswa (misal 'Islam') ada di dalam nama mapel (misal 'Pendidikan Agama Islam')
    if (!empty($agama_siswa_string) && stripos($nama_mapel_db, $agama_siswa_string) !== false) {
        $id_mapel_agama_siswa_pdf = $id_mapel_db;
    }
}

// Buat string ID untuk NOT IN (..., ...)
$string_id_semua_agama = !empty($list_id_semua_agama) ? implode(',', $list_id_semua_agama) : '0';

// Query Mapel yang diajarkan di kelas ini
$query_mapel_string_pdf = "
    SELECT 
        mp.id_mapel, 
        mp.nama_mapel, 
        mp.urutan 
    FROM 
        mata_pelajaran AS mp
    JOIN 
        guru_mengajar AS gm ON mp.id_mapel = gm.id_mapel
    WHERE 
        gm.id_kelas = $id_kelas_siswa 
        AND gm.id_tahun_ajaran = $id_tahun_ajaran_pdf
";

// Filter Pintar:
// Ambil mapel JIKA:
// 1. ID Mapel TERSEBUT BUKAN termasuk mapel agama (seperti Matematika, IPAS, dll)
//    ATAU
// 2. ID Mapel TERSEBUT ADALAH mapel agama milik siswa ini
$query_mapel_string_pdf .= " AND (
    mp.id_mapel NOT IN ($string_id_semua_agama) 
    OR mp.id_mapel = " . ($id_mapel_agama_siswa_pdf ? $id_mapel_agama_siswa_pdf : '0') . "
)";

$query_mapel_string_pdf .= " 
    GROUP BY mp.id_mapel 
    ORDER BY mp.urutan ASC, mp.nama_mapel ASC
";

$semua_mapel_query_pdf = mysqli_query($koneksi, $query_mapel_string_pdf);
// --- AKHIR LOGIKA FILTER OTOMATIS ---


$daftar_mapel_rapor_pdf = [];
$nilai_tersimpan_pdf = [];
if ($id_rapor > 0) {
    $detail_akademik_query_pdf = mysqli_query($koneksi, "SELECT id_mapel, nilai_akhir, capaian_kompetensi FROM rapor_detail_akademik WHERE id_rapor = $id_rapor");
    if ($detail_akademik_query_pdf) {
        while ($d_pdf = mysqli_fetch_assoc($detail_akademik_query_pdf)) {
            $nilai_tersimpan_pdf[$d_pdf['id_mapel']] = $d_pdf;
        }
    }
}
if ($semua_mapel_query_pdf) { 
    while ($mapel_pdf = mysqli_fetch_assoc($semua_mapel_query_pdf)) {
        $id_mapel_pdf = $mapel_pdf['id_mapel'];
        if (isset($nilai_tersimpan_pdf[$id_mapel_pdf])) {
            $daftar_mapel_rapor_pdf[] = [
                'nama_mapel' => $mapel_pdf['nama_mapel'], 
                'nilai_akhir' => $nilai_tersimpan_pdf[$id_mapel_pdf]['nilai_akhir'], 
                'capaian_kompetensi' => $nilai_tersimpan_pdf[$id_mapel_pdf]['capaian_kompetensi']
            ];
        } else {
            $daftar_mapel_rapor_pdf[] = [
                'nama_mapel' => $mapel_pdf['nama_mapel'], 
                'nilai_akhir' => '-', 
                'capaian_kompetensi' => '-'
            ];
        }
    }
}

// --- [LOGIKA PENGGABUNGAN SENI & PRAKARYA] ---
$seni_mapel_names = ['Seni Musik', 'Seni Rupa', 'Seni Tari', 'Seni Teater']; 
$prakarya_mapel_names = ['Prakarya'];

$seni_data = null;
$prakarya_data = null;
$final_daftar_mapel_pdf = [];
$found_seni_index = -1;
$found_prakarya_index = -1;

foreach ($daftar_mapel_rapor_pdf as $index => $mapel) {
    if (in_array($mapel['nama_mapel'], $seni_mapel_names)) {
        $seni_data = $mapel;
        $found_seni_index = $index;
    } elseif (in_array($mapel['nama_mapel'], $prakarya_mapel_names)) {
        $prakarya_data = $mapel;
        $found_prakarya_index = $index;
    }
}

$combined_data = null;
if ($seni_data && $prakarya_data) {
    $nilai_seni = is_numeric($seni_data['nilai_akhir']) ? (int)$seni_data['nilai_akhir'] : 0;
    $nilai_prakarya = is_numeric($prakarya_data['nilai_akhir']) ? (int)$prakarya_data['nilai_akhir'] : 0;
    $nilai_rata_rata = '-';
    
    if ($nilai_seni > 0 && $nilai_prakarya > 0) {
        $nilai_rata_rata = round(($nilai_seni + $nilai_prakarya) / 2);
    } elseif ($nilai_seni > 0) {
        $nilai_rata_rata = $nilai_seni;
    } elseif ($nilai_prakarya > 0) {
        $nilai_rata_rata = $nilai_prakarya;
    }

    $deskripsi_gabungan = trim($seni_data['capaian_kompetensi']) . "\n" . trim($prakarya_data['capaian_kompetensi']);
    
    $combined_data = [
        'nama_mapel' => 'Seni Budaya dan Prakarya',
        'nilai_akhir' => $nilai_rata_rata,
        'capaian_kompetensi' => trim($deskripsi_gabungan)
    ];

} elseif ($seni_data) {
    $seni_data['nama_mapel'] = 'Seni Budaya dan Prakarya';
    $combined_data = $seni_data;
} elseif ($prakarya_data) {
    $prakarya_data['nama_mapel'] = 'Seni Budaya dan Prakarya';
    $combined_data = $prakarya_data;
}

$mapel_sudah_digabung = false;
foreach ($daftar_mapel_rapor_pdf as $index => $mapel) {
    if ($index == $found_seni_index || $index == $found_prakarya_index) {
        if (!$mapel_sudah_digabung && $combined_data) {
            $final_daftar_mapel_pdf[] = $combined_data;
            $mapel_sudah_digabung = true;
        }
    } else {
        $final_daftar_mapel_pdf[] = $mapel;
    }
}

$daftar_mapel_rapor_pdf = $final_daftar_mapel_pdf;
// --- [AKHIR LOGIKA PENGGABUNGAN] ---


// --- Query Ekstrakurikuler ---
$detail_ekskul_query_pdf = mysqli_prepare($koneksi, "
    SELECT 
        e.nama_ekskul, 
        kh.jumlah_hadir, 
        kh.total_pertemuan,
        (SELECT GROUP_CONCAT(CONCAT(et.deskripsi_tujuan, ': ', pn.nilai) SEPARATOR '; ') 
         FROM ekskul_penilaian pn
         JOIN ekskul_tujuan et ON pn.id_tujuan_ekskul = et.id_tujuan_ekskul
         WHERE 
             pn.id_peserta_ekskul = ep.id_peserta_ekskul 
             AND et.semester = ?) as 'penilaian_deskriptif'
    FROM 
        ekskul_peserta ep
    JOIN 
        ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
    LEFT JOIN 
        ekskul_kehadiran kh ON ep.id_peserta_ekskul = kh.id_peserta_ekskul 
                           AND kh.semester = ?
    WHERE 
        ep.id_siswa = ?
    ORDER BY 
        e.nama_ekskul ASC
");
mysqli_stmt_bind_param($detail_ekskul_query_pdf, "iii", $semester_aktif_pdf, $semester_aktif_pdf, $id_siswa); 
mysqli_stmt_execute($detail_ekskul_query_pdf);
$detail_ekskul_result_pdf = mysqli_stmt_get_result($detail_ekskul_query_pdf);


// --- BAGIAN 3: MULAI GENERATE HTML ---
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapor <?php echo htmlspecialchars($siswa_pdf['nama_lengkap']); ?></title>
    
    <style>
        @page { margin: 150px 30px 40px 30px; } 
        
        body { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 10pt; 
            color: #333; 
        }
        
        header { 
            position: fixed; 
            top: -150px; 
            left: 0px; 
            right: 0px; 
        }
        
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
        /* ### [PERBAIKAN] CSS UNTUK PAGE BREAK TABEL ### */
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
            /* [PERBAIKAN BARIS INI] Izinkan sel terpotong jika perlu */
            page-break-inside: auto; 
        }
        .content-table tr { 
            /* [PERBAIKAN BARIS INI] Izinkan baris terpotong jika perlu */
            page-break-inside: auto; 
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
        
        .capaian { 
            font-size: 9pt; 
            line-height: 1.4; 
            white-space: pre-wrap; 
        }
        
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
        .footer-table .halaman .page-number:after {
            content: counter(page) " dari " counter(pages);
        }
    </style>
</head>
<body>

    <div class="watermark">
        <?php if (!empty($watermark_base64)): ?>
            <img src="<?php echo $watermark_base64; ?>" alt="Watermark">
        <?php endif; ?>
    </div>

    <footer>
        <table class="footer-table">
            <tr>
                <td class="nama-sekolah"><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah']); ?></td>
                <td class="nama-siswa"><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? 'Siswa'); ?></td>
                <td class="halaman">Halaman <span class="page-number"></span></td>
            </tr>
        </table>
    </footer>

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

    <main>
        <table class="info-table">
                <tr>
                    <td width="20%">Nama Murid</td><td width="1%">:</td><td width="34%"><?php echo htmlspecialchars($siswa_pdf['nama_lengkap'] ?? ''); ?></td>
                    <td width="20%">Kelas</td><td width="1G%">:</td><td width="24%"><?php echo htmlspecialchars($siswa_pdf['nama_kelas'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>NISN / NIS</td><td>:</td><td><?php echo htmlspecialchars($siswa_pdf['nisn'] ?? ''); ?> / <?php echo htmlspecialchars($siswa_pdf['nis'] ?? ''); ?></td>
                    <td>Fase</td><td>:</td><td><?php echo htmlspecialchars($siswa_pdf['fase'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['nama_sekolah']); ?></td>
                    <td>Semester</td><td>:</td><td><?php echo $semester_text_pdf; ?></td>
                </tr>
                    <tr>
                    <td>Alamat Sekolah</td><td>:</td><td><?php echo htmlspecialchars($sekolah_pdf['jalan'] ?? ''); ?>, Desa/Kel. <?php echo htmlspecialchars($sekolah_pdf['desa_kelurahan'] ?? ''); ?></td>
                    <td>Tahun Ajaran</td><td>:</td><td><?php echo htmlspecialchars($tahun_ajaran_pdf); ?></td>
                </tr>
        </table>

        <div class="section-title">A. NILAI AKADEMIK</div>
        <table class="content-table">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="<?php echo $show_nilai_column_pdf ? '25%' : '33%'; ?>">Mata Pelajaran</th>
                    <?php if ($show_nilai_column_pdf): ?>
                        <th width="8%">Nilai Akhir</th>
                    <?php endif; ?>
                    <th>Capaian Kompetensi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no_pdf = 1;
                foreach ($daftar_mapel_rapor_pdf as $d_pdf):
                ?>
                <tr>
                    <td class="text-center"><?php echo $no_pdf++; ?></td>
                    <td><?php echo htmlspecialchars($d_pdf['nama_mapel']); ?></td>
                    <?php if ($show_nilai_column_pdf): ?>
                        <td class="text-center"><?php echo $d_pdf['nilai_akhir']; ?></td>
                    <?php endif; ?>
                    <td class="capaian"><?php echo !empty($d_pdf['capaian_kompetensi']) ? nl2br(htmlspecialchars($d_pdf['capaian_kompetensi'])) : '-'; ?></td>
                </tr>
                <?php
                endforeach;
                if (empty($daftar_mapel_rapor_pdf)) {
                    $colspan_pdf = $show_nilai_column_pdf ? 4 : 3;
                    echo "<tr><td colspan='$colspan_pdf' class='text-center'>Data nilai akademik belum tersedia atau belum ada mapel yang diatur untuk kelas ini.</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <!-- ====================================================== -->
        <!-- ### [PERBAIKAN] HAPUS MANUAL PAGE BREAK ### -->
        <!-- ====================================================== -->
        <!-- <div class="page-break"></div> --> 
        <!-- Baris ini dihapus agar konten B, C, D, E mengalir alami -->
        
        <table class="section-header-table">
            <thead>
                <tr>
                    <th>B. KOKURIKULER</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="capaian"><?php echo !empty($rapor_pdf['deskripsi_kokurikuler']) ? nl2br(htmlspecialchars($rapor_pdf['deskripsi_kokurikuler'])) : '-'; ?></td>
                </tr>
            </tbody>
        </table>
        <!-- ====================================================== -->
        <!-- ### [AKHIR PERBAIKAN] ### -->
        <!-- ====================================================== -->

        <div class="section-title">C. EKSTRAKURIKULER</div>
        <table class="content-table">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th width="35%">Ekstrakurikuler</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no_ekskul_pdf = 1;
                if($detail_ekskul_result_pdf && mysqli_num_rows($detail_ekskul_result_pdf) > 0){
                    while($e_pdf = mysqli_fetch_assoc($detail_ekskul_result_pdf)):
                        $keterangan_pdf = "";
                        if (!empty($e_pdf['penilaian_deskriptif'])) {
                            $keterangan_pdf .= "Menunjukkan penguasaan: " . str_replace('; ', ', ', htmlspecialchars($e_pdf['penilaian_deskriptif'])) . ".";
                        } else {
                            $keterangan_pdf .= "Penilaian deskriptif belum diisi.";
                        }
                        
                        if (isset($e_pdf['jumlah_hadir']) && isset($e_pdf['total_pertemuan']) && $e_pdf['total_pertemuan'] > 0) {
                            $persentase_hadir_pdf = round(($e_pdf['jumlah_hadir'] / $e_pdf['total_pertemuan']) * 100);
                            $keterangan_pdf .= " Kehadiran: " . htmlspecialchars($e_pdf['jumlah_hadir']) . "/" . htmlspecialchars($e_pdf['total_pertemuan']) . " ($persentase_hadir_pdf%).";
                        } else if (isset($e_pdf['jumlah_hadir']) && $e_pdf['jumlah_hadir'] !== null) {
                             $keterangan_pdf .= " Kehadiran: " . htmlspecialchars($e_pdf['jumlah_hadir']) . " pertemuan.";
                        } else {
                            $keterangan_pdf .= " Kehadiran belum diisi.";
                        }
                ?>
                <tr>
                    <td class="text-center"><?php echo $no_ekskul_pdf++; ?></td>
                    <td><?php echo htmlspecialchars($e_pdf['nama_ekskul']); ?></td>
                    <td class="capaian"><?php echo $keterangan_pdf; ?></td>
                </tr>
                <?php
                    endwhile;
                } else {
                    echo "<tr><td colspan='3' class='text-center'>Tidak mengikuti kegiatan ekstrakurikuler.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <div style="width: 100%; margin-top: 15px; page-break-inside: avoid;">
            <div style="width: 40%; float: left;">
                <div class="section-title" style="margin-top:0;">D. KETIDAKHADIRAN</div>
                <table class="content-table">
                    <tr><td width="70%">Sakit</td><td>: <?php echo $rapor_pdf['sakit'] ?? 0; ?> hari</td></tr>
                    <tr><td>Izin</td><td>: <?php echo $rapor_pdf['izin'] ?? 0; ?> hari</td></tr>
                    <tr><td>Tanpa Keterangan</td><td>: <?php echo $rapor_pdf['tanpa_keterangan'] ?? 0; ?> hari</td></tr>
                </table>
            </div>
            <div style="width: 55%; float: right;">
                <div class="section-title" style="margin-top:0;">E. CATATAN WALI KELAS</div>
                <table class="content-table" style="height: 80px;">
                    <tr><td class="capaian"><?php echo !empty($rapor_pdf['catatan_wali_kelas']) ? nl2br(htmlspecialchars($rapor_pdf['catatan_wali_kelas'])) : '-'; ?></td></tr>
                </table>
            </div>
        </div>
        
        <div style="clear:both;"></div>
        
        <div class="section-title">Tanggapan Orang Tua/Wali Murid</div>
        <table class="content-table"><tr><td style="height: 80px;"></td></tr></table>

        <table class="signature-table" style="width: 100%; margin-top: 50px;">
            <tr>
                <td style="width: 33.33%; text-align: center;">
                    Orang Tua/Wali Murid
                    <div class="signature-space"></div>
                    ( ................................. )
                </td>
                <td style="width: 33.33%; text-align: center;">
                    Mengetahui,<br>
                    Kepala Sekolah
                    <div class="signature-space"></div>
                    <strong><u><?php echo htmlspecialchars($sekolah_pdf['nama_kepsek']); ?></u></strong><br>
                    <span style="font-size: 9pt;"><?php echo htmlspecialchars($sekolah_pdf['jabatan_kepsek']); ?></span><br>
                    NIP. <?php echo htmlspecialchars($sekolah_pdf['nip_kepsek']); ?>
                </td>
                <td style="width: 33.33%; text-align: center;">
                    <?php echo htmlspecialchars($sekolah_pdf['kabupaten_kota']); ?>, <?php echo $tanggal_rapor_pdf; ?><br>
                    Wali Kelas
                    <div class="signature-space"></div>
                    <strong><u><?php echo htmlspecialchars($siswa_pdf['nama_walikelas'] ?? ''); ?></u></strong><br>
                    NIP. <?php echo htmlspecialchars($siswa_pdf['nip_walikelas'] ?? ''); ?>
                </td>
            </tr>
        </table>
    </main>

</body>
</html>
<?php
// --- BAGIAN 4: PROSES RENDER HTML KE PDF ---
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', dirname(__FILE__)); // Menggunakan path dari file ini
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', false); // Nonaktifkan PHP untuk footer CSS

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
// [MODIFIKASI] Menggunakan Ukuran Kertas dari pengaturan
switch ($ukuran_kertas_pdf) {
    case 'F4':
        $width_pt = (215 / 25.4) * 72;
        $height_pt = (330 / 25.4) * 72;
        $ukuran_kertas_pdf = array(0, 0, $width_pt, $height_pt);
        break;
}
$dompdf->setPaper($ukuran_kertas_pdf, 'portrait');
$dompdf->render();

$filename = "Rapor - " . $siswa_pdf['nama_lengkap'] . " - Smst " . $semester_aktif_pdf . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
exit();
?>