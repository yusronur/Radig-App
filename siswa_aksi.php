<?php
// Menaikkan batas memori dan waktu eksekusi di awal skrip
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300'); // 5 menit

session_start();
include 'koneksi.php';

// --- PERBAIKAN: Otomatis ubah error mysqli menjadi Exceptions ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// ----------------------------------------------------------------

// Pastikan hanya admin yang bisa mengakses file ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak.");
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

//======================================================================
// --- AKSI TAMBAH SISWA (MANUAL) ---
//======================================================================
if ($aksi == 'tambah') {
    // Logika tambah siswa Anda (tidak diubah)
    $id_kelas = empty($_POST['id_kelas']) ? NULL : (int)$_POST['id_kelas'];
    $nisn = $_POST['nisn'];
    $nis = $_POST['nis'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $username = $_POST['username'];
    $password = password_hash($nisn, PASSWORD_DEFAULT);
    
    // [MODIFIKASI] Ambil id_wali_kelas saat tambah siswa baru
    $id_wali_kelas_baru = NULL;
    if ($id_kelas !== NULL) {
        try {
            $stmt_kelas = mysqli_prepare($koneksi, "SELECT id_wali_kelas FROM kelas WHERE id_kelas = ?");
            mysqli_stmt_bind_param($stmt_kelas, "i", $id_kelas);
            mysqli_stmt_execute($stmt_kelas);
            $result_kelas = mysqli_stmt_get_result($stmt_kelas);
            if ($data_kelas = mysqli_fetch_assoc($result_kelas)) {
                $id_wali_kelas_baru = $data_kelas['id_wali_kelas'];
            }
        } catch (Exception $e) { /* Biarkan NULL jika gagal */ }
    }
    
    $query = "INSERT INTO siswa (nisn, nis, nama_lengkap, username, password, id_kelas, id_guru_wali) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "sssssii", $nisn, $nis, $nama_lengkap, $username, $password, $id_kelas, $id_wali_kelas_baru);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['pesan'] = "Siswa baru berhasil ditambahkan.";
    } else {
        $_SESSION['error'] = "Gagal menambahkan siswa. Pastikan NISN dan Username unik.";
    }
    header("location: siswa_tampil.php?id_kelas=$id_kelas");
    exit();

//======================================================================
// --- AKSI UPDATE SISWA (MANUAL) --- (KODE INI TELAH DIPERBAIKI)
//======================================================================
} elseif ($aksi == 'update') {
    
    // Ambil data yang BENAR-BENAR DIKIRIM oleh siswa_edit.php
    $id_siswa = (int)$_POST['id_siswa'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $nisn = $_POST['nisn'];
    $nis = $_POST['nis'];
    $username = $_POST['username'];
    $password_baru = $_POST['password']; // Bisa kosong
    $id_kelas_baru = empty($_POST['id_kelas']) ? NULL : (int)$_POST['id_kelas'];

    // [MODIFIKASI PENTING]
    // Kita juga perlu mengupdate id_guru_wali berdasarkan id_kelas yang baru
    // Logika ini diambil dari aksi 'import_lengkap' Anda
    $id_wali_kelas_baru = NULL;
    if ($id_kelas_baru !== NULL) {
        try {
            $stmt_kelas = mysqli_prepare($koneksi, "SELECT id_wali_kelas FROM kelas WHERE id_kelas = ?");
            mysqli_stmt_bind_param($stmt_kelas, "i", $id_kelas_baru);
            mysqli_stmt_execute($stmt_kelas);
            $result_kelas = mysqli_stmt_get_result($stmt_kelas);
            if ($data_kelas = mysqli_fetch_assoc($result_kelas)) {
                $id_wali_kelas_baru = $data_kelas['id_wali_kelas']; // Bisa jadi NULL jika kelas tsb blm punya wali
            }
        } catch (Exception $e) {
            // Biarkan id_wali_kelas_baru tetap NULL jika query kelas gagal
        }
    }

    // Siapkan query berdasarkan apakah password diubah atau tidak
    if (!empty($password_baru)) {
        // --- Query JIKA PASSWORD BARU DIISI ---
        $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
        $query_update = "UPDATE siswa SET 
            nama_lengkap = ?, 
            nisn = ?, 
            nis = ?, 
            username = ?, 
            password = ?, 
            id_kelas = ?, 
            id_guru_wali = ? 
            WHERE id_siswa = ?";
        
        $stmt = mysqli_prepare($koneksi, $query_update);
        // Tipe data: string, string, string, string, string, integer, integer, integer
        mysqli_stmt_bind_param($stmt, "sssssiii", 
            $nama_lengkap, $nisn, $nis, $username, $hashed_password, 
            $id_kelas_baru, $id_wali_kelas_baru, $id_siswa);

    } else {
        // --- Query JIKA PASSWORD DIKOSONGKAN ---
        $query_update = "UPDATE siswa SET 
            nama_lengkap = ?, 
            nisn = ?, 
            nis = ?, 
            username = ?, 
            id_kelas = ?, 
            id_guru_wali = ? 
            WHERE id_siswa = ?";
        
        $stmt = mysqli_prepare($koneksi, $query_update);
        // Tipe data: string, string, string, string, integer, integer, integer
        mysqli_stmt_bind_param($stmt, "ssssiii", 
            $nama_lengkap, $nisn, $nis, $username, 
            $id_kelas_baru, $id_wali_kelas_baru, $id_siswa);
    }

    // Eksekusi query
    try {
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['pesan'] = "Data siswa $nama_lengkap berhasil diperbarui.";
        } else {
            $_SESSION['pesan_error'] = "Gagal memperbarui data. Pastikan NISN dan Username unik.";
        }
    } catch (mysqli_sql_exception $e) {
         // Tangkap error duplikat
         if ($e->getCode() == 1062) { // Error code for duplicate entry
            $_SESSION['pesan_error'] = "Gagal: Terdapat duplikasi data. Pastikan NISN dan Username unik.";
         } else {
            $_SESSION['pesan_error'] = "Gagal: " . str_replace("'", "", $e->getMessage());
         }
    }
    
    // Redirect kembali ke halaman daftar pengguna (sesuai tombol 'Kembali' di form siswa_edit.php)
    header("location: pengguna_tampil.php");
    exit();

//======================================================================
// --- AKSI HAPUS SISWA (MANUAL) ---
//======================================================================
} elseif ($aksi == 'hapus') {
    $id_siswa = (int)$_GET['id'];
    // [MODIFIKASI] Cek dari mana redirect berasal
    $id_kelas_redirect = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0; 

    // Hapus data terkait (sesuai file Anda & SQL)
    mysqli_query($koneksi, "DELETE FROM penilaian_detail_nilai WHERE id_siswa = $id_siswa");
    mysqli_query($koneksi, "DELETE FROM catatan_guru_wali WHERE id_siswa = $id_siswa");
    mysqli_query($koneksi, "DELETE FROM ekskul_peserta WHERE id_siswa = $id_siswa");
    mysqli_query($koneksi, "DELETE FROM kokurikuler_asesmen WHERE id_siswa = $id_siswa");
    mysqli_query($koneksi, "DELETE FROM mutasi_keluar WHERE id_siswa = $id_siswa");
    mysqli_query($koneksi, "DELETE FROM mutasi_masuk WHERE id_siswa = $id_siswa");

    $query_hapus = "DELETE FROM siswa WHERE id_siswa = ?";
    $stmt = mysqli_prepare($koneksi, $query_hapus);
    mysqli_stmt_bind_param($stmt, "i", $id_siswa);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['pesan'] = "Data siswa dan semua data terkait berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal menghapus data siswa.";
    }
    
    // [MODIFIKASI] Redirect cerdas
    if ($id_kelas_redirect > 0) {
        header("location: siswa_tampil.php?id_kelas=$id_kelas_redirect");
    } else {
        header("location: pengguna_tampil.php"); // Kembali ke manajemen pengguna
    }
    exit();

