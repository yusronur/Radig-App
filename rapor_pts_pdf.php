<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'koneksi.php'; // Memastikan koneksi database disertakan
include 'utils/tanggal.php';

// Memanggil autoloader Dompdf
require_once 'libs/autoload.php'; // Pastikan path ini benar
use Dompdf\Dompdf;
use Dompdf\Options;

// --- PART 1: MENGAMBIL SEMUA DATA YANG DIPERLUKAN ---
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) {
    die("Error: Siswa tidak ditemukan.");
}

// Mengambil data pengaturan (Tahun Ajaran, Semester, Tanggal, Opsi Rapor) dari DB
$pengaturan_pdf = [];
$query_pengaturan_pdf = mysqli_query($koneksi, "SELECT * FROM pengaturan");
while ($row_pdf = mysqli_fetch_assoc($query_pengaturan_pdf)) {
    $pengaturan_pdf[$row_pdf['nama_pengaturan']] = $row_pdf['nilai_pengaturan'];
}

$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$d_ta = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran = $d_ta['id_tahun_ajaran'] ?? 0;
$tahun_ajaran = $d_ta['tahun_ajaran'] ?? 'N/A';

$semester_aktif = $pengaturan_pdf['semester_aktif'] ?? 1;
$semester_text = ($semester_aktif == 1) ? '1 (Ganjil)' : '2 (Genap)';

// [MODIFIKASI] Menggunakan tanggal PTS dari pengaturan
$tanggal_rapor_pts_db = $pengaturan_pdf['tanggal_rapor_pts'] ?? date_id("Y-m-d");
$tanggal_rapor_pts = date_id("d F Y", strtotime($tanggal_rapor_pts_db));

// [MODIFIKASI] Mengambil Ukuran Kertas dan Skema Warna
$ukuran_kertas_pdf = $pengaturan_pdf['rapor_ukuran_kertas'] ?? 'A4'; // Default A4
$skema_warna_pdf = $pengaturan_pdf['rapor_skema_warna'] ?? 'bw'; // Default Hitam Putih (bw)

// Mengambil data sekolah
$q_sekolah = mysqli_query($koneksi, "SELECT * FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);

// Mengambil data siswa dan kelas
$q_siswa_sql = "SELECT s.*, k.nama_kelas, k.fase, g.nama_guru as nama_walikelas, g.nip as nip_walikelas
             FROM siswa s
             LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
             LEFT JOIN guru g ON k.id_wali_kelas = g.id_guru
             WHERE s.id_siswa = ?";
$stmt_siswa = mysqli_prepare($koneksi, $q_siswa_sql);
mysqli_stmt_bind_param($stmt_siswa, "i", $id_siswa);
mysqli_stmt_execute($stmt_siswa);
$result_siswa = mysqli_stmt_get_result($stmt_siswa);
if (mysqli_num_rows($result_siswa) == 0) {
    die("Error: Data siswa dengan ID $id_siswa tidak ditemukan.");
}
$siswa = mysqli_fetch_assoc($result_siswa);
$id_kelas_siswa = $siswa['id_kelas'] ?? 0;

// [MODIFIKASI] Logika Skema Warna Hemat Tinta
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


// Mengambil data kehadiran
$sakit = 0;
$izin = 0;
$tanpa_keterangan = 0;
$q_kehadiran = mysqli_prepare($koneksi, "SELECT sakit, izin, tanpa_keterangan FROM rapor WHERE id_siswa = ? AND semester = ? AND id_tahun_ajaran = ? ORDER BY id_rapor DESC LIMIT 1");
mysqli_stmt_bind_param($q_kehadiran, "iii", $id_siswa, $semester_aktif, $id_tahun_ajaran);
mysqli_stmt_execute($q_kehadiran);
$result_kehadiran = mysqli_stmt_get_result($q_kehadiran);
if ($kehadiran = mysqli_fetch_assoc($result_kehadiran)) {
    $sakit = $kehadiran['sakit'] ?? 0;
    $izin = $kehadiran['izin'] ?? 0;
    $tanpa_keterangan = $kehadiran['tanpa_keterangan'] ?? 0;
}
mysqli_stmt_close($q_kehadiran);


// --- [LOGIKA BARU] FILTER MAPEL OTOMATIS ---
// 1. Ambil semua mapel yang mengandung kata 'Agama' dari database
$q_mapel_agama_all = mysqli_query($koneksi, "SELECT id_mapel, nama_mapel FROM mata_pelajaran WHERE nama_mapel LIKE '%Agama%'");
$list_id_semua_agama = [];
$id_mapel_agama_siswa = null;
$agama_siswa_string = trim($siswa['agama'] ?? ''); // misal 'Islam'

while ($row_agama = mysqli_fetch_assoc($q_mapel_agama_all)) {
    $id_mapel_db = $row_agama['id_mapel'];
    $nama_mapel_db = $row_agama['nama_mapel'];
    
    // Simpan ID ini sebagai 'ID mapel agama'
    $list_id_semua_agama[] = $id_mapel_db;

    // Cek apakah mapel ini cocok dengan agama siswa
    // Logika: Jika agama siswa (misal 'Islam') ada di dalam nama mapel (misal 'Pendidikan Agama Islam')
    if (!empty($agama_siswa_string) && stripos($nama_mapel_db, $agama_siswa_string) !== false) {
        $id_mapel_agama_siswa = $id_mapel_db;
    }
}

// Buat string ID untuk NOT IN (..., ...)
$string_id_semua_agama = !empty($list_id_semua_agama) ? implode(',', $list_id_semua_agama) : '0';

$query_mapel_string = "
    SELECT DISTINCT mp.id_mapel, mp.nama_mapel, mp.urutan 
    FROM mata_pelajaran mp
    JOIN guru_mengajar gm ON mp.id_mapel = gm.id_mapel
    WHERE 
        gm.id_kelas = $id_kelas_siswa 
        AND gm.id_tahun_ajaran = $id_tahun_ajaran
 ";

// Filter Pintar:
// Ambil mapel JIKA:
// 1. ID Mapel TERSEBUT BUKAN termasuk mapel agama (seperti Matematika, IPAS, dll)
//    ATAU
// 2. ID Mapel TERSEBUT ADALAH mapel agama milik siswa ini
$query_mapel_string .= " AND (
    mp.id_mapel NOT IN ($string_id_semua_agama) 
    OR mp.id_mapel = " . ($id_mapel_agama_siswa ? $id_mapel_agama_siswa : '0') . "
)";

