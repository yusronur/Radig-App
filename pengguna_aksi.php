<?php
session_start();
include 'koneksi.php';

// Keamanan dasar: hanya admin yang boleh akses file ini
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { 
    // Mengirim header 'Forbidden' dan menghentikan eksekusi
    header('HTTP/1.0 403 Forbidden');
    die("Akses ditolak."); 
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

// --- AKSI TAMBAH PENGGUNA ---
if ($aksi == 'tambah') {
    // Validasi input dasar
    if (empty($_POST['nama_guru']) || empty($_POST['username']) || empty($_POST['password'])) {
        $_SESSION['error'] = json_encode([
            'icon' => 'warning',
            'title' => 'Data Tidak Lengkap',
            'html' => 'Nama, Username, dan Password wajib diisi.'
        ]);
        header("location:pengguna_tampil.php");
        exit();
    }

    $nama = $_POST['nama_guru'];
    $nip = !empty($_POST['nip']) ? $_POST['nip'] : null;
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    try {
        $query = "INSERT INTO guru (nama_guru, nip, username, password, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "sssss", $nama, $nip, $username, $password, $role);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['pesan'] = json_encode([
                'icon' => 'success',
                'title' => 'Berhasil!',
                'html' => 'Pengguna baru berhasil ditambahkan.'
            ]);
        } else {
            // Ini mungkin tidak akan tereksekusi jika 'mysqli_report' aktif, tapi sebagai cadangan
            $error_code = mysqli_stmt_errno($stmt);
            if ($error_code == 1062) { // 1062 = Error duplicate entry
                $_SESSION['error'] = json_encode([
                    'icon' => 'error',
                    'title' => 'Gagal',
                    'html' => "Username '<b>" . htmlspecialchars($username) . "</b>' atau NIP sudah ada."
                ]);
            } else {
                $_SESSION['error'] = json_encode([
                    'icon' => 'error',
                    'title' => 'Gagal',
                    'html' => 'Gagal menambahkan pengguna: ' . htmlspecialchars(mysqli_stmt_error($stmt))
                ]);
            }
        }
    
    } catch (mysqli_sql_exception $e) {
        // [PERBAIKAN UTAMA] Menangkap 500 Error (jika execute() melempar exception)
        if ($e->getCode() == 1062) { // 1062 = Error duplicate entry
            $_SESSION['error'] = json_encode([
                'icon' => 'error',
                'title' => 'Gagal',
                'html' => "Username '<b>" . htmlspecialchars($username) . "</b>' atau NIP sudah ada."
            ]);
        } else {
            $_SESSION['error'] = json_encode([
                'icon' => 'error',
                'title' => 'Error Database',
                'html' => 'Terjadi kesalahan teknis. Silakan coba lagi.<br><small>' . htmlspecialchars($e->getMessage()) . '</small>'
            ]);
        }
    }
    
    header("location:pengguna_tampil.php");
    exit();

