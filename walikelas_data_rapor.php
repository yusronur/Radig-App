<?php
include 'header.php';
include 'koneksi.php';

// Validasi role Wali Kelas atau Admin
if (!in_array($_SESSION['role'], ['guru', 'admin'])) {
    echo "<script>Swal.fire('Akses Ditolak','Anda tidak memiliki wewenang.','error').then(() => window.location = 'dashboard.php');</script>";
    exit;
}

$id_wali_kelas = $_SESSION['id_guru'];

// Ambil info tahun ajaran dan semester aktif
$q_ta = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
$id_tahun_ajaran = mysqli_fetch_assoc($q_ta)['id_tahun_ajaran'];
$q_smt = mysqli_query($koneksi, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'semester_aktif' LIMIT 1");
$semester_aktif = mysqli_fetch_assoc($q_smt)['nilai_pengaturan'];

// Ambil data kelas yang diampu oleh Wali Kelas ini
$q_kelas = mysqli_prepare($koneksi, "SELECT id_kelas, nama_kelas, fase FROM kelas WHERE id_wali_kelas = ? AND id_tahun_ajaran = ?");
mysqli_stmt_bind_param($q_kelas, "ii", $id_wali_kelas, $id_tahun_ajaran);
mysqli_stmt_execute($q_kelas);
$result_kelas = mysqli_stmt_get_result($q_kelas);
$kelas = mysqli_fetch_assoc($result_kelas);
$id_kelas = $kelas['id_kelas'] ?? 0;
$fase_kelas = $kelas['fase'] ?? '';

// Ambil semua siswa di kelas ini
$q_siswa = $id_kelas ? mysqli_query($koneksi, "SELECT id_siswa, nama_lengkap FROM siswa WHERE id_kelas = $id_kelas ORDER BY nama_lengkap ASC") : false;

// Pre-fetch data yang dibutuhkan untuk tampilan awal
// 1. Data Rapor (Absensi & Catatan yang sudah ada)
$data_rapor = [];
if ($id_kelas) {
    $q_rapor = mysqli_query($koneksi, "SELECT id_rapor, id_siswa, sakit, izin, tanpa_keterangan, catatan_wali_kelas FROM rapor WHERE id_kelas = $id_kelas AND semester = $semester_aktif AND id_tahun_ajaran = $id_tahun_ajaran");
    while ($r = mysqli_fetch_assoc($q_rapor)) {
        $data_rapor[$r['id_siswa']] = $r;
    }
}

// 2. Data Ekstrakurikuler Siswa untuk ditampilkan
$data_ekskul_siswa = [];
if ($id_kelas) {
    $query_ekskul = mysqli_prepare($koneksi, "
        SELECT 
            s.id_siswa, e.nama_ekskul,
            (SELECT CONCAT(k.jumlah_hadir, ' / ', k.total_pertemuan) 
             FROM ekskul_kehadiran k 
             WHERE k.id_peserta_ekskul = ep.id_peserta_ekskul AND k.semester = ?) as kehadiran,
            GROUP_CONCAT(CONCAT(t.deskripsi_tujuan, ':', p.nilai) SEPARATOR ';') as penilaian
        FROM siswa s
        JOIN ekskul_peserta ep ON s.id_siswa = ep.id_siswa
        JOIN ekstrakurikuler e ON ep.id_ekskul = e.id_ekskul
        LEFT JOIN ekskul_penilaian p ON ep.id_peserta_ekskul = p.id_peserta_ekskul
        LEFT JOIN ekskul_tujuan t ON p.id_tujuan_ekskul = t.id_tujuan_ekskul AND t.semester = ?
        WHERE s.id_kelas = ? AND e.id_tahun_ajaran = ?
        GROUP BY s.id_siswa, e.id_ekskul
    ");
    mysqli_stmt_bind_param($query_ekskul, "iiii", $semester_aktif, $semester_aktif, $id_kelas, $id_tahun_ajaran);
    mysqli_stmt_execute($query_ekskul);
    $result_ekskul = mysqli_stmt_get_result($query_ekskul);
    while ($ekskul = mysqli_fetch_assoc($result_ekskul)) {
        $data_ekskul_siswa[$ekskul['id_siswa']][] = $ekskul;
    }
}
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        padding: 2.5rem 2rem;
        border-radius: 0.75rem;
        color: white;
    }

    .page-header h1 {
        font-weight: 700;
    }

    .accordion-button:not(.collapsed) {
        background-color: #e0f2f1;
        color: var(--secondary-color);
        box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .125);
    }

    .accordion-button:focus {
        box-shadow: 0 0 0 0.25rem rgba(38, 166, 154, 0.25);
    }

    .info-label {
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        display: block;
    }

    .ekskul-summary-card {
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--secondary-color);
    }

    .guidance-card {
        background-color: var(--bs-info-bg-subtle);
        border-left: 5px solid var(--bs-info);
    }

    .guidance-card ul {
        padding-left: 1.2rem;
    }

    .guidance-card li {
        margin-bottom: 0.5rem;
    }
