<?php
include 'header.php';
include 'koneksi.php';

// ==========================================================
// 1. VALIDASI AKSES & INISIALISASI
// ==========================================================
// Pastikan hanya Guru (Wali Kelas) atau Admin yang bisa akses
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    include 'footer.php';
    exit;
}

$id_guru_akses = $_SESSION['id_guru'];

// Ambil Tahun Ajaran Aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran, tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$ta_aktif = mysqli_fetch_assoc($q_ta);
$id_tahun_ajaran = $ta_aktif['id_tahun_ajaran'] ?? 0;
$tahun_ajaran_label = $ta_aktif['tahun_ajaran'] ?? '-';

// Ambil Semester Aktif dari Pengaturan
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'] ?? 1;

// Ambil KKM dari Pengaturan
$q_kkm = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'kkm' LIMIT 1");
$kkm = mysqli_fetch_assoc($q_kkm)['nilai_pengaturan'] ?? 75;

// ==========================================================
// 2. CEK STATUS WALI KELAS
// ==========================================================
// Mengambil data kelas dimana user login adalah wali kelas di tahun ajaran aktif
// [Sesuai DB]: Tabel kelas memiliki kolom id_kelas, nama_kelas, fase, id_wali_kelas, id_tahun_ajaran
$stmt_kelas = mysqli_prepare($koneksi, "
    SELECT id_kelas, nama_kelas, fase 
    FROM kelas 
    WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?
");
mysqli_stmt_bind_param($stmt_kelas, "ii", $id_guru_akses, $id_tahun_ajaran);
mysqli_stmt_execute($stmt_kelas);
$kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_kelas));

if (!$kelas) {
    echo "<div class='container mt-5'><div class='alert alert-danger shadow-sm border-0'>
            <h4 class='alert-heading'><i class='bi bi-exclamation-triangle-fill me-2'></i>Akses Terbatas</h4>
            <p>Anda tidak terdaftar sebagai Wali Kelas di Tahun Ajaran <strong>$tahun_ajaran_label</strong> saat ini.</p>
            <hr>
            <p class='mb-0'>Silakan hubungi administrator jika ini adalah kesalahan.</p>
          </div></div>";
    include 'footer.php';
    exit;
}

$id_kelas = $kelas['id_kelas'];
$nama_kelas = $kelas['nama_kelas'];
$fase = $kelas['fase'];

// Hitung Total Siswa Aktif di Kelas Ini untuk pembagi persentase
$q_siswa_count = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif'");
$total_siswa = mysqli_fetch_assoc($q_siswa_count)['total'];

// ==========================================================
// 3. LOGIKA UTAMA: MONITORING PROGRESS GURU MAPEL
// ==========================================================
$monitoring_data = [];

// Langkah A: Ambil Daftar Mapel yang DIAJARKAN di kelas ini (berdasarkan tabel guru_mengajar)
// Ini lebih akurat daripada mengambil semua mapel, karena kita tahu siapa gurunya.
$query_mapel_ajar = "
    SELECT gm.id_mapel, gm.id_guru, m.nama_mapel, m.kode_mapel, g.nama_guru
    FROM guru_mengajar gm
    JOIN mata_pelajaran m ON gm.id_mapel = m.id_mapel
    JOIN guru g ON gm.id_guru = g.id_guru
    WHERE gm.id_kelas = $id_kelas AND gm.id_tahun_ajaran = $id_tahun_ajaran
    ORDER BY m.urutan ASC
";
$result_mapel = mysqli_query($koneksi, $query_mapel_ajar);

