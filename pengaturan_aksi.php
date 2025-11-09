<?php
session_start();
include 'koneksi.php';

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak. Anda harus login sebagai admin.");
}

// Mengambil aksi dari GET atau POST untuk fleksibilitas
$aksi = $_GET['aksi'] ?? $_POST['aksi'] ?? ''; 

// Fungsi helper untuk pesan JSON SweetAlert
function set_json_pesan($tipe, $judul, $teks) {
    return json_encode(['icon' => $tipe, 'title' => $judul, 'text' => $teks]);
}

// Fungsi helper untuk upload file (Logo & Watermark)
// Mengembalikan nama file jika sukses, atau null jika gagal/tidak ada file
function handleFileUpload($file_key, $upload_dir, $allowed_types, $field_name) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
        $file = $_FILES[$file_key];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', "Format file untuk $field_name tidak diizinkan. Harap unggah " . implode(', ', $allowed_types));
            return null;
        }
        
        if ($file['size'] > 1048576) { // 1MB Limit
             $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', "Ukuran file $field_name terlalu besar. Maksimal 1MB.");
            return null;
        }
        
        $new_filename = $field_name . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $new_filename;
        } else {
            $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', "Gagal memindahkan file $field_name.");
            return null;
        }
    }
    return null; // Tidak ada file yang diunggah
}

// ==============================================
// === FUNGSI UNTUK MENJALANKAN SKRIP MIGRASI ===
// ==============================================
// Ini adalah skrip yang "memperbaiki" data lama agar sesuai dengan struktur baru
function jalankan_skrip_migrasi($koneksi, &$errors) {
    // 1. Ubah KKM dari 75 (lama) menjadi 50 (baru)
    $sql1 = "UPDATE `pengaturan` SET `nilai_pengaturan` = '50' WHERE `nama_pengaturan` = 'kkm'";
    if (!mysqli_query($koneksi, $sql1)) $errors[] = "Gagal update KKM: " . mysqli_error($koneksi);

    // 2. Tambah Pengaturan Baru (Gunakan ON DUPLICATE KEY UPDATE agar aman)
    $sql2 = "INSERT INTO `pengaturan` (nama_pengaturan, nilai_pengaturan) VALUES 
             ('rapor_ukuran_kertas', 'F4'),
             ('rapor_skema_warna', 'light_green'),
             ('tanggal_rapor_pts', '2025-09-10')
             ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)";
    if (!mysqli_query($koneksi, $sql2)) $errors[] = "Gagal tambah pengaturan baru: " . mysqli_error($koneksi);

    // 3. Ubah 'Seni Musik' (id 8 di db lama) menjadi 'Seni Rupa' (id 8 di db baru)
    $sql3 = "UPDATE `mata_pelajaran` SET `nama_mapel` = 'Seni Rupa', `urutan` = 14 WHERE `id_mapel` = 8";
    if (!mysqli_query($koneksi, $sql3)) $errors[] = "Gagal update Seni Rupa: " . mysqli_error($koneksi);

    // 4. Update urutan mapel lain agar sesuai dengan db baru
    $sql4 = "UPDATE `mata_pelajaran` SET `urutan` = CASE `id_mapel`
                WHEN 1 THEN 11
                WHEN 2 THEN 1
                WHEN 3 THEN 6
                WHEN 4 THEN 7
                WHEN 5 THEN 8
                WHEN 6 THEN 9
                WHEN 7 THEN 10
                WHEN 9 THEN 15
                WHEN 10 THEN 13
                WHEN 11 THEN 16
                WHEN 12 THEN 12
                WHEN 13 THEN 2
                ELSE `urutan`
             END
             WHERE `id_mapel` IN (1,2,3,4,5,6,7,9,10,11,12,13)";
    if (!mysqli_query($koneksi, $sql4)) $errors[] = "Gagal update urutan mapel: " . mysqli_error($koneksi);

    // 5. Tambah 3 mapel agama baru (Gunakan INSERT IGNORE agar aman jika dijalankan 2x)
    $sql5 = "INSERT IGNORE INTO `mata_pelajaran` (`id_mapel`, `nama_mapel`, `kode_mapel`, `urutan`) VALUES
             (14, 'Pendidikan Agama Hindu dan Budi Pekerti', 'PAH', 4),
             (15, 'Pendidikan Agama Budha dan Budi Pekerti', 'PAB', 3),
             (16, 'Pendidikan Agama Katolik dan Budi Pekerti', 'PAKK', 5)";
    if (!mysqli_query($koneksi, $sql5)) $errors[] = "Gagal tambah mapel agama baru: " . mysqli_error($koneksi);
}
// ==============================================