// --- AKSI UPDATE PENGGUNA ---
} elseif ($aksi == 'update') {
    // Validasi input dasar
    if (empty($_POST['id_guru']) || empty($_POST['nama_guru']) || empty($_POST['username'])) {
        $_SESSION['error'] = json_encode([
            'icon' => 'warning',
            'title' => 'Data Tidak Lengkap',
            'html' => 'Data tidak lengkap untuk melakukan update.'
        ]);
        header("location:pengguna_tampil.php");
        exit();
    }

    $id_guru = $_POST['id_guru'];
    $nama = $_POST['nama_guru'];
    $nip = !empty($_POST['nip']) ? $_POST['nip'] : null;
    $username = $_POST['username'];
    $role = $_POST['role'];
    $penugasan_dipilih = isset($_POST['penugasan']) ? $_POST['penugasan'] : [];

    // [PERBAIKAN] Mulai transaksi untuk memastikan semua query berhasil
    mysqli_begin_transaction($koneksi);

    try {
        // 1. Update data utama di tabel guru
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query = "UPDATE guru SET nama_guru=?, nip=?, username=?, password=?, role=? WHERE id_guru=?";
            $stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($stmt, "sssssi", $nama, $nip, $username, $password, $role, $id_guru);
        } else {
            $query = "UPDATE guru SET nama_guru=?, nip=?, username=?, role=? WHERE id_guru=?";
            $stmt = mysqli_prepare($koneksi, $query);
            mysqli_stmt_bind_param($stmt, "ssssi", $nama, $nip, $username, $role, $id_guru);
        }
        mysqli_stmt_execute($stmt); // Ini bisa melempar exception

        // 2. Ambil tahun ajaran aktif
        $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
        $data_ta = mysqli_fetch_assoc($q_ta);
        $id_tahun_ajaran = $data_ta ? $data_ta['id_tahun_ajaran'] : 0;

        if ($id_tahun_ajaran == 0) {
            // Jika tidak ada TA aktif, lempar error untuk di-rollback
            throw new Exception("Tidak ada Tahun Ajaran aktif ditemukan.");
        }

        // 3. Hapus penugasan lama guru ini di tahun ajaran aktif
        $stmt_delete = mysqli_prepare($koneksi, "DELETE FROM guru_mengajar WHERE id_guru = ? AND id_tahun_ajaran = ?");
        mysqli_stmt_bind_param($stmt_delete, "ii", $id_guru, $id_tahun_ajaran);
        mysqli_stmt_execute($stmt_delete);

        // 4. Proses penugasan baru jika role-nya adalah 'guru'
        if ($role == 'guru' && !empty($penugasan_dipilih)) {
            $stmt_insert = mysqli_prepare($koneksi, "INSERT INTO guru_mengajar (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");
            foreach ($penugasan_dipilih as $id_mapel => $daftar_kelas) {
                if (is_array($daftar_kelas)) {
                    foreach ($daftar_kelas as $id_kelas) {
                        mysqli_stmt_bind_param($stmt_insert, "iiii", $id_guru, $id_mapel, $id_kelas, $id_tahun_ajaran);
                        mysqli_stmt_execute($stmt_insert);
                    }
                }
            }
        }

        // Jika semua berhasil, commit transaksi
        mysqli_commit($koneksi);
        
        $_SESSION['pesan'] = json_encode([
            'icon' => 'success',
            'title' => 'Berhasil!',
            'html' => 'Data pengguna berhasil diperbarui.'
        ]);

    } catch (mysqli_sql_exception $e) {
        // [PERBAIKAN UTAMA] Tangkap error 500 dan batalkan semua perubahan
        mysqli_rollback($koneksi);
        
        if ($e->getCode() == 1062) { // 1062 = Error duplicate entry
            $_SESSION['error'] = json_encode([
                'icon' => 'error',
                'title' => 'Gagal',
                'html' => "Update gagal. Username '<b>" . htmlspecialchars($username) . "</b>' atau NIP sudah digunakan."
            ]);
        } else {
            $_SESSION['error'] = json_encode([
                'icon' => 'error',
                'title' => 'Error Database',
                'html' => 'Terjadi kesalahan teknis saat update. Perubahan dibatalkan.<br><small>' . htmlspecialchars($e->getMessage()) . '</small>'
            ]);
        }
    } catch (Exception $e) {
        // Menangkap error non-SQL (seperti "Tahun Ajaran tidak aktif")
        mysqli_rollback($koneksi);
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Gagal Update',
            'html' => htmlspecialchars($e->getMessage())
        ]);
    }

    header("location:pengguna_tampil.php");
    exit();


