<?php
session_start();
include 'koneksi.php';
include './utils/tanggal.php';
require_once 'libs/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;
if ($id_siswa == 0) die("Error: ID Siswa tidak valid.");

// Ambil semua data siswa, termasuk kolom foto_siswa
$q_siswa = mysqli_prepare($koneksi, "SELECT * FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa, "i", $id_siswa);
mysqli_stmt_execute($q_siswa);
$result_siswa = mysqli_stmt_get_result($q_siswa);

if(mysqli_num_rows($result_siswa) == 0) die("Error: Data siswa tidak ditemukan.");
$siswa = mysqli_fetch_assoc($result_siswa);

// --- PERUBAHAN: Ambil data sekolah (termasuk kecamatan) ---
$q_sekolah = mysqli_query($koneksi, "SELECT nama_kepsek, nip_kepsek, jabatan_kepsek, kecamatan FROM sekolah WHERE id_sekolah = 1");
$sekolah = mysqli_fetch_assoc($q_sekolah);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Identitas Siswa - <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></title>
    <style>
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
            text-align: center; 
            line-height: 4cm; 
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
    <div class="header">IDENTITAS PESERTA DIDIK</div>
    
    <div class="clearfix">
        <div class="photo-box">
    <?php
    $nama_file_foto = $siswa['foto_siswa'];
    $path_relatif = 'uploads/foto_siswa/' . $nama_file_foto;

    if (!empty($nama_file_foto) && file_exists($path_relatif)) {
        // Ambil tipe gambar (misal: image/jpeg)
        $type = pathinfo($path_relatif, PATHINFO_EXTENSION);
        // Baca data file gambar
        $data = file_get_contents($path_relatif);
        // Konversi data gambar menjadi base64
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        
        // Tampilkan gambar menggunakan data base64
        echo '<img src="' . $base64 . '" style="width: 100%; height: 100%; object-fit: cover;">';
    }
    ?>
</div>
        
        <table class="info-table">
            <tr>
                <td class="num">1.</td>
                <td class="label">Nama Lengkap Peserta Didik</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
            </tr>
            <tr>
                <td class="num">2.</td>
                <td class="label">NIS / NISN</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['nis']) . ' / ' . htmlspecialchars($siswa['nisn']); ?></td>
            </tr>
            <tr>
                <td class="num">3.</td>
                <td class="label">Tempat, Tanggal Lahir</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['tempat_lahir']) . ', ' . date_id('d F Y', strtotime($siswa['tanggal_lahir'])); ?></td>
            </tr>
            <tr>
                <td class="num">4.</td>
                <td class="label">Jenis Kelamin</td>
                <td class="separator">:</td>
                <td><?php echo ($siswa['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan'; ?></td>
            </tr>
            <tr>
                <td class="num">5.</td>
                <td class="label">Agama</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['agama']); ?></td>
            </tr>
            <tr>
                <td class="num">6.</td>
                <td class="label">Status dalam Keluarga</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['status_dalam_keluarga']); ?></td>
            </tr>
            <tr>
                <td class="num">7.</td>
                <td class="label">Anak ke</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['anak_ke']); ?></td>
            </tr>
            <tr>
                <td class="num">8.</td>
                <td class="label">Alamat Peserta Didik</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['alamat']); ?></td>
            </tr>
            <tr>
                <td class="num">9.</td>
                <td class="label">Nomor WA Orang Tua</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['telepon_siswa']); ?></td>
            </tr>
            <tr>
                <td class="num">10.</td>
                <td class="label">Sekolah Asal</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['sekolah_asal']); ?></td>
            </tr>
            <tr>
                <td class="num">11.</td>
                <td colspan="3">Diterima di sekolah ini</td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-left: 20px;">Di kelas</td>
                <td class="separator">:</td>
                <td>-</td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-left: 20px;">Pada tanggal</td>
                <td class="separator">:</td>
                <td><?php echo !empty($siswa['diterima_tanggal']) ? date_id('d F Y', strtotime($siswa['diterima_tanggal'])) : '-'; ?></td>
            </tr>
            <tr>
                <td class="num">12.</td>
                <td colspan="3">Nama Orang Tua</td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-left: 20px;">a. Ayah</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['nama_ayah']); ?></td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-left: 20px;">b. Ibu</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['nama_ibu']); ?></td>
            </tr>
             <tr>
                <td class="num">13.</td>
                <td class="label">Alamat Orang Tua</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['alamat']); ?></td>
            </tr>
             <tr>
                <td class="num">14.</td>
                <td class="label">Nomor Telepon Rumah</td>
                <td class="separator">:</td>
                <td>-</td>
            </tr>
             <tr>
                <td class="num">15.</td>
                <td colspan="3">Pekerjaan Orang Tua</td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-left: 20px;">a. Ayah</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['pekerjaan_ayah']); ?></td>
            </tr>
            <tr>
                <td></td>
                <td style="padding-left: 20px;">b. Ibu</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['pekerjaan_ibu']); ?></td>
            </tr>
            <tr>
                <td class="num">16.</td>
                <td class="label">Nama Wali Siswa</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['nama_wali']); ?></td>
            </tr>
            <tr>
                <td class="num">17.</td>
                <td class="label">Alamat Wali Peserta Didik</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['alamat_wali']); ?></td>
            </tr>
            <tr>
                <td class="num">18.</td>
                <td class="label">Nomor Telepon Rumah</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['telepon_wali']); ?></td>
            </tr>
            <tr>
                <td class="num">19.</td>
                <td class="label">Pekerjaan Wali Peserta Didik</td>
                <td class="separator">:</td>
                <td><?php echo htmlspecialchars($siswa['pekerjaan_wali']); ?></td>
            </tr>
        </table>
    </div>

    <div class="signature-block">
        <!-- PERUBAHAN: Lokasi dinamis -->
        <?php echo htmlspecialchars($sekolah['kecamatan']); ?>, <?php echo date_id('d F Y'); ?><br>
        Kepala Sekolah,
        <div class="signature-space"></div>
        <strong><u><?php echo htmlspecialchars($sekolah['nama_kepsek']); ?></u></strong><br>
        <?php echo htmlspecialchars($sekolah['jabatan_kepsek']); ?><br>
        NIP. <?php echo htmlspecialchars($sekolah['nip_kepsek']); ?>
    </div>

</body>
</html>
<?php
$html = ob_get_clean();
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Identitas - " . htmlspecialchars($siswa['nama_lengkap']) . ".pdf", ["Attachment" => 0]);
?>