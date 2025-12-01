<?php
// File ini dipanggil oleh rapor_cetak_massal.php
// Variabel $koneksi dan $id_siswa sudah tersedia dari file tersebut.

// Ambil semua data siswa yang ada
$q_siswa_identitas = mysqli_prepare($koneksi, "SELECT * FROM siswa WHERE id_siswa = ?");
mysqli_stmt_bind_param($q_siswa_identitas, "i", $id_siswa);
mysqli_stmt_execute($q_siswa_identitas);
$result_siswa_identitas = mysqli_stmt_get_result($q_siswa_identitas);

if(mysqli_num_rows($result_siswa_identitas) == 0) {
    echo "<p>Error: Data siswa dengan ID $id_siswa tidak ditemukan.</p>";
    return; 
}
$siswa_identitas = mysqli_fetch_assoc($result_siswa_identitas);

// --- PERUBAHAN: Ambil data sekolah (termasuk kecamatan) ---
$q_sekolah_identitas = mysqli_query($koneksi, "SELECT nama_kepsek, nip_kepsek, jabatan_kepsek, kecamatan FROM sekolah WHERE id_sekolah = 1");
$sekolah_identitas = mysqli_fetch_assoc($q_sekolah_identitas);
?>

<!-- KONTEN HTML DIMULAI (TIDAK ADA <html>, <head>, dll.) -->
<div class="header">IDENTITAS PESERTA DIDIK</div>

<div class="clearfix">
    <div class="photo-box">
        <?php
        $nama_file_foto = $siswa_identitas['foto_siswa'];
        $path_relatif_foto = 'uploads/foto_siswa/' . $nama_file_foto; 
        
        if (!empty($nama_file_foto) && file_exists($path_relatif_foto)) {
            $type = pathinfo($path_relatif_foto, PATHINFO_EXTENSION);
            $data = file_get_contents($path_relatif_foto);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            echo '<img src="' . $base64 . '" style="width: 100%; height: 100%; object-fit: cover;">';
        }
        ?>
    </div>
    
    <table class="info-table">
        <tr>
            <td class="num">1.</td>
            <td class="label">Nama Lengkap Peserta Didik</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['nama_lengkap']); ?></td>
        </tr>
        <tr>
            <td class="num">2.</td>
            <td class="label">NIS / NISN</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['nis']) . ' / ' . htmlspecialchars($siswa_identitas['nisn']); ?></td>
        </tr>
        <tr>
            <td class="num">3.</td>
            <td class="label">Tempat, Tanggal Lahir</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['tempat_lahir']) . ', ' . date_id('d F Y', strtotime($siswa_identitas['tanggal_lahir'])); ?></td>
        </tr>
        <tr>
            <td class="num">4.</td>
            <td class="label">Jenis Kelamin</td>
            <td class="separator">:</td>
            <td><?php echo ($siswa_identitas['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan'; ?></td>
        </tr>
        <tr>
            <td class="num">5.</td>
            <td class="label">Agama</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['agama']); ?></td>
        </tr>
        <tr>
            <td class="num">6.</td>
            <td class="label">Status dalam Keluarga</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['status_dalam_keluarga']); ?></td>
        </tr>
        <tr>
            <td class="num">7.</td>
            <td class="label">Anak ke</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['anak_ke']); ?></td>
        </tr>
        <tr>
            <td class="num">8.</td>
            <td class="label">Alamat Peserta Didik</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['alamat']); ?></td>
        </tr>
        <tr>
            <td class="num">9.</td>
            <td class="label">Nomor WA Orang Tua</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['telepon_siswa']); ?></td>
        </tr>
        <tr>
            <td class="num">10.</td>
            <td class="label">Sekolah Asal</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['sekolah_asal']); ?></td>
        </tr>
        <tr>
            <td class="num">11.</td>
            <td colspan="3">Diterima di sekolah ini</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-left: 20px;">Di kelas</td>
            <td class="separator">:</td>
            <td>-</td> </tr>
        <tr>
            <td></td>
            <td style="padding-left: 20px;">Pada tanggal</td>
            <td class="separator">:</td>
            <td><?php echo !empty($siswa_identitas['diterima_tanggal']) ? date_id('d F Y', strtotime($siswa_identitas['diterima_tanggal'])) : '-'; ?></td>
        </tr>
        <tr>
            <td class="num">12.</td>
            <td colspan="3">Nama Orang Tua</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-left: 20px;">a. Ayah</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['nama_ayah']); ?></td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-left: 20px;">b. Ibu</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['nama_ibu']); ?></td>
        </tr>
         <tr>
            <td class="num">13.</td>
            <td class="label">Alamat Orang Tua</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['alamat']); ?></td>
        </tr>
         <tr>
            <td class="num">14.</td>
            <td class="label">Nomor Telepon</td>
            <td class="separator">:</td>
             <td><?php echo htmlspecialchars($siswa_identitas['telepon_siswa']); ?></td>
        </tr>
         <tr>
            <td class="num">15.</td>
            <td colspan="3">Pekerjaan Orang Tua</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-left: 20px;">a. Ayah</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['pekerjaan_ayah']); ?></td>
        </tr>
        <tr>
            <td></td>
            <td style="padding-left: 20px;">b. Ibu</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['pekerjaan_ibu']); ?></td>
        </tr>
        <tr>
            <td class="num">16.</td>
            <td class="label">Nama Wali Siswa</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['nama_wali']); ?></td>
        </tr>
        <tr>
            <td class="num">17.</td>
            <td class="label">Alamat Wali Peserta Didik</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['alamat_wali']); ?></td>
        </tr>
        <tr>
            <td class="num">18.</td>
            <td class="label">Nomor Telepon Wali</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['telepon_wali']); ?></td>
        </tr>
        <tr>
            <td class="num">19.</td>
            <td class="label">Pekerjaan Wali Peserta Didik</td>
            <td class="separator">:</td>
            <td><?php echo htmlspecialchars($siswa_identitas['pekerjaan_wali']); ?></td>
        </tr>
    </table>
</div>

<div class="signature-block">
    <?php echo htmlspecialchars($sekolah_identitas['kecamatan']); ?>, <?php echo date_id('d F Y'); ?><br>
    Kepala Sekolah,
    <div class="signature-space"></div>
    <strong><u><?php echo htmlspecialchars($sekolah_identitas['nama_kepsek']); ?></u></strong><br>
    <?php echo htmlspecialchars($sekolah_identitas['jabatan_kepsek']); ?><br>
    NIP. <?php echo htmlspecialchars($sekolah_identitas['nip_kepsek']); ?>
</div>
<!-- KONTEN HTML BERAKHIR -->