$query_mapel_string .= " ORDER BY mp.urutan ASC, mp.nama_mapel ASC";
// --- AKHIR FILTER MAPEL OTOMATIS ---

$semua_mapel_query = mysqli_query($koneksi, $query_mapel_string);

$daftar_nilai_pts = [];

// Statement untuk mengambil rata-rata nilai 'Sumatif TP'
$query_avg_score = mysqli_prepare(
    $koneksi,
    "SELECT AVG(pdn.nilai) as rata_rata_nilai
     FROM penilaian_detail_nilai pdn
     JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
     WHERE pdn.id_siswa = ?
       AND p.id_mapel = ?
       AND p.id_kelas = ?
       AND p.semester = ?
       AND p.jenis_penilaian = 'Sumatif'
       AND p.subjenis_penilaian = 'Sumatif TP'"
);

if (!$query_avg_score) {
    die("Error preparing average score statement: " . mysqli_error($koneksi));
}

if ($semua_mapel_query) {
    while ($mapel = mysqli_fetch_assoc($semua_mapel_query)) {
        $id_mapel = $mapel['id_mapel'];
        $rata_rata = null;

        mysqli_stmt_bind_param($query_avg_score, "iiii", $id_siswa, $id_mapel, $id_kelas_siswa, $semester_aktif);
        mysqli_stmt_execute($query_avg_score);
        $result_avg = mysqli_stmt_get_result($query_avg_score);

        if ($row_avg = mysqli_fetch_assoc($result_avg)) {
            $rata_rata = ($row_avg['rata_rata_nilai'] !== null) ? round($row_avg['rata_rata_nilai']) : '-';
        } else {
            $rata_rata = '-';
        }

        $daftar_nilai_pts[] = [
            'nama_mapel' => $mapel['nama_mapel'],
            'nilai_pts' => $rata_rata
        ];
    }
}
mysqli_stmt_close($query_avg_score);