</style>

<div class="container-fluid">
    <div class="page-header text-white mb-4 shadow">
        <h1 class="mb-1">Input Data Rapor</h1>
        <p class="lead mb-0 opacity-75">
            Kelas: <?php echo htmlspecialchars($kelas['nama_kelas'] ?? 'Anda tidak menjadi wali kelas di tahun ajaran aktif'); ?>
            (Fase <?php echo htmlspecialchars($fase_kelas); ?>)
        </p>
    </div>

    <?php if ($id_kelas && $q_siswa && mysqli_num_rows($q_siswa) > 0) : ?>
        <form action="walikelas_aksi.php?aksi=simpan_data" method="POST">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="card guidance-card mb-4">
                        <div class="card-body">
                            <h5 class="card-title text-info-emphasis"><i class="bi bi-info-circle-fill me-2"></i>Panduan Pengisian Catatan Wali Kelas</h5>
                            <?php if (strtoupper($fase_kelas) == 'A'): ?>
                                <p class="card-text">Untuk siswa <strong>Fase A</strong>, fokuskan catatan pada perkembangan <strong>kemampuan fondasi</strong> mereka. Contoh poin yang bisa disampaikan:</p>
                                <ul>
                                    <li><strong>Keterampilan Sosial & Bahasa:</strong> Bagaimana ananda berinteraksi dengan teman? Apakah sudah bisa mengomunikasikan kebutuhannya?</li>
                                    <li><strong>Kematangan Emosi:</strong> Bagaimana ananda mengelola emosi saat menghadapi kesulitan atau saat bermain bersama teman?</li>
                                    <li><strong>Kemandirian:</strong> Sejauh mana ananda sudah mandiri dalam merawat diri dan peralatannya di sekolah?</li>
                                    <li><strong>Pemaknaan Belajar:</strong> Apakah ananda menunjukkan antusiasme dan rasa ingin tahu dalam kegiatan belajar?</li>
                                    <li>Sertakan juga saran konkret untuk orang tua agar bisa berkolaborasi mendukung perkembangan anak.</li>
                                </ul>
                            <?php else: ?>
                                <p class="card-text">Untuk siswa <strong>Fase B, C, dan D</strong>, fokuskan catatan pada hal-hal berikut:</p>
                                <ul>
                                    <li><strong>Perkembangan Akademik Menonjol:</strong> Sebutkan kekuatan spesifik siswa pada beberapa mata pelajaran atau keterampilan yang paling menonjol.</li>
                                    <li><strong>Aspek yang Perlu Ditingkatkan:</strong> Berikan masukan konstruktif tentang area yang masih perlu dikembangkan, baik akademik maupun non-akademik.</li>
                                    <li><strong>Perkembangan Karakter (Profil Lulusan):</strong> Ceritakan perkembangan karakter siswa, misalnya dalam hal gotong royong, kemandirian, atau kreativitas selama pembelajaran.</li>
                                    <li><strong>Saran Tindak Lanjut:</strong> Berikan saran yang bisa dilakukan siswa dan orang tua untuk meningkatkan prestasi dan potensi di semester berikutnya.</li>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="accordion" id="accordionSiswa">
                        <?php $no = 1;
                        mysqli_data_seek($q_siswa, 0);
                        while ($siswa = mysqli_fetch_assoc($q_siswa)) :
                            $id_siswa = $siswa['id_siswa'];
                            $rapor_siswa = $data_rapor[$id_siswa] ?? null;
                            $ekskul_siswa = $data_ekskul_siswa[$id_siswa] ?? [];
                        ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading-<?php echo $id_siswa; ?>">
                                    <button class="accordion-button <?php echo $no > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $id_siswa; ?>">
                                        <span class="badge bg-primary me-3"><?php echo $no; ?></span>
                                        <span class="fw-bold"><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></span>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo $id_siswa; ?>" class="accordion-collapse collapse <?php echo $no == 1 ? 'show' : ''; ?>" data-bs-parent="#accordionSiswa">
                                    <div class="accordion-body bg-light">
                                        <div class="row g-4">
                                            <div class="col-lg-6">
                                                <div class="mb-4">
                                                    <span class="info-label"><i class="bi bi-calendar-check me-2"></i>Absensi (Jumlah Hari)</span>
                                                    <div class="row g-3">
                                                        <div class="col">
                                                            <div class="input-group"><span class="input-group-text">Sakit</span><input type="number" min="0" name="absensi[<?php echo $id_siswa; ?>][sakit]" class="form-control" value="<?php echo $rapor_siswa['sakit'] ?? 0; ?>"></div>
                                                        </div>
                                                        <div class="col">
                                                            <div class="input-group"><span class="input-group-text">Izin</span><input type="number" min="0" name="absensi[<?php echo $id_siswa; ?>][izin]" class="form-control" value="<?php echo $rapor_siswa['izin'] ?? 0; ?>"></div>
                                                        </div>
                                                        <div class="col">
                                                            <div class="input-group"><span class="input-group-text">Alpha</span><input type="number" min="0" name="absensi[<?php echo $id_siswa; ?>][tanpa_keterangan]" class="form-control" value="<?php echo $rapor_siswa['tanpa_keterangan'] ?? 0; ?>"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="info-label mb-0"><i class="bi bi-chat-left-text me-2"></i>Catatan Wali Kelas</span>
                                                        <button type="button" class="btn btn-info btn-sm generate-note-btn"
                                                            data-siswa-id="<?php echo $id_siswa; ?>">
                                                            <i class="bi bi-magic me-1"></i> Buat Otomatis
                                                        </button>
                                                    </div>
                                                    <textarea name="catatan[<?php echo $id_siswa; ?>]"
                                                        id="catatan-<?php echo $id_siswa; ?>"
                                                        class="form-control"
                                                        rows="8"
                                                        placeholder="Klik tombol 'Buat Otomatis' atau isi manual..."><?php echo htmlspecialchars($rapor_siswa['catatan_wali_kelas'] ?? ''); ?></textarea>
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <span class="info-label"><i class="bi bi-bicycle me-2"></i>Rangkuman Ekstrakurikuler</span>
                                                <?php if (!empty($ekskul_siswa)): ?>
                                                    <div class="d-flex flex-column" style="gap: 1rem;">
                                                        <?php foreach ($ekskul_siswa as $ekskul): ?>
                                                            <div class="p-3 rounded ekskul-summary-card">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($ekskul['nama_ekskul']); ?></h6>
                                                                    <span class="badge bg-secondary">Kehadiran: <?php echo htmlspecialchars($ekskul['kehadiran'] ?? 'N/A'); ?></span>
                                                                </div>
                                                                <div class="ps-2 small text-muted">
                                                                    <?php
                                                                    if (!empty($ekskul['penilaian'])) {
                                                                        $penilaian_list = explode(';', $ekskul['penilaian']);
                                                                        foreach ($penilaian_list as $item) {
                                                                            list($tujuan, $nilai) = array_pad(explode(':', $item, 2), 2, '');
                                                                            if (!empty($tujuan)) {
                                                                                echo '<div>' . htmlspecialchars($tujuan) . ': <strong>' . htmlspecialchars($nilai) . '</strong></div>';
                                                                            }
                                                                        }
                                                                    } else {
                                                                        echo "<em>Belum ada penilaian capaian.</em>";
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-light text-center">Siswa tidak terdaftar pada ekstrakurikuler apapun semester ini.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php $no++;
                        endwhile; ?>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex justify-content-end">
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-floppy-fill me-2"></i> Simpan Semua Perubahan</button>
            </div>
        </form>
    <?php elseif (!$id_kelas): ?>
        <div class="card shadow-sm text-center py-5">
            <div class="card-body"><i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                <h3 class="mt-3">Akses Ditolak</h3>
                <p class="text-muted">Anda tidak terdaftar sebagai wali kelas pada tahun ajaran aktif ini.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm text-center py-5">
            <div class="card-body"><i class="bi bi-people-fill fs-1 text-muted"></i>
                <h3 class="mt-3">Kelas Kosong</h3>
                <p class="text-muted">Belum ada data siswa yang ditambahkan ke kelas ini.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function() {
        // Validasi input absensi
        $('input[name^="absensi"]').on('change', function(e) {
            e.preventDefault();

            let regex = /^(?:0|[1-9]\d*)(?:\.\d+)?$/;
            const val = $(this).val();
            if (val === '' || !regex.test(val)) {
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-tooltip">Harap masukkan angka valid untuk absensi.</div>');
                $(this).focus();
                return;
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        $('#accordionSiswa').on('hide.bs.collapse', function(e) {
            if ($(e.target).find('.is-invalid').length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Masih Ada Kesalahan',
                    text: 'Perbaiki input yang tidak valid sebelum menutup panel ini.',
                    confirmButtonText: 'Oke'
                });
            }
        }).on('show.bs.collapse', function(e) {
            const opened = $(this).find('.accordion-collapse.show');
            if (opened.length > 0 && opened.find('.is-invalid').length > 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Panel Sebelumnya Belum Valid',
                    text: 'Perbaiki data pada panel sebelumnya sebelum membuka siswa lain.',
                    confirmButtonText: 'Oke'
                });
            }
        });

        $('.generate-note-btn').on('click', function(e) {
            e.preventDefault();

            var button = $(this);
            var siswaId = button.data('siswa-id');

            // Ambil nilai absensi terbaru dari input form
            var sakit = $('input[name="absensi[' + siswaId + '][sakit]"]').val();
            var izin = $('input[name="absensi[' + siswaId + '][izin]"]').val();
            var alpha = $('input[name="absensi[' + siswaId + '][tanpa_keterangan]"]').val();

            var targetTextarea = $('#catatan-' + siswaId);

            // Simpan teks asli tombol & nonaktifkan
            var originalButtonText = button.html();
            button.html('<i class="spinner-border spinner-border-sm"></i> Memproses...').prop('disabled', true);

            $.ajax({
                url: 'ajax_generate_catatan.php',
                type: 'POST',
                data: {
                    id_siswa: siswaId,
                    sakit: sakit,
                    izin: izin,
                    alpha: alpha
                },
                success: function(response) {
                    // Masukkan hasil ke textarea
                    targetTextarea.val($('<div/>').html(response).text()); // Membersihkan tag HTML jika ada
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Catatan berhasil dibuat!',
                        showConfirmButton: false,
                        timer: 2000
                    });
                },
                error: function() {
                    Swal.fire('Error', 'Gagal membuat catatan. Silakan coba lagi.', 'error');
                },
                complete: function() {
                    // Kembalikan tombol ke keadaan semula
                    button.html(originalButtonText).prop('disabled', false);
                }
            });
        });
    });
</script>

<?php
if (isset($_SESSION['pesan'])) {
    echo "<script>Swal.fire('Berhasil!','" . addslashes($_SESSION['pesan']) . "','success');</script>";
    unset($_SESSION['pesan']);
}
include 'footer.php';
?>