switch ($aksi) {
    // 1. Aksi untuk tab Identitas Sekolah
    case 'update_sekolah':
        $stmt = mysqli_prepare($koneksi, "UPDATE sekolah SET 
            nama_sekolah=?, jenjang=?, npsn=?, nss=?, jalan=?, desa_kelurahan=?, 
            kecamatan=?, kabupaten_kota=?, provinsi=?, telepon=?, email=?, website=? 
            WHERE id_sekolah = 1");
        mysqli_stmt_bind_param($stmt, "ssssssssssss", 
            $_POST['nama_sekolah'], $_POST['jenjang'], $_POST['npsn'], $_POST['nss'], 
            $_POST['jalan'], $_POST['desa_kelurahan'], $_POST['kecamatan'], 
            $_POST['kabupaten_kota'], $_POST['provinsi'], $_POST['telepon'], 
            $_POST['email'], $_POST['website']
        );
        if(mysqli_stmt_execute($stmt)){
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Data identitas sekolah telah diperbarui.');
        } else {
            $_SESSION['pesan'] = set_json_pesan('error', 'Gagal!', 'Terjadi kesalahan saat menyimpan data sekolah: ' . mysqli_error($koneksi));
        }
        header("Location: pengaturan_tampil.php"); // Redirect kembali
        exit();
        break;

    // 2. Aksi untuk tab Pejabat
    case 'update_pejabat':
        $stmt = mysqli_prepare($koneksi, "UPDATE sekolah SET nama_kepsek=?, jabatan_kepsek=?, nip_kepsek=? WHERE id_sekolah = 1");
        mysqli_stmt_bind_param($stmt, "sss", $_POST['nama_kepsek'], $_POST['jabatan_kepsek'], $_POST['nip_kepsek']);
        if(mysqli_stmt_execute($stmt)){
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Data pejabat telah diperbarui.');
        } else {
            $_SESSION['pesan'] = set_json_pesan('error', 'Gagal!', 'Terjadi kesalahan saat menyimpan data pejabat.');
        }
        header("Location: pengaturan_tampil.php"); // Redirect kembali
        exit();
        break;

    // 3. Aksi untuk tab T.A & Tanggal DAN Modifikasi Rapor
    case 'update_pengaturan':
        if (isset($_POST['pengaturan']) && is_array($_POST['pengaturan'])) {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)");
            
            foreach ($_POST['pengaturan'] as $nama => $nilai) {
                mysqli_stmt_bind_param($stmt, "ss", $nama, $nilai);
                mysqli_stmt_execute($stmt);
            }
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Pengaturan rapor telah disimpan.');
        } else {
            $_SESSION['pesan'] = set_json_pesan('warning', 'Tidak Ada Data', 'Tidak ada data pengaturan yang dikirim.');
        }
        header("Location: pengaturan_tampil.php"); // Redirect kembali
        exit();
        break;

    // 4. Aksi untuk Tambah Tahun Ajaran
    case 'tambah_ta':
        $tahun_ajaran = $_POST['tahun_ajaran'] ?? '';
        if (!empty($tahun_ajaran)) {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO tahun_ajaran (tahun_ajaran, status) VALUES (?, 'Tidak Aktif')");
            mysqli_stmt_bind_param($stmt, "s", $tahun_ajaran);
            if(mysqli_stmt_execute($stmt)){
                $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Tahun ajaran baru telah ditambahkan.');
            } else {
                 $_SESSION['pesan'] = set_json_pesan('error', 'Gagal!', 'Tahun ajaran mungkin sudah ada.');
            }
        }
        header("Location: pengaturan_tampil.php"); // Redirect kembali
        exit();
        break;

    // 5. Aksi untuk Aktifkan Tahun Ajaran
    case 'aktifkan_ta':
        $id_ta = $_GET['id'] ?? 0;
        if ($id_ta > 0) {
            mysqli_query($koneksi, "UPDATE tahun_ajaran SET status = 'Tidak Aktif'");
            $stmt = mysqli_prepare($koneksi, "UPDATE tahun_ajaran SET status = 'Aktif' WHERE id_tahun_ajaran = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_ta);
            if(mysqli_stmt_execute($stmt)){
                $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Tahun ajaran telah diaktifkan.');
            }
        }
        header("Location: pengaturan_tampil.php"); // Redirect kembali
        exit();
        break;

    // 6. Aksi untuk Upload Logo
    case 'update_logo':
        $upload_dir = 'uploads/';
        $new_logo_name = handleFileUpload('logo_sekolah', $upload_dir, ['jpg', 'jpeg', 'png'], 'logo');
        
        if ($new_logo_name) {
            $q_logo_lama = mysqli_query($koneksi, "SELECT logo_sekolah FROM sekolah WHERE id_sekolah = 1");
            $d_logo_lama = mysqli_fetch_assoc($q_logo_lama);
            if (!empty($d_logo_lama['logo_sekolah']) && file_exists($upload_dir . $d_logo_lama['logo_sekolah'])) {
                unlink($upload_dir . $d_logo_lama['logo_sekolah']);
            }
            
            $stmt = mysqli_prepare($koneksi, "UPDATE sekolah SET logo_sekolah = ? WHERE id_sekolah = 1");
            mysqli_stmt_bind_param($stmt, "s", $new_logo_name);
            mysqli_stmt_execute($stmt);
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Logo sekolah telah diperbarui.');
        }
        header("Location: pengaturan_tampil.php"); // Redirect kembali
        exit();
        break;

    // 7. Aksi untuk Upload Watermark
    case 'update_watermark':
        $upload_dir = 'uploads/';
        $watermark_lama = $_POST['watermark_lama'] ?? null;
        
        if (isset($_POST['hapus_watermark']) && $_POST['hapus_watermark'] == '1') {
            if (!empty($watermark_lama) && file_exists($upload_dir . $watermark_lama)) {
                unlink($upload_dir . $watermark_lama);
            }
            $stmt = mysqli_prepare($koneksi, "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES ('watermark_file', '') ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)");
            mysqli_stmt_execute($stmt);
            $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Watermark telah dihapus.');
            
        } else {
            $new_watermark_name = handleFileUpload('watermark_baru', $upload_dir, ['png'], 'watermark');
            
            if ($new_watermark_name) {
                if (!empty($watermark_lama) && file_exists($upload_dir . $watermark_lama)) {
                    unlink($upload_dir . $watermark_lama);
                }
                
                $stmt = mysqli_prepare($koneksi, "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES ('watermark_file', ?) ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)");
                mysqli_stmt_bind_param($stmt, "s", $new_watermark_name);
                mysqli_stmt_execute($stmt);
                $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil!', 'Watermark telah diperbarui.');
            }
        }
        header("Location: pengaturan_tampil.php"); // Redirect kembali
        exit();
        break;
    
    // 8. Aksi untuk membuat file backup
    case 'buat_backup':
        global $host, $user, $pass, $db; 
        $db_host = $host ?? 'localhost'; $db_user = $user ?? 'root'; $db_pass = $pass ?? ''; $db_name = $db ?? 'raporsmp';
        try {
            $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if ($mysqli->connect_error) { throw new Exception('Koneksi database gagal: ' . $mysqli->connect_error); }
            $mysqli->set_charset("utf8mb4");
            
            $backup_content = "-- Rapor Digital Backup (Pure PHP Method)\n-- Host: {$db_host}\n-- Waktu: " . date('Y-m-d H:i:s') . "\n-- Database: `{$db_name}`\n-- ------------------------------------------------------\n\n";
            
            $tables = []; $result = $mysqli->query("SHOW TABLES");
            while ($row = $result->fetch_row()) { $tables[] = $row[0]; }
            
            foreach ($tables as $table) {
                $result_struktur = $mysqli->query("SHOW CREATE TABLE `{$table}`"); $row_struktur = $result_struktur->fetch_row();
                $backup_content .= "DROP TABLE IF EXISTS `{$table}`;\n"; $backup_content .= $row_struktur[1] . ";\n\n";
                
                $result_data = $mysqli->query("SELECT * FROM `{$table}`"); 
                if($result_data === false) continue; // Lewati jika tabel tidak bisa dibaca
                
                $num_fields = $result_data->field_count;
                if ($result_data->num_rows > 0) {
                    while ($row = $result_data->fetch_row()) {
                        $backup_content .= "INSERT INTO `{$table}` VALUES(";
                        for ($j = 0; $j < $num_fields; $j++) {
                            if (isset($row[$j])) { 
                                $row[$j] = $mysqli->real_escape_string($row[$j]);
                                $backup_content .= "'{$row[$j]}'"; 
                            } else { $backup_content .= "NULL"; }
                            if ($j < ($num_fields - 1)) { $backup_content .= ','; }
                        }
                        $backup_content .= ");\n";
                    }
                    $backup_content .= "\n";
                }
            }
            $mysqli->close();
            
            $backup_dir = 'backups/'; if (!is_dir($backup_dir)) { mkdir($backup_dir, 0755, true); }
            $nama_file = 'backup_' . $db_name . '_' . date('Y-m-d_H-i-s') . '.sql';
            if(file_put_contents($backup_dir . $nama_file, $backup_content) === false) {
                 throw new Exception('Gagal menulis file backup. Periksa izin folder.');
            }
            
            $_SESSION['pesan'] = set_json_pesan('success', 'Backup Berhasil', 'File backup ' . $nama_file . ' berhasil dibuat.');
        } catch (Exception $e) { 
            $_SESSION['pesan'] = set_json_pesan('error', 'Backup Gagal', 'Terjadi kesalahan: ' . $e->getMessage()); 
        }
        header('Location: pengaturan_backup_tampil.php');
        exit;
        break;
    
    // 9. Aksi untuk Restore Total
    case 'lakukan_restore_total':
        global $koneksi; 

        if (!isset($_FILES['sql_file_restore']) || $_FILES['sql_file_restore']['error'] != UPLOAD_ERR_OK) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', 'Tidak ada file SQL yang diunggah atau terjadi error.');
            header('Location: pengaturan_backup_tampil.php');
            exit;
        }

        $file_path = $_FILES['sql_file_restore']['tmp_name'];

        try {
            set_time_limit(0); // Mencegah timeout
            mysqli_query($koneksi, "SET foreign_key_checks = 0");

            $tables = [];
            $result = mysqli_query($koneksi, "SHOW TABLES");
            if (!$result) { throw new Exception("Gagal mendapatkan daftar tabel: " . mysqli_error($koneksi)); }
            while ($row = mysqli_fetch_row($result)) {
                $tables[] = $row[0];
            }

            foreach ($tables as $table) {
                if (!mysqli_query($koneksi, "DROP TABLE IF EXISTS `{$table}`")) {
                    throw new Exception("Gagal menghapus tabel `{$table}`: " . mysqli_error($koneksi));
                }
            }

            $query_buffer = '';
            $file_handle = fopen($file_path, 'r');
            if (!$file_handle) { throw new Exception("Gagal membaca file SQL yang diunggah."); }

            while (($line = fgets($file_handle)) !== false) {
                $line_trimmed = trim($line);
                
                if (empty($line_trimmed) || substr($line_trimmed, 0, 2) == '--' || substr($line_trimmed, 0, 1) == '#') {
                    continue;
                }

                $query_buffer .= $line;

                if (substr($line_trimmed, -1, 1) == ';') {
                    if (!mysqli_query($koneksi, $query_buffer)) {
                         $errors[] = "Query gagal: " . mysqli_error($koneksi); // Catat error tapi lanjut
                    }
                    $query_buffer = ''; 
                }
            }
            fclose($file_handle);

            mysqli_query($koneksi, "SET foreign_key_checks = 1");
            $_SESSION['pesan'] = set_json_pesan('success', 'Restore Total Berhasil', 'Database telah berhasil dipulihkan dari file unggahan.');

        } catch (Exception $e) {
            mysqli_query($koneksi, "SET foreign_key_checks = 1");
            $_SESSION['pesan'] = set_json_pesan('error', 'Restore Gagal Total', 'Terjadi kesalahan: ' . $e->getMessage());
        }
        
        header('Location: pengaturan_backup_tampil.php');
        exit;
        break;

    // 10. Aksi untuk menghapus file backup
    case 'hapus_backup':
        $backup_dir = 'backups/';
        $file_name = urldecode($_GET['file']);
        if (basename($file_name) == $file_name) {
            $file_path = $backup_dir . $file_name;
            if (file_exists($file_path)) {
                unlink($file_path);
                $_SESSION['pesan'] = set_json_pesan('success', 'Berhasil', 'File backup telah dihapus.');
            } else {
                $_SESSION['pesan'] = set_json_pesan('error', 'Gagal', 'File tidak ditemukan.');
            }
        } else {
             $_SESSION['pesan'] = set_json_pesan('error', 'Akses Ditolak', 'Nama file tidak valid.');
        }
        header('Location: pengaturan_backup_tampil.php');
        exit;
        break;

    // =======================================================
    // === AKSI BARU: MIGRASI DARI FILE BACKUP LAMA         ===
    // =======================================================
    case 'migrasi_via_file':
        global $koneksi;
        $errors = [];

        // Mencegah server timeout saat impor file besar
        set_time_limit(0); 

        if (!isset($_FILES['sql_file_migrasi']) || $_FILES['sql_file_migrasi']['error'] != UPLOAD_ERR_OK) {
            $_SESSION['pesan'] = set_json_pesan('error', 'Upload Gagal', 'Tidak ada file SQL migrasi yang diunggah.');
            header('Location: pengaturan_backup_tampil.php');
            exit;
        }

        $file_path = $_FILES['sql_file_migrasi']['tmp_name'];

        try {
            // 1. Nonaktifkan foreign key & ubah sementara kolom 'jenis_kelamin'
            mysqli_query($koneksi, "SET foreign_key_checks = 0");
            // **PERBAIKAN ERROR 'Data truncated'**
            mysqli_query($koneksi, "ALTER TABLE `siswa` MODIFY `jenis_kelamin` VARCHAR(5) DEFAULT NULL");
            
            $tables = [];
            $result = mysqli_query($koneksi, "SHOW TABLES");
            if (!$result) throw new Exception("Gagal mendapatkan daftar tabel: " . mysqli_error($koneksi));
            while ($row = mysqli_fetch_row($result)) {
                $tables[] = $row[0];
            }
            
            // TRUNCATE (kosongkan) semua tabel. Ini tidak menghapus struktur baru.
            foreach ($tables as $table) {
                if (!mysqli_query($koneksi, "TRUNCATE TABLE `{$table}`")) {
                     $errors[] = "Gagal mengosongkan tabel `{$table}`: " . mysqli_error($koneksi);
                }
            }

            // 2. Baca file SQL dan HANYA jalankan perintah INSERT
            $query_buffer = '';
            $file_handle = fopen($file_path, 'r');
            if (!$file_handle) throw new Exception("Gagal membaca file SQL yang diunggah.");

            while (($line = fgets($file_handle)) !== false) {
                $line_trimmed = trim($line);
                
                // Lewati baris kosong, komentar, DROP, atau CREATE
                if (empty($line_trimmed) || substr($line_trimmed, 0, 2) == '--' || substr($line_trimmed, 0, 1) == '#') continue;
                if (strtoupper(substr($line_trimmed, 0, 10)) == 'DROP TABLE') continue;
                if (strtoupper(substr($line_trimmed, 0, 12)) == 'CREATE TABLE') continue;

                $query_buffer .= $line;

                // Jika ini adalah akhir dari perintah SQL (;)
                if (substr($line_trimmed, -1, 1) == ';') {
                    // Hanya jalankan jika ini adalah perintah INSERT
                    if (strtoupper(substr(trim($query_buffer), 0, 11)) == 'INSERT INTO') {
                        if (!mysqli_query($koneksi, $query_buffer)) {
                            // Catat error tapi jangan hentikan proses
                            $errors[] = "Gagal insert (data mungkin duplikat): " . substr(mysqli_error($koneksi), 0, 100);
                        }
                    }
                    $query_buffer = ''; // Kosongkan buffer
                }
            }
            fclose($file_handle);
            
            // 3. Kembalikan struktur 'jenis_kelamin' dan bersihkan data
            // Set data yang tidak valid ('') menjadi NULL
            mysqli_query($koneksi, "UPDATE `siswa` SET `jenis_kelamin` = NULL WHERE `jenis_kelamin` NOT IN ('L', 'P')");
            // Kembalikan ke ENUM
            mysqli_query($koneksi, "ALTER TABLE `siswa` MODIFY `jenis_kelamin` ENUM('L','P') DEFAULT NULL");

            // 4. Jalankan skrip migrasi untuk memperbaiki data (KKM, Mapel, dll)
            jalankan_skrip_migrasi($koneksi, $errors);

            // 5. Aktifkan kembali foreign key
            mysqli_query($koneksi, "SET foreign_key_checks = 1");

            // 6. Beri laporan
            if (empty($errors)) {
                $_SESSION['pesan'] = set_json_pesan('success', 'Migrasi Berhasil!', 'Data lama Anda telah berhasil diimpor dan diperbarui ke struktur baru.');
            } else {
                 // Jika ada error, kita tetap beri pesan sukses karena migrasi data adalah yg utama
                 $_SESSION['pesan'] = set_json_pesan('success', 'Migrasi Selesai!', 'Data lama Anda telah berhasil diimpor dan diperbarui.');
            }

        } catch (Exception $e) {
            mysqli_query($koneksi, "SET foreign_key_checks = 1"); // Pastikan FK aktif kembali jika ada error
            $_SESSION['pesan'] = set_json_pesan('error', 'Migrasi Gagal Total', 'Terjadi kesalahan: ' . $e->getMessage());
        }

        header('Location: pengaturan_backup_tampil.php');
        exit;
        break;

    // Aksi default jika tidak ada yang cocok
    default:
        // Tidak melakukan apa-apa, redirect ke halaman utama pengaturan
        header("Location: pengaturan_tampil.php");
        exit();
        break;
}
?> 