// Mengambil data watermark
$watermark_filename = $pengaturan_pdf['watermark_file'] ?? null;
$watermark_base64 = '';
if (!empty($watermark_filename)) {
    $watermark_full_path = 'uploads/' . $watermark_filename;
    if (file_exists($watermark_full_path) && is_readable($watermark_full_path)) {
        $type = pathinfo($watermark_full_path, PATHINFO_EXTENSION);
        $data = file_get_contents($watermark_full_path);
        $watermark_base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}


// --- PART 2: MEMBUAT STRUKTUR HTML UNTUK PDF ---
ob_start(); // Mulai output buffering
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Rapor PTS <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
        @page {
            margin: 140px 30px 40px 30px;
            /* top, right, bottom, left */
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #333;
        }

        header {
            position: fixed;
            top: -130px;
            /* Sesuaikan dengan margin atas */
            left: 0px;
            right: 0px;
        }

        .pagenum:before {
            content: counter(page);
        }

        .header-table {
            width: 100%;
            border-bottom: 3px solid #000;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }

        .header-table .logo-column {
            width: 15%;
            text-align: center;
            vertical-align: middle;
        }

        .header-table .kop-text {
            width: 70%;
            text-align: center;
            vertical-align: middle;
        }

        .header-table h4,
        .header-table h3,
        .header-table p {
            margin: 0;
            padding: 1px 0;
        }

        .header-table h4 {
            font-size: 13pt;
        }

        /* [MODIFIKASI] Warna KOP dinamis */
        .header-table h3 {
            font-size: 15pt;
            color: <?php echo $theme_color_kop; ?>;
        }

        .header_table p {
            font-size: 8pt;
            line-height: 1.2;
        }

        .report-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            /* [PERBAIKAN] Digeser ke bawah (dari 15px) */
            margin-bottom: 15px;
            color: #000;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 3px 5px;
        }

        .content-table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
            margin-top: 5px;
        }

        .content-table th,
        .content-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        /* [MODIFIKASI] Warna header tabel dinamis */
        .content-table th {
            background-color: <?php echo $theme_color_bg; ?>;
            color: <?php echo $theme_color_text; ?>;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }

        .section-title {
            /* [MODIFIKASI] Warna header tabel dinamis */
            background-color: <?php echo $theme_color_bg; ?>;
            color: <?php echo $theme_color_text; ?>;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 11pt;
            margin-top: 20px;
            margin-bottom: 0;
            border: 1px solid #000;
            border-bottom: none;
        }

        .text-center {
            text-align: center;
        }

        .signature-table {
            width: 100%;
            margin-top: 40px;
            font-size: 10pt;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }

        .signature-space {
            height: 60px;
        }

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
            width: 90%;
            /* [PERBAIKAN] Disesuaikan agar lebih pas */
            margin-top: -10%;
            /* [PERBAIKAN] Ditarik ke atas (dari 15%) */
        }
    </style>
</head>