// --- AKSI HAPUS PENGGUNA ---
} elseif ($aksi == 'hapus') {
    $id_guru = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id_guru == 0) {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'ID Tidak Valid',
            'html' => 'ID pengguna tidak valid untuk dihapus.'
        ]);
        header("location:pengguna_tampil.php");
        exit();
    }
    
    // Untuk mencegah admin menghapus akunnya sendiri
    if ($id_guru == $_SESSION['id_guru']) {
        $_SESSION['error'] = json_encode([
            'icon' => 'warning',
            'title' => 'Aksi Ditolak',
            'html' => 'Anda tidak dapat menghapus akun Anda sendiri.'
        ]);
        header("location:pengguna_tampil.php");
        exit();
    }

    $query = "DELETE FROM guru WHERE id_guru=?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "i", $id_guru);
    
    if(mysqli_stmt_execute($stmt)){
        $_SESSION['pesan'] = json_encode([
            'icon' => 'success',
            'title' => 'Berhasil',
            'html' => 'Data pengguna berhasil dihapus.'
        ]);
    } else {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Gagal',
            'html' => 'Gagal menghapus data pengguna. Error: ' . htmlspecialchars(mysqli_stmt_error($stmt))
        ]);
    }
    header("location:pengguna_tampil.php");
    exit();

// --- [BARU] AKSI HAPUS PENUGASAN TIDAK AKTIF (TUNGGAL) ---
} elseif ($aksi == 'hapus_penugasan') {
    $id_gm = isset($_GET['id_gm']) ? (int)$_GET['id_gm'] : 0;
    $id_guru_redirect = isset($_GET['id_guru_redirect']) ? (int)$_GET['id_guru_redirect'] : 0;

    if ($id_gm == 0 || $id_guru_redirect == 0) {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'ID Tidak Valid',
            'html' => 'ID penugasan atau ID guru tidak valid.'
        ]);
        header("location:pengguna_tampil.php");
        exit();
    }

    // Pastikan admin hanya menghapus penugasan yang memang milik guru tsb (keamanan tambahan)
    $query = "DELETE FROM guru_mengajar WHERE id_guru_mengajar = ? AND id_guru = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "ii", $id_gm, $id_guru_redirect);
    
    if(mysqli_stmt_execute($stmt)){
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $_SESSION['pesan'] = json_encode([
                'icon' => 'success',
                'title' => 'Berhasil',
                'html' => 'Penugasan di tahun ajaran tidak aktif berhasil dihapus.'
            ]);
        } else {
            $_SESSION['error'] = json_encode([
                'icon' => 'warning',
                'title' => 'Gagal',
                'html' => 'Gagal menghapus penugasan (data tidak ditemukan atau tidak cocok).'
            ]);
        }
    } else {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Gagal',
            'html' => 'Gagal menghapus penugasan. Error: ' . htmlspecialchars(mysqli_stmt_error($stmt))
        ]);
    }
    // Redirect kembali ke halaman edit guru
    header("location:pengguna_edit.php?id=" . $id_guru_redirect);
    exit();

// --- AKSI HAPUS BANYAK PENGGUNA ---
} elseif ($aksi == 'hapus_banyak') {
    $user_ids = $_POST['user_ids'] ?? [];
    
    if (empty($user_ids)) {
        $_SESSION['error'] = json_encode([
            'icon' => 'warning',
            'title' => 'Tidak Ada Data',
            'html' => 'Tidak ada pengguna yang dipilih untuk dihapus.'
        ]);
        header("location:pengguna_tampil.php");
        exit();
    }

    // Filter untuk memastikan admin tidak menghapus dirinya sendiri
    $filtered_ids = array_filter($user_ids, function($id) {
        return (int)$id != $_SESSION['id_guru'];
    });

    if (count($user_ids) > count($filtered_ids)) {
        // Jika ada percobaan hapus diri sendiri, beri notifikasi
        $_SESSION['error'] = json_encode([
            'icon' => 'warning',
            'title' => 'Aksi Ditolak',
            'html' => 'Anda tidak dapat menghapus akun Anda sendiri dari daftar hapus banyak.'
        ]);
        // Jika *hanya* akun sendiri yang dipilih, hentikan
        if (empty($filtered_ids)) {
            header("location:pengguna_tampil.php");
            exit();
        }
    }

    // Ubah array ID menjadi string yang aman untuk query IN
    $id_list = implode(',', array_map('intval', $filtered_ids));
    
    $query = "DELETE FROM guru WHERE id_guru IN ($id_list)";
    
    if(mysqli_query($koneksi, $query)){
        $jumlah_terhapus = mysqli_affected_rows($koneksi);
        $_SESSION['pesan'] = json_encode([
            'icon' => 'success',
            'title' => 'Berhasil',
            'html' => "<b>$jumlah_terhapus</b> pengguna berhasil dihapus."
        ]);
    } else {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Gagal',
            'html' => 'Gagal menghapus pengguna. Error: ' . htmlspecialchars(mysqli_error($koneksi))
        ]);
    }
    header("location:pengguna_tampil.php");
    exit();