if (mysqli_num_rows($result_mapel) > 0) {
    while ($row = mysqli_fetch_assoc($result_mapel)) {
        $id_mapel = $row['id_mapel'];
        
        // Langkah B: Hitung Statistik Penilaian untuk Mapel ini di Kelas ini & Semester ini
        // Fokus pada 'Sumatif' karena ini yang masuk Rapor
        
        // 1. Berapa banyak Judul Penilaian (Asesmen) yang sudah dibuat guru?
        $q_asesmen = mysqli_query($koneksi, "
            SELECT COUNT(*) as jumlah, SUM(bobot_penilaian) as total_bobot 
            FROM penilaian 
            WHERE id_kelas = $id_kelas 
              AND id_mapel = $id_mapel 
              AND semester = $semester_aktif 
              AND jenis_penilaian = 'Sumatif'
        ");
        $data_asesmen = mysqli_fetch_assoc($q_asesmen);
        $jml_asesmen = $data_asesmen['jumlah'];
        $total_bobot_mapel = $data_asesmen['total_bobot'] ?? 0;

        // 2. Berapa banyak Nilai Siswa yang sudah diinput?
        // (Misal: 2 asesmen x 30 siswa = target 60 nilai. Jika baru 30, berarti 50%)
        $target_nilai = $jml_asesmen * $total_siswa;
        
        $q_nilai_masuk = mysqli_query($koneksi, "
            SELECT COUNT(*) as jumlah 
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
            WHERE p.id_kelas = $id_kelas 
              AND p.id_mapel = $id_mapel 
              AND p.semester = $semester_aktif 
              AND p.jenis_penilaian = 'Sumatif'
        ");
        $jml_nilai_masuk = mysqli_fetch_assoc($q_nilai_masuk)['jumlah'];

        // 3. Hitung Persentase Kelengkapan
        $persentase = 0;
        $status_badge = '';
        $status_text = '';

        if ($target_nilai > 0) {
            $persentase = round(($jml_nilai_masuk / $target_nilai) * 100);
        }

        // Tentukan Status Visual
        if ($jml_asesmen == 0) {
            $status_badge = 'bg-danger';
            $status_text = 'Belum Ada Asesmen';
            $persentase = 0;
        } elseif ($persentase >= 100) {
            $status_badge = 'bg-success';
            $status_text = 'Selesai (100%)';
        } elseif ($persentase >= 50) {
            $status_badge = 'bg-info text-dark';
            $status_text = 'Sedang Berjalan';
        } else {
            $status_badge = 'bg-warning text-dark';
            $status_text = 'Baru Dimulai';
        }

        $monitoring_data[] = [
            'id_mapel' => $id_mapel,
            'nama_mapel' => $row['nama_mapel'],
            'nama_guru' => $row['nama_guru'],
            'jml_asesmen' => $jml_asesmen,
            'jml_nilai' => $jml_nilai_masuk,
            'target_nilai' => $target_nilai,
            'persentase' => $persentase,
            'badge' => $status_badge,
            'text' => $status_text,
            'total_bobot' => $total_bobot_mapel
        ];
    }
}

// ==========================================================
// 4. FUNGSI UNTUK AJAX (MENGHITUNG NILAI AKHIR SISWA)
// ==========================================================
// Fungsi ini dipanggil via Fetch API untuk mengisi Modal Detail
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'get_nilai_mapel') {
    // Bersihkan buffer agar JSON valid
    ob_clean(); 
    
    $req_id_mapel = $_POST['id_mapel'];
    
    // Ambil semua siswa di kelas ini
    $q_siswa = mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap, nisn FROM siswa WHERE id_kelas = $id_kelas AND status_siswa = 'Aktif' ORDER BY nama_lengkap ASC");
    
    $result_data = [];
    
    while ($siswa = mysqli_fetch_assoc($q_siswa)) {
        $id_siswa = $siswa['id_siswa'];
        
        // Ambil SEMUA nilai sumatif siswa ini untuk mapel ini
        // Rumus: Rata-rata Tertimbang = SUM(Nilai * Bobot) / SUM(Bobot)
        $q_nilai = mysqli_query($koneksi, "
            SELECT pdn.nilai, p.bobot_penilaian
            FROM penilaian_detail_nilai pdn
            JOIN penilaian p ON pdn.id_penilaian = p.id_penilaian
            WHERE pdn.id_siswa = $id_siswa
              AND p.id_mapel = $req_id_mapel
              AND p.id_kelas = $id_kelas
              AND p.semester = $semester_aktif
              AND p.jenis_penilaian = 'Sumatif'
        ");
        
        $total_skor = 0;
        $total_bobot = 0;
        $jumlah_data = 0;
        
        while ($n = mysqli_fetch_assoc($q_nilai)) {
            $total_skor += ($n['nilai'] * $n['bobot_penilaian']);
            $total_bobot += $n['bobot_penilaian'];
            $jumlah_data++;
        }
        
        $nilai_akhir = 0;
        $status_nilai = 'Kosong'; // Kosong, Kurang, Tuntas
        
        if ($total_bobot > 0) {
            $nilai_akhir = round($total_skor / $total_bobot);
            if ($nilai_akhir < $kkm) {
                $status_nilai = 'Kurang';
            } else {
                $status_nilai = 'Tuntas';
            }
        } else {
            // Jika belum ada nilai sama sekali
            $nilai_akhir = '-';
        }
        
        $result_data[] = [
            'nama' => $siswa['nama_lengkap'],
            'nisn' => $siswa['nisn'],
            'nilai_akhir' => $nilai_akhir,
            'jml_asesmen_diikuti' => $jumlah_data,
            'status' => $status_nilai
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result_data);
    exit;
}
?>

<style>
    .card-monitor {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-monitor:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .progress-custom {
        height: 12px;
        border-radius: 6px;
        background-color: #e9ecef;
        overflow: hidden;
    }
    .hero-header {
        background: linear-gradient(120deg, #43cea2 0%, #185a9d 100%);
        color: white;
        padding: 2.5rem 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    .hero-header::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: url('assets/img/pattern-white.png'); /* Opsional pattern */
        opacity: 0.1;
    }
    .avatar-circle {
        width: 40px; height: 40px;
        background-color: #e9ecef;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; color: #555;
    }
    .table-vcenter td { vertical-align: middle; }
</style>

<div class="container-fluid py-4">
    
    <!-- HEADER SECTION -->
    <div class="hero-header shadow">
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard Monitoring Wali Kelas</h2>
                <p class="mb-0 opacity-75">Pantau progres penilaian guru mata pelajaran dan prediksi nilai rapor siswa.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="card bg-white text-dark border-0 shadow-sm" style="border-radius: 10px;">
                    <div class="card-body p-3 text-start">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Kelas Perwalian</small>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0 fw-bold text-primary"><?php echo htmlspecialchars($nama_kelas); ?></h3>
                            <span class="badge bg-light text-dark border">Fase <?php echo $fase; ?></span>
                        </div>
                        <div class="mt-2 pt-2 border-top d-flex justify-content-between">
                            <small>Siswa: <strong><?php echo $total_siswa; ?></strong></small>
                            <small>KKM: <strong><?php echo $kkm; ?></strong></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-monitor h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-2">Total Mapel Terdaftar</h6>
                    <div class="d-flex align-items-center">
                        <div class="fs-2 fw-bold text-dark me-2"><?php echo count($monitoring_data); ?></div>
                        <i class="bi bi-journal-bookmark fs-1 text-primary ms-auto opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-monitor h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-2">Penilaian Selesai</h6>
                    <?php 
                        $selesai = array_filter($monitoring_data, fn($i) => $i['persentase'] == 100); 
                    ?>
                    <div class="d-flex align-items-center">
                        <div class="fs-2 fw-bold text-success me-2"><?php echo count($selesai); ?></div>
                        <small class="text-muted">Mapel</small>
                        <i class="bi bi-check-circle-fill fs-1 text-success ms-auto opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-monitor h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small mb-2">Belum Ada Nilai</h6>
                    <?php 
                        $kosong = array_filter($monitoring_data, fn($i) => $i['jml_asesmen'] == 0); 
                    ?>
                    <div class="d-flex align-items-center">
                        <div class="fs-2 fw-bold text-danger me-2"><?php echo count($kosong); ?></div>
                        <small class="text-muted">Mapel</small>
                        <i class="bi bi-exclamation-triangle-fill fs-1 text-danger ms-auto opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-monitor h-100 bg-light border-dashed">
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                    <a href="walikelas_cetak_rapor.php" class="btn btn-outline-primary w-100 stretched-link">
                        <i class="bi bi-printer-fill me-2"></i>Ke Menu Cetak
                    </a>
                    <small class="mt-2 text-muted">Lanjut jika semua nilai lengkap</small>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-task me-2 text-primary"></i>Status Penilaian per Mata Pelajaran</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 50px;">#</th>
                            <th style="width: 25%;">Mata Pelajaran</th>
                            <th style="width: 20%;">Guru Pengampu</th>
                            <th style="width: 30%;">Progres Input Nilai</th>
                            <th class="text-center" style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monitoring_data)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <img src="assets/img/empty.svg" alt="Empty" style="width: 100px; opacity: 0.5;">
                                    <p class="text-muted mt-3">Belum ada data mata pelajaran yang terhubung dengan kelas ini.</p>
                                    <small>Pastikan data di menu "Plotting Guru Mapel" sudah benar.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($monitoring_data as $mapel): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted"><?php echo $no++; ?></td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($mapel['nama_mapel']); ?></span>
                                    <small class="text-muted">Total Asesmen Sumatif: <strong><?php echo $mapel['jml_asesmen']; ?></strong></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2 text-white bg-secondary" style="font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($mapel['nama_guru'], 0, 2)); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($mapel['nama_guru']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="badge <?php echo $mapel['badge']; ?>"><?php echo $mapel['text']; ?></span>
                                        <small class="fw-bold"><?php echo $mapel['persentase']; ?>%</small>
                                    </div>
                                    <div class="progress progress-custom">
                                        <div class="progress-bar <?php echo ($mapel['persentase'] == 100) ? 'bg-success' : 'bg-primary'; ?> progress-bar-striped progress-bar-animated" 
                                             role="progressbar" 
                                             style="width: <?php echo $mapel['persentase']; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                        Data masuk: <?php echo $mapel['jml_nilai']; ?> / <?php echo $mapel['target_nilai']; ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <?php if ($mapel['jml_asesmen'] > 0): ?>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailNilaiModal"
                                                data-id="<?php echo $mapel['id_mapel']; ?>"
                                                data-nama="<?php echo htmlspecialchars($mapel['nama_mapel']); ?>"
                                                data-guru="<?php echo htmlspecialchars($mapel['nama_guru']); ?>">
                                            <i class="bi bi-eye me-1"></i> Lihat Nilai
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light text-muted rounded-pill px-3" disabled>
                                            <i class="bi bi-slash-circle me-1"></i> Kosong
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETAIL NILAI -->
<div class="modal fade" id="detailNilaiModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title" id="modalLabel">Rincian Perkiraan Nilai Rapor</h5>
                    <small id="modalSubTitle" class="opacity-75"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <!-- Loading Spinner -->
                <div id="loadingState" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Sedang mengkalkulasi nilai...</p>
                </div>

                <!-- Table Content -->
                <div id="contentTable" class="d-none">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="ps-4" width="5%">No</th>
                                            <th width="30%">Nama Siswa</th>
                                            <th width="15%">NISN</th>
                                            <th class="text-center" width="15%">Jml Asesmen Masuk</th>
                                            <th class="text-center" width="15%">Perkiraan Nilai Akhir</th>
                                            <th class="text-center" width="20%">Status (KKM: <?php echo $kkm; ?>)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalTableBody">
                                        <!-- Data will be injected here via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0 d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Catatan:</strong> Nilai Akhir di atas adalah kalkulasi sementara berdasarkan rata-rata tertimbang (Nilai x Bobot) dari seluruh penilaian Sumatif yang telah dinilai oleh guru. Nilai ini bisa berubah jika guru menambahkan penilaian baru.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailModal = document.getElementById('detailNilaiModal');
    const loadingState = document.getElementById('loadingState');
    const contentTable = document.getElementById('contentTable');
    const tableBody = document.getElementById('modalTableBody');
    const modalSubTitle = document.getElementById('modalSubTitle');

    detailModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const idMapel = button.getAttribute('data-id');
        const namaMapel = button.getAttribute('data-nama');
        const namaGuru = button.getAttribute('data-guru');

        // Update Header Modal
        modalSubTitle.textContent = `${namaMapel} - Pengampu: ${namaGuru}`;
        
        // Show Loading, Hide Table
        loadingState.classList.remove('d-none');
        contentTable.classList.add('d-none');
        tableBody.innerHTML = '';

        // Fetch Data
        const formData = new FormData();
        formData.append('ajax_action', 'get_nilai_mapel');
        formData.append('id_mapel', idMapel);

        fetch('walikelas_proses_rapor.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Hide Loading, Show Table
            loadingState.classList.add('d-none');
            contentTable.classList.remove('d-none');

            if (data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Tidak ada data siswa.</td></tr>';
                return;
            }

            let html = '';
            data.forEach((siswa, index) => {
                let badgeClass = 'bg-secondary';
                let nilaiDisplay = siswa.nilai_akhir;
                let statusIcon = '';

                if (siswa.nilai_akhir === '-' || siswa.nilai_akhir === 0) {
                    nilaiDisplay = '<span class="text-muted fst-italic">Belum ada</span>';
                    badgeClass = 'bg-light text-dark border';
                } else if (siswa.status === 'Tuntas') {
                    badgeClass = 'bg-success';
                    statusIcon = '<i class="bi bi-check-lg me-1"></i>';
                } else {
                    badgeClass = 'bg-danger';
                    statusIcon = '<i class="bi bi-x-lg me-1"></i>';
                }

                html += `
                    <tr>
                        <td class="ps-4">${index + 1}</td>
                        <td class="fw-bold">${siswa.nama}</td>
                        <td>${siswa.nisn}</td>
                        <td class="text-center">${siswa.jml_asesmen_diikuti}</td>
                        <td class="text-center fw-bold fs-5">${nilaiDisplay}</td>
                        <td class="text-center">
                            <span class="badge ${badgeClass}">${statusIcon}${siswa.status}</span>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            loadingState.classList.add('d-none');
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Gagal memuat data. Coba lagi nanti.</td></tr>';
            contentTable.classList.remove('d-none');
        });
    });
});
</script>

<?php include 'footer.php'; ?>