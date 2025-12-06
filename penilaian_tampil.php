<?php
include 'koneksi.php';
include 'header.php';

// ===========================================================
// VALIDASI AKSES
// ===========================================================
// Pastikan hanya GURU yang bisa mengakses halaman ini, bukan admin
if ($_SESSION['role'] != 'guru') {
    echo "<script>Swal.fire({icon: 'error', title: 'Akses Ditolak', text: 'Halaman ini hanya untuk Guru.'}).then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

// Ambil ID guru yang sedang login dari sesi
$id_guru_login = $_SESSION['id_guru'];

// Ambil id mapel dan kelas dari URL
$id_mapel = isset($_GET['id_mapel']) ? (int)$_GET['id_mapel'] : 0;
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;

// Ambil tahun ajaran aktif untuk query
$q_ta_aktif = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran_aktif = mysqli_fetch_assoc($q_ta_aktif)['id_tahun_ajaran'] ?? 0;

// Ambil semester aktif
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// --- Inisialisasi variabel di scope global agar JS tidak error ---
$penugasan_by_mapel = []; 
?>

<style>
    /* CSS Styling Modern */
    .page-header { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); padding: 2.5rem 2rem; border-radius: 0.75rem; color: white; }
    .page-header h1 { font-weight: 700; }
    .card-title i { color: var(--primary-color); }
    .penilaian-group-header { font-weight: 600; color: var(--secondary-color); padding-bottom: 0.5rem; border-bottom: 2px solid var(--secondary-color); margin-top: 2.5rem; margin-bottom: 1.5rem; }
    .deskripsi-rapor-cell { max-width: 400px; min-width: 300px; white-space: normal; font-size: 0.85em; line-height: 1.4; }
    
    /* --- CSS BARU: Style untuk Fitur Katrol & Toggle Modern --- */
    .kolom-katrol { display: none; background-color: #fff3cd !important; width: 100px; }
    .input-katrol { width: 100%; text-align: center; border: 1px solid #ffc107; border-radius: 8px; padding: 6px; font-weight: 800; color: #856404; transition: all 0.3s; }
    .input-katrol:focus { outline: none; border-color: #d39e00; box-shadow: 0 0 8px rgba(255, 193, 7, 0.5); transform: scale(1.05); }
    .badge-katrol-aktif { border: 2px solid #ffc107; color: #000; background-color: #fff3cd !important; box-shadow: 0 0 10px rgba(255, 193, 7, 0.4); }

    /* Modern Toggle Switch */
    .toggle-wrapper { position: relative; }
    .toggle-checkbox { display: none; } 
    
    .toggle-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        width: 200px;
        height: 50px;
        background: rgba(255, 255, 255, 0.15); 
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 50px;
        position: relative;
        padding: 5px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        backdrop-filter: blur(8px);
    }
    
    .toggle-label:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }

    /* Bola Switch */
    .toggle-ball {
        width: 40px;
        height: 40px;
        background: white;
        border-radius: 50%;
        position: absolute;
        left: 5px;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); 
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        color: #ccc;
        font-size: 1.2rem;
    }

    /* Teks Label */
    .toggle-text {
        position: absolute;
        color: white;
        font-weight: 700;
        font-size: 0.85rem;
        letter-spacing: 1px;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
        text-transform: uppercase;
        pointer-events: none;
    }
    
    .toggle-text-off { right: 20px; opacity: 1; }
    .toggle-text-on { left: 20px; opacity: 0; color: #fff; }

    /* State: CHECKED (Aktif) */
    .toggle-checkbox:checked + .toggle-label {
        background: linear-gradient(135deg, #ffc107, #ff9800); 
        border-color: #ffc107;
        box-shadow: 0 0 20px rgba(255, 193, 7, 0.6);
    }

    .toggle-checkbox:checked + .toggle-label .toggle-ball {
        transform: translateX(150px); 
        color: #ff9800; 
        background-color: #fff;
    }

    .toggle-checkbox:checked + .toggle-label .toggle-text-off { opacity: 0; transform: translateX(-10px); }
    .toggle-checkbox:checked + .toggle-label .toggle-text-on { opacity: 1; transform: translateX(0); }
    
    .toggle-checkbox:checked + .toggle-label .toggle-ball i {
        animation: pulse-magic 1.5s infinite;
    }
    
    @keyframes pulse-magic {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); text-shadow: 0 0 5px #ff9800; }
        100% { transform: scale(1); }
    }
</style>

<div class="container-fluid">
<?php
// =============================================
// TAMPILAN 1: FORM FILTER JIKA MAPEL/KELAS BELUM DIPILIH
// =============================================
if ($id_mapel == 0 || $id_kelas == 0) :

    $total_mapel_ditugaskan = 0;
    $total_kelas_diampu = 0;
    $ada_penugasan_dasar = false;

    if ($id_tahun_ajaran_aktif > 0) {
        $stmt_dasar = mysqli_prepare($koneksi, "
            SELECT COUNT(DISTINCT id_mapel) as total_mapel, COUNT(DISTINCT id_kelas) as total_kelas
            FROM guru_mengajar WHERE id_guru = ? AND id_tahun_ajaran = ?
        ");
        mysqli_stmt_bind_param($stmt_dasar, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
        mysqli_stmt_execute($stmt_dasar);
        $result_dasar = mysqli_stmt_get_result($stmt_dasar);
        if ($data_dasar = mysqli_fetch_assoc($result_dasar)) {
            $total_mapel_ditugaskan = $data_dasar['total_mapel'];
            $total_kelas_diampu = $data_dasar['total_kelas'];
        }
        $ada_penugasan_dasar = ($total_mapel_ditugaskan > 0);
        mysqli_stmt_close($stmt_dasar);

        if ($ada_penugasan_dasar) {
            $query_dropdown = "
                SELECT mp.id_mapel, mp.nama_mapel, k.id_kelas, k.nama_kelas
                FROM guru_mengajar gm
                JOIN mata_pelajaran mp ON gm.id_mapel = mp.id_mapel
                JOIN kelas k ON gm.id_kelas = k.id_kelas
                WHERE gm.id_guru = ? AND gm.id_tahun_ajaran = ?
                ORDER BY mp.nama_mapel, k.nama_kelas
            ";
            $stmt_dropdown = mysqli_prepare($koneksi, $query_dropdown);
            mysqli_stmt_bind_param($stmt_dropdown, "ii", $id_guru_login, $id_tahun_ajaran_aktif);
            mysqli_stmt_execute($stmt_dropdown);
            $result_dropdown = mysqli_stmt_get_result($stmt_dropdown);
            while ($row = mysqli_fetch_assoc($result_dropdown)) {
                $penugasan_by_mapel[$row['id_mapel']]['nama_mapel'] = $row['nama_mapel'];
                $penugasan_by_mapel[$row['id_mapel']]['kelas'][] = [
                    'id_kelas' => $row['id_kelas'],
                    'nama_kelas' => $row['nama_kelas']
                ];
            }
            mysqli_stmt_close($stmt_dropdown);
        }
    }

    $q_total_penilaian = mysqli_query($koneksi, "SELECT COUNT(id_penilaian) as total FROM penilaian WHERE id_guru = $id_guru_login");
    $total_penilaian_dibuat = mysqli_fetch_assoc($q_total_penilaian)['total'];
?>
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Bank Nilai Akademik</h1>
        <p class="lead mb-0 opacity-75">Kelola semua penilaian dan input nilai siswa per kelas.</p>
    </div>

    <!-- Statistik Card -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-start border-primary border-4">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-journal-bookmark-fill fs-1 text-primary me-3"></i>
                    <div>
                        <div class="text-muted">Total Mapel Ditugaskan</div>
                        <div class="fs-4 fw-bold"><?php echo $total_mapel_ditugaskan; ?> Mapel</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-start border-success border-4">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-door-open-fill fs-1 text-success me-3"></i>
                    <div>
                        <div class="text-muted">Total Kelas Diampu</div>
                        <div class="fs-4 fw-bold"><?php echo $total_kelas_diampu; ?> Kelas</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-start border-info border-4">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-card-checklist fs-1 text-info me-3"></i>
                    <div>
                        <div class="text-muted">Total Penilaian Dibuat</div>
                        <div class="fs-4 fw-bold"><?php echo $total_penilaian_dibuat; ?> Penilaian</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm filter-card">
        <div class="card-body p-4 p-md-5">
             <div class="text-center mb-4">
                <h3 class="card-title"><i class="bi bi-filter-circle-fill text-primary me-2"></i>Pilih Mata Pelajaran & Kelas</h3>
                <p class="text-muted">Untuk melanjutkan, silakan pilih mata pelajaran dan kelas yang Anda ampu.</p>
            </div>
            
            <?php
            $cek_tp_q = mysqli_query($koneksi, "SELECT 1 FROM tujuan_pembelajaran WHERE id_guru_pembuat = $id_guru_login AND id_tahun_ajaran = $id_tahun_ajaran_aktif LIMIT 1");
            $ada_tp_dibuat = mysqli_num_rows($cek_tp_q) > 0;

            if ($ada_penugasan_dasar && !$ada_tp_dibuat) {
            ?>
                <div class="alert alert-info mt-4 text-center">
                    <h4 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Langkah Selanjutnya!</h4>
                    <p>Anda sudah terdaftar mengajar, namun sistem belum menemukan <strong>Tujuan Pembelajaran (TP)</strong> yang Anda buat untuk tahun ajaran ini.</p>
                    <hr>
                    <p class="mb-0">Silakan buat TP terlebih dahulu di menu <strong>"Kelola TP Saya"</strong> agar dapat melanjutkan ke tahap penilaian.</p>
                    <a href="tp_guru_tampil.php" class="btn btn-primary mt-3"><i class="bi bi-card-checklist me-2"></i> Buka Menu Kelola TP Saya</a>
                </div>
            <?php
            } elseif (!empty($penugasan_by_mapel)) {
            ?>
                 <form action='penilaian_tampil.php' method='GET' class="mt-4">
                    <div class='row justify-content-center'>
                        <div class='col-md-5 mb-3'>
                            <label for="id_mapel" class="form-label fw-bold">Mata Pelajaran</label>
                            <select class='form-select form-select-lg' id='id_mapel' name='id_mapel' required>
                                <option value='' disabled selected>-- Pilih Mata Pelajaran --</option>
                                <?php
                                foreach ($penugasan_by_mapel as $id_m => $data_mapel) {
                                    echo "<option value='{$id_m}'>" . htmlspecialchars($data_mapel['nama_mapel']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class='col-md-5 mb-3'>
                            <label for="id_kelas" class="form-label fw-bold">Kelas</label>
                            <select class='form-select form-select-lg' id='id_kelas' name='id_kelas' required disabled>
                                <option value='' disabled selected>-- Pilih Kelas --</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type='submit' class='btn btn-primary btn-lg mt-3' id="tombolTampilkan" disabled><i class="bi bi-arrow-right-circle-fill me-2"></i>Tampilkan Penilaian</button>
                    </div>
                </form>
            <?php
            } else {
            ?>
                <div class="alert alert-warning mt-4 text-center">
                    <strong>Penugasan Kosong!</strong><br>
                    Anda belum ditugaskan untuk mengajar di kelas manapun pada tahun ajaran aktif ini. Silakan hubungi Administrator.
                </div>
            <?php
            }
            ?>
        </div>
    </div>
<?php
// =============================================
// TAMPILAN 2: DAFTAR PENILAIAN SETELAH MAPEL/KELAS DIPILIH
// =============================================
else:
    // Ambil data kelas dan mapel
    $q_kelas = mysqli_query($koneksi, "SELECT nama_kelas FROM kelas WHERE id_kelas=$id_kelas");
    $d_kelas = mysqli_fetch_assoc($q_kelas);
    $q_mapel = mysqli_query($koneksi, "SELECT nama_mapel FROM mata_pelajaran WHERE id_mapel=$id_mapel");
    $d_mapel = mysqli_fetch_assoc($q_mapel);

    // Ambil KKM
    $q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
    $kkm_db = mysqli_fetch_assoc($q_kkm);
    $kkm = $kkm_db ? (int)$kkm_db['nilai_pengaturan'] : 75;
    
    // =========================================================================
    // FUNGSI HITUNG NILAI & DESKRIPSI RAPOR (DIPERBARUI SESUAI REGULASI 2025)
    // =========================================================================
    function hitungDataRaporSiswa($koneksi, $id_siswa, $id_kelas, $id_mapel, $kkm) {
        global $semester_aktif; 

        // 1. Ambil Nilai Sumatif Lingkup Materi (TP)
        $stmt_sumatif_tp = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian, GROUP_CONCAT(tp.deskripsi_tp SEPARATOR '|||') as deskripsi_tps
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian 
            JOIN penilaian_tp ptp ON p.id_penilaian = ptp.id_penilaian
            JOIN tujuan_pembelajaran tp ON ptp.id_tp = tp.id_tp 
            WHERE p.subjenis_penilaian = 'Sumatif TP' 
            AND pdn.id_siswa = ? AND p.id_mapel = ? AND p.id_kelas = ? AND p.semester = ? 
            GROUP BY p.id_penilaian, pdn.nilai, p.bobot_penilaian
        ");
        
        // 2. Ambil Nilai Sumatif Akhir (Non-TP, langsung nilai akhir)
        $stmt_sumatif_akhir = mysqli_prepare($koneksi, "
            SELECT p.nama_penilaian, p.subjenis_penilaian, pdn.nilai, p.bobot_penilaian 
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian 
            WHERE p.subjenis_penilaian IN ('Sumatif Akhir Semester', 'Sumatif Akhir Tahun')
            AND p.jenis_penilaian = 'Sumatif' 
            AND pdn.id_siswa = ? AND p.id_mapel = ? AND p.id_kelas = ? AND p.semester = ?
        ");
        
        $skor_per_tp = []; 
        $total_nilai_x_bobot = 0; 
        $total_bobot = 0;

        // Eksekusi Query Sumatif TP
        mysqli_stmt_bind_param($stmt_sumatif_tp, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_tp);
        $result_sumatif_tp = mysqli_stmt_get_result($stmt_sumatif_tp);
        
        while ($d_nilai = mysqli_fetch_assoc($result_sumatif_tp)) {
            // Pecah TP (jika satu penilaian mencakup banyak TP)
            $tps_individu = explode('|||', $d_nilai['deskripsi_tps']);
            foreach($tps_individu as $desc_tp) {
                if (!isset($skor_per_tp[$desc_tp])) { $skor_per_tp[$desc_tp] = []; }
                $skor_per_tp[$desc_tp][] = $d_nilai['nilai'];
            }
            // Hitung rata-rata tertimbang
            $total_nilai_x_bobot += $d_nilai['nilai'] * $d_nilai['bobot_penilaian'];
            $total_bobot += $d_nilai['bobot_penilaian'];
        }

        // Eksekusi Query Sumatif Akhir
        mysqli_stmt_bind_param($stmt_sumatif_akhir, "iiii", $id_siswa, $id_mapel, $id_kelas, $semester_aktif);
        mysqli_stmt_execute($stmt_sumatif_akhir);
        $result_sumatif_akhir = mysqli_stmt_get_result($stmt_sumatif_akhir);
        
        while ($d_nilai_akhir = mysqli_fetch_assoc($result_sumatif_akhir)) {
            $total_nilai_x_bobot += $d_nilai_akhir['nilai'] * $d_nilai_akhir['bobot_penilaian'];
            $total_bobot += $d_nilai_akhir['bobot_penilaian'];
        }

        // Hitung Nilai Akhir
        $nilai_akhir = ($total_bobot > 0) ? round($total_nilai_x_bobot / $total_bobot) : null;
        
        // --- LOGIKA BARU DESKRIPSI (PANDUAN 2025) ---
        $deskripsi_final = '';
        if ($nilai_akhir !== null && !empty($skor_per_tp)) {
            // 1. Hitung Rata-rata per TP dan Bersihkan Kalimat
            $rekap_tp = [];
            foreach ($skor_per_tp as $deskripsi => $skor_array) {
                $avg = array_sum($skor_array) / count($skor_array);
                // Bersihkan deskripsi dari kata kerja operasional yang berulang
                $desc_clean = lcfirst(trim(str_replace(
                    ['Peserta didik dapat', 'Peserta didik mampu', 'peserta didik mampu', 'mampu', 'memahami', 'menguasai', 'menjelaskan', 'menganalisis'], 
                    '', 
                    $deskripsi
                )));
                $rekap_tp[$desc_clean] = $avg;
            }

            // 2. Urutkan Nilai TP dari Tertinggi ke Terendah
            arsort($rekap_tp); 

            // 3. Ambil TP Tertinggi (Maksimal 2 TP teratas untuk deskripsi positif)
            $top_tp = array_slice($rekap_tp, 0, 2, true); 
            $kalimat_positif = [];
            foreach($top_tp as $tp => $val) {
                // Masukkan ke deskripsi positif jika nilainya Bagus (>= KKM)
                if($val >= $kkm) {
                    $kalimat_positif[] = $tp;
                }
            }

            // 4. Ambil TP Terendah (Maksimal 2 TP terbawah untuk deskripsi intervensi)
            // Ambil 2 terakhir dari array yang sudah diurutkan (terendah)
            $bottom_tp = array_slice($rekap_tp, -2, 2, true);
            // Balik urutan biar yang paling jelek disebut terakhir atau terpisah, lalu diurutkan lagi
            asort($bottom_tp); 
            
            $kalimat_negatif = [];
            foreach($bottom_tp as $tp => $val) {
                // Masukkan ke deskripsi perlu bimbingan jika nilainya di bawah KKM
                // ATAU jika nilainya pas-pasan (opsional, di sini kita set < KKM)
                if($val < $kkm) {
                    $kalimat_negatif[] = $tp;
                }
            }

            // 5. Susun Deskripsi Final
            $deskripsi_draf = "";
            
            // Kalimat Kekuatan
            if (!empty($kalimat_positif)) {
                $deskripsi_draf .= "Menunjukkan penguasaan yang sangat baik dalam " . implode(', ', $kalimat_positif) . ". ";
            }
            
            // Kalimat Kelemahan/Intervensi
            if (!empty($kalimat_negatif)) {
                $deskripsi_draf .= "Perlu pendampingan lebih lanjut dalam " . implode(', ', $kalimat_negatif) . ".";
            }

            // Fallback jika data kosong/error atau semua nilai rata-rata (jarang terjadi)
            if (empty(trim($deskripsi_draf))) {
                if ($nilai_akhir >= $kkm) {
                    $deskripsi_final = 'Capaian kompetensi sudah baik pada seluruh materi.';
                } else {
                    $deskripsi_final = 'Perlu peningkatan pada beberapa tujuan pembelajaran.';
                }
            } else {
                $deskripsi_final = ucfirst(trim($deskripsi_draf));
            }

        } elseif ($nilai_akhir !== null) {
            $deskripsi_final = 'Capaian kompetensi secara umum sudah menunjukkan ketuntasan yang baik.';
        }
        
        return ['nilai_akhir' => $nilai_akhir, 'deskripsi' => $deskripsi_final];
    }
    
    // Ambil daftar penilaian
    $query = "SELECT p.*, GROUP_CONCAT(tp.deskripsi_tp SEPARATOR ', ') AS deskripsi_tp FROM penilaian p
              LEFT JOIN penilaian_tp pt ON p.id_penilaian = pt.id_penilaian LEFT JOIN tujuan_pembelajaran tp ON pt.id_tp = tp.id_tp
              WHERE p.id_kelas = ? AND p.id_mapel = ? AND p.id_guru = ? GROUP BY p.id_penilaian
              ORDER BY p.jenis_penilaian, p.tanggal_penilaian DESC, p.id_penilaian DESC";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "iii", $id_kelas, $id_mapel, $id_guru_login);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $penilaian_dikelompokkan = ['Sumatif' => [], 'Formatif' => []];
    while ($data = mysqli_fetch_assoc($result)) {
        $penilaian_dikelompokkan[$data['jenis_penilaian']][] = $data;
    }
?>
    <!-- ============================================= -->
    <!-- === BAGIAN HEADER HALAMAN === -->
    <!-- ============================================= -->
    <div class="page-header text-white mb-4 shadow">
        <div class="d-sm-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="mb-1"><?php echo htmlspecialchars($d_mapel['nama_mapel']); ?></h1>
                <p class="lead mb-0 opacity-75">Bank Nilai - Kelas <?php echo htmlspecialchars($d_kelas['nama_kelas']); ?></p>
            </div>
            <div class="d-flex mt-3 mt-sm-0">
                <a href="penilaian_tampil.php" class="btn btn-outline-light me-2"><i class="bi bi-arrow-left-right me-2"></i>Ganti</a>
                <a href="penilaian_tambah.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-light"><i class="bi bi-plus-circle-fill me-2"></i>Buat Penilaian</a>
            </div>
        </div>
        <hr>
        <div class="mt-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div class="d-flex gap-2">
                <a href="penilaian_excel_template_batch.php?id_kelas=<?php echo $id_kelas; ?>&id_mapel=<?php echo $id_mapel; ?>" class="btn btn-light">
                    <i class="bi bi-download me-2"></i>Download Template Kelas
                </a>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#modalImportBatch">
                    <i class="bi bi-upload me-2"></i>Import Nilai Kelas
                </button>
            </div>
            
            <!-- Tombol Toggle KATROL Modern Eye Catching + Info Button -->
            <div class="d-flex align-items-center gap-2">
                <div class="toggle-wrapper">
                    <input type="checkbox" id="toggleKatrol" class="toggle-checkbox">
                    <label for="toggleKatrol" class="toggle-label">
                        <div class="toggle-ball"><i class="bi bi-magic"></i></div>
                        <span class="toggle-text toggle-text-off">KATROL OFF</span>
                        <span class="toggle-text toggle-text-on">KATROL ON</span>
                    </label>
                </div>
                <!-- Tombol Info Katrol -->
                <button type="button" class="btn btn-outline-light rounded-circle" id="btnInfoKatrol" title="Info Fitur Katrol" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(5px); background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.4);">
                    <i class="bi bi-question-lg fw-bold"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Batch Import (Sama) -->
    <div class="modal fade" id="modalImportBatch" tabindex="-1" aria-labelledby="modalImportBatchLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="modalImportBatchLabel">Import Nilai (Batch)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form action="penilaian_aksi.php?aksi=import_nilai_batch" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
                    <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">
                    <p>Pilih file template Excel (yang diunduh dari tombol "Download Template Kelas") yang sudah diisi nilai siswa.</p>
                    <div class="mb-3">
                        <label for="file_excel" class="form-label">File Excel (.xlsx)</label>
                        <input class="form-control" type="file" name="file_excel" id="file_excel" accept=".xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Unggah dan Proses</button>
                </div>
              </form>
            </div>
        </div>
    </div>

    <?php foreach ($penilaian_dikelompokkan as $jenis => $daftar_penilaian): ?>
        <h3 class="penilaian-group-header">
            <i class="bi <?php echo $jenis == 'Sumatif' ? 'bi-bar-chart-line-fill' : 'bi-check-circle-fill'; ?> me-2"></i>
            Daftar Penilaian <?php echo $jenis; ?>
        </h3>
        
        <?php if (empty($daftar_penilaian)): ?>
            <div class="card card-body text-center"><p class="text-muted mb-0">Belum ada penilaian jenis <strong><?php echo $jenis; ?></strong> yang dibuat.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th>Nama Penilaian</th>
                            <th style="width: 35%;">Tujuan Pembelajaran (TP)</th>
                            <th class="text-center" style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($daftar_penilaian as $data): ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td>
                                <strong class="d-block"><?php echo htmlspecialchars($data['nama_penilaian']); ?></strong>
                                <?php if($data['jenis_penilaian'] == 'Sumatif' && !empty($data['subjenis_penilaian'])): ?>
                                    <small class="text-muted fst-italic">(<?php echo htmlspecialchars($data['subjenis_penilaian']); ?> - Bobot: <?php echo $data['bobot_penilaian']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($data['deskripsi_tp'])) {
                                    echo htmlspecialchars($data['deskripsi_tp']);
                                } else {
                                    echo '<i class="text-muted">— Penilaian non-TP (e.g., Sumatif Akhir Semester) —</i>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="penilaian_input_nilai.php?id_penilaian=<?php echo $data['id_penilaian']; ?>" class="btn btn-success btn-sm" title="Input atau Lihat Nilai Siswa"><i class="bi bi-input-cursor-text me-1"></i> Input Nilai</a>
                                    <a href="penilaian_aksi.php?aksi=hapus_penilaian&id_penilaian=<?php echo $data['id_penilaian']; ?>" class="btn btn-outline-danger btn-sm btn-hapus" data-nama="<?php echo htmlspecialchars($data['nama_penilaian']); ?>" title="Hapus Penilaian Ini"><i class="bi bi-trash-fill"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    // --- Filter Siswa Berdasarkan Agama ---
    $nama_mapel_lower = strtolower($d_mapel['nama_mapel']);
    $agama_terdeteksi = null;
    $agama_list = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu']; 

    foreach ($agama_list as $agama) {
        if (strpos($nama_mapel_lower, strtolower($agama)) !== false) {
            $agama_terdeteksi = $agama; 
            break;
        }
    }

    $query_siswa_sql = "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif'";
    if ($agama_terdeteksi !== null) {
        $agama_filter_sql = mysqli_real_escape_string($koneksi, $agama_terdeteksi);
        $query_siswa_sql .= " AND agama = '$agama_filter_sql'";
    }
    $query_siswa_sql .= " ORDER BY nama_lengkap";
    $q_siswa_kelas = mysqli_query($koneksi, $query_siswa_sql);
    $daftar_siswa = mysqli_fetch_all($q_siswa_kelas, MYSQLI_ASSOC);

    // Ambil Data Nilai Sumatif
    $q_sumatif_kelas = mysqli_query($koneksi, "SELECT id_penilaian, nama_penilaian FROM penilaian WHERE id_kelas = $id_kelas AND id_mapel = $id_mapel AND jenis_penilaian = 'Sumatif' ORDER BY tanggal_penilaian, id_penilaian");
    $daftar_sumatif = mysqli_fetch_all($q_sumatif_kelas, MYSQLI_ASSOC);
    
    $nilai_semua_siswa = [];
    if (!empty($daftar_sumatif)) {
        $id_penilaian_list = array_column($daftar_sumatif, 'id_penilaian');
        $id_penilaian_str = implode(',', $id_penilaian_list);
        if(!empty($id_penilaian_str)) {
            $q_nilai = mysqli_query($koneksi, "SELECT id_siswa, id_penilaian, nilai FROM penilaian_detail_nilai WHERE id_penilaian IN ($id_penilaian_str)");
            while($row = mysqli_fetch_assoc($q_nilai)) {
                $nilai_semua_siswa[$row['id_siswa']][$row['id_penilaian']] = $row['nilai'];
            }
        }
    }

    // --- LOGIKA BARU: AMBIL DATA KATROL DARI DATABASE ---
    $nilai_katrol_siswa = [];
    $q_katrol = mysqli_query($koneksi, "
        SELECT r.id_siswa, rda.nilai_katrol
        FROM rapor r
        JOIN rapor_detail_akademik rda ON r.id_rapor = rda.id_rapor
        WHERE r.id_kelas = $id_kelas 
        AND rda.id_mapel = $id_mapel
        AND r.semester = $semester_aktif
    ");
    while($k = mysqli_fetch_assoc($q_katrol)){
        $nilai_katrol_siswa[$k['id_siswa']] = $k['nilai_katrol'];
    }
    ?>

    <h3 class="penilaian-group-header">
        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i>
        Ringkasan Nilai Akhir & Rapor (Satu Kelas)
    </h3>

    <?php if (empty($daftar_siswa) || empty($daftar_sumatif)): ?>
        <div class="card card-body text-center">
            <p class="text-muted mb-0">
                <?php if ($agama_terdeteksi !== null && empty($daftar_siswa)) : ?>
                    Tidak ada siswa dengan agama <strong><?php echo htmlspecialchars($agama_terdeteksi); ?></strong> yang ditemukan di kelas ini.
                <?php else : ?>
                    Data siswa atau penilaian sumatif belum cukup untuk menampilkan ringkasan nilai rapor.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" style="font-size: 0.9em;">
                        <thead class="table-light text-center">
                            <tr>
                                <th rowspan="2" class="align-middle">No</th>
                                <th rowspan="2" class="align-middle">Nama Siswa</th>
                                <th colspan="<?php echo count($daftar_sumatif); ?>">Nilai Sumatif</th>
                                <!-- Kolom Katrol (Hidden by default) -->
                                <th rowspan="2" class="align-middle kolom-katrol border-warning">KATROL</th>
                                <th rowspan="2" class="align-middle bg-success-subtle">Nilai Rapor</th>
                                <th rowspan="2" class="align-middle bg-success-subtle">Deskripsi Capaian Kompetensi (Rapor)</th>
                            </tr>
                            <tr>
                                <?php foreach ($daftar_sumatif as $sumatif) : ?>
                                    <th class="fw-normal" style="min-width: 100px;"><?php echo htmlspecialchars($sumatif['nama_penilaian']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($daftar_siswa as $siswa) : ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td style="min-width: 200px;"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                    <?php foreach ($daftar_sumatif as $sumatif) :
                                        $nilai_siswa = $nilai_semua_siswa[$siswa['id_siswa']][$sumatif['id_penilaian']] ?? '-';
                                        $text_color = ($nilai_siswa !== '-' && $nilai_siswa < $kkm) ? 'text-danger fw-bold' : '';
                                    ?>
                                        <td class="text-center <?php echo $text_color; ?>"><?php echo $nilai_siswa; ?></td>
                                    <?php endforeach; ?>
                                    
                                    <?php
                                    $data_rapor = hitungDataRaporSiswa($koneksi, $siswa['id_siswa'], $id_kelas, $id_mapel, $kkm);
                                    $nilai_murni = $data_rapor['nilai_akhir']; // Nilai hitungan sistem
                                    $deskripsi_rapor = $data_rapor['deskripsi'];
                                    
                                    // Ambil nilai katrol jika ada
                                    $nilai_katrol = $nilai_katrol_siswa[$siswa['id_siswa']] ?? '';
                                    
                                    // LOGIKA PENENTUAN NILAI FINAL
                                    // Jika katrol diisi (>0), gunakan katrol. Jika tidak, gunakan nilai murni
                                    $pakai_katrol = ($nilai_katrol !== '' && $nilai_katrol > 0);
                                    $nilai_final = $pakai_katrol ? $nilai_katrol : $nilai_murni;
                                    
                                    $badge_class = 'bg-secondary';
                                    $badge_extra_class = $pakai_katrol ? 'badge-katrol-aktif' : ''; // Style khusus jika katrol aktif

                                    if ($nilai_final !== null) {
                                        if ($nilai_final < $kkm) { $badge_class = 'bg-danger'; }
                                        else { $badge_class = 'bg-success'; }
                                    }
                                    ?>
                                    <!-- INPUTAN KATROL -->
                                    <td class="text-center kolom-katrol p-1">
                                        <input type="number" 
                                               class="input-katrol" 
                                               value="<?php echo $nilai_katrol; ?>" 
                                               min="0" max="100" 
                                               placeholder="-"
                                               data-idsiswa="<?php echo $siswa['id_siswa']; ?>"
                                               data-nilaimurni="<?php echo $nilai_murni ?? ''; ?>">
                                    </td>

                                    <td class="text-center fw-bold">
                                        <span class="badge <?php echo $badge_class; ?> fs-6 badge-nilai-final <?php echo $badge_extra_class; ?>" 
                                              id="badge-<?php echo $siswa['id_siswa']; ?>">
                                            <?php echo $nilai_final ?? 'N/A'; ?>
                                        </span>
                                    </td>
                                    <td class="deskripsi-rapor-cell">
                                        <?php echo htmlspecialchars($deskripsi_rapor); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapelSelect = document.getElementById('id_mapel');
    if (mapelSelect) {
        const kelasSelect = document.getElementById('id_kelas');
        const tombolTampilkan = document.getElementById('tombolTampilkan');
        
        // --- PERBAIKAN PENTING: Penanganan JSON lebih aman ---
        // 1. Pastikan variable PHP tidak null/kosong sebelum di-encode
        // 2. Gunakan operator OR untuk fallback ke object kosong {} jika PHP echo gagal
        const penugasan = <?php echo json_encode($penugasan_by_mapel, JSON_INVALID_UTF8_IGNORE) ?: '{}'; ?>;

        mapelSelect.addEventListener('change', function() {
            const idMapelTerpilih = this.value;
            kelasSelect.innerHTML = '<option value="" disabled selected>-- Pilih Kelas --</option>';
            kelasSelect.disabled = true;
            tombolTampilkan.disabled = true;

            // Pastikan ID valid dan Data tersedia di object penugasan
            if (idMapelTerpilih && penugasan && penugasan[idMapelTerpilih]) {
                const dataMapel = penugasan[idMapelTerpilih];
                
                if(dataMapel.kelas && Array.isArray(dataMapel.kelas)) {
                    dataMapel.kelas.forEach(function(kelas) {
                        const option = document.createElement('option');
                        option.value = kelas.id_kelas;
                        option.textContent = kelas.nama_kelas;
                        kelasSelect.appendChild(option);
                    });
                    
                    // Aktifkan dropdown kelas jika ada isinya
                    if (dataMapel.kelas.length > 0) {
                        kelasSelect.disabled = false;
                    }
                }
            }
        });

        kelasSelect.addEventListener('change', function() {
            tombolTampilkan.disabled = !this.value;
        });
    }

    // === INFO KATROL ===
    const btnInfoKatrol = document.getElementById('btnInfoKatrol');
    if (btnInfoKatrol) {
        btnInfoKatrol.addEventListener('click', function() {
            Swal.fire({
                title: '<strong>Panduan Fitur Katrol Nilai</strong>',
                icon: 'info',
                html: `
                    <div style="text-align: left; font-size: 0.95em;">
                        <p>Fitur ini berfungsi untuk memberikan <b>Nilai Kebijakan</b> yang berbeda dari hitungan sistem.</p>
                        <hr>
                        <ul style="list-style-type: none; padding-left: 0;">
                            <li class="mb-2">
                                <i class="bi bi-1-circle-fill text-primary me-2"></i> 
                                <b>Aktifkan Toggle</b> "KATROL ON" untuk memunculkan kolom inputan.
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-2-circle-fill text-primary me-2"></i> 
                                <b>Isi Angka</b> pada kolom Katrol jika ingin mengubah nilai rapor siswa tertentu.
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-3-circle-fill text-primary me-2"></i> 
                                <b>Kosongkan</b> jika ingin kembali menggunakan nilai asli hitungan sistem.
                            </li>
                        </ul>
                        <div class="alert alert-warning d-flex align-items-center mt-3 mb-0" role="alert">
                            <i class="bi bi-lightbulb-fill fs-4 me-3"></i>
                            <div>
                                Nilai tersimpan <b>Otomatis</b> saat Anda selesai mengetik atau pindah kolom.
                            </div>
                        </div>
                    </div>
                `,
                showCloseButton: true,
                focusConfirm: false,
                confirmButtonText: '<i class="bi bi-hand-thumbs-up-fill"></i> Paham!',
                confirmButtonAriaLabel: 'Thumbs up, great!',
            });
        });
    }

    // === LOGIKA FITUR KATROL ===
    const toggleKatrol = document.getElementById('toggleKatrol');
    const kolomKatrol = document.querySelectorAll('.kolom-katrol');
    
    // 1. Toggle Tampilan Kolom
    if(toggleKatrol) {
        toggleKatrol.addEventListener('change', function() {
            const isVisible = this.checked;
            kolomKatrol.forEach(el => {
                el.style.display = isVisible ? 'table-cell' : 'none';
            });
        });
    }

    // 2. Input Nilai Katrol (Auto Save & Update UI)
    const inputKatrols = document.querySelectorAll('.input-katrol');
    // Pastikan nilai KKM terdefinisi, default 75 jika error
    const kkm = <?php echo isset($kkm) ? $kkm : 75; ?>;

    if(inputKatrols.length > 0) {
        inputKatrols.forEach(input => {
            // Event saat mengetik/berubah
            input.addEventListener('input', function() {
                const idSiswa = this.dataset.idsiswa;
                const nilaiMurni = parseFloat(this.dataset.nilaimurni) || 0;
                const nilaiInput = this.value;
                const badge = document.getElementById('badge-' + idSiswa);
                
                if(!badge) return; // Guard clause jika elemen tidak ketemu

                let nilaiFinal;
                
                // Logic Update UI Badge
                if (nilaiInput !== '' && !isNaN(nilaiInput)) {
                    nilaiFinal = parseFloat(nilaiInput);
                    badge.classList.add('badge-katrol-aktif'); // Tambah border kuning
                } else {
                    nilaiFinal = nilaiMurni;
                    badge.classList.remove('badge-katrol-aktif'); // Hapus border kuning
                }

                // Update Angka
                badge.textContent = nilaiFinal;

                // Update Warna Badge (Merah/Hijau)
                badge.classList.remove('bg-danger', 'bg-success', 'bg-secondary');
                if(nilaiFinal < kkm) {
                    badge.classList.add('bg-danger');
                } else {
                    badge.classList.add('bg-success');
                }
            });

            // Event Simpan ke Database (saat selesai mengetik / blur)
            input.addEventListener('change', function() {
                const idSiswa = this.dataset.idsiswa;
                const val = this.value;
                
                // Kirim AJAX
                const formData = new FormData();
                formData.append('id_siswa', idSiswa);
                formData.append('id_mapel', '<?php echo $id_mapel; ?>');
                formData.append('id_kelas', '<?php echo $id_kelas; ?>');
                formData.append('nilai_katrol', val);

                fetch('simpan_nilai_katrol.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.status !== 'success') {
                        Swal.fire({icon: 'error', title: 'Gagal Menyimpan', text: data.message || 'Terjadi kesalahan sistem'});
                    } else {
                        // Opsional: Tampilkan notifikasi kecil/toast bahwa tersimpan
                        // console.log('Nilai katrol tersimpan');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({icon: 'error', title: 'Error Koneksi', text: 'Gagal menghubungi server'});
                });
            });
        });
    }

    // (Logika JS untuk tombol hapus - tidak diubah)
    const tombolHapus = document.querySelectorAll('.btn-hapus');
    tombolHapus.forEach(function(tombol) {
        tombol.addEventListener('click', function(event) {
            event.preventDefault();
            const href = this.getAttribute('href');
            const namaPenilaian = this.getAttribute('data-nama');
            Swal.fire({
                title: 'Anda Yakin?',
                html: `Penilaian "<b>${namaPenilaian}</b>" dan semua nilai siswa di dalamnya akan dihapus secara permanen.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus Saja!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });
    });
});
</script>

<?php
// (Logika JS untuk menampilkan pesan session - tidak diubah)
if (isset($_SESSION['pesan'])) {
    $pesan_json = $_SESSION['pesan'];
    if (strpos($pesan_json, '{') === 0) {
        $pesan_data = json_decode($pesan_json, true);
        echo "<script>Swal.fire({icon: '".($pesan_data['icon'] ?? 'info')."', title: '".($pesan_data['title'] ?? 'Pemberitahuan')."', text: '".($pesan_data['text'] ?? '')."'});</script>";
    } else {
        echo "<script>Swal.fire('Berhasil!','" . addslashes($pesan_json) . "','success');</script>";
    }
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>