// --- AKSI IMPORT PENGGUNA (SIMPEL) ---
} elseif ($aksi == 'import') {
    require 'vendor/autoload.php';

    $file_mimes = array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    
    if(isset($_FILES['file_pengguna']['name']) && in_array($_FILES['file_pengguna']['type'], $file_mimes)) {
        
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($_FILES['file_pengguna']['tmp_name']);
        $sheetData = $spreadsheet->getActiveSheet()->toArray();
        
        $berhasil = 0;
        $gagal = 0;
        $pesan_gagal = [];

        $query = "INSERT IGNORE INTO guru (nip, nama_guru, username, password, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($koneksi, $query);

        for($i = 1; $i < count($sheetData); $i++) {
            $nip = trim($sheetData[$i][0] ?? '');
            $nama = trim($sheetData[$i][1] ?? '');
            $username = trim($sheetData[$i][2] ?? '');
            $role = strtolower(trim($sheetData[$i][3] ?? ''));
            $password_excel = trim($sheetData[$i][4] ?? '');

            if(empty($nama) || empty($username) || !in_array($role, ['admin', 'guru'])) {
                $gagal++;
                $pesan_gagal[] = "Baris " . ($i + 1) . ": Data tidak lengkap atau role tidak valid.";
                continue;
            }

            $password_to_hash = !empty($password_excel) ? $password_excel : $username;
            $password = password_hash($password_to_hash, PASSWORD_DEFAULT);
            $nip_final = !empty($nip) ? $nip : null;
            
            mysqli_stmt_bind_param($stmt, "sssss", $nip_final, $nama, $username, $password, $role);
            mysqli_stmt_execute($stmt);
            
            if(mysqli_stmt_affected_rows($stmt) > 0){
                $berhasil++;
            } else {
                $gagal++;
                $pesan_gagal[] = "Baris " . ($i + 1) . ": Username atau NIP mungkin sudah ada.";
            }
        }
        
        $html_report = "Proses import selesai.<br>Berhasil: <b>$berhasil</b><br>Gagal: <b>$gagal</b>";
        if(!empty($pesan_gagal)) {
            $html_report .= "<br><small>" . implode("<br>", array_slice($pesan_gagal, 0, 5)) . (count($pesan_gagal) > 5 ? "<br>...dan lainnya." : "") . "</small>";
        }
        
        $_SESSION['pesan'] = json_encode([
            'icon' => $gagal > 0 ? 'warning' : 'success',
            'title' => 'Import Selesai',
            'html' => $html_report
        ]);

    } else {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Gagal Upload',
            'html' => 'Gagal! Pastikan file yang Anda unggah adalah format .xlsx yang benar.'
        ]);
    }

    header("location: pengguna_tampil.php");
    exit();