//======================================================================
// --- [BARU] AKSI HAPUS BANYAK SISWA ---
//======================================================================
} elseif ($aksi == 'hapus_banyak') {
    $siswa_ids = $_POST['siswa_ids'] ?? [];
    
    if (empty($siswa_ids)) {
        $_SESSION['error'] = "Tidak ada siswa yang dipilih untuk dihapus.";
        header("location:pengguna_tampil.php");
        exit();
    }

    $id_list = implode(',', array_map('intval', $siswa_ids));
    
    // Hapus data terkait (sesuai file Anda & SQL)
    mysqli_query($koneksi, "DELETE FROM penilaian_detail_nilai WHERE id_siswa IN ($id_list)");
    mysqli_query($koneksi, "DELETE FROM catatan_guru_wali WHERE id_siswa IN ($id_list)");
    mysqli_query($koneksi, "DELETE FROM ekskul_peserta WHERE id_siswa IN ($id_list)");
    mysqli_query($koneksi, "DELETE FROM kokurikuler_asesmen WHERE id_siswa IN ($id_list)");
    mysqli_query($koneksi, "DELETE FROM mutasi_keluar WHERE id_siswa IN ($id_list)");
    mysqli_query($koneksi, "DELETE FROM mutasi_masuk WHERE id_siswa IN ($id_list)");
    
    // Hapus data siswa
    $query = "DELETE FROM siswa WHERE id_siswa IN ($id_list)";
    
    if(mysqli_query($koneksi, $query)){
        $jumlah_terhapus = mysqli_affected_rows($koneksi);
        $_SESSION['pesan'] = "$jumlah_terhapus siswa berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal menghapus siswa. Error: " . mysqli_error($koneksi);
    }
    header("location:pengguna_tampil.php");
    exit();

//======================================================================
// --- AKSI IMPORT SISWA LENGKAP (dari template) ---
//======================================================================
} elseif ($aksi == 'import_lengkap') {
    
    require 'vendor/autoload.php';
    
    if (isset($_FILES['file_siswa_lengkap']['name']) && $_FILES['file_siswa_lengkap']['error'] == 0) {
        $file_name = $_FILES['file_siswa_lengkap']['name'];
        $file_tmp = $_FILES['file_siswa_lengkap']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext == 'xlsx') {
            $stmt_check_nisn = null;
            $stmt_check_kelas = null;
            $stmt_insert = null;
            $stmt_update = null;

            try {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $spreadsheet = $reader->load($file_tmp);
                $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
                
                mysqli_autocommit($koneksi, FALSE);

                $query_check_nisn = "SELECT id_siswa FROM siswa WHERE nisn = ? LIMIT 1";
                $stmt_check_nisn = mysqli_prepare($koneksi, $query_check_nisn);

                // [MODIFIKASI] Ambil id_kelas DAN id_wali_kelas dari TA Aktif
                $query_check_kelas = "SELECT k.id_kelas, k.id_wali_kelas FROM kelas k JOIN tahun_ajaran ta ON k.id_tahun_ajaran = ta.id_tahun_ajaran WHERE k.nama_kelas = ? AND ta.status = 'Aktif' LIMIT 1";
                $stmt_check_kelas = mysqli_prepare($koneksi, $query_check_kelas);

                // [MODIFIKASI] Tambahkan id_guru_wali
                $query_insert = "INSERT INTO siswa (nama_lengkap, jenis_kelamin, nisn, nis, tempat_lahir, tanggal_lahir, nik, agama, alamat, sekolah_asal, diterima_tanggal, anak_ke, status_dalam_keluarga, telepon_siswa, nama_ayah, pekerjaan_ayah, nama_ibu, pekerjaan_ibu, nama_wali, alamat_wali, telepon_wali, pekerjaan_wali, username, password, id_kelas, id_guru_wali, status_siswa) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Aktif')";
                $stmt_insert = mysqli_prepare($koneksi, $query_insert);

                // [MODIFIKASI] Tambahkan id_guru_wali
                $query_update = "UPDATE siswa SET nama_lengkap=?, jenis_kelamin=?, nis=?, tempat_lahir=?, tanggal_lahir=?, nik=?, agama=?, alamat=?, sekolah_asal=?, diterima_tanggal=?, anak_ke=?, status_dalam_keluarga=?, telepon_siswa=?, nama_ayah=?, pekerjaan_ayah=?, nama_ibu=?, pekerjaan_ibu=?, nama_wali=?, alamat_wali=?, telepon_wali=?, pekerjaan_wali=?, id_kelas=?, id_guru_wali=?, status_siswa='Aktif' WHERE nisn=?";
                $stmt_update = mysqli_prepare($koneksi, $query_update);
                
                $berhasil_tambah = 0;
                $berhasil_update = 0;
                $gagal = 0;
                $tanpa_kelas = 0;

                $baris_pertama = true;
                foreach ($sheetData as $row) {
                    if ($baris_pertama) { $baris_pertama = false; continue; }

                    $nama = trim($row['A'] ?? '');
                    $jk = trim($row['B'] ?? ''); 
                    $nisn = trim($row['C'] ?? '');
                    $nis = trim($row['D'] ?? '');
                    $tempat_lahir = trim($row['E'] ?? '');
                    $tanggal_lahir = trim($row['F'] ?? '');
                    $nik = trim($row['G'] ?? '');
                    $agama = trim($row['H'] ?? '');
                    $alamat = trim($row['I'] ?? '');
                    $sekolah_asal = trim($row['J'] ?? '');
                    $diterima_tanggal = trim($row['K'] ?? '');
                    $anak_ke = trim($row['L'] ?? '');
                    $status_keluarga = trim($row['M'] ?? ''); 
                    $telepon_siswa = trim($row['N'] ?? '');
                    $nama_ayah = trim($row['O'] ?? '');
                    $pekerjaan_ayah = trim($row['P'] ?? '');
                    $nama_ibu = trim($row['Q'] ?? '');
                    $pekerjaan_ibu = trim($row['R'] ?? '');
                    $nama_wali = trim($row['S'] ?? '');
                    $alamat_wali = trim($row['T'] ?? '');
                    $telepon_wali = trim($row['U'] ?? '');
                    $pekerjaan_wali = trim($row['V'] ?? '');
                    $username_excel = trim($row['W'] ?? '');
                    $password_excel = trim($row['X'] ?? '');
                    $nama_kelas = trim($row['Y'] ?? '');

                    if (empty($nama) || empty($nisn) || empty($username_excel) || empty($nama_kelas)) {
                        $gagal++;
                        continue;
                    }

                    $tanggal_lahir_db = !empty($tanggal_lahir) ? date('Y-m-d', strtotime($tanggal_lahir)) : NULL;
                    $diterima_tanggal_db = !empty($diterima_tanggal) ? date('Y-m-d', strtotime($diterima_tanggal)) : NULL;

                    mysqli_stmt_bind_param($stmt_check_kelas, "s", $nama_kelas);
                    mysqli_stmt_execute($stmt_check_kelas);
                    $result_kelas = mysqli_stmt_get_result($stmt_check_kelas);
                    if ($data_kelas = mysqli_fetch_assoc($result_kelas)) {
                        $id_kelas = $data_kelas['id_kelas'];
                        $id_wali_kelas = $data_kelas['id_wali_kelas']; // [MODIFIKASI]
                    } else {
                        $id_kelas = NULL;
                        $id_wali_kelas = NULL; // [MODIFIKASI]
                        $tanpa_kelas++;
                    }

                    $username = $username_excel;
                    $password_final = !empty($password_excel) ? password_hash($password_excel, PASSWORD_DEFAULT) : password_hash($nisn, PASSWORD_DEFAULT);

                    mysqli_stmt_bind_param($stmt_check_nisn, "s", $nisn);
                    mysqli_stmt_execute($stmt_check_nisn);
                    $result_nisn = mysqli_stmt_get_result($stmt_check_nisn);

                    if (mysqli_num_rows($result_nisn) > 0) { // UPDATE
                        mysqli_stmt_bind_param($stmt_update, "sssssssssssssssssssssiss", $nama, $jk, $nis, $tempat_lahir, $tanggal_lahir_db, $nik, $agama, $alamat, $sekolah_asal, $diterima_tanggal_db, $anak_ke, $status_keluarga, $telepon_siswa, $nama_ayah, $pekerjaan_ayah, $nama_ibu, $pekerjaan_ibu, $nama_wali, $alamat_wali, $telepon_wali, $pekerjaan_wali, $id_kelas, $id_wali_kelas, $nisn);
                        if(mysqli_stmt_execute($stmt_update)){ $berhasil_update++; } else { $gagal++; }
                    } else { // INSERT
                        mysqli_stmt_bind_param($stmt_insert, "ssssssssssssssssssssssssii", $nama, $jk, $nisn, $nis, $tempat_lahir, $tanggal_lahir_db, $nik, $agama, $alamat, $sekolah_asal, $diterima_tanggal_db, $anak_ke, $status_keluarga, $telepon_siswa, $nama_ayah, $pekerjaan_ayah, $nama_ibu, $pekerjaan_ibu, $nama_wali, $alamat_wali, $telepon_wali, $pekerjaan_wali, $username, $password_final, $id_kelas, $id_wali_kelas);
                        if(mysqli_stmt_execute($stmt_insert)){ $berhasil_tambah++; } else { $gagal++; }
                    }
                }
                
                mysqli_commit($koneksi);
                $pesan = "<b>Proses Import Selesai!</b><br>Siswa Baru: <b>$berhasil_tambah</b> | Diperbarui: <b>$berhasil_update</b> | Gagal: <b>$gagal</b> | Tanpa Kelas: <b>$tanpa_kelas</b>";
                $_SESSION['pesan'] = json_encode(['icon' => 'info', 'title' => 'Hasil Import', 'html' => $pesan]);
                
            } catch(Exception $e) {
                mysqli_rollback($koneksi);
                $_SESSION['error'] = "Gagal memproses file. Pastikan file tidak rusak. Error: " . $e->getMessage();
            } finally {
                if(isset($stmt_check_nisn)) mysqli_stmt_close($stmt_check_nisn);
                if(isset($stmt_check_kelas)) mysqli_stmt_close($stmt_check_kelas);
                if(isset($stmt_insert)) mysqli_stmt_close($stmt_insert);
                if(isset($stmt_update)) mysqli_stmt_close($stmt_update);
                mysqli_autocommit($koneksi, TRUE);
            }
        } else {
            $_SESSION['error'] = "Gagal! Format file harus .xlsx. Ekstensi file terdeteksi: .$file_ext";
        }
    } else {
        $upload_error = $_FILES['file_siswa_lengkap']['error'] ?? 'Tidak ada file';
        $_SESSION['error'] = "Gagal! Tidak ada file yang diunggah atau terjadi error. Kode Error: $upload_error";
    }
    
    header("location: pengguna_tampil.php"); // [MODIFIKASI] Redirect ke halaman baru
    exit();
}
//======================================================================
// --- AKSI RESET PASSWORD SISWA --- (dari siswa_edit.php)
//======================================================================
elseif ($aksi == 'reset_password') {
    // Logika reset password Anda (tidak diubah)
    $id_siswa = (int)$_GET['id'];
    // [MODIFIKASI] Ambil id_kelas dari form
    $id_kelas = (int)$_GET['id_kelas'];
    
    $q_nisn = mysqli_prepare($koneksi, "SELECT nisn FROM siswa WHERE id_siswa = ?");
    mysqli_stmt_bind_param($q_nisn, "i", $id_siswa);
    mysqli_stmt_execute($q_nisn);
    $nisn = mysqli_fetch_assoc(mysqli_stmt_get_result($q_nisn))['nisn'];
    if ($nisn) {
        $password_baru = password_hash($nisn, PASSWORD_DEFAULT);
        $q_update = mysqli_prepare($koneksi, "UPDATE siswa SET password = ? WHERE id_siswa = ?");
        mysqli_stmt_bind_param($q_update, "si", $password_baru, $id_siswa);
        if (mysqli_stmt_execute($q_update)) {
            $_SESSION['pesan'] = "Password siswa berhasil direset ke NISN siswa.";
        } else {
            $_SESSION['pesan_error'] = "Gagal mereset password.";
        }
    } else {
        $_SESSION['pesan_error'] = "Siswa tidak ditemukan untuk reset password.";
    }
    // [MODIFIKASI] Redirect kembali ke halaman edit, bukan pengguna_tampil
    header("location: siswa_edit.php?id=$id_siswa&id_kelas=$id_kelas");
    exit();
}

// Jika tidak ada aksi yang cocok
else {
    header("location: dashboard.php");
    exit();
}
?>