<body>
    <div class="watermark">
        <?php if (!empty($watermark_base64)): ?>
            <img src="<?php echo $watermark_base64; ?>" alt="Watermark">
        <?php endif; ?>
    </div>

    <!-- Header -->
    <header>
        <table class="header-table">
            <tr>
                <!-- KIRI: Logo Kabupaten -->
                <td class="logo-column">
                    <?php
                    $path_kab_pts = 'uploads/logo_kabupaten.png'; // Path relatif dari CHROOT
                    if (file_exists($path_kab_pts)) {
                        $type_kab_pts = pathinfo($path_kab_pts, PATHINFO_EXTENSION);
                        $data_kab_pts = file_get_contents($path_kab_pts);
                        $base64_kab_pts = 'data:image/' . $type_kab_pts . ';base64,' . base64_encode($data_kab_pts);
                        echo '<img src="' . $base64_kab_pts . '" alt="Logo Kabupaten" style="width: 80px;">';
                    }
                    ?>
                </td>
                <!-- TENGAH: Teks KOP (Dinamis) -->
                <td class="kop-text">
                    <h4>PEMERINTAH KABUPATEN <?php echo strtoupper(htmlspecialchars($sekolah['kabupaten_kota'] ?? '')); ?></h4>
                    <h4>DINAS PENDIDIKAN</h4>
                    <h3><?php echo strtoupper(htmlspecialchars($sekolah['nama_sekolah'])); ?></h3>
                    <p><?php echo htmlspecialchars($sekolah['jalan'] ?? ''); ?>, Desa/Kel. <?php echo htmlspecialchars($sekolah['desa_kelurahan'] ?? ''); ?>, Kec. <?php echo htmlspecialchars($sekolah['kecamatan'] ?? ''); ?><br>
                        Telp: <?php echo htmlspecialchars($sekolah['telepon'] ?? '-'); ?> Email: <?php echo htmlspecialchars($sekolah['email'] ?? '-'); ?></p>
                </td>
                <!-- KANAN: Logo Sekolah (Dinamis) -->
                <td class="logo-column">
                    <?php if (!empty($sekolah['logo_sekolah'])):
                        $path_sekolah_pts = 'uploads/' . $sekolah['logo_sekolah'];
                        if (file_exists($path_sekolah_pts)) {
                            $type_sekolah_pts = pathinfo($path_sekolah_pts, PATHINFO_EXTENSION);
                            $data_sekolah_pts = file_get_contents($path_sekolah_pts);
                            $base64_sekolah_pts = 'data:image/' . $type_sekolah_pts . ';base64,' . base64_encode($data_sekolah_pts);
                            echo '<img src="' . $base64_sekolah_pts . '" alt="Logo Sekolah" style="width: 80px;">';
                        }
                    endif; ?>
                </td>
            </tr>
        </table>
    </header>

    <!-- Konten Utama -->
    <main>
        <div class="report-title">LAPORAN HASIL BELAJAR TENGAH SEMESTER</div>

        <table class="info-table">
            <tr>
                <td width="15%">Nama Murid</td>
                <td width="1%">:</td>
                <td width="34%"><b><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></b></td>
                <td width="15%">Kelas</td>
                <td width="1%">:</td>
                <td width="34%"><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
            </tr>
            <tr>
                <td>NISN / NIS</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($siswa['nisn']); ?> / <?php echo htmlspecialchars($siswa['nis'] ?? '-'); ?></td>
                <td>Fase</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($siswa['fase']); ?></td>
            </tr>
            <tr>
                <td>Sekolah</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($sekolah['nama_sekolah']); ?></td>
                <td>Semester</td>
                <td>:</td>
                <td><?php echo $semester_text; ?></td>
            </tr>
            <tr>
                <td>Alamat Sekolah</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($sekolah['jalan'] ?? ''); ?>, <?php echo htmlspecialchars($sekolah['desa_kelurahan'] ?? ''); ?></td>
                <td>Tahun Ajaran</td>
                <td>:</td>
                <td><?php echo htmlspecialchars($tahun_ajaran); ?></td>
            </tr>
        </table>

        <!-- Tabel Nilai Akademik (Tengah Semester) -->
        <div class="section-title">NILAI TENGAH SEMESTER</div>
        <table class="content-table">
            <thead>
                <tr>
                    <th width="5%">No.</th>
                    <th>Mata Pelajaran</th>
                    <th width="15%">Nilai PTS</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                foreach ($daftar_nilai_pts as $d):
                ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($d['nama_mapel']); ?></td>
                        <td class="text-center"><?php echo $d['nilai_pts']; ?></td>
                    </tr>
                <?php
                endforeach;
                if (empty($daftar_nilai_pts)) {
                    echo "<tr><td colspan='3' class='text-center'>Belum ada nilai Sumatif TP yang diinput untuk semester ini.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- [MODIFIKASI] Wrapper to make table narrower and centered -->
        <div style="width: 50%; margin: 0 auto; page-break-inside: avoid;">
            <!-- Attendance Table -->
            <div class="section-title">KETIDAKHADIRAN</div>
            <table class="content-table">
                <thead>
                    <th width="33.3%">Sakit</th>
                    <th width="33.3%">Izin</th>
                    <th width="33.3%">Tanpa Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center"><?php echo $sakit; ?> hari</td>
                        <td class="text-center"><?php echo $izin; ?> hari</td>
                        <td class="text-center"><?php echo $tanpa_keterangan; ?> hari</td>
                    </tr>
                </tbody>
            </table>
        </div>


        <!-- Bagian Tanda Tangan -->
        <table class="signature-table">
            <tr>
                <td>
                    Orang Tua/Wali Murid,
                    <div class="signature-space"></div>
                    ( ................................. )
                </td>
                <td>
                    Mengetahui,<br>
                    Kepala Sekolah,
                    <div class="signature-space"></div>
                    <b><?php echo htmlspecialchars($sekolah['nama_kepsek'] ?? ''); ?></b><br>
                    NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek'] ?? '-'); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($sekolah['kabupaten_kota'] ?? 'Tempat'); ?>, <?php echo $tanggal_rapor_pts; ?><br>
                    Wali Kelas,
                    <div class="signature-space"></div>
                    <b><?php echo htmlspecialchars($siswa['nama_walikelas'] ?? ''); ?></b><br>
                    NIP. <?php echo htmlspecialchars($siswa['nip_walikelas'] ?? '-'); ?>
                </td>
            </tr>
        </table>
    </main>

    <!-- Script untuk Footer (Nomor Halaman) -->
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Helvetica", "italic");
            $size = 8;
            $color = array(0.5, 0.5, 0.5); // Warna abu-abu
            
            $text = "<?php echo addslashes(htmlspecialchars($sekolah['nama_sekolah'])); ?>   |   <?php echo addslashes(htmlspecialchars($siswa['nama_lengkap'] ?? 'Siswa')); ?>   |   <?php echo addslashes(htmlspecialchars($siswa['nama_kelas'] ?? '-')); ?>   |   Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $page_width = $pdf->get_width();
            $x = ($page_width - $width) / 2;
            $y = $pdf->get_height() - 30;
            
            $pdf->page_text($x, $y, $text, $font, $size, $color);
        }
    </script>
</body>

</html>
<?php
// --- PART 3: RENDER HTML KE PDF ---
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true); // Diperlukan untuk footer nomor halaman

// Menentukan CHROOT ke direktori root aplikasi Anda
$chroot_path = dirname(__FILE__); // Menggunakan path dari file ini
$options->set('chroot', $chroot_path);

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

$filename = "RaporPTS - " . preg_replace('/[^A-Za-z0-9_\-]/', '_', $siswa['nama_lengkap']) . " - Smstr " . $semester_aktif . ".pdf";
$dompdf->stream($filename, array("Attachment" => 0));
exit();
?>