// --- AKSI IMPORT GURU & MENGAJAR (LENGKAP) ---
} elseif ($aksi == 'import_mengajar') {
    // Blok ini sudah menggunakan format JSON, jadi tidak perlu diubah
    require 'vendor/autoload.php';

    $file_mimes = array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    
    if(!isset($_FILES['file_guru_mengajar']['name']) || !in_array($_FILES['file_guru_mengajar']['type'], $file_mimes)) {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Gagal Upload',
            'html' => 'Gagal! Pastikan file yang Anda unggah adalah format .xlsx yang benar.'
        ]);
        header("location: pengguna_tampil.php");
        exit();
    }

    // 1. Ambil data penting dari DB
    $q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'] ?? 0;

    if ($id_tahun_ajaran_aktif == 0) {
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Gagal Import',
            'html' => 'Gagal import: Tidak ada Tahun Ajaran yang berstatus \'Aktif\' di sistem.'
        ]);
        header("location: pengguna_tampil.php");
        exit();
    }

    // 2. Buat cache Mapel (Kode Mapel -> id_mapel)
    $mapel_cache = [];
    $query_mapel = mysqli_query($koneksi, "SELECT id_mapel, kode_mapel FROM mata_pelajaran WHERE kode_mapel IS NOT NULL AND kode_mapel != ''");
    while($m = mysqli_fetch_assoc($query_mapel)) {
        $mapel_cache[strtoupper(trim($m['kode_mapel']))] = $m['id_mapel'];
    }

    // 3. Buat cache Kelas (Nama Kelas -> id_kelas) untuk TA Aktif
    $kelas_cache = [];
    $query_kelas = mysqli_query($koneksi, "SELECT id_kelas, nama_kelas FROM kelas WHERE id_tahun_ajaran = $id_tahun_ajaran_aktif");
    while($k = mysqli_fetch_assoc($query_kelas)) {
        $kelas_cache[strtoupper(trim($k['nama_kelas']))] = $k['id_kelas'];
    }

    $processed_gurus = [];
    $guru_baru_dibuat = 0;
    $penugasan_berhasil = 0;
    $gagal_total = 0;
    $pesan_error_detail = [];

    $stmt_cek_guru = mysqli_prepare($koneksi, "SELECT id_guru FROM guru WHERE username = ?");
    $stmt_insert_guru = mysqli_prepare($koneksi, "INSERT INTO guru (nip, nama_guru, username, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt_insert_penugasan = mysqli_prepare($koneksi, "INSERT IGNORE INTO guru_mengajar (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");

    mysqli_begin_transaction($koneksi);

    try {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($_FILES['file_guru_mengajar']['tmp_name']);
        $sheetData = $spreadsheet->getSheetByName('Import Guru & Mengajar')->toArray();

        for($i = 1; $i < count($sheetData); $i++) {
            $row = $sheetData[$i];
            $baris_ke = $i + 1;

            $username = trim($row[2] ?? '');
            if (empty($username)) {
                $gagal_total++;
                $pesan_error_detail[] = "Baris $baris_ke: Username wajib diisi.";
                continue;
            }

            $id_guru_to_assign = null;

            if (isset($processed_gurus[$username])) {
                $id_guru_to_assign = $processed_gurus[$username];
            } else {
                mysqli_stmt_bind_param($stmt_cek_guru, "s", $username);
                mysqli_stmt_execute($stmt_cek_guru);
                $result_guru = mysqli_stmt_get_result($stmt_cek_guru);
                $data_guru = mysqli_fetch_assoc($result_guru);

                if ($data_guru) {
                    $id_guru_to_assign = $data_guru['id_guru'];
                } else {
                    $nip = !empty(trim($row[0] ?? '')) ? trim($row[0]) : null;
                    $nama = trim($row[1] ?? '');
                    $role = strtolower(trim($row[3] ?? ''));
                    $password_excel = trim($row[4] ?? '');

                    if (empty($nama) || !in_array($role, ['admin', 'guru'])) {
                        $gagal_total++;
                        $pesan_error_detail[] = "Baris $baris_ke: Data guru baru (Nama/Role) tidak lengkap/valid untuk username $username.";
                        continue;
                    }
                    
                    $password_to_hash = !empty($password_excel) ? $password_excel : $username;
                    $password = password_hash($password_to_hash, PASSWORD_DEFAULT);

                    mysqli_stmt_bind_param($stmt_insert_guru, "sssss", $nip, $nama, $username, $password, $role);
                    if (mysqli_stmt_execute($stmt_insert_guru)) {
                        $id_guru_to_assign = mysqli_insert_id($koneksi);
                        $guru_baru_dibuat++;
                    } else {
                        $gagal_total++;
                        $pesan_error_detail[] = "Baris $baris_ke: Gagal menyimpan guru baru $username (NIP mungkin duplikat?).";
                        continue;
                    }
                }
                $processed_gurus[$username] = $id_guru_to_assign;
            }

            $role_guru = strtolower(trim($row[3] ?? '')); 
            if ($role_guru == 'guru') {
                $kode_mapel = strtoupper(trim($row[5] ?? ''));
                $nama_kelas = strtoupper(trim($row[6] ?? ''));

                if (empty($kode_mapel) || empty($nama_kelas)) {
                    $gagal_total++;
                    $pesan_error_detail[] = "Baris $baris_ke: Kode Mapel & Nama Kelas wajib diisi untuk $username.";
                    continue;
                }

                if (!isset($mapel_cache[$kode_mapel])) {
                    $gagal_total++;
                    $pesan_error_detail[] = "Baris $baris_ke: Kode Mapel '$kode_mapel' tidak ditemukan di database.";
                    continue;
                }
                if (!isset($kelas_cache[$nama_kelas])) {
                    $gagal_total++;
                    $pesan_error_detail[] = "Baris $baris_ke: Nama Kelas '$nama_kelas' tidak ditemukan di Tahun Ajaran Aktif.";
                    continue;
                }

                $id_mapel = $mapel_cache[$kode_mapel];
                $id_kelas = $kelas_cache[$nama_kelas];

                mysqli_stmt_bind_param($stmt_insert_penugasan, "iiii", $id_guru_to_assign, $id_mapel, $id_kelas, $id_tahun_ajaran_aktif);
                mysqli_stmt_execute($stmt_insert_penugasan);
                
                if (mysqli_stmt_affected_rows($stmt_insert_penugasan) > 0) {
                    $penugasan_berhasil++;
                }
            }
        } 

        if ($gagal_total > 0) {
            mysqli_rollback($koneksi);
            $html_errors = "Proses import GAGAL dan dibatalkan (Rollback).<br>Ditemukan $gagal_total error:<br><small><ul>";
            foreach (array_slice($pesan_error_detail, 0, 10) as $err) {
                $html_errors .= "<li>" . htmlspecialchars($err) . "</li>";
            }
            if(count($pesan_error_detail) > 10) $html_errors .= "<li>...dan lainnya.</li>";
            $html_errors .= "</ul></small>Perbaiki file Excel Anda dan coba lagi.";
            
            $_SESSION['error'] = json_encode([
                'icon' => 'error',
                'title' => 'Import Gagal Total',
                'html' => $html_errors
            ]);

        } else {
            mysqli_commit($koneksi);
            $html_success = "Proses import selesai.<br>";
            $html_success .= "<b>$guru_baru_dibuat</b> guru baru berhasil dibuat.<br>";
            $html_success .= "<b>$penugasan_berhasil</b> penugasan mengajar berhasil ditambahkan.";
            
            $_SESSION['pesan'] = json_encode([
                'icon' => 'success',
                'title' => 'Import Berhasil',
                'html' => $html_success
            ]);
        }

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['error'] = json_encode([
            'icon' => 'error',
            'title' => 'Error Server',
            'html' => "Terjadi error saat pemrosesan file: " . htmlspecialchars($e->getMessage())
        ]);
    }

    header("location: pengguna_tampil.php");
    exit();
}

// Jika tidak ada aksi yang cocok
else {
    header("location: dashboard.php");
    exit